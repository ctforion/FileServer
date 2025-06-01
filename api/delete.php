<?php
require_once '../config.php';
require_once '../core/auth/SimpleFileAuthenticator.php';
require_once '../core/storage/FileManager.php';
require_once '../core/utils/EnvLoader.php';
require_once '../core/utils/Validator.php';

header('Content-Type: application/json');

// Load configuration
EnvLoader::load('../config.php');

// Initialize classes
$auth = new SimpleFileAuthenticator(EnvLoader::getStoragePath() . '/users.json');
$fileManager = new FileManager(
    EnvLoader::getStoragePath(),
    EnvLoader::getMaxFileSize(),
    EnvLoader::getAllowedExtensions()
);

// Check authentication
$auth->requireAuth();

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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
    
    // Don't allow deletion of files in public directory for security
    if (strpos($filepath, 'public/') === 0) {
        throw new Exception('Cannot delete public files via API');
    }
    
    // Delete file
    $result = $fileManager->delete($filepath);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message']
        ]);
    } else {
        throw new Exception($result['message']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
