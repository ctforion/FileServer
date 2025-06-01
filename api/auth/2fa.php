<?php
/**
 * Authentication API - Two-Factor Authentication Setup
 * Handles 2FA QR code generation and secret setup
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/auth/Auth.php';
require_once __DIR__ . '/../controllers/AuthController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if 2FA is enabled
if (!ENABLE_2FA) {
    http_response_code(403);
    echo json_encode(['error' => '2FA is disabled']);
    exit;
}

try {
    $controller = new AuthController();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->getTwoFactorSetup();
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->setupTwoFactor();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("2FA Setup API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
