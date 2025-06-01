<?php
/**
 * Quota API - User Quota Management
 * Handles storage quota monitoring and management
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

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

try {
    $controller = new AdminController();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $userId = $_GET['user_id'] ?? $user['id'];
            
            // Users can only view their own quota unless they're admin/moderator
            if ($userId != $user['id'] && !in_array($user['role'], ['admin', 'moderator'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                exit;
            }
            
            $controller->getUserQuota($userId);
            break;
            
        case 'PUT':
            // Only admins can modify quotas
            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                exit;
            }
            
            $userId = $_GET['user_id'] ?? null;
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
                exit;
            }
            
            $controller->updateUserQuota($userId);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Quota API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Quota operation failed: ' . $e->getMessage()]);
}
?>
