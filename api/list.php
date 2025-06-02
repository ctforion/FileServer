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
if (!$security->checkRateLimit($clientIp, 'list', 60, 60)) { // 60 requests per minute
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    $logger->warning('List rate limit exceeded', ['ip' => $clientIp]);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get current user if authenticated
    $currentUser = null;
    if (isset($_SESSION['user_id'])) {
        $currentUser = $userManager->getUserById($_SESSION['user_id']);
    }
    
    // Get directory
    $directory = $_GET['dir'] ?? 'public';
    
    // Validate directory
    $allowedDirs = ['public', 'private', 'temp'];
    if ($currentUser && $currentUser['role'] === 'admin') {
        $allowedDirs[] = 'admin';
    }
    
    if (!in_array($directory, $allowedDirs)) {
        throw new Exception('Invalid directory');
    }
    
    // Check permissions
    if ($directory !== 'public') {
        if (!$currentUser) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        
        // Check admin directory access
        if ($directory === 'admin' && $currentUser['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit;
        }
    }
    
    // Get pagination and filter parameters
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
    $search = $_GET['search'] ?? '';
    $sortBy = $_GET['sort_by'] ?? 'name';
    $sortOrder = $_GET['sort_order'] ?? 'asc';
    $fileType = $_GET['file_type'] ?? '';
    
    // Validate sort parameters
    $allowedSortFields = ['name', 'size', 'created', 'modified', 'type'];
    if (!in_array($sortBy, $allowedSortFields)) {
        $sortBy = 'name';
    }
    
    if (!in_array($sortOrder, ['asc', 'desc'])) {
        $sortOrder = 'asc';
    }
    
    // Get files from database
    $allFiles = $dbManager->getFiles();
    $directoryFiles = [];
    
    // Filter by directory and user permissions
    foreach ($allFiles as $file) {
        $filePath = $file['path'];
        $fileDirectory = dirname($filePath);
        
        // Check if file is in requested directory
        if ($fileDirectory !== $directory && !str_starts_with($filePath, $directory . '/')) {
            continue;
        }
        
        // Check file permissions
        $isPublic = $file['is_public'] ?? (strpos($filePath, 'public/') === 0);
        
        if (!$isPublic && (!$currentUser || 
            ($file['uploaded_by'] !== $currentUser['id'] && $currentUser['role'] !== 'admin'))) {
            continue;
        }
        
        // Apply search filter
        if (!empty($search)) {
            $filename = basename($file['path']);
            if (stripos($filename, $search) === false) {
                continue;
            }
        }
        
        // Apply file type filter
        if (!empty($fileType)) {
            $extension = strtolower(pathinfo($file['path'], PATHINFO_EXTENSION));
            if ($extension !== strtolower($fileType)) {
                continue;
            }
        }
          // Add file size and type information
        $fullPath = $config['storage_path'] . '/' . ltrim($file['path'], '/');
        if (file_exists($fullPath)) {
            $file['size'] = filesize($fullPath);
            $file['readable_size'] = formatFileSize($file['size']);
            $file['type'] = pathinfo($file['path'], PATHINFO_EXTENSION);
            $file['is_image'] = in_array(strtolower($file['type']), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
        }
        
        $directoryFiles[] = $file;
    }
    
    // Sort files
    usort($directoryFiles, function($a, $b) use ($sortBy, $sortOrder) {
        $valueA = $a[$sortBy] ?? '';
        $valueB = $b[$sortBy] ?? '';
        
        if (is_numeric($valueA) && is_numeric($valueB)) {
            $comparison = $valueA <=> $valueB;
        } else {
            $comparison = strcasecmp($valueA, $valueB);
        }
        
        return $sortOrder === 'desc' ? -$comparison : $comparison;
    });
    
    // Apply pagination
    $totalFiles = count($directoryFiles);
    $totalPages = ceil($totalFiles / $limit);
    $offset = ($page - 1) * $limit;
    $paginatedFiles = array_slice($directoryFiles, $offset, $limit);
    
    // Log successful listing
    $logger->info('Files listed', [
        'user_id' => $currentUser['id'] ?? 'anonymous',
        'directory' => $directory,
        'total_files' => $totalFiles,
        'page' => $page,
        'limit' => $limit,
        'search' => $search,
        'ip' => $clientIp
    ]);
    
    // Return response
    echo json_encode([
        'success' => true,
        'data' => [
            'files' => $paginatedFiles,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_files' => $totalFiles,
                'per_page' => $limit,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ],
            'filters' => [
                'directory' => $directory,
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'file_type' => $fileType
            ]
        ]
    ]);
    
} catch (Exception $e) {
    $errorCode = 400;
    if ($e->getMessage() === 'Authentication required') {
        $errorCode = 401;
    } elseif ($e->getMessage() === 'Admin access required') {
        $errorCode = 403;
    }
    
    http_response_code($errorCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    $logger->error('List files failed', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id'] ?? 'anonymous',
        'directory' => $_GET['dir'] ?? 'unknown',
        'ip' => $clientIp
    ]);
}

/**
 * Format file size in human readable format
 */
function formatFileSize($size) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $unitIndex = 0;
    
    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }
    
    return round($size, 2) . ' ' . $units[$unitIndex];
}
