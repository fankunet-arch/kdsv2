<?php
/**
 * TopTea POS - Core Configuration File
 *
 * This file initializes the application configuration by:
 * 1. Loading environment variables from .env.pos file
 * 2. Setting up error handling and logging
 * 3. Establishing database connection
 * 4. Defining application constants
 *
 * @author TopTea Engineering Team
 * @version 1.0.0 (Refactored Architecture)
 * @date 2026-01-03
 */

// Load autoloader for our classes
require_once __DIR__ . '/../Core/Autoloader.php';

use TopTea\POS\Config\DotEnv;
use TopTea\POS\Core\ErrorHandler;
use TopTea\POS\Core\Logger;

// Initialize autoloader
\TopTea\POS\Core\Autoloader::register();

// Load environment variables from .env.pos
try {
    $dotenv = new DotEnv(dirname(__DIR__, 3)); // Points to /kdsv2/
    $dotenv->load();
} catch (\Exception $e) {
    // Fatal error: cannot proceed without configuration
    http_response_code(500);
    die('Configuration Error: Unable to load environment settings. Please contact administrator.');
}

// Configure error handling based on environment
$appDebug = filter_var(DotEnv::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);

if ($appDebug) {
    // Development mode
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    // Production mode
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

// Set default charset
mb_internal_encoding('UTF-8');

// Define application constants
define('POS_DEBUG', $appDebug);
define('POS_TIMEZONE', DotEnv::get('APP_TIMEZONE', 'Europe/Madrid'));
define('POS_BASE_URL', '/pos/');

// Define directory paths
define('POS_ROOT_PATH', dirname(__DIR__, 3)); // /kdsv2/
define('POS_PUBLIC_PATH', POS_ROOT_PATH . '/public/pos');
define('POS_SRC_PATH', POS_ROOT_PATH . '/src/pos');
define('POS_VIEW_PATH', POS_SRC_PATH . '/Views');
define('POS_ASSETS_PATH', POS_PUBLIC_PATH . '/assets');

// Set timezone
date_default_timezone_set(POS_TIMEZONE);

// Initialize logger
$logPath = DotEnv::get('LOG_PATH', POS_ROOT_PATH . '/storage/logs/pos/');
if (!is_dir($logPath)) {
    @mkdir($logPath, 0755, true);
}

Logger::init(
    $logPath,
    $appDebug ? Logger::DEBUG : Logger::INFO,
    $appDebug  // Include stack trace in development
);

// Initialize custom error handler
ErrorHandler::register($appDebug);

// Set error log location
ini_set('error_log', $logPath . 'php_errors_pos.log');

// Log configuration initialization
Logger::info('POS Configuration loaded', [
    'environment' => $appDebug ? 'development' : 'production',
    'log_path' => $logPath,
]);

// Database configuration
$db_host = DotEnv::get('DB_HOST', 'localhost');
$db_name = DotEnv::get('DB_NAME');
$db_user = DotEnv::get('DB_USER');
$db_pass = DotEnv::get('DB_PASS');
$db_char = DotEnv::get('DB_CHARSET', 'utf8mb4');

if (empty($db_name) || empty($db_user)) {
    Logger::error('Database configuration incomplete', [
        'db_name' => $db_name ? 'set' : 'missing',
        'db_user' => $db_user ? 'set' : 'missing'
    ]);
    http_response_code(500);
    die('Database Configuration Error: Please check environment settings.');
}

// Establish database connection
$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_char}";
$options = [
    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    \PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new \PDO($dsn, $db_user, $db_pass, $options);

    // Set connection timezone to UTC
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
        'message' => $appDebug ? 'DB Connection Error: ' . $e->getMessage() : 'Database connection error. Please try again later.',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
