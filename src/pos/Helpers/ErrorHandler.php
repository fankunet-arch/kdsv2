<?php
/**
 * TopTea POS - Error Handler
 * Version: 1.0.0
 * Date: 2026-01-03
 * Engineer: Claude
 *
 * 职责:
 * - 统一处理PHP错误、异常和致命错误
 * - 将错误记录到Logger
 * - 在生产环境隐藏敏感错误信息
 * - 提供友好的错误页面/JSON响应
 * - 防止错误信息泄露系统敏感信息
 */

namespace TopTea\POS\Helpers;

use Throwable;
use ErrorException;

class ErrorHandler
{
    /**
     * 是否已注册处理器
     */
    private static bool $registered = false;

    /**
     * 是否为开发环境
     */
    private static bool $isDevelopment = false;

    /**
     * 是否为API请求
     */
    private static bool $isApiRequest = false;

    /**
     * 注册错误处理器
     *
     * @param bool $isDevelopment 是否为开发环境（显示详细错误）
     */
    public static function register(bool $isDevelopment = false): void
    {
        if (self::$registered) {
            return;
        }

        self::$isDevelopment = $isDevelopment;
        self::$isApiRequest = self::detectApiRequest();

        // 注册异常处理器
        set_exception_handler([self::class, 'handleException']);

        // 注册错误处理器（将错误转换为异常）
        set_error_handler([self::class, 'handleError']);

        // 注册致命错误处理器
        register_shutdown_function([self::class, 'handleShutdown']);

        // 设置错误报告级别
        if (self::$isDevelopment) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '0');
        }

        self::$registered = true;

        Logger::info('ErrorHandler registered', [
            'environment' => self::$isDevelopment ? 'development' : 'production',
            'api_request' => self::$isApiRequest,
        ]);
    }

    /**
     * 处理异常
     *
     * @param Throwable $exception 异常对象
     */
    public static function handleException(Throwable $exception): void
    {
        // 记录异常到日志
        Logger::error('Uncaught Exception: ' . $exception->getMessage(), [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'trace' => self::$isDevelopment ? $exception->getTraceAsString() : '(hidden in production)',
        ]);

        // 输出错误响应
        self::sendErrorResponse(
            'Internal Server Error',
            $exception->getMessage(),
            500,
            $exception
        );
    }

    /**
     * 处理PHP错误（转换为异常）
     *
     * @param int $errno 错误级别
     * @param string $errstr 错误信息
     * @param string $errfile 错误文件
     * @param int $errline 错误行号
     * @return bool
     * @throws ErrorException
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // 检查错误是否在error_reporting范围内
        if (!(error_reporting() & $errno)) {
            return false;
        }

        // 将错误转换为异常
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * 处理致命错误（shutdown时检查）
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            // 记录致命错误
            Logger::critical('Fatal Error: ' . $error['message'], [
                'type' => self::getErrorTypeName($error['type']),
                'file' => $error['file'],
                'line' => $error['line'],
            ]);

            // 输出错误响应
            self::sendErrorResponse(
                'Fatal Error',
                'A critical error occurred and the request could not be completed.',
                500,
                null,
                $error
            );
        }
    }

    /**
     * 发送错误响应（JSON或HTML）
     *
     * @param string $title 错误标题
     * @param string $message 错误消息
     * @param int $httpCode HTTP状态码
     * @param Throwable|null $exception 异常对象（开发环境使用）
     * @param array|null $error PHP错误数组（shutdown使用）
     */
    private static function sendErrorResponse(
        string $title,
        string $message,
        int $httpCode,
        ?Throwable $exception = null,
        ?array $error = null
    ): void {
        // 防止重复输出
        if (headers_sent()) {
            return;
        }

        // 清空之前的输出缓冲
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // 设置HTTP状态码
        http_response_code($httpCode);

        // 在生产环境隐藏详细错误信息
        if (!self::$isDevelopment) {
            $message = 'An error occurred. Please try again later or contact support.';
        }

        // 根据请求类型发送不同格式的响应
        if (self::$isApiRequest) {
            self::sendJsonError($title, $message, $httpCode, $exception, $error);
        } else {
            self::sendHtmlError($title, $message, $httpCode, $exception, $error);
        }

        exit(1);
    }

    /**
     * 发送JSON格式错误响应
     *
     * @param string $title 错误标题
     * @param string $message 错误消息
     * @param int $httpCode HTTP状态码
     * @param Throwable|null $exception 异常对象
     * @param array|null $error PHP错误数组
     */
    private static function sendJsonError(
        string $title,
        string $message,
        int $httpCode,
        ?Throwable $exception = null,
        ?array $error = null
    ): void {
        header('Content-Type: application/json; charset=utf-8');

        $response = [
            'status' => 'error',
            'message' => $message,
            'error_code' => 'INTERNAL_ERROR',
        ];

        // 在开发环境添加调试信息
        if (self::$isDevelopment) {
            $response['debug'] = [];

            if ($exception !== null) {
                $response['debug']['exception'] = get_class($exception);
                $response['debug']['file'] = $exception->getFile();
                $response['debug']['line'] = $exception->getLine();
                $response['debug']['trace'] = $exception->getTraceAsString();
            } elseif ($error !== null) {
                $response['debug']['type'] = self::getErrorTypeName($error['type']);
                $response['debug']['file'] = $error['file'];
                $response['debug']['line'] = $error['line'];
                $response['debug']['message'] = $error['message'];
            }
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 发送HTML格式错误响应
     *
     * @param string $title 错误标题
     * @param string $message 错误消息
     * @param int $httpCode HTTP状态码
     * @param Throwable|null $exception 异常对象
     * @param array|null $error PHP错误数组
     */
    private static function sendHtmlError(
        string $title,
        string $message,
        int $httpCode,
        ?Throwable $exception = null,
        ?array $error = null
    ): void {
        header('Content-Type: text/html; charset=utf-8');

        $debugInfo = '';
        if (self::$isDevelopment) {
            if ($exception !== null) {
                $debugInfo = sprintf(
                    '<div class="debug-info"><h3>Debug Information</h3><p><strong>Exception:</strong> %s</p><p><strong>File:</strong> %s</p><p><strong>Line:</strong> %d</p><pre>%s</pre></div>',
                    htmlspecialchars(get_class($exception)),
                    htmlspecialchars($exception->getFile()),
                    $exception->getLine(),
                    htmlspecialchars($exception->getTraceAsString())
                );
            } elseif ($error !== null) {
                $debugInfo = sprintf(
                    '<div class="debug-info"><h3>Debug Information</h3><p><strong>Type:</strong> %s</p><p><strong>File:</strong> %s</p><p><strong>Line:</strong> %d</p><p><strong>Message:</strong> %s</p></div>',
                    htmlspecialchars(self::getErrorTypeName($error['type'])),
                    htmlspecialchars($error['file']),
                    $error['line'],
                    htmlspecialchars($error['message'])
                );
            }
        }

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - TopTea POS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f5f5f5; color: #333; padding: 40px 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { font-size: 32px; color: #d32f2f; margin-bottom: 20px; }
        p { font-size: 16px; line-height: 1.6; margin-bottom: 20px; }
        .debug-info { margin-top: 30px; padding: 20px; background: #f5f5f5; border-left: 4px solid #ff9800; border-radius: 4px; }
        .debug-info h3 { font-size: 18px; margin-bottom: 15px; color: #ff9800; }
        .debug-info pre { background: #fff; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        .back-link { display: inline-block; margin-top: 20px; color: #1976d2; text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{$title}</h1>
        <p>{$message}</p>
        {$debugInfo}
        <a href="javascript:history.back()" class="back-link">← Go Back</a>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 检测是否为API请求
     *
     * @return bool
     */
    private static function detectApiRequest(): bool
    {
        // 检查请求路径是否包含 /api/
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($requestUri, '/api/') !== false) {
            return true;
        }

        // 检查Accept header是否为JSON
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }

        // 检查Content-Type header是否为JSON
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            return true;
        }

        return false;
    }

    /**
     * 获取错误类型名称
     *
     * @param int $type 错误类型代码
     * @return string 错误类型名称
     */
    private static function getErrorTypeName(int $type): string
    {
        $types = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];

        return $types[$type] ?? 'UNKNOWN';
    }
}
