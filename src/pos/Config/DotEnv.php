<?php
/**
 * TopTea POS - Native PHP .env File Loader
 *
 * A lightweight, native PHP implementation for loading .env files
 * No external dependencies required
 *
 * @author TopTea Engineering Team
 * @version 1.0.0
 * @date 2026-01-03
 */

namespace TopTea\POS\Config;

class DotEnv
{
    private string $path;
    private string $filename;
    private static bool $loaded = false;

    /**
     * Constructor
     *
     * @param string $path Path to the directory containing .env file
     * @param string $filename Name of the env file (default: .env.pos)
     */
    public function __construct(string $path, string $filename = '.env.pos')
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException("Invalid path: {$path}");
        }
        $this->path = rtrim($path, DIRECTORY_SEPARATOR);
        $this->filename = $filename;
    }

    /**
     * Load .env file and populate $_ENV and getenv()
     *
     * @return void
     * @throws \RuntimeException if .env file doesn't exist in production
     */
    public function load(): void
    {
        if (self::$loaded) {
            return; // Already loaded
        }

        $envFile = $this->path . DIRECTORY_SEPARATOR . $this->filename;

        // In production, .env MUST exist
        if (!file_exists($envFile)) {
            throw new \RuntimeException(
                "{$this->filename} file not found. Please create it and configure database credentials."
            );
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new \RuntimeException("Failed to read env file: {$envFile}");
        }

        foreach ($lines as $line) {
            // Skip comments and empty lines
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=VALUE
            if (str_contains($line, '=')) {
                [$key, $value] = $this->parseLine($line);

                // Set in $_ENV
                $_ENV[$key] = $value;

                // Set in getenv/putenv
                putenv("{$key}={$value}");

                // Also set in $_SERVER for compatibility
                $_SERVER[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Parse a single line from .env file
     *
     * @param string $line The line to parse
     * @return array [key, value]
     */
    private function parseLine(string $line): array
    {
        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);

        // Remove quotes from value if present
        if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
            $value = $matches[2];
        }

        // Handle variable expansion ${VAR_NAME}
        $value = preg_replace_callback('/\$\{([A-Z_][A-Z0-9_]*)\}/', function($matches) {
            return $_ENV[$matches[1]] ?? getenv($matches[1]) ?: $matches[0];
        }, $value);

        return [$key, $value];
    }

    /**
     * Get environment variable with optional default
     *
     * @param string $key The environment variable key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Check if environment variable is set
     *
     * @param string $key The environment variable key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset($_ENV[$key]) || getenv($key) !== false;
    }
}
