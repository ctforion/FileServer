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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Validate uploaded file
    if (!isset($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['file'];
    $validation = Validator::validateUploadedFile($file);
    
    if (!$validation['valid']) {
        throw new Exception($validation['message']);
    }
    
    // Get target directory (default: private for authenticated users)
    $directory = $_POST['directory'] ?? 'private';
    
    // Validate directory
    if (!in_array($directory, ['public', 'private', 'temp'])) {
        $directory = 'private';
    }
    
    // Upload file
    $result = $fileManager->upload($file, $directory);
    
    if ($result['success']) {
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'file' => [
                'name' => $result['filename'],
                'size' => $result['size'],
                'path' => $result['path'],
                'uploaded_at' => date('Y-m-d H:i:s')
            ]
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
