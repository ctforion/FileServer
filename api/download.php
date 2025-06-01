<?php
require_once '../config.php';
require_once '../core/auth/SimpleFileAuthenticator.php';
require_once '../core/storage/FileManager.php';
require_once '../core/utils/EnvLoader.php';
require_once '../core/utils/Validator.php';

// Load configuration
EnvLoader::load('../config.php');

// Initialize classes
$auth = new SimpleFileAuthenticator(EnvLoader::getStoragePath() . '/users.json');
$fileManager = new FileManager(
    EnvLoader::getStoragePath(),
    EnvLoader::getMaxFileSize(),
    EnvLoader::getAllowedExtensions()
);

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get file path
    $filepath = $_GET['file'] ?? '';
    
    if (empty($filepath)) {
        throw new Exception('File path is required');
    }
    
    // Validate path
    if (!Validator::validatePath($filepath)) {
        throw new Exception('Invalid file path');
    }
    
    // Check if file is in public directory or user is authenticated
    $isPublic = strpos($filepath, 'public/') === 0;
    
    if (!$isPublic) {
        $auth->requireAuth();
    }
    
    // Download file
    if (!$fileManager->download($filepath)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
