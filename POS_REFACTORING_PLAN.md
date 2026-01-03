# POS系统重构实施计划 (POS System Refactoring Implementation Plan)

**版本**: 1.0
**日期**: 2026-01-03
**架构师**: Claude (Senior PHP System Architect)
**参考**: KDS System Refactoring (已完成)

---

## 目录 (Table of Contents)

1. [重构目标](#1-重构目标)
2. [架构对比](#2-架构对比)
3. [目录结构规划](#3-目录结构规划)
4. [核心组件设计](#4-核心组件设计)
5. [分阶段实施计划](#5-分阶段实施计划)
6. [数据库迁移策略](#6-数据库迁移策略)
7. [文件迁移映射表](#7-文件迁移映射表)
8. [测试策略](#8-测试策略)
9. [风险控制](#9-风险控制)
10. [回滚方案](#10-回滚方案)

---

## 1. 重构目标 (Refactoring Goals)

### 1.1 主要目标

1. **与KDS架构对齐**
   - 采用相同的目录结构
   - 使用相同的核心组件 (DotEnv, SessionManager, ErrorHandler, Logger)
   - 保持代码风格一致性

2. **消除安全隐患**
   - 移除硬编码数据库凭据
   - 实现CSRF保护
   - 添加速率限制
   - 统一session管理

3. **提升可维护性**
   - 实现PSR-4自动加载
   - 采用MVC架构
   - 模块化代码结构
   - 统一错误处理

4. **增强可测试性**
   - 分离业务逻辑
   - 实现依赖注入
   - 添加单元测试

5. **保持业务连续性**
   - 100%数据库兼容
   - API逐步迁移
   - 零停机部署

### 1.2 非目标 (Out of Scope)

- ❌ 修改数据库schema (除非必要)
- ❌ 重写前端框架 (保持现有Bootstrap/jQuery)
- ❌ 修改业务逻辑 (仅重构架构)
- ❌ 改变密码哈希算法 (用户要求保持SHA256)

---

## 2. 架构对比 (Architecture Comparison)

### 2.1 重构前 (Current Architecture)

```
store/store_html/
├── pos_backend/               # 混乱的后端逻辑
│   ├── core/
│   │   └── config.php         # ❌ 硬编码凭据
│   ├── helpers/               # ❌ 无命名空间
│   ├── services/
│   └── compliance/
├── html/pos/
│   ├── index.php              # ❌ 54KB单体文件
│   ├── api/
│   │   ├── pos_api_gateway.php
│   │   └── registries/        # ❌ 注册表碎片化
│   └── assets/
└── docs/
```

**问题**:
- 配置管理混乱
- 无自动加载机制
- 注册表模式难维护
- 前后端代码混合
- 路径深度过深 (`../../../../`)

---

### 2.2 重构后 (Target Architecture - 与KDS一致)

```
/home/user/kdsv2/
├── .env                       # ✅ 环境配置 (不提交Git)
├── .env.example               # ✅ 配置模板
├── public/pos/                # ✅ Web可访问根目录
│   ├── index.php              # ✅ 轻量级入口点
│   ├── login.php
│   ├── logout.php
│   ├── api/
│   │   └── gateway.php        # ✅ 统一API网关
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   │   ├── pos_modal.js   # ✅ Modal错误处理 (类似KDS)
│   │   │   ├── cart.js
│   │   │   ├── member.js
│   │   │   └── components/
│   │   └── img/
│   └── views/                 # ✅ 拆分的视图文件
│       ├── layout/
│       │   ├── header.php
│       │   └── footer.php
│       ├── home.php
│       ├── cart.php
│       ├── members.php
│       └── settings.php
└── src/pos/                   # ✅ 核心逻辑 (不可Web访问)
    ├── Config/
    │   ├── DotEnv.php         # ✅ 环境变量加载器 (复制自KDS)
    │   └── Database.php
    ├── Core/
    │   ├── Autoloader.php     # ✅ PSR-4自动加载 (复制自KDS)
    │   ├── SessionManager.php # ✅ Session管理器 (复制自KDS)
    │   ├── ErrorHandler.php   # ✅ 错误处理器 (复制自KDS)
    │   ├── Logger.php         # ✅ 日志记录器 (复制自KDS)
    │   └── Router.php         # ✅ 现代路由系统
    ├── Controllers/           # ✅ MVC控制器
    │   ├── AuthController.php
    │   ├── OrderController.php
    │   ├── CartController.php
    │   ├── MemberController.php
    │   ├── ShiftController.php
    │   ├── EODController.php
    │   ├── PassController.php
    │   ├── PrintController.php
    │   └── AvailabilityController.php
    ├── Models/                # ✅ 数据模型
    │   ├── Order.php
    │   ├── Member.php
    │   ├── Shift.php
    │   ├── Invoice.php
    │   └── Pass.php
    ├── Repositories/          # ✅ 数据访问层
    │   ├── OrderRepository.php
    │   ├── MemberRepository.php
    │   └── InvoiceRepository.php
    ├── Services/              # ✅ 业务服务
    │   ├── PromotionEngine.php
    │   ├── InvoiceService.php
    │   ├── ComplianceService.php
    │   └── PaymentService.php
    ├── Middleware/            # ✅ 中间件
    │   ├── AuthMiddleware.php
    │   ├── CSRFMiddleware.php
    │   └── RateLimitMiddleware.php
    ├── Database/
    │   └── migrations/        # ✅ 数据库迁移
    │       └── 001_pos_refactor_indexes.sql
    └── Helpers/
        ├── DateTimeHelper.php
        ├── ResponseHelper.php
        └── ValidationHelper.php
```

**改进**:
- ✅ 清晰的目录结构
- ✅ 公开/私有代码分离
- ✅ PSR-4命名空间
- ✅ MVC架构
- ✅ 统一配置管理

---

## 3. 目录结构规划 (Directory Structure Plan)

### 3.1 Web可访问目录 (`public/pos/`)

```
public/pos/
├── index.php                  # 主应用入口点
├── login.php                  # 登录页面
├── logout.php                 # 登出处理
├── api/
│   └── gateway.php            # 统一API网关 (路由到Controllers)
├── assets/
│   ├── css/
│   │   ├── pos.css            # POS主样式
│   │   └── components/
│   ├── js/
│   │   ├── pos_modal.js       # Modal错误处理 (类似KDS kds_modal.js)
│   │   ├── app.js             # 主应用逻辑
│   │   ├── cart.js
│   │   ├── member.js
│   │   ├── shift.js
│   │   └── components/
│   └── img/
│       └── logo.png
└── views/                     # 视图模板
    ├── layout/
    │   ├── header.php         # 公共头部
    │   ├── footer.php         # 公共底部
    │   └── sidebar.php        # 侧边栏
    ├── home.php               # 主页 (商品网格)
    ├── cart.php               # 购物车视图
    ├── members.php            # 会员管理
    ├── shift.php              # 班次管理
    ├── eod.php                # 日结
    └── settings.php           # 设置
```

### 3.2 核心业务逻辑目录 (`src/pos/`)

```
src/pos/
├── Config/
│   ├── DotEnv.php             # 环境变量加载器
│   ├── Database.php           # 数据库配置类
│   └── AppConfig.php          # 应用配置
├── Core/
│   ├── Autoloader.php         # PSR-4自动加载器
│   ├── SessionManager.php     # Session管理器
│   ├── ErrorHandler.php       # 全局错误处理器
│   ├── Logger.php             # 日志记录器
│   ├── Router.php             # 路由系统
│   └── Request.php            # 请求封装
├── Controllers/
│   ├── AuthController.php     # 认证 (登录/登出)
│   ├── OrderController.php    # 订单提交/查询
│   ├── CartController.php     # 购物车计算
│   ├── MemberController.php   # 会员查找/创建
│   ├── ShiftController.php    # 班次管理
│   ├── EODController.php      # 日结报告
│   ├── PassController.php     # 次卡售卖/核销
│   ├── PrintController.php    # 打印模板
│   ├── AvailabilityController.php  # 估清管理
│   ├── HoldController.php     # 挂单
│   └── DataController.php     # 数据加载
├── Models/
│   ├── Order.php
│   ├── Invoice.php
│   ├── Member.php
│   ├── Shift.php
│   ├── Pass.php
│   ├── Product.php
│   └── Promotion.php
├── Repositories/
│   ├── OrderRepository.php
│   ├── MemberRepository.php
│   ├── InvoiceRepository.php
│   ├── ShiftRepository.php
│   └── PassRepository.php
├── Services/
│   ├── PromotionEngine.php    # 促销引擎 (保留现有逻辑)
│   ├── InvoiceService.php     # 开票服务
│   ├── ComplianceService.php  # 合规处理 (TICKETBAI/VERIFACTU)
│   ├── PaymentService.php     # 支付解析
│   └── PrintService.php       # 打印服务
├── Middleware/
│   ├── AuthMiddleware.php     # 认证检查
│   ├── CSRFMiddleware.php     # CSRF保护
│   ├── RateLimitMiddleware.php # 速率限制
│   └── ShiftGuardMiddleware.php # 班次检查
├── Database/
│   ├── Connection.php         # PDO连接封装
│   └── migrations/
│       ├── 001_add_indexes.sql
│       └── 002_add_constraints.sql
└── Helpers/
    ├── DateTimeHelper.php     # 时间处理 (保留现有逻辑)
    ├── ResponseHelper.php     # JSON响应
    ├── ValidationHelper.php   # 输入验证
    └── CSRFHelper.php         # CSRF Token管理
```

---

## 4. 核心组件设计 (Core Component Design)

### 4.1 DotEnv加载器 (`src/pos/Config/DotEnv.php`)

**源代码**: 直接复制自KDS重构后的 `src/kds/Config/DotEnv.php`

```php
<?php
namespace Pos\Config;

class DotEnv {
    public static function load(string $path): void {
        // ... (与KDS完全相同的实现)
    }

    public static function get(string $key, $default = null) {
        // ... (与KDS完全相同的实现)
    }
}
```

**功能**:
- 加载`.env`文件
- 支持变量展开 `${VAR_NAME}`
- 类型转换 (bool/int/float)

---

### 4.2 SessionManager (`src/pos/Core/SessionManager.php`)

**源代码**: 直接复制自KDS重构后的 `src/kds/Core/SessionManager.php`

```php
<?php
namespace Pos\Core;

class SessionManager {
    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // 先配置，再启动
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');

            if (!session_start()) {
                throw new \RuntimeException('Session initialization failed');
            }
        }
    }

    public static function regenerate(): void {
        session_regenerate_id(true);
    }

    public static function destroy(): void {
        session_destroy();
    }

    public static function isLoggedIn(): bool {
        return isset($_SESSION['pos_logged_in']) && $_SESSION['pos_logged_in'] === true;
    }

    // CSRF保护
    public static function generateCSRFToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCSRFToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
```

**改进**:
- ✅ 移除所有`@session_start()`
- ✅ 先`ini_set()`再`session_start()`
- ✅ 集成CSRF保护

---

### 4.3 ErrorHandler (`src/pos/Core/ErrorHandler.php`)

**源代码**: 基于KDS重构后的 `src/kds/Core/ErrorHandler.php`，适配POS

```php
<?php
namespace Pos\Core;

class ErrorHandler {
    public static function register(): void {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(\Throwable $e): void {
        Logger::error('Uncaught Exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        // 判断是AJAX请求还是页面请求
        if (self::isAjaxRequest()) {
            self::sendJsonError($e);
        } else {
            self::showErrorPage($e);
        }
    }

    private static function isAjaxRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
               && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private static function sendJsonError(\Throwable $e): void {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'data' => null
        ]);
        exit;
    }

    private static function showErrorPage(\Throwable $e): void {
        http_response_code(500);
        require __DIR__ . '/../../public/pos/views/error.php';
        exit;
    }
}
```

**特性**:
- ✅ 统一错误处理
- ✅ AJAX返回JSON
- ✅ 页面请求显示友好错误页
- ✅ 自动记录日志

---

### 4.4 Logger (`src/pos/Core/Logger.php`)

**源代码**: 直接复制自KDS重构后的 `src/kds/Core/Logger.php`

```php
<?php
namespace Pos\Core;

class Logger {
    const DEBUG = 'DEBUG';
    const INFO = 'INFO';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';

    private static function log(string $level, string $message, array $context = []): void {
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);

        $logFile = $logDir . '/pos_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        $logLine = "[{$timestamp}] [{$level}] {$message} {$contextJson}\n";
        file_put_contents($logFile, $logLine, FILE_APPEND);
    }

    public static function debug(string $message, array $context = []): void {
        self::log(self::DEBUG, $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::log(self::ERROR, $message, $context);
    }

    // ... 其他级别
}
```

---

### 4.5 Router (`src/pos/Core/Router.php`)

**新增组件** - POS专用路由系统

```php
<?php
namespace Pos\Core;

class Router {
    private array $routes = [];

    public function register(string $method, string $path, array $handler): void {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler // ['ControllerClass', 'methodName']
        ];
    }

    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchPath($route['path'], $path)) {
                $this->callHandler($route['handler']);
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Route not found']);
    }

    private function matchPath(string $pattern, string $path): bool {
        // 简单的路径匹配逻辑
        return $pattern === $path;
    }

    private function callHandler(array $handler): void {
        [$controllerClass, $method] = $handler;

        // 实例化控制器
        $controller = new $controllerClass();

        // 调用方法
        $controller->$method();
    }
}
```

---

### 4.6 控制器基类 (`src/pos/Controllers/BaseController.php`)

```php
<?php
namespace Pos\Controllers;

use Pos\Core\SessionManager;
use Pos\Helpers\ResponseHelper;

abstract class BaseController {
    protected \PDO $db;

    public function __construct() {
        // 从Database类获取PDO实例
        $this->db = \Pos\Database\Connection::getInstance();
    }

    protected function requireAuth(): void {
        if (!SessionManager::isLoggedIn()) {
            ResponseHelper::error('Unauthorized', 401);
        }
    }

    protected function requireShift(): void {
        $this->requireAuth();

        if (!isset($_SESSION['pos_shift_id'])) {
            ResponseHelper::error('No active shift', 403, [
                'error_code' => 'NO_ACTIVE_SHIFT'
            ]);
        }
    }

    protected function json($data, string $message = 'Success', int $code = 200): void {
        ResponseHelper::success($data, $message, $code);
    }

    protected function error(string $message, int $code = 400, $data = null): void {
        ResponseHelper::error($message, $code, $data);
    }
}
```

---

## 5. 分阶段实施计划 (Phased Implementation Plan)

### 5.1 阶段0: 准备工作 (1-2天)

**任务清单**:
- [ ] 创建新的目录结构
- [ ] 配置`.env`文件
- [ ] 设置Git分支 (`pos/refactor-v2`)
- [ ] 备份当前系统

**交付物**:
- 空目录结构
- .env.example模板
- Git分支

---

### 5.2 阶段1: 核心基础设施 (3-5天)

**任务清单**:
- [ ] 复制KDS的DotEnv.php → `src/pos/Config/DotEnv.php`
- [ ] 复制KDS的Autoloader.php → `src/pos/Core/Autoloader.php`
- [ ] 复制KDS的SessionManager.php → `src/pos/Core/SessionManager.php`
- [ ] 复制KDS的ErrorHandler.php → `src/pos/Core/ErrorHandler.php` (修改命名空间)
- [ ] 复制KDS的Logger.php → `src/pos/Core/Logger.php` (修改命名空间)
- [ ] 创建Database.php连接类
- [ ] 创建ResponseHelper.php

**测试**:
- [ ] DotEnv加载`.env`文件
- [ ] Autoloader加载类
- [ ] SessionManager正确初始化
- [ ] ErrorHandler捕获异常
- [ ] Logger写入日志文件

**交付物**:
- 5个核心类文件
- 单元测试

---

### 5.3 阶段2: 配置迁移 (2-3天)

**任务清单**:
- [ ] 迁移config.php → Database.php
- [ ] 移除硬编码凭据到.env
- [ ] 实现CSRF保护 (CSRFMiddleware + Helper)
- [ ] 实现速率限制 (RateLimitMiddleware)
- [ ] 更新login.php使用SessionManager

**测试**:
- [ ] 数据库连接正常
- [ ] Session启动无错误
- [ ] CSRF token验证生效
- [ ] 速率限制阻止暴力破解

**交付物**:
- Database.php
- 2个中间件类
- CSRFHelper.php
- 更新后的login.php

---

### 5.4 阶段3: 认证与中间件 (3-4天)

**任务清单**:
- [ ] 创建AuthMiddleware
- [ ] 创建ShiftGuardMiddleware
- [ ] 创建AuthController
  - `login()` - 登录处理
  - `logout()` - 登出处理
  - `checkAuth()` - 验证登录状态
- [ ] 迁移pos_login_handler.php逻辑
- [ ] 移除所有`@session_start()`

**测试**:
- [ ] 登录成功
- [ ] 登出清除session
- [ ] 未登录拦截
- [ ] 无班次拦截

**交付物**:
- AuthController.php
- 3个中间件类
- 更新后的login/logout处理

---

### 5.5 阶段4: 数据访问层 (5-7天)

**任务清单**:
- [ ] 创建BaseRepository基类
- [ ] 创建OrderRepository (迁移pos_repo.php逻辑)
- [ ] 创建MemberRepository
- [ ] 创建InvoiceRepository
- [ ] 创建ShiftRepository
- [ ] 创建PassRepository
- [ ] 迁移所有`get_*`函数为Repository方法

**示例** (OrderRepository):
```php
<?php
namespace Pos\Repositories;

class OrderRepository {
    private \PDO $db;

    public function __construct(\PDO $db) {
        $this->db = $db;
    }

    public function getInvoiceSummaryForPeriod(int $storeId, string $startUtc, string $endUtc): array {
        // 迁移自 pos_repo.php::getInvoiceSummaryForPeriod()
    }

    public function allocateInvoiceNumber(string $prefix, ?string $complianceSystem): array {
        // 迁移自 pos_repo.php::allocate_invoice_number()
    }
}
```

**测试**:
- [ ] 每个Repository方法的单元测试
- [ ] 数据库查询正确性

**交付物**:
- 5个Repository类
- 单元测试

---

### 5.6 阶段5: 控制器层 (7-10天)

**任务清单**:
- [ ] 创建BaseController
- [ ] 创建OrderController (迁移handle_order_submit)
- [ ] 创建CartController (迁移handle_cart_calculate)
- [ ] 创建MemberController (迁移handle_member_*)
- [ ] 创建ShiftController (迁移handle_shift_*)
- [ ] 创建EODController (迁移handle_eod_*)
- [ ] 创建PassController (迁移handle_pass_*)
- [ ] 创建PrintController (迁移handle_print_*)
- [ ] 创建AvailabilityController (迁移handle_avail_*)
- [ ] 创建HoldController (迁移handle_hold_*)
- [ ] 创建DataController (迁移handle_data_load)

**示例** (OrderController):
```php
<?php
namespace Pos\Controllers;

use Pos\Repositories\OrderRepository;
use Pos\Services\PromotionEngine;

class OrderController extends BaseController {
    private OrderRepository $orderRepo;
    private PromotionEngine $promotionEngine;

    public function __construct() {
        parent::__construct();
        $this->orderRepo = new OrderRepository($this->db);
        $this->promotionEngine = new PromotionEngine($this->db);
    }

    public function submit(): void {
        $this->requireShift();

        // 迁移自 handle_order_submit()
        $jsonData = $this->getRequestData();

        // ... 业务逻辑 ...

        $this->json([
            'invoice_id' => $invoiceId,
            'invoice_number' => $fullInvoiceNumber,
            'qr_content' => $qrPayload,
            'print_jobs' => $printJobs
        ], 'Order created.');
    }
}
```

**测试**:
- [ ] 每个控制器方法的功能测试
- [ ] API端点集成测试

**交付物**:
- 11个控制器类
- 集成测试

---

### 5.7 阶段6: 服务层 (3-5天)

**任务清单**:
- [ ] 迁移PromotionEngine.php到新目录 (保留逻辑)
- [ ] 创建InvoiceService (票号分配、合规处理)
- [ ] 创建ComplianceService (TICKETBAI/VERIFACTU)
- [ ] 创建PaymentService (支付解析)
- [ ] 创建PrintService (打印逻辑)

**测试**:
- [ ] PromotionEngine单元测试
- [ ] InvoiceService单元测试
- [ ] PaymentService单元测试

**交付物**:
- 5个服务类
- 单元测试

---

### 5.8 阶段7: 路由与网关 (2-3天)

**任务清单**:
- [ ] 创建Router.php
- [ ] 创建api/gateway.php (新网关)
- [ ] 注册所有路由:
```php
// public/pos/api/gateway.php
$router = new Router();

// 订单
$router->register('POST', '/api/order/submit', [OrderController::class, 'submit']);

// 购物车
$router->register('POST', '/api/cart/calculate', [CartController::class, 'calculate']);

// 会员
$router->register('GET', '/api/member/find', [MemberController::class, 'find']);
$router->register('POST', '/api/member/create', [MemberController::class, 'create']);

// ... 其他路由 ...

$router->dispatch();
```

**测试**:
- [ ] 所有API路由可访问
- [ ] 中间件生效 (Auth/CSRF/RateLimit)

**交付物**:
- Router.php
- gateway.php
- 路由配置

---

### 5.9 阶段8: 前端重构 (5-7天)

**任务清单**:
- [ ] 创建pos_modal.js (复制自KDS kds_modal.js)
- [ ] 拆分index.php (54KB) → 多个视图:
  - views/layout/header.php
  - views/layout/footer.php
  - views/home.php (商品网格)
  - views/cart.php
  - views/members.php
  - views/shift.php
  - views/eod.php
- [ ] 更新前端JS调用新API路径
- [ ] 实现CSP策略
- [ ] 提取内联JS到外部文件

**测试**:
- [ ] 所有页面正常渲染
- [ ] Modal错误提示正常
- [ ] AJAX请求正常
- [ ] CSP策略无阻断

**交付物**:
- pos_modal.js
- 拆分的视图文件
- 外部JS文件
- CSP配置

---

### 5.10 阶段9: 数据库优化 (2-3天)

**任务清单**:
- [ ] 创建migration: 001_add_indexes.sql
  - 为常用查询字段添加索引
- [ ] 创建migration: 002_add_constraints.sql
  - 添加外键约束
- [ ] (可选) 创建pos_cash_movements表

**测试**:
- [ ] Migration执行无错误
- [ ] 索引提升查询性能
- [ ] 外键约束正常工作

**交付物**:
- 2-3个migration SQL文件
- Migration执行脚本

---

### 5.11 阶段10: 测试与部署 (5-7天)

**任务清单**:
- [ ] 编写单元测试 (目标覆盖率70%)
- [ ] 编写集成测试
- [ ] 性能测试
- [ ] 安全测试 (CSRF/XSS/SQL注入)
- [ ] 用户验收测试 (UAT)
- [ ] 文档编写:
  - API文档
  - 部署文档
  - 用户手册更新
- [ ] 生产环境部署

**测试清单**:
- [ ] 登录/登出
- [ ] 订单提交
- [ ] 会员管理
- [ ] 班次管理
- [ ] 日结报告
- [ ] 次卡售卖/核销
- [ ] 估清管理
- [ ] 打印功能

**交付物**:
- 测试报告
- 部署文档
- 用户手册
- 生产系统

---

## 6. 数据库迁移策略 (Database Migration Strategy)

### 6.1 原则

- ✅ **零数据丢失**
- ✅ **向后兼容**
- ✅ **可回滚**
- ✅ **版本化管理**

### 6.2 Migration文件

**001_add_indexes.sql**:
```sql
-- POS Refactoring: Add performance indexes
-- Date: 2026-01-03

-- pos_invoices
ALTER TABLE pos_invoices ADD INDEX idx_store_issued (store_id, issued_at);
ALTER TABLE pos_invoices ADD INDEX idx_series_number (series, number);

-- pos_members
ALTER TABLE pos_members ADD INDEX idx_phone (phone_number);
ALTER TABLE pos_members ADD INDEX idx_active (is_active);

-- pos_shifts
ALTER TABLE pos_shifts ADD INDEX idx_store_status (store_id, status);
ALTER TABLE pos_shifts ADD INDEX idx_user_active (user_id, status);

-- pos_product_availability
ALTER TABLE pos_product_availability ADD INDEX idx_store_sold_out (store_id, is_sold_out);
```

**002_add_constraints.sql**:
```sql
-- POS Refactoring: Add foreign key constraints
-- Date: 2026-01-03

-- pos_invoices → kds_stores
ALTER TABLE pos_invoices
ADD CONSTRAINT fk_invoice_store
FOREIGN KEY (store_id) REFERENCES kds_stores(id)
ON DELETE RESTRICT;

-- pos_members → pos_member_levels
ALTER TABLE pos_members
ADD CONSTRAINT fk_member_level
FOREIGN KEY (member_level_id) REFERENCES pos_member_levels(id)
ON DELETE RESTRICT;

-- ... 其他外键 ...
```

### 6.3 执行流程

```bash
# 1. 备份数据库
mysqldump -u root -p mhdlmskv3gjbpqv3 > backup_before_refactor.sql

# 2. 执行migration
mysql -u root -p mhdlmskv3gjbpqv3 < 001_add_indexes.sql
mysql -u root -p mhdlmskv3gjbpqv3 < 002_add_constraints.sql

# 3. 验证
mysql -u root -p -e "SHOW INDEX FROM pos_invoices;" mhdlmskv3gjbpqv3
```

---

## 7. 文件迁移映射表 (File Migration Mapping)

| 旧文件 (Old) | 新文件 (New) | 操作 | 说明 |
|--------------|--------------|------|------|
| `pos_backend/core/config.php` | `src/pos/Config/Database.php` + `.env` | 重构 | 分离配置 |
| `pos_backend/core/pos_auth_core.php` | `src/pos/Middleware/AuthMiddleware.php` | 重构 | 中间件模式 |
| `pos_backend/core/pos_api_core.php` | `src/pos/Core/Router.php` | 重构 | 现代路由 |
| `pos_backend/core/invoicing_guard.php` | `src/pos/Middleware/InvoicingMiddleware.php` | 迁移 | 保留逻辑 |
| `pos_backend/helpers/pos_helper.php` | `src/pos/Helpers/` (拆分) | 重构 | 拆分为多个助手 |
| `pos_backend/helpers/pos_repo.php` | `src/pos/Repositories/` (拆分) | 重构 | Repository模式 |
| `pos_backend/helpers/pos_json_helper.php` | `src/pos/Helpers/ResponseHelper.php` | 迁移 | 改名+命名空间 |
| `pos_backend/helpers/pos_datetime_helper.php` | `src/pos/Helpers/DateTimeHelper.php` | 迁移 | 改名+命名空间 |
| `pos_backend/services/PromotionEngine.php` | `src/pos/Services/PromotionEngine.php` | 迁移 | 加命名空间 |
| `html/pos/api/registries/pos_registry.php` | `src/pos/Controllers/` (拆分) | 重构 | 控制器模式 |
| `html/pos/api/registries/pos_registry_sales.php` | `src/pos/Controllers/OrderController.php` | 重构 | 控制器 |
| `html/pos/api/registries/pos_registry_ops_shift.php` | `src/pos/Controllers/ShiftController.php` | 重构 | 控制器 |
| `html/pos/api/registries/pos_registry_ops_eod.php` | `src/pos/Controllers/EODController.php` | 重构 | 控制器 |
| `html/pos/api/registries/pos_registry_member_pass.php` | `src/pos/Controllers/MemberController.php` + `PassController.php` | 重构 | 拆分控制器 |
| `html/pos/api/pos_api_gateway.php` | `public/pos/api/gateway.php` | 重构 | 简化网关 |
| `html/pos/api/pos_login_handler.php` | `src/pos/Controllers/AuthController.php::login()` | 重构 | 控制器方法 |
| `html/pos/index.php` (54KB) | `public/pos/views/*.php` (拆分) | 重构 | 拆分视图 |
| `html/pos/login.php` | `public/pos/login.php` | 更新 | 使用SessionManager |
| `html/pos/logout.php` | `public/pos/logout.php` | 更新 | 使用SessionManager |

**统计**:
- 旧文件数: ~40个
- 新文件数: ~60个 (拆分+新增)
- 重构文件: ~25个
- 迁移文件: ~10个
- 新增文件: ~25个

---

## 8. 测试策略 (Testing Strategy)

### 8.1 单元测试

**工具**: PHPUnit 9.x

**覆盖范围**:
- ✅ 所有Repository类方法
- ✅ 所有Service类方法
- ✅ 关键Helper函数

**示例** (OrderRepositoryTest):
```php
<?php
namespace Pos\Tests\Unit\Repositories;

use PHPUnit\Framework\TestCase;
use Pos\Repositories\OrderRepository;

class OrderRepositoryTest extends TestCase {
    private $db;
    private $repo;

    protected function setUp(): void {
        $this->db = $this->createMock(\PDO::class);
        $this->repo = new OrderRepository($this->db);
    }

    public function testAllocateInvoiceNumber() {
        // Mock PDO prepare/execute
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->willReturn(true);

        $this->db->expects($this->once())
                 ->method('prepare')
                 ->willReturn($stmt);

        // 测试逻辑
        [$series, $number] = $this->repo->allocateInvoiceNumber('S1', 'TICKETBAI');

        $this->assertStringStartsWith('S1Y', $series);
        $this->assertGreaterThan(0, $number);
    }
}
```

**目标覆盖率**: 70%

---

### 8.2 集成测试

**工具**: PHPUnit + MySQL测试数据库

**测试场景**:
1. 完整订单流程 (登录 → 开班 → 下单 → 关班 → 日结)
2. 会员流程 (创建 → 查找 → 积分兑换)
3. 次卡流程 (售卡 → 核销)
4. 错误处理 (无班次下单 → 拒绝)

---

### 8.3 性能测试

**工具**: Apache JMeter

**测试指标**:
- API响应时间 < 200ms
- 并发订单提交 (50并发)
- 数据库查询优化验证

---

### 8.4 安全测试

**测试项**:
- [ ] CSRF token验证
- [ ] SQL注入防护 (prepared statements)
- [ ] XSS防护 (输入清理)
- [ ] Session固定攻击防护
- [ ] 速率限制生效

**工具**: OWASP ZAP

---

## 9. 风险控制 (Risk Control)

### 9.1 技术风险

| 风险 | 等级 | 缓解措施 |
|------|------|----------|
| 数据迁移失败 | 🟢 低 | 无需迁移，复用现有数据库 |
| API兼容性 | 🟡 中 | 保留旧端点代理，逐步迁移 |
| Session不兼容 | 🟢 低 | 保持变量名一致 |
| 性能下降 | 🟡 中 | 性能测试+索引优化 |
| Bug引入 | 🟡 中 | 单元测试+集成测试 |

---

### 9.2 业务风险

| 风险 | 等级 | 缓解措施 |
|------|------|----------|
| 停机时间 | 🔴 高 | 零停机部署 (蓝绿部署) |
| 业务中断 | 🔴 高 | 渐进式迁移 |
| 用户培训 | 🟢 低 | UI无变化，无需培训 |
| 数据丢失 | 🔴 高 | 多重备份+回滚方案 |

---

### 9.3 风险应对计划

**部署前**:
1. 完整数据库备份
2. 旧代码备份 (Git tag)
3. 准备回滚脚本

**部署中**:
1. 灰度发布 (10% → 50% → 100%)
2. 实时监控错误率
3. 准备快速回滚

**部署后**:
1. 24小时监控
2. 用户反馈收集
3. 性能指标对比

---

## 10. 回滚方案 (Rollback Plan)

### 10.1 代码回滚

```bash
# 1. 切换到旧版本Git tag
git checkout pos-v1.0-stable

# 2. 重新部署旧代码
cp -r store/store_html/pos_backend /var/www/html/pos_backend
cp -r store/store_html/html/pos /var/www/html/pos

# 3. 重启Web服务器
sudo systemctl restart apache2
```

---

### 10.2 数据库回滚

```bash
# 1. 回滚migration (如果执行了)
mysql -u root -p mhdlmskv3gjbpqv3 < rollback_migrations.sql

# rollback_migrations.sql内容:
-- DROP INDEX idx_store_issued ON pos_invoices;
-- ALTER TABLE pos_invoices DROP FOREIGN KEY fk_invoice_store;

# 2. 恢复备份 (最后手段)
mysql -u root -p mhdlmskv3gjbpqv3 < backup_before_refactor.sql
```

---

### 10.3 回滚决策标准

**触发回滚条件**:
- 🔴 严重: 系统无法启动
- 🔴 严重: 数据丢失
- 🔴 严重: 核心功能失效 (下单/支付)
- 🟡 中等: 性能下降>50%
- 🟡 中等: 错误率>5%

**不回滚条件**:
- 🟢 轻微: UI样式问题
- 🟢 轻微: 非核心功能失效
- 🟢 轻微: 日志格式变化

---

## 11. 成功标准 (Success Criteria)

### 11.1 功能标准

- [ ] 所有现有功能100%可用
- [ ] 无数据丢失
- [ ] 无业务中断
- [ ] API响应时间<200ms
- [ ] 错误率<0.1%

### 11.2 代码质量标准

- [ ] 单元测试覆盖率>70%
- [ ] 无Critical/High安全漏洞
- [ ] 代码符合PSR-12规范
- [ ] 技术债务减少50%

### 11.3 文档标准

- [ ] API文档完整
- [ ] 部署文档完整
- [ ] 代码注释覆盖>60%
- [ ] 用户手册更新

---

## 12. 时间线 (Timeline)

### 12.1 完整时间线

| 阶段 | 任务 | 工时 (天) | 开始日期 | 结束日期 |
|------|------|----------|---------|---------|
| 0 | 准备工作 | 2 | 2026-01-06 | 2026-01-07 |
| 1 | 核心基础设施 | 5 | 2026-01-08 | 2026-01-14 |
| 2 | 配置迁移 | 3 | 2026-01-15 | 2026-01-17 |
| 3 | 认证与中间件 | 4 | 2026-01-20 | 2026-01-23 |
| 4 | 数据访问层 | 7 | 2026-01-24 | 2026-02-01 |
| 5 | 控制器层 | 10 | 2026-02-02 | 2026-02-13 |
| 6 | 服务层 | 5 | 2026-02-14 | 2026-02-20 |
| 7 | 路由与网关 | 3 | 2026-02-21 | 2026-02-25 |
| 8 | 前端重构 | 7 | 2026-02-26 | 2026-03-06 |
| 9 | 数据库优化 | 3 | 2026-03-07 | 2026-03-11 |
| 10 | 测试与部署 | 7 | 2026-03-12 | 2026-03-20 |

**总工期**: **56个工作日** (约2.5个月)

---

### 12.2 里程碑

- 🎯 **M1** (2026-01-14): 核心基础设施完成
- 🎯 **M2** (2026-01-23): 认证系统完成
- 🎯 **M3** (2026-02-13): 控制器层完成
- 🎯 **M4** (2026-02-25): 路由系统完成
- 🎯 **M5** (2026-03-06): 前端重构完成
- 🎯 **M6** (2026-03-20): 系统上线

---

## 13. 团队与职责 (Team and Responsibilities)

| 角色 | 负责人 | 职责 |
|------|--------|------|
| **技术架构师** | Claude | 架构设计、代码审查 |
| **后端工程师** | 待定 | 控制器/服务/Repository实现 |
| **前端工程师** | 待定 | 视图拆分、JS重构 |
| **测试工程师** | 待定 | 单元测试、集成测试 |
| **DBA** | 待定 | 数据库迁移、性能优化 |
| **项目经理** | 待定 | 进度管理、风险控制 |

---

## 14. 附录 (Appendix)

### 14.1 参考文档

- [KDS重构实施记录](../KDS_REFACTORING_NOTES.md) (如有)
- [POS系统审计报告](./POS_SYSTEM_AUDIT_REPORT.md)
- [PSR-4自动加载规范](https://www.php-fig.org/psr/psr-4/)
- [PSR-12编码规范](https://www.php-fig.org/psr/psr-12/)

---

### 14.2 关键决策记录

| 日期 | 决策 | 原因 |
|------|------|------|
| 2026-01-03 | 采用与KDS相同架构 | 保持系统一致性 |
| 2026-01-03 | 保持SHA256密码哈希 | 用户要求 |
| 2026-01-03 | 不迁移数据库schema | 向后兼容 |
| 2026-01-03 | 采用PSR-4自动加载 | 现代化标准 |

---

### 14.3 待确认事项

- [ ] 生产环境部署时间窗口
- [ ] 是否创建pos_cash_movements表
- [ ] 前端框架是否升级 (Bootstrap 4 → 5)
- [ ] 是否实施代码压缩/打包

---

**计划制定日期**: 2026-01-03
**计划版本**: v1.0
**审批**: 待审批

---

**END OF REFACTORING PLAN**
