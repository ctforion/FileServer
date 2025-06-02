<?php
/**
 * File Manager
 * Handles file upload, download, and storage operations
 */

require_once __DIR__ . '/../database/DatabaseManager.php';
require_once __DIR__ . '/MetadataManager.php';
require_once __DIR__ . '/../logging/Logger.php';
require_once __DIR__ . '/../utils/SecurityManager.php';

class FileManager {
    private $storagePath;
    private $maxFileSize;
    private $allowedExtensions;
    private $metadata;
    private $logger;
    private $security;
    private $db;
    
    public function __construct($storagePath, $maxFileSize = 10485760, $allowedExtensions = []) {
        $this->storagePath = rtrim($storagePath, '/\\');
        $this->maxFileSize = is_numeric($maxFileSize) ? (int)$maxFileSize : 10485760; // Default 10MB
        $this->allowedExtensions = is_array($allowedExtensions) ? $allowedExtensions : [];
        
        $this->metadata = new MetadataManager();
        $this->logger = new Logger();
        $this->security = new SecurityManager();
        $this->db = DatabaseManager::getInstance();
        
        // Ensure storage directories exist
        $this->ensureDirectoriesExist();
    }
    
    /**
     * Upload a file with metadata
     */
    public function upload($file, $directory = 'public', $metadata = []) {
        try {
            // Validate upload
            $validation = $this->validateUpload($file);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['error'],
                    'error_code' => 'VALIDATION_FAILED'
                ];
            }
            
            // Sanitize directory and filename
            $directory = $this->sanitizeDirectory($directory);
            $filename = $this->generateUniqueFilename($file['name'], $directory);
            $relativePath = $directory . '/' . $filename;
            $fullPath = $this->storagePath . '/' . $relativePath;
            
