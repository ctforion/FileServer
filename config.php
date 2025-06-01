<?php
/**
 * Portable PHP File Storage Server - Main Configuration
 * 
 * This file loads configuration from .env file for easier management
 * OS Independent implementation
 */

// Load environment configuration
require_once __DIR__ . '/core/utils/EnvLoader.php';

// try {
//     EnvLoader::load();
//     EnvLoader::createDirectories();
// } catch (Exception $e) {
//     die('Configuration Error: ' . $e->getMessage());
// }

// =============================================================================
// RUNTIME CONFIGURATION
// =============================================================================

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Session configuration
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => BASE_PATH ?: '/',
    'domain' => parse_url(BASE_URL, PHP_URL_HOST),
    'secure' => strpos(BASE_URL, 'https://') === 0,
    'httponly' => true,
    'samesite' => 'Strict'
]);

// File upload configuration
$maxFileSize = is_numeric(MAX_FILE_SIZE) ? MAX_FILE_SIZE : self::sizeToBytes(MAX_FILE_SIZE);
ini_set('upload_max_filesize', $maxFileSize);
ini_set('post_max_size', $maxFileSize);
ini_set('max_execution_time', 300); // 5 minutes for large uploads
ini_set('memory_limit', '256M');

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Get configuration value with default
 */
function config($key, $default = null) 
{
    return defined($key) ? constant($key) : $default;
}

/**
 * Get full URL for a path
 */
function url($path = '') 
{
    $path = ltrim($path, '/');
    return BASE_URL . BASE_PATH . ($path ? '/' . $path : '');
}

/**
 * Get storage path with OS-independent separators
 */
function storage_path($path = '') 
{
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    return STORAGE_PATH . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
}

/**
 * Get cache path
 */
function cache_path($path = '') 
{
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    return CACHE_PATH . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
}

/**
 * Get logs path
 */
function logs_path($path = '') 
{
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    return LOGS_PATH . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
}

/**
 * Convert size string to bytes
 */
function size_to_bytes($size) 
{
    if (is_numeric($size)) {
        return (int)$size;
    }
    
    $size = trim($size);
    $unit = strtoupper(substr($size, -2));
    $value = (int)substr($size, 0, -2);
    
    switch ($unit) {
        case 'KB': return $value * 1024;
        case 'MB': return $value * 1024 * 1024;
        case 'GB': return $value * 1024 * 1024 * 1024;
        case 'TB': return $value * 1024 * 1024 * 1024 * 1024;
        default: return (int)$size;
    }
}

/**
 * Format bytes to human readable size
 */
function format_bytes($size, $precision = 2) 
{
    $base = log($size, 1024);
    $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

/**
 * Generate secure random string
 */
function generate_token($length = 32) 
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length / 2));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    } else {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $token;
    }
}

/**
 * Check if running on Windows
 */
function is_windows() 
{
    return DIRECTORY_SEPARATOR === '\\';
}

/**
 * Get OS-independent path
 */
function normalize_path($path) 
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

/**
 * Ensure directory exists with proper permissions
 */
function ensure_dir($path, $mode = 0755) 
{
    if (!is_dir($path)) {
        if (!mkdir($path, $mode, true)) {
            throw new Exception("Failed to create directory: $path");
        }
    }
    return $path;
}

/**
 * Get file extension safely
 */
function get_file_extension($filename) 
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file extension is allowed
 */
function is_allowed_extension($filename) 
{
    $extension = get_file_extension($filename);
    $allowed = explode(',', strtolower(ALLOWED_EXTENSIONS));
    return in_array($extension, $allowed);
}

/**
 * Sanitize filename for safe storage
 */
function sanitize_filename($filename) 
{
    // Remove path traversal attempts
    $filename = basename($filename);
    
    // Replace unsafe characters
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    
    // Remove multiple underscores
    $filename = preg_replace('/_+/', '_', $filename);
    
    // Trim underscores from start/end
    return trim($filename, '_');
}

/**
 * Get MIME type of file
 */
function get_mime_type($filepath) 
{
    if (function_exists('finfo_file')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filepath);
        finfo_close($finfo);
        return $mime;
    } elseif (function_exists('mime_content_type')) {
        return mime_content_type($filepath);
    } else {
        // Fallback to extension-based detection
        $ext = get_file_extension($filepath);
        $mimes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'pdf' => 'application/pdf', 'txt' => 'text/plain',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'zip' => 'application/zip', 'mp4' => 'video/mp4', 'mp3' => 'audio/mpeg'
        ];
        return isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream';
    }
}

/**
 * Check if file is image
 */
function is_image($filepath) 
{
    $mime = get_mime_type($filepath);
    return strpos($mime, 'image/') === 0;
}

/**
 * Log message to file
 */
function log_message($message, $level = 'INFO', $file = 'app.log') 
{
    if (!ENABLE_LOGGING) return;
    
    $logFile = logs_path($file);
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Ensure log directory exists
    ensure_dir(dirname($logFile));
    
    // Rotate log if too large
    if (file_exists($logFile) && filesize($logFile) > size_to_bytes(LOG_MAX_SIZE)) {
        $rotated = $logFile . '.' . date('Y-m-d-H-i-s');
        rename($logFile, $rotated);
        
        // Clean old log files
        $logDir = dirname($logFile);
        $files = glob($logDir . DIRECTORY_SEPARATOR . basename($file) . '.*');
        if (count($files) > LOG_MAX_FILES) {
            usort($files, function($a, $b) { return filemtime($a) - filemtime($b); });
            foreach (array_slice($files, 0, -LOG_MAX_FILES) as $oldFile) {
                unlink($oldFile);
            }
        }
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// =============================================================================
// AUTOLOADER
// =============================================================================

spl_autoload_register(function ($className) {
    $basePath = __DIR__ . DIRECTORY_SEPARATOR;
    
    // Convert namespace to file path
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    
    $possiblePaths = [
        $basePath . 'core' . DIRECTORY_SEPARATOR . $classPath . '.php',
        $basePath . 'core' . DIRECTORY_SEPARATOR . strtolower($classPath) . '.php',
        $basePath . $classPath . '.php'
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// =============================================================================
// CONSTANTS DERIVED FROM CONFIG
// =============================================================================

define('MAX_FILE_SIZE_BYTES', size_to_bytes(MAX_FILE_SIZE));
define('MAX_TOTAL_SIZE_BYTES', size_to_bytes(MAX_TOTAL_SIZE));
define('ALLOWED_EXTENSIONS_ARRAY', explode(',', strtolower(ALLOWED_EXTENSIONS)));
define('BASE_DIR', __DIR__);
define('CORE_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'core');
define('API_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'api');
define('WEB_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'web');
define('TEMPLATES_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'templates');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log system startup
log_message('System initialized successfully', 'INFO', 'system.log');
    