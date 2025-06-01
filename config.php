<?php
// Simple File Storage Server Configuration

// Database Configuration (SQLite for simplicity)
if (!defined('DB_PATH')) define('DB_PATH', __DIR__ . '/storage/fileserver.db');

// Storage Configuration
if (!defined('STORAGE_PATH')) define('STORAGE_PATH', __DIR__ . '/storage');
if (!defined('PUBLIC_PATH')) define('PUBLIC_PATH', STORAGE_PATH . '/public');
if (!defined('PRIVATE_PATH')) define('PRIVATE_PATH', STORAGE_PATH . '/private');
if (!defined('TEMP_PATH')) define('TEMP_PATH', STORAGE_PATH . '/temp');

// Upload Configuration
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
if (!defined('ALLOWED_EXTENSIONS')) define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'mp4', 'mp3']);

// Security Configuration
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600); // 1 hour
if (!defined('CSRF_TOKEN_NAME')) define('CSRF_TOKEN_NAME', 'csrf_token');

// Application Configuration
if (!defined('APP_NAME')) define('APP_NAME', 'Simple File Storage');
if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost/FileServer');

// Default Admin User (change these in production!)
if (!defined('ADMIN_USERNAME')) define('ADMIN_USERNAME', 'admin');
if (!defined('ADMIN_PASSWORD')) define('ADMIN_PASSWORD', 'admin123'); // This will be hashed

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto-create directories if they don't exist
$directories = [
    dirname(DB_PATH),
    STORAGE_PATH,
    PUBLIC_PATH,
    PRIVATE_PATH,
    TEMP_PATH
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>
