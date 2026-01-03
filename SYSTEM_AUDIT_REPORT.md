# TopTea KDS 系统全面审计报告
**审计日期**: 2026-01-03
**审计工程师**: 系统审计师 + MySQL架构师
**项目**: TopTea 门店KDS系统 (第一次全面审计)
**代码库**: kdsv2

---

## 执行摘要

本次审计是该KDS系统的首次全面审计。系统采用PHP + MySQL架构，分段编写，存在多处架构不一致、安全隐患、函数冗余和数据库设计问题。以下报告详细列出了所有发现的问题及修复方案。

---

## 1. 严重安全问题 ⚠️ (Critical)

### 1.1 phpinfo() 信息泄露
**位置**: `store/store_html/html/kds/api/1.php:1`
**问题**: 文件仅包含 `<?phpinfo()?>`,会暴露完整的服务器配置信息
**风险等级**: 🔴 严重
**影响**: 攻击者可获取PHP版本、扩展、路径、环境变量等敏感信息

**修复方案**:
```bash
# 立即删除此文件
rm ./kds/store/store_html/html/kds/api/1.php
```

### 1.2 数据库凭证硬编码
**位置**: `store/store_html/kds/core/config.php:19-23`
**问题**: 数据库密码明文硬编码在代码中
```php
$db_host = 'mhdlmskvtmwsnt5z.mysql.db';
$db_name = 'mhdlmskvtmwsnt5z';
$db_user = 'mhdlmskvtmwsnt5z';
$db_pass = 'p8PQF7M8ZKLVxtjvatMkrthFQQUB9'; // 明文密码
```
**风险等级**: 🔴 严重
**影响**: 代码泄露会直接导致数据库被入侵

**修复方案**:
1. 使用环境变量或配置文件(不纳入版本控制)
2. 添加 `.env` 文件到 `.gitignore`
3. 使用 `vlucas/phpdotenv` 库加载配置

```php
// 推荐方案
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: '';
$db_user = getenv('DB_USER') ?: '';
$db_pass = getenv('DB_PASS') ?: '';
```

### 1.3 密码哈希算法不安全
**位置**: `store/store_html/html/kds/api/kds_login_handler.php:37`
**问题**: 使用简单的SHA256无盐哈希验证密码
```php
if ($user && hash_equals($user['password_hash'], hash('sha256', $password))) {
```
**风险等级**: 🟠 高
**影响**: 易受彩虹表攻击,密码可能被破解

**修复方案**:
```php
// 修改为使用PHP内置的password_hash/password_verify
// 注册时:
$password_hash = password_hash($password, PASSWORD_ARGON2ID);

// 登录时:
if ($user && password_verify($password, $user['password_hash'])) {
    // 登录成功
}
```

### 1.4 Session安全配置不足
**位置**: 多处使用 `@session_start()`
**问题**:
- 使用 `@` 抑制错误
- 未设置 session 安全参数
- 未设置 httponly, secure, samesite 标志

**修复方案**:
```php
// 在 config.php 或 kds_auth_core.php 中设置
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // 仅HTTPS环境
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
session_start();
```

---

## 2. 数据库架构问题 🗄️

### 2.1 数据库名称不一致 ⚠️
**问题**: 配置文件与SQL架构文件中的数据库名不匹配
- **config.php**: `mhdlmskvtmwsnt5z`
- **db_schema_structure_only.sql**: `mhdlmskv3gjbpqv3` (Line 21,24)

**风险等级**: 🟠 高
**影响**: 可能导致系统连接到错误的数据库,或初始化失败

**修复方案**:
1. 确认生产环境使用的数据库名
2. 统一所有配置文件和文档中的数据库名
3. 建议使用环境变量管理数据库名

### 2.2 kds_users表缺少role外键约束
**位置**: `kds_users` 表
**问题**:
- `kds_users.role` 字段为varchar类型,但无外键约束
- 与 `cpsys_users.role_id` (有外键到cpsys_roles)设计不一致
- 可能导致数据不一致(输入任意字符串作为角色)

**当前设计**:
```sql
CREATE TABLE `kds_users` (
  `role` varchar(50) DEFAULT 'staff' COMMENT '角色 (e.g., staff, manager)',
  -- 无外键约束
);
```

