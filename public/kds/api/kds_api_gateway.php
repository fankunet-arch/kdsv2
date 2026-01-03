<?php
/**
 * TopTea KDS - API Gateway
 * Unified API entry point for KDS operations
 *
 * @author TopTea Engineering Team
 * @version 2.0.0 (Restored and updated for new architecture)
 * @date 2026-01-03
 *
 * [CRITICAL FIX 2026-01-03]
 * - Restored missing KDS API Gateway
 * - Updated paths for new architecture (public/kds/, src/kds/)
 * - Uses unified config.php and PSR-4 autoloading
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Load configuration (initializes autoloader, database, logger, etc.)
require_once __DIR__ . '/../../src/kds/Config/config.php';

use TopTea\KDS\Helpers\JsonHelper;
use TopTea\KDS\Core\SessionManager;

// Initialize session
SessionManager::init();

// Verify authentication
if (!SessionManager::isLoggedIn()) {
    JsonHelper::error('Unauthorized: Please log in', 401);
    exit;
}

// Load KDS API core (if it exists)
$api_core_path = __DIR__ . '/../../src/kds/Core/kds_api_core.php';
if (file_exists($api_core_path)) {
    require_once $api_core_path;
}

// Load registry
$registry_path = __DIR__ . '/registries/kds_registry.php';
if (!file_exists($registry_path)) {
    JsonHelper::error('KDS registry not found', 500);
    exit;
}

$registry = require $registry_path;

if (!is_array($registry)) {
    JsonHelper::error('Invalid registry format', 500);
    exit;
}

// Check if run_api function exists (from kds_api_core.php or registry)
if (function_exists('run_api')) {
    // Use the run_api function if available
    try {
        run_api($registry, $pdo);
    } catch (\Throwable $e) {
        error_log("KDS API Gateway Error: " . $e->getMessage());
        JsonHelper::error(
            KDS_DEBUG ? $e->getMessage() : 'Internal server error',
            500
        );
    }
} else {
    // Fallback: simple request routing
    $resource = $_GET['resource'] ?? $_POST['resource'] ?? null;
    $action = $_GET['action'] ?? $_POST['action'] ?? null;

    if (!$resource) {
        JsonHelper::error('Missing resource parameter', 400);
        exit;
    }

    if (!isset($registry[$resource])) {
        JsonHelper::error("Unknown resource: {$resource}", 404);
        exit;
    }

    $resourceConfig = $registry[$resource];

    // Handle custom action
    if ($action && isset($resourceConfig['actions'][$action])) {
        $handler = $resourceConfig['actions'][$action];

        if (!is_callable($handler)) {
            JsonHelper::error("Handler for action '{$action}' is not callable", 500);
            exit;
        }

        try {
            // Get input data
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $inputData = [];

            if ($method === 'POST' || $method === 'PUT') {
                $rawInput = file_get_contents('php://input');
                $jsonData = json_decode($rawInput, true);
                $inputData = $jsonData ?? $_POST;
            } else {
                $inputData = $_GET;
            }

            // Call handler
            $handler($pdo, $inputData);

        } catch (\Throwable $e) {
            error_log("KDS API Error [{$resource}/{$action}]: " . $e->getMessage());
            JsonHelper::error(
                KDS_DEBUG ? $e->getMessage() : 'Internal server error',
                500
            );
        }
    } else {
        JsonHelper::error("No action specified or action not found", 400);
    }
}
