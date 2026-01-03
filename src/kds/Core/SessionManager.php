<?php
/**
 * TopTea KDS - Unified Session Manager
 *
 * Centralized session management with security best practices
 *
 * @author TopTea Engineering Team
 * @version 1.0.0
 * @date 2026-01-03
 */

namespace TopTea\KDS\Core;

use TopTea\KDS\Config\DotEnv;

class SessionManager
{
    private static bool $initialized = false;

    /**
     * Initialize session with secure configuration
     *
     * @throws \RuntimeException if session cannot be started
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        // Only initialize if session not already started
        if (session_status() === PHP_SESSION_NONE) {
            // Configure session BEFORE starting it
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_lifetime', '0'); // Session cookie

            // Get session lifetime from env
            $lifetime = (int) DotEnv::get('SESSION_LIFETIME', '3600');
            ini_set('session.gc_maxlifetime', (string)$lifetime);

            // Enable secure cookie in production (requires HTTPS)
            if (DotEnv::get('APP_ENV') === 'production') {
                $cookieSecure = filter_var(
                    DotEnv::get('SESSION_COOKIE_SECURE', 'false'),
                    FILTER_VALIDATE_BOOLEAN
                );
                if ($cookieSecure) {
                    ini_set('session.cookie_secure', '1');
                }
            }

            // Start session
            if (!session_start()) {
                Logger::error('Failed to start session');
                throw new \RuntimeException('Session initialization failed');
            }

            Logger::debug('Session initialized', [
                'session_id' => session_id(),
                'lifetime' => $lifetime
            ]);
        }

        self::$initialized = true;
    }

    /**
     * Regenerate session ID (call after login/privilege escalation)
     *
     * @param bool $deleteOldSession Whether to delete old session data
     */
    public static function regenerate(bool $deleteOldSession = true): void
    {
        self::init();

        $oldSessionId = session_id();

        if (session_regenerate_id($deleteOldSession)) {
            Logger::info('Session regenerated', [
                'old_id' => $oldSessionId,
                'new_id' => session_id()
            ]);
        }
    }

    /**
     * Destroy current session (logout)
     */
    public static function destroy(): void
    {
        self::init();

        $sessionId = session_id();

        // Clear session data
        $_SESSION = [];

        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
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

        // Destroy session
        session_destroy();

        Logger::info('Session destroyed', ['session_id' => $sessionId]);
    }

    /**
     * Set a session value
     *
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, mixed $value): void
    {
        self::init();
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value
     *
     * @param string $key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::init();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session key exists
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::init();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session value
     *
     * @param string $key
     */
    public static function remove(string $key): void
    {
        self::init();
        unset($_SESSION[$key]);
    }

    /**
     * Check if user is logged in (KDS)
     *
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        self::init();
        return isset($_SESSION['kds_logged_in']) && $_SESSION['kds_logged_in'] === true;
    }

    /**
     * Get current user ID
     *
     * @return int|null
     */
    public static function getUserId(): ?int
    {
        self::init();
        return isset($_SESSION['kds_user_id']) ? (int)$_SESSION['kds_user_id'] : null;
    }

    /**
     * Get current store ID
     *
     * @return int|null
     */
    public static function getStoreId(): ?int
    {
        self::init();
        return isset($_SESSION['kds_store_id']) ? (int)$_SESSION['kds_store_id'] : null;
    }

    /**
     * Get current user role
     *
     * @return string|null
     */
    public static function getUserRole(): ?string
    {
        self::init();
        return $_SESSION['kds_user_role'] ?? null;
    }
}
