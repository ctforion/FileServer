<?php
/**
 * Webhook API - List Webhooks Endpoint
 * Handles webhook management operations
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/auth/Auth.php';
require_once __DIR__ . '/../controllers/WebhookController.php';

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

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Check permission for webhook management
if (!in_array($user['role'], ['admin', 'moderator'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions']);
    exit;
}

try {
    $controller = new WebhookController();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $webhookId = $_GET['id'] ?? null;
            if ($webhookId) {
                $controller->getWebhook($webhookId);
            } else {
                $controller->listWebhooks();
            }
            break;
            
        case 'POST':
            $controller->createWebhook();
            break;
            
        case 'PUT':
            $webhookId = $_GET['id'] ?? null;
            if (!$webhookId) {
                http_response_code(400);
                echo json_encode(['error' => 'Webhook ID required']);
                exit;
            }
            $controller->updateWebhook($webhookId);
            break;
            
        case 'DELETE':
            $webhookId = $_GET['id'] ?? null;
            if (!$webhookId) {
                http_response_code(400);
                echo json_encode(['error' => 'Webhook ID required']);
                exit;
            }
            $controller->deleteWebhook($webhookId);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Webhook API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Webhook operation failed: ' . $e->getMessage()]);
}
?>
