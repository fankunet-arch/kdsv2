<?php
/**
 * TopTea KDS - Simple Structured Logger
 *
 * PSR-3 inspired logger with file rotation support
 *
 * @author TopTea Engineering Team
 * @version 1.0.0
 * @date 2026-01-03
 */

namespace TopTea\KDS\Core;

class Logger
{
    private static ?string $logPath = null;
    private static string $logLevel = 'WARNING';
    private static array $levels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];

    /**
     * Initialize logger
     *
     * @param string $path Log file directory
     * @param string $level Minimum log level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
     */
    public static function init(string $path, string $level = 'WARNING'): void
    {
        self::$logPath = rtrim($path, '/') . '/';
        self::$logLevel = strtoupper($level);

        // Ensure log directory exists
        if (!is_dir(self::$logPath)) {
            if (!mkdir(self::$logPath, 0755, true) && !is_dir(self::$logPath)) {
                error_log("Failed to create log directory: " . self::$logPath);
            }
        }
    }

    /**
     * Log a debug message
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }

    /**
     * Log an info message
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    /**
     * Log a warning message
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    /**
     * Log an error message
     */
    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    /**
     * Log a critical message
     */
    public static function critical(string $message, array $context = []): void
    {
        self::log('CRITICAL', $message, $context);
    }

    /**
     * Core logging method
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        // Check if this level should be logged
        if (self::$levels[$level] < self::$levels[self::$logLevel]) {
            return;
        }

        $logFile = self::$logPath . 'kds_' . date('Y-m-d') . '.log';

        // Format: [2026-01-03 10:30:45] [ERROR] Message {context as JSON}
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logLine = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        // Write to file
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

        // Also log to PHP error log for CRITICAL errors
        if ($level === 'CRITICAL') {
            error_log("[KDS CRITICAL] {$message}" . $contextStr);
        }
    }

    /**
     * Rotate old log files (call this from cron or periodic task)
     *
     * @param int $daysToKeep Number of days to keep logs
     */
    public static function rotateLogs(int $daysToKeep = 30): void
    {
        if (self::$logPath === null) {
            return;
        }

        $cutoffDate = strtotime("-{$daysToKeep} days");
        $files = glob(self::$logPath . 'kds_*.log');

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffDate) {
                unlink($file);
            }
        }
    }
}
