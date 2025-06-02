#!/usr/bin/env php
<?php
/**
 * FileServer Deployment Script
 * Automates the deployment and setup process
 */

// Ensure script is run from command line
if (PHP_SAPI !== 'cli') {
    die('This script must be run from the command line.');
}

// Colors for console output
class Console {
    const RESET = "\033[0m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const MAGENTA = "\033[35m";
    const CYAN = "\033[36m";
    const WHITE = "\033[37m";
    
    public static function output($message, $color = self::WHITE) {
        echo $color . $message . self::RESET . "\n";
    }
    
    public static function success($message) {
        self::output("✓ " . $message, self::GREEN);
    }
    
    public static function error($message) {
        self::output("✗ " . $message, self::RED);
    }
    
    public static function warning($message) {
        self::output("⚠ " . $message, self::YELLOW);
    }
    
    public static function info($message) {
        self::output("ℹ " . $message, self::BLUE);
    }
    
    public static function title($message) {
        self::output("", self::WHITE);
        self::output("=== " . $message . " ===", self::CYAN);
        self::output("", self::WHITE);
    }
}

/**
 * Deployment class
 */
class FileServerDeployer {
    private $errors = [];
    private $warnings = [];
    
    public function __construct() {
        Console::title("FileServer Deployment Script");
        Console::info("Initializing FileServer deployment...");
    }
    
    /**
     * Run the complete deployment process
     */
    public function deploy() {
        $this->checkSystemRequirements();
        $this->createDirectories();
        $this->setPermissions();
        $this->createConfigurationFiles();
        $this->initializeDataFiles();
        $this->createSecurityFiles();
        $this->runHealthCheck();
        $this->displaySummary();
    }
    
    /**
     * Check system requirements
     */
    private function checkSystemRequirements() {
        Console::title("Checking System Requirements");
        
        // Check PHP version
        $php_version = PHP_VERSION;
        if (version_compare($php_version, '7.4.0', '>=')) {
            Console::success("PHP version: $php_version");
        } else {
            Console::error("PHP version $php_version is not supported. Minimum required: 7.4.0");
            $this->errors[] = "Unsupported PHP version";
        }
        
        // Check required extensions
        $required_extensions = ['json', 'mbstring', 'fileinfo'];
        $optional_extensions = ['zip', 'gd', 'curl'];
        
        foreach ($required_extensions as $extension) {
            if (extension_loaded($extension)) {
                Console::success("Extension '$extension' is loaded");
            } else {
                Console::error("Required extension '$extension' is not loaded");
                $this->errors[] = "Missing extension: $extension";
            }
        }
        
        foreach ($optional_extensions as $extension) {
            if (extension_loaded($extension)) {
                Console::success("Extension '$extension' is loaded");
            } else {
                Console::warning("Optional extension '$extension' is not loaded");
                $this->warnings[] = "Missing optional extension: $extension";
            }
        }
        
        // Check memory limit
        $memory_limit = ini_get('memory_limit');
        Console::info("Memory limit: $memory_limit");
        
        // Check upload limits
        $upload_max = ini_get('upload_max_filesize');
        $post_max = ini_get('post_max_size');
        Console::info("Upload max filesize: $upload_max");
        Console::info("Post max size: $post_max");
    }
    
