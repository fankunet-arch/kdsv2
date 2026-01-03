<?php
/**
 * TopTea KDS - Input Validation & Sanitization
 *
 * Centralized input validation and XSS prevention
 *
 * @author TopTea Engineering Team
 * @version 1.0.0
 * @date 2026-01-03
 */

namespace TopTea\KDS\Helpers;

class InputValidator
{
    /**
     * Sanitize string input (XSS prevention)
     *
     * @param string $input Raw input
     * @return string Sanitized output
     */
    public static function sanitizeString(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate store code format
     *
     * @param string $code Store code
     * @return bool
     */
    public static function validateStoreCode(string $code): bool
    {
        // Alphanumeric, 1-20 characters
        return preg_match('/^[A-Z0-9]{1,20}$/i', $code) === 1;
    }

    /**
     * Validate username format
     *
     * @param string $username Username
     * @return bool
     */
    public static function validateUsername(string $username): bool
    {
        // Alphanumeric and underscore, 3-50 characters
        return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username) === 1;
    }

    /**
     * Validate email format
     *
     * @param string $email Email address
     * @return bool
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone number (basic)
     *
     * @param string $phone Phone number
     * @return bool
     */
    public static function validatePhone(string $phone): bool
    {
        // Allow digits, spaces, +, -, (, )
        return preg_match('/^[0-9\s\+\-\(\)]{7,20}$/', $phone) === 1;
    }

    /**
     * Validate product code format
     *
     * @param string $code Product code
     * @return bool
     */
    public static function validateProductCode(string $code): bool
    {
        // Allow alphanumeric and hyphens, 1-32 characters
        return preg_match('/^[A-Z0-9\-]{1,32}$/i', $code) === 1;
    }

    /**
     * Validate integer range
     *
     * @param mixed $value Value to validate
     * @param int $min Minimum value
     * @param int|null $max Maximum value (null = no limit)
     * @return bool
     */
    public static function validateIntRange(mixed $value, int $min, ?int $max = null): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $intValue = (int)$value;

        if ($intValue < $min) {
            return false;
        }

        if ($max !== null && $intValue > $max) {
            return false;
        }

        return true;
    }

    /**
     * Validate decimal range
     *
     * @param mixed $value Value to validate
     * @param float $min Minimum value
     * @param float|null $max Maximum value (null = no limit)
     * @return bool
     */
    public static function validateDecimalRange(mixed $value, float $min, ?float $max = null): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $floatValue = (float)$value;

        if ($floatValue < $min) {
            return false;
        }

        if ($max !== null && $floatValue > $max) {
            return false;
        }

        return true;
    }

    /**
     * Validate date format (YYYY-MM-DD)
     *
     * @param string $date Date string
     * @return bool
     */
    public static function validateDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Sanitize filename (prevent directory traversal)
     *
     * @param string $filename Original filename
     * @return string Safe filename
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove any path components
        $filename = basename($filename);

        // Remove special characters except alphanumeric, dots, hyphens, underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Prevent double extensions and hidden files
        $filename = ltrim($filename, '.');

        return $filename;
    }

    /**
     * Validate and sanitize array of IDs
     *
     * @param array $ids Array of potential IDs
     * @return array Array of valid integer IDs
     */
    public static function sanitizeIds(array $ids): array
    {
        return array_filter(
            array_map('intval', $ids),
            fn($id) => $id > 0
        );
    }

    /**
     * Validate JSON string
     *
     * @param string $json JSON string
     * @return bool
     */
    public static function validateJson(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Sanitize output for JSON (prevent XSS in JSON responses)
     *
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    public static function sanitizeForJson(mixed $data): mixed
    {
        if (is_string($data)) {
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }

        if (is_array($data)) {
            return array_map([self::class, 'sanitizeForJson'], $data);
        }

        return $data;
    }
}
