<?php
/**
 * AdminManager - Administrative Management System
 * 
 * Handles admin-level functionality including:
 * - User management and administration
 * - System monitoring and statistics
 * - File management and cleanup
 * - Settings and configuration management
 * - System maintenance tasks
 */

class AdminManager {
    private $db;
    private $config;
    private $logger;
    private $userManager;
    private $fileManager;

    public function __construct($database, $config, $userManager, $fileManager) {
        $this->db = $database;
        $this->config = $config;
        $this->logger = new Logger($database);
        $this->userManager = $userManager;
        $this->fileManager = $fileManager;
    }

    /**
     * Check if user has admin privileges
     */
    public function isAdmin($userId) {
        $stmt = $this->db->prepare("
            SELECT role FROM users WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user && $user['role'] === 'admin';
    }

    /**
     * Get system dashboard statistics
     */
    public function getDashboardStats() {
        try {
            $stats = [];

            // User statistics
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_users_week,
                    SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_users_month
                FROM users 
                WHERE deleted_at IS NULL
            ");
            $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // File statistics
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_files,
                    SUM(file_size) as total_storage,
                    AVG(file_size) as avg_file_size,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as files_today
                FROM files 
                WHERE deleted_at IS NULL
            ");
            $stats['files'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Share statistics
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_shares,
                    SUM(download_count) as total_downloads,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_shares,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as shares_week
                FROM shares
            ");
            $stats['shares'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // System statistics
            $stats['system'] = [
                'php_version' => PHP_VERSION,
                'server_time' => date('Y-m-d H:i:s'),
                'disk_free_space' => disk_free_space($this->config['UPLOAD_PATH']),
                'disk_total_space' => disk_total_space($this->config['UPLOAD_PATH']),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true)
            ];

            // Recent activity
            $stmt = $this->db->prepare("
                SELECT action, COUNT(*) as count
                FROM logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY action
                ORDER BY count DESC
                LIMIT 10
            ");
            $stmt->execute();
            $stats['recent_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $stats;

        } catch (Exception $e) {
            $this->logger->log('admin_dashboard_error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get all users with pagination and filters
     */
    public function getUsers($page = 1, $limit = 20, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $where = ["deleted_at IS NULL"];
            $params = [];

            // Apply filters
            if (!empty($filters['search'])) {
                $where[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            if (!empty($filters['role'])) {
                $where[] = "role = ?";
                $params[] = $filters['role'];
            }

            if (isset($filters['is_active'])) {
                $where[] = "is_active = ?";
                $params[] = $filters['is_active'];
            }

            if (isset($filters['email_verified'])) {
                $where[] = "email_verified = ?";
                $params[] = $filters['email_verified'];
            }

            $whereClause = implode(' AND ', $where);

            // Get users
            $sql = "
                SELECT user_id, username, email, full_name, role, is_active, 
                       email_verified, created_at, last_login, storage_used, storage_limit,
                       failed_login_attempts, locked_until
                FROM users 
                WHERE $whereClause
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM users WHERE $whereClause";
            $countParams = array_slice($params, 0, -2); // Remove limit and offset

            $stmt = $this->db->prepare($countSql);
            $stmt->execute($countParams);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'users' => $users,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit)
            ];

        } catch (Exception $e) {
            $this->logger->log('admin_get_users_error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update user by admin
     */
    public function updateUser($userId, $updates, $adminId) {
        try {
            $allowedFields = ['username', 'email', 'full_name', 'role', 'is_active', 
                            'email_verified', 'storage_limit'];
            $setClause = [];
            $values = [];

            foreach ($updates as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    if ($field === 'username' || $field === 'email') {
                        // Check uniqueness
                        if ($this->isFieldTaken($field, $value, $userId)) {
                            throw new Exception(ucfirst($field) . ' is already taken');
                        }
                    }
                    $setClause[] = "$field = ?";
                    $values[] = $value;
                }
            }

            if (empty($setClause)) {
                throw new Exception('No valid fields to update');
            }

            $values[] = $userId;
            $sql = "UPDATE users SET " . implode(', ', $setClause) . " WHERE user_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            $this->logger->log('admin_user_updated', [
                'target_user_id' => $userId,
                'admin_id' => $adminId,
                'updates' => array_keys($updates)
            ], $adminId);

            return true;

        } catch (Exception $e) {
            $this->logger->log('admin_user_update_error', [
                'target_user_id' => $userId,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ], $adminId);
            throw $e;
        }
    }

    /**
     * Delete user (soft delete)
     */
    public function deleteUser($userId, $adminId) {
        try {
            // Prevent self-deletion
            if ($userId == $adminId) {
                throw new Exception('Cannot delete your own account');
            }

            // Check if user exists
            $user = $this->userManager->getUserById($userId);
            if (!$user) {
                throw new Exception('User not found');
            }

            // Soft delete user
            $stmt = $this->db->prepare("
                UPDATE users 
                SET deleted_at = NOW(), is_active = 0
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);

            // Mark user files as deleted
            $stmt = $this->db->prepare("
                UPDATE files 
                SET deleted_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);

            // Deactivate user shares
            $stmt = $this->db->prepare("
                UPDATE shares 
                SET is_active = 0, deleted_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);

            $this->logger->log('admin_user_deleted', [
                'target_user_id' => $userId,
                'username' => $user['username'],
                'admin_id' => $adminId
            ], $adminId);

            return true;

        } catch (Exception $e) {
            $this->logger->log('admin_user_delete_error', [
                'target_user_id' => $userId,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ], $adminId);
            throw $e;
        }
    }

    /**
     * Get all files with pagination and filters
     */
    public function getFiles($page = 1, $limit = 20, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $where = ["f.deleted_at IS NULL"];
            $params = [];

            // Apply filters
            if (!empty($filters['search'])) {
                $where[] = "f.original_name LIKE ?";
                $params[] = '%' . $filters['search'] . '%';
            }

            if (!empty($filters['user_id'])) {
                $where[] = "f.user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['mime_type'])) {
                $where[] = "f.mime_type LIKE ?";
                $params[] = $filters['mime_type'] . '%';
            }

            $whereClause = implode(' AND ', $where);

            // Get files
            $sql = "
                SELECT f.*, u.username
                FROM files f
                JOIN users u ON f.user_id = u.user_id
                WHERE $whereClause
                ORDER BY f.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "
                SELECT COUNT(*) as total 
                FROM files f
                JOIN users u ON f.user_id = u.user_id
                WHERE $whereClause
            ";
            $countParams = array_slice($params, 0, -2);

            $stmt = $this->db->prepare($countSql);
            $stmt->execute($countParams);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'files' => $files,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit)
            ];

        } catch (Exception $e) {
            $this->logger->log('admin_get_files_error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete file (admin)
     */
    public function deleteFile($fileId, $adminId) {
        try {
            // Get file info
            $stmt = $this->db->prepare("
                SELECT f.*, u.username
                FROM files f
                JOIN users u ON f.user_id = u.user_id
                WHERE f.file_id = ?
            ");
            $stmt->execute([$fileId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$file) {
                throw new Exception('File not found');
            }

            // Delete file using FileManager
            $this->fileManager->deleteFile($fileId, $file['user_id'], true); // Force delete

            $this->logger->log('admin_file_deleted', [
                'file_id' => $fileId,
                'filename' => $file['original_name'],
                'owner' => $file['username'],
                'admin_id' => $adminId
            ], $adminId);

            return true;

        } catch (Exception $e) {
            $this->logger->log('admin_file_delete_error', [
                'file_id' => $fileId,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ], $adminId);
            throw $e;
        }
    }

    /**
     * Get system logs with pagination and filters
     */
    public function getLogs($page = 1, $limit = 50, $filters = []) {
        try {
            $offset = ($page - 1) * $limit;
            $where = ["1=1"];
            $params = [];

            // Apply filters
            if (!empty($filters['action'])) {
                $where[] = "l.action = ?";
                $params[] = $filters['action'];
            }

            if (!empty($filters['user_id'])) {
                $where[] = "l.user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['date_from'])) {
                $where[] = "l.created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "l.created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }

            $whereClause = implode(' AND ', $where);

            // Get logs
            $sql = "
                SELECT l.*, u.username
                FROM logs l
                LEFT JOIN users u ON l.user_id = u.user_id
                WHERE $whereClause
                ORDER BY l.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON data
            foreach ($logs as &$log) {
                $log['data'] = json_decode($log['data'], true);
            }

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM logs l WHERE $whereClause";
            $countParams = array_slice($params, 0, -2);

            $stmt = $this->db->prepare($countSql);
            $stmt->execute($countParams);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'logs' => $logs,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit)
            ];

        } catch (Exception $e) {
            $this->logger->log('admin_get_logs_error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get system settings
     */
    public function getSettings() {
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings");
            $settings = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            return $settings;

        } catch (Exception $e) {
            $this->logger->log('admin_get_settings_error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update system settings
     */
    public function updateSettings($settings, $adminId) {
        try {
            foreach ($settings as $key => $value) {
                $stmt = $this->db->prepare("
                    INSERT INTO settings (setting_key, setting_value, updated_at)
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_at = VALUES(updated_at)
                ");
                $stmt->execute([$key, $value]);
            }

            $this->logger->log('admin_settings_updated', [
                'admin_id' => $adminId,
                'settings' => array_keys($settings)
            ], $adminId);

            return true;

        } catch (Exception $e) {
            $this->logger->log('admin_settings_update_error', [
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ], $adminId);
            throw $e;
        }
    }

    /**
     * System maintenance tasks
     */
    public function runMaintenance($tasks, $adminId) {
        try {
            $results = [];

            if (in_array('cleanup_expired_shares', $tasks)) {
                $shareManager = new ShareManager($this->db, $this->config);
                $results['expired_shares'] = $shareManager->cleanupExpiredShares();
            }

            if (in_array('cleanup_expired_sessions', $tasks)) {
                $results['expired_sessions'] = $this->cleanupExpiredSessions();
            }

            if (in_array('cleanup_temp_files', $tasks)) {
                $results['temp_files'] = $this->cleanupTempFiles();
            }

            if (in_array('update_storage_usage', $tasks)) {
                $results['storage_update'] = $this->updateAllStorageUsage();
            }

            if (in_array('cleanup_old_logs', $tasks)) {
                $results['old_logs'] = $this->cleanupOldLogs();
            }

            $this->logger->log('admin_maintenance_run', [
                'admin_id' => $adminId,
                'tasks' => $tasks,
                'results' => $results
            ], $adminId);

            return $results;

        } catch (Exception $e) {
            $this->logger->log('admin_maintenance_error', [
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ], $adminId);
            throw $e;
        }
    }

    /**
     * Private helper methods
     */
    private function isFieldTaken($field, $value, $excludeUserId = null) {
        $sql = "SELECT user_id FROM users WHERE $field = ? AND deleted_at IS NULL";
        $params = [$value];

        if ($excludeUserId) {
            $sql .= " AND user_id != ?";
            $params[] = $excludeUserId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    private function cleanupExpiredSessions() {
        $stmt = $this->db->prepare("
            UPDATE sessions 
            SET is_active = 0 
            WHERE expires_at < NOW() AND is_active = 1
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function cleanupTempFiles() {
        $tempDir = $this->config['UPLOAD_PATH'] . '/temp';
        $count = 0;

        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < strtotime('-1 hour')) {
                    unlink($file);
                    $count++;
                }
            }
        }

        return $count;
    }

    private function updateAllStorageUsage() {
        $stmt = $this->db->prepare("
            UPDATE users u
            SET storage_used = (
                SELECT COALESCE(SUM(f.file_size), 0)
                FROM files f
                WHERE f.user_id = u.user_id AND f.deleted_at IS NULL
            )
            WHERE u.deleted_at IS NULL
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function cleanupOldLogs($days = 90) {
        $stmt = $this->db->prepare("
            DELETE FROM logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}