**修复方案** (二选一):

**方案A**: 创建 `kds_roles` 表并添加外键(推荐)
```sql
-- 1. 创建角色表
CREATE TABLE `kds_roles` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_code` varchar(50) NOT NULL UNIQUE,
  `role_name_zh` varchar(100) NOT NULL,
  `role_name_es` varchar(100),
  `created_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. 插入预定义角色
INSERT INTO `kds_roles` (role_code, role_name_zh, role_name_es) VALUES
('staff', '店员', 'Empleado'),
('manager', '店长', 'Gerente');

-- 3. 修改kds_users表
ALTER TABLE `kds_users`
  ADD COLUMN `role_id` int UNSIGNED NOT NULL DEFAULT 1 AFTER `display_name`,
  ADD CONSTRAINT `fk_kds_user_role` FOREIGN KEY (`role_id`) REFERENCES `kds_roles` (`id`) ON DELETE RESTRICT;

-- 4. 迁移现有数据
UPDATE `kds_users` u
JOIN `kds_roles` r ON u.role = r.role_code
SET u.role_id = r.id;

-- 5. 删除旧字段(可选,或保留作为快照)
-- ALTER TABLE `kds_users` DROP COLUMN `role`;
```

**方案B**: 添加CHECK约束(MySQL 8.0.16+)
```sql
ALTER TABLE `kds_users`
  ADD CONSTRAINT `chk_kds_user_role`
  CHECK (role IN ('staff', 'manager'));
```

### 2.3 外键约束不完整
**问题**: 多个表引用 `kds_users.id` 但未定义外键

**缺少外键的引用** (部分列表):
- `pass_redemption_batches.cashier_user_id` -> `kds_users.id` (存在外键 ✓)
- `pass_redemptions.cashier_user_id` -> `kds_users.id` (❌ 缺少)
- `pos_invoices.user_id` -> `kds_users.id` (❌ 缺少)
- `pos_eod_reports.user_id` -> `kds_users` 或 `cpsys_users` (❌ 缺少,注释说明两者都可)
- `audit_logs.actor_user_id` -> `kds_users` 或 `cpsys_users` (❌ 缺少)

**风险**: 可能插入不存在的user_id,导致数据完整性问题

**修复方案**:
```sql
-- 添加缺失的外键(需要先确认现有数据的完整性)

-- 1. pass_redemptions
ALTER TABLE `pass_redemptions`
  ADD CONSTRAINT `fk_redemption_cashier`
  FOREIGN KEY (`cashier_user_id`) REFERENCES `kds_users` (`id`) ON DELETE RESTRICT;

-- 2. pos_invoices
ALTER TABLE `pos_invoices`
  ADD CONSTRAINT `fk_invoice_user`
  FOREIGN KEY (`user_id`) REFERENCES `kds_users` (`id`) ON DELETE RESTRICT;

-- 注意: pos_eod_reports.user_id 和 audit_logs.actor_user_id 引用多个表,
-- 需要重新设计或使用触发器验证
```

### 2.4 混合用户系统设计缺陷
**问题**: 系统中存在两套用户表 (`kds_users` 和 `cpsys_users`)，但部分表的外键字段无法明确指向哪个表

**影响表**:
- `audit_logs.actor_user_id` + `actor_type` (使用enum区分)
- `pos_eod_reports.user_id` (注释说可能是两者之一,但无区分字段)

**修复方案**:
```sql
-- 为 pos_eod_reports 添加用户类型字段
ALTER TABLE `pos_eod_reports`
  ADD COLUMN `user_type` ENUM('kds_user', 'cpsys_user') NOT NULL DEFAULT 'kds_user' AFTER `user_id`;

-- 创建触发器验证外键一致性
DELIMITER $$
CREATE TRIGGER `before_eod_report_insert` BEFORE INSERT ON `pos_eod_reports`
FOR EACH ROW
BEGIN
  DECLARE user_exists INT;

  IF NEW.user_type = 'kds_user' THEN
    SELECT COUNT(*) INTO user_exists FROM kds_users WHERE id = NEW.user_id;
  ELSE
    SELECT COUNT(*) INTO user_exists FROM cpsys_users WHERE id = NEW.user_id;
  END IF;

  IF user_exists = 0 THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Referenced user does not exist';
  END IF;
END$$
DELIMITER ;
```

