<?php
/**
 * Admin API - User Management
 * Handles user administration operations
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/auth/Auth.php';
require_once __DIR__ . '/../controllers/AdminController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Authenticate user
$auth = new Auth();
$user = $auth->getCurrentUser();

if (!$user || !in_array($user['role'], ['admin', 'moderator'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Administrative access required']);
    exit;
}

try {
    $controller = new AdminController();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $userId = $_GET['id'] ?? null;
            if ($userId) {
                $controller->getUser($userId);
            } else {
                $controller->listUsers();
            }
            break;
            
        case 'POST':
            // Only admins can create users
            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                exit;
            }
            $controller->createUser();
            break;
            
        case 'PUT':
            $userId = $_GET['id'] ?? null;
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
                exit;
            }
            
            // Moderators can only edit regular users
            if ($user['role'] === 'moderator') {
                $targetUser = $controller->getUserById($userId);
                if ($targetUser && in_array($targetUser['role'], ['admin', 'moderator'])) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Cannot modify admin or moderator accounts']);
                    exit;
                }
            }
            
            $controller->updateUser($userId);
            break;
            
        case 'DELETE':
            // Only admins can delete users
            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin access required']);
                exit;
            }
            
            $userId = $_GET['id'] ?? null;
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
                exit;
            }
            
            // Prevent deleting self
            if ($userId == $user['id']) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot delete your own account']);
                exit;
            }
            
            $controller->deleteUser($userId);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Admin Users API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'User operation failed: ' . $e->getMessage()]);
}
?>
