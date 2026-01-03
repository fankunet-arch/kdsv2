<?php
/**
 * Toptea Store - POS
 * Core Authentication & Session Check for POS
 * Engineer: Gemini | Date: 2025-10-29
 *
 * [SECURITY UPDATE 2026-01-03]
 * - Replaced @session_start() with SessionManager::start()
 * - Improved session security with centralized management
 */

require_once __DIR__ . '/SessionManager.php';
use TopTea\POS\Core\SessionManager;

// Start session using SessionManager
SessionManager::start();

// If the session variable is not set or is not true, redirect to login page.
if (!isset($_SESSION['pos_logged_in']) || $_SESSION['pos_logged_in'] !== true) {
    SessionManager::destroy();
    header('Location: login.php');
    exit;
}