<?php
/**
 * Admin API - System Settings Management
 * Handles system configuration updates
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/auth/Auth.php';
require_once __DIR__ . '/../controllers/AdminController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Authenticate user
$auth = new Auth();
$user = $auth->getCurrentUser();

if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

try {
    $controller = new AdminController();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $controller->getSystemSettings();
            break;
            
        case 'PUT':
            $controller->updateSystemSettings();
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Admin Settings API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Settings operation failed: ' . $e->getMessage()]);
}
?>
