<?php
/**
 * Webhook API - Test Webhook Endpoint
 * Handles webhook testing and validation
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/auth/Auth.php';
require_once __DIR__ . '/../controllers/WebhookController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Check permission for webhook testing
if (!in_array($user['role'], ['admin', 'moderator'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions']);
    exit;
}

try {
    $controller = new WebhookController();
    $controller->testWebhook();
} catch (Exception $e) {
    error_log("Webhook Test API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Webhook test failed: ' . $e->getMessage()]);
}
?>
