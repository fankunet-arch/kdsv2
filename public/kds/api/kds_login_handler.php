<?php
/**
 * TopTea KDS - Login Handler
 *
 * Handles user authentication with security features:
 * - Rate limiting
 * - CSRF protection
 * - Secure session management
 * - Audit logging
 *
 * @author TopTea Engineering Team
 * @version 2.0.0 (Refactored)
 * @date 2026-01-03
 */

require_once __DIR__ . '/../../../src/kds/Config/config.php';

use TopTea\KDS\Core\SessionManager;
use TopTea\KDS\Core\Logger;
use TopTea\KDS\Helpers\CsrfHelper;
use TopTea\KDS\Helpers\InputValidator;

// Initialize session
SessionManager::init();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

// CSRF Protection
$csrf_token = $_POST['csrf_token'] ?? '';
if (!CsrfHelper::verifyToken($csrf_token)) {
    Logger::warning('Login CSRF validation failed', ['ip' => $_SERVER['REMOTE_ADDR']]);
    header('Location: ../login.php?error=csrf');
    exit;
}

// Get and validate input
$store_code = trim($_POST['store_code'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($store_code) || empty($username) || empty($password)) {
    header('Location: ../login.php?error=missing_fields');
    exit;
}

// Validate input format
if (!InputValidator::validateStoreCode($store_code) || !InputValidator::validateUsername($username)) {
    Logger::warning('Login invalid input format', [
        'store_code' => $store_code,
        'username' => $username,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    header('Location: ../login.php?error=invalid_format');
    exit;
}

// Rate limiting check
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
try {
    $stmt_attempts = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE (username = ? OR ip_address = ?)
         AND attempted_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
         AND success = 0"
    );
    $stmt_attempts->execute([$username, $ip_address]);
    $attempt_count = $stmt_attempts->fetchColumn();

    if ($attempt_count >= 5) {
        Logger::warning('Rate limit exceeded', [
            'username' => $username,
            'ip' => $ip_address,
            'attempts' => $attempt_count
        ]);
        header('Location: ../login.php?error=rate_limit');
        exit;
    }
} catch (\PDOException $e) {
    Logger::error('Failed to check rate limit', ['error' => $e->getMessage()]);
    // Continue anyway (fail open for availability)
}

try {
    // Find store
    $stmt_store = $pdo->prepare(
        "SELECT id, store_name FROM kds_stores
         WHERE store_code = ? AND is_active = 1 AND deleted_at IS NULL"
    );
    $stmt_store->execute([$store_code]);
    $store = $stmt_store->fetch();

    if ($store) {
        // Find user within store
        $stmt_user = $pdo->prepare(
            "SELECT id, username, password_hash, display_name, role
             FROM kds_users
             WHERE username = ? AND store_id = ? AND is_active = 1 AND deleted_at IS NULL"
        );
        $stmt_user->execute([$username, $store['id']]);
        $user = $stmt_user->fetch();

        // Verify password (using SHA256 as per current system - NOT CHANGED per requirements)
        if ($user && hash_equals($user['password_hash'], hash('sha256', $password))) {
            // ===== LOGIN SUCCESSFUL =====

            // Clear failed attempts
            try {
                $pdo->prepare("DELETE FROM login_attempts WHERE username = ? OR ip_address = ?")
                    ->execute([$username, $ip_address]);
            } catch (\PDOException $e) {
                Logger::error('Failed to clear login attempts', ['error' => $e->getMessage()]);
            }

            // Record successful login
            try {
                $pdo->prepare(
                    "INSERT INTO login_attempts (username, ip_address, user_agent, success)
                     VALUES (?, ?, ?, 1)"
                )->execute([$username, $ip_address, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            } catch (\PDOException $e) {
                Logger::error('Failed to record successful login', ['error' => $e->getMessage()]);
            }

            // Regenerate session ID (prevent session fixation)
            SessionManager::regenerate(true);

            // Set session variables
            SessionManager::set('kds_logged_in', true);
            SessionManager::set('kds_user_id', $user['id']);
            SessionManager::set('kds_username', $user['username']);
            SessionManager::set('kds_display_name', $user['display_name']);
            SessionManager::set('kds_user_role', $user['role']);
            SessionManager::set('kds_store_id', $store['id']);
            SessionManager::set('kds_store_name', $store['store_name']);

            // Regenerate CSRF token after login
            CsrfHelper::regenerateToken();

            // Update last login timestamp
            $pdo->prepare("UPDATE kds_users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$user['id']]);

            Logger::info('User logged in successfully', [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'store_id' => $store['id']
            ]);

            // Redirect to main page
            header('Location: ../index.php');
            exit;
        }
    }

    // ===== LOGIN FAILED =====

    // Record failed attempt
    try {
        $pdo->prepare(
            "INSERT INTO login_attempts (username, ip_address, user_agent, success)
             VALUES (?, ?, ?, 0)"
        )->execute([$username, $ip_address, $_SERVER['HTTP_USER_AGENT'] ?? null]);
    } catch (\PDOException $e) {
        Logger::error('Failed to record login attempt', ['error' => $e->getMessage()]);
    }

    Logger::warning('Login failed', [
        'username' => $username,
        'store_code' => $store_code,
        'ip' => $ip_address
    ]);

    header('Location: ../login.php?error=invalid_credentials');
    exit;

} catch (\PDOException $e) {
    Logger::error('Login database error', ['error' => $e->getMessage()]);
    header('Location: ../login.php?error=system_error');
    exit;
}
