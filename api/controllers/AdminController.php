<?php
namespace App\Controllers;

require_once __DIR__ . '/../../core/database/Database.php';
require_once __DIR__ . '/../../core/auth/Auth.php';

/**
 * Admin Controller
 * Handles administrative functions and system management
 */
class AdminController {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    /**
     * Check if user has admin privileges
     */
    private function requireAdmin() {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user || $user['role'] !== 'admin') {
            return ['error' => 'Admin privileges required', 'code' => 'INSUFFICIENT_PRIVILEGES'];
        }
        return null;
    }
    
    /**
     * Get system statistics
     */
    public function stats($request, $params) {
        $adminCheck = $this->requireAdmin();
        if ($adminCheck) return $adminCheck;
        
        // User statistics
        $userStats = [
            'total' => $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'active' => $this->db->query("SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL")->fetchColumn(),
            'admins' => $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
            'moderators' => $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'moderator'")->fetchColumn(),
            'new_today' => $this->db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn()
        ];
        
        // File statistics
        $fileStats = [
            'total' => $this->db->query("SELECT COUNT(*) FROM files WHERE deleted_at IS NULL")->fetchColumn(),
            'total_size' => $this->db->query("SELECT COALESCE(SUM(file_size), 0) FROM files WHERE deleted_at IS NULL")->fetchColumn(),
            'public' => $this->db->query("SELECT COUNT(*) FROM files WHERE is_public = 1 AND deleted_at IS NULL")->fetchColumn(),
            'private' => $this->db->query("SELECT COUNT(*) FROM files WHERE is_public = 0 AND deleted_at IS NULL")->fetchColumn(),
            'uploaded_today' => $this->db->query("SELECT COUNT(*) FROM files WHERE DATE(created_at) = CURDATE() AND deleted_at IS NULL")->fetchColumn()
        ];
        
        // Storage usage by type
        $storageByType = $this->db->query("
            SELECT 
                CASE 
                    WHEN mime_type LIKE 'image/%' THEN 'images'
                    WHEN mime_type LIKE 'video/%' THEN 'videos'
                    WHEN mime_type LIKE 'audio/%' THEN 'audio'
                    WHEN mime_type LIKE 'application/pdf' OR mime_type LIKE 'text/%' THEN 'documents'
                    ELSE 'other'
                END as type,
                COUNT(*) as count,
                COALESCE(SUM(file_size), 0) as size
            FROM files 
            WHERE deleted_at IS NULL 
            GROUP BY type
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent activity
        $recentActivity = $this->db->query("
            SELECT action, user_email, ip_address, created_at
            FROM audit_log 
            ORDER BY created_at DESC 
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // System health
        $diskSpace = disk_total_space(STORAGE_DIR);
        $diskFree = disk_free_space(STORAGE_DIR);
        $diskUsed = $diskSpace - $diskFree;
        
        $systemHealth = [
            'disk_total' => $diskSpace,
            'disk_used' => $diskUsed,
            'disk_free' => $diskFree,
            'disk_usage_percent' => round(($diskUsed / $diskSpace) * 100, 2),
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_upload_size' => ini_get('upload_max_filesize'),
            'timezone' => date_default_timezone_get()
        ];
        
        return [
            'success' => true,
            'stats' => [
                'users' => $userStats,
                'files' => $fileStats,
                'storage_by_type' => $storageByType,
                'system_health' => $systemHealth
            ],
            'recent_activity' => $recentActivity
        ];
    }
    
    /**
     * Get audit log
     */
    public function auditLog($request, $params) {
        $adminCheck = $this->requireAdmin();
        if ($adminCheck) return $adminCheck;
        
        $query = $request['query'];
        $page = (int)($query['page'] ?? 1);
        $limit = min((int)($query['limit'] ?? 50), 200);
        $action = $query['action'] ?? '';
        $user = $query['user'] ?? '';
        $dateFrom = $query['date_from'] ?? '';
        $dateTo = $query['date_to'] ?? '';
        
        $sql = "SELECT * FROM audit_log WHERE 1=1";
        $params_sql = [];
        
        if ($action) {
            $sql .= " AND action = ?";
            $params_sql[] = $action;
        }
        
        if ($user) {
            $sql .= " AND user_email LIKE ?";
            $params_sql[] = "%$user%";
        }
        
        if ($dateFrom) {
            $sql .= " AND created_at >= ?";
            $params_sql[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND created_at <= ?";
            $params_sql[] = $dateTo . ' 23:59:59';
        }
        
        // Count total
        $countSql = str_replace('SELECT *', 'SELECT COUNT(*)', $sql);
        $total = $this->db->query($countSql, $params_sql)->fetchColumn();
        
        // Add ordering and pagination
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params_sql[] = $limit;
        $params_sql[] = ($page - 1) * $limit;
        
        $logs = $this->db->query($sql, $params_sql)->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Get system settings
     */
    public function getSettings($request, $params) {
        $adminCheck = $this->requireAdmin();
        if ($adminCheck) return $adminCheck;
        
        $settings = $this->db->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted = [];
        foreach ($settings as $setting) {
            $formatted[$setting['key']] = [
                'value' => $setting['value'],
                'type' => $setting['type'],
                'description' => $setting['description']
            ];
        }
        
        return [
            'success' => true,
            'settings' => $formatted
        ];
    }
    
    /**
     * Update system settings
     */
    public function updateSettings($request, $params) {
        $adminCheck = $this->requireAdmin();
        if ($adminCheck) return $adminCheck;
        
        $settings = $request['body']['settings'] ?? [];
        
        $this->db->beginTransaction();
        
        try {
            foreach ($settings as $key => $value) {
                $this->db->query(
                    "UPDATE settings SET value = ?, updated_at = NOW() WHERE `key` = ?",
                    [$value, $key]
                );
            }
            
            $this->db->commit();
            
            // Log the action
            $user = $GLOBALS['current_user'];
            $this->auth->logAction('settings_updated', $user['email'], $_SERVER['REMOTE_ADDR'], [
                'updated_settings' => array_keys($settings)
            ]);
            
            return [
                'success' => true,
                'message' => 'Settings updated successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['error' => 'Failed to update settings', 'code' => 'UPDATE_FAILED'];
        }
    }
    
    /**
     * Run maintenance tasks
     */
    public function maintenance($request, $params) {
        $adminCheck = $this->requireAdmin();
        if ($adminCheck) return $adminCheck;
        
        $task = $request['body']['task'] ?? '';
        $results = [];
        
        switch ($task) {
            case 'cleanup_deleted_files':
                $results = $this->cleanupDeletedFiles();
                break;
                
            case 'optimize_database':
                $results = $this->optimizeDatabase();
                break;
                
            case 'clear_cache':
                $results = $this->clearCache();
                break;
                
            case 'generate_thumbnails':
                $results = $this->generateMissingThumbnails();
                break;
                
            case 'vacuum_database':
                $results = $this->vacuumDatabase();
                break;
                
            default:
                return ['error' => 'Unknown maintenance task', 'code' => 'UNKNOWN_TASK'];
        }
        
        // Log the maintenance action
        $user = $GLOBALS['current_user'];
        $this->auth->logAction('maintenance_run', $user['email'], $_SERVER['REMOTE_ADDR'], [
            'task' => $task,
            'results' => $results
        ]);
        
        return [
            'success' => true,
            'task' => $task,
            'results' => $results
        ];
    }
    
    /**
     * Get system health status
     */
    public function health($request, $params) {
        $health = [
            'status' => 'healthy',
            'checks' => []
        ];
        
        // Database connection
        try {
            $this->db->query("SELECT 1")->fetch();
            $health['checks']['database'] = ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (Exception $e) {
            $health['checks']['database'] = ['status' => 'error', 'message' => 'Database connection failed'];
            $health['status'] = 'unhealthy';
        }
        
        // Storage directory
        if (is_writable(STORAGE_DIR)) {
            $health['checks']['storage'] = ['status' => 'ok', 'message' => 'Storage directory writable'];
        } else {
            $health['checks']['storage'] = ['status' => 'error', 'message' => 'Storage directory not writable'];
            $health['status'] = 'unhealthy';
        }
        
        // Disk space
        $diskFree = disk_free_space(STORAGE_DIR);
        $diskTotal = disk_total_space(STORAGE_DIR);
        $usagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;
        
        if ($usagePercent < 90) {
            $health['checks']['disk_space'] = ['status' => 'ok', 'message' => "Disk usage: {$usagePercent}%"];
        } else {
            $health['checks']['disk_space'] = ['status' => 'warning', 'message' => "High disk usage: {$usagePercent}%"];
            if ($health['status'] === 'healthy') $health['status'] = 'warning';
        }
        
        // PHP extensions
        $requiredExtensions = ['pdo', 'gd', 'openssl', 'json', 'mbstring'];
        $missingExtensions = [];
        
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }
        
        if (empty($missingExtensions)) {
            $health['checks']['php_extensions'] = ['status' => 'ok', 'message' => 'All required extensions loaded'];
        } else {
            $health['checks']['php_extensions'] = ['status' => 'error', 'message' => 'Missing extensions: ' . implode(', ', $missingExtensions)];
            $health['status'] = 'unhealthy';
        }
        
        return [
            'success' => true,
            'health' => $health
        ];
    }
    
    private function cleanupDeletedFiles() {
        $retentionDays = (int)env('DELETED_FILES_RETENTION_DAYS', 30);
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-$retentionDays days"));
        
        $files = $this->db->query(
            "SELECT id, file_path FROM files WHERE deleted_at IS NOT NULL AND deleted_at < ?",
            [$cutoffDate]
        )->fetchAll(PDO::FETCH_ASSOC);
        
        $deletedCount = 0;
        foreach ($files as $file) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
            $this->db->query("DELETE FROM files WHERE id = ?", [$file['id']]);
            $deletedCount++;
        }
        
        return ['deleted_files' => $deletedCount];
    }
    
    private function optimizeDatabase() {
        $tables = ['users', 'files', 'file_shares', 'sessions', 'audit_log', 'settings'];
        $optimized = [];
        
        foreach ($tables as $table) {
            try {
                $this->db->query("OPTIMIZE TABLE `$table`");
                $optimized[] = $table;
            } catch (Exception $e) {
                // Table optimization might not be supported in all databases
            }
        }
        
        return ['optimized_tables' => $optimized];
    }
    
    private function clearCache() {
        $cacheDir = CACHE_DIR;
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
        
        return ['cleared_files' => $cleared];
    }
    
    private function generateMissingThumbnails() {
        // This would integrate with FileManager to generate missing thumbnails
        return ['message' => 'Thumbnail generation not implemented yet'];
    }
    
    private function vacuumDatabase() {
        try {
            if ($this->db->getDriverName() === 'sqlite') {
                $this->db->query("VACUUM");
                return ['message' => 'Database vacuumed successfully'];
            } else {
                return ['message' => 'Vacuum not applicable for this database type'];
            }
        } catch (Exception $e) {
            return ['error' => 'Failed to vacuum database'];
        }
    }
}
?>
