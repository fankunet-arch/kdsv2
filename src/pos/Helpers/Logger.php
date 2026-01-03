<?php
/**
 * TopTea POS - Logger
 * Version: 1.0.0
 * Date: 2026-01-03
 * Engineer: Claude
 *
 * 职责:
 * - 统一POS系统的日志记录
 * - 支持多个日志级别（DEBUG, INFO, WARNING, ERROR, CRITICAL）
 * - 提供结构化日志输出
 * - 支持日志文件轮转
 * - 包含请求上下文信息（IP、用户、会话等）
 *
 * PSR-3 简化实现
 */

namespace TopTea\POS\Helpers;

class Logger
{
    /**
     * 日志级别常量
     */
    public const DEBUG = 'DEBUG';
    public const INFO = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';
    public const CRITICAL = 'CRITICAL';

    /**
     * 日志级别优先级映射
     */
    private const LEVEL_PRIORITY = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::WARNING => 2,
        self::ERROR => 3,
        self::CRITICAL => 4,
    ];

    /**
     * 日志文件路径
     */
    private static ?string $logPath = null;

    /**
     * 最小日志级别（低于此级别的日志不会被记录）
     */
    private static string $minLevel = self::INFO;

    /**
     * 是否包含堆栈跟踪（仅ERROR和CRITICAL级别）
     */
    private static bool $includeTrace = false;

    /**
     * 初始化Logger
     *
     * @param string $logPath 日志文件路径（默认从.env.pos读取）
     * @param string $minLevel 最小日志级别
     * @param bool $includeTrace 是否包含堆栈跟踪
     */
    public static function init(
        ?string $logPath = null,
        string $minLevel = self::INFO,
        bool $includeTrace = false
    ): void {
        if ($logPath !== null) {
            self::$logPath = $logPath;
        } else {
            // 从环境变量读取日志路径
            $envPath = $_ENV['LOG_PATH'] ?? getenv('LOG_PATH');
            if ($envPath) {
                self::$logPath = rtrim($envPath, '/') . '/';
            } else {
                // 默认路径
                self::$logPath = realpath(__DIR__ . '/../../../storage/logs/pos/') . '/';
            }
        }

        self::$minLevel = $minLevel;
        self::$includeTrace = $includeTrace;

        // 确保日志目录存在
        if (!is_dir(self::$logPath)) {
            @mkdir(self::$logPath, 0755, true);
        }
    }

    /**
     * 记录DEBUG级别日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * 记录INFO级别日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::INFO, $message, $context);
    }

    /**
     * 记录WARNING级别日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * 记录ERROR级别日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * 记录CRITICAL级别日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public static function critical(string $message, array $context = []): void
    {
        self::log(self::CRITICAL, $message, $context);
    }

    /**
     * 核心日志记录方法
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        // 确保Logger已初始化
        if (self::$logPath === null) {
            self::init();
        }

        // 检查日志级别是否达到最小级别
        if (self::LEVEL_PRIORITY[$level] < self::LEVEL_PRIORITY[self::$minLevel]) {
            return;
        }

        // 构建日志记录
        $logEntry = self::formatLogEntry($level, $message, $context);

        // 确定日志文件名（按日期轮转）
        $logFile = self::$logPath . 'pos_' . date('Y-m-d') . '.log';

        // 写入日志文件
        @file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);

        // 对于ERROR和CRITICAL级别，同时写入PHP错误日志
        if (in_array($level, [self::ERROR, self::CRITICAL], true)) {
            error_log("[POS {$level}] {$message}");
        }
    }

    /**
     * 格式化日志条目
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @return string 格式化后的日志字符串
     */
    private static function formatLogEntry(string $level, string $message, array $context): string
    {
        // 时间戳（ISO 8601格式，UTC时间）
        $timestamp = gmdate('Y-m-d\TH:i:s.v\Z');

        // 获取请求上下文
        $requestContext = self::getRequestContext();

        // 合并上下文
        $fullContext = array_merge($requestContext, $context);

        // 是否包含堆栈跟踪
        if (self::$includeTrace && in_array($level, [self::ERROR, self::CRITICAL], true)) {
            $fullContext['trace'] = self::getStackTrace();
        }

        // 构建日志JSON
        $logData = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $fullContext,
        ];

        return json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 获取请求上下文信息
     *
     * @return array 请求上下文
     */
    private static function getRequestContext(): array
    {
        $context = [];

        // IP地址
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $context['ip'] = $_SERVER['REMOTE_ADDR'];
        }

        // 请求方法和URI
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $context['method'] = $_SERVER['REQUEST_METHOD'];
        }
        if (isset($_SERVER['REQUEST_URI'])) {
            $context['uri'] = $_SERVER['REQUEST_URI'];
        }

        // 用户信息（如果已登录）
        if (isset($_SESSION['pos_user_id'])) {
            $context['user_id'] = $_SESSION['pos_user_id'];
        }
        if (isset($_SESSION['pos_store_id'])) {
            $context['store_id'] = $_SESSION['pos_store_id'];
        }
        if (isset($_SESSION['pos_shift_id'])) {
            $context['shift_id'] = $_SESSION['pos_shift_id'];
        }

        // User Agent
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $context['user_agent'] = substr($_SERVER['HTTP_USER_AGENT'], 0, 200);
        }

        return $context;
    }

    /**
     * 获取简化的堆栈跟踪
     *
     * @return array 堆栈跟踪信息
     */
    private static function getStackTrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $simplifiedTrace = [];

        foreach ($trace as $frame) {
            // 跳过Logger自身的调用
            if (isset($frame['class']) && $frame['class'] === self::class) {
                continue;
            }

            $simplifiedTrace[] = [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
            ];
        }

        return $simplifiedTrace;
    }

    /**
     * 设置最小日志级别
     *
     * @param string $level 日志级别
     */
    public static function setMinLevel(string $level): void
    {
        if (isset(self::LEVEL_PRIORITY[$level])) {
            self::$minLevel = $level;
        }
    }

    /**
     * 获取当前最小日志级别
     *
     * @return string 最小日志级别
     */
    public static function getMinLevel(): string
    {
        return self::$minLevel;
    }

    /**
     * 启用/禁用堆栈跟踪
     *
     * @param bool $enable 是否启用
     */
    public static function setIncludeTrace(bool $enable): void
    {
        self::$includeTrace = $enable;
    }

    /**
     * 清理旧日志文件（保留最近N天）
     *
     * @param int $days 保留天数（默认30天）
     */
    public static function cleanOldLogs(int $days = 30): void
    {
        if (self::$logPath === null) {
            self::init();
        }

        $cutoffTime = time() - ($days * 24 * 60 * 60);
        $files = glob(self::$logPath . 'pos_*.log');

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                @unlink($file);
            }
        }
    }
}