### 2.5 索引缺失
**问题**: 部分高频查询字段缺少索引

**需要添加的索引**:
```sql
-- kds_material_expiries: 经常按门店和状态查询
ALTER TABLE `kds_material_expiries`
  ADD INDEX `idx_store_status` (`store_id`, `status`);

-- pos_invoices: 经常按门店和时间范围查询
ALTER TABLE `pos_invoices`
  ADD INDEX `idx_store_issued` (`store_id`, `issued_at`);

-- pass_redemptions: 经常按会员卡查询
ALTER TABLE `pass_redemptions`
  ADD INDEX `idx_pass_redeemed` (`member_pass_id`, `redeemed_at`);

-- kds_users: 按用户名和门店查询
ALTER TABLE `kds_users`
  ADD INDEX `idx_username_store` (`username`, `store_id`);
```

---

## 3. PHP代码架构问题 🏗️

### 3.1 目录结构混乱
**问题**: 代码分散在多层嵌套目录中,不符合常规项目结构

**当前结构**:
```
kds/
└── store/
    └── store_html/
        ├── html/kds/          # 公网可访问的入口文件
        ├── kds/               # KDS前端相关(views, core)
        └── kds_backend/       # KDS后端逻辑(helpers, core)
```

**问题**:
1. 根目录下有冗余的 `kds/store/` 层级
2. `kds` 和 `kds_backend` 职责划分不清晰
3. `html/kds` 应该是唯一的公网入口点,但部分核心文件也在其中

**修复方案**:
```
推荐结构:

kdsv2/
├── public/              # 公网可访问 (原 html/kds)
│   ├── index.php
│   ├── login.php
│   ├── api/
│   │   └── kds_api_gateway.php
│   ├── css/
│   └── js/
├── app/                 # 应用核心 (原 kds/)
│   ├── views/
│   ├── core/
│   │   ├── config.php
│   │   └── kds_auth_core.php
│   └── helpers/
├── backend/             # 后端逻辑 (原 kds_backend/)
│   ├── core/
│   │   └── kds_api_core.php
│   ├── helpers/
│   │   ├── kds_helper.php
│   │   ├── kds_repo.php
│   │   ├── kds_json_helper.php
│   │   └── kds_datetime_helper.php
│   └── registries/
│       └── kds_registry.php
├── storage/
│   └── store_images/
└── docs/
    └── db_schema_structure_only.sql
```

### 3.2 URL路径与文件路径不一致
**问题**: 根据用户提供的对齐关系,URL路径与实际文件路径存在偏差

**用户提供的对齐**:
- URL: `https://<域名>/kds/login.php`
- 文件: `store/store_html/html/kds/login.php`

**实际问题**:
1. URL中没有 `html` 层级,说明 `html` 目录被配置为Web根目录
2. 但 `html` 下还有 `kds` 子目录,说明URL中的 `/kds/` 是真实的子目录

**确认事项** (需与运维确认):
```apache
# 可能的Apache配置
DocumentRoot "/path/to/kds/store/store_html/html"

# 或使用别名
Alias /kds /path/to/kds/store/store_html/html/kds
```

**修复方案**:
```apache
# 推荐配置: 将 html/kds 设置为虚拟主机根目录
<VirtualHost *:443>
    DocumentRoot "/var/www/kds/store/store_html/html/kds"
    ServerName kds.example.com

    <Directory "/var/www/kds/store/store_html/html/kds">
        AllowOverride All
        Require all granted
    </Directory>

    # 禁止访问上级目录
    <DirectoryMatch "^/var/www/kds/store/store_html/(kds|kds_backend|docs)/">
        Require all denied
    </DirectoryMatch>
</VirtualHost>
```

```nginx
# Nginx 配置
server {
    listen 443 ssl;
    server_name kds.example.com;
    root /var/www/kds/store/store_html/html/kds;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }

    # 禁止访问敏感目录
    location ~ ^/(\.git|docs|store_images) {
        deny all;
    }
}
```

