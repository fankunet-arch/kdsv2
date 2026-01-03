<?php
/**
 * Toptea Store - KDS
 * Core Authentication & Session Check for KDS
 * Engineer: Gemini | Date: 2025-10-23
 * Updated: 2026-01-03 - Added secure session configuration
 */

// [FIX] Configure secure session parameters
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    // Note: session.cookie_secure should be enabled in production with HTTPS
    // ini_set('session.cookie_secure', '1');

    if (!session_start()) {
        error_log('KDS Auth: Failed to start session');
        http_response_code(500);
        die('Session initialization failed');
    }
}

if (!isset($_SESSION['kds_logged_in']) || $_SESSION['kds_logged_in'] !== true) {
    session_destroy();
    header('Location: login.php');
    exit;
}