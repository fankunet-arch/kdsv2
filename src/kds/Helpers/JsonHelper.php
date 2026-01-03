<?php
/**
 * TopTea KDS - JSON Response Helper
 *
 * Unified JSON response formatting for API endpoints
 *
 * @author TopTea Engineering Team
 * @version 2.0.0 (Refactored)
 * @date 2026-01-03
 */

namespace TopTea\KDS\Helpers;

use TopTea\KDS\Core\Logger;

class JsonHelper
{
    private static bool $headersSent = false;

    /**
     * Ensure JSON headers are sent only once
     */
    private static function sendHeadersOnce(): void
    {
        if (!self::$headersSent && !headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            self::$headersSent = true;
        }
    }

    /**
     * Send successful JSON response and exit
     *
     * @param mixed $data Data to send
     * @param string $message Success message
     * @param int $httpCode HTTP status code
     */
    public static function success(mixed $data = null, string $message = '操作成功', int $httpCode = 200): never
    {
        self::sendHeadersOnce();
        http_response_code($httpCode);

        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    /**
     * Send error JSON response and exit
     *
     * @param string $message Error message
     * @param int $httpCode HTTP status code (400-599)
     * @param mixed $data Additional error details
     */
    public static function error(string $message, int $httpCode = 400, mixed $data = null): never
    {
        self::sendHeadersOnce();

        // Ensure error code is valid
        $httpCode = ($httpCode >= 400 && $httpCode < 600) ? $httpCode : 400;
        http_response_code($httpCode);

        // Log error
        Logger::warning('API Error Response', [
            'message' => $message,
            'http_code' => $httpCode,
            'data' => $data
        ]);

        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    /**
     * Read JSON input from request body
     *
     * @return array Parsed JSON as associative array
     */
    public static function readInput(): array
    {
        $raw = file_get_contents('php://input');

        if (empty($raw)) {
            return [];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            self::error('Invalid JSON request body: ' . json_last_error_msg(), 400);
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Automatically detect and return request data (JSON, POST, or GET)
     *
     * @return array Request data
     */
    public static function getRequestData(): array
    {
        // Check Content-Type for JSON
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            return self::readInput();
        }

        // Priority: POST > GET
        if (!empty($_POST)) {
            return $_POST;
        }

        if (!empty($_GET)) {
            // Remove routing parameters
            $data = $_GET;
            unset($data['res'], $data['act']);
            return $data;
        }

        return [];
    }

    /**
     * Validate required fields in data
     *
     * @param array $data Input data
     * @param array $required Array of required field names
     * @throws \InvalidArgumentException if fields are missing
     */
    public static function requireFields(array $data, array $required): void
    {
        $missing = [];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            self::error(
                'Missing required fields: ' . implode(', ', $missing),
                400,
                ['missing_fields' => $missing]
            );
        }
    }
}

// Backward compatibility: global functions (deprecated)
if (!function_exists('json_ok')) {
    function json_ok(mixed $data = null, string $message = '操作成功', int $httpCode = 200): never {
        \TopTea\KDS\Helpers\JsonHelper::success($data, $message, $httpCode);
    }
}

if (!function_exists('json_error')) {
    function json_error(string $message, int $httpCode = 400, mixed $data = null): never {
        \TopTea\KDS\Helpers\JsonHelper::error($message, $httpCode, $data);
    }
}

if (!function_exists('get_request_data')) {
    function get_request_data(): array {
        return \TopTea\KDS\Helpers\JsonHelper::getRequestData();
    }
}

if (!function_exists('read_json_input')) {
    function read_json_input(): array {
        return \TopTea\KDS\Helpers\JsonHelper::readInput();
    }
}