    /**
     * Create required directories
     */
    private function createDirectories() {
        Console::title("Creating Directories");
        
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
            'storage/versions',
            'assets/css',
            'assets/js',
            'templates',
            'includes',
            'api'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    Console::success("Created directory: $dir");
                } else {
                    Console::error("Failed to create directory: $dir");
                    $this->errors[] = "Failed to create directory: $dir";
                }
            } else {
                Console::info("Directory already exists: $dir");
            }
        }
    }
    
    /**
     * Set appropriate permissions
     */
    private function setPermissions() {
        Console::title("Setting Permissions");
        
        $permission_map = [
            'data' => 0755,
            'logs' => 0755,
            'storage' => 0755,
            'storage/uploads' => 0755,
            'storage/compressed' => 0755,
            'storage/quarantine' => 0755,
            'storage/thumbnails' => 0755,
            'storage/versions' => 0755
        ];
        
        foreach ($permission_map as $path => $permission) {
            if (is_dir($path)) {
                if (chmod($path, $permission)) {
                    Console::success("Set permissions for $path: " . decoct($permission));
                } else {
                    Console::warning("Failed to set permissions for $path");
                    $this->warnings[] = "Failed to set permissions for $path";
                }
            }
        }
        
        // Check if directories are writable
        $writable_dirs = ['data', 'logs', 'storage'];
        foreach ($writable_dirs as $dir) {
            if (is_writable($dir)) {
                Console::success("Directory $dir is writable");
            } else {
                Console::error("Directory $dir is not writable");
                $this->errors[] = "Directory $dir is not writable";
            }
        }
    }
    
    /**
     * Create configuration files
     */
    private function createConfigurationFiles() {
        Console::title("Creating Configuration Files");
        
        // Create .env file if it doesn't exist
        if (!file_exists('.env')) {
            $env_content = "# FileServer Environment Configuration
APP_NAME=FileServer
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

# Database Configuration (if using database)
DB_HOST=localhost
DB_USERNAME=fileserver
DB_PASSWORD=
DB_DATABASE=fileserver

# Security
APP_KEY=" . bin2hex(random_bytes(16)) . "
SESSION_SECURE=false
CSRF_ENABLED=true

# File Upload
MAX_FILE_SIZE=52428800
UPLOAD_PATH=storage/uploads

# Email Configuration
MAIL_ENABLED=false
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@localhost
MAIL_FROM_NAME=FileServer

# Features
REGISTRATION_ENABLED=true
SHARING_ENABLED=true
COMPRESSION_ENABLED=true
THUMBNAILS_ENABLED=true
";
            
            if (file_put_contents('.env', $env_content)) {
                Console::success("Created .env file");
            } else {
                Console::error("Failed to create .env file");
                $this->errors[] = "Failed to create .env file";
            }
        } else {
            Console::info(".env file already exists");
        }
        
        // Create local configuration file
        if (!file_exists('config.local.php')) {
            $local_config = "<?php
/**
 * Local Configuration Override
 * This file is ignored by version control
 */

return [
    'debug' => false,
    'app_url' => 'http://localhost',
    'session_secure' => false,
    'timezone' => 'UTC',
    
    // Override any configuration values here
    'max_file_size' => 50 * 1024 * 1024, // 50MB
    'allow_registration' => true,
    'default_theme' => 'auto',
    
    // Database configuration (if needed)
    'use_database' => false,
    'database' => [
        'host' => 'localhost',
        'username' => '',
        'password' => '',
        'database' => 'fileserver'
    ]
];
";
            
            if (file_put_contents('config.local.php', $local_config)) {
                Console::success("Created config.local.php file");
            } else {
                Console::error("Failed to create config.local.php file");
                $this->errors[] = "Failed to create config.local.php file";
            }
        } else {
            Console::info("config.local.php file already exists");
        }
    }
    
    /**
     * Initialize data files
     */
    private function initializeDataFiles() {
        Console::title("Initializing Data Files");
        
        // Include the initialization script
        if (file_exists('init.php')) {
            ob_start();
            include 'init.php';
            $output = ob_get_clean();
            
            Console::success("Data files initialized");
        } else {
            Console::warning("init.php not found, creating basic data files");
            $this->createBasicDataFiles();
        }
    }
    
    /**
     * Create basic data files
     */
    private function createBasicDataFiles() {
        $data_files = [
            'data/users.json' => ['users' => [], 'last_updated' => time()],
            'data/files.json' => ['files' => [], 'last_updated' => time()],
            'data/shares.json' => ['shares' => [], 'last_updated' => time()],
            'data/logs.json' => ['logs' => [], 'last_updated' => time()],
            'data/blocked-ips.json' => ['blocked_ips' => [], 'last_updated' => time()],
            'data/config.json' => [
                'settings' => [
                    'site_name' => 'FileServer',
                    'require_login' => true,
                    'allow_registration' => true
                ],
                'last_updated' => time()
            ]
        ];
        
        foreach ($data_files as $file => $data) {
            if (!file_exists($file)) {
                if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT))) {
                    Console::success("Created data file: $file");
                } else {
                    Console::error("Failed to create data file: $file");
                    $this->errors[] = "Failed to create data file: $file";
                }
            }
        }
    }
    
    /**
     * Create security files
     */
    private function createSecurityFiles() {
        Console::title("Creating Security Files");
        
        // Main .htaccess
        $htaccess_content = "# FileServer Security Configuration
RewriteEngine On

# Deny access to sensitive files
<FilesMatch \"\\.(json|log|ini|env)$\">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Allow specific PHP files
<Files \"index.php\">
    Allow from all
</Files>

<Files \"login.php\">
    Allow from all
</Files>

<Files \"dashboard.php\">
    Allow from all
</Files>

# Security headers
<IfModule mod_headers.c>
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set X-XSS-Protection \"1; mode=block\"
</IfModule>

# Hide server information
ServerSignature Off
";
        
        if (!file_exists('.htaccess')) {
            if (file_put_contents('.htaccess', $htaccess_content)) {
                Console::success("Created main .htaccess file");
            } else {
                Console::error("Failed to create main .htaccess file");
                $this->errors[] = "Failed to create main .htaccess file";
            }
        }
        
        // Data directory .htaccess
        $data_htaccess = "Order Allow,Deny\nDeny from all\n";
        if (!file_exists('data/.htaccess')) {
            if (file_put_contents('data/.htaccess', $data_htaccess)) {
                Console::success("Created data/.htaccess file");
            } else {
                Console::error("Failed to create data/.htaccess file");
                $this->errors[] = "Failed to create data/.htaccess file";
            }
        }
        
        // Logs directory .htaccess
        if (!file_exists('logs/.htaccess')) {
            if (file_put_contents('logs/.htaccess', $data_htaccess)) {
                Console::success("Created logs/.htaccess file");
            } else {
                Console::error("Failed to create logs/.htaccess file");
                $this->errors[] = "Failed to create logs/.htaccess file";
            }
        }
    }
    
    /**
     * Run health check
     */
    private function runHealthCheck() {
        Console::title("Running Health Check");
        
        // Check file permissions
        $critical_files = [
            'index.php' => 'readable',
            'data/users.json' => 'writable',
            'data/config.json' => 'writable',
            'storage/uploads' => 'writable',
            'logs' => 'writable'
        ];
        
        foreach ($critical_files as $file => $requirement) {
            if (file_exists($file)) {
                if ($requirement === 'readable' && is_readable($file)) {
                    Console::success("$file is readable");
                } elseif ($requirement === 'writable' && is_writable($file)) {
                    Console::success("$file is writable");
                } else {
                    Console::error("$file is not $requirement");
                    $this->errors[] = "$file is not $requirement";
                }
            } else {
                Console::error("$file does not exist");
                $this->errors[] = "$file does not exist";
            }
        }
        
        // Check disk space
        $free_space = disk_free_space('.');
        $total_space = disk_total_space('.');
        $free_mb = round($free_space / (1024 * 1024));
        $total_mb = round($total_space / (1024 * 1024));
        
        Console::info("Disk space: {$free_mb}MB free of {$total_mb}MB total");
        
        if ($free_space < 100 * 1024 * 1024) { // Less than 100MB
            Console::warning("Low disk space available");
            $this->warnings[] = "Low disk space available";
        }
    }
    
    /**
     * Display deployment summary
     */
    private function displaySummary() {
        Console::title("Deployment Summary");
        
        if (empty($this->errors)) {
            Console::success("Deployment completed successfully!");
            
            if (!empty($this->warnings)) {
                Console::warning("Warnings encountered:");
                foreach ($this->warnings as $warning) {
                    Console::warning("  • $warning");
                }
            }
            
            Console::info("");
            Console::info("Next steps:");
            Console::info("1. Configure your web server to point to this directory");
            Console::info("2. Access the application in your web browser");
            Console::info("3. Log in with default credentials: admin/admin123");
            Console::info("4. Change the default admin password");
            Console::info("5. Configure system settings via the admin panel");
            Console::info("");
            Console::success("FileServer is ready to use!");
            
        } else {
            Console::error("Deployment failed with errors:");
            foreach ($this->errors as $error) {
                Console::error("  • $error");
            }
            
            Console::info("");
            Console::info("Please fix the above errors and run the deployment script again.");
        }
    }
}

// Run deployment
$deployer = new FileServerDeployer();
$deployer->deploy();

exit(empty($deployer->errors) ? 0 : 1);
?>
