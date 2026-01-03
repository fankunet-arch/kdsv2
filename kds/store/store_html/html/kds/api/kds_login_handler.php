<?php
/**
 * Toptea Store - KDS
 * Backend Login Handler for KDS
 * Engineer: Gemini | Date: 2025-10-23
 */

// [FIX] Secure session start
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

require_once realpath(__DIR__ . '/../../../kds/core/config.php');
require_once realpath(__DIR__ . '/../../../kds/helpers/csrf_helper.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

// [FIX] CSRF Protection
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf_token)) {
    error_log('KDS Login: CSRF token validation failed');
    header('Location: ../login.php?error=csrf');
    exit;
}

$store_code = trim($_POST['store_code'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($store_code) || empty($username) || empty($password)) {
    header('Location: ../login.php?error=1');
    exit;
}

// [FIX] Rate Limiting - Check login attempts
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
try {
    $stmt_attempts = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE (username = ? OR ip_address = ?)
         AND attempted_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)"
    );
    $stmt_attempts->execute([$username, $ip_address]);
    $attempt_count = $stmt_attempts->fetchColumn();

    if ($attempt_count >= 5) {
        error_log("KDS Login: Rate limit exceeded for $username from $ip_address");
        header('Location: ../login.php?error=rate_limit');
        exit;
    }
} catch (PDOException $e) {
    error_log("KDS Login: Failed to check rate limit: " . $e->getMessage());
    // Continue with login attempt even if rate limit check fails
}

try {
    // 1. Find the store
    $stmt_store = $pdo->prepare("SELECT id, store_name FROM kds_stores WHERE store_code = ? AND is_active = 1 AND deleted_at IS NULL");
    $stmt_store->execute([$store_code]);
    $store = $stmt_store->fetch();

    if ($store) {
        // 2. Find the user within that store
        $stmt_user = $pdo->prepare("SELECT id, username, password_hash, display_name, role FROM kds_users WHERE username = ? AND store_id = ? AND is_active = 1 AND deleted_at IS NULL");
        $stmt_user->execute([$username, $store['id']]);
        $user = $stmt_user->fetch();

        if ($user && hash_equals($user['password_hash'], hash('sha256', $password))) {
            // --- Login Successful ---

            // [FIX] Clear login attempts on successful login
            try {
                $pdo->prepare("DELETE FROM login_attempts WHERE username = ? OR ip_address = ?")
                    ->execute([$username, $ip_address]);
            } catch (PDOException $e) {
                error_log("KDS Login: Failed to clear login attempts: " . $e->getMessage());
            }

            session_regenerate_id(true);
            $_SESSION['kds_logged_in'] = true;
            $_SESSION['kds_user_id'] = $user['id'];
            $_SESSION['kds_username'] = $user['username'];
            $_SESSION['kds_display_name'] = $user['display_name'];
            $_SESSION['kds_user_role'] = $user['role']; // [FIX] Set user role to session
            $_SESSION['kds_store_id'] = $store['id'];
            $_SESSION['kds_store_name'] = $store['store_name'];

            // Update last login
            $pdo->prepare("UPDATE kds_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);

            // Redirect to KDS main page
            header('Location: ../index.php');
            exit;
        }
    }

    // [FIX] Record failed login attempt
    try {
        $pdo->prepare("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)")
            ->execute([$username, $ip_address]);
    } catch (PDOException $e) {
        error_log("KDS Login: Failed to record login attempt: " . $e->getMessage());
    }

    // If store or user not found, or password mismatch
    header('Location: ../login.php?error=1');
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ../login.php?error=1');
    exit;
}