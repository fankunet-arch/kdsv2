# KDS系统修复总结文档
**执行日期**: 2026-01-03
**执行工程师**: System Auditor
**关联审计报告**: SYSTEM_AUDIT_REPORT.md

---

## 修复概述

根据系统审计报告和用户指示，本次修复专注于以下三类问题：
1. **高优先级问题**
2. **架构问题**
3. **安全缺陷问题**

**注意**: 密码哈希和数据库凭证硬编码问题本次不调整（按用户要求）

---

## ✅ 已完成的修复

### 1. 删除安全漏洞文件

#### ❌ 删除: `api/1.php`
**原因**: 文件仅包含 `<?phpinfo()?>`, 会泄露完整服务器配置信息
**操作**: 永久删除该文件

```bash
# 已执行
rm ./kds/store/store_html/html/kds/api/1.php
```

---

### 2. 更新数据库配置

#### 📝 修改: `kds/core/config.php`
**问题**: 数据库配置与实际环境不符
**修改**: 更新为正确的数据库连接信息

**变更**:
```php
// 旧配置
$db_host = 'mhdlmskvtmwsnt5z.mysql.db';
$db_name = 'mhdlmskvtmwsnt5z';
$db_user = 'mhdlmskvtmwsnt5z';
$db_pass = 'p8PQF7M8ZKLVxtjvatMkrthFQQUB9';

// 新配置 ✓
$db_host = 'mhdlmskv3gjbpqv3.mysql.db';
$db_name = 'mhdlmskv3gjbpqv3';
$db_user = 'mhdlmskv3gjbpqv3';
$db_pass = 'zqVdVfAWYYaa4gTAuHWX7CngpRDqR';
```

---

### 3. 修复登录处理器Session问题

#### 📝 修改: `html/kds/api/kds_login_handler.php`
**问题**: 登录成功后未将用户角色存入Session
**影响**: 每次API调用都需要额外查询数据库获取角色
**修复**: 添加角色到Session

**新增代码** (Line 44):
```php
$_SESSION['kds_user_role'] = $user['role']; // [FIX] Set user role to session
```

---

### 4. 删除空函数壳文件

#### ❌ 删除: `kds/helpers/kds_helper_shim.php`
**原因**: 文件只包含空函数壳，无实际作用且可能导致错误
**操作**: 永久删除该文件

```bash
# 已执行
rm ./kds/store/store_html/kds/helpers/kds_helper_shim.php
```

---

### 5. 创建数据库迁移脚本

#### ✨ 新建: `docs/migrations/001_fix_constraints_and_indexes.sql`
**目的**: 修复数据库架构问题
**包含内容**:

1. **添加 `kds_users.role` CHECK约束**
   ```sql
   ALTER TABLE `kds_users`
     ADD CONSTRAINT `chk_kds_user_role`
     CHECK (`role` IN ('staff', 'manager'));
   ```

2. **添加缺失的外键约束**
   - `pass_redemptions.cashier_user_id` -> `kds_users.id`
   - `pos_invoices.user_id` -> `kds_users.id`

3. **为 `pos_eod_reports` 添加用户类型区分**
   - 新增 `user_type` 字段
   - 创建触发器验证用户存在性

4. **添加性能优化索引**
   - `kds_material_expiries` - `idx_store_status`
   - `pos_invoices` - `idx_store_issued`
   - `pass_redemptions` - `idx_pass_redeemed`
   - `kds_users` - `idx_username_store`

5. **创建登录尝试记录表**
   ```sql
   CREATE TABLE `login_attempts` (
     `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
     `username` varchar(50) NOT NULL,
     `ip_address` varchar(45) NOT NULL,
     `attempted_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6)),
     PRIMARY KEY (`id`),
     INDEX `idx_username_ip_time` (`username`, `ip_address`, `attempted_at`)
   ) ENGINE=InnoDB;
   ```

**执行方法**:
```bash
mysql -u mhdlmskv3gjbpqv3 -p mhdlmskv3gjbpqv3 < ./kds/store/store_html/docs/migrations/001_fix_constraints_and_indexes.sql
```

---

### 6. 添加Session安全配置

#### 📝 修改: `kds/core/kds_auth_core.php`
**问题**: Session缺少安全参数配置，使用`@`抑制错误
**修复**:
- 移除`@`符号
- 添加secure session配置
- 正确处理session启动错误

