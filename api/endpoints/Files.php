<?php
/**
 * Files API Endpoint
 * Separated file management functionality
 */

require_once dirname(__DIR__, 2) . '/core/storage/FileManager.php';
require_once dirname(__DIR__, 2) . '/core/storage/MetadataManager.php';
require_once dirname(__DIR__, 2) . '/core/logging/Logger.php';
require_once dirname(__DIR__, 2) . '/core/utils/SecurityManager.php';
require_once dirname(__DIR__) . '/core/ApiResponse.php';

class FilesEndpoint {
    private $fileManager;
    private $metadata;
    private $logger;
    private $security;
    
    public function __construct() {
        $config = include dirname(__DIR__, 2) . '/config.php';
        $this->fileManager = new FileManager(
            $config['storage_path'],
            $config['max_file_size'] ?? 10485760,
            $config['allowed_extensions'] ?? []
        );
        $this->metadata = new MetadataManager();
        $this->logger = new Logger();
        $this->security = new SecurityManager();
    }
    
    /**
     * List user files
     */
    public function handleListFiles($params, $user) {
        $page = (int)($_GET['page'] ?? 1);
        $perPage = min((int)($_GET['per_page'] ?? 20), 100);
        $search = $_GET['search'] ?? '';
        $sortBy = $_GET['sort_by'] ?? 'upload_date';
        $sortOrder = $_GET['sort_order'] ?? 'desc';
        
        try {
            // Get user's files
            $userId = $user['role'] === 'admin' && isset($_GET['user_id']) ? $_GET['user_id'] : $user['id'];
            $files = $this->metadata->getUserFiles($userId);
            
            // Filter by search term
            if (!empty($search)) {
                $files = array_filter($files, function($file) use ($search) {
                    return stripos($file['filename'], $search) !== false ||
                           stripos($file['original_name'], $search) !== false;
                });
            }
            
            // Sort files
            $this->sortFiles($files, $sortBy, $sortOrder);
            
            $total = count($files);
            $files = array_slice($files, ($page - 1) * $perPage, $perPage);
            
            // Add additional file information
            $files = array_map([$this, 'enrichFileData'], $files);
            
            ApiResponse::paginated($files, $total, $page, $perPage);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to list files', ['user_id' => $user['id'], 'error' => $e->getMessage()]);
            ApiResponse::serverError('Failed to retrieve files');
        }
    }
    
