<?php
session_start();

require_once '../config.php';
require_once '../core/database/DatabaseManager.php';
require_once '../core/auth/UserManager.php';
require_once '../core/storage/FileManager.php';
require_once '../core/logging/Logger.php';
require_once '../core/utils/SecurityManager.php';

// Initialize managers
$config = require '../config.php';
$dbManager = DatabaseManager::getInstance();
$userManager = new UserManager();
$fileManager = new FileManager(
    $config['storage_path'],
    $config['max_file_size'],
    $config['allowed_extensions']
);
$logger = new Logger($config['logging']['log_path']);
$security = new SecurityManager();

// Set response headers
header('Content-Type: application/json');

// Rate limiting
$clientIp = $_SERVER['REMOTE_ADDR'];
if (!$security->checkRateLimit($clientIp, 'download', 30, 60)) { // 30 downloads per minute
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    $logger->warning('Download rate limit exceeded', ['ip' => $clientIp]);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get file path and validate
    $filepath = $_GET['file'] ?? '';
    $fileId = $_GET['file_id'] ?? '';
    
    if (empty($filepath) && empty($fileId)) {
        throw new Exception('File path or file ID is required');
    }
    
    // Get current user if authenticated
    $currentUser = null;
    if (isset($_SESSION['user_id'])) {
        $currentUser = $userManager->getUserById($_SESSION['user_id']);
    }
    
    // Find file metadata
    $fileMetadata = null;
    if (!empty($fileId)) {
        $files = $dbManager->getFiles();
        foreach ($files as $file) {
            if ($file['id'] === $fileId) {
                $fileMetadata = $file;
                $filepath = $file['path'];
                break;
            }
        }
        if (!$fileMetadata) {
            throw new Exception('File not found');
        }
    } else {
        // Find by path
        $files = $dbManager->getFiles();
        foreach ($files as $file) {
            if ($file['path'] === $filepath) {
                $fileMetadata = $file;
                break;
            }
        }
    }
    
    // Validate file path
    if (!$security->validateFilePath($filepath)) {
        throw new Exception('Invalid file path');
    }
    
    // Check file permissions
    $isPublic = isset($fileMetadata['is_public']) ? $fileMetadata['is_public'] : 
                (strpos($filepath, 'public/') === 0);
    
    if (!$isPublic) {
        if (!$currentUser) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        
        // Check if user owns the file or is admin
        if ($fileMetadata && 
            $fileMetadata['uploaded_by'] !== $currentUser['id'] && 
            $currentUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            $logger->warning('Unauthorized download attempt', [
                'user_id' => $currentUser['id'],
                'file_path' => $filepath,
                'ip' => $clientIp
            ]);
            exit;
        }
    }
    
    // Check if file exists physically
    $fullPath = $config['storage_path'] . '/' . ltrim($filepath, '/');
    if (!file_exists($fullPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        $logger->error('File not found on disk', ['file_path' => $fullPath]);
        exit;
    }
    
    // Log download attempt
    $logger->info('File download initiated', [
        'user_id' => $currentUser['id'] ?? 'anonymous',
        'file_path' => $filepath,
        'file_id' => $fileId,
        'ip' => $clientIp,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Update download count if metadata exists
    if ($fileMetadata) {
        $downloadCount = ($fileMetadata['download_count'] ?? 0) + 1;
        $lastDownload = date('Y-m-d H:i:s');
        
        // Update file metadata
        $files = $dbManager->getFiles();
        foreach ($files as &$file) {
            if ($file['id'] === $fileMetadata['id']) {
                $file['download_count'] = $downloadCount;
                $file['last_downloaded'] = $lastDownload;
                if ($currentUser) {
                    $file['last_downloaded_by'] = $currentUser['id'];
                }
                break;
            }
        }
        $dbManager->saveData('files', $files);
    }
    
    // Perform download
    if ($fileManager->download($filepath)) {
        // Log successful download
        $logger->info('File download completed', [
            'user_id' => $currentUser['id'] ?? 'anonymous',
            'file_path' => $filepath,
            'file_size' => filesize($fullPath),
            'ip' => $clientIp
        ]);
    } else {
        throw new Exception('Download failed');
    }
    
} catch (Exception $e) {
    $errorCode = 400;
    if ($e->getMessage() === 'Authentication required') {
        $errorCode = 401;
    } elseif ($e->getMessage() === 'Access denied') {
        $errorCode = 403;
    } elseif ($e->getMessage() === 'File not found') {
        $errorCode = 404;
    }
    
    http_response_code($errorCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    $logger->error('Download failed', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id'] ?? 'anonymous',
        'file_path' => $filepath ?? 'unknown',
        'ip' => $clientIp
    ]);
}
