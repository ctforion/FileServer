<?php
/**
 * Files API - Download Endpoint
 * Handles file downloads with access control
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/auth/Auth.php';
require_once __DIR__ . '/../controllers/FileController.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Authenticate user
$auth = new Auth();
$user = $auth->getCurrentUser();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Get file ID from URL
$fileId = $_GET['id'] ?? null;
if (!$fileId) {
    http_response_code(400);
    echo json_encode(['error' => 'File ID required']);
    exit;
}

try {
    $controller = new FileController();
    $controller->download($fileId);
} catch (Exception $e) {
    error_log("Download API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Download failed: ' . $e->getMessage()]);
}
?>
