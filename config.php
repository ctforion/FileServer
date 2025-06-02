<?php
/**
 * Enhanced File Storage Server Configuration
 * Updated for JSON database and new systems
 */

// Return configuration as array for new system
return [
    // Database Configuration (JSON-based)
    'database_path' => __DIR__ . '/data',
    
    // Storage Configuration
    'storage_path' => __DIR__ . '/storage',
    'public_path' => __DIR__ . '/storage/public',
    'private_path' => __DIR__ . '/storage/private',
    'temp_path' => __DIR__ . '/storage/temp',
    'admin_path' => __DIR__ . '/storage/admin',
    
    // Upload Configuration
    'max_file_size' => 50 * 1024 * 1024, // 50MB
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'mp4', 'mp3', 'xlsx', 'pptx'],
      // Security Configuration
    'session_timeout' => 86400, // 24 hours
    'csrf_protection' => false,
    'rate_limiting' => [
        'enabled' => true,
        'upload_limit' => 10, // per minute
        'download_limit' => 50, // per minute
        'api_limit' => 100 // per minute
    ],
    
    // Password Requirements
    'password_requirements' => [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => false
    ],
    
    // Application Configuration
    'app_name' => 'PHP FileServer Enhanced',
    'app_version' => '2.0.0',
    'base_url' => 'http://localhost/FileServer',
    'timezone' => 'UTC',
      // Logging Configuration
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'log_path' => __DIR__ . '/data/logs',
        'max_file_size' => 10485760, // 10MB
        'retention_days' => 30
    ],
    
    // Maintenance Mode
    'maintenance_mode' => false,
    'maintenance_message' => 'System is under maintenance. Please try again later.',
    
    // User Registration
    'registration_enabled' => true,
    'default_user_quota' => 104857600, // 100MB
    'email_verification' => false,
    
    // File Management
    'enable_versioning' => true,
    'enable_file_sharing' => true,
    'enable_public_uploads' => false,
    
    // Admin Configuration
    'admin_username' => 'admin',
    'admin_email' => 'admin@fileserver.local'
];

// Legacy constants for backward compatibility
if (!defined('STORAGE_PATH')) define('STORAGE_PATH', __DIR__ . '/storage');
if (!defined('PUBLIC_PATH')) define('PUBLIC_PATH', __DIR__ . '/storage/public');
if (!defined('PRIVATE_PATH')) define('PRIVATE_PATH', __DIR__ . '/storage/private');
if (!defined('TEMP_PATH')) define('TEMP_PATH', __DIR__ . '/storage/temp');
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 50 * 1024 * 1024);
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 86400);
if (!defined('APP_NAME')) define('APP_NAME', 'PHP FileServer Enhanced');

// Error Reporting (for development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto-create directories if they don't exist
$config = include __FILE__;
$directories = [
    $config['database_path'],
    $config['storage_path'],
    $config['public_path'],
    $config['private_path'],
    $config['temp_path'],
    $config['admin_path'],
    $config['database_path'] . '/logs'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>