### 3.3 文件包含路径过于复杂
**位置**: 多个文件中的 `require_once realpath(...)`

**问题示例**:
```php
// kds_api_gateway.php:21-24
$path_config   = $STORE_HTML . '/kds/core/config.php';
$path_jsonhelp = $STORE_HTML . '/kds_backend/helpers/kds_json_helper.php';
$path_core     = $STORE_HTML . '/kds_backend/core/kds_api_core.php';
$path_registry = $API_DIR    . '/registries/kds_registry.php';

// kds_registry.php:18
require_once realpath(__DIR__ . '/../../../../kds_backend/helpers/kds_helper.php');
```

**问题**:
1. 大量使用 `../../../../` 回溯路径,易出错
2. 依赖 `realpath()` 的返回值,但未检查返回 false 的情况(已在gateway中修复)

**修复方案**:
```php
// 在入口文件(index.php, login.php等)定义基础路径常量
define('ROOT_PATH', dirname(__DIR__, 2)); // 指向 store/store_html
define('APP_PATH', ROOT_PATH . '/kds');
define('BACKEND_PATH', ROOT_PATH . '/kds_backend');
define('PUBLIC_PATH', ROOT_PATH . '/html/kds');

// 使用常量简化路径
require_once APP_PATH . '/core/config.php';
require_once BACKEND_PATH . '/helpers/kds_helper.php';
```

---

## 4. 函数冗余和遗漏 🔧

### 4.1 kds_helper_shim.php 空函数壳
**位置**: `store/store_html/kds/helpers/kds_helper_shim.php:13-20`

**问题**:
```php
if (!function_exists('getMaterialById')) {
    /**
     * [GEMINI FIX 3.C] 移除函数体，保留外壳以防其他旧文件依赖 function_exists。
     */
}
```

**影响**:
- 声明了函数但无实现,调用会导致致命错误
- 注释说明是为了解决与 `kds_repo.php` 的冲突
- 实际上 `kds_repo.php:525` 已经定义了完整的 `getMaterialById`

**修复方案**:
```php
// 方案1: 完全删除 kds_helper_shim.php (推荐)
// 确认没有其他文件 require 它后删除

// 方案2: 如果必须保留,添加实际的 shim 逻辑
if (!function_exists('getMaterialById')) {
    function getMaterialById(PDO $pdo, int $id) {
        // 确保 kds_repo.php 已加载
        if (function_exists('getMaterialById')) {
            return getMaterialById($pdo, $id);
        }
        throw new RuntimeException('getMaterialById not implemented');
    }
}
```

### 4.2 kds_helper.php 清理不彻底
**位置**: `store/store_html/kds/helpers/kds_helper.php:15-17`

**问题**:
```php
if (function_exists('base_recipe')) {
    // return; // 保持原有的 return 逻辑
}
```

**影响**:
- 注释掉的 `return` 导致即使函数存在也不会跳过加载
- 可能导致函数重复定义错误

**修复方案**:
```php
if (function_exists('base_recipe')) {
    return; // 取消注释,如果函数已定义则跳过加载
}
```

### 4.3 函数名称不一致
**问题**: 代码中存在多个功能相似但命名不同的函数

**示例**:
- `get_base_recipe()` (kds_repo.php:329) vs `base_recipe()` (kds_helper.php注释中提到)
- `norm_cat()` (kds_repo.php:240) vs 数据库中的 `step_category` enum

**修复方案**:
1. 统一函数命名规范(建议使用 snake_case)
2. 移除所有冗余函数
3. 在 kds_repo.php 的文件头添加函数索引注释

```php
/**
 * KDS Repository Functions Index:
 *
 * SOP Parsing:
 * - KdsSopParser::parse()
 * - id_by_code()
 * - get_product()
 *
 * Recipe Processing:
 * - get_base_recipe()
 * - apply_global_rules()
 * - apply_overrides()
 *
 * Data Retrieval:
 * - m_details()
 * - u_name()
 * - get_product_info_bilingual()
 * ...
 */
```

---

## 5. 配置管理问题 ⚙️

### 5.1 config.php 混合职责
**位置**: `store/store_html/kds/core/config.php`

