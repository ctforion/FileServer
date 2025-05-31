<?php
/**
 * File Manager Class
 * Handles file operations, uploads, downloads, and file system management
 */

class FileManager {
    private $db;
    private $config;
    private $uploadPath;
    private $thumbnailPath;
    
    public function __construct($db, $config) {
        $this->db = $db;
        $this->config = $config;
        $this->uploadPath = rtrim($config['UPLOAD_PATH'], '/') . '/';
        $this->thumbnailPath = rtrim($config['THUMBNAIL_PATH'], '/') . '/';
        
        $this->ensureDirectories();
    }
    
    private function ensureDirectories() {
        $directories = [
            $this->uploadPath,
            $this->thumbnailPath,
            dirname($this->config['LOG_FILE'])
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Upload a file
     */
    public function uploadFile($fileData, $userId, $folder = '/') {
        try {
            // Validate file
            $validation = $this->validateFile($fileData);
            if (!$validation['valid']) {
                throw new Exception($validation['error']);
            }
            
            // Generate unique filename
            $originalName = $fileData['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $filename = $this->generateUniqueFilename($originalName);
            $filePath = $this->uploadPath . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($fileData['tmp_name'], $filePath)) {
                throw new Exception('Failed to move uploaded file');
            }
            
            // Get file info
            $fileSize = filesize($filePath);
            $mimeType = $this->getMimeType($filePath);
            $hash = $this->calculateFileHash($filePath);
            
            // Generate thumbnail if image
            $thumbnailPath = null;
            if ($this->isImageFile($extension)) {
                $thumbnailPath = $this->generateThumbnail($filePath, $filename);
            }
            
            // Save to database
            $fileId = $this->saveFileRecord([
                'user_id' => $userId,
                'original_name' => $originalName,
                'filename' => $filename,
                'extension' => $extension,
                'mime_type' => $mimeType,
                'size' => $fileSize,
                'hash' => $hash,
                'folder' => $folder,
                'thumbnail' => $thumbnailPath,
                'uploaded_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log the upload
            $this->logActivity($userId, 'file_upload', [
                'file_id' => $fileId,
                'filename' => $originalName,
                'size' => $fileSize
            ]);
            
            return [
                'success' => true,
                'file_id' => $fileId,
                'filename' => $filename,
                'original_name' => $originalName,
                'size' => $fileSize,
                'thumbnail' => $thumbnailPath
            ];
            
        } catch (Exception $e) {
            // Clean up failed upload
            if (isset($filePath) && file_exists($filePath)) {
                unlink($filePath);
            }
            
            throw $e;
        }
    }
    
    /**
     * Chunked file upload initialization
     */
    public function initChunkedUpload($filename, $fileSize, $totalChunks, $userId) {
        $uploadId = $this->generateUploadId();
        $tempPath = $this->uploadPath . 'temp/' . $uploadId . '/';
        
        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }
        
        // Save upload session
        $stmt = $this->db->prepare("
            INSERT INTO upload_sessions 
            (upload_id, user_id, filename, total_size, total_chunks, temp_path, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $uploadId,
            $userId,
            $filename,
            $fileSize,
            $totalChunks,
            $tempPath,
            date('Y-m-d H:i:s')
        ]);
        
        return $uploadId;
    }
    
    /**
     * Handle chunk upload
     */
    public function uploadChunk($uploadId, $chunkIndex, $chunkData) {
        // Get upload session
        $stmt = $this->db->prepare("SELECT * FROM upload_sessions WHERE upload_id = ?");
        $stmt->execute([$uploadId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            throw new Exception('Invalid upload session');
        }
        
        $chunkPath = $session['temp_path'] . 'chunk_' . $chunkIndex;
        
        if (!move_uploaded_file($chunkData['tmp_name'], $chunkPath)) {
            throw new Exception('Failed to save chunk');
        }
        
        // Update chunk status
        $stmt = $this->db->prepare("
            INSERT INTO upload_chunks (upload_id, chunk_index, chunk_size, uploaded_at)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $uploadId,
            $chunkIndex,
            filesize($chunkPath),
            date('Y-m-d H:i:s')
        ]);
        
        return true;
    }
    
    /**
     * Finalize chunked upload
     */
    public function finalizeChunkedUpload($uploadId) {
        // Get upload session
        $stmt = $this->db->prepare("SELECT * FROM upload_sessions WHERE upload_id = ?");
        $stmt->execute([$uploadId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            throw new Exception('Invalid upload session');
        }
        
        // Verify all chunks are uploaded
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as chunk_count 
            FROM upload_chunks 
            WHERE upload_id = ?
        ");
        $stmt->execute([$uploadId]);
        $chunkCount = $stmt->fetch(PDO::FETCH_ASSOC)['chunk_count'];
        
        if ($chunkCount != $session['total_chunks']) {
            throw new Exception('Missing chunks');
        }
        
        // Combine chunks
        $finalFilename = $this->generateUniqueFilename($session['filename']);
        $finalPath = $this->uploadPath . $finalFilename;
        $finalFile = fopen($finalPath, 'wb');
        
        for ($i = 0; $i < $session['total_chunks']; $i++) {
            $chunkPath = $session['temp_path'] . 'chunk_' . $i;
            if (!file_exists($chunkPath)) {
                throw new Exception('Missing chunk: ' . $i);
            }
            
            $chunk = fopen($chunkPath, 'rb');
            stream_copy_to_stream($chunk, $finalFile);
            fclose($chunk);
            unlink($chunkPath);
        }
        
        fclose($finalFile);
        
        // Clean up temp directory
        rmdir($session['temp_path']);
        
        // Get file info and save record
        $extension = strtolower(pathinfo($session['filename'], PATHINFO_EXTENSION));
        $mimeType = $this->getMimeType($finalPath);
        $hash = $this->calculateFileHash($finalPath);
        
        // Generate thumbnail if image
        $thumbnailPath = null;
        if ($this->isImageFile($extension)) {
            $thumbnailPath = $this->generateThumbnail($finalPath, $finalFilename);
        }
        
        // Save file record
        $fileId = $this->saveFileRecord([
            'user_id' => $session['user_id'],
            'original_name' => $session['filename'],
            'filename' => $finalFilename,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $session['total_size'],
            'hash' => $hash,
            'folder' => '/',
            'thumbnail' => $thumbnailPath,
            'uploaded_at' => date('Y-m-d H:i:s')
        ]);
        
        // Clean up upload session
        $this->db->prepare("DELETE FROM upload_sessions WHERE upload_id = ?")->execute([$uploadId]);
        $this->db->prepare("DELETE FROM upload_chunks WHERE upload_id = ?")->execute([$uploadId]);
        
        return $fileId;
    }
    
    /**
     * Get file list for user
     */
    public function getFileList($userId, $folder = '/', $search = null, $limit = 50, $offset = 0) {
        $sql = "
            SELECT f.*, u.username as owner
            FROM files f
            JOIN users u ON f.user_id = u.id
            WHERE f.deleted_at IS NULL
        ";
        
        $params = [];
        
        // User permission check (admin sees all, users see their own)
        if (!$this->isAdmin($userId)) {
            $sql .= " AND f.user_id = ?";
            $params[] = $userId;
        }
        
        // Folder filter
        if ($folder !== null) {
            $sql .= " AND f.folder = ?";
            $params[] = $folder;
        }
        
        // Search filter
        if ($search) {
            $sql .= " AND (f.original_name LIKE ? OR f.extension LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        
        $sql .= " ORDER BY f.uploaded_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get file by ID
     */
    public function getFile($fileId, $userId = null) {
        $sql = "
            SELECT f.*, u.username as owner
            FROM files f
            JOIN users u ON f.user_id = u.id
            WHERE f.id = ? AND f.deleted_at IS NULL
        ";
        
        $params = [$fileId];
        
        // Permission check
        if ($userId && !$this->isAdmin($userId)) {
            $sql .= " AND f.user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Download file
     */
    public function downloadFile($fileId, $userId = null) {
        $file = $this->getFile($fileId, $userId);
        
        if (!$file) {
            throw new Exception('File not found');
        }
        
        $filePath = $this->uploadPath . $file['filename'];
        
        if (!file_exists($filePath)) {
            throw new Exception('File does not exist on disk');
        }
        
        // Log download
        $this->logActivity($userId, 'file_download', [
            'file_id' => $fileId,
            'filename' => $file['original_name']
        ]);
        
        // Update download count
        $stmt = $this->db->prepare("UPDATE files SET download_count = download_count + 1 WHERE id = ?");
        $stmt->execute([$fileId]);
        
        return [
            'path' => $filePath,
            'name' => $file['original_name'],
            'size' => $file['size'],
            'mime_type' => $file['mime_type']
        ];
    }
    
    /**
     * Delete file
     */
    public function deleteFile($fileId, $userId) {
        $file = $this->getFile($fileId, $userId);
        
        if (!$file) {
            throw new Exception('File not found');
        }
        
        // Soft delete
        $stmt = $this->db->prepare("UPDATE files SET deleted_at = ? WHERE id = ?");
        $stmt->execute([date('Y-m-d H:i:s'), $fileId]);
        
        // Log deletion
        $this->logActivity($userId, 'file_delete', [
            'file_id' => $fileId,
            'filename' => $file['original_name']
        ]);
        
        return true;
    }
    
    /**
     * Permanently delete file
     */
    public function permanentDeleteFile($fileId) {
        $file = $this->getFile($fileId);
        
        if (!$file) {
            throw new Exception('File not found');
        }
        
        // Delete physical files
        $filePath = $this->uploadPath . $file['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        if ($file['thumbnail']) {
            $thumbnailPath = $this->thumbnailPath . $file['thumbnail'];
            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
        }
        
        // Delete from database
        $stmt = $this->db->prepare("DELETE FROM files WHERE id = ?");
        $stmt->execute([$fileId]);
        
        return true;
    }
    
    /**
     * Generate thumbnail for image
     */
    private function generateThumbnail($filePath, $filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return null;
        }
        
        $thumbnailName = 'thumb_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
        $thumbnailPath = $this->thumbnailPath . $thumbnailName;
        
        try {
            $image = null;
            
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($filePath);
                    break;
                case 'png':
                    $image = imagecreatefrompng($filePath);
                    break;
                case 'gif':
                    $image = imagecreatefromgif($filePath);
                    break;
                case 'webp':
                    $image = imagecreatefromwebp($filePath);
                    break;
            }
            
            if (!$image) {
                return null;
            }
            
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);
            
            // Calculate thumbnail dimensions
            $thumbWidth = 200;
            $thumbHeight = 200;
            
            if ($originalWidth > $originalHeight) {
                $thumbHeight = ($originalHeight / $originalWidth) * $thumbWidth;
            } else {
                $thumbWidth = ($originalWidth / $originalHeight) * $thumbHeight;
            }
            
            // Create thumbnail
            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $originalWidth, $originalHeight);
            
            // Save thumbnail
            imagejpeg($thumbnail, $thumbnailPath, 85);
            
            // Cleanup
            imagedestroy($image);
            imagedestroy($thumbnail);
            
            return $thumbnailName;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($fileData) {
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload error: ' . $fileData['error']];
        }
        
        if ($fileData['size'] > $this->config['MAX_FILE_SIZE']) {
            return ['valid' => false, 'error' => 'File too large'];
        }
        
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->config['ALLOWED_EXTENSIONS'])) {
            return ['valid' => false, 'error' => 'File type not allowed'];
        }
        
        // Additional MIME type check
        $mimeType = $this->getMimeType($fileData['tmp_name']);
        if (!$this->isAllowedMimeType($mimeType)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Generate unique filename
     */
    private function generateUniqueFilename($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        
        $filename = $basename . '_' . uniqid() . '.' . $extension;
        
        // Ensure uniqueness
        while (file_exists($this->uploadPath . $filename)) {
            $filename = $basename . '_' . uniqid() . '.' . $extension;
        }
        
        return $filename;
    }
    
    /**
     * Calculate file hash
     */
    private function calculateFileHash($filePath) {
        return hash_file('sha256', $filePath);
    }
    
    /**
     * Get MIME type
     */
    private function getMimeType($filePath) {
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            return finfo_file($finfo, $filePath);
        } elseif (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        }
        
        return 'application/octet-stream';
    }
    
    /**
     * Check if MIME type is allowed
     */
    private function isAllowedMimeType($mimeType) {
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'application/pdf',
            'text/plain', 'text/html', 'text/css', 'text/javascript',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip', 'application/x-rar', 'application/x-tar', 'application/gzip',
            'video/mp4', 'video/avi', 'video/quicktime',
            'audio/mpeg', 'audio/wav', 'audio/ogg'
        ];
        
        return in_array($mimeType, $allowedMimes);
    }
    
    /**
     * Check if file is an image
     */
    private function isImageFile($extension) {
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
    }
    
    /**
     * Save file record to database
     */
    private function saveFileRecord($data) {
        $stmt = $this->db->prepare("
            INSERT INTO files 
            (user_id, original_name, filename, extension, mime_type, size, hash, folder, thumbnail, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['user_id'],
            $data['original_name'],
            $data['filename'],
            $data['extension'],
            $data['mime_type'],
            $data['size'],
            $data['hash'],
            $data['folder'],
            $data['thumbnail'],
            $data['uploaded_at']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Generate upload ID
     */
    private function generateUploadId() {
        return uniqid('upload_', true);
    }
    
    /**
     * Log activity
     */
    private function logActivity($userId, $action, $data = []) {
        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (user_id, action, data, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            json_encode($data),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Check if user is admin
     */
    private function isAdmin($userId) {
        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user && $user['role'] === 'admin';
    }
    
    /**
     * Get storage statistics
     */
    public function getStorageStats($userId = null) {
        $stats = [];
        
        // Total files
        $sql = "SELECT COUNT(*) as total_files, SUM(size) as total_size FROM files WHERE deleted_at IS NULL";
        $params = [];
        
        if ($userId && !$this->isAdmin($userId)) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stats['total_files'] = $result['total_files'];
        $stats['total_size'] = $result['total_size'] ?: 0;
        
        // File types breakdown
        $sql = "
            SELECT extension, COUNT(*) as count, SUM(size) as size
            FROM files 
            WHERE deleted_at IS NULL
        ";
        
        if ($userId && !$this->isAdmin($userId)) {
            $sql .= " AND user_id = ?";
        }
        
        $sql .= " GROUP BY extension ORDER BY count DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
    
    /**
     * Cleanup orphaned files
     */
    public function cleanupOrphanedFiles() {
        $cleaned = 0;
        
        // Get all files in upload directory
        $files = glob($this->uploadPath . '*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $filename = basename($file);
                
                // Check if file exists in database
                $stmt = $this->db->prepare("SELECT id FROM files WHERE filename = ?");
                $stmt->execute([$filename]);
                
                if (!$stmt->fetch()) {
                    unlink($file);
                    $cleaned++;
                }
            }
        }
        
        // Cleanup thumbnails
        $thumbnails = glob($this->thumbnailPath . '*');
        
        foreach ($thumbnails as $thumbnail) {
            if (is_file($thumbnail)) {
                $thumbnailName = basename($thumbnail);
                
                $stmt = $this->db->prepare("SELECT id FROM files WHERE thumbnail = ?");
                $stmt->execute([$thumbnailName]);
                
                if (!$stmt->fetch()) {
                    unlink($thumbnail);
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
}
