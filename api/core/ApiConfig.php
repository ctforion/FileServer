<?php
/**
 * API Configuration Management
 * Centralized configuration for separated API system
 */

class ApiConfig {
    private static $config = null;
    
    public static function init() {
        if (self::$config === null) {
            // Load main configuration
            $mainConfig = include dirname(__DIR__, 2) . '/config.php';
            
            // API-specific configuration
            self::$config = [
                // Core settings
                'version' => '1.0.0',
                'base_path' => '/api',
                'timezone' => 'UTC',
                
                // Security settings
                'rate_limit' => [
                    'max_requests' => 100,
                    'time_window' => 3600, // 1 hour
                    'blocked_duration' => 300 // 5 minutes
                ],
                'cors' => [
                    'allowed_origins' => ['*'],
                    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With']
                ],
                
                // Authentication
                'auth' => [
                    'session_timeout' => 7200, // 2 hours
                    'max_login_attempts' => 5,
                    'lockout_duration' => 900, // 15 minutes
                    'password_min_length' => 8
                ],
                
                // File handling
                'upload' => [
                    'max_size' => $mainConfig['max_file_size'] ?? 10485760, // 10MB
                    'allowed_extensions' => $mainConfig['allowed_extensions'] ?? [],
                    'storage_path' => $mainConfig['storage_path'] ?? 'uploads'
                ],
                
                // Database settings
                'database' => [
                    'path' => $mainConfig['database_path'] ?? 'data/database.db',
                    'backup_interval' => 3600 // 1 hour
                ],
                
                // Logging
                'logging' => [
                    'enabled' => true,
                    'level' => 'INFO',
                    'max_file_size' => 10485760, // 10MB
                    'max_files' => 5
                ],
                
                // Response format
                'response' => [
                    'include_timestamp' => true,
                    'include_request_id' => true,
                    'pretty_print' => false
                ]
            ];
        }
        return self::$config;
    }
    
    public static function get($key = null, $default = null) {
        $config = self::init();
        
        if ($key === null) {
            return $config;
        }
        
        // Support dot notation for nested keys
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    public static function set($key, $value) {
        $config = self::init();
        
        // Support dot notation for nested keys
        $keys = explode('.', $key);
        $current = &self::$config;
        
        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        
        $current = $value;
    }
    
    public static function getStoragePath() {
        return dirname(__DIR__, 2) . '/' . self::get('upload.storage_path', 'uploads');
    }
    
    public static function getDataPath() {
        return dirname(__DIR__, 2) . '/data';
    }
    
    public static function getUsersFile() {
        return self::getDataPath() . '/users.json';
    }
    
    public static function isDebugMode() {
        return self::get('debug', false);
    }
}