**问题**:
文件同时包含:
1. 错误处理配置 (Line 9-15)
2. 数据库连接配置 (Line 18-23)
3. 应用路径常量 (Line 26-32)
4. PDO连接初始化 (Line 42-58)

**影响**:
- 每次 require config.php 都会创建新的PDO连接(如果不小心多次包含)
- 难以进行单元测试
- 配置和执行逻辑耦合

**修复方案**:
```php
// config.php - 仅包含配置
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'name' => getenv('DB_NAME') ?: '',
        'user' => getenv('DB_USER') ?: '',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_url' => '/kds/',
        'timezone' => 'Europe/Madrid',
    ],
    'paths' => [
        'root' => dirname(__DIR__),
        'app' => dirname(__DIR__) . '/app',
        'core' => dirname(__DIR__) . '/core',
        'public' => dirname(__DIR__) . '/html',
    ],
];

// database.php - 数据库连接单例
class Database {
    private static $pdo = null;

    public static function getInstance(): PDO {
        if (self::$pdo === null) {
            $config = require __DIR__ . '/config.php';
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['db']['host'],
                $config['db']['name'],
                $config['db']['charset']
            );
            self::$pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec("SET time_zone='+00:00'");
        }
        return self::$pdo;
    }
}

// 使用
$pdo = Database::getInstance();
```

### 5.2 错误日志路径设置不当
**位置**: `config.php:12`

**问题**:
```php
ini_set('error_log', __DIR__ . '/php_errors_kds.log');
```

**影响**:
- 日志文件在代码目录中,可能被意外提交到版本控制
- 可能没有写入权限

**修复方案**:
```php
// 将日志放在专门的日志目录
$log_dir = dirname(__DIR__, 2) . '/storage/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
ini_set('error_log', $log_dir . '/kds_' . date('Y-m-d') . '.log');

// 添加到 .gitignore
// storage/logs/*
// !storage/logs/.gitkeep
```

---

## 6. 注册表问题 📋

### 6.1 kds_api_core.php 角色检查逻辑复杂
**位置**: `kds_backend/core/kds_api_core.php:59-78`

**问题**:
```php
$user_role = $_SESSION['kds_user_role'] ?? null;

// 修复 KDS 登录处理器未设置角色的问题
if ($user_role === null && $user_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT role FROM kds_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_role_from_db = $stmt->fetchColumn();
        if ($user_role_from_db) {
            $_SESSION['kds_user_role'] = $user_role_from_db;
            $user_role = $user_role_from_db;
        }
    } catch (Throwable $e) {
        error_log("KDS API Core: Failed to fetch KDS user role: " . $e->getMessage());
    }
}
```

**影响**:
- 每次API调用都可能查询数据库获取角色
- 说明登录时未正确设置 `$_SESSION['kds_user_role']`

**根本原因**: `kds_login_handler.php:46` 未设置角色到session

**修复方案**:
```php
// kds_login_handler.php: 登录成功时设置角色
$_SESSION['kds_user_role'] = $user['role']; // 添加这一行

// kds_api_core.php: 简化检查逻辑
$user_role = $_SESSION['kds_user_role'] ?? ROLE_STORE_USER;
if ($user_role !== ROLE_STORE_MANAGER && $user_role !== $required_role) {
    json_error("权限不足,需要 '{$required_role}' 权限。", 403);
}
```

### 6.2 kds_registry.php 复制了POS代码
**位置**: `html/kds/api/registries/kds_registry.php:31-55`

**问题**:
注释说明 `handle_print_get_templates` 是从 `pos_registry.php` 复制的

```php
/* -------------------------------------------------------------------------- */
/* Handlers: 迁移自 /pos/api/pos_print_handler.php (KDS 需要)     */
/* -------------------------------------------------------------------------- */
function handle_print_get_templates(PDO $pdo, array $config, array $input_data): void {
```

**影响**:
- 代码重复,违反DRY原则
- 两处代码可能不同步

