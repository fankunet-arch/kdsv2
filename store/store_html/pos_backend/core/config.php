<?php
/**
 * Toptea POS - Core Configuration File
 * Engineer: Gemini | Date: 2025-10-24
 * Revision: 4.0 (Security Refactor - Environment Variables)
 *
 * [SECURITY FIX 2026-01-03]
 * - Removed hardcoded database credentials
 * - Implemented .env.pos configuration file
 * - Added DotEnv loader for secure configuration management
 */

// --- Load Environment Variables ---
require_once __DIR__ . '/../../../../../src/pos/Config/DotEnv.php';

use TopTea\POS\Config\DotEnv;

$dotenv = new DotEnv(__DIR__ . '/../../../../../');
$dotenv->load();

// --- [SECURITY FIX V2.0 + V4.0] ---
$logPath = DotEnv::get('LOG_PATH', __DIR__ . '/../../../../../storage/logs/pos/');
if (!is_dir($logPath)) {
    @mkdir($logPath, 0755, true);
}

ini_set('display_errors', DotEnv::get('APP_DEBUG', 'false') === 'true' ? '1' : '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logPath . 'php_errors_pos.log');
// --- [END FIX] ---

error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

// --- Database Configuration (From .env.pos) ---
$db_host = DotEnv::get('DB_HOST');
$db_name = DotEnv::get('DB_NAME');
$db_user = DotEnv::get('DB_USER');
$db_pass = DotEnv::get('DB_PASS');
$db_char = DotEnv::get('DB_CHARSET', 'utf8mb4');

// --- Application Settings ---
define('POS_BASE_URL', '/pos/'); // Relative base URL for the POS app

// --- Directory Paths ---
define('POS_ROOT_PATH', dirname(__DIR__));
define('POS_APP_PATH', POS_ROOT_PATH . '/app');
define('POS_CORE_PATH', POS_ROOT_PATH . '/core');
define('POS_PUBLIC_PATH', POS_ROOT_PATH . '/html');

// --- Database Connection (PDO) ---
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_char";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    // [A1 UTC SYNC] Set connection timezone to UTC
    $pdo->exec("SET time_zone='+00:00'");
    
} catch (\PDOException $e) {
    error_log("POS Database connection failed: " . $e->getMessage());
    // For POS, we must die cleanly in a way the frontend can parse
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(503); // Service Unavailable
    echo json_encode([
        'status' => 'error',
        'message' => 'DB Connection Error (POS)',
        'data' => null
    ]);
    exit;
}