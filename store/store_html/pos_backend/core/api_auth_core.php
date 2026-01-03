<?php
/**
 * TopTea POS · API Auth Core
 * v1.0.1
 *
 * [SECURITY UPDATE 2026-01-03]
 * - Replaced @session_start() with SessionManager::start()
 */

require_once realpath(__DIR__ . '/../../../../src/pos/Core/SessionManager.php');
use TopTea\POS\Core\SessionManager;

// Start session using SessionManager
SessionManager::start();

/** 统一 JSON 错误返回 */
function api_auth_fail(string $msg = 'Unauthorized'): void {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/** 基础鉴权：必须已登录，且会话里有门店与用户信息 */
if (!isset($_SESSION['pos_logged_in']) || $_SESSION['pos_logged_in'] !== true) {
    api_auth_fail('Unauthorized: please login.');
}
if (!isset($_SESSION['pos_user_id'], $_SESSION['pos_store_id'])) {
    api_auth_fail('Unauthorized: invalid session.');
}

/** 禁止缓存，防止奇怪的会话行为 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
