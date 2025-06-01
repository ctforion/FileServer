<?php

class Validator {
    public static function sanitizeString($input, $maxLength = 255) {
        $input = trim($input);
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        if ($maxLength > 0 && strlen($input) > $maxLength) {
            $input = substr($input, 0, $maxLength);
        }
        
        return $input;
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validateFilename($filename) {
        // Check for invalid characters
        if (preg_match('/[<>:"|?*]/', $filename)) {
            return false;
        }
        
        // Check for reserved names (Windows)
        $reserved = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
        $name = strtoupper(pathinfo($filename, PATHINFO_FILENAME));
        if (in_array($name, $reserved)) {
            return false;
        }
        
        // Check length
        if (strlen($filename) > 255) {
            return false;
        }
        
        return true;
    }
    
    public static function validateFileExtension($filename, $allowedExtensions) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $allowedExtensions);
    }
    
    public static function validateFileSize($size, $maxSize) {
        return $size <= $maxSize && $size > 0;
    }
    
    public static function validatePath($path) {
        // Prevent path traversal
        $normalizedPath = str_replace(['../', '..\\', '../', '..\\'], '', $path);
        
        // Check for null bytes
        if (strpos($normalizedPath, "\0") !== false) {
            return false;
        }
        
        return $normalizedPath === $path;
    }
    
    public static function validateInteger($value, $min = null, $max = null) {
        if (!is_numeric($value)) {
            return false;
        }
        
        $value = (int)$value;
        
        if ($min !== null && $value < $min) {
            return false;
        }
        
        if ($max !== null && $value > $max) {
            return false;
        }
        
        return true;
    }
    
    public static function validateMimeType($file, $allowedTypes = []) {
        if (empty($allowedTypes)) {
            return true;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file);
        finfo_close($finfo);
        
        return in_array($mimeType, $allowedTypes);
    }
    
    public static function sanitizeFilename($filename) {
        // Remove path information
        $filename = basename($filename);
        
        // Replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);
        
        // Trim underscores from start and end
        $filename = trim($filename, '_');
        
        // Ensure we have a filename
        if (empty($filename)) {
            $filename = 'file_' . time();
        }
        
        return $filename;
    }
    
    public static function validateUploadedFile($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'message' => 'No valid file uploaded'];
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'Upload error: ' . $file['error']];
        }
        
        if ($file['size'] === 0) {
            return ['valid' => false, 'message' => 'Empty file uploaded'];
        }
        
        return ['valid' => true, 'message' => 'File is valid'];
    }
    
    public static function isValidJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    public static function validateRequired($fields, $data) {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        return empty($missing) ? true : $missing;
    }
}