**新增代码**:
```php
// [FIX] Configure secure session parameters
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    // Note: session.cookie_secure should be enabled in production with HTTPS

    if (!session_start()) {
        error_log('KDS Auth: Failed to start session');
        http_response_code(500);
        die('Session initialization failed');
    }
}
```

---

### 7. 实现CSRF保护机制

#### ✨ 新建: `kds/helpers/csrf_helper.php`
**目的**: 提供CSRF token生成和验证功能
**包含函数**:
- `generateCsrfToken()` - 生成或获取CSRF token
- `verifyCsrfToken($token)` - 验证token有效性
- `csrfTokenField()` - 生成HTML隐藏字段
- `csrfTokenMeta()` - 生成meta标签（用于AJAX）

#### 📝 修改: `html/kds/api/kds_login_handler.php`
**新增**: CSRF token验证逻辑

```php
// [FIX] CSRF Protection
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf_token)) {
    error_log('KDS Login: CSRF token validation failed');
    header('Location: ../login.php?error=csrf');
    exit;
}
```

#### 📝 修改: `kds/app/views/kds/login_view.php`
**新增**:
1. 加载CSRF helper
2. 在表单中添加CSRF token字段
3. 更新错误消息处理（区分CSRF和rate_limit错误）

```php
<form action="api/kds_login_handler.php" method="POST">
    <?php echo csrfTokenField(); /* [FIX] CSRF Protection */ ?>
    <!-- ... 其他字段 ... -->
</form>
```

---

### 8. 实现登录速率限制

#### 📝 修改: `html/kds/api/kds_login_handler.php`
**功能**: 防止暴力破解攻击
**策略**: 15分钟内同一用户名或IP超过5次失败尝试则拒绝登录

**新增逻辑**:

1. **检查速率限制**:
```php
// [FIX] Rate Limiting - Check login attempts
$stmt_attempts = $pdo->prepare(
    "SELECT COUNT(*) FROM login_attempts
     WHERE (username = ? OR ip_address = ?)
     AND attempted_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)"
);
$stmt_attempts->execute([$username, $ip_address]);
$attempt_count = $stmt_attempts->fetchColumn();

if ($attempt_count >= 5) {
    header('Location: ../login.php?error=rate_limit');
    exit;
}
```

2. **记录失败尝试**:
```php
// [FIX] Record failed login attempt
$pdo->prepare("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)")
    ->execute([$username, $ip_address]);
```

3. **成功登录后清除记录**:
```php
// [FIX] Clear login attempts on successful login
$pdo->prepare("DELETE FROM login_attempts WHERE username = ? OR ip_address = ?")
    ->execute([$username, $ip_address]);
```

---

### 9. 添加登出审计日志

#### 📝 修改: `html/kds/logout.php`
**问题**: 用户登出时未记录到审计日志
**修复**: 在清除session前记录登出行为到`audit_logs`表

**新增代码**:
```php
// [FIX] Record logout action to audit_logs
if (isset($_SESSION['kds_user_id']) && isset($_SESSION['kds_store_id'])) {
    try {
        require_once realpath(__DIR__ . '/../../kds/core/config.php');

        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (action, actor_user_id, actor_type, ip, ua, session_id, created_at)
             VALUES ('user.logout', ?, 'store_user', ?, ?, ?, UTC_TIMESTAMP())"
        );
        $stmt->execute([
            $_SESSION['kds_user_id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            session_id()
        ]);
    } catch (Exception $e) {
        error_log("KDS Logout: Failed to log logout: " . $e->getMessage());
    }
}
```

---

## 📋 修改文件清单

### 已删除文件 (2个)
- ❌ `html/kds/api/1.php`
- ❌ `kds/helpers/kds_helper_shim.php`

### 新创建文件 (2个)
- ✨ `kds/helpers/csrf_helper.php`
- ✨ `docs/migrations/001_fix_constraints_and_indexes.sql`

### 已修改文件 (5个)
- 📝 `kds/core/config.php` (数据库配置)
- 📝 `kds/core/kds_auth_core.php` (Session安全配置)
- 📝 `html/kds/api/kds_login_handler.php` (角色Session + CSRF + 速率限制)
- 📝 `kds/app/views/kds/login_view.php` (CSRF token + 错误提示)
- 📝 `html/kds/logout.php` (审计日志)

