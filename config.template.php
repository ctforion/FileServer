<?php
/**
 * Configuration Template for FileServer
 * Copy this file to config.local.php and customize for your environment
 */

// Database Configuration (if using database instead of JSON)
$db_config = [
    'host' => 'localhost',
    'username' => 'fileserver_user',
    'password' => 'your_secure_password',
    'database' => 'fileserver_db',
    'charset' => 'utf8mb4'
];

// Application Configuration
$app_config = [
    // Basic Settings
    'app_name' => 'FileServer',
    'app_version' => '1.0.0',
    'app_url' => 'https://your-domain.com',
    'timezone' => 'UTC',
    'debug' => false,
    
    // Session Configuration
    'session_name' => 'fileserver_session',
    'session_lifetime' => 3600, // 1 hour in seconds
    'session_secure' => true, // Set to true for HTTPS
    'session_httponly' => true,
    'session_samesite' => 'Strict',
    
    // File Upload Configuration
    'max_file_size' => 50 * 1024 * 1024, // 50MB
    'max_chunk_size' => 1 * 1024 * 1024, // 1MB for chunked upload
    'allowed_extensions' => [
        // Documents
        'txt', 'rtf', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'odp', 'csv',
        // Images
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico',
        // Archives
        'zip', 'rar', '7z', 'tar', 'gz', 'bz2',
        // Audio
        'mp3', 'wav', 'ogg', 'flac', 'm4a',
        // Video
        'mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm',
        // Code
        'html', 'css', 'js', 'php', 'py', 'java', 'cpp', 'c', 'json', 'xml'
    ],
    'quarantine_extensions' => [
        'exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'vbs', 'js', 'jar',
        'msi', 'deb', 'rpm', 'dmg', 'app'
    ],
    
    // Storage Paths
    'base_path' => __DIR__,
    'uploads_path' => 'storage/uploads/',
    'quarantine_path' => 'storage/quarantine/',
    'thumbnails_path' => 'storage/thumbnails/',
    'compressed_path' => 'storage/compressed/',
    'versions_path' => 'storage/versions/',
    'backups_path' => 'data/backups/',
    'logs_path' => 'logs/',
    'data_path' => 'data/',
    
    // Security Configuration
    'enable_csrf' => true,
    'csrf_token_name' => '_token',
    'max_login_attempts' => 5,
    'lockout_duration' => 900, // 15 minutes
    'password_min_length' => 8,
    'require_strong_passwords' => true,
    'enable_two_factor' => false,
    'enable_captcha' => false,
    'captcha_site_key' => '',
    'captcha_secret_key' => '',
    
    // Features Configuration
    'allow_registration' => true,
    'require_email_verification' => false,
    'enable_file_sharing' => true,
    'enable_file_versioning' => true,
    'enable_compression' => true,
    'enable_thumbnails' => true,
    'enable_virus_scan' => false,
    'virus_scan_command' => 'clamscan',
    
    // File Sharing Configuration
    'share_link_length' => 32,
    'default_share_expiry' => 7 * 24 * 3600, // 7 days
    'max_share_expiry' => 30 * 24 * 3600, // 30 days
    'require_password_for_shares' => false,
    
    // Backup Configuration
    'enable_auto_backup' => false,
    'backup_schedule' => 'daily', // daily, weekly, monthly
    'backup_retention_days' => 30,
    'backup_compression' => true,
    'backup_encryption' => false,
    'backup_encryption_key' => '',
    
    // Email Configuration
    'smtp_enabled' => false,
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls', // tls or ssl
    'from_email' => 'noreply@your-domain.com',
    'from_name' => 'FileServer',
    
    // Logging Configuration
    'log_level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
    'log_max_size' => 10 * 1024 * 1024, // 10MB
    'log_rotation' => true,
    'log_retention_days' => 30,
    
    // Performance Configuration
    'enable_caching' => true,
    'cache_lifetime' => 3600, // 1 hour
    'enable_compression' => true,
    'thumbnail_quality' => 85,
    'thumbnail_max_width' => 300,
    'thumbnail_max_height' => 300,
    
    // UI Configuration
    'default_theme' => 'auto', // light, dark, auto
    'enable_theme_switching' => true,
    'default_language' => 'en',
    'enable_rtl' => false,
    'items_per_page' => 50,
    'enable_keyboard_shortcuts' => true,
    
    // API Configuration
    'enable_api' => true,
    'api_rate_limit' => 1000, // requests per hour
    'api_token_expiry' => 24 * 3600, // 24 hours
    
    // External Services
    'google_analytics_id' => '',
    'enable_google_drive_sync' => false,
    'google_drive_client_id' => '',
    'google_drive_client_secret' => '',
    'enable_dropbox_sync' => false,
    'dropbox_app_key' => '',
    'dropbox_app_secret' => '',
    
    // Maintenance Configuration
    'maintenance_mode' => false,
    'maintenance_message' => 'System maintenance in progress. Please try again later.',
    'maintenance_allowed_ips' => [
        '127.0.0.1',
        '::1'
    ],
    
    // Advanced Configuration
    'enable_file_encryption' => false,
    'encryption_key' => '', // Generate with: bin2hex(random_bytes(32))
    'enable_file_deduplication' => false,
    'enable_audit_log' => true,
    'enable_user_activity_tracking' => true,
    'enable_file_preview' => true,
    'max_preview_size' => 5 * 1024 * 1024, // 5MB
    
    // Custom Configuration
    'custom_css' => '',
    'custom_js' => '',
    'custom_footer_text' => '',
    'enable_custom_branding' => false,
    'custom_logo_url' => '',
    'custom_favicon_url' => ''
];

