<?php
/**
 * TopTea POS - Main Application Entry Point
 *
 * @author TopTea Engineering Team
 * @version 1.0.0
 * @date 2026-01-03
 */

require_once __DIR__ . '/../../src/pos/Config/config.php';

use TopTea\POS\Auth\AuthGuard;
use TopTea\POS\Helpers\CSRFHelper;

// Require authentication
AuthGuard::requireAuth();

// Get CSRF token for the page
$csrf_token = CSRFHelper::getToken();

// Get cache version for assets
$cache_version = time();

// Load main view
require POS_VIEW_PATH . '/layouts/main.php';
