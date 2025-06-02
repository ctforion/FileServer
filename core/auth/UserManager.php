<?php
/**
 * User Manager
 * Complete user lifecycle management system
 */

require_once __DIR__ . '/../database/DatabaseManager.php';
require_once __DIR__ . '/../logging/Logger.php';

class UserManager {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->logger = new Logger();
    }
    
    /**
     * Create new user
     */
    public function createUser($username, $email, $password, $role = 'user', $options = []) {
        try {
            // Validate input
            $this->validateUserData($username, $email, $password);
            
            // Check if user already exists
            if ($this->getUserByUsername($username)) {
                throw new Exception("Username already exists: $username");
            }
            
            if ($this->getUserByEmail($email)) {
                throw new Exception("Email already exists: $email");
            }
            
            // Prepare user data
            $userData = array_merge([
                'username' => $username,
                'email' => $email,
                'password' => $password, // Will be hashed by DatabaseManager
                'role' => $role,
                'status' => 'active',
                'quota' => $this->getDefaultQuota($role),
                'settings' => [
                    'theme' => 'default',
                    'language' => 'en',
                    'timezone' => 'UTC',
                    'notifications' => true
                ],
                'permissions' => $this->getDefaultPermissions($role),
                'profile' => [
                    'first_name' => $options['first_name'] ?? '',
                    'last_name' => $options['last_name'] ?? '',
                    'avatar' => null
                ]
            ], $options);
            
            // Create user in database
            $success = $this->db->createUser($username, $userData);
            
            if ($success) {
                $this->logger->logAdmin('user_created', $username, [
                    'role' => $role,
                    'email' => $email,
                    'created_by' => $this->getCurrentUser()
                ]);
                
                return [
                    'success' => true,
                    'message' => 'User created successfully',
                    'user' => $this->getUserByUsername($username)
                ];
            } else {
                throw new Exception("Failed to create user");
            }
            
        } catch (Exception $e) {
            $this->logger->logError("User creation failed", [
                'username' => $username,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get user by username
     */
    public function getUserByUsername($username) {
        return $this->db->getUser($username);
    }
    
    /**
     * Get user by email
     */
    public function getUserByEmail($email) {
        $users = $this->db->getAllUsers();
        foreach ($users as $user) {
            if ($user['email'] === $email) {
                return $user;
            }
        }
        return null;
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        $users = $this->db->getAllUsers();
        foreach ($users as $user) {
            if ($user['id'] === $userId) {
                return $user;
            }
        }
        return null;
    }

    /**
     * Get user by username or ID (API compatibility method)
     */
    public function getUser($identifier) {
        // First try to get by username
        $user = $this->getUserByUsername($identifier);
        if ($user) {
            return $user;
        }
        
        // If not found, try to get by ID
        return $this->getUserById($identifier);
    }

    /**
     * Update user
     */
    public function updateUser($username, $data) {
        try {
            $currentUser = $this->getUserByUsername($username);
            if (!$currentUser) {
                throw new Exception("User not found: $username");
            }
            
            // Validate email if being changed
            if (isset($data['email']) && $data['email'] !== $currentUser['email']) {
                if ($this->getUserByEmail($data['email'])) {
                    throw new Exception("Email already exists: " . $data['email']);
                }
            }
            
            // Handle password change
            if (isset($data['password'])) {
                $data['password_new'] = $data['password'];
                unset($data['password']);
            }
            
            // Update user
            $success = $this->db->updateUser($username, $data);
            
            if ($success) {
                $this->logger->logAdmin('user_updated', $username, [
                    'fields_updated' => array_keys($data),
                    'updated_by' => $this->getCurrentUser()
                ]);
                
                return [
                    'success' => true,
                    'message' => 'User updated successfully',
                    'user' => $this->getUserByUsername($username)
                ];
            } else {
                throw new Exception("Failed to update user");
            }
            
        } catch (Exception $e) {
            $this->logger->logError("User update failed", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete user
     */
    public function deleteUser($username) {
        try {
            if ($username === 'admin') {
                throw new Exception("Cannot delete admin user");
            }
            
            $user = $this->getUserByUsername($username);
            if (!$user) {
                throw new Exception("User not found: $username");
            }
            
            // Delete user files
            $this->deleteUserFiles($username);
            
            // Delete user sessions
            $this->deleteUserSessions($username);
            
            // Delete user from database
            $success = $this->db->deleteUser($username);
            
            if ($success) {
                $this->logger->logAdmin('user_deleted', $username, [
                    'deleted_by' => $this->getCurrentUser()
                ]);
                
                return [
                    'success' => true,
                    'message' => 'User deleted successfully'
                ];
            } else {
                throw new Exception("Failed to delete user");
            }
            
        } catch (Exception $e) {
            $this->logger->logError("User deletion failed", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Authenticate user
     */
    public function authenticateUser($username, $password) {
        try {
            $user = $this->db->authenticateUser($username, $password);
            
            if ($user) {
                $this->logger->logAuth('login', $username, true);
                
                return [
                    'success' => true,
                    'user' => $user
                ];
            } else {
                $this->logger->logAuth('login', $username, false, [
                    'reason' => 'invalid_credentials'
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Invalid credentials'
                ];
            }
            
        } catch (Exception $e) {
            $this->logger->logError("Authentication error", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Authentication failed'
            ];
        }
    }
    
    /**
     * Change user password
     */
    public function changePassword($username, $currentPassword, $newPassword) {
        try {            // Verify current password
            $user = $this->getUserByUsername($username);
            if (!$user) {
                throw new Exception("User not found");
            }
            
            // Check both plain text and hashed passwords for backward compatibility
            $currentPasswordValid = false;
            if (isset($user['password']) && $user['password'] === $currentPassword) {
                // Plain text password check
                $currentPasswordValid = true;
            } elseif (isset($user['password_hash']) && password_verify($currentPassword, $user['password_hash'])) {
                // Legacy hashed password check
                $currentPasswordValid = true;
            }
            
            if (!$currentPasswordValid) {
                throw new Exception("Current password is incorrect");
            }
              // Validate new password
            $this->validatePassword($newPassword);
            
            // Update password
            $success = $this->db->updateUser($username, [
                'password' => $newPassword // Store as plain text
            ]);
            
            if ($success) {
                $this->logger->logAuth('password_change', $username, true);
                
                return [
                    'success' => true,
                    'message' => 'Password changed successfully'
                ];
            } else {
                throw new Exception("Failed to update password");
            }
            
        } catch (Exception $e) {
            $this->logger->logAuth('password_change', $username, false, [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Reset user password (admin only)
     */
    public function resetPassword($username, $newPassword = null) {
        try {
            if (!$this->isAdmin()) {
                throw new Exception("Admin access required");
            }
            
            $user = $this->getUserByUsername($username);
            if (!$user) {
                throw new Exception("User not found: $username");
            }
            
            // Generate random password if not provided
            if ($newPassword === null) {
                $newPassword = $this->generatePassword();
            }
              // Update password
            $success = $this->db->updateUser($username, [
                'password' => $newPassword // Store as plain text
            ]);
            
            if ($success) {
                $this->logger->logAdmin('password_reset', $username, [
                    'reset_by' => $this->getCurrentUser()
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Password reset successfully',
                    'new_password' => $newPassword
                ];
            } else {
                throw new Exception("Failed to reset password");
            }
            
        } catch (Exception $e) {
            $this->logger->logError("Password reset failed", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get all users (admin only)
     */
    public function getAllUsers($includeStats = false) {
        if (!$this->isAdmin()) {
            return [
                'success' => false,
                'message' => 'Admin access required'
            ];
        }
        
        $users = $this->db->getAllUsers();
        
        if ($includeStats) {
            foreach ($users as $username => &$user) {
                $user['stats'] = $this->getUserStats($username);
            }
        }
        
        return [
            'success' => true,
            'users' => $users
        ];
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats($username) {
        $files = $this->db->getUserFiles($username);
        $sessions = $this->db->getUserSessions($username);
        
        $totalSize = 0;
        $fileCount = count($files);
        
        foreach ($files as $file) {
            $totalSize += $file['size'] ?? 0;
        }
        
        return [
            'file_count' => $fileCount,
            'total_size' => $totalSize,
            'quota_used' => $totalSize,
            'active_sessions' => count($sessions),
            'last_login' => $this->getUserByUsername($username)['last_login'] ?? null
        ];
    }
    
    /**
     * Check user permissions
     */
    public function hasPermission($username, $permission) {
        $user = $this->getUserByUsername($username);
        if (!$user) {
            return false;
        }
        
        // Admin has all permissions
        if ($user['role'] === 'admin') {
            return true;
        }
        
        $permissions = $user['permissions'] ?? [];
        return in_array($permission, $permissions);
    }
    
    /**
     * Update user permissions
     */
    public function updatePermissions($username, $permissions) {
        if (!$this->isAdmin()) {
            return [
                'success' => false,
                'message' => 'Admin access required'
            ];
        }
        
        return $this->updateUser($username, [
            'permissions' => $permissions
        ]);
    }
    
    /**
     * Get user files
     */
    public function getUserFiles($username) {
        return $this->db->getUserFiles($username);
    }
    
    /**
     * Update user profile
     */
    public function updateUserProfile($username, $data) {
        try {
            $user = $this->getUserByUsername($username);
            if (!$user) {
                throw new Exception("User not found: $username");
            }
            
            // Merge profile data
            if (isset($data['profile'])) {
                $currentProfile = $user['profile'] ?? [];
                $data['profile'] = array_merge($currentProfile, $data['profile']);
            }
            
            $success = $this->db->updateUser($username, $data);
            
            if ($success) {
                $this->logger->logAccess('profile_updated', [
                    'username' => $username,
                    'fields' => array_keys($data)
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Profile updated successfully'
                ];
            } else {
                throw new Exception("Failed to update profile");
            }
            
        } catch (Exception $e) {
            $this->logger->logError("Profile update failed", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update user settings
     */
    public function updateUserSettings($username, $settings) {
        try {
            $user = $this->getUserByUsername($username);
            if (!$user) {
                throw new Exception("User not found: $username");
            }
            
            // Merge settings data
            $currentSettings = $user['settings'] ?? [];
            $newSettings = array_merge($currentSettings, $settings);
            
            $success = $this->db->updateUser($username, ['settings' => $newSettings]);
            
            if ($success) {
                $this->logger->logAccess('settings_updated', [
                    'username' => $username,
                    'settings' => array_keys($settings)
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Settings updated successfully'
                ];
            } else {
                throw new Exception("Failed to update settings");
            }
            
        } catch (Exception $e) {
            $this->logger->logError("Settings update failed", [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate user data
     */
    private function validateUserData($username, $email, $password) {
        // Username validation
        if (empty($username) || strlen($username) < 3) {
            throw new Exception("Username must be at least 3 characters long");
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            throw new Exception("Username can only contain letters, numbers, and underscores");
        }
        
        // Email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address");
        }
        
        // Password validation
        $this->validatePassword($password);
    }
    
    /**
     * Validate password
     */
    private function validatePassword($password) {
        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters long");
        }
        
        // Add more password complexity rules here if needed
    }
    
    /**
     * Get default quota for role
     */
    private function getDefaultQuota($role) {
        $quotas = [
            'admin' => 1073741824, // 1GB
            'user' => 104857600,   // 100MB
            'guest' => 10485760    // 10MB
        ];
        
        return $quotas[$role] ?? $quotas['user'];
    }
    
    /**
     * Get default permissions for role
     */
    private function getDefaultPermissions($role) {
        $permissions = [
            'admin' => ['*'], // All permissions
            'user' => ['upload', 'download', 'delete_own', 'view_own'],
            'guest' => ['download', 'view_public']
        ];
        
        return $permissions[$role] ?? $permissions['user'];
    }
    
    /**
     * Generate random password
     */
    private function generatePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        return substr(str_shuffle($chars), 0, $length);
    }
    
    /**
     * Delete user files
     */
    private function deleteUserFiles($username) {
        $files = $this->db->getUserFiles($username);
        
        foreach ($files as $fileId => $file) {
            // Delete physical file
            $filePath = __DIR__ . '/../../storage/' . $file['path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Delete from database
            $this->db->deleteFile($fileId);
        }
    }
    
    /**
     * Delete user sessions
     */
    private function deleteUserSessions($username) {
        $sessions = $this->db->getUserSessions($username);
        
        foreach ($sessions as $sessionId => $session) {
            $this->db->deleteSession($sessionId);
        }
    }
    
    /**
     * Get current user from session
     */
    private function getCurrentUser() {
        session_start();
        return $_SESSION['username'] ?? 'system';
    }
      /**
     * Check if current user is admin
     */
    private function isAdmin() {
        $currentUser = $this->getCurrentUser();
        if ($currentUser === 'system') {
            return true; // System operations
        }
          $user = $this->getUserByUsername($currentUser);
        return $user && $user['role'] === 'admin';
    }
}