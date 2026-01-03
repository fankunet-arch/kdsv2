<?php
/**
 * TopTea POS - Class Autoloader
 *
 * PSR-4 compliant autoloader for the TopTea\POS namespace
 *
 * @author TopTea Engineering Team
 * @version 1.0.0
 * @date 2026-01-03
 */

namespace TopTea\POS\Core;

class Autoloader
{
    /**
     * Base directory for the namespace prefix
     */
    private const BASE_DIR = __DIR__ . '/../';

    /**
     * Namespace prefix for this autoloader
     */
    private const NAMESPACE_PREFIX = 'TopTea\\POS\\';

    /**
     * Register the autoloader
     *
     * @return void
     */
    public static function register(): void
    {
        spl_autoload_register([self::class, 'loadClass']);
    }

    /**
     * Load a class file
     *
     * @param string $class The fully-qualified class name
     * @return void
     */
    private static function loadClass(string $class): void
    {
        // Check if the class uses the namespace prefix
        $prefix_len = strlen(self::NAMESPACE_PREFIX);
        if (strncmp(self::NAMESPACE_PREFIX, $class, $prefix_len) !== 0) {
            // Class does not use this namespace, skip
            return;
        }

        // Get the relative class name
        $relative_class = substr($class, $prefix_len);

        // Replace namespace separators with directory separators
        // and append .php
        $file = self::BASE_DIR . str_replace('\\', '/', $relative_class) . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require_once $file;
        }
    }

    /**
     * Get the base directory
     *
     * @return string
     */
    public static function getBaseDir(): string
    {
        return self::BASE_DIR;
    }

    /**
     * Get the namespace prefix
     *
     * @return string
     */
    public static function getNamespacePrefix(): string
    {
        return self::NAMESPACE_PREFIX;
    }
}
