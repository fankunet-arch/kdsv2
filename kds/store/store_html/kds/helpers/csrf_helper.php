<?php
/**
 * Toptea KDS - CSRF Protection Helper
 * Date: 2026-01-03
 * Purpose: Provide CSRF token generation and validation
 */

if (!function_exists('generateCsrfToken')) {
    /**
     * Generate or retrieve CSRF token from session
     * @return string The CSRF token
     */
    function generateCsrfToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        } else {
            // Regenerate token if older than 1 hour
            $token_age = time() - ($_SESSION['csrf_token_time'] ?? 0);
            if ($token_age > 3600) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['csrf_token_time'] = time();
            }
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    /**
     * Verify CSRF token
     * @param string $token The token to verify
     * @return bool True if valid, false otherwise
     */
    function verifyCsrfToken(string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('csrfTokenField')) {
    /**
     * Generate HTML hidden input field for CSRF token
     * @return string HTML input field
     */
    function csrfTokenField(): string {
        $token = generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrfTokenMeta')) {
    /**
     * Generate HTML meta tag for CSRF token (for AJAX requests)
     * @return string HTML meta tag
     */
    function csrfTokenMeta(): string {
        $token = generateCsrfToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
