<?php
/**
 * Toptea POS - Core Configuration File
 * Engineer: Gemini | Date: 2025-10-24
 * Revision: 5.0 (Error Handling & Logging)
 *
 * [SECURITY FIX 2026-01-03]
 * - Removed hardcoded database credentials
 * - Implemented .env.pos configuration file
 * - Added DotEnv loader for secure configuration management
 * - Added unified Logger and ErrorHandler (Phase 3)
 */

// --- Load Environment Variables ---
require_once __DIR__ . '/../../../../../src/pos/Config/DotEnv.php';
require_once __DIR__ . '/../../../../../src/pos/Helpers/Logger.php';
require_once __DIR__ . '/../../../../../src/pos/Helpers/ErrorHandler.php';

use TopTea\POS\Config\DotEnv;
use TopTea\POS\Helpers\Logger;
use TopTea\POS\Helpers\ErrorHandler;

$dotenv = new DotEnv(__DIR__ . '/../../../../../');
$dotenv->load();

// --- [PHASE 3 FIX] Initialize Logger and ErrorHandler ---
$logPath = DotEnv::get('LOG_PATH', __DIR__ . '/../../../../../storage/logs/pos/');
if (!is_dir($logPath)) {
    @mkdir($logPath, 0755, true);
}

// Initialize Logger
$isDevelopment = DotEnv::get('APP_DEBUG', 'false') === 'true';
Logger::init(
    $logPath,
    $isDevelopment ? Logger::DEBUG : Logger::INFO,
    $isDevelopment  // Include stack trace in development
);

// Initialize ErrorHandler
ErrorHandler::register($isDevelopment);

// Configure PHP error handling
ini_set('display_errors', $isDevelopment ? '1' : '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logPath . 'php_errors_pos.log');
// --- [END PHASE 3 FIX] ---

error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

// Log configuration initialization
Logger::info('POS Configuration loaded', [
    'environment' => $isDevelopment ? 'development' : 'production',
    'log_path' => $logPath,
]);

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

    Logger::info('Database connection established');

} catch (\PDOException $e) {
    // Log database connection error
    Logger::critical('Database connection failed', [
        'error' => $e->getMessage(),
        'host' => $db_host,
        'database' => $db_name,
    ]);

    // For POS, we must die cleanly in a way the frontend can parse
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(503); // Service Unavailable
    echo json_encode([
        'status' => 'error',
        'message' => $isDevelopment ? 'DB Connection Error: ' . $e->getMessage() : 'Database connection error. Please try again later.',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}