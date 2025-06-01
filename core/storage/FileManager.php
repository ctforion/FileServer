<?php

class FileManager {
    private $storagePath;
    private $maxFileSize;
    private $allowedExtensions;
    
    public function __construct($storagePath, $maxFileSize = 10485760, $allowedExtensions = []) {
        $this->storagePath = rtrim($storagePath, '/');
        $this->maxFileSize = $maxFileSize;
        $this->allowedExtensions = $allowedExtensions ?: [
            'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 
            'txt', 'zip', 'rar', 'mp4', 'mp3', 'xlsx', 'pptx'
        ];
        
        $this->initDirectories();
    }
    
    private function initDirectories() {
        $dirs = ['public', 'private', 'temp'];
        foreach ($dirs as $dir) {
            $fullPath = $this->storagePath . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
        }
    }
    
    public function upload($file, $directory = 'public') {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No valid file uploaded'];
        }
        
        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            return ['success' => false, 'message' => 'File too large'];
        }
        
        // Validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return ['success' => false, 'message' => 'File type not allowed'];
        }
        
        // Sanitize filename
        $filename = $this->sanitizeFilename($file['name']);
          // Ensure target directory exists
        $targetDir = $this->storagePath . '/' . $directory;
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                return ['success' => false, 'message' => 'Cannot create storage directory: ' . $targetDir];
            }
        }
        
        // Check if directory is writable
        if (!is_writable($targetDir)) {
            return ['success' => false, 'message' => 'Storage directory not writable: ' . $targetDir . '. Please check permissions.'];
        }
        
        // Create unique filename if exists
        $targetPath = $targetDir . '/' . $filename;
        $counter = 1;
        while (file_exists($targetPath)) {
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $filename = $name . '_' . $counter . '.' . $ext;
            $targetPath = $targetDir . '/' . $filename;
            $counter++;
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'filename' => $filename,
                'size' => $file['size'],
                'path' => $directory . '/' . $filename
            ];
        }
        
        // Enhanced error message
        $error = error_get_last();
        $errorMsg = 'Failed to save file to: ' . $targetPath;
        if ($error && strpos($error['message'], 'move_uploaded_file') !== false) {
            $errorMsg .= '. Error: ' . $error['message'];
        }
        
        return ['success' => false, 'message' => $errorMsg];
    }
    
    public function download($filepath) {
        $fullPath = $this->storagePath . '/' . ltrim($filepath, '/');
        
        // Security check - prevent path traversal
        if (!$this->isPathSafe($fullPath)) {
            return false;
        }
        
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return false;
        }
        
        // Set headers for download
        $filename = basename($fullPath);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($fullPath));
        
        // Output file
        readfile($fullPath);
        return true;
    }
    
    public function delete($filepath) {
        $fullPath = $this->storagePath . '/' . ltrim($filepath, '/');
        
        if (!$this->isPathSafe($fullPath)) {
            return ['success' => false, 'message' => 'Invalid file path'];
        }
        
        if (!file_exists($fullPath)) {
            return ['success' => false, 'message' => 'File not found'];
        }
        
        if (unlink($fullPath)) {
            return ['success' => true, 'message' => 'File deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete file'];
    }
    
    public function listFiles($directory = 'public', $page = 1, $limit = 20) {
        $fullPath = $this->storagePath . '/' . $directory;
        
        if (!is_dir($fullPath)) {
            return ['success' => false, 'message' => 'Directory not found'];
        }
        
        $files = array_diff(scandir($fullPath), ['.', '..', '.htaccess']);
        $totalFiles = count($files);
        
        // Apply pagination
        $offset = ($page - 1) * $limit;
        $files = array_slice($files, $offset, $limit);
        
        $fileList = [];
        foreach ($files as $file) {
            $filePath = $fullPath . '/' . $file;
            if (is_file($filePath)) {
                $fileList[] = [
                    'name' => $file,
                    'size' => filesize($filePath),
                    'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                    'path' => $directory . '/' . $file
                ];
            }
        }
        
        return [
            'success' => true,
            'files' => $fileList,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalFiles,
                'pages' => ceil($totalFiles / $limit)
            ]
        ];
    }
    
    public function getFileInfo($filepath) {
        $fullPath = $this->storagePath . '/' . ltrim($filepath, '/');
        
        if (!$this->isPathSafe($fullPath) || !file_exists($fullPath)) {
            return null;
        }
        
        return [
            'name' => basename($fullPath),
            'size' => filesize($fullPath),
            'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
            'type' => mime_content_type($fullPath),
            'path' => $filepath
        ];
    }
    
    private function sanitizeFilename($filename) {
        // Remove any path information
        $filename = basename($filename);
        
        // Replace spaces and special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);
        
        return $filename;
    }
    
    private function isPathSafe($path) {
        $realPath = realpath(dirname($path));
        $realStoragePath = realpath($this->storagePath);
        
        return $realPath && $realStoragePath && strpos($realPath, $realStoragePath) === 0;
    }
    
    public function cleanupTemp($hours = 24) {
        $tempPath = $this->storagePath . '/temp';
        $cutoff = time() - ($hours * 3600);
        
        if (!is_dir($tempPath)) {
            return;
        }
        
        $files = array_diff(scandir($tempPath), ['.', '..', '.htaccess']);
        foreach ($files as $file) {
            $filePath = $tempPath . '/' . $file;
            if (is_file($filePath) && filemtime($filePath) < $cutoff) {
                unlink($filePath);
            }
        }
    }
}
