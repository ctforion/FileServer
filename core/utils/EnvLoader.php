<?php

class EnvLoader {
    private static $config = [];
    
    public static function load($configFile) {
        if (file_exists($configFile)) {
            self::$config = include $configFile;
        }
    }
    
    public static function get($key, $default = null) {
        return self::$config[$key] ?? $default;
    }
    
    public static function set($key, $value) {
        self::$config[$key] = $value;
    }
    
    public static function getAll() {
        return self::$config;
    }
    
    public static function getStoragePath() {
        return self::get('storage_path', __DIR__ . '/../../storage');
    }
    
    public static function getDatabasePath() {
        return self::get('database_path', __DIR__ . '/../../storage/database.sqlite');
    }
    
    public static function getMaxFileSize() {
        return self::get('max_file_size', 10485760); // 10MB default
    }
    
    public static function getAllowedExtensions() {
        return self::get('allowed_extensions', [
            'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 
            'txt', 'zip', 'rar', 'mp4', 'mp3', 'xlsx', 'pptx'
        ]);
    }
    
    public static function isDebugMode() {
        return self::get('debug', false);
    }
    
    public static function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME']);
        return $protocol . '://' . $host . $path;
    }
}
