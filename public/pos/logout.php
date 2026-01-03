<?php
/**
 * TopTea POS - Logout Handler
 *
 * @author TopTea Engineering Team
 * @version 1.0.0
 * @date 2026-01-03
 */

require_once __DIR__ . '/../../src/pos/Config/config.php';

use TopTea\POS\Core\SessionManager;

// Start session first (required before destroying)
SessionManager::start();

// Destroy session and clean up all session data
SessionManager::destroy();

// Redirect to login page
header('Location: login.php');
exit;