**修复方案**:
```php
// 创建共享的helper文件: shared/helpers/print_helper.php
function get_print_templates(PDO $pdo, int $store_id): array {
    $stmt = $pdo->prepare(
        "SELECT template_type, template_content, physical_size
         FROM pos_print_templates
         WHERE (store_id = :store_id OR store_id IS NULL) AND is_active = 1
         ORDER BY store_id DESC"
    );
    $stmt->execute([':store_id' => $store_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $templates = [];
    foreach ($results as $row) {
        if (!isset($templates[$row['template_type']])) {
            $templates[$row['template_type']] = [
                'content' => json_decode($row['template_content'], true),
                'size' => $row['physical_size']
            ];
        }
    }
    return $templates;
}

// kds_registry.php 和 pos_registry.php 都调用共享函数
function handle_print_get_templates(PDO $pdo, array $config, array $input_data): void {
    $store_id = (int)($_SESSION['kds_store_id'] ?? $_SESSION['store_id'] ?? 0);
    if ($store_id === 0) json_error('无法确定门店ID。', 401);

    $templates = get_print_templates($pdo, $store_id);
    json_ok($templates, 'Templates loaded.');
}
```

---

## 7. 其他代码质量问题 🧹

### 7.1 过度使用 `@` 错误抑制
**位置**: 多个文件
- `kds_auth_core.php:7` - `@session_start()`
- `login.php:2` - `@session_start()`
- `kds_api_core.php:48` - `@session_start()`

**问题**: 隐藏了潜在错误,增加调试难度

**修复方案**:
```php
// 不要使用 @, 而是正确处理错误
if (session_status() === PHP_SESSION_NONE) {
    if (!session_start()) {
        error_log('Failed to start session');
        http_response_code(500);
        die('Session initialization failed');
    }
}
```

### 7.2 硬编码的HTTP响应码
**位置**: 多处

**问题**: 直接使用数字,不易理解
```php
http_response_code(503);
http_response_code(401);
```

**修复方案**:
```php
// 定义常量
class HttpStatus {
    const OK = 200;
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const INTERNAL_SERVER_ERROR = 500;
    const SERVICE_UNAVAILABLE = 503;
}

// 使用
http_response_code(HttpStatus::SERVICE_UNAVAILABLE);
```

### 7.3 SQL查询字符串拼接
**位置**: `kds_repo.php:442-447`

**问题**:
```php
$sql = "SELECT material_id, quantity, unit_id, step_category FROM kds_recipe_adjustments
        WHERE " . implode(' AND ', $cond) . " ORDER BY {$scoreExpr} DESC, id DESC LIMIT 1";
```

**虽然使用了预处理语句,但拼接ORDER BY可能有注入风险**

**修复方案**: 由于 `$scoreExpr` 是代码生成的而非用户输入,当前实现是安全的,但建议添加注释说明

```php
// $scoreExpr is internally generated, safe from SQL injection
$sql = "SELECT material_id, quantity, unit_id, step_category FROM kds_recipe_adjustments
        WHERE " . implode(' AND ', $cond) . " ORDER BY {$scoreExpr} DESC, id DESC LIMIT 1";
```

### 7.4 缺少类型声明
**位置**: 大部分函数

**问题**: 虽然部分文件使用了类型提示,但不一致

**示例**:
```php
// kds_json_helper.php 有类型声明
function json_ok($data = null, string $message = '操作成功', int $http_code = 200): void {

// 但 kds_repo.php 缺少返回类型
function id_by_code(PDO $pdo, string $table, string $col, $val): ?int {
```

**修复方案**:
在所有函数上添加参数类型和返回类型声明(PHP 7.4+支持)

---

## 8. 遗漏功能检查 ❓

### 8.1 缺少登出日志
**位置**: `logout.php`

**问题**: 用户登出时未记录到audit_logs

**修复方案**:
```php
// logout.php (修改后)
<?php
require_once realpath(__DIR__ . '/../../kds/core/config.php');
session_start();

// 记录登出行为
if (isset($_SESSION['kds_user_id'])) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (action, actor_user_id, actor_type, ip, ua, created_at)
             VALUES ('user.logout', ?, 'store_user', ?, ?, NOW())"
        );
        $stmt->execute([
            $_SESSION['kds_user_id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (Exception $e) {
        error_log("Failed to log logout: " . $e->getMessage());
    }
}

// 清理session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]);
}
session_destroy();
header('Location: login.php');
exit;
```

