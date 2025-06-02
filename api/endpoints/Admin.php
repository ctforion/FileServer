<?php
/**
 * Admin API Endpoint
 * Separated admin functionality
 */

require_once dirname(__DIR__, 2) . '/core/auth/UserManager.php';
require_once dirname(__DIR__, 2) . '/core/logging/Logger.php';
require_once dirname(__DIR__, 2) . '/core/logging/LogAnalyzer.php';
require_once dirname(__DIR__, 2) . '/core/storage/MetadataManager.php';
require_once dirname(__DIR__, 2) . '/core/utils/SecurityManager.php';
require_once dirname(__DIR__) . '/core/ApiResponse.php';

class AdminEndpoint {
    private $userManager;
    private $logger;
    private $logAnalyzer;
    private $metadata;
    private $security;
    
    public function __construct() {
        $this->userManager = new UserManager();
        $this->logger = new Logger();
        $this->logAnalyzer = new LogAnalyzer();
        $this->metadata = new MetadataManager();
        $this->security = new SecurityManager();
    }
    
    /**
     * Get system statistics
     */
    public function handleAdminStats($params, $user) {
        try {
            $stats = [
                'users' => [
                    'total' => count($this->userManager->getAllUsers()),
                    'active' => count(array_filter($this->userManager->getAllUsers(), function($u) {
                        return $u['status'] === 'active';
                    })),
                    'admins' => count(array_filter($this->userManager->getAllUsers(), function($u) {
                        return $u['role'] === 'admin';
                    }))
                ],
                'files' => [
                    'total' => $this->metadata->getTotalFileCount(),
                    'total_size' => $this->metadata->getTotalStorageUsed(),
                    'total_size_human' => $this->formatFileSize($this->metadata->getTotalStorageUsed())
                ],
                'storage' => [
                    'disk_free' => disk_free_space(ApiConfig::getStoragePath()),
                    'disk_total' => disk_total_space(ApiConfig::getStoragePath()),
                    'disk_used' => disk_total_space(ApiConfig::getStoragePath()) - disk_free_space(ApiConfig::getStoragePath())
                ],
                'system' => [
                    'php_version' => PHP_VERSION,
                    'server_time' => date('c'),
                    'uptime' => $this->getSystemUptime(),
                    'load' => sys_getloadavg()
                ]
            ];
            
            // Calculate disk usage percentage
            $stats['storage']['disk_usage_percent'] = $stats['storage']['disk_total'] > 0 
                ? round(($stats['storage']['disk_used'] / $stats['storage']['disk_total']) * 100, 2) 
                : 0;
            
            // Format storage sizes
            $stats['storage']['disk_free_human'] = $this->formatFileSize($stats['storage']['disk_free']);
            $stats['storage']['disk_total_human'] = $this->formatFileSize($stats['storage']['disk_total']);
            $stats['storage']['disk_used_human'] = $this->formatFileSize($stats['storage']['disk_used']);
            
            ApiResponse::success($stats);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get admin stats', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Failed to retrieve system statistics');
        }
    }
    
    /**
     * Get system logs
     */
    public function handleAdminLogs($params, $user) {
        $level = $_GET['level'] ?? 'all';
        $limit = min((int)($_GET['limit'] ?? 100), 1000);
        $since = $_GET['since'] ?? null;
        
        try {
            $logs = $this->logAnalyzer->getRecentLogs($limit, $level, $since);
            
            $response = [
                'logs' => $logs,
                'total' => count($logs),
                'filters' => [
                    'level' => $level,
                    'limit' => $limit,
                    'since' => $since
                ]
            ];
            
            ApiResponse::success($response);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get logs', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Failed to retrieve logs');
        }
    }
    
    /**
     * System cleanup operations
     */
    public function handleAdminCleanup($params, $user) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $operations = $input['operations'] ?? [];
        
        if (empty($operations)) {
            ApiResponse::error('No cleanup operations specified', 400);
        }
        
        $results = [];
        
        try {
            foreach ($operations as $operation) {
                switch ($operation) {
                    case 'temp_files':
                        $results['temp_files'] = $this->cleanupTempFiles();
                        break;
                        
                    case 'old_logs':
                        $results['old_logs'] = $this->cleanupOldLogs();
                        break;
                        
                    case 'orphaned_files':
                        $results['orphaned_files'] = $this->cleanupOrphanedFiles();
                        break;
                        
                    case 'expired_sessions':
                        $results['expired_sessions'] = $this->cleanupExpiredSessions();
                        break;
                        
                    default:
                        $results[$operation] = ['success' => false, 'error' => 'Unknown operation'];
                }
            }
            
            $this->logger->info('Admin cleanup performed', [
                'operations' => $operations,
                'results' => $results,
                'admin' => $user['username']
            ]);
            
            ApiResponse::success($results, 'Cleanup operations completed');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to perform cleanup', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Failed to perform cleanup operations');
        }
    }
    
    /**
     * Get security report
     */
    public function handleSecurityReport($params, $user) {
        try {
            $report = [
                'failed_logins' => $this->security->getFailedLoginStats(),
                'rate_limits' => $this->security->getRateLimitStats(),
                'suspicious_activity' => $this->security->getSuspiciousActivity(),
                'security_settings' => [
                    'max_login_attempts' => ApiConfig::get('auth.max_login_attempts'),
                    'session_timeout' => ApiConfig::get('auth.session_timeout'),
                    'rate_limit_enabled' => true
                ]
            ];
            
            ApiResponse::success($report);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to generate security report', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Failed to generate security report');
        }
    }
    
    /**
     * Backup system data
     */
    public function handleBackup($params, $user) {
        try {
            $backupPath = ApiConfig::getDataPath() . '/backups';
            if (!is_dir($backupPath)) {
                mkdir($backupPath, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $backupFile = $backupPath . "/backup_{$timestamp}.zip";
            
            $zip = new ZipArchive();
            if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
                ApiResponse::serverError('Failed to create backup file');
            }
            
            // Add data files
            $dataPath = ApiConfig::getDataPath();
            $files = glob($dataPath . '/*.json');
            
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            
            // Add logs
            $logFiles = glob($dataPath . '/logs/*.log');
            foreach ($logFiles as $file) {
                $zip->addFile($file, 'logs/' . basename($file));
            }
            
            $zip->close();
            
            $this->logger->info('System backup created', [
                'backup_file' => $backupFile,
                'admin' => $user['username']
            ]);
            
            ApiResponse::success([
                'backup_file' => basename($backupFile),
                'size' => filesize($backupFile),
                'size_human' => $this->formatFileSize(filesize($backupFile)),
                'created_at' => date('c')
            ], 'Backup created successfully');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to create backup', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Failed to create backup');
        }
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanupTempFiles() {
        $tempPath = ApiConfig::getStoragePath() . '/temp';
        $cleaned = 0;
        $errors = 0;
        
        if (is_dir($tempPath)) {
            $files = glob($tempPath . '/*');
            $cutoff = time() - 86400; // 24 hours
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    if (unlink($file)) {
                        $cleaned++;
                    } else {
                        $errors++;
                    }
                }
            }
        }
        
        return ['success' => true, 'cleaned' => $cleaned, 'errors' => $errors];
    }
    
    /**
     * Clean up old log files
     */
    private function cleanupOldLogs() {
        $logPath = ApiConfig::getDataPath() . '/logs';
        $cleaned = 0;
        $errors = 0;
        
        if (is_dir($logPath)) {
            $files = glob($logPath . '/*.log');
            $cutoff = time() - (30 * 86400); // 30 days
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    if (unlink($file)) {
                        $cleaned++;
                    } else {
                        $errors++;
                    }
                }
            }
        }
        
        return ['success' => true, 'cleaned' => $cleaned, 'errors' => $errors];
    }
    
    /**
     * Clean up orphaned files
     */
    private function cleanupOrphanedFiles() {
        // This would check for files in storage that don't have metadata entries
        // Implementation depends on your file storage structure
        return ['success' => true, 'cleaned' => 0, 'message' => 'Orphaned file cleanup not yet implemented'];
    }
    
    /**
     * Clean up expired sessions
     */
    private function cleanupExpiredSessions() {
        $sessionPath = session_save_path();
        $cleaned = 0;
        $errors = 0;
        
        if (is_dir($sessionPath)) {
            $files = glob($sessionPath . '/sess_*');
            $timeout = ApiConfig::get('auth.session_timeout', 7200);
            $cutoff = time() - $timeout;
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    if (unlink($file)) {
                        $cleaned++;
                    } else {
                        $errors++;
                    }
                }
            }
        }
        
        return ['success' => true, 'cleaned' => $cleaned, 'errors' => $errors];
    }
    
    /**
     * Format file size in human readable format
     */
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
    
    /**
     * Get system uptime (placeholder)
     */
    private function getSystemUptime() {
        // This is a placeholder - actual implementation would depend on the system
        return 'N/A';
    }
}
