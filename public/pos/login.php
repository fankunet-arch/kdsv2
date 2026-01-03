<?php
/**
 * TopTea POS - Login Page
 *
 * @author TopTea Engineering Team
 * @version 1.0.0
 * @date 2026-01-03
 */

require_once __DIR__ . '/../../src/pos/Config/config.php';

use TopTea\POS\Core\SessionManager;
use TopTea\POS\Helpers\CSRFHelper;

// Initialize session
SessionManager::start();

// If the user is already logged in, redirect them to the main POS page
if (SessionManager::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Get CSRF token
$csrf_token = CSRFHelper::getToken();

// Load login view
require POS_VIEW_PATH . '/pages/login.php';
