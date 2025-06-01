<?php

/**
 * SystemController
 * 
 * Handles system-level operations including health monitoring,
 * performance metrics, configuration management, and system maintenance.
 */
class SystemController extends BaseController {
    private $db;
    private $auth;
    
    public function __construct($db, $auth) {
        parent::__construct();
        $this->db = $db;
        $this->auth = $auth;
    }
    
    /**
     * Get system health status
     */
    public function health() {
        try {
            $health = [
                'status' => 'ok',
                'timestamp' => date('c'),
                'checks' => []
            ];
            
            // Database connectivity
            try {
                $this->db->query("SELECT 1");
                $health['checks']['database'] = ['status' => 'ok', 'message' => 'Database connected'];
            } catch (Exception $e) {
                $health['checks']['database'] = ['status' => 'error', 'message' => $e->getMessage()];
                $health['status'] = 'error';
            }
            
            // Storage directory
            $storageDir = getenv('STORAGE_PATH') ?: './storage';
            if (is_writable($storageDir)) {
                $health['checks']['storage'] = ['status' => 'ok', 'message' => 'Storage writable'];
            } else {
                $health['checks']['storage'] = ['status' => 'error', 'message' => 'Storage not writable'];
                $health['status'] = 'error';
            }
            
            // Memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = ini_get('memory_limit');
            $memoryPercent = ($memoryUsage / $this->convertToBytes($memoryLimit)) * 100;
            
            $health['checks']['memory'] = [
                'status' => $memoryPercent > 90 ? 'warning' : 'ok',
                'usage' => $this->formatBytes($memoryUsage),
                'limit' => $memoryLimit,
                'percent' => round($memoryPercent, 2)
            ];
            
            // Disk space
            $diskFree = disk_free_space($storageDir);
            $diskTotal = disk_total_space($storageDir);
            $diskPercent = (($diskTotal - $diskFree) / $diskTotal) * 100;
            
            $health['checks']['disk'] = [
                'status' => $diskPercent > 90 ? 'warning' : 'ok',
                'free' => $this->formatBytes($diskFree),
                'total' => $this->formatBytes($diskTotal),
                'used_percent' => round($diskPercent, 2)
            ];
            
            // PHP version and extensions
            $requiredExtensions = ['pdo', 'json', 'curl', 'mbstring', 'openssl'];
            $missingExtensions = [];
            
            foreach ($requiredExtensions as $ext) {
                if (!extension_loaded($ext)) {
                    $missingExtensions[] = $ext;
                }
            }
            
            $health['checks']['php'] = [
                'status' => empty($missingExtensions) ? 'ok' : 'error',
                'version' => PHP_VERSION,
                'missing_extensions' => $missingExtensions
            ];
            
            return $this->success($health);
            
        } catch (Exception $e) {
            return $this->error('Failed to check system health: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get system metrics and statistics
     */
    public function metrics() {
        try {
            if (!$this->auth->hasPermission('admin.view')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $metrics = [];
            
            // System uptime (approximated by first user creation)
            $firstUser = $this->db->query("SELECT created_at FROM users ORDER BY created_at ASC LIMIT 1")->fetch();
            if ($firstUser) {
                $uptime = time() - strtotime($firstUser['created_at']);
                $metrics['uptime'] = [
                    'seconds' => $uptime,
                    'human' => $this->formatDuration($uptime)
                ];
            }
            
            // User statistics
            $userStats = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as new_24h
                FROM users
            ")->fetch();
            $metrics['users'] = $userStats;
            
            // File statistics
            $fileStats = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(size) as total_size,
                    AVG(size) as avg_size,
                    COUNT(DISTINCT user_id) as unique_uploaders,
                    SUM(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as uploaded_24h
                FROM files WHERE deleted_at IS NULL
            ")->fetch();
            $metrics['files'] = [
                'total' => intval($fileStats['total']),
                'total_size' => intval($fileStats['total_size']),
                'total_size_human' => $this->formatBytes($fileStats['total_size']),
                'avg_size' => intval($fileStats['avg_size']),
                'avg_size_human' => $this->formatBytes($fileStats['avg_size']),
                'unique_uploaders' => intval($fileStats['unique_uploaders']),
                'uploaded_24h' => intval($fileStats['uploaded_24h'])
            ];
            
            // API usage statistics
            $apiStats = $this->db->query("
                SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as requests_1h,
                    SUM(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as requests_24h
                FROM audit_logs WHERE action LIKE 'api.%'
            ")->fetch();
            $metrics['api'] = $apiStats;
            
            // Storage usage by type
            $storageByType = $this->db->query("
                SELECT 
                    SUBSTRING_INDEX(filename, '.', -1) as extension,
                    COUNT(*) as count,
                    SUM(size) as total_size
                FROM files 
                WHERE deleted_at IS NULL
                GROUP BY extension
                ORDER BY total_size DESC
                LIMIT 10
            ")->fetchAll();
            
            $metrics['storage_by_type'] = array_map(function($row) {
                return [
                    'extension' => $row['extension'],
                    'count' => intval($row['count']),
                    'size' => intval($row['total_size']),
                    'size_human' => $this->formatBytes($row['total_size'])
                ];
            }, $storageByType);
            
            // Performance metrics
            $metrics['performance'] = [
                'memory_usage' => memory_get_usage(true),
                'memory_usage_human' => $this->formatBytes(memory_get_usage(true)),
                'memory_peak' => memory_get_peak_usage(true),
                'memory_peak_human' => $this->formatBytes(memory_get_peak_usage(true)),
                'php_version' => PHP_VERSION,
                'loaded_extensions' => get_loaded_extensions()
            ];
            
            return $this->success($metrics);
            
        } catch (Exception $e) {
            return $this->error('Failed to get system metrics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get system configuration
     */
    public function config() {
        try {
            if (!$this->auth->hasPermission('admin.config')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $settings = $this->db->query("SELECT name, value, type FROM settings")->fetchAll();
            
            $config = [];
            foreach ($settings as $setting) {
                $value = $setting['value'];
                
                // Convert value based on type
                switch ($setting['type']) {
                    case 'boolean':
                        $value = (bool)$value;
                        break;
                    case 'integer':
                        $value = (int)$value;
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                }
                
                $config[$setting['name']] = $value;
            }
            
            return $this->success($config);
            
        } catch (Exception $e) {
            return $this->error('Failed to get configuration: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update system configuration
     */
    public function updateConfig() {
        try {
            if (!$this->auth->hasPermission('admin.config')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $data = $this->getJsonInput();
            if (empty($data)) {
                return $this->error('No configuration data provided', 400);
            }
            
            $this->db->beginTransaction();
            
            foreach ($data as $name => $value) {
                // Determine type
                $type = 'string';
                if (is_bool($value)) {
                    $type = 'boolean';
                    $value = $value ? '1' : '0';
                } elseif (is_int($value)) {
                    $type = 'integer';
                } elseif (is_array($value) || is_object($value)) {
                    $type = 'json';
                    $value = json_encode($value);
                }
                
                // Update or insert setting
                $existing = $this->db->query("SELECT id FROM settings WHERE name = ?", [$name])->fetch();
                
                if ($existing) {
                    $this->db->query("UPDATE settings SET value = ?, type = ?, updated_at = NOW() WHERE name = ?", 
                        [$value, $type, $name]);
                } else {
                    $this->db->query("INSERT INTO settings (name, value, type, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())", 
                        [$name, $value, $type]);
                }
            }
            
            $this->db->commit();
            
            // Log configuration change
            $this->db->query("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())", 
                [$this->auth->getCurrentUser()['id'], 'system.config.update', json_encode($data), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            return $this->success(['message' => 'Configuration updated successfully']);
            
        } catch (Exception $e) {
            $this->db->rollback();
            return $this->error('Failed to update configuration: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Clear system cache
     */
    public function clearCache() {
        try {
            if (!$this->auth->hasPermission('admin.maintenance')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $cacheDir = getenv('CACHE_PATH') ?: './cache';
            $cleared = 0;
            
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                        $cleared++;
                    }
                }
            }
            
            // Log cache clear
            $this->db->query("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())", 
                [$this->auth->getCurrentUser()['id'], 'system.cache.clear', json_encode(['files_cleared' => $cleared]), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            return $this->success(['message' => "Cache cleared successfully", 'files_cleared' => $cleared]);
            
        } catch (Exception $e) {
            return $this->error('Failed to clear cache: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Run system maintenance
     */
    public function maintenance() {
        try {
            if (!$this->auth->hasPermission('admin.maintenance')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $results = [];
            
            // Clean up old sessions
            $cleaned = $this->db->query("DELETE FROM sessions WHERE expires_at < NOW()")->rowCount();
            $results['cleaned_sessions'] = $cleaned;
            
            // Clean up old audit logs (older than 90 days)
            $cleaned = $this->db->query("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)")->rowCount();
            $results['cleaned_audit_logs'] = $cleaned;
            
            // Clean up orphaned file records
            $orphaned = $this->db->query("DELETE FROM files WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")->rowCount();
            $results['cleaned_orphaned_files'] = $orphaned;
            
            // Optimize database
            if (getenv('DB_TYPE') === 'mysql') {
                $tables = ['users', 'files', 'sessions', 'audit_logs', 'settings', 'webhooks', 'plugins'];
                foreach ($tables as $table) {
                    $this->db->query("OPTIMIZE TABLE $table");
                }
                $results['optimized_tables'] = count($tables);
            }
            
            // Log maintenance run
            $this->db->query("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())", 
                [$this->auth->getCurrentUser()['id'], 'system.maintenance.run', json_encode($results), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            return $this->success(['message' => 'Maintenance completed successfully', 'results' => $results]);
            
        } catch (Exception $e) {
            return $this->error('Failed to run maintenance: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get system information
     */
    public function info() {
        try {
            if (!$this->auth->hasPermission('admin.view')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $info = [
                'version' => '1.0.0',
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'database_type' => getenv('DB_TYPE'),
                'timezone' => date_default_timezone_get(),
                'max_upload_size' => ini_get('upload_max_filesize'),
                'max_post_size' => ini_get('post_max_size'),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'extensions' => get_loaded_extensions(),
                'environment' => getenv('APP_ENV') ?: 'production'
            ];
            
            return $this->success($info);
            
        } catch (Exception $e) {
            return $this->error('Failed to get system info: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = intval($val);
        
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
    
    /**
     * Format bytes to human readable string
     */
    private function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = log($size, 1024);
        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
    }
    
    /**
     * Format duration in seconds to human readable string
     */
    private function formatDuration($seconds) {
        $units = [
            'year' => 31536000,
            'month' => 2592000,
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1
        ];
        
        $result = [];
        foreach ($units as $name => $divisor) {
            $value = floor($seconds / $divisor);
            if ($value > 0) {
                $result[] = $value . ' ' . $name . ($value > 1 ? 's' : '');
                $seconds %= $divisor;
            }
            if (count($result) >= 2) break;
        }
        
        return implode(', ', $result) ?: '0 seconds';
    }
}
