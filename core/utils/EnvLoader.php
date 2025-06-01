<?php
/**
 * Environment Configuration Loader
 * 
 * Loads configuration from .env file and defines constants
 * OS Independent implementation
 */

class EnvLoader 
{
    private static $loaded = false;
    private static $env = [];

    /**
     * Load environment configuration
     */
    public static function load($envPath = null) 
    {
        if (self::$loaded) {
            return;
        }

        $envPath = $envPath ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        
        if (!file_exists($envPath)) {
            $examplePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'example.env';
            if (file_exists($examplePath)) {
                copy($examplePath, $envPath);
            } else {
                throw new Exception('.env file not found and example.env not available');
            }
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            
            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (($value[0] === '"' && $value[-1] === '"') || 
                    ($value[0] === "'" && $value[-1] === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                self::$env[$key] = $value;
            }
        }

        self::defineConstants();
        self::$loaded = true;
    }

    /**
     * Define PHP constants from environment variables
     */
    private static function defineConstants() 
    {
        // Basic settings
        self::define('APP_NAME', 'FileServer');
        self::define('APP_VERSION', '2.0.0');
        self::define('APP_DESCRIPTION', 'Portable PHP File Storage Server');
        self::define('DEBUG', false);
        self::define('TIMEZONE', 'UTC');

        // Server configuration
        self::define('BASE_URL', 'http://localhost');
        self::define('BASE_PATH', '');
        self::define('ADMIN_EMAIL', 'admin@localhost');

        // Database settings
        self::define('DB_TYPE', 'sqlite');
        self::define('DB_HOST', 'localhost');
        self::define('DB_NAME', 'fileserver');
        self::define('DB_USER', 'root');        self::define('DB_PASS', '');
        self::define('DB_PREFIX', 'fs_');
        self::define('DB_CHARSET', 'utf8mb4');
        self::define('DB_PATH', self::getStoragePath('system/database.sqlite'));

        // Storage paths
        $basePath = dirname(__DIR__, 2);
        $storagePath = $basePath . DIRECTORY_SEPARATOR . 'storage';
        self::define('STORAGE_PATH', $storagePath);
        self::define('PUBLIC_PATH', $storagePath . DIRECTORY_SEPARATOR . 'public');
        self::define('PRIVATE_PATH', $storagePath . DIRECTORY_SEPARATOR . 'private');
        self::define('TEMP_PATH', $storagePath . DIRECTORY_SEPARATOR . 'temp');
        self::define('SHARED_PATH', $storagePath . DIRECTORY_SEPARATOR . 'shared');
        self::define('BACKUP_PATH', $storagePath . DIRECTORY_SEPARATOR . 'backup');
        self::define('ARCHIVE_PATH', $storagePath . DIRECTORY_SEPARATOR . 'archive');
        self::define('CACHE_PATH', $basePath . DIRECTORY_SEPARATOR . 'cache');
        self::define('LOGS_PATH', $basePath . DIRECTORY_SEPARATOR . 'logs');

        // File settings
        self::define('MAX_FILE_SIZE', '100MB');
        self::define('MAX_TOTAL_SIZE', '10GB');
        self::define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,pdf,txt,doc,docx,zip,mp4,mp3');
        self::define('ENABLE_THUMBNAILS', true);
        self::define('THUMBNAIL_SIZE', 300);
        self::define('THUMBNAIL_QUALITY', 85);

        // Security settings
        self::define('SECRET_KEY', self::generateSecureKey());
        self::define('ENCRYPTION_KEY', self::generateSecureKey());
        self::define('JWT_SECRET', self::generateSecureKey());
        self::define('SESSION_LIFETIME', 3600);
        self::define('PASSWORD_MIN_LENGTH', 8);
        self::define('LOGIN_ATTEMPTS_MAX', 5);
        self::define('LOGIN_LOCKOUT_TIME', 900);
        self::define('ENABLE_2FA', false);
        self::define('ENABLE_REGISTRATION', true);
        self::define('REQUIRE_EMAIL_VERIFICATION', false);

        // Admin settings
        self::define('ADMIN_USERNAME', 'admin');
        self::define('ADMIN_PASSWORD', 'admin123');

        // Feature settings
        self::define('ENABLE_API', true);
        self::define('ENABLE_WEBHOOKS', false);
        self::define('ENABLE_COMPRESSION', true);
        self::define('ENABLE_VERSIONING', true);
        self::define('MAX_VERSIONS', 5);
        self::define('ENABLE_SHARING', true);
        self::define('SHARE_TOKEN_LENGTH', 32);
        self::define('ENABLE_SEARCH', true);
        self::define('ENABLE_LOGGING', true);
        self::define('LOG_LEVEL', 'INFO');
        self::define('LOG_MAX_SIZE', '10MB');
        self::define('LOG_MAX_FILES', 5);

        // Performance settings
        self::define('ENABLE_CACHING', true);
        self::define('CACHE_DURATION', 3600);
        self::define('UPLOAD_CHUNK_SIZE', 1048576);
        self::define('STREAMING_CHUNK_SIZE', 8192);
        self::define('MAX_CONCURRENT_UPLOADS', 3);

        // Email settings
        self::define('ENABLE_EMAIL', false);
        self::define('SMTP_HOST', '');
        self::define('SMTP_PORT', 587);
        self::define('SMTP_USER', '');
        self::define('SMTP_PASS', '');
        self::define('SMTP_FROM', '');
        self::define('SMTP_ENCRYPTION', 'tls');

        // Update settings
        self::define('AUTO_UPDATE_CHECK', true);
        self::define('UPDATE_BRANCH', 'main');
        self::define('UPDATE_REPO', 'yourusername/FileServer');
        self::define('UPDATE_INTERVAL', 86400);

        // Rate limiting
        self::define('API_RATE_LIMIT', 100);
        self::define('UPLOAD_RATE_LIMIT', 10);
        self::define('DOWNLOAD_RATE_LIMIT', 50);

        // Webhook settings
        self::define('WEBHOOK_MAX_RETRIES', 3);
        self::define('WEBHOOK_TIMEOUT', 30);
        self::define('WEBHOOK_SECRET', self::generateSecureKey());

        // Plugin system
        self::define('ENABLE_PLUGINS', true);
        self::define('PLUGIN_AUTO_UPDATE', false);

        // Analytics
        self::define('ENABLE_ANALYTICS', true);
        self::define('ANALYTICS_RETENTION_DAYS', 90);
        self::define('ENABLE_HEALTH_CHECKS', true);
        self::define('HEALTH_CHECK_INTERVAL', 300);
    }

    /**
     * Define constant with environment value or default
     */
    private static function define($key, $default = null) 
    {
        if (!defined($key)) {
            $value = self::get($key, $default);
            
            // Convert string booleans
            if (is_string($value)) {
                $lower = strtolower($value);
                if ($lower === 'true') $value = true;
                elseif ($lower === 'false') $value = false;
                elseif (is_numeric($value)) $value = is_float($value + 0) ? (float)$value : (int)$value;
            }
            
            define($key, $value);
        }
    }

    /**
     * Get environment value
     */
    public static function get($key, $default = null) 
    {
        return isset(self::$env[$key]) ? self::$env[$key] : $default;
    }

    /**
     * Update environment file
     */
    public static function updateEnv($updates, $envPath = null) 
    {
        $envPath = $envPath ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        
        if (!file_exists($envPath)) {
            throw new Exception('.env file not found');
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES);
        $updated = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            if (empty($trimmed) || $trimmed[0] === '#') {
                $updated[] = $line;
                continue;
            }

            if (strpos($trimmed, '=') !== false) {
                list($key, $oldValue) = explode('=', $trimmed, 2);
                $key = trim($key);
                
                if (isset($updates[$key])) {
                    $newValue = $updates[$key];
                    // Add quotes if value contains spaces
                    if (strpos($newValue, ' ') !== false) {
                        $newValue = '"' . $newValue . '"';
                    }
                    $updated[] = $key . '=' . $newValue;
                    unset($updates[$key]);
                } else {
                    $updated[] = $line;
                }
            } else {
                $updated[] = $line;
            }
        }

        // Add any new keys
        foreach ($updates as $key => $value) {
            if (strpos($value, ' ') !== false) {
                $value = '"' . $value . '"';
            }
            $updated[] = $key . '=' . $value;
        }

        return file_put_contents($envPath, implode(PHP_EOL, $updated) . PHP_EOL);
    }

