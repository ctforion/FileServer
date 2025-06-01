<?php
/**
 * File Storage Management System
 * Handles file upload, storage, compression, versioning, and metadata
 */

namespace FileServer\Core\Storage;

use FileServer\Core\Database\Database;
use FileServer\Core\Auth\Auth;
use Exception;
use finfo;

class FileManager {
    private static $instance = null;
    private $db;
    private $auth;
    private $storagePath;
    private $allowedTypes;
    private $maxFileSize;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->db = Database::getInstance();
        $this->auth = Auth::getInstance();
        $this->storagePath = storage_path('files');
        $this->allowedTypes = explode(',', str_replace(' ', '', ALLOWED_FILE_TYPES));
        $this->maxFileSize = size_to_bytes(MAX_FILE_SIZE);
        
        $this->ensureStorageDirectories();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Ensure storage directories exist
     */
    private function ensureStorageDirectories(): void {
        $directories = [
            $this->storagePath,
            $this->storagePath . DIRECTORY_SEPARATOR . 'uploads',
            $this->storagePath . DIRECTORY_SEPARATOR . 'compressed',
            $this->storagePath . DIRECTORY_SEPARATOR . 'versions',
            $this->storagePath . DIRECTORY_SEPARATOR . 'thumbnails',
            $this->storagePath . DIRECTORY_SEPARATOR . 'temp'
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Upload and store file
     */
    public function uploadFile(array $fileData, array $options = []): array {
        try {
            $currentUser = $this->auth->getCurrentUser();
            if (!$currentUser) {
                return ['success' => false, 'message' => 'Authentication required'];
            }

            // Validate file upload
            $validation = $this->validateUpload($fileData, $currentUser);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }

            // Generate file information
            $fileInfo = $this->generateFileInfo($fileData, $options);
            
            // Check for duplicate files
            if (DEDUPLICATE_FILES) {
                $existingFile = $this->findDuplicateFile($fileInfo['hash']);
                if ($existingFile) {
                    return $this->handleDuplicateFile($existingFile, $fileInfo, $currentUser);
                }
            }

            // Store physical file
            $storagePath = $this->storePhysicalFile($fileData['tmp_name'], $fileInfo);
            if (!$storagePath) {
                return ['success' => false, 'message' => 'Failed to store file'];
            }

            // Compress file if enabled and appropriate
            $compressionInfo = null;
            if (COMPRESSION_ENABLED && $this->shouldCompress($fileInfo)) {
                $compressionInfo = $this->compressFile($storagePath, $fileInfo);
            }

            // Extract metadata
            $metadata = $this->extractMetadata($storagePath, $fileInfo);

            // Generate thumbnail if it's an image
            $thumbnailPath = null;
            if ($this->isImage($fileInfo['mime_type'])) {
                $thumbnailPath = $this->generateThumbnail($storagePath, $fileInfo);
            }

            // Store file record in database
            $fileRecord = $this->createFileRecord($fileInfo, $currentUser, $storagePath, $compressionInfo, $metadata, $thumbnailPath, $options);
            
            if (!$fileRecord) {
                // Clean up physical file if database insert failed
                $this->cleanupFile($storagePath);
                return ['success' => false, 'message' => 'Failed to save file information'];
            }

            // Update user storage usage
            $this->updateUserStorageUsage($currentUser['id'], $fileInfo['size']);

            // Log file upload
            $this->log("File uploaded: {$fileInfo['original_name']} by {$currentUser['username']}", 'info');
            $this->auditLog('file.upload', 'file', $fileRecord['id'], null, $fileRecord);

            // Trigger webhooks
            $this->triggerWebhook('file.uploaded', $fileRecord);

            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'file' => $this->sanitizeFileData($fileRecord)
            ];

        } catch (Exception $e) {
            $this->log("File upload error: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'An error occurred during file upload'];
        }
    }

    /**
     * Validate file upload
     */
    private function validateUpload(array $fileData, array $user): array {
        // Check for upload errors
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => $this->getUploadErrorMessage($fileData['error'])];
        }