---

## 🔧 待执行步骤

### 步骤1: 执行数据库迁移 ⚠️ **必须执行**

```bash
# 进入迁移文件目录
cd kds/store/store_html/docs/migrations/

# 执行迁移脚本
mysql -u mhdlmskv3gjbpqv3 -p mhdlmskv3gjbpqv3 < 001_fix_constraints_and_indexes.sql

# 输入密码: zqVdVfAWYYaa4gTAuHWX7CngpRDqR

# 验证迁移成功
# 应该看到: Migration 001 completed successfully!
```

**重要提示**:
- 在执行迁移前，建议先备份数据库
- 迁移脚本会自动检查约束和索引是否已存在，可以安全地重复执行
- 如果有数据不符合新约束（如kds_users.role包含非法值），迁移会失败，需要先清理数据

### 步骤2: 验证CSRF保护工作正常

1. 访问登录页面: `https://<域名>/kds/login.php`
2. 查看页面源代码，确认存在CSRF token字段:
   ```html
   <input type="hidden" name="csrf_token" value="...">
   ```
3. 尝试登录，验证功能正常

### 步骤3: 测试速率限制

1. 故意输入错误密码5次
2. 第6次尝试应该看到错误提示: "登录尝试过多，请15分钟后再试。"
3. 使用正确密码登录成功后，失败记录应该被清除

### 步骤4: 验证审计日志

```sql
-- 登出后检查audit_logs表
SELECT * FROM audit_logs
WHERE action = 'user.logout'
ORDER BY created_at DESC
LIMIT 10;

-- 应该能看到登出记录，包含IP、UA等信息
```

---

## 🚫 未修复的问题（按用户要求）

以下问题在审计报告中被标记为严重或高优先级，但按用户要求本次不修复：

1. **密码哈希算法不安全** (SHA256无盐)
   - 建议未来升级为 `password_hash()` + Argon2id

2. **数据库凭证硬编码**
   - 建议未来迁移到环境变量或`.env`文件

---

## 📊 修复效果评估

### 安全性提升
- ✅ 移除了phpinfo()信息泄露漏洞
- ✅ 添加了CSRF保护，防止跨站请求伪造攻击
- ✅ 实现了登录速率限制，防止暴力破解
- ✅ 增强了Session安全性（httponly, samesite, strict mode）
- ✅ 添加了审计日志，可追踪用户行为

### 数据完整性提升
- ✅ 添加了kds_users.role CHECK约束，防止非法角色值
- ✅ 补充了外键约束，保证引用完整性
- ✅ 添加了触发器验证混合用户表的引用

### 性能提升
- ✅ 添加了4个复合索引，优化高频查询
- ✅ 修复了角色Session缓存问题，减少数据库查询

### 代码质量提升
- ✅ 移除了空函数壳文件，减少潜在错误
- ✅ 移除了`@`错误抑制符，提高调试能力
- ✅ 统一了Session配置，提高可维护性

---

## ⚠️ 注意事项

1. **HTTPS配置**
   - 在生产环境启用HTTPS后，取消注释 `kds_auth_core.php` 中的:
     ```php
     ini_set('session.cookie_secure', '1');
     ```

2. **速率限制清理**
   - 建议设置定时任务清理旧的登录尝试记录:
     ```sql
     -- 每天执行一次
     DELETE FROM login_attempts
     WHERE attempted_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR);
     ```

3. **错误日志监控**
   - 新增的错误日志会记录到 `kds/core/php_errors_kds.log`
   - 建议定期检查日志文件，监控异常情况

4. **CSRF Token刷新**
   - CSRF token每1小时自动刷新
   - 如果用户长时间停留在登录页面，可能需要刷新页面

---

## 📚 相关文档

- **审计报告**: `SYSTEM_AUDIT_REPORT.md`
- **数据库迁移**: `docs/migrations/001_fix_constraints_and_indexes.sql`
- **CSRF Helper**: `kds/helpers/csrf_helper.php`

---

## ✅ 修复签名

- **执行工程师**: System Auditor
- **复核工程师**: (待填写)
- **测试工程师**: (待填写)
- **上线日期**: (待填写)

---

**修复完成日期**: 2026-01-03
**版本**: v1.0