    /**
     * Upload file
     */
    public function handleUploadFile($params, $user) {
        // Check rate limiting
        $rateLimitCheck = $this->security->checkRateLimit('file_upload');
        if (!$rateLimitCheck['allowed']) {
            ApiResponse::rateLimited('Upload rate limit exceeded: ' . $rateLimitCheck['reason']);
        }
        
        if (!isset($_FILES['file'])) {
            ApiResponse::error('No file uploaded', 400);
        }
        
        $file = $_FILES['file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = $this->getUploadErrorMessage($file['error']);
            ApiResponse::error('Upload failed: ' . $errorMessage, 400);
        }
        
        try {
            // Check user quota
            $currentUsage = $this->metadata->getUserStorageUsage($user['id']);
            if (($currentUsage + $file['size']) > $user['quota']) {
                ApiResponse::error('Upload would exceed storage quota', 413);
            }
            
            // Process upload
            $result = $this->fileManager->uploadFile($file, $user['id']);
            
            if (!$result['success']) {
                ApiResponse::error('Upload failed: ' . $result['error'], 400);
            }
            
            // Store metadata
            $metadata = [
                'user_id' => $user['id'],
                'filename' => $result['filename'],
                'original_name' => $file['name'],
                'size' => $file['size'],
                'mime_type' => $file['type'],
                'upload_date' => date('Y-m-d H:i:s'),
                'file_hash' => $result['hash'] ?? null
            ];
            
            $fileId = $this->metadata->addFile($metadata);
            
            if (!$fileId) {
                // Clean up uploaded file
                $this->fileManager->deleteFile($result['filename'], $user['id']);
                ApiResponse::serverError('Failed to store file metadata');
            }
            
            $this->logger->info('File uploaded via API', [
                'file_id' => $fileId,
                'filename' => $result['filename'],
                'original_name' => $file['name'],
                'size' => $file['size'],
                'user_id' => $user['id']
            ]);
            
            // Return file information
            $fileData = $this->metadata->getFile($fileId);
            $fileData = $this->enrichFileData($fileData);
            
            ApiResponse::success($fileData, 'File uploaded successfully', 201);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to upload file', [
                'user_id' => $user['id'],
                'filename' => $file['name'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            ApiResponse::serverError('Failed to upload file');
        }
    }
    
    /**
     * Download file
     */
    public function handleDownloadFile($params, $user) {
        $fileId = $params['id'] ?? '';
        
        try {
            $file = $this->metadata->getFile($fileId);
            
            if (!$file) {
                ApiResponse::notFound('File not found');
            }
            
            // Check permissions
            if ($user['role'] !== 'admin' && $file['user_id'] !== $user['id']) {
                ApiResponse::forbidden('Access denied');
            }
            
            $filePath = $this->fileManager->getFilePath($file['filename'], $file['user_id']);
            
            if (!file_exists($filePath)) {
                ApiResponse::notFound('File not found on disk');
            }
            
            $this->logger->info('File downloaded via API', [
                'file_id' => $fileId,
                'filename' => $file['filename'],
                'user_id' => $user['id']
            ]);
            
            // Update download count
            $this->metadata->updateFileStats($fileId, ['downloads' => ($file['downloads'] ?? 0) + 1]);
            
            // Stream file download
            ApiResponse::downloadFile($filePath, $file['original_name'], $file['mime_type']);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to download file', [
                'file_id' => $fileId,
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            ApiResponse::serverError('Failed to download file');
        }
    }
    
    /**
     * Delete file
     */
    public function handleDeleteFile($params, $user) {
        $fileId = $params['id'] ?? '';
        
        try {
            $file = $this->metadata->getFile($fileId);
            
            if (!$file) {
                ApiResponse::notFound('File not found');
            }
            
            // Check permissions
            if ($user['role'] !== 'admin' && $file['user_id'] !== $user['id']) {
                ApiResponse::forbidden('Access denied');
            }
            
            // Delete physical file
            $deleted = $this->fileManager->deleteFile($file['filename'], $file['user_id']);
            
            if ($deleted) {
                // Remove metadata
                $this->metadata->deleteFile($fileId);
                
                $this->logger->info('File deleted via API', [
                    'file_id' => $fileId,
                    'filename' => $file['filename'],
                    'user_id' => $user['id']
                ]);
                
                ApiResponse::success(null, 'File deleted successfully');
            } else {
                ApiResponse::serverError('Failed to delete file');
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to delete file', [
                'file_id' => $fileId,
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            ApiResponse::serverError('Failed to delete file');
        }
    }
    
    /**
     * Get file information
     */
    public function handleFileInfo($params, $user) {
        $fileId = $params['id'] ?? '';
        
        try {
            $file = $this->metadata->getFile($fileId);
            
            if (!$file) {
                ApiResponse::notFound('File not found');
            }
            
            // Check permissions
            if ($user['role'] !== 'admin' && $file['user_id'] !== $user['id']) {
                ApiResponse::forbidden('Access denied');
            }
            
            // Enrich with additional data
            $file = $this->enrichFileData($file);
            
            ApiResponse::success($file);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get file info', [
                'file_id' => $fileId,
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            ApiResponse::serverError('Failed to retrieve file information');
        }
    }
    
    /**
     * Get user storage statistics
     */
    public function handleStorageStats($params, $user) {
        $userId = $user['role'] === 'admin' && isset($_GET['user_id']) ? $_GET['user_id'] : $user['id'];
        
        try {
            $stats = [
                'user_id' => $userId,
                'storage_used' => $this->metadata->getUserStorageUsage($userId),
                'storage_quota' => $user['quota'],
                'file_count' => $this->metadata->getUserFileCount($userId),
                'total_downloads' => $this->metadata->getUserTotalDownloads($userId)
            ];
            
            $stats['storage_percentage'] = $stats['storage_quota'] > 0 
                ? round(($stats['storage_used'] / $stats['storage_quota']) * 100, 2) 
                : 0;
            
            ApiResponse::success($stats);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get storage stats', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            ApiResponse::serverError('Failed to retrieve storage statistics');
        }
    }
    
    /**
     * Sort files array
     */
    private function sortFiles(&$files, $sortBy, $sortOrder) {
        $validSortFields = ['filename', 'original_name', 'size', 'upload_date', 'downloads'];
        
        if (!in_array($sortBy, $validSortFields)) {
            $sortBy = 'upload_date';
        }
        
        $sortOrder = strtolower($sortOrder) === 'asc' ? 1 : -1;
        
        usort($files, function($a, $b) use ($sortBy, $sortOrder) {
            $valA = $a[$sortBy] ?? '';
            $valB = $b[$sortBy] ?? '';
            
            if (is_numeric($valA) && is_numeric($valB)) {
                return ($valA <=> $valB) * $sortOrder;
            }
            
            return strcasecmp($valA, $valB) * $sortOrder;
        });
    }
    
    /**
     * Enrich file data with additional information
     */
    private function enrichFileData($file) {
        $file['size_human'] = $this->formatFileSize($file['size']);
        $file['upload_date_human'] = $this->formatDate($file['upload_date']);
        $file['is_image'] = $this->isImageFile($file['mime_type']);
        $file['download_url'] = '/api/files/' . $file['id'] . '/download';
        
        return $file;
    }
    
    /**
     * Format file size in human readable format
     */
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
    
    /**
     * Format date in human readable format
     */
    private function formatDate($date) {
        return date('M j, Y g:i A', strtotime($date));
    }
    
    /**
     * Check if file is an image
     */
    private function isImageFile($mimeType) {
        return strpos($mimeType, 'image/') === 0;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        
        return $errors[$errorCode] ?? 'Unknown upload error';
    }
}
