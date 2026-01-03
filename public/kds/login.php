<?php
/**
 * TopTea KDS - Login Page
 *
 * @author TopTea Engineering Team
 * @version 2.0.0
 * @date 2026-01-03
 */

require_once __DIR__ . '/../../src/kds/Config/config.php';

use TopTea\KDS\Core\SessionManager;

// Initialize session
SessionManager::init();

// If already logged in, redirect to main page
if (SessionManager::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Load login view
require_once KDS_VIEW_PATH . '/pages/login_view.php';
