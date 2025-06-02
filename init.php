<?php
/**
 * Database/Data Initialization Script
 * Creates necessary data files and directories for FileServer
 */

// Include required functions
require_once 'includes/config.php';
require_once 'includes/json-functions.php';
require_once 'includes/functions.php';

/**
 * Initialize all required data files and directories
 */
function initialize_fileserver() {
    $success = true;
    $messages = [];
    
    // Create required directories
    $directories = [
        'data',
        'data/backups',
        'data/locks',
        'logs',
        'storage',
        'storage/uploads',
        'storage/compressed',
        'storage/quarantine',
        'storage/thumbnails',
        'storage/versions'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true)) {
                $messages[] = "Created directory: $dir";
            } else {
                $messages[] = "Failed to create directory: $dir";
                $success = false;
            }
        }
    }
    
    // Create .htaccess files for security
    create_security_files();
    
    // Initialize data files
    initialize_data_files();
    
    // Create default admin user if no users exist
    create_default_admin();
    
    return [
        'success' => $success,
        'messages' => $messages
    ];
}

/**
 * Create security .htaccess files
 */
function create_security_files() {
    // Main .htaccess
    $htaccess_content = "# FileServer Security Configuration
# Deny direct access to sensitive files
<Files ~ \"\\.(json|log|php|ini)$\">
    Order Allow,Deny
    Deny from all
</Files>

# Allow specific PHP files
<Files \"index.php\">
    Allow from all
</Files>

<Files \"login.php\">
    Allow from all
</Files>

<Files \"register.php\">
    Allow from all
</Files>

<Files \"logout.php\">
    Allow from all
</Files>

<Files \"dashboard.php\">
    Allow from all
</Files>

<Files \"file-browser.php\">
    Allow from all
</Files>

<Files \"upload.php\">
    Allow from all
</Files>

<Files \"search.php\">
    Allow from all
</Files>

<Files \"settings.php\">
    Allow from all
</Files>

<Files \"admin.php\">
    Allow from all
</Files>

<Files \"user-management.php\">
    Allow from all
</Files>

<Files \"system-monitor.php\">
    Allow from all
</Files>

<Files \"backup.php\">
    Allow from all
</Files>

# Custom error pages
ErrorDocument 403 /error.php?code=403
ErrorDocument 404 /error.php?code=404
ErrorDocument 500 /error.php?code=500

# Security headers
<IfModule mod_headers.c>
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set X-XSS-Protection \"1; mode=block\"
    Header always set Referrer-Policy \"strict-origin-when-cross-origin\"
    Header always set Content-Security-Policy \"default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';\"
</IfModule>

# Disable server signature
ServerSignature Off

# Hide PHP version
<IfModule mod_php7.c>
    php_flag expose_php off
</IfModule>

<IfModule mod_php8.c>
    php_flag expose_php off
</IfModule>
";

    if (!file_exists('.htaccess')) {
        file_put_contents('.htaccess', $htaccess_content);
    }
    
    // Data directory .htaccess
    $data_htaccess = "# Deny all access to data directory
Order Allow,Deny
Deny from all
";
    
    if (!file_exists('data/.htaccess')) {
        file_put_contents('data/.htaccess', $data_htaccess);
    }
    
    // Logs directory .htaccess
    if (!file_exists('logs/.htaccess')) {
        file_put_contents('logs/.htaccess', $data_htaccess);
    }
    
    // Storage directory .htaccess (allow uploads subfolder)
    $storage_htaccess = "# Allow access to uploads but deny PHP execution
<Files ~ \"\\.php$\">
    Order Allow,Deny
    Deny from all
</Files>

# Allow common file types
<FilesMatch \"\\.(jpg|jpeg|png|gif|pdf|txt|doc|docx|xls|xlsx|ppt|pptx|zip|rar)$\">
    Order Allow,Deny
    Allow from all
</FilesMatch>
";
    
    if (!file_exists('storage/.htaccess')) {
        file_put_contents('storage/.htaccess', $storage_htaccess);
    }
}

/**
 * Initialize data files with default structure
 */
