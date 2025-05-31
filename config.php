<?php
/**
 * Portable PHP File Storage Server - Main Configuration
 * 
 * Single configuration file for the entire application.
 * Edit these settings according to your environment.
 */

// =============================================================================
// BASIC SETTINGS
// =============================================================================

define('APP_NAME', 'FileServer');
define('APP_VERSION', '1.0.0');
define('DEBUG', false); // Set to true for development

// =============================================================================
// SERVER CONFIGURATION
// =============================================================================

// Your domain and path (no trailing slash)
define('BASE_URL', 'https://0xAhmadYousuf.com');
define('BASE_PATH', '/FileServer'); // Set to '' if in document root

// Timezone
define('TIMEZONE', 'UTC');

// =============================================================================
// DATABASE SETTINGS
// =============================================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'fileserver');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// =============================================================================
// FILE STORAGE SETTINGS
// =============================================================================

// Storage paths (relative to project root)
define('STORAGE_PATH', __DIR__ . '/source/storage');
define('PUBLIC_PATH', STORAGE_PATH . '/public');
define('PRIVATE_PATH', STORAGE_PATH . '/private');
define('TEMP_PATH', STORAGE_PATH . '/temp');
define('SHARED_PATH', STORAGE_PATH . '/shared');

// File upload limits
define('MAX_FILE_SIZE', '100MB'); // Max individual file size
define('MAX_TOTAL_SIZE', '1GB');  // Max total storage per user
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,pdf,txt,doc,docx,zip,mp4,mp3');

// Automatic cleanup
define('TEMP_CLEANUP_HOURS', 24);    // Delete temp files after 24 hours
define('SHARED_CLEANUP_DAYS', 7);    // Delete shared files after 7 days

// =============================================================================
// SECURITY SETTINGS
// =============================================================================

// Authentication
define('SESSION_LIFETIME', 3600);    // 1 hour in seconds
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_ATTEMPTS_MAX', 5);
define('LOGIN_LOCKOUT_TIME', 900);   // 15 minutes in seconds

// Admin user (created automatically if doesn't exist)
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // CHANGE THIS IMMEDIATELY!
define('ADMIN_EMAIL', 'admin@localhost');

// Security keys (generate random strings for production)
define('SECRET_KEY', 'change-this-secret-key-in-production');
define('ENCRYPTION_KEY', 'change-this-encryption-key-too');

// File access
define('DOWNLOAD_TOKEN_EXPIRES', 300); // 5 minutes for download tokens
define('SHARE_TOKEN_LENGTH', 32);      // Length of sharing tokens

// =============================================================================
// FEATURE SETTINGS
// =============================================================================

// Thumbnails
define('ENABLE_THUMBNAILS', true);
define('THUMBNAIL_SIZE', 200);
define('THUMBNAIL_QUALITY', 80);

// User registration
define('ALLOW_REGISTRATION', true);
define('REQUIRE_EMAIL_VERIFICATION', false);

// File versioning
define('ENABLE_VERSIONING', true);
define('MAX_VERSIONS', 5);

// Logging
define('ENABLE_LOGGING', true);
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_MAX_SIZE', '10MB');
define('LOG_MAX_FILES', 5);

// =============================================================================
// API SETTINGS
// =============================================================================

define('API_RATE_LIMIT', 100);        // Requests per hour per IP
define('API_KEY_LENGTH', 40);
define('API_TOKEN_EXPIRES', 86400);   // 24 hours

// =============================================================================
// EMAIL SETTINGS (Optional - for notifications)
// =============================================================================

define('ENABLE_EMAIL', false);
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'noreply@localhost');

// =============================================================================
// ADVANCED SETTINGS
// =============================================================================

// Performance
define('ENABLE_CACHING', true);
define('CACHE_DURATION', 3600);

// Backup
define('AUTO_BACKUP', false);
define('BACKUP_INTERVAL', 86400); // Daily backups

// Update system
define('AUTO_UPDATE_CHECK', true);
define('UPDATE_BRANCH', 'main');
define('UPDATE_REPO', 'ctforion/FileServer');

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
session_set_cookie_params(SESSION_LIFETIME);

// File upload configuration
ini_set('upload_max_filesize', MAX_FILE_SIZE);
ini_set('post_max_size', MAX_FILE_SIZE);
ini_set('max_execution_time', 300); // 5 minutes for large uploads

// Memory limit for processing
ini_set('memory_limit', '256M');

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Get configuration value with default
 */
function config($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

/**
 * Get full URL for a path
 */
function url($path = '') {
    $path = ltrim($path, '/');
    return BASE_URL . BASE_PATH . ($path ? '/' . $path : '');
}

/**
 * Get storage path
 */
function storage_path($path = '') {
    return STORAGE_PATH . '/' . ltrim($path, '/');
}

/**
 * Convert size string to bytes
 */
function size_to_bytes($size) {
    $unit = strtoupper(substr($size, -2));
    $value = (int) $size;
    
    switch ($unit) {
        case 'KB': return $value * 1024;
        case 'MB': return $value * 1024 * 1024;
        case 'GB': return $value * 1024 * 1024 * 1024;
        default: return $value;
    }
}

/**
 * Format bytes to human readable size
 */
function format_bytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

// =============================================================================
// AUTOLOAD SETUP
// =============================================================================

/**
 * Simple autoloader for the application
 */
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/source/core/',
        __DIR__ . '/source/api/',
    ];
    
    $file = str_replace('\\', '/', $class) . '.php';
    
    foreach ($paths as $path) {
        $fullPath = $path . $file;
        if (file_exists($fullPath)) {
            require_once $fullPath;
            return;
        }
        
        // Try with different path structures
        $segments = explode('/', $file);
        if (count($segments) > 1) {
            $altPath = $path . strtolower($segments[0]) . '/' . implode('/', array_slice($segments, 1));
            if (file_exists($altPath)) {
                require_once $altPath;
                return;
            }
        }
    }
});

// =============================================================================
// DIRECTORY CREATION
// =============================================================================

// Ensure required directories exist
$required_dirs = [
    STORAGE_PATH,
    PUBLIC_PATH,
    PRIVATE_PATH,
    TEMP_PATH,
    SHARED_PATH,
    __DIR__ . '/logs',
    __DIR__ . '/source/storage/thumbnails'
];

foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Create .htaccess for protected directories
$protected_dirs = [PRIVATE_PATH, TEMP_PATH, __DIR__ . '/logs'];
foreach ($protected_dirs as $dir) {
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Order deny,allow\nDeny from all");
    }
}

// =============================================================================
// CONSTANTS DERIVED FROM CONFIG
// =============================================================================

define('MAX_FILE_SIZE_BYTES', size_to_bytes(MAX_FILE_SIZE));
define('MAX_TOTAL_SIZE_BYTES', size_to_bytes(MAX_TOTAL_SIZE));
define('ALLOWED_EXTENSIONS_ARRAY', explode(',', strtolower(ALLOWED_EXTENSIONS)));

?>
