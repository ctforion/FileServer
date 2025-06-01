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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get directory
    $directory = $_GET['dir'] ?? 'public';
    
    // Validate directory
    if (!in_array($directory, ['public', 'private', 'temp'])) {
        throw new Exception('Invalid directory');
    }
    
    // Check if directory is public or user is authenticated
    if ($directory !== 'public') {
        $auth->requireAuth();
    }
    
    // Get pagination parameters
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
    
    // List files
    $result = $fileManager->listFiles($directory, $page, $limit);
    
    if ($result['success']) {
        echo json_encode($result);
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
