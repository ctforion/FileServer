<?php
/**
 * Centralized API Router
 * Clean separation of API logic from integrated PHP code
 * Fast and easy to maintain
 */

// Enable CORS for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Basic error handling
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

try {
    // Load core API configuration
    require_once __DIR__ . '/api/core/ApiConfig.php';
    require_once __DIR__ . '/api/core/ApiResponse.php';
    require_once __DIR__ . '/api/core/ApiAuth.php';
    require_once __DIR__ . '/api/core/ApiRouter.php';
    
    // Initialize configuration
    ApiConfig::init();
    
    // Initialize API router and handle request
    $router = new ApiRouter();
    $router->handleRequest();
      
} catch (Exception $e) {
    ApiResponse::handleException($e);
} catch (Error $e) {
    ApiResponse::handleException($e);
}
?>
