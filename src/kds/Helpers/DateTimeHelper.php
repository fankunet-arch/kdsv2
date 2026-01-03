<?php
/**
 * TopTea KDS - DateTime Helper (UTC Sync)
 *
 * Provides UTC time conversion and localization functionality
 * All database times are stored in UTC, displayed in Madrid timezone
 *
 * @author TopTea Engineering Team
 * @version 2.0.0 (Refactored to class)
 * @date 2026-01-03
 */

namespace TopTea\KDS\Helpers;

use TopTea\KDS\Config\DotEnv;

class DateTimeHelper
{
    private const UTC = 'UTC';

    /**
     * Get default application timezone
     *
     * @return string
     */
    private static function getDefaultTimezone(): string
    {
        return DotEnv::get('APP_TIMEZONE', 'Europe/Madrid');
    }

    /**
     * Get current UTC DateTime object
     *
     * @return \DateTime
     */
    public static function utcNow(): \DateTime
    {
        return new \DateTime('now', new \DateTimeZone(self::UTC));
    }

    /**
     * Convert UTC DateTime to local timezone string
     *
     * @param string|\DateTime|null $utcDatetime UTC time (string or DateTime object)
     * @param string $format PHP DateTime format string
     * @param string|null $timezone Target timezone (null = use default)
     * @return string|null Formatted local time, null if input invalid
     */
    public static function formatLocal(
        string|\DateTime|null $utcDatetime,
        string $format = 'Y-m-d H:i:s',
        ?string $timezone = null
    ): ?string {
        if (!$utcDatetime) {
            return null;
        }

        $timezone = $timezone ?? self::getDefaultTimezone();

        try {
            if ($utcDatetime instanceof \DateTime) {
                $dt = clone $utcDatetime;
            } else {
                // Assume input string is UTC
                $dt = new \DateTime($utcDatetime, new \DateTimeZone(self::UTC));
            }

            $dt->setTimezone(new \DateTimeZone($timezone));
            return $dt->format($format);

        } catch (\Exception $e) {
            \TopTea\KDS\Core\Logger::warning('DateTimeHelper::formatLocal error', [
                'message' => $e->getMessage(),
                'input' => is_string($utcDatetime) ? $utcDatetime : 'DateTime object'
            ]);
            return null;
        }
    }

    /**
     * Convert local date range to UTC DateTime window for DB queries
     *
     * @param string $localDateFrom Local start date (e.g., "2025-11-09")
     * @param string|null $localDateTo Local end date (null = same as start)
     * @param string|null $timezone Local timezone (null = use default)
     * @return array [\DateTime $utcStart, \DateTime $utcEnd]
     */
    public static function toUtcWindow(
        string $localDateFrom,
        ?string $localDateTo = null,
        ?string $timezone = null
    ): array {
        $timezone = $timezone ?? self::getDefaultTimezone();

        $tz = new \DateTimeZone($timezone);
        $utc = new \DateTimeZone(self::UTC);

        try {
            // Start time: beginning of day
            $dtStart = new \DateTime($localDateFrom . ' 00:00:00', $tz);

            // End time: end of day
            if ($localDateTo === null || $localDateTo === $localDateFrom) {
                // Single day query
                $dtEnd = new \DateTime($localDateFrom . ' 23:59:59.999999', $tz);
            } else {
                // Date range query
                $dtEnd = new \DateTime($localDateTo . ' 23:59:59.999999', $tz);
            }

            // Convert to UTC
            $dtStart->setTimezone($utc);
            $dtEnd->setTimezone($utc);

            return [$dtStart, $dtEnd];

        } catch (\Exception $e) {
            \TopTea\KDS\Core\Logger::warning('DateTimeHelper::toUtcWindow error', [
                'message' => $e->getMessage(),
                'from' => $localDateFrom,
                'to' => $localDateTo
            ]);

            // Return safe fallback (current moment)
            $now = self::utcNow();
            return [$now, $now];
        }
    }

    /**
     * Convert local datetime string to UTC DateTime
     *
     * @param string $localDatetime Local datetime string
     * @param string|null $timezone Local timezone (null = use default)
     * @return \DateTime UTC DateTime object
     */
    public static function localToUtc(string $localDatetime, ?string $timezone = null): \DateTime
    {
        $timezone = $timezone ?? self::getDefaultTimezone();

        $dt = new \DateTime($localDatetime, new \DateTimeZone($timezone));
        $dt->setTimezone(new \DateTimeZone(self::UTC));

        return $dt;
    }

    /**
     * Get current date in local timezone (for display)
     *
     * @param string $format Date format
     * @return string Formatted local date
     */
    public static function localDate(string $format = 'Y-m-d'): string
    {
        $now = new \DateTime('now', new \DateTimeZone(self::getDefaultTimezone()));
        return $now->format($format);
    }

    /**
     * Get current datetime in local timezone (for display)
     *
     * @param string $format DateTime format
     * @return string Formatted local datetime
     */
    public static function localDateTime(string $format = 'Y-m-d H:i:s'): string
    {
        $now = new \DateTime('now', new \DateTimeZone(self::getDefaultTimezone()));
        return $now->format($format);
    }
}

// Backward compatibility: global functions (deprecated, use class methods)
if (!defined('APP_DEFAULT_TIMEZONE')) {
    define('APP_DEFAULT_TIMEZONE', 'Europe/Madrid');
}

if (!function_exists('utc_now')) {
    function utc_now(): DateTime {
        return \TopTea\KDS\Helpers\DateTimeHelper::utcNow();
    }
}

if (!function_exists('fmt_local')) {
    function fmt_local(
        string|DateTime|null $utc_datetime,
        string $format = 'Y-m-d H:i:s',
        string $timezone = APP_DEFAULT_TIMEZONE
    ): ?string {
        return \TopTea\KDS\Helpers\DateTimeHelper::formatLocal($utc_datetime, $format, $timezone);
    }
}

if (!function_exists('to_utc_window')) {
    function to_utc_window(
        string $local_date_from,
        ?string $local_date_to = null,
        string $timezone = APP_DEFAULT_TIMEZONE
    ): array {
        return \TopTea\KDS\Helpers\DateTimeHelper::toUtcWindow($local_date_from, $local_date_to, $timezone);
    }
}