function initialize_data_files() {
    // Users file
    if (!file_exists('data/users.json')) {
        $users_data = [
            'users' => [],
            'last_updated' => time(),
            'version' => '1.0'
        ];
        json_write('data/users.json', $users_data);
    }
    
    // Files metadata
    if (!file_exists('data/files.json')) {
        $files_data = [
            'files' => [],
            'last_updated' => time(),
            'version' => '1.0'
        ];
        json_write('data/files.json', $files_data);
    }
    
    // Shares data
    if (!file_exists('data/shares.json')) {
        $shares_data = [
            'shares' => [],
            'last_updated' => time(),
            'version' => '1.0'
        ];
        json_write('data/shares.json', $shares_data);
    }
    
    // System logs
    if (!file_exists('data/logs.json')) {
        $logs_data = [
            'logs' => [],
            'last_updated' => time(),
            'version' => '1.0'
        ];
        json_write('data/logs.json', $logs_data);
    }
    
    // Blocked IPs
    if (!file_exists('data/blocked-ips.json')) {
        $blocked_ips_data = [
            'blocked_ips' => [],
            'last_updated' => time(),
            'version' => '1.0'
        ];
        json_write('data/blocked-ips.json', $blocked_ips_data);
    }
    
    // System configuration
    if (!file_exists('data/config.json')) {
        $config_data = [
            'settings' => [
                'site_name' => 'FileServer',
                'max_file_size' => 50 * 1024 * 1024, // 50MB
                'allowed_extensions' => ['txt', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'],
                'require_login' => true,
                'allow_registration' => true,
                'enable_sharing' => true,
                'enable_compression' => true,
                'auto_backup' => false,
                'backup_retention_days' => 30,
                'session_timeout' => 3600, // 1 hour
                'max_login_attempts' => 5,
                'lockout_duration' => 900, // 15 minutes
                'enable_thumbnails' => true,
                'enable_versioning' => true,
                'enable_virus_scan' => false,
                'maintenance_mode' => false
            ],
            'last_updated' => time(),
            'version' => '1.0'
        ];
        json_write('data/config.json', $config_data);
    }
}

/**
 * Create default admin user if no users exist
 */
function create_default_admin() {
    $users_data = json_read('data/users.json');
    
    if (empty($users_data['users'])) {
        $admin_user = [
            'id' => 1,
            'username' => 'admin',
            'email' => 'admin@fileserver.local',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => time(),
            'last_login' => null,
            'is_active' => true,
            'profile' => [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'theme' => 'auto',
                'language' => 'en',
                'timezone' => 'UTC'
            ],
            'security' => [
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'password_changed_at' => time(),
                'two_factor_enabled' => false
            ],
            'permissions' => [
                'upload' => true,
                'download' => true,
                'delete' => true,
                'share' => true,
                'admin' => true,
                'user_management' => true,
                'system_settings' => true
            ]
        ];
        
        $users_data['users'][] = $admin_user;
        $users_data['last_updated'] = time();
        
        json_write('data/users.json', $users_data);
        
        // Log the admin user creation
        log_activity('system', 'admin_created', 'Default admin user created', [
            'username' => 'admin',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
}

/**
 * Check system requirements
 */
function check_system_requirements() {
    $requirements = [
        'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'json_extension' => extension_loaded('json'),
        'mbstring_extension' => extension_loaded('mbstring'),
        'fileinfo_extension' => extension_loaded('fileinfo'),
        'zip_extension' => extension_loaded('zip'),
        'gd_extension' => extension_loaded('gd'),
        'curl_extension' => extension_loaded('curl'),
        'data_writable' => is_writable('data') || mkdir('data', 0755, true),
        'logs_writable' => is_writable('logs') || mkdir('logs', 0755, true),
        'storage_writable' => is_writable('storage') || mkdir('storage', 0755, true)
    ];
    
    return $requirements;
}

/**
 * Generate system report
 */
function generate_system_report() {
    $requirements = check_system_requirements();
    $report = [];
    
    $report['php_info'] = [
        'version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size')
    ];
    
    $report['requirements'] = $requirements;
    $report['disk_space'] = [
        'total' => disk_total_space('.'),
        'free' => disk_free_space('.'),
        'used' => disk_total_space('.') - disk_free_space('.')
    ];
    
    $report['directory_permissions'] = [
        'data' => [
            'exists' => is_dir('data'),
            'readable' => is_readable('data'),
            'writable' => is_writable('data')
        ],
        'logs' => [
            'exists' => is_dir('logs'),
            'readable' => is_readable('logs'),
            'writable' => is_writable('logs')
        ],
        'storage' => [
            'exists' => is_dir('storage'),
            'readable' => is_readable('storage'),
            'writable' => is_writable('storage')
        ]
    ];
    
    return $report;
}

// Run initialization if called directly
if (basename($_SERVER['PHP_SELF']) === 'init.php') {
    // Check if already initialized
    if (file_exists('data/config.json') && file_exists('data/users.json')) {
        echo json_encode([
            'success' => false,
            'message' => 'FileServer is already initialized'
        ]);
        exit;
    }
    
    // Run initialization
    $result = initialize_fileserver();
    
    // Check system requirements
    $requirements = check_system_requirements();
    $all_requirements_met = !in_array(false, $requirements, true);
    
    if (!$all_requirements_met) {
        $result['success'] = false;
        $result['requirements'] = $requirements;
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($result);
}
?>
