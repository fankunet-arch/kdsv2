<?php
/**
 * TopTea KDS - Authentication Guard
 *
 * Protects routes and enforces authentication
 *
 * @author TopTea Engineering Team
 * @version 1.0.0
 * @date 2026-01-03
 */

namespace TopTea\KDS\Auth;

use TopTea\KDS\Core\SessionManager;
use TopTea\KDS\Core\Logger;

class AuthGuard
{
    /**
     * Require user to be authenticated, redirect to login if not
     */
    public static function requireAuth(): void
    {
        SessionManager::init();

        if (!SessionManager::isLoggedIn()) {
            Logger::warning('Unauthorized access attempt', [
                'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            SessionManager::destroy();
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Require specific role
     *
     * @param string $requiredRole Required role (e.g., 'manager')
     */
    public static function requireRole(string $requiredRole): void
    {
        self::requireAuth();

        $userRole = SessionManager::getUserRole();

        // Manager has access to everything
        if ($userRole === 'manager') {
            return;
        }

        if ($userRole !== $requiredRole) {
            Logger::warning('Insufficient permissions', [
                'user_id' => SessionManager::getUserId(),
                'user_role' => $userRole,
                'required_role' => $requiredRole
            ]);

            http_response_code(403);
            die('Access Denied: Insufficient permissions');
        }
    }

    /**
     * Check if user is logged in (non-blocking)
     *
     * @return bool
     */
    public static function check(): bool
    {
        SessionManager::init();
        return SessionManager::isLoggedIn();
    }

    /**
     * Get current authenticated user info
     *
     * @return array|null
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => SessionManager::getUserId(),
            'username' => SessionManager::get('kds_username'),
            'display_name' => SessionManager::get('kds_display_name'),
            'role' => SessionManager::getUserRole(),
            'store_id' => SessionManager::getStoreId(),
            'store_name' => SessionManager::get('kds_store_name')
        ];
    }
}