### 8.2 缺少CSRF保护
**位置**: 所有表单提交

**问题**: 登录表单和API调用缺少CSRF token

**修复方案**:
```php
// 在 kds_auth_core.php 或 config.php 中添加
function generateCsrfToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 在表单中添加
// login_view.php
<input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

// 在处理器中验证
// kds_login_handler.php
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf_token)) {
    header('Location: ../login.php?error=csrf');
    exit;
}
```

### 8.3 缺少速率限制
**位置**: 登录API

**问题**: 没有防止暴力破解的机制

**修复方案**:
```sql
-- 创建登录尝试记录表
CREATE TABLE `login_attempts` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6)),
  PRIMARY KEY (`id`),
  INDEX `idx_username_ip_time` (`username`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

```php
// 在 kds_login_handler.php 开头添加
function checkRateLimit(PDO $pdo, string $username, string $ip): void {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE (username = ? OR ip_address = ?)
         AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );
    $stmt->execute([$username, $ip]);
    $count = $stmt->fetchColumn();

    if ($count >= 5) {
        error_log("Rate limit exceeded for $username from $ip");
        header('Location: ../login.php?error=rate_limit');
        exit;
    }

    // 记录本次尝试
    $stmt = $pdo->prepare(
        "INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)"
    );
    $stmt->execute([$username, $ip]);
}

