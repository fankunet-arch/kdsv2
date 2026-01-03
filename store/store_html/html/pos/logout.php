<?php
/**
 * POS Logout Handler
 *
 * [SECURITY UPDATE 2026-01-03]
 * - Replaced manual session destruction with SessionManager::destroy()
 * - SessionManager handles session cleanup, cookie deletion, and security
 */

require_once realpath(__DIR__ . '/../../../src/pos/Core/SessionManager.php');
use TopTea\POS\Core\SessionManager;

// Start session first (required before destroying)
SessionManager::start();

// Destroy session and clean up all session data
SessionManager::destroy();

// Redirect to login page
header('Location: login.php');
exit;