<?php
/**
 * TopTea POS - CSRF Protection Helper
 *
 * Provides CSRF token generation and validation
 *
 * @author TopTea Engineering Team
 * @version 1.0.0
 * @date 2026-01-03
 */

namespace TopTea\POS\Helpers;

class CSRFHelper
{
    private const TOKEN_SESSION_KEY = 'pos_csrf_token';
    private const TOKEN_EXPIRY_KEY = 'pos_csrf_token_expiry';

    /**
     * Generate a new CSRF token
     *
     * @return string The generated token
     */
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate a new random token
        $token = bin2hex(random_bytes(32));

        // Store in session
        $_SESSION[self::TOKEN_SESSION_KEY] = $token;

        // Set expiry time (default: 1 hour)
        $expirySeconds = (int)($_ENV['CSRF_TOKEN_EXPIRY'] ?? 3600);
        $_SESSION[self::TOKEN_EXPIRY_KEY] = time() + $expirySeconds;

        return $token;
    }

    /**
     * Get the current CSRF token (generate if doesn't exist)
     *
     * @return string The CSRF token
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if token exists and is not expired
        if (self::isTokenExpired()) {
            return self::generateToken();
        }

        // Return existing token or generate new one
        return $_SESSION[self::TOKEN_SESSION_KEY] ?? self::generateToken();
    }

    /**
     * Validate a CSRF token
     *
     * @param string $token The token to validate
     * @return bool True if valid, false otherwise
     */
    public static function validateToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if token exists in session
        if (!isset($_SESSION[self::TOKEN_SESSION_KEY])) {
            return false;
        }

        // Check if token is expired
        if (self::isTokenExpired()) {
            return false;
        }

        // Compare tokens using timing-safe comparison
        return hash_equals($_SESSION[self::TOKEN_SESSION_KEY], $token);
    }

    /**
     * Generate a hidden input field with CSRF token
     *
     * @param string $fieldName The name attribute for the input field
     * @return string HTML input field
     */
    public static function getTokenField(string $fieldName = 'csrf_token'): string
    {
        $token = self::getToken();
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Check if the current token is expired
     *
     * @return bool True if expired or doesn't exist
     */
    private static function isTokenExpired(): bool
    {
        if (!isset($_SESSION[self::TOKEN_EXPIRY_KEY])) {
            return true;
        }

        return time() > $_SESSION[self::TOKEN_EXPIRY_KEY];
    }

    /**
     * Regenerate the CSRF token (call after successful form submission)
     *
     * @return string The new token
     */
    public static function regenerateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear old token
        unset($_SESSION[self::TOKEN_SESSION_KEY]);
        unset($_SESSION[self::TOKEN_EXPIRY_KEY]);

        // Generate new token
        return self::generateToken();
    }
}
