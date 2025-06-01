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
    $config['storage']['upload_path'],
    $config['upload']['max_file_size'],
    $config['upload']['allowed_extensions']
);
$logger = new Logger($config['logging']['log_path']);
$security = new SecurityManager();

// Set response headers
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$currentUser = $userManager->getUserById($_SESSION['user_id']);
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

// CSRF protection
if (empty($_POST['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'CSRF token required']);
    exit;
}

try {
    $security->validateCSRFToken($_POST['csrf_token']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Rate limiting
$clientIp = $_SERVER['REMOTE_ADDR'];
if (!$security->checkRateLimit($clientIp, 'delete', 10, 3600)) { // 10 deletions per hour
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    $logger->warning('Delete rate limit exceeded', ['ip' => $clientIp, 'user_id' => $currentUser['id']]);
    exit;
}

// Only allow DELETE or POST requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'])) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get file path or file ID
    $filepath = $_REQUEST['file'] ?? '';
    $fileId = $_REQUEST['file_id'] ?? '';
    
    if (empty($filepath) && empty($fileId)) {
        throw new Exception('File path or file ID is required');
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
    
    // Check ownership and permissions
    if ($fileMetadata) {
        if ($fileMetadata['uploaded_by'] !== $currentUser['id'] && $currentUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            $logger->warning('Unauthorized delete attempt', [
                'user_id' => $currentUser['id'],
                'file_path' => $filepath,
                'file_owner' => $fileMetadata['uploaded_by'],
                'ip' => $clientIp
            ]);
            exit;
        }
    } else {
        // If no metadata exists, only allow admin to delete
        if ($currentUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied - file metadata not found']);
            exit;
        }
    }
    
    // Additional security: prevent deletion of certain system files
    $protectedPaths = ['admin/system', 'admin/logs', 'admin/config'];
    foreach ($protectedPaths as $protectedPath) {
        if (strpos($filepath, $protectedPath) === 0) {
            if ($currentUser['role'] !== 'admin') {
                throw new Exception('Cannot delete protected system files');
            }
        }
    }
    
    // Check if file exists physically
    $fullPath = $config['storage']['upload_path'] . '/' . ltrim($filepath, '/');
    if (!file_exists($fullPath)) {
        // File doesn't exist physically, but might exist in database
        if ($fileMetadata) {
            // Remove from database only
            $files = $dbManager->getFiles();
            $files = array_filter($files, function($file) use ($fileId, $filepath) {
                return $file['id'] !== $fileId && $file['path'] !== $filepath;
            });
            $dbManager->saveData('files', array_values($files));
            
            $logger->info('File metadata cleaned up (file not found on disk)', [
                'user_id' => $currentUser['id'],
                'file_path' => $filepath,
                'file_id' => $fileId,
                'ip' => $clientIp
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'File metadata cleaned up'
            ]);
            exit;
        } else {
            throw new Exception('File not found');
        }
    }
    
    // Log deletion attempt
    $logger->info('File deletion initiated', [
        'user_id' => $currentUser['id'],
        'file_path' => $filepath,
        'file_id' => $fileId,
        'file_size' => filesize($fullPath),
        'ip' => $clientIp
    ]);
    
    // Create backup before deletion if it's an important file
    $backupPath = null;
    if ($fileMetadata && ($fileMetadata['importance'] ?? 'normal') === 'high') {
        $backupDir = $config['storage']['upload_path'] . '/admin/backups/' . date('Y-m-d');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $backupPath = $backupDir . '/' . basename($filepath) . '_' . date('H-i-s');
        copy($fullPath, $backupPath);
        
        $logger->info('Important file backed up before deletion', [
            'original_path' => $fullPath,
            'backup_path' => $backupPath,
            'user_id' => $currentUser['id']
        ]);
    }
    
    // Delete physical file
    if (unlink($fullPath)) {
        // Remove from database
        if ($fileMetadata) {
            $files = $dbManager->getFiles();
            $files = array_filter($files, function($file) use ($fileId, $filepath) {
                return $file['id'] !== $fileId && $file['path'] !== $filepath;
            });
            $dbManager->saveData('files', array_values($files));
        }
        
        // Log successful deletion
        $logger->info('File deleted successfully', [
            'user_id' => $currentUser['id'],
            'file_path' => $filepath,
            'file_id' => $fileId,
            'backup_created' => $backupPath !== null,
            'backup_path' => $backupPath,
            'ip' => $clientIp
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'File deleted successfully',
            'backup_created' => $backupPath !== null
        ]);
        
    } else {
        throw new Exception('Failed to delete file from disk');
    }
    
} catch (Exception $e) {
    $errorCode = 400;
    if ($e->getMessage() === 'Access denied' || strpos($e->getMessage(), 'Access denied') !== false) {
        $errorCode = 403;
    } elseif ($e->getMessage() === 'File not found') {
        $errorCode = 404;
    }
    
    http_response_code($errorCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    $logger->error('File deletion failed', [
        'error' => $e->getMessage(),
        'user_id' => $currentUser['id'],
        'file_path' => $filepath ?? 'unknown',
        'file_id' => $fileId ?? 'unknown',
        'ip' => $clientIp
    ]);
}