        // Check file size
        if ($fileData['size'] > $this->maxFileSize) {
            return ['valid' => false, 'message' => 'File size exceeds maximum allowed size'];
        }

        // Check file extension
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes) && !in_array('*', $this->allowedTypes)) {
            return ['valid' => false, 'message' => 'File type not allowed'];
        }

        // Check user storage quota
        if ($user['storage_quota'] > 0 && ($user['storage_used'] + $fileData['size']) > $user['storage_quota']) {
            return ['valid' => false, 'message' => 'Storage quota exceeded'];
        }

        // Security scan
        if (!$this->securityScan($fileData['tmp_name'], $fileData['name'])) {
            return ['valid' => false, 'message' => 'File failed security scan'];
        }

        return ['valid' => true];
    }

    /**
     * Generate file information
     */
    private function generateFileInfo(array $fileData, array $options): array {
        $originalName = $fileData['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mimeType = $this->getMimeType($fileData['tmp_name']);
        $hash = hash_file('sha256', $fileData['tmp_name']);
        
        // Generate unique filename
        $filename = $this->generateUniqueFilename($originalName, $hash);

        return [
            'original_name' => $originalName,
            'filename' => $filename,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $fileData['size'],
            'hash' => $hash,
            'is_public' => $options['is_public'] ?? false,
            'description' => $options['description'] ?? '',
            'tags' => $options['tags'] ?? '',
            'parent_id' => $options['parent_id'] ?? null
        ];
    }

    /**
     * Store physical file
     */
    private function storePhysicalFile(string $tempPath, array $fileInfo): ?string {
        $targetDir = $this->storagePath . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileInfo['filename'];
        
        if (move_uploaded_file($tempPath, $targetPath)) {
            // Set file permissions
            chmod($targetPath, 0644);
            return $targetPath;
        }

        return null;
    }

    /**
     * Create file record in database
     */
    private function createFileRecord(array $fileInfo, array $user, string $storagePath, ?array $compressionInfo, array $metadata, ?string $thumbnailPath, array $options): ?array {
        $relativePath = str_replace($this->storagePath . DIRECTORY_SEPARATOR, '', $storagePath);
        
        $fileRecord = [
            'user_id' => $user['id'],
            'parent_id' => $fileInfo['parent_id'],
            'filename' => $fileInfo['filename'],
            'original_filename' => $fileInfo['original_name'],
            'file_path' => $relativePath,
            'file_hash' => $fileInfo['hash'],
            'mime_type' => $fileInfo['mime_type'],
            'file_size' => $fileInfo['size'],
            'is_public' => $fileInfo['is_public'] ? 1 : 0,
            'description' => $fileInfo['description'],
            'tags' => $fileInfo['tags'],
            'metadata' => json_encode($metadata),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Add compression info if available
        if ($compressionInfo) {
            $fileRecord['compressed_size'] = $compressionInfo['compressed_size'];
            $fileRecord['is_compressed'] = 1;
            $fileRecord['compression_type'] = $compressionInfo['type'];
        }

        $success = $this->db->insert('files', $fileRecord);
        
        if ($success) {
            $fileRecord['id'] = $this->db->getLastInsertId();
            return $fileRecord;
        }

        return null;
    }

    /**
     * Download file
     */
    public function downloadFile(int $fileId, array $options = []): array {
        try {
            $currentUser = $this->auth->getCurrentUser();
            
            // Get file record
            $file = $this->getFileById($fileId);
            if (!$file) {
                return ['success' => false, 'message' => 'File not found'];
            }

            // Check permissions
            if (!$this->hasFileAccess($file, $currentUser, 'read')) {
                return ['success' => false, 'message' => 'Access denied'];
            }

            // Check if file exists physically
            $filePath = $this->getPhysicalPath($file);
            if (!file_exists($filePath)) {
                return ['success' => false, 'message' => 'Physical file not found'];
            }

            // Update download count
            $this->incrementDownloadCount($fileId);

            // Log download
            $this->log("File downloaded: {$file['original_filename']} by " . ($currentUser['username'] ?? 'anonymous'), 'info');
            $this->auditLog('file.download', 'file', $fileId, null, ['filename' => $file['original_filename']]);

            // Prepare download response
            return [
                'success' => true,
                'file_path' => $filePath,
                'filename' => $file['original_filename'],
                'mime_type' => $file['mime_type'],
                'file_size' => $file['file_size'],
                'is_compressed' => $file['is_compressed']
            ];

        } catch (Exception $e) {
            $this->log("File download error: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'An error occurred during download'];
        }
    }

    /**
     * Delete file
     */
    public function deleteFile(int $fileId, bool $permanent = false): array {
        try {
            $currentUser = $this->auth->getCurrentUser();
            if (!$currentUser) {
                return ['success' => false, 'message' => 'Authentication required'];
            }

            // Get file record
            $file = $this->getFileById($fileId);
            if (!$file) {
                return ['success' => false, 'message' => 'File not found'];
            }

            // Check permissions
            if (!$this->hasFileAccess($file, $currentUser, 'delete')) {
                return ['success' => false, 'message' => 'Access denied'];
            }

            if ($permanent || !ENABLE_TRASH) {
                // Permanent deletion
                $result = $this->permanentDelete($file, $currentUser);
            } else {
                // Move to trash
                $result = $this->moveToTrash($file, $currentUser);
            }

            if ($result['success']) {
                // Update user storage usage
                $this->updateUserStorageUsage($currentUser['id'], -$file['file_size']);

                // Trigger webhooks
                $this->triggerWebhook('file.deleted', $file);
            }

            return $result;

        } catch (Exception $e) {
            $this->log("File deletion error: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'An error occurred during deletion'];
        }
    }

    /**
     * Get file list with filtering and pagination
     */
    public function getFileList(array $filters = [], int $page = 1, int $perPage = 20): array {
        try {
            $currentUser = $this->auth->getCurrentUser();
            if (!$currentUser) {
                return ['success' => false, 'message' => 'Authentication required'];
            }

            $offset = ($page - 1) * $perPage;
            $whereConditions = ['status = ?'];
            $params = ['active'];

            // User filter
            if ($currentUser['role'] !== 'admin') {
                $whereConditions[] = '(user_id = ? OR is_public = 1)';
                $params[] = $currentUser['id'];
            }

            // Parent directory filter
            if (isset($filters['parent_id'])) {
                $whereConditions[] = 'parent_id = ?';
                $params[] = $filters['parent_id'];
            } else {
                $whereConditions[] = 'parent_id IS NULL';
            }

            // Search filter
            if (!empty($filters['search'])) {
                $whereConditions[] = '(original_filename LIKE ? OR description LIKE ? OR tags LIKE ?)';
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            // File type filter
            if (!empty($filters['type'])) {
                $whereConditions[] = 'mime_type LIKE ?';
                $params[] = $filters['type'] . '%';
            }

            // Date range filter
            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'created_at >= ?';
                $params[] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'created_at <= ?';
                $params[] = $filters['date_to'];
            }

            $whereClause = implode(' AND ', $whereConditions);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM files WHERE {$whereClause}";
            $totalResult = $this->db->selectOne($countSql, $params);
            $total = $totalResult['total'] ?? 0;

            // Get files
            $orderBy = $filters['sort'] ?? 'created_at DESC';
            $sql = "SELECT * FROM files WHERE {$whereClause} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
            $files = $this->db->select($sql, $params);

            // Sanitize file data
            $files = array_map([$this, 'sanitizeFileData'], $files);

            return [
                'success' => true,
                'files' => $files,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                ]
            ];

        } catch (Exception $e) {
            $this->log("File list error: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'An error occurred while fetching files'];
        }
    }

    /**
     * Create directory
     */
    public function createDirectory(string $name, int $parentId = null): array {
        try {
            $currentUser = $this->auth->getCurrentUser();
            if (!$currentUser) {
                return ['success' => false, 'message' => 'Authentication required'];
            }

            // Validate directory name
            if (!$this->isValidDirectoryName($name)) {
                return ['success' => false, 'message' => 'Invalid directory name'];
            }

            // Check if directory already exists
            $existing = $this->db->selectOne(
                "SELECT id FROM files WHERE user_id = ? AND parent_id = ? AND filename = ? AND is_directory = 1 AND status = 'active'",
                [$currentUser['id'], $parentId, $name]
            );

            if ($existing) {
                return ['success' => false, 'message' => 'Directory already exists'];
            }

            // Create directory record
            $directoryRecord = [
                'user_id' => $currentUser['id'],
                'parent_id' => $parentId,
                'filename' => $name,
                'original_filename' => $name,
                'file_path' => '', // Directories don't have physical paths
                'file_hash' => '',
                'mime_type' => 'directory',
                'file_size' => 0,
                'is_directory' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $success = $this->db->insert('files', $directoryRecord);
            
            if ($success) {
                $directoryRecord['id'] = $this->db->getLastInsertId();
                
                // Log directory creation
                $this->log("Directory created: {$name} by {$currentUser['username']}", 'info');
                $this->auditLog('directory.create', 'file', $directoryRecord['id'], null, $directoryRecord);

                return [
                    'success' => true,
                    'message' => 'Directory created successfully',
                    'directory' => $this->sanitizeFileData($directoryRecord)
                ];
            }

            return ['success' => false, 'message' => 'Failed to create directory'];

        } catch (Exception $e) {
            $this->log("Directory creation error: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'An error occurred while creating directory'];
        }
    }

    /**
     * Helper methods
     */
    private function generateUniqueFilename(string $originalName, string $hash): string {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Use first 8 characters of hash as prefix for uniqueness
        $prefix = substr($hash, 0, 8);
        
        return $prefix . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $baseName) . '.' . $extension;
    }

    private function getMimeType(string $filePath): string {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($filePath) ?: 'application/octet-stream';
    }

    private function securityScan(string $filePath, string $filename): bool {
        // Basic security checks
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'pht', 'phar', 'exe', 'bat', 'cmd', 'com', 'scr', 'vbs', 'js', 'jar'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($extension, $dangerousExtensions)) {
            return false;
        }

        // Check file content for PHP tags
        $content = file_get_contents($filePath, false, null, 0, 1024);
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            return false;
        }

        return true;
    }

    private function shouldCompress(array $fileInfo): bool {
        $compressibleTypes = ['text/', 'application/json', 'application/xml', 'application/javascript'];
        $nonCompressibleTypes = ['image/', 'video/', 'audio/', 'application/zip', 'application/gzip'];
        
        foreach ($compressibleTypes as $type) {
            if (strpos($fileInfo['mime_type'], $type) === 0) {
                return $fileInfo['size'] > 1024; // Only compress files larger than 1KB
            }
        }
        
        foreach ($nonCompressibleTypes as $type) {
            if (strpos($fileInfo['mime_type'], $type) === 0) {
                return false;
            }
        }
        
        return false;
    }

    private function compressFile(string $filePath, array $fileInfo): ?array {
        $compressedPath = $this->storagePath . DIRECTORY_SEPARATOR . 'compressed' . DIRECTORY_SEPARATOR . basename($filePath) . '.gz';
        
        $originalContent = file_get_contents($filePath);
        $compressedContent = gzcompress($originalContent, 9);
        
        if ($compressedContent && file_put_contents($compressedPath, $compressedContent)) {
            $compressedSize = filesize($compressedPath);
            
            // Only use compression if it saves significant space
            if ($compressedSize < ($fileInfo['size'] * 0.9)) {
                return [
                    'compressed_size' => $compressedSize,
                    'type' => 'gzip',
                    'path' => $compressedPath
                ];
            } else {
                // Remove compressed file if not beneficial
                unlink($compressedPath);
            }
        }
        
        return null;
    }

    private function extractMetadata(string $filePath, array $fileInfo): array {
        $metadata = [
            'size' => $fileInfo['size'],
            'hash' => $fileInfo['hash'],
            'created' => date('c'),
            'mime_type' => $fileInfo['mime_type']
        ];

        // Extract image metadata
        if ($this->isImage($fileInfo['mime_type'])) {
            $imageInfo = getimagesize($filePath);
            if ($imageInfo) {
                $metadata['width'] = $imageInfo[0];
                $metadata['height'] = $imageInfo[1];
                $metadata['type'] = image_type_to_mime_type($imageInfo[2]);
            }

            // Extract EXIF data if available
            if (function_exists('exif_read_data') && in_array($fileInfo['extension'], ['jpg', 'jpeg', 'tiff'])) {
                $exif = @exif_read_data($filePath);
                if ($exif) {
                    $metadata['exif'] = array_filter($exif, function($key) {
                        return !is_array($exif[$key]);
                    }, ARRAY_FILTER_USE_KEY);
                }
            }
        }

        return $metadata;
    }

    private function isImage(string $mimeType): bool {
        return strpos($mimeType, 'image/') === 0;
    }

    private function generateThumbnail(string $filePath, array $fileInfo): ?string {
        if (!extension_loaded('gd')) {
            return null;
        }

        $thumbnailDir = $this->storagePath . DIRECTORY_SEPARATOR . 'thumbnails';
        $thumbnailPath = $thumbnailDir . DIRECTORY_SEPARATOR . pathinfo($fileInfo['filename'], PATHINFO_FILENAME) . '_thumb.jpg';

        $image = null;
        
        switch ($fileInfo['mime_type']) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($filePath);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($filePath);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = imagecreatefromwebp($filePath);
                }
                break;
        }

        if ($image) {
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);
            
            $thumbWidth = 200;
            $thumbHeight = 200;
            
            // Calculate proportional dimensions
            $ratio = min($thumbWidth / $originalWidth, $thumbHeight / $originalHeight);
            $newWidth = intval($originalWidth * $ratio);
            $newHeight = intval($originalHeight * $ratio);
            
            $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            
            if (imagejpeg($thumbnail, $thumbnailPath, 80)) {
                imagedestroy($image);
                imagedestroy($thumbnail);
                return str_replace($this->storagePath . DIRECTORY_SEPARATOR, '', $thumbnailPath);
            }
            
            imagedestroy($image);
            imagedestroy($thumbnail);
        }

        return null;
    }

    private function getFileById(int $fileId): ?array {
        return $this->db->selectOne("SELECT * FROM files WHERE id = ? AND status = 'active'", [$fileId]);
    }

    private function hasFileAccess(array $file, ?array $user, string $permission): bool {
        // Public files can be read by anyone
        if ($permission === 'read' && $file['is_public']) {
            return true;
        }

        // Must be logged in for other operations
        if (!$user) {
            return false;
        }

        // Admin has all access
        if ($user['role'] === 'admin') {
            return true;
        }

        // Owner has all access to their files
        if ($file['user_id'] == $user['id']) {
            return true;
        }

        // Check specific permissions
        return $this->auth->hasPermission($permission, $file['id']);
    }

    private function getPhysicalPath(array $file): string {
        return $this->storagePath . DIRECTORY_SEPARATOR . $file['file_path'];
    }

    private function incrementDownloadCount(int $fileId): void {
        $this->db->query("UPDATE files SET download_count = download_count + 1 WHERE id = ?", [$fileId]);
    }

    private function updateUserStorageUsage(int $userId, int $sizeChange): void {
        $this->db->query("UPDATE users SET storage_used = storage_used + ? WHERE id = ?", [$sizeChange, $userId]);
    }

    private function sanitizeFileData(array $file): array {
        // Remove sensitive information
        unset($file['file_hash']);
        return $file;
    }

    private function isValidDirectoryName(string $name): bool {
        return !empty($name) && 
               strlen($name) <= 255 && 
               !preg_match('/[<>:"|?*]/', $name) && 
               !in_array($name, ['.', '..']);
    }

    private function findDuplicateFile(string $hash): ?array {
        return $this->db->selectOne("SELECT * FROM files WHERE file_hash = ? AND status = 'active' LIMIT 1", [$hash]);
    }

    private function handleDuplicateFile(array $existingFile, array $fileInfo, array $user): array {
        // Create a reference to the existing file instead of storing duplicate
        $fileRecord = [
            'user_id' => $user['id'],
            'parent_id' => $fileInfo['parent_id'],
            'filename' => $fileInfo['filename'],
            'original_filename' => $fileInfo['original_name'],
            'file_path' => $existingFile['file_path'], // Reference to existing file
            'file_hash' => $fileInfo['hash'],
            'mime_type' => $fileInfo['mime_type'],
            'file_size' => $fileInfo['size'],
            'is_public' => $fileInfo['is_public'] ? 1 : 0,
            'description' => $fileInfo['description'],
            'tags' => $fileInfo['tags'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $success = $this->db->insert('files', $fileRecord);
        
        if ($success) {
            $fileRecord['id'] = $this->db->getLastInsertId();
            return [
                'success' => true,
                'message' => 'File uploaded successfully (deduplicated)',
                'file' => $this->sanitizeFileData($fileRecord),
                'deduplicated' => true
            ];
        }

        return ['success' => false, 'message' => 'Failed to create file reference'];
    }

    private function permanentDelete(array $file, array $user): array {
        // Delete physical file
        $filePath = $this->getPhysicalPath($file);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete compressed version if exists
        if ($file['is_compressed']) {
            $compressedPath = $this->storagePath . DIRECTORY_SEPARATOR . 'compressed' . DIRECTORY_SEPARATOR . basename($file['file_path']) . '.gz';
            if (file_exists($compressedPath)) {
                unlink($compressedPath);
            }
        }

        // Delete from database
        $deleted = $this->db->delete('files', 'id = ?', [$file['id']]);

        if ($deleted) {
            $this->log("File permanently deleted: {$file['original_filename']} by {$user['username']}", 'info');
            $this->auditLog('file.delete_permanent', 'file', $file['id'], $file, null);
            
            return ['success' => true, 'message' => 'File permanently deleted'];
        }

        return ['success' => false, 'message' => 'Failed to delete file'];
    }

    private function moveToTrash(array $file, array $user): array {
        $updated = $this->db->update('files', [
            'status' => 'deleted',
            'deleted_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$file['id']]);

        if ($updated) {
            $this->log("File moved to trash: {$file['original_filename']} by {$user['username']}", 'info');
            $this->auditLog('file.delete', 'file', $file['id'], null, ['status' => 'deleted']);
            
            return ['success' => true, 'message' => 'File moved to trash'];
        }

        return ['success' => false, 'message' => 'Failed to move file to trash'];
    }

    private function getUploadErrorMessage(int $errorCode): string {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];

        return $messages[$errorCode] ?? 'Unknown upload error';
    }

    private function cleanupFile(string $filePath): void {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Logging and webhook methods
     */
    private function log(string $message, string $level = 'info'): void {
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            $logMessage = "[" . date('Y-m-d H:i:s') . "] [STORAGE] [{$level}] {$message}" . PHP_EOL;
            $logFile = storage_path('logs' . DIRECTORY_SEPARATOR . 'storage.log');
            
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }

    private function auditLog(string $action, string $resourceType, int $resourceId, ?array $oldValues = null, ?array $newValues = null): void {
        $currentUser = $this->auth->getCurrentUser();
        $this->db->insert('audit_log', [
            'user_id' => $currentUser['id'] ?? null,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function triggerWebhook(string $event, array $data): void {
        if (WEBHOOKS_ENABLED) {
            // Webhook implementation would go here
            // This could be a separate service or background job
        }
    }

    /**
     * Prevent cloning and unserialization
     */
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
    }
}
