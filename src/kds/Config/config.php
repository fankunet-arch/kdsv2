<?php
/**
 * TopTea KDS - Core Configuration File
 *
 * This file initializes the application configuration by:
 * 1. Loading environment variables from .env file
 * 2. Setting up error handling
 * 3. Establishing database connection
 * 4. Defining application constants
 *
 * @author TopTea Engineering Team
 * @version 4.0.0 (Refactored)
 * @date 2026-01-03
 */

// Load autoloader for our classes
require_once __DIR__ . '/../Core/Autoloader.php';

use TopTea\KDS\Config\DotEnv;
use TopTea\KDS\Core\ErrorHandler;
use TopTea\KDS\Core\Logger;

// Initialize autoloader
\TopTea\KDS\Core\Autoloader::register();

// Load environment variables
try {
    $dotenv = new DotEnv(dirname(__DIR__, 3)); // Points to /kdsv2/
    $dotenv->load();
} catch (\Exception $e) {
    // Fatal error: cannot proceed without configuration
    http_response_code(500);
    die('Configuration Error: Unable to load environment settings. Please contact administrator.');
}

// Configure error handling based on environment
$appEnv = DotEnv::get('APP_ENV', 'production');
$appDebug = filter_var(DotEnv::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);

if ($appEnv === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
} else {
    // Development mode
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// Set error log location
ini_set('error_log', __DIR__ . '/../Core/php_errors_kds.log');

// Set default charset
mb_internal_encoding('UTF-8');

// Initialize custom error handler
ErrorHandler::init($appDebug);

// Initialize logger
Logger::init(
    DotEnv::get('LOG_PATH', __DIR__ . '/../Core/'),
    DotEnv::get('LOG_LEVEL', 'WARNING')
);

// Define application constants
define('KDS_ENV', $appEnv);
define('KDS_DEBUG', $appDebug);
define('KDS_TIMEZONE', DotEnv::get('APP_TIMEZONE', 'Europe/Madrid'));
define('KDS_BASE_URL', '/kds/');

// Define directory paths
define('KDS_ROOT_PATH', dirname(__DIR__, 3)); // /kdsv2/
define('KDS_PUBLIC_PATH', KDS_ROOT_PATH . '/public/kds');
define('KDS_SRC_PATH', KDS_ROOT_PATH . '/src/kds');
define('KDS_VIEW_PATH', KDS_SRC_PATH . '/Views');
define('KDS_ASSETS_PATH', KDS_PUBLIC_PATH . '/assets');

// Set timezone
date_default_timezone_set(KDS_TIMEZONE);

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
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    // Set connection timezone to UTC (all DB operations in UTC)
    $pdo->exec("SET time_zone='+00:00'");

    Logger::debug('Database connection established');

} catch (\PDOException $e) {
    Logger::error('Database connection failed', [
        'error' => $e->getMessage(),
        'host' => $db_host,
        'database' => $db_name
    ]);

    // Don't expose error details in production
    if (KDS_DEBUG) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => 'Database Connection Error',
            'debug' => $e->getMessage()
        ]);
    } else {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => 'Database Connection Error. Please contact administrator.',
            'data' => null
        ]);
    }
    exit;
}

// Configuration loaded successfully
Logger::debug('KDS System initialized', [
    'env' => KDS_ENV,
    'timezone' => KDS_TIMEZONE
]);
