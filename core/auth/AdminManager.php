<?php
/**
 * Admin Manager
 * Admin-specific functions and system management tools
 */

require_once __DIR__ . '/../database/DatabaseManager.php';
require_once __DIR__ . '/../logging/Logger.php';
require_once __DIR__ . '/../logging/LogAnalyzer.php';
require_once __DIR__ . '/UserManager.php';

class AdminManager {
    private $db;
    private $logger;
    private $logAnalyzer;
    private $userManager;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->logger = new Logger();
        $this->logAnalyzer = new LogAnalyzer();
        $this->userManager = new UserManager();
    }
    
    /**
     * Get system statistics
     */
    public function getSystemStats() {
        try {
            $stats = [
                'users' => $this->getUserStats(),
                'files' => $this->getFileStats(),
                'storage' => $this->getStorageStats(),
                'logs' => $this->getLogStats(),
                'system' => $this->getSystemInfo(),
                'performance' => $this->getPerformanceStats()
            ];
            
            $this->logger->logAdmin('system_stats_accessed', [
                'admin_user' => $_SESSION['username'] ?? 'unknown',
                'timestamp' => date('c')
            ]);
            
            return $stats;
        } catch (Exception $e) {
            $this->logger->logError('admin_stats_error', [
                'error' => $e->getMessage(),
                'admin_user' => $_SESSION['username'] ?? 'unknown'
            ]);
            throw $e;
        }
    }
    
    /**
     * Get user statistics
     */
    private function getUserStats() {
        $users = $this->db->getUsers();
        $totalUsers = count($users['users'] ?? []);
        
        $roleStats = ['admin' => 0, 'user' => 0, 'guest' => 0];
        $activeUsers = 0;
        $recentLogins = 0;
        $cutoffTime = strtotime('-30 days');
        
        foreach ($users['users'] ?? [] as $user) {
            $roleStats[$user['role'] ?? 'user']++;
            
            if (!empty($user['last_login'])) {
                $lastLogin = strtotime($user['last_login']);
                if ($lastLogin > $cutoffTime) {
                    $activeUsers++;
                }
                if ($lastLogin > strtotime('-7 days')) {
                    $recentLogins++;
                }
            }
        }
        
        return [
            'total' => $totalUsers,
            'roles' => $roleStats,
            'active_30_days' => $activeUsers,
            'recent_logins' => $recentLogins,
            'registration_trend' => $this->getUserRegistrationTrend()
        ];
    }
    
    /**
     * Get file statistics
     */
    private function getFileStats() {
        $files = $this->db->getFiles();
        $totalFiles = count($files['files'] ?? []);
        
        $typeStats = [];
        $totalSize = 0;
        $uploadTrend = [];
        
        foreach ($files['files'] ?? [] as $file) {
            $ext = strtolower(pathinfo($file['filename'] ?? '', PATHINFO_EXTENSION));
            $typeStats[$ext] = ($typeStats[$ext] ?? 0) + 1;
            $totalSize += $file['size'] ?? 0;
        }
        
        return [
            'total' => $totalFiles,
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'types' => $typeStats,
            'upload_trend' => $this->getUploadTrend(),
            'average_size' => $totalFiles > 0 ? round($totalSize / $totalFiles) : 0
        ];
    }
    
    /**
     * Get storage statistics
     */
    private function getStorageStats() {
        $config = include __DIR__ . '/../../config.php';
        $storagePath = $config['storage_path'] ?? __DIR__ . '/../../storage';
        
        $stats = [
            'directories' => [],
            'total_used' => 0,
            'disk_space' => []
        ];
        
        $directories = ['public', 'private', 'temp', 'admin'];
        
        foreach ($directories as $dir) {
            $path = $storagePath . '/' . $dir;
            if (is_dir($path)) {
                $size = $this->getDirectorySize($path);
                $stats['directories'][$dir] = [
                    'size' => $size,
                    'size_formatted' => $this->formatBytes($size),
                    'file_count' => $this->countFilesInDirectory($path)
                ];
                $stats['total_used'] += $size;
            }
        }
        
        $stats['total_used_formatted'] = $this->formatBytes($stats['total_used']);
        
        // Get disk space information
        if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
            $freeBytes = disk_free_space($storagePath);
            $totalBytes = disk_total_space($storagePath);
            
            $stats['disk_space'] = [
                'free' => $freeBytes,
                'total' => $totalBytes,
                'used_percent' => $totalBytes > 0 ? round(($totalBytes - $freeBytes) / $totalBytes * 100, 2) : 0,
                'free_formatted' => $this->formatBytes($freeBytes),
                'total_formatted' => $this->formatBytes($totalBytes)
            ];
        }
        
        return $stats;
    }
    
    /**
     * Get log statistics
     */
    private function getLogStats() {
        return [
            'access' => $this->logAnalyzer->getLogStats('access'),
            'errors' => $this->logAnalyzer->getLogStats('errors'),
            'admin' => $this->logAnalyzer->getLogStats('admin'),
            'system' => $this->logAnalyzer->getLogStats('system'),
            'recent_errors' => $this->logAnalyzer->getRecentErrors(24), // Last 24 hours
            'top_activities' => $this->logAnalyzer->getTopActivities()
        ];
    }
    
    /**
     * Get system information
     */
    private function getSystemInfo() {
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'server_time' => date('c'),
            'uptime' => $this->getServerUptime(),
            'load_average' => $this->getLoadAverage()
        ];
    }
    
    /**
     * Get performance statistics
     */
    private function getPerformanceStats() {
        return [
            'response_times' => $this->logAnalyzer->getAverageResponseTimes(),
            'error_rate' => $this->logAnalyzer->getErrorRate(),
            'peak_usage_hours' => $this->logAnalyzer->getPeakUsageHours(),
            'bandwidth_usage' => $this->logAnalyzer->getBandwidthUsage()
        ];
    }
    
    /**
     * Manage user accounts
     */
    public function manageUser($action, $data) {
        $this->logger->logAdmin("user_management_$action", [
            'admin_user' => $_SESSION['username'] ?? 'unknown',
            'target_user' => $data['username'] ?? 'unknown',
            'action' => $action,
            'timestamp' => date('c')
        ]);
        
        switch ($action) {
            case 'create':
                return $this->userManager->createUser(
                    $data['username'],
                    $data['email'],
                    $data['password'],
                    $data['role'] ?? 'user',
                    $data
                );
                
            case 'update':
                return $this->userManager->updateUser($data['username'], $data);
                
            case 'delete':
                return $this->userManager->deleteUser($data['username']);
                
            case 'activate':
                return $this->userManager->updateUser($data['username'], ['active' => true]);
                
            case 'deactivate':
                return $this->userManager->updateUser($data['username'], ['active' => false]);
                
            case 'reset_password':
                $newPassword = $this->generateRandomPassword();
                $result = $this->userManager->updateUser($data['username'], [
                    'password' => password_hash($newPassword, PASSWORD_ARGON2ID),
                    'force_password_change' => true
                ]);
                return ['success' => $result, 'new_password' => $newPassword];
                
            default:
                throw new Exception("Unknown user management action: $action");
        }
    }
    
    /**
     * System maintenance operations
     */
    public function performMaintenance($operation, $options = []) {
        $this->logger->logAdmin("maintenance_$operation", [
            'admin_user' => $_SESSION['username'] ?? 'unknown',
            'operation' => $operation,
            'options' => $options,
            'timestamp' => date('c')
        ]);
        
        switch ($operation) {
            case 'backup_database':
                return $this->backupDatabase($options);
                
            case 'restore_database':
                return $this->restoreDatabase($options);
                
            case 'cleanup_temp_files':
                return $this->cleanupTempFiles($options);
                
            case 'rotate_logs':
                return $this->rotateLogs($options);
                
            case 'optimize_database':
                return $this->optimizeDatabase($options);
                
            case 'system_health_check':
                return $this->performHealthCheck($options);
                
            default:
                throw new Exception("Unknown maintenance operation: $operation");
        }
    }
    
    /**
     * Backup database
     */
    private function backupDatabase($options) {
        $backupPath = $options['path'] ?? __DIR__ . '/../../data/backups';
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = "$backupPath/backup_$timestamp.json";
        
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }
        
        $backup = [
            'timestamp' => date('c'),
            'version' => '1.0',
            'data' => [
                'users' => $this->db->getUsers(),
                'files' => $this->db->getFiles(),
                'sessions' => $this->db->getSessions(),
                'settings' => $this->db->getSettings()
            ]
        ];
        
        $result = file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT));
        
        if ($result !== false) {
            $this->logger->logSystem('database_backup_created', [
                'backup_file' => $backupFile,
                'size' => $result
            ]);
            return ['success' => true, 'backup_file' => $backupFile, 'size' => $result];
        } else {
            throw new Exception("Failed to create backup file: $backupFile");
        }
    }
    
    /**
     * Cleanup temporary files
     */
    private function cleanupTempFiles($options) {
        $config = include __DIR__ . '/../../config.php';
        $tempPath = $config['storage_path'] . '/temp';
        $maxAge = $options['max_age'] ?? 86400; // 24 hours default
        
        $cleaned = 0;
        $freedSpace = 0;
        
        if (is_dir($tempPath)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tempPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($files as $file) {
                if ($file->isFile() && (time() - $file->getMTime()) > $maxAge) {
                    $size = $file->getSize();
                    if (unlink($file->getPathname())) {
                        $cleaned++;
                        $freedSpace += $size;
                    }
                }
            }
        }
        
        $this->logger->logSystem('temp_cleanup_completed', [
            'files_cleaned' => $cleaned,
            'space_freed' => $freedSpace,
            'space_freed_formatted' => $this->formatBytes($freedSpace)
        ]);
        
        return [
            'success' => true,
            'files_cleaned' => $cleaned,
            'space_freed' => $freedSpace,
            'space_freed_formatted' => $this->formatBytes($freedSpace)
        ];
    }
    
    /**
     * Get security alerts
     */
    public function getSecurityAlerts() {
        $alerts = [];
        
        // Check for failed login attempts
        $failedLogins = $this->logAnalyzer->getFailedLoginAttempts(24);
        if ($failedLogins > 10) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "High number of failed login attempts: $failedLogins in last 24 hours",
                'severity' => 'medium'
            ];
        }
        
        // Check for unusual file access patterns
        $suspiciousAccess = $this->logAnalyzer->getSuspiciousFileAccess();
        if (!empty($suspiciousAccess)) {
            $alerts[] = [
                'type' => 'security',
                'message' => "Suspicious file access patterns detected",
                'severity' => 'high',
                'details' => $suspiciousAccess
            ];
        }
        
        // Check disk space
        $storageStats = $this->getStorageStats();
        if (isset($storageStats['disk_space']['used_percent']) && $storageStats['disk_space']['used_percent'] > 90) {
            $alerts[] = [
                'type' => 'system',
                'message' => "Disk space critically low: {$storageStats['disk_space']['used_percent']}% used",
                'severity' => 'high'
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Helper methods
     */
    private function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = log($size, 1024);
        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
    }
    
    private function getDirectorySize($directory) {
        $size = 0;
        if (is_dir($directory)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }
        return $size;
    }
    
    private function countFilesInDirectory($directory) {
        $count = 0;
        if (is_dir($directory)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        }
        return $count;
    }
    
    private function getUserRegistrationTrend() {
        // Implementation for user registration trend analysis
        $users = $this->db->getUsers();
        $trend = [];
        
        foreach ($users['users'] ?? [] as $user) {
            if (!empty($user['created'])) {
                $month = date('Y-m', strtotime($user['created']));
                $trend[$month] = ($trend[$month] ?? 0) + 1;
            }
        }
        
        return $trend;
    }
    
    private function getUploadTrend() {
        // Implementation for file upload trend analysis
        $files = $this->db->getFiles();
        $trend = [];
        
        foreach ($files['files'] ?? [] as $file) {
            if (!empty($file['uploaded'])) {
                $month = date('Y-m', strtotime($file['uploaded']));
                $trend[$month] = ($trend[$month] ?? 0) + 1;
            }
        }
        
        return $trend;
    }
    
    private function generateRandomPassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        return substr(str_shuffle($chars), 0, $length);
    }
    
    private function getServerUptime() {
        if (function_exists('sys_getloadavg') && is_readable('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            return floatval(explode(' ', $uptime)[0]);
        }
        return null;
    }
    
    private function getLoadAverage() {
        if (function_exists('sys_getloadavg')) {
            return sys_getloadavg();
        }
        return null;
    }
    
    private function rotateLogs($options) {
        // Implementation for log rotation
        $maxSize = $options['max_size'] ?? 10485760; // 10MB
        $keepFiles = $options['keep_files'] ?? 5;
        
        $logPath = __DIR__ . '/../../data/logs';
        $rotated = 0;
        
        if (is_dir($logPath)) {
            $logFiles = glob($logPath . '/*.json');
            foreach ($logFiles as $logFile) {
                if (filesize($logFile) > $maxSize) {
                    $this->rotateLogFile($logFile, $keepFiles);
                    $rotated++;
                }
            }
        }
        
        return ['success' => true, 'rotated_files' => $rotated];
    }
    
    private function rotateLogFile($logFile, $keepFiles) {
        $baseFile = pathinfo($logFile, PATHINFO_FILENAME);
        $dir = pathinfo($logFile, PATHINFO_DIRNAME);
        
        // Rotate existing files
        for ($i = $keepFiles - 1; $i >= 1; $i--) {
            $oldFile = "$dir/$baseFile.$i.json";
            $newFile = "$dir/$baseFile." . ($i + 1) . ".json";
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }
        
        // Move current file to .1
        rename($logFile, "$dir/$baseFile.1.json");
        
        // Create new empty log file
        file_put_contents($logFile, json_encode(['logs' => []], JSON_PRETTY_PRINT));
    }
    
    private function optimizeDatabase($options) {
        // Clean up old sessions, expired data, etc.
        $cleaned = 0;
        
        // Clean expired sessions
        $sessions = $this->db->getSessions();
        $now = time();
        $newSessions = ['sessions' => []];
        
        foreach ($sessions['sessions'] ?? [] as $sessionId => $session) {
            if (isset($session['expires']) && strtotime($session['expires']) > $now) {
                $newSessions['sessions'][$sessionId] = $session;
            } else {
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            $this->db->updateSessions($newSessions);
        }
        
        return ['success' => true, 'cleaned_sessions' => $cleaned];
    }
    
    private function performHealthCheck($options) {
        $checks = [];
        
        // Check database files
        $dbFiles = ['users.json', 'files.json', 'sessions.json', 'settings.json'];
        foreach ($dbFiles as $file) {
            $path = __DIR__ . "/../../data/$file";
            $checks["db_$file"] = [
                'status' => file_exists($path) && is_readable($path) ? 'ok' : 'error',
                'writable' => is_writable($path),
                'size' => file_exists($path) ? filesize($path) : 0
            ];
        }
        
        // Check storage directories
        $config = include __DIR__ . '/../../config.php';
        $storagePath = $config['storage_path'] ?? __DIR__ . '/../../storage';
        $storageDirectories = ['public', 'private', 'temp', 'admin'];
        
        foreach ($storageDirectories as $dir) {
            $path = "$storagePath/$dir";
            $checks["storage_$dir"] = [
                'status' => is_dir($path) && is_writable($path) ? 'ok' : 'error',
                'exists' => is_dir($path),
                'writable' => is_writable($path)
            ];
        }
        
        // Check PHP configuration
        $checks['php_config'] = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'json_extension' => extension_loaded('json') ? 'ok' : 'error'
        ];
        
        return ['success' => true, 'checks' => $checks];
    }
}
