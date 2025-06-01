<?php
/**
 * Files API - List Files Endpoint
 * Handles file listing with pagination and filtering
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/auth/Auth.php';
require_once __DIR__ . '/../controllers/FileController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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

try {
    $controller = new FileController();
    $controller->listFiles();
} catch (Exception $e) {
    error_log("List Files API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to list files: ' . $e->getMessage()]);
}
?>
