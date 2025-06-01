<?php
/**
 * Files API - File Metadata Endpoint
 * Handles file metadata retrieval and updates
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/auth/Auth.php';
require_once __DIR__ . '/../controllers/FileController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'PUT'])) {
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
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->getMetadata($fileId);
    } else {
        $controller->updateMetadata($fileId);
    }
} catch (Exception $e) {
    error_log("File Metadata API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Metadata operation failed: ' . $e->getMessage()]);
}
?>
