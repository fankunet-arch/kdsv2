<?php
/**
 * TopTea KDS - CSRF Protection Helper
 *
 * Provides CSRF token generation and validation
 *
 * @author TopTea Engineering Team
 * @version 2.0.0 (Refactored)
 * @date 2026-01-03
 */

namespace TopTea\KDS\Helpers;

use TopTea\KDS\Core\SessionManager;
use TopTea\KDS\Config\DotEnv;

class CsrfHelper
{
    private const TOKEN_KEY = 'csrf_token';
    private const TIME_KEY = 'csrf_token_time';

    /**
     * Generate or retrieve CSRF token from session
     *
     * @return string The CSRF token
     */
    public static function generateToken(): string
    {
        SessionManager::init();

        if (!SessionManager::has(self::TOKEN_KEY)) {
            self::regenerateToken();
        } else {
            // Regenerate token if older than configured expiry (default 1 hour)
            $tokenAge = time() - (SessionManager::get(self::TIME_KEY) ?? 0);
            $expiry = (int) DotEnv::get('CSRF_TOKEN_EXPIRY', '3600');

            if ($tokenAge > $expiry) {
                self::regenerateToken();
            }
        }

        return SessionManager::get(self::TOKEN_KEY);
    }

    /**
     * Regenerate CSRF token (call after critical operations)
     *
     * @return string New token
     */
    public static function regenerateToken(): string
    {
        SessionManager::init();

        $token = bin2hex(random_bytes(32));
        SessionManager::set(self::TOKEN_KEY, $token);
        SessionManager::set(self::TIME_KEY, time());

        return $token;
    }

    /**
     * Verify CSRF token
     *
     * @param string $token The token to verify
     * @return bool True if valid, false otherwise
     */
    public static function verifyToken(string $token): bool
    {
        SessionManager::init();

        if (!SessionManager::has(self::TOKEN_KEY)) {
            return false;
        }

        $sessionToken = SessionManager::get(self::TOKEN_KEY);

        return hash_equals($sessionToken, $token);
    }

    /**
     * Generate HTML hidden input field for CSRF token
     *
     * @return string HTML input field
     */
    public static function tokenField(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' .
               htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Generate HTML meta tag for CSRF token (for AJAX requests)
     *
     * @return string HTML meta tag
     */
    public static function tokenMeta(): string
    {
        $token = self::generateToken();
        return '<meta name="csrf-token" content="' .
               htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Get token for JavaScript/AJAX
     *
     * @return string Token value
     */
    public static function getToken(): string
    {
        return self::generateToken();
    }
}

// Backward compatibility: global functions (deprecated, use class methods)
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken(): string {
        return \TopTea\KDS\Helpers\CsrfHelper::generateToken();
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(string $token): bool {
        return \TopTea\KDS\Helpers\CsrfHelper::verifyToken($token);
    }
}

if (!function_exists('csrfTokenField')) {
    function csrfTokenField(): string {
        return \TopTea\KDS\Helpers\CsrfHelper::tokenField();
    }
}

if (!function_exists('csrfTokenMeta')) {
    function csrfTokenMeta(): string {
        return \TopTea\KDS\Helpers\CsrfHelper::tokenMeta();
    }
}
