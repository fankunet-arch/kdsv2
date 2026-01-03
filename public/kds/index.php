<?php
/**
 * TopTea KDS - Main Application Entry Point
 *
 * @author TopTea Engineering Team
 * @version 2.0.0
 * @date 2026-01-03
 */

require_once __DIR__ . '/../../src/kds/Config/config.php';

use TopTea\KDS\Auth\AuthGuard;
use TopTea\KDS\Core\SessionManager;

// Require authentication
AuthGuard::requireAuth();

header('Content-Type: text/html; charset=utf-8');

// Get requested page
$page = $_GET['page'] ?? 'sop';

// Define available pages
$pages = [
    'sop' => [
        'title' => '制茶助手 - SOP',
        'view' => 'sop_view.php',
        'js' => 'kds_sop.js'
    ],
    'expiry' => [
        'title' => '效期管理',
        'view' => 'expiry_view.php',
        'js' => 'kds_expiry.js'
    ],
    'prep' => [
        'title' => '备料管理',
        'view' => 'prep_view.php',
        'js' => 'kds_prep.js'
    ]
];

// Validate page
if (!isset($pages[$page])) {
    http_response_code(404);
    echo "<h1>404 - Page Not Found</h1>";
    exit;
}

$pageConfig = $pages[$page];
$page_title = $pageConfig['title'];
$content_view = KDS_VIEW_PATH . '/pages/' . $pageConfig['view'];
$page_js = $pageConfig['js'];

// Verify view file exists
if (!file_exists($content_view)) {
    \TopTea\KDS\Core\Logger::error('View file not found', ['path' => $content_view]);
    die("Critical Error: View file not found");
}

// Load main layout
require KDS_VIEW_PATH . '/layouts/main.php';
