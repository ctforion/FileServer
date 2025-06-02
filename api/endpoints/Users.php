<?php
/**
 * Users API Endpoint
 * Separated user management functionality
 */

require_once dirname(__DIR__, 2) . '/core/auth/UserManager.php';
require_once dirname(__DIR__, 2) . '/core/logging/Logger.php';
require_once dirname(__DIR__, 2) . '/core/utils/SecurityManager.php';
require_once dirname(__DIR__) . '/core/ApiResponse.php';

class UsersEndpoint {
    private $userManager;
    private $logger;
    private $security;
    
    public function __construct() {
        $this->userManager = new UserManager();
        $this->logger = new Logger();
        $this->security = new SecurityManager();
    }
    
    /**
     * Get all users (admin only)
     */
    public function handleGetUsers($params, $user) {
        $page = (int)($_GET['page'] ?? 1);
        $perPage = min((int)($_GET['per_page'] ?? 20), 100);
        $search = $_GET['search'] ?? '';
        
        try {
            $users = $this->userManager->getAllUsers();
            
            // Filter by search term
            if (!empty($search)) {
                $users = array_filter($users, function($u) use ($search) {
                    return stripos($u['username'], $search) !== false ||
                           stripos($u['email'], $search) !== false;
                });
            }
            
            // Remove sensitive data
            $users = array_map(function($u) {
                unset($u['password_hash']);
                return $u;
            }, $users);
            
            $total = count($users);
            $users = array_slice($users, ($page - 1) * $perPage, $perPage);
            
            ApiResponse::paginated($users, $total, $page, $perPage);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get users', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Failed to retrieve users');
        }
    }
    
    /**
     * Get single user
     */
    public function handleGetUser($params, $user) {
        $userId = $params['id'] ?? '';
        
        // Users can only view their own data unless admin
        if ($user['role'] !== 'admin' && $user['id'] !== $userId) {
            ApiResponse::forbidden('Access denied');
        }
        
        try {
            $targetUser = $this->userManager->getUser($userId);
            
            if (!$targetUser) {
                ApiResponse::notFound('User not found');
            }
            
            // Remove sensitive data
            unset($targetUser['password_hash']);
            
            ApiResponse::success($targetUser);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get user', ['user_id' => $userId, 'error' => $e->getMessage()]);
            ApiResponse::serverError('Failed to retrieve user');
        }
    }
    
    /**
     * Create new user (admin only)
     */
    public function handleCreateUser($params, $user) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        // Validate required fields
        $required = ['username', 'email', 'password'];
        $errors = [];
        
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $errors[$field] = ucfirst($field) . ' is required';
            }
        }
        
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        // Validate email format
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        // Validate password strength
        if (strlen($input['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long';
        }
        
        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }
        
        try {
            // Check if user already exists
            if ($this->userManager->getUser($input['username'])) {
                ApiResponse::error('Username already exists', 409);
            }
            
            // Create user data
            $userData = [
                'username' => $input['username'],
                'email' => $input['email'],
                'password' => $input['password'],
                'role' => $input['role'] ?? 'user',
                'quota' => $input['quota'] ?? ApiConfig::get('default_quota', 1073741824), // 1GB
                'status' => 'active'
            ];
            
            $result = $this->userManager->createUser($userData);
            
            if (!$result) {
                ApiResponse::serverError('Failed to create user');
            }
            
            $this->logger->info('User created via API', [
                'username' => $userData['username'],
                'created_by' => $user['username']
            ]);
            
            // Return user data without password
            $newUser = $this->userManager->getUser($userData['username']);
            unset($newUser['password_hash']);
            
            ApiResponse::success($newUser, 'User created successfully', 201);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to create user', ['error' => $e->getMessage()]);
            ApiResponse::serverError('Failed to create user');
        }
    }
    
    /**
     * Update user
     */
    public function handleUpdateUser($params, $user) {
        $userId = $params['id'] ?? '';
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        // Users can only update their own data unless admin
        if ($user['role'] !== 'admin' && $user['id'] !== $userId) {
            ApiResponse::forbidden('Access denied');
        }
        
        try {
            $targetUser = $this->userManager->getUser($userId);
            
            if (!targetUser) {
                ApiResponse::notFound('User not found');
            }
            
            // Build update data
            $updateData = [];
            $allowedFields = ['email', 'role', 'quota', 'status'];
            
            // Non-admin users can only update email
            if ($user['role'] !== 'admin') {
                $allowedFields = ['email'];
            }
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateData[$field] = $input[$field];
                }
            }
            
            // Handle password change
            if (isset($input['password']) && !empty($input['password'])) {
                if (strlen($input['password']) < 8) {
                    ApiResponse::validationError(['password' => 'Password must be at least 8 characters long']);
                }
                
                // Users can change their own password, or admin can change any password
                if ($user['role'] === 'admin' || $user['id'] === $userId) {
                    $updateData['password'] = $input['password'];
                } else {
                    ApiResponse::forbidden('Cannot change password for other users');
                }
            }
            
            if (empty($updateData)) {
                ApiResponse::error('No valid fields to update', 400);
            }
            
            // Validate email if provided
            if (isset($updateData['email']) && !filter_var($updateData['email'], FILTER_VALIDATE_EMAIL)) {
                ApiResponse::validationError(['email' => 'Invalid email format']);
            }
            
            $result = $this->userManager->updateUser($userId, $updateData);
            
            if (!$result) {
                ApiResponse::serverError('Failed to update user');
            }
            
            $this->logger->info('User updated via API', [
                'user_id' => $userId,
                'updated_by' => $user['username'],
                'fields' => array_keys($updateData)
            ]);
            
            // Return updated user data
            $updatedUser = $this->userManager->getUser($userId);
            unset($updatedUser['password_hash']);
            
            ApiResponse::success($updatedUser, 'User updated successfully');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to update user', ['user_id' => $userId, 'error' => $e->getMessage()]);
            ApiResponse::serverError('Failed to update user');
        }
    }
    
    /**
     * Delete user (admin only)
     */
    public function handleDeleteUser($params, $user) {
        $userId = $params['id'] ?? '';
        
        // Cannot delete self
        if ($user['id'] === $userId) {
            ApiResponse::error('Cannot delete your own account', 400);
        }
        
        try {
            $targetUser = $this->userManager->getUser($userId);
            
            if (!$targetUser) {
                ApiResponse::notFound('User not found');
            }
            
            $result = $this->userManager->deleteUser($userId);
            
            if (!$result) {
                ApiResponse::serverError('Failed to delete user');
            }
            
            $this->logger->info('User deleted via API', [
                'user_id' => $userId,
                'username' => $targetUser['username'],
                'deleted_by' => $user['username']
            ]);
            
            ApiResponse::success(null, 'User deleted successfully');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to delete user', ['user_id' => $userId, 'error' => $e->getMessage()]);
            ApiResponse::serverError('Failed to delete user');
        }
    }
    
    /**
     * Get user statistics
     */
    public function handleUserStats($params, $user) {
        $userId = $params['id'] ?? $user['id'];
        
        // Users can only view their own stats unless admin
        if ($user['role'] !== 'admin' && $user['id'] !== $userId) {
            ApiResponse::forbidden('Access denied');
        }
        
        try {
            $targetUser = $this->userManager->getUser($userId);
            
            if (!$targetUser) {
                ApiResponse::notFound('User not found');
            }
            
            // Calculate storage usage (this would typically come from FileManager)
            $storageUsed = 0; // Placeholder
            $fileCount = 0;   // Placeholder
            
            $stats = [
                'user_id' => $userId,
                'username' => $targetUser['username'],
                'storage' => [
                    'used' => $storageUsed,
                    'quota' => $targetUser['quota'],
                    'percentage' => $targetUser['quota'] > 0 ? round(($storageUsed / $targetUser['quota']) * 100, 2) : 0
                ],
                'files' => [
                    'total' => $fileCount
                ],
                'last_activity' => $targetUser['last_login'] ?? null
            ];
            
            ApiResponse::success($stats);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get user stats', ['user_id' => $userId, 'error' => $e->getMessage()]);
            ApiResponse::serverError('Failed to retrieve user statistics');
        }
    }
}
