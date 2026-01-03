<?php
/**
 * TopTea KDS - Image Serving API
 *
 * Secure image serving with access control
 * Path adapted for new directory structure
 *
 * @author TopTea Engineering Team
 * @version 2.0.0 (Path updated)
 * @date 2026-01-03
 */

require_once __DIR__ . '/../../../src/kds/Config/config.php';

use TopTea\KDS\Auth\AuthGuard;
use TopTea\KDS\Helpers\InputValidator;
use TopTea\KDS\Core\Logger;

// Require authentication
AuthGuard::requireAuth();

// Get and validate filename
$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    die('Error: No file specified');
}

// Sanitize filename (prevent directory traversal)
$filename = InputValidator::sanitizeFilename($filename);

// Build full path to image (new location)
$imagePath = KDS_ASSETS_PATH . '/images/' . $filename;

// Verify file exists and is within allowed directory
$realPath = realpath($imagePath);
$allowedDir = realpath(KDS_ASSETS_PATH . '/images/');

if ($realPath === false || strpos($realPath, $allowedDir) !== 0) {
    Logger::warning('Attempted access to invalid image path', [
        'filename' => $filename,
        'path' => $imagePath,
        'user_id' => \TopTea\KDS\Core\SessionManager::getUserId()
    ]);
    http_response_code(404);
    die('Error: File not found');
}

// Verify it's an image file
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
$extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions)) {
    http_response_code(403);
    die('Error: Invalid file type');
}

// Set appropriate content type
$contentTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml'
];

header('Content-Type: ' . $contentTypes[$extension]);
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year

// Output file
readfile($realPath);
exit;
