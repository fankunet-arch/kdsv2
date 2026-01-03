<?php
/**
 * TopTea POS - Session Manager
 * Version: 1.0.0
 * Date: 2026-01-03
 * Engineer: Claude
 *
 * 职责:
 * - 统一管理 POS 系统的 Session 初始化
 * - 提供安全的 Session 配置
 * - 避免重复的 session_start() 调用
 * - 集中管理 Session 相关的安全设置
 *
 * [SECURITY FEATURES]
 * - HttpOnly cookies (防止 XSS 窃取 session)
 * - Secure cookies in production (强制 HTTPS)
 * - SameSite=Strict (防止 CSRF)
 * - Session regeneration on privilege escalation
 * - Configurable session lifetime
 */

namespace TopTea\POS\Core;

class SessionManager
{
    /**
     * Session 是否已启动
     */
    private static bool $initialized = false;

    /**
     * Session 配置选项
     */
    private static array $config = [];

    /**
     * 初始化 Session
     *
     * @param array $options Session 配置选项
     * @return void
     */
    public static function start(array $options = []): void
    {
        // 如果 Session 已经启动，直接返回
        if (self::$initialized || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // 合并默认配置和传入的配置
        self::$config = array_merge(self::getDefaultConfig(), $options);

        // 设置 Session 参数
        self::configureSession();

        // 启动 Session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            self::$initialized = true;

            // 首次启动时，验证 Session 的有效性
            self::validateSession();
        }
    }

    /**
     * 获取默认配置
     *
     * @return array
     */
    private static function getDefaultConfig(): array
    {
        // 从环境变量读取配置，如果没有则使用默认值
        $cookieName = $_ENV['SESSION_COOKIE_NAME'] ?? getenv('SESSION_COOKIE_NAME') ?: 'POS_SESSION';
        $cookieLifetime = (int)($_ENV['SESSION_COOKIE_LIFETIME'] ?? getenv('SESSION_COOKIE_LIFETIME') ?: 0);
        $sessionLifetime = (int)($_ENV['SESSION_LIFETIME'] ?? getenv('SESSION_LIFETIME') ?: 7200); // 2 hours

        // 检测是否为生产环境 (HTTPS)
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || ($_SERVER['SERVER_PORT'] ?? 0) == 443
                    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        return [
            'cookie_name' => $cookieName,
            'cookie_lifetime' => $cookieLifetime, // 0 = until browser closes
            'cookie_path' => '/',
            'cookie_domain' => '',
            'cookie_secure' => $isSecure, // Only send over HTTPS in production
            'cookie_httponly' => true, // Prevent JavaScript access
            'cookie_samesite' => 'Strict', // CSRF protection
            'gc_maxlifetime' => $sessionLifetime, // Session data lifetime on server
            'use_strict_mode' => true, // Reject uninitialized session IDs
            'use_only_cookies' => true, // Don't accept session IDs from URL
        ];
    }

    /**
     * 配置 Session 参数
     *
     * @return void
     */
    private static function configureSession(): void
    {
        // 设置 Session 名称
        session_name(self::$config['cookie_name']);

        // 设置 Session Cookie 参数
        session_set_cookie_params([
            'lifetime' => self::$config['cookie_lifetime'],
            'path' => self::$config['cookie_path'],
            'domain' => self::$config['cookie_domain'],
            'secure' => self::$config['cookie_secure'],
            'httponly' => self::$config['cookie_httponly'],
            'samesite' => self::$config['cookie_samesite'],
        ]);

        // 设置其他 Session 配置
        ini_set('session.gc_maxlifetime', (string)self::$config['gc_maxlifetime']);
        ini_set('session.use_strict_mode', self::$config['use_strict_mode'] ? '1' : '0');
        ini_set('session.use_only_cookies', self::$config['use_only_cookies'] ? '1' : '0');
    }

