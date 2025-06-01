<?php
/**
 * Sync API - File Synchronization Status
 * Handles file sync status and conflict resolution
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/auth/Auth.php';
require_once __DIR__ . '/../controllers/FileController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
            // Get sync status for files
            $lastSync = $_GET['last_sync'] ?? null;
            $controller->getSyncStatus($user['id'], $lastSync);
            break;
            
        case 'POST':
            // Initialize sync session
            $controller->initializeSync($user['id']);
            break;
            
        case 'PUT':
            // Update sync status or resolve conflicts
            $action = $_GET['action'] ?? 'update';
            
            if ($action === 'resolve') {
                $controller->resolveConflicts();
            } else {
                $controller->updateSyncStatus();
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Sync API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Sync operation failed: ' . $e->getMessage()]);
}
?>