            // Ensure directory exists
            $this->ensureDirectoryExists(dirname($fullPath));
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                return [
                    'success' => false,
                    'message' => 'Failed to move uploaded file',
                    'error_code' => 'MOVE_FAILED'
                ];
            }
            
            // Set file permissions
            chmod($fullPath, 0644);
            
            // Generate file ID
            $fileId = $this->generateFileId($fullPath);
            
            // Prepare metadata
            $fileMetadata = array_merge($metadata, [
                'original_name' => $file['name'],
                'upload_date' => date('c'),
                'uploader' => $_SESSION['username'] ?? 'anonymous',
                'directory' => $directory,
                'size' => filesize($fullPath),
                'mime_type' => $this->getMimeType($fullPath),
                'checksum' => hash_file('sha256', $fullPath)
            ]);
            
            // Save metadata
            $this->metadata->addFileMetadata($fullPath, $fileMetadata);
            
            // Log upload
            $this->logger->info('File uploaded successfully', [
                'file_id' => $fileId,
                'filename' => $filename,
                'size' => $fileMetadata['size'],
                'user' => $fileMetadata['uploader']
            ]);
            
            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'file_id' => $fileId,
                'filename' => $filename,
                'original_name' => $file['name'],
                'size' => $fileMetadata['size'],
                'path' => $relativePath,
                'mime_type' => $fileMetadata['mime_type'],
                'directory' => $directory,
                'upload_date' => $fileMetadata['upload_date']
            ];
            
        } catch (Exception $e) {
            $this->logger->error('File upload failed', [
                'error' => $e->getMessage(),
                'file' => $file['name'] ?? 'unknown'
            ]);
            
            return [
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
                'error_code' => 'UPLOAD_ERROR'
            ];
        }
    }
    
    /**
     * Upload file for modern API (alternative method name)
     */
    public function uploadFile($file, $directory = 'public', $metadata = []) {
        return $this->upload($file, $directory, $metadata);
    }
    
    /**
     * Download/stream a file
     */
    public function download($filepath) {
        try {
            // Handle both relative and absolute paths
            if (!file_exists($filepath)) {
                $fullPath = $this->storagePath . '/' . ltrim($filepath, '/\\');
                if (!file_exists($fullPath)) {
                    return false;
                }
                $filepath = $fullPath;
            }
            
            // Security check - ensure file is within storage path
            $realPath = realpath($filepath);
            $realStoragePath = realpath($this->storagePath);
            
            if (!$realPath || !$realStoragePath || strpos($realPath, $realStoragePath) !== 0) {
                $this->logger->warning('Attempted access to file outside storage path', [
                    'requested_path' => $filepath,
                    'real_path' => $realPath
                ]);
                return false;
            }
            
            if (!is_readable($filepath)) {
                return false;
            }
            
            // Get file info
            $filename = basename($filepath);
            $filesize = filesize($filepath);
            $mimeType = $this->getMimeType($filepath);
            
            // Set headers for download
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . $filesize);
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            // Stream file in chunks
            $handle = fopen($filepath, 'rb');
            if ($handle === false) {
                return false;
            }
            
            while (!feof($handle)) {
                echo fread($handle, 8192);
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
            
            fclose($handle);
              // Update download count in metadata
            $fileId = $this->generateFileId($filepath);
            $this->metadata->trackDownload($fileId);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('File download failed', [
                'error' => $e->getMessage(),
                'filepath' => $filepath
            ]);
            return false;
        }
    }
    
    /**
     * List files in a directory
     */
    public function listFiles($directory = '', $page = 1, $perPage = 20) {
        try {
            $directory = $this->sanitizeDirectory($directory);
            $fullPath = $this->storagePath . '/' . $directory;
            
            if (!is_dir($fullPath)) {
                return ['files' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
            }
            
            $files = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace($this->storagePath . '/', '', $file->getPathname());
                    $fileData = $this->getFileInfo($file->getPathname());
                    if ($fileData) {
                        $files[] = $fileData;
                    }
                }
            }
            
            // Sort by upload date (newest first)
            usort($files, function($a, $b) {
                return strtotime($b['upload_date'] ?? 0) - strtotime($a['upload_date'] ?? 0);
            });
            
            // Paginate
            $total = count($files);
            $offset = ($page - 1) * $perPage;
            $files = array_slice($files, $offset, $perPage);
            
            return [
                'files' => $files,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to list files', [
                'error' => $e->getMessage(),
                'directory' => $directory
            ]);
            
            return ['files' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }
    }
    
    /**
     * Delete a file
     */
    public function deleteFile($filepath) {
        try {
            $fullPath = $this->storagePath . '/' . ltrim($filepath, '/\\');
            
            if (!file_exists($fullPath)) {
                return false;
            }
            
            // Security check
            $realPath = realpath($fullPath);
            $realStoragePath = realpath($this->storagePath);
            
            if (!$realPath || !$realStoragePath || strpos($realPath, $realStoragePath) !== 0) {
                return false;
            }
              // Remove metadata and database record
            $fileId = $this->generateFileId($fullPath);
            $this->db->deleteFile($fileId);
            
            // Delete file
            $result = unlink($fullPath);
            
            if ($result) {
                $this->logger->info('File deleted', ['filepath' => $filepath]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('File deletion failed', [
                'error' => $e->getMessage(),
                'filepath' => $filepath
            ]);
            return false;
        }
    }
    
    /**
     * Validate file upload
     */
    private function validateUpload($file) {
        // Check for upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['valid' => false, 'error' => 'Invalid file upload'];
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['valid' => false, 'error' => 'No file uploaded'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['valid' => false, 'error' => 'File too large'];
            default:
                return ['valid' => false, 'error' => 'Upload error occurred'];
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return ['valid' => false, 'error' => 'File size exceeds limit (' . number_format($this->maxFileSize / 1024 / 1024, 1) . 'MB)'];
        }
        
        // Check file extension
        if (!empty($this->allowedExtensions)) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, array_map('strtolower', $this->allowedExtensions))) {
                return ['valid' => false, 'error' => 'File type not allowed'];
            }
        }
          // Security checks
        if (!$this->security->validateFileUpload($file, $this->storagePath)) {
            return ['valid' => false, 'error' => 'File failed security checks'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Generate unique filename
     */
    private function generateUniqueFilename($originalName, $directory) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize basename
        $basename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $basename);
        $basename = trim($basename, '._-');
        
        if (empty($basename)) {
            $basename = 'file_' . date('YmdHis');
        }
        
        $counter = 0;
        $filename = $basename . ($extension ? '.' . $extension : '');
        
        while (file_exists($this->storagePath . '/' . $directory . '/' . $filename)) {
            $counter++;
            $filename = $basename . '_' . $counter . ($extension ? '.' . $extension : '');
        }
        
        return $filename;
    }
    
    /**
     * Sanitize directory path
     */
    private function sanitizeDirectory($directory) {
        $directory = trim($directory, '/\\');
        $directory = preg_replace('/[^a-zA-Z0-9._/-]/', '_', $directory);
        $directory = preg_replace('/\/+/', '/', $directory);
        
        // Prevent directory traversal
        $directory = str_replace(['../', '..\\'], '', $directory);
        
        return $directory ?: 'public';
    }
    
    /**
     * Generate file ID
     */
    private function generateFileId($filepath) {
        return md5($filepath . microtime(true));
    }
    
    /**
     * Get MIME type
     */
    private function getMimeType($filepath) {
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($filepath);
            if ($mimeType !== false) {
                return $mimeType;
            }
        }
        
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            if ($mimeType !== false) {
                return $mimeType;
            }
        }
        
        // Fallback based on extension
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Get file information
     */
    private function getFileInfo($filepath) {
        if (!file_exists($filepath)) {
            return null;
        }
        
        $relativePath = str_replace($this->storagePath . '/', '', $filepath);
        $fileId = $this->generateFileId($filepath);
        
        // Try to get metadata from database
        $metadata = $this->metadata->getFileMetadata($fileId);
        
        return [
            'id' => $fileId,
            'filename' => basename($filepath),
            'path' => $relativePath,
            'size' => filesize($filepath),
            'mime_type' => $this->getMimeType($filepath),
            'upload_date' => $metadata['upload_date'] ?? date('c', filemtime($filepath)),
            'last_modified' => date('c', filemtime($filepath)),
            'download_count' => $metadata['download_count'] ?? 0,
            'description' => $metadata['description'] ?? '',
            'tags' => $metadata['tags'] ?? [],
            'category' => $metadata['category'] ?? 'general'
        ];
    }
      /**
     * Ensure storage directories exist
     */
    private function ensureDirectoriesExist() {
        $directories = [
            $this->storagePath,
            $this->storagePath . '/public',
            $this->storagePath . '/private',
            $this->storagePath . '/temp'
        ];
        
        foreach ($directories as $dir) {
            $this->ensureDirectoryExists($dir);
        }
    }
    
    /**
     * Ensure a specific directory exists
     */
    private function ensureDirectoryExists($directory) {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new Exception("Failed to create directory: $directory");
            }
        }
    }
    
    /**
     * Get storage statistics
     */
    public function getStorageStats() {
        try {
            $totalFiles = 0;
            $totalSize = 0;
            $typeStats = [];
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->storagePath, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $totalFiles++;
                    $size = $file->getSize();
                    $totalSize += $size;
                    
                    $extension = strtolower($file->getExtension());
                    if (!isset($typeStats[$extension])) {
                        $typeStats[$extension] = ['count' => 0, 'size' => 0];
                    }
                    $typeStats[$extension]['count']++;
                    $typeStats[$extension]['size'] += $size;
                }
            }
            
            return [
                'total_files' => $totalFiles,
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'type_statistics' => $typeStats,
                'storage_path' => $this->storagePath
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get storage stats', ['error' => $e->getMessage()]);
            return [
                'total_files' => 0,
                'total_size' => 0,
                'total_size_formatted' => '0 B',
                'type_statistics' => [],
                'storage_path' => $this->storagePath
            ];
        }
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}-