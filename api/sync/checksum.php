<?php
/**
 * Sync API - File Checksums
 * Handles file integrity verification and checksums
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/auth/Auth.php';
require_once __DIR__ . '/../controllers/FileController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get checksums for files
            $fileIds = explode(',', $_GET['file_ids'] ?? '');
            $fileIds = array_filter($fileIds);
            
            if (empty($fileIds)) {
                http_response_code(400);
                echo json_encode(['error' => 'File IDs required']);
                exit;
            }
            
            $controller->getFileChecksums($fileIds, $user['id']);
            break;
            
        case 'POST':
            // Verify file integrity
            $controller->verifyFileIntegrity($user['id']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Checksum API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Checksum operation failed: ' . $e->getMessage()]);
}
?>