// 在验证密码之前调用
checkRateLimit($pdo, $username, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
```

---

## 9. 前端代码问题 (需进一步审计) 🎨

**注意**: 本次审计主要针对后端PHP和数据库,前端JS代码未深入审计,以下是初步发现:

### 9.1 JS文件内容未审计
**位置**: `html/kds/js/*`
**发现的文件**:
- kds_login.js
- kds_prep.js
- kds_sop_bind.js
- kds_print_bridge.js
- kds_state.js
- kds_sop.js
- kds_ui_helpers.js
- kds_expiry.js

**建议**: 后续需要审计这些文件是否存在:
- XSS漏洞
- 不安全的localStorage使用
- API密钥泄露
- 逻辑错误

### 9.2 CSS文件
**位置**: `html/kds/css/*`
- kds_style.css
- kds_login.css

**建议**: 检查是否包含敏感信息或影响功能的样式问题

---

## 10. 总结与优先级建议 📊

### 🔴 立即修复 (Critical - 24小时内)
1. **删除 api/1.php** (phpinfo泄露)
2. **修复数据库名不一致** (config.php vs SQL文件)
3. **移除或实现 kds_helper_shim.php 的空函数**
4. **修复登录时未设置 $_SESSION['kds_user_role']**

### 🟠 高优先级 (1周内)
1. **迁移数据库凭证到环境变量**
2. **升级密码哈希算法** (SHA256 -> Argon2id)
3. **添加kds_users.role的外键约束或CHECK约束**
4. **添加缺失的外键约束** (pos_invoices.user_id等)
5. **实现CSRF保护**
6. **实现登录速率限制**

### 🟡 中优先级 (1个月内)
1. **重构config.php** (分离配置和执行)
2. **重构目录结构** (参考第3.1节)
3. **统一路径定义** (使用常量代替相对路径)
4. **添加Session安全配置**
5. **添加数据库索引** (提升查询性能)
6. **添加登出日志**

### 🟢 低优先级 (技术债务)
1. **消除代码重复** (print_templates等)
2. **统一HTTP状态码管理**
3. **添加完整的类型声明**
4. **移除所有 @ 错误抑制**
5. **审计前端JS代码**

---

## 11. 修复检查清单 ✅

使用以下清单跟踪修复进度:

```markdown
### 安全问题
- [ ] 删除 api/1.php
- [ ] 迁移数据库凭证到 .env
- [ ] 更新密码哈希算法
- [ ] 添加 CSRF 保护
- [ ] 实现登录速率限制
- [ ] 配置 Session 安全参数

### 数据库架构
- [ ] 统一数据库名称
- [ ] 添加 kds_users.role 约束
- [ ] 补充缺失的外键
- [ ] 添加性能索引
- [ ] 修复混合用户系统引用

### 代码架构
- [ ] 重构目录结构
- [ ] 重构 config.php
- [ ] 统一路径常量
- [ ] 简化文件包含路径
- [ ] 修复 kds_helper_shim.php
- [ ] 取消注释 kds_helper.php:17 的 return

### 功能完善
- [ ] 修复登录时设置角色
- [ ] 添加登出审计日志
- [ ] 消除 print_templates 代码重复

### 代码质量
- [ ] 移除 @ 错误抑制
- [ ] 使用 HttpStatus 常量
- [ ] 添加类型声明
- [ ] 前端代码审计
```

---

## 12. 附录

### A. 相关文件清单
```
关键PHP文件 (25个):
├── html/kds/
│   ├── login.php
│   ├── logout.php
│   ├── index.php
│   ├── prep.php
│   ├── expiry.php
│   └── api/
│       ├── 1.php ⚠️
│       ├── get_image.php
│       ├── kds_api_gateway.php
│       ├── kds_login_handler.php
│       └── registries/
│           └── kds_registry.php
├── kds/
│   ├── core/
│   │   ├── config.php ⚠️
│   │   └── kds_auth_core.php
│   ├── helpers/
│   │   ├── kds_helper.php
│   │   └── kds_helper_shim.php ⚠️
│   └── app/views/kds/
│       ├── login_view.php
│       ├── sop_view.php
│       ├── prep_view.php
│       ├── expiry_view.php
│       └── layouts/
│           └── main.php
└── kds_backend/
    ├── core/
    │   └── kds_api_core.php
    └── helpers/
        ├── kds_helper.php
        ├── kds_repo.php ⭐
        ├── kds_json_helper.php
        └── kds_datetime_helper.php

数据库文件:
└── docs/
    └── db_schema_structure_only.sql (2505 lines)
```

### B. 数据库表统计
```
总计: 49个表

KDS系统 (23个表):
- kds_cups
- kds_global_adjustment_rules
- kds_ice_options
- kds_ice_option_translations
- kds_materials
- kds_material_expiries
- kds_material_translations
- kds_products
- kds_product_adjustments
- kds_product_categories
- kds_product_category_translations
- kds_product_ice_options
- kds_product_recipes
- kds_product_statuses
- kds_product_sweetness_options
- kds_product_translations
- kds_recipe_adjustments
- kds_sop_query_rules
- kds_stores
- kds_sweetness_options
- kds_sweetness_option_translations
- kds_units
- kds_unit_translations
- kds_users ⚠️

POS系统 (19个表):
- pos_addons
- pos_addon_tag_map
- pos_categories
- pos_coupons
- pos_daily_tracking
- pos_eod_records
- pos_eod_reports
- pos_held_orders
- pos_invoices
- pos_invoice_counters
- pos_invoice_items
- pos_item_variants
- pos_members
- pos_member_issued_coupons
- pos_member_levels
- pos_member_points_log
- pos_menu_items
- pos_point_redemption_rules
- pos_print_templates
- pos_product_availability
- pos_product_tag_map
- pos_promotions
- pos_settings
- pos_shifts
- pos_tags
- pos_vr_counters

会员系统 (4个表):
- member_passes
- pass_daily_usage
- pass_plans
- pass_redemptions
- pass_redemption_batches

充值系统 (1个表):
- topup_orders

后台系统 (2个表):
- cpsys_roles
- cpsys_users

审计系统 (1个表):
- audit_logs

库存系统 (2个表):
- expsys_store_stock
- expsys_warehouse_stock

其他 (1个表):
- v_unauthorized_access_attempts
```

### C. 函数依赖图
```
kds_api_gateway.php
  └── require: kds_registry.php
        ├── require: kds_helper.php
        │     ├── require: kds_datetime_helper.php
        │     └── require: kds_repo.php (定义所有业务函数)
        ├── require: kds_json_helper.php
        └── require: kds_api_core.php
              └── require: kds_json_helper.php
```

---

**审计结论**: 系统存在多处严重安全隐患和架构设计问题,必须立即处理Critical级别问题,并制定计划逐步修复其他问题。建议在修复后进行渗透测试和性能压测。

**下一步行动**: 请确认是否接受本报告,并告知优先修复哪些问题。我将提供详细的代码补丁。
