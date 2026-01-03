<?php
/**
 * TopTea KDS - PSR-4 Compatible Autoloader
 *
 * Simple autoloader for TopTea\KDS namespace
 *
 * @author TopTea Engineering Team
 * @version 1.0.0
 * @date 2026-01-03
 */

namespace TopTea\KDS\Core;

class Autoloader
{
    private static bool $registered = false;

    /**
     * Register the autoloader
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        spl_autoload_register([self::class, 'load']);
        self::$registered = true;
    }

    /**
     * Load a class file
     *
     * @param string $class Fully qualified class name
     */
    private static function load(string $class): void
    {
        // Project namespace prefix
        $prefix = 'TopTea\\KDS\\';

        // Base directory for the namespace prefix
        $baseDir = dirname(__DIR__) . '/';

        // Check if the class uses the namespace prefix
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            // Not our namespace, let other autoloaders handle it
            return;
        }

        // Get the relative class name
        $relativeClass = substr($class, $len);

        // Replace namespace separators with directory separators
        // Add .php extension
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    }
}
