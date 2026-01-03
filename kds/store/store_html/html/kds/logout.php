<?php
/**
 * Toptea Store - KDS Logout Handler
 * Updated: 2026-01-03 - Added audit logging
 */

session_start();

// [FIX] Record logout action to audit_logs
if (isset($_SESSION['kds_user_id']) && isset($_SESSION['kds_store_id'])) {
    try {
        require_once realpath(__DIR__ . '/../../kds/core/config.php');

        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (action, actor_user_id, actor_type, ip, ua, session_id, created_at)
             VALUES ('user.logout', ?, 'store_user', ?, ?, ?, UTC_TIMESTAMP())"
        );
        $stmt->execute([
            $_SESSION['kds_user_id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            session_id()
        ]);
    } catch (Exception $e) {
        error_log("KDS Logout: Failed to log logout: " . $e->getMessage());
        // Continue with logout even if audit log fails
    }
}

// Clear session
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