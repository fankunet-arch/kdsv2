# TopTea KDS & POS 系统全面审计报告
**日期:** 2026-01-03
**审计员:** Jules (资深PHP系统审计师)

## 1. 架构错误 (严重)

### 1.1 KDS API Gateway 缺失
- **严重程度:** **严重** (系统不可用)
- **描述:** 代码库中完全缺失 `public/kds/api/kds_api_gateway.php` 文件及其注册表 `public/kds/api/registries/kds_registry.php`。
- **影响:** KDS 前端除了登录 (`kds_login_handler.php`) 和获取图片 (`get_image.php`) 外，无法与后端进行任何通信。所有业务 API 调用（如获取订单、SOP）都将返回 **404 Not Found**。
- **证据:** `ls public/kds/api/` 仅显示 `get_image.php` 和 `kds_login_handler.php`。
- **建议:** 立即恢复 KDS API Gateway 和注册表文件。

### 1.2 KDS 逻辑代码孤立
- **严重程度:** 高
- **描述:** `src/kds/Helpers/KdsRepository.php` 包含迁移后的业务逻辑（例如 `KdsSopParser`），但由于缺少 API 网关调用它，这部分代码目前处于**不可达**状态（死代码）。

## 2. 函数错误与遗漏

### 2.1 POS 现金流向逻辑不匹配
- **严重程度:** 中
- **描述:** 在 `src/pos/Helpers/pos_repo.php`（`compute_expected_cash` 函数）中，代码明确将 `cash_in`、`cash_out` 和 `cash_refunds` 硬编码为 `0.0`，并注释称该表不存在。然而，迁移脚本 `001_system_fixes_and_optimizations.sql` **确实创建**了 `pos_cash_movements` 表。
- **影响:** 即使数据库支持，POS 中的现金追踪功能也无法工作。
- **建议:** 更新 `pos_repo.php` 以查询 `pos_cash_movements` 表。

### 2.2 POS Json Helper 冗余
- **严重程度:** 低 (维护性)
- **描述:** POS 使用带有全局函数（`json_ok`, `json_error`）的 `src/pos/Helpers/pos_json_helper.php`，而 KDS 使用规范的类 `TopTea\KDS\Helpers\JsonHelper`。这导致了编码标准不一致。
- **建议:** 重构 POS 以使用 `TopTea\POS\Helpers\JsonHelper` 类以保持一致性。

## 3. 注册表与代码冗余

### 3.1 核心类重复
- **严重程度:** 中
- **描述:** 以下核心类在 `src/kds/Core` 和 `src/pos/Core` 中完全重复：
    - `SessionManager`
    - `Logger`
    - `ErrorHandler`
    - `Autoloader`
    - `DotEnv` (在 Config 中)
- **影响:** 双重维护成本。KDS Core 中的修复不会自动应用于 POS Core。
- **建议:** 将共享的核心逻辑移动到公共的 `src/Shared/Core` 命名空间。

## 4. 实现错误 (运行时发现)

### 4.1 KDS 登录视图路径错误 (阻塞性)
- **严重程度:** 高 (阻塞)
- **描述:** `src/kds/Views/pages/login_view.php` 尝试 `require_once` 路径为 `../../../helpers/csrf_helper.php` 的文件。该路径不正确（小写的 `helpers`），且文件应为 `src/kds/Helpers/CsrfHelper.php`（基于类的文件）。
- **影响:** KDS 登录页面抛出致命错误 (500) 且无法加载。**见截图 `kds_login_result.png`。**
- **修复方案:** 更新 `src/kds/Views/pages/login_view.php` 以引入 `__DIR__ . '/../../Helpers/CsrfHelper.php'` 并使用 `CsrfHelper` 类方法，而非已废弃的全局函数（或者确保加载了全局别名）。

### 4.2 POS 配置命名空间不匹配 (阻塞性)
- **严重程度:** 高 (阻塞)
- **描述:** `src/pos/Config/config.php` 尝试使用 `TopTea\POS\Core\Logger` 和 `TopTea\POS\Core\ErrorHandler`，但这些类定义在 `TopTea\POS\Helpers` 命名空间中。
- **影响:** POS 系统在启动时抛出致命错误 (500)。**见截图 `pos_login_result.png`。**
- **修复方案:** 更新 `src/pos/Config/config.php` 以使用正确的命名空间：`use TopTea\POS\Helpers\Logger;` 和 `use TopTea\POS\Helpers\ErrorHandler;`。

## 5. 安全与最佳实践 (已验证)

- **登录处理器:** KDS 和 POS 登录处理器均正确实现了：
    - **CSRF 防护:** 已验证。
    - **速率限制:** 已验证 (15分钟内5次尝试)。
    - **安全会话:** 已验证 (`SessionManager::init()` 使用了 `httponly`, `samesite`)。
- **输入验证:** KDS 使用 `InputValidator` 类；POS 使用内联检查。风格一致但实现不同。

## 6. 环境验证状态

由于“禁止修改代码”的限制，在验证问题后，源代码已回退到原始状态。
- **KDS 登录:** 由于问题 4.1 失败 (500 错误)。
- **POS 登录:** 由于问题 4.2 失败 (500 错误)。
- **截图:** 提供的截图 (`kds_login_result.png`, `pos_login_result.png`) 展示了由这些 Bug 导致的 500 错误页面，证实了审计发现。

---
**审计报告结束**
