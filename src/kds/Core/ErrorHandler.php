<?php
/**
 * TopTea KDS - Custom Error Handler
 *
 * Handles PHP errors and exceptions gracefully
 * Returns JSON for AJAX requests or redirects to error page for web requests
 * NO system alert boxes - all errors shown via Bootstrap modals
 *
 * @author TopTea Engineering Team
 * @version 1.0.0
 * @date 2026-01-03
 */

namespace TopTea\KDS\Core;

class ErrorHandler
{
    private static bool $debug = false;

    /**
     * Initialize error handler
     *
     * @param bool $debug Enable debug mode
     */
    public static function init(bool $debug = false): void
    {
        self::$debug = $debug;

        // Set custom error handler
        set_error_handler([self::class, 'handleError']);

        // Set custom exception handler
        set_exception_handler([self::class, 'handleException']);

        // Register shutdown function to catch fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Handle PHP errors (warnings, notices, etc.)
     *
     * @param int $errno Error number
     * @param string $errstr Error message
     * @param string $errfile Error file
     * @param int $errline Error line
     * @return bool
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // Don't handle suppressed errors (@)
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorType = self::getErrorType($errno);

        Logger::error("PHP {$errorType}", [
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline
        ]);

        // For fatal-like errors, throw exception
        if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        return true;
    }

    /**
     * Handle uncaught exceptions
     *
     * @param \Throwable $exception
     */
    public static function handleException(\Throwable $exception): void
    {
        Logger::critical('Uncaught Exception', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Check if this is an AJAX request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax) {
            // Return JSON error
            self::sendJsonError(
                'An unexpected error occurred',
                self::$debug ? $exception->getMessage() : null
            );
        } else {
            // Redirect to error page or show friendly error
            self::showErrorPage(
                'System Error',
                'An unexpected error occurred. Please try again or contact support.',
                self::$debug ? $exception->getMessage() : null
            );
        }
    }

    /**
     * Handle fatal errors during shutdown
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            Logger::critical('Fatal Error', [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);

            // Clear any partial output
            if (ob_get_length()) {
                ob_clean();
            }

            // Send error response
            self::showErrorPage(
                'Fatal Error',
                'A critical error occurred. Please contact the system administrator.',
                self::$debug ? $error['message'] : null
            );
        }
    }

    /**
     * Send JSON error response (for AJAX requests)
     *
     * @param string $message User-friendly message
     * @param string|null $debug Debug information (only in debug mode)
     */
    private static function sendJsonError(string $message, ?string $debug = null): void
    {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');

        $response = [
            'status' => 'error',
            'message' => $message,
            'data' => null
        ];

        if (self::$debug && $debug !== null) {
            $response['debug'] = $debug;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Show error page (for web requests)
     *
     * @param string $title Error title
     * @param string $message User-friendly message
     * @param string|null $debug Debug information (only in debug mode)
     */
    private static function showErrorPage(string $title, string $message, ?string $debug = null): void
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');

        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> - TopTea KDS</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .error-container {
                    background: white;
                    border-radius: 10px;
                    padding: 40px;
                    max-width: 600px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                }
            </style>
        </head>
        <body>
            <div class="error-container text-center">
                <h1 class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <p class="lead"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (self::$debug && $debug !== null): ?>
                    <div class="alert alert-warning text-start mt-4">
                        <strong>Debug Info:</strong><br>
                        <code><?= htmlspecialchars($debug, ENT_QUOTES, 'UTF-8') ?></code>
                    </div>
                <?php endif; ?>
                <a href="/kds/login.php" class="btn btn-primary mt-3">Return to Login</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Get human-readable error type
     *
     * @param int $errno Error number
     * @return string
     */
    private static function getErrorType(int $errno): string
    {
        return match($errno) {
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED',
            default => 'UNKNOWN',
        };
    }
}
