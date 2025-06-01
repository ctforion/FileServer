<?php
namespace App\Controllers;

require_once __DIR__ . '/../../core/database/Database.php';

/**
 * User Controller
 * Handles user management API endpoints
 */
class UserController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * List users (admin/moderator only)
     */
    public function list($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'moderator'])) {
            return ['error' => 'Insufficient privileges', 'code' => 'INSUFFICIENT_PRIVILEGES'];
        }
        
        $query = $request['query'];
        $page = (int)($query['page'] ?? 1);
        $limit = min((int)($query['limit'] ?? 20), 100);
        $search = $query['search'] ?? '';
        $role = $query['role'] ?? '';
        $status = $query['status'] ?? '';
        
        $sql = "SELECT id, email, first_name, last_name, role, email_verified_at, 
                       two_factor_enabled, last_login_at, created_at 
                FROM users WHERE 1=1";
        $params_sql = [];
        
        if ($search) {
            $sql .= " AND (email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
            $searchPattern = "%$search%";
            $params_sql[] = $searchPattern;
            $params_sql[] = $searchPattern;
            $params_sql[] = $searchPattern;
        }
        
        if ($role) {
            $sql .= " AND role = ?";
            $params_sql[] = $role;
        }
        
        if ($status === 'verified') {
            $sql .= " AND email_verified_at IS NOT NULL";
        } elseif ($status === 'unverified') {
            $sql .= " AND email_verified_at IS NULL";
        }
        
        // Count total
        $countSql = str_replace('SELECT id, email, first_name, last_name, role, email_verified_at, two_factor_enabled, last_login_at, created_at', 'SELECT COUNT(*)', $sql);
        $total = $this->db->query($countSql, $params_sql)->fetchColumn();
        
        // Add ordering and pagination
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params_sql[] = $limit;
        $params_sql[] = ($page - 1) * $limit;
        
        $users = $this->db->query($sql, $params_sql)->fetchAll(PDO::FETCH_ASSOC);
        
        // Add file counts for each user
        foreach ($users as &$userData) {
            $fileCount = $this->db->query(
                "SELECT COUNT(*) FROM files WHERE user_id = ? AND deleted_at IS NULL",
                [$userData['id']]
            )->fetchColumn();
            
            $storageUsed = $this->db->query(
                "SELECT COALESCE(SUM(file_size), 0) FROM files WHERE user_id = ? AND deleted_at IS NULL",
                [$userData['id']]
            )->fetchColumn();
            
            $userData['file_count'] = (int)$fileCount;
            $userData['storage_used'] = (int)$storageUsed;
        }
        
        return [
            'success' => true,
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Get user details
     */
    public function get($request, $params) {
        $currentUser = $GLOBALS['current_user'] ?? null;
        if (!$currentUser) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $userId = $params['id'];
        
        // Users can only view their own profile unless they're admin/moderator
        if ($currentUser['id'] != $userId && !in_array($currentUser['role'], ['admin', 'moderator'])) {
            return ['error' => 'Insufficient privileges', 'code' => 'INSUFFICIENT_PRIVILEGES'];
        }
        
        $userData = $this->db->query(
            "SELECT id, email, first_name, last_name, role, email_verified_at, 
                    two_factor_enabled, last_login_at, created_at, updated_at 
             FROM users WHERE id = ?",
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
        
        if (!$userData) {
            return ['error' => 'User not found', 'code' => 'USER_NOT_FOUND'];
        }
        
        // Add file statistics
        $fileStats = $this->db->query(
            "SELECT 
                COUNT(*) as total_files,
                COALESCE(SUM(file_size), 0) as storage_used,
                COUNT(CASE WHEN is_public = 1 THEN 1 END) as public_files
             FROM files 
             WHERE user_id = ? AND deleted_at IS NULL",
            [$userId]
        )->fetch(PDO::FETCH_ASSOC);
        
        $userData['file_stats'] = $fileStats;
        
        return [
            'success' => true,
            'user' => $userData
        ];
    }
    
    /**
     * Update user
     */
    public function update($request, $params) {
        $currentUser = $GLOBALS['current_user'] ?? null;
        if (!$currentUser) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $userId = $params['id'];
        $data = $request['body'];
        
        // Users can only update their own profile unless they're admin
        if ($currentUser['id'] != $userId && $currentUser['role'] !== 'admin') {
            return ['error' => 'Insufficient privileges', 'code' => 'INSUFFICIENT_PRIVILEGES'];
        }
        
        $allowedFields = ['first_name', 'last_name', 'email'];
        
        // Only admins can update role
        if ($currentUser['role'] === 'admin' && isset($data['role'])) {
            $allowedFields[] = 'role';
        }
        
        $updateFields = [];
        $updateValues = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = ?";
                $updateValues[] = $data[$field];
            }
        }
        
        if (empty($updateFields)) {
            return ['error' => 'No valid fields to update', 'code' => 'NO_FIELDS'];
        }
        
        $updateValues[] = $userId;
        
        // Check if email is already taken
        if (isset($data['email'])) {
            $existingUser = $this->db->query(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                [$data['email'], $userId]
            )->fetch();
            
            if ($existingUser) {
                return ['error' => 'Email already in use', 'code' => 'EMAIL_EXISTS'];
            }
        }
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
        
        try {
            $this->db->query($sql, $updateValues);
            
            return [
                'success' => true,
                'message' => 'User updated successfully'
            ];
        } catch (Exception $e) {
            return ['error' => 'Failed to update user', 'code' => 'UPDATE_FAILED'];
        }
    }
    
    /**
     * Delete user (admin only)
     */
    public function delete($request, $params) {
        $currentUser = $GLOBALS['current_user'] ?? null;
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            return ['error' => 'Admin privileges required', 'code' => 'INSUFFICIENT_PRIVILEGES'];
        }
        
        $userId = $params['id'];
        
        // Prevent deletion of own account
        if ($currentUser['id'] == $userId) {
            return ['error' => 'Cannot delete own account', 'code' => 'SELF_DELETE'];
        }
        
        $this->db->beginTransaction();
        
        try {
            // Mark user files as deleted
            $this->db->query(
                "UPDATE files SET deleted_at = NOW() WHERE user_id = ?",
                [$userId]
            );
            
            // Delete user sessions
            $this->db->query("DELETE FROM sessions WHERE user_id = ?", [$userId]);
            
            // Delete file shares
            $this->db->query("DELETE FROM file_shares WHERE shared_with_user_id = ?", [$userId]);
            
            // Delete the user
            $this->db->query("DELETE FROM users WHERE id = ?", [$userId]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'User deleted successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['error' => 'Failed to delete user', 'code' => 'DELETE_FAILED'];
        }
    }
    
    /**
     * Get user files
     */
    public function files($request, $params) {
        $currentUser = $GLOBALS['current_user'] ?? null;
        if (!$currentUser) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $userId = $params['id'];
        
        // Users can only view their own files unless they're admin/moderator
        if ($currentUser['id'] != $userId && !in_array($currentUser['role'], ['admin', 'moderator'])) {
            return ['error' => 'Insufficient privileges', 'code' => 'INSUFFICIENT_PRIVILEGES'];
        }
        
        $query = $request['query'];
        $page = (int)($query['page'] ?? 1);
        $limit = min((int)($query['limit'] ?? 20), 100);
        
        $sql = "SELECT * FROM files WHERE user_id = ? AND deleted_at IS NULL";
        $params_sql = [$userId];
        
        // Count total
        $total = $this->db->query(
            "SELECT COUNT(*) FROM files WHERE user_id = ? AND deleted_at IS NULL",
            [$userId]
        )->fetchColumn();
        
        // Add ordering and pagination
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params_sql[] = $limit;
        $params_sql[] = ($page - 1) * $limit;
        
        $files = $this->db->query($sql, $params_sql)->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'files' => $files,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Update user role (admin only)
     */
    public function updateRole($request, $params) {
        $currentUser = $GLOBALS['current_user'] ?? null;
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            return ['error' => 'Admin privileges required', 'code' => 'INSUFFICIENT_PRIVILEGES'];
        }
        
        $userId = $params['id'];
        $newRole = $request['body']['role'] ?? '';
        
        if (!in_array($newRole, ['user', 'moderator', 'admin'])) {
            return ['error' => 'Invalid role', 'code' => 'INVALID_ROLE'];
        }
        
        // Prevent changing own role
        if ($currentUser['id'] == $userId) {
            return ['error' => 'Cannot change own role', 'code' => 'SELF_ROLE_CHANGE'];
        }
        
        try {
            $this->db->query(
                "UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?",
                [$newRole, $userId]
            );
            
            return [
                'success' => true,
                'message' => 'User role updated successfully'
            ];
            
        } catch (Exception $e) {
            return ['error' => 'Failed to update role', 'code' => 'ROLE_UPDATE_FAILED'];
        }
    }
}
?>