    /**
     * 验证 Session 的有效性
     *
     * @return void
     */
    private static function validateSession(): void
    {
        // 检查 Session 是否过期
        if (isset($_SESSION['CREATED_AT'])) {
            $sessionAge = time() - $_SESSION['CREATED_AT'];
            if ($sessionAge > self::$config['gc_maxlifetime']) {
                // Session 过期，销毁并重新创建
                self::destroy();
                session_start();
                self::$initialized = true;
            }
        }

        // 记录 Session 创建时间（如果还没有）
        if (!isset($_SESSION['CREATED_AT'])) {
            $_SESSION['CREATED_AT'] = time();
        }

        // 定期更新最后活动时间
        $_SESSION['LAST_ACTIVITY'] = time();

        // 绑定 Session 到 IP 地址（可选，增强安全性）
        if (isset(self::$config['bind_to_ip']) && self::$config['bind_to_ip']) {
            $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
            if (isset($_SESSION['IP_ADDRESS'])) {
                if ($_SESSION['IP_ADDRESS'] !== $currentIp) {
                    // IP 地址变化，销毁 Session
                    self::destroy();
                    session_start();
                    self::$initialized = true;
                }
            }
            $_SESSION['IP_ADDRESS'] = $currentIp;
        }
    }

    /**
     * 重新生成 Session ID
     * 用于权限提升场景（如登录后）
     *
     * @param bool $deleteOldSession 是否删除旧的 Session 数据
     * @return bool
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $result = session_regenerate_id($deleteOldSession);

        if ($result) {
            // 更新创建时间
            $_SESSION['CREATED_AT'] = time();
            $_SESSION['LAST_ACTIVITY'] = time();
        }

        return $result;
    }

    /**
     * 销毁 Session
     *
     * @return void
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // 清空 Session 数据
            $_SESSION = [];

            // 删除 Session Cookie
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            // 销毁 Session
            session_destroy();
            self::$initialized = false;
        }
    }

    /**
     * 检查 Session 是否已启动
     *
     * @return bool
     */
    public static function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * 获取 Session 配置
     *
     * @param string|null $key 配置键名，null 返回所有配置
     * @return mixed
     */
    public static function getConfig(?string $key = null): mixed
    {
        if ($key === null) {
            return self::$config;
        }

        return self::$config[$key] ?? null;
    }

    /**
     * 设置 Session 值
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * 获取 Session 值
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * 检查 Session 键是否存在
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * 删除 Session 值
     *
     * @param string $key
     * @return void
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * 清空所有 Session 数据（但不销毁 Session）
     *
     * @return void
     */
    public static function clear(): void
    {
        self::start();
        $_SESSION = [];
    }

    // ========================================================================
    // Authentication Helper Methods (for AuthGuard compatibility)
    // ========================================================================

    /**
     * Initialize session (alias for start() for KDS compatibility)
     *
     * @return void
     */
    public static function init(): void
    {
        self::start();
    }

    /**
     * Check if user is logged in
     *
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        self::start();
        return isset($_SESSION['pos_logged_in']) && $_SESSION['pos_logged_in'] === true;
    }

    /**
     * Get current user ID
     *
     * @return int|null
     */
    public static function getUserId(): ?int
    {
        self::start();
        return isset($_SESSION['pos_user_id']) ? (int)$_SESSION['pos_user_id'] : null;
    }

    /**
     * Get current user role
     *
     * @return string|null
     */
    public static function getUserRole(): ?string
    {
        self::start();
        return $_SESSION['pos_user_role'] ?? null;
    }

    /**
     * Get current store ID
     *
     * @return int|null
     */
    public static function getStoreId(): ?int
    {
        self::start();
        return isset($_SESSION['pos_store_id']) ? (int)$_SESSION['pos_store_id'] : null;
    }

    /**
     * Get current shift ID
     *
     * @return int|null
     */
    public static function getShiftId(): ?int
    {
        self::start();
        return isset($_SESSION['pos_shift_id']) ? (int)$_SESSION['pos_shift_id'] : null;
    }
}
