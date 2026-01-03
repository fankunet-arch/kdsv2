<?php
/**
 * Toptea Store - POS
 * Backend Login Handler for POS
 * Engineer: Gemini | Date: 2025-10-30
 * Revision: 2.0 (Security Refactor - CSRF + Rate Limiting)
 *
 * [SECURITY FIX 2026-01-03]
 * - Added CSRF token validation
 * - Added login rate limiting (5 attempts per 15 minutes)
 * - Removed @session_start() (will be replaced with SessionManager in Phase 2)
 */

@session_start();
require_once realpath(__DIR__ . '/../../../pos_backend/core/config.php');
require_once realpath(__DIR__ . '/../../../../../src/pos/Helpers/CSRFHelper.php');

use TopTea\POS\Helpers\CSRFHelper;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

// --- CSRF Token Validation ---
$csrfToken = $_POST['csrf_token'] ?? '';
if (!CSRFHelper::validateToken($csrfToken)) {
    error_log("POS Login: CSRF token validation failed. IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    header('Location: ../login.php?error=csrf');
    exit;
}

// Get login credentials
$store_code = trim($_POST['store_code'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($store_code) || empty($username) || empty($password)) {
    header('Location: ../login.php?error=1');
    exit;
}

// Get client IP address
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

try {
    // --- Rate Limiting: Check login attempts ---
    $maxAttempts = (int)DotEnv::get('LOGIN_MAX_ATTEMPTS', 5);
    $lockoutMinutes = (int)DotEnv::get('LOGIN_LOCKOUT_MINUTES', 15);

    // Calculate lockout window (15 minutes ago in UTC)
    $lockoutWindow = date('Y-m-d H:i:s', strtotime("-{$lockoutMinutes} minutes"));

    // Check failed attempts in the last 15 minutes
    $stmt_check = $pdo->prepare("
        SELECT COUNT(*) as attempt_count
        FROM login_attempts
        WHERE username = ?
          AND ip_address = ?
          AND success = 0
          AND attempted_at >= ?
    ");
    $stmt_check->execute([$username, $ip_address, $lockoutWindow]);
    $result = $stmt_check->fetch();
    $attemptCount = (int)($result['attempt_count'] ?? 0);

    if ($attemptCount >= $maxAttempts) {
        // Rate limit exceeded
        error_log("POS Login: Rate limit exceeded for user '{$username}' from IP {$ip_address}");
        header('Location: ../login.php?error=rate_limit');
        exit;
    }

    // --- Proceed with authentication ---

    // 1. Find the store
    $stmt_store = $pdo->prepare("SELECT id, store_name FROM kds_stores WHERE store_code = ? AND deleted_at IS NULL");
    $stmt_store->execute([$store_code]);
    $store = $stmt_store->fetch();

    if ($store) {
        // 2. Find the user within that store
        $stmt_user = $pdo->prepare("SELECT id, username, password_hash, display_name, role FROM kds_users WHERE username = ? AND store_id = ? AND deleted_at IS NULL");
        $stmt_user->execute([$username, $store['id']]);
        $user = $stmt_user->fetch();

        // 3. Verify password
        if ($user && hash_equals($user['password_hash'], hash('sha256', $password))) {
            // --- Login Successful ---

            // Record successful login attempt
            $stmt_success = $pdo->prepare("
                INSERT INTO login_attempts (username, ip_address, attempted_at, success)
                VALUES (?, ?, UTC_TIMESTAMP(6), 1)
            ");
            $stmt_success->execute([$username, $ip_address]);

            // Clear failed attempts for this user/IP
            $stmt_clear = $pdo->prepare("
                DELETE FROM login_attempts
                WHERE username = ?
                  AND ip_address = ?
                  AND success = 0
            ");
            $stmt_clear->execute([$username, $ip_address]);

            // Regenerate session ID for security
            session_regenerate_id(true);

            // Set session variables
            $_SESSION['pos_logged_in'] = true;
            $_SESSION['pos_user_id'] = $user['id'];
            $_SESSION['pos_display_name'] = $user['display_name'];
            $_SESSION['pos_store_id'] = $store['id'];
            $_SESSION['pos_store_name'] = $store['store_name'];
            $_SESSION['pos_user_role'] = $user['role'];

            // Regenerate CSRF token after successful login
            CSRFHelper::regenerateToken();

            // Update last login timestamp
            $pdo->prepare("UPDATE kds_users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$user['id']]);

            // --- [估清 需求2] 每日自动重置 ---
            try {
                // 1. 获取门店的 EOD 截止时间 (e.g., 3)
                $stmt_cutoff = $pdo->prepare("SELECT eod_cutoff_hour FROM kds_stores WHERE id = ?");
                $stmt_cutoff->execute([$store['id']]);
                $cutoff_hour = (int)($stmt_cutoff->fetchColumn() ?: 3);

                // 2. 计算当前的"营业日"
                $tz = new DateTimeZone('Europe/Madrid');
                $current_business_date = (new DateTime('now', $tz))
                                         ->modify("-{$cutoff_hour} hours")
                                         ->format('Y-m-d');

                // 3. 检查 `pos_daily_tracking` 表
                $stmt_track = $pdo->prepare("SELECT last_daily_reset_business_date FROM pos_daily_tracking WHERE store_id = ?");
                $stmt_track->execute([$store['id']]);
                $last_reset_date = $stmt_track->fetchColumn();

                if ($last_reset_date !== $current_business_date) {
                    // 4. 这是今天第一次登录，执行重置

                    // 4a. [需求2] 重置所有估清状态
                    $pdo->prepare("DELETE FROM pos_product_availability WHERE store_id = ?")
                        ->execute([$store['id']]);

                    // 4b. [需求3] 清除上一日的交接班快照
                    $sql_update_tracking = "
                        INSERT INTO pos_daily_tracking (store_id, last_daily_reset_business_date, sold_out_state_snapshot, snapshot_taken_at)
                        VALUES (?, ?, NULL, NULL)
                        ON DUPLICATE KEY UPDATE
                            last_daily_reset_business_date = VALUES(last_daily_reset_business_date),
                            sold_out_state_snapshot = VALUES(sold_out_state_snapshot),
                            snapshot_taken_at = VALUES(snapshot_taken_at)
                    ";
                    $pdo->prepare($sql_update_tracking)
                        ->execute([$store['id'], $current_business_date]);
                }

            } catch (Throwable $e) {
                // 即使重置失败，也不应阻止登录
                error_log("CRITICAL: Daily reset for store {$store['id']} failed: " . $e->getMessage());
            }
            // --- [估清 需求2] 结束 ---

            // Redirect to POS main page
            header('Location: ../index.php');
            exit;
        }
    }

    // --- Login Failed ---

    // Record failed login attempt
    $stmt_fail = $pdo->prepare("
        INSERT INTO login_attempts (username, ip_address, attempted_at, success)
        VALUES (?, ?, UTC_TIMESTAMP(6), 0)
    ");
    $stmt_fail->execute([$username, $ip_address]);

    // Log failed attempt
    error_log("POS Login: Failed login attempt for user '{$username}' from IP {$ip_address}");

    // Redirect back with error
    header('Location: ../login.php?error=1');
    exit;

} catch (PDOException $e) {
    error_log("POS Login Error: " . $e->getMessage());
    header('Location: ../login.php?error=1');
    exit;
}
