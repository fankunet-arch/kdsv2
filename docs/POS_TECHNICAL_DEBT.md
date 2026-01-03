# POS系统技术债文档

**文档版本**: 1.0.0
**创建日期**: 2026-01-03
**最后更新**: 2026-01-03
**维护者**: Development Team

## 文档说明

本文档记录POS系统中已知的技术债项目。这些问题已被识别但由于业务、技术或资源限制暂时无法解决。所有技术债项目应定期审查，并在条件允许时逐步解决。

---

## 高优先级技术债

### TD-POS-001: SHA256密码哈希算法

**状态**: 🔴 待解决
**优先级**: 高
**影响范围**: 用户认证系统
**发现日期**: 2026-01-03

#### 问题描述

POS系统当前使用SHA256算法对用户密码进行哈希存储。SHA256是快速哈希算法，不适合密码存储，容易受到暴力破解和彩虹表攻击。

**当前实现位置**:
- `store/store_html/html/pos/api/pos_login_handler.php:85`

```php
// 当前实现（不安全）
$stmt_user = $pdo->prepare("SELECT * FROM kds_users WHERE username = ? AND password_hash = SHA2(?, 256)");
```

#### 安全风险

1. **暴力破解**: SHA256执行速度快，攻击者可以每秒尝试数十亿次密码
2. **彩虹表攻击**: 预计算的SHA256哈希表可以快速反向查找密码
3. **缺少盐值**: 相同密码会产生相同哈希，暴露密码重用模式
4. **不符合最佳实践**: OWASP和NIST都不推荐使用快速哈希算法存储密码

#### 推荐解决方案

使用PHP内置的`password_hash()`和`password_verify()`函数，默认使用bcrypt算法：

```php
// 注册时
$password_hash = password_hash($password, PASSWORD_DEFAULT);
INSERT INTO kds_users (username, password_hash) VALUES (?, ?);

// 登录时
SELECT id, password_hash, ... FROM kds_users WHERE username = ?;
if (password_verify($input_password, $row['password_hash'])) {
    // 密码正确
}
```

#### 迁移策略

1. **添加新列**: 在`kds_users`表添加`password_hash_new`列
2. **双重验证**: 登录时先验证新哈希，失败则验证旧SHA256哈希
3. **透明升级**: 旧哈希验证成功后，自动更新为新哈希
4. **逐步淘汰**: 6个月后所有活跃用户已升级，移除旧列

#### 阻塞原因

**用户决策**: 用户在2026-01-03的审计报告审查中明确要求："密码加密的技术债写入报告中，本次修复不变动。"

#### 估算工作量

- 数据库迁移: 2小时
- 代码修改: 4小时
- 测试验证: 4小时
- **总计**: 10小时（1.25个工作日）

#### 相关文档

- OWASP Password Storage Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html
- PHP password_hash() 文档: https://www.php.net/manual/en/function.password-hash.php

---

### TD-POS-002: pos_cash_movements表缺失

**状态**: 🔴 待解决
**优先级**: 高
**影响范围**: 现金管理功能
**发现日期**: 2026-01-03

#### 问题描述

代码中引用了`pos_cash_movements`表用于记录现金流动（如找零、取现等），但该表在数据库中不存在。导致相关功能被硬编码为0值。

**受影响代码位置**:
- `store/store_html/pos_backend/services/ShiftService.php`
- EOD报告生成逻辑
- 班次交接逻辑

**当前临时方案**:
```php
// 硬编码为0，因为表不存在
$cash_movements = 0;
```

#### 业务影响

1. **功能不完整**: 无法记录和追踪现金流动
2. **审计困难**: 缺少现金操作审计日志
3. **数据不准确**: 班次报告中现金流动始终为0

#### 推荐解决方案

创建`pos_cash_movements`表：

```sql
CREATE TABLE `pos_cash_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `store_id` INT UNSIGNED NOT NULL COMMENT 'FK: kds_stores.id',
  `shift_id` BIGINT UNSIGNED NULL COMMENT 'FK: pos_shifts.id (NULL if outside shift)',
  `user_id` INT UNSIGNED NOT NULL COMMENT 'FK: kds_users.id',
  `movement_type` ENUM('ADD', 'REMOVE', 'ADJUST') NOT NULL COMMENT 'Movement type',
  `amount` DECIMAL(10,2) NOT NULL COMMENT 'Amount (positive for ADD, negative for REMOVE)',
  `reason` VARCHAR(255) NOT NULL COMMENT 'Reason for movement',
  `notes` TEXT NULL COMMENT 'Additional notes',
  `created_at` DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)),

  INDEX `idx_store_shift` (`store_id`, `shift_id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_created_at` (`created_at`),

  FOREIGN KEY (`store_id`) REFERENCES `kds_stores`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`shift_id`) REFERENCES `pos_shifts`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `kds_users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='POS cash movements (add/remove/adjust)';
```

#### 迁移路径

1. 创建表结构
2. 在ShiftService中实现记录逻辑
3. 更新EOD和班次报告以包含真实数据
4. 添加前端UI支持现金操作

#### 估算工作量

- 数据库设计: 2小时
- 后端实现: 8小时
- 前端UI: 6小时
- 测试: 4小时
- **总计**: 20小时（2.5个工作日）

---

## 中优先级技术债

### TD-POS-003: index.php单体文件（54KB）

**状态**: 🟡 部分解决（Phase 6计划中）
**优先级**: 中
**影响范围**: 前端维护性
**发现日期**: 2026-01-03

#### 问题描述

`store/store_html/html/pos/index.php`文件包含所有HTML结构（~1500行，54KB），导致：
- 难以维护和导航
- Git合并冲突频繁
- 加载时间较长
- 代码可读性差

#### 推荐解决方案

拆分为模块化组件：
- `views/header.php` - 导航栏和顶部
- `views/search.php` - 搜索框
- `views/category_tabs.php` - 分类标签
- `views/product_grid.php` - 产品网格
- `views/cart_offcanvas.php` - 购物车侧边栏
- `views/modals.php` - 所有模态框
- `index.php` - 主文件（仅组装各部分）

#### 估算工作量

- 代码拆分: 6小时
- 测试验证: 2小时
- **总计**: 8小时（1个工作日）

**注**: 此项将在Phase 6中解决

---

### TD-POS-004: 内联JavaScript代码

**状态**: 🟡 部分解决（Phase 6计划中）
**优先级**: 中
**影响范围**: 前端维护性、CSP安全
**发现日期**: 2026-01-03

#### 问题描述

index.php中包含大量内联JavaScript代码（~200行），违反内容安全策略（CSP）最佳实践。

**当前位置**:
- Modal初始化代码
- 事件监听器
- 配置变量注入

#### 推荐解决方案

1. 提取到`assets/js/init.js`
2. 使用data属性传递配置
3. 启用严格的CSP策略

#### 估算工作量

- 代码提取: 4小时
- CSP配置: 2小时
- **总计**: 6小时（0.75个工作日）

**注**: 此项将在Phase 6中解决

---

## 低优先级技术债

### TD-POS-005: calculate_eod_totals()函数已废弃

**状态**: ✅ 已解决
**优先级**: 低
**解决日期**: 2026-01-03

#### 问题描述

`calculate_eod_totals()`函数引用不存在的`pos_invoice_payments`表，已被`pos_repo::getInvoiceSummaryForPeriod()`替代。

#### 解决方案

函数已在Phase 4中完全删除。

**提交记录**: `81258f4 - POS安全重构阶段4：代码清理 - 删除废弃函数`

---

### TD-POS-006: 重复的session_start()调用

**状态**: ✅ 已解决
**优先级**: 低
**解决日期**: 2026-01-03

#### 问题描述

12处分散的`session_start()`调用，缺少统一管理，安全配置不一致。

#### 解决方案

创建`SessionManager`类统一管理所有Session操作，已在Phase 2中完成。

**提交记录**: `10b6baa - POS安全重构阶段2：统一Session管理`

---

### TD-POS-007: 硬编码数据库凭据

**状态**: ✅ 已解决
**优先级**: 严重 → 已解决
**解决日期**: 2026-01-03

#### 问题描述

数据库密码硬编码在`config.php`中，严重安全风险。

#### 解决方案

创建`.env.pos`环境变量文件，使用`DotEnv`类加载配置，已在Phase 1中完成。

**提交记录**: `996d9ec - POS安全重构阶段1（部分）：移除硬编码凭据和添加CSRF保护`

---

### TD-POS-008: 缺少CSRF保护

**状态**: ✅ 已解决
**优先级**: 严重 → 已解决
**解决日期**: 2026-01-03

#### 问题描述

所有POST请求缺少CSRF令牌验证，易受跨站请求伪造攻击。

#### 解决方案

创建`CSRFHelper`类，在所有表单和API请求中实现CSRF保护，已在Phase 1中完成。

**提交记录**: `2da8771 - POS安全重构阶段1：完成全系统CSRF保护`

---

## 技术债统计

| 状态 | 数量 | 占比 |
|------|------|------|
| 🔴 待解决 | 2 | 25% |
| 🟡 进行中 | 2 | 25% |
| ✅ 已解决 | 4 | 50% |
| **总计** | **8** | **100%** |

### 按优先级分类

| 优先级 | 待解决 | 进行中 | 已解决 | 总计 |
|--------|--------|--------|--------|------|
| 严重   | 0      | 0      | 2      | 2    |
| 高     | 2      | 0      | 0      | 2    |
| 中     | 0      | 2      | 0      | 2    |
| 低     | 0      | 0      | 2      | 2    |

---

## 解决时间线

### 2026-01-03（Phase 1-4）

- ✅ TD-POS-007: 硬编码数据库凭据
- ✅ TD-POS-008: 缺少CSRF保护
- ✅ TD-POS-006: 重复的session_start()调用
- ✅ TD-POS-005: calculate_eod_totals()函数已废弃

### 2026-01-03（Phase 5-6计划）

- 🟡 TD-POS-003: index.php单体文件
- 🟡 TD-POS-004: 内联JavaScript代码

### 待计划

- 🔴 TD-POS-001: SHA256密码哈希算法
- 🔴 TD-POS-002: pos_cash_movements表缺失

---

## 审查流程

本文档应在以下情况下审查和更新：

1. **季度审查**: 每季度审查所有未解决的技术债
2. **新发现**: 发现新技术债时立即添加
3. **状态变更**: 技术债状态变化时更新
4. **优先级调整**: 业务需求变化导致优先级调整

---

## 联系信息

如对本文档有任何疑问或建议，请联系开发团队。

**文档维护者**: Development Team
**最后审查**: 2026-01-03