    /**
     * Generate secure random key
     */
    private static function generateSecureKey($length = 64) 
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        } else {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $key = '';
            for ($i = 0; $i < $length; $i++) {
                $key .= $chars[mt_rand(0, strlen($chars) - 1)];
            }
            return $key;
        }
    }    /**
     * Get storage path with OS-independent directory separators
     */
    private static function getStoragePath($path = '') 
    {
        $basePath = dirname(__DIR__, 2);
        $storagePath = $basePath . DIRECTORY_SEPARATOR . 'storage';
        return $storagePath . ($path ? DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path) : '');
    }

    /**
     * Ensure required directories exist
     */
    public static function createDirectories() 
    {
        $directories = [
            STORAGE_PATH,
            PUBLIC_PATH,
            PRIVATE_PATH,
            TEMP_PATH,
            SHARED_PATH,
            BACKUP_PATH,
            ARCHIVE_PATH,
            CACHE_PATH,
            LOGS_PATH,
            STORAGE_PATH . DIRECTORY_SEPARATOR . 'system',
            STORAGE_PATH . DIRECTORY_SEPARATOR . 'thumbnails',
            STORAGE_PATH . DIRECTORY_SEPARATOR . 'quarantine',
            STORAGE_PATH . DIRECTORY_SEPARATOR . 'index',
            CACHE_PATH . DIRECTORY_SEPARATOR . 'templates',
            CACHE_PATH . DIRECTORY_SEPARATOR . 'data',
            CACHE_PATH . DIRECTORY_SEPARATOR . 'thumbnails',
            CACHE_PATH . DIRECTORY_SEPARATOR . 'search',
            LOGS_PATH . DIRECTORY_SEPARATOR . 'archive'
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception("Failed to create directory: $dir");
                }
            }
        }

        // Create .htaccess files for protected directories
        self::createHtaccessFiles();
    }

    /**
     * Create .htaccess protection files
     */
    private static function createHtaccessFiles() 
    {
        $protectedDirs = [
            PRIVATE_PATH => "# Private files - no direct access\nOrder deny,allow\nDeny from all\n",
            TEMP_PATH => "# Temporary files - no direct access\nOrder deny,allow\nDeny from all\n",
            BACKUP_PATH => "# Backup files - no direct access\nOrder deny,allow\nDeny from all\n",
            STORAGE_PATH . DIRECTORY_SEPARATOR . 'system' => "# System files - no direct access\nOrder deny,allow\nDeny from all\n",
            STORAGE_PATH . DIRECTORY_SEPARATOR . 'quarantine' => "# Quarantine files - no direct access\nOrder deny,allow\nDeny from all\n",
            CACHE_PATH => "# Cache files - no direct access\nOrder deny,allow\nDeny from all\n",
            LOGS_PATH => "# Log files - no direct access\nOrder deny,allow\nDeny from all\n"
        ];

        foreach ($protectedDirs as $dir => $content) {
            $htaccessFile = $dir . DIRECTORY_SEPARATOR . '.htaccess';
            if (!file_exists($htaccessFile)) {
                file_put_contents($htaccessFile, $content);
            }
        }

        // Public directory - allow access but prevent script execution
        $publicHtaccess = PUBLIC_PATH . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($publicHtaccess)) {
            $content = "# Public files - allow access but prevent script execution\n";
            $content .= "php_flag engine off\n";
            $content .= "AddHandler cgi-script .php .phtml .php3 .pl .py .jsp .asp .sh .cgi\n";
            $content .= "Options -ExecCGI\n";
            $content .= "\n# Force download for potentially dangerous files\n";
            $content .= "<FilesMatch \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\">\n";
            $content .= "    ForceType application/octet-stream\n";
            $content .= "    Header set Content-Disposition attachment\n";
            $content .= "</FilesMatch>\n";
            file_put_contents($publicHtaccess, $content);
        }
    }
}
