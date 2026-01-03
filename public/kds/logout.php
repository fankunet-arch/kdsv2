<?php
/**
 * TopTea KDS - Logout Handler
 *
 * @author TopTea Engineering Team
 * @version 2.0.0
 * @date 2026-01-03
 */

require_once __DIR__ . '/../../src/kds/Config/config.php';

use TopTea\KDS\Core\SessionManager;
use TopTea\KDS\Core\Logger;

// Initialize session
SessionManager::init();

// Log the logout
if (SessionManager::isLoggedIn()) {
    Logger::info('User logged out', [
        'user_id' => SessionManager::getUserId(),
        'username' => SessionManager::get('kds_username')
    ]);
}

// Destroy session
SessionManager::destroy();

// Redirect to login page
header('Location: login.php');
exit;