// Environment-specific overrides
if (file_exists(__DIR__ . '/config.local.php')) {
    $local_config = include __DIR__ . '/config.local.php';
    $app_config = array_merge($app_config, $local_config);
}

// Set PHP configuration based on app config
ini_set('upload_max_filesize', $app_config['max_file_size']);
ini_set('post_max_size', $app_config['max_file_size'] * 1.1);
ini_set('max_execution_time', 300); // 5 minutes
ini_set('memory_limit', '256M');

// Set timezone
date_default_timezone_set($app_config['timezone']);

// Session configuration
ini_set('session.name', $app_config['session_name']);
ini_set('session.gc_maxlifetime', $app_config['session_lifetime']);
ini_set('session.cookie_lifetime', $app_config['session_lifetime']);
ini_set('session.cookie_secure', $app_config['session_secure']);
ini_set('session.cookie_httponly', $app_config['session_httponly']);
ini_set('session.cookie_samesite', $app_config['session_samesite']);

// Error reporting
if ($app_config['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Set error log file
ini_set('error_log', $app_config['logs_path'] . 'php_errors.log');

// Define constants
define('APP_NAME', $app_config['app_name']);
define('APP_VERSION', $app_config['app_version']);
define('APP_URL', $app_config['app_url']);
define('BASE_PATH', $app_config['base_path']);
define('UPLOADS_PATH', $app_config['uploads_path']);
define('DATA_PATH', $app_config['data_path']);
define('LOGS_PATH', $app_config['logs_path']);
define('DEBUG_MODE', $app_config['debug']);

// Make config globally available
$GLOBALS['config'] = $app_config;

/**
 * Get configuration value
 */
function get_config($key, $default = null) {
    return $GLOBALS['config'][$key] ?? $default;
}

/**
 * Set configuration value
 */
function set_config($key, $value) {
    $GLOBALS['config'][$key] = $value;
}

/**
 * Check if feature is enabled
 */
function is_feature_enabled($feature) {
    return get_config($feature, false) === true;
}

/**
 * Get file size limit in bytes
 */
function get_max_file_size() {
    return get_config('max_file_size', 50 * 1024 * 1024);
}

/**
 * Get allowed file extensions
 */
function get_allowed_extensions() {
    return get_config('allowed_extensions', []);
}

/**
 * Check if file extension is allowed
 */
function is_extension_allowed($extension) {
    $allowed = get_allowed_extensions();
    return in_array(strtolower($extension), array_map('strtolower', $allowed));
}

/**
 * Get quarantine extensions
 */
function get_quarantine_extensions() {
    return get_config('quarantine_extensions', []);
}

/**
 * Check if file should be quarantined
 */
function should_quarantine_file($extension) {
    $quarantine = get_quarantine_extensions();
    return in_array(strtolower($extension), array_map('strtolower', $quarantine));
}

// Load environment-specific configuration
if (file_exists(__DIR__ . '/.env')) {
    $env_file = file_get_contents(__DIR__ . '/.env');
    $env_lines = explode("\n", $env_file);
    
    foreach ($env_lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, '"\'');
        
        if (!empty($key)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

return $app_config;
?>
