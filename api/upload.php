<?php
/**
 * File Upload API
 * Enhanced with new database and logging systems
 */

require_once '../config.php';
require_once '../core/storage/FileManager.php';
require_once '../core/logging/Logger.php';
require_once '../core/utils/SecurityManager.php';

header('Content-Type: application/json');
session_start();

// Check authentication
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Initialize classes
$config = include '../config.php';
$fileManager = new FileManager(
    $config['storage_path'],
    $config['max_file_size'] ?? 10485760,
    $config['allowed_extensions'] ?? []
);
$logger = new Logger();
$security = new SecurityManager();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Rate limiting check
    $rateLimitCheck = $security->checkRateLimit('upload');
    if (!$rateLimitCheck['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded: ' . $rateLimitCheck['reason']
        ]);
        exit;
    }
    
    // Validate uploaded file
    if (!isset($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['file'];
    
    // Get target directory (default: private for authenticated users)
    $directory = $_POST['directory'] ?? 'private';
    
    // Validate directory access
    $allowedDirs = ['public', 'private', 'temp'];
    if (!in_array($directory, $allowedDirs)) {
        $directory = 'private';
    }
    
    // Prepare metadata
    $metadata = [
        'description' => $_POST['description'] ?? '',
        'tags' => !empty($_POST['tags']) ? explode(',', $_POST['tags']) : [],
        'category' => $_POST['category'] ?? 'general'
    ];
    
    // Upload file with metadata
    $result = $fileManager->upload($file, $directory, $metadata);
    
    if ($result['success']) {
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'file' => [
                'id' => $result['file_id'],
                'name' => $result['filename'],
                'size' => $result['size'],
                'path' => $result['path'],
                'uploaded_at' => date('Y-m-d H:i:s'),
                'directory' => $directory
            ]
        ]);
        
        $logger->logAccess('file_upload_api', [
            'file_id' => $result['file_id'],
            'filename' => $result['filename'],
            'size' => $result['size'],
            'directory' => $directory,
            'user' => $_SESSION['username'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } else {
        throw new Exception($result['message']);
    }
    
} catch (Exception $e) {
    $logger->logError('file_upload_api_error', [
        'error' => $e->getMessage(),
        'user' => $_SESSION['username'] ?? 'anonymous',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'file' => $_FILES['file']['name'] ?? 'unknown'
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
