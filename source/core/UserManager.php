<?php
/**
 * UserManager - User Management System
 * 
 * Handles user-related functionality including:
 * - User registration and authentication
 * - Profile management
 * - User settings and preferences
 * - Password management and security
 * - User roles and permissions
 */

class UserManager {
    private $db;
    private $config;
    private $logger;

    public function __construct($database, $config) {
        $this->db = $database;
        $this->config = $config;
        $this->logger = new Logger($database);
    }

    /**
     * Register a new user
     */
    public function registerUser($username, $email, $password, $fullName = null) {
        try {
            // Validate input
            $this->validateRegistrationData($username, $email, $password);

            // Check if user already exists
            if ($this->userExists($username, $email)) {
                throw new Exception('Username or email already exists');
            }

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Generate verification token
            $verificationToken = bin2hex(random_bytes(32));

            $stmt = $this->db->prepare("
                INSERT INTO users (
                    username, email, password_hash, full_name, 
                    verification_token, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $username, $email, $passwordHash, $fullName, $verificationToken
            ]);

            $userId = $this->db->lastInsertId();

            // Create user storage directory
            $this->createUserStorage($userId);

            $this->logger->log('user_registered', [
                'user_id' => $userId,
                'username' => $username,
                'email' => $email
            ]);

            return [
                'user_id' => $userId,
                'verification_token' => $verificationToken
            ];

        } catch (Exception $e) {
            $this->logger->log('registration_failed', [
                'username' => $username,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Authenticate user login
     */
    public function authenticateUser($username, $password, $rememberMe = false) {
        try {
            // Get user by username or email
            $stmt = $this->db->prepare("
                SELECT user_id, username, email, password_hash, role, is_active, 
                       email_verified, two_factor_enabled, failed_login_attempts,
                       last_failed_login, locked_until
                FROM users 
                WHERE (username = ? OR email = ?) AND deleted_at IS NULL
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception('Invalid credentials');
            }

            // Check if account is locked
            if ($this->isAccountLocked($user)) {
                throw new Exception('Account is temporarily locked due to multiple failed login attempts');
            }

            // Check if account is active
            if (!$user['is_active']) {
                throw new Exception('Account is deactivated');
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->handleFailedLogin($user['user_id']);
                throw new Exception('Invalid credentials');
            }

            // Reset failed login attempts
            $this->resetFailedLoginAttempts($user['user_id']);

            // Create session
            $sessionToken = $this->createSession($user['user_id'], $rememberMe);

            // Update last login
            $this->updateLastLogin($user['user_id']);

            $this->logger->log('user_login', [
                'user_id' => $user['user_id'],
                'username' => $user['username']
            ], $user['user_id']);

            return [
                'user' => [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'email_verified' => $user['email_verified']
                ],
                'session_token' => $sessionToken,
                'two_factor_required' => $user['two_factor_enabled']
            ];

        } catch (Exception $e) {
            $this->logger->log('login_failed', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        $stmt = $this->db->prepare("
            SELECT user_id, username, email, full_name, role, is_active, 
                   email_verified, created_at, last_login, storage_used, storage_limit
            FROM users 
            WHERE user_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update user profile
     */
    public function updateProfile($userId, $updates) {
        try {
            $allowedFields = ['full_name', 'email'];
            $setClause = [];
            $values = [];

            foreach ($updates as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    if ($field === 'email') {
                        // Check if email is already in use
                        if ($this->emailExists($value, $userId)) {
                            throw new Exception('Email already in use');
                        }
                        $setClause[] = "email = ?, email_verified = 0";
                        $values[] = $value;
                    } else {
                        $setClause[] = "$field = ?";
                        $values[] = $value;
                    }
                }
            }

            if (empty($setClause)) {
                throw new Exception('No valid fields to update');
            }

            $values[] = $userId;
            $sql = "UPDATE users SET " . implode(', ', $setClause) . " WHERE user_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            $this->logger->log('profile_updated', [
                'user_id' => $userId,
                'fields' => array_keys($updates)
            ], $userId);

            return true;

        } catch (Exception $e) {
            $this->logger->log('profile_update_failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ], $userId);
            throw $e;
        }
    }

    /**
     * Change user password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Get current password hash
            $stmt = $this->db->prepare("
                SELECT password_hash FROM users WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                throw new Exception('Current password is incorrect');
            }

            // Validate new password
            $this->validatePassword($newPassword);

            // Hash new password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update password
            $stmt = $this->db->prepare("
                UPDATE users 
                SET password_hash = ?, password_changed_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$newPasswordHash, $userId]);

            // Invalidate all sessions except current
            $this->invalidateOtherSessions($userId);

            $this->logger->log('password_changed', ['user_id' => $userId], $userId);

            return true;

        } catch (Exception $e) {
            $this->logger->log('password_change_failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ], $userId);
            throw $e;
        }
    }

    /**
     * Reset password via email
     */
    public function requestPasswordReset($email) {
        try {
            $stmt = $this->db->prepare("
                SELECT user_id, username FROM users 
                WHERE email = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // Don't reveal if email exists
                return true;
            }

            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $this->db->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                token = VALUES(token), 
                expires_at = VALUES(expires_at),
                created_at = VALUES(created_at)
            ");
            $stmt->execute([$user['user_id'], $resetToken, $expiresAt]);

            $this->logger->log('password_reset_requested', [
                'user_id' => $user['user_id'],
                'email' => $email
            ], $user['user_id']);

            // Return token for email sending (implement email sending separately)
            return [
                'reset_token' => $resetToken,
                'username' => $user['username'],
                'email' => $email
            ];

        } catch (Exception $e) {
            $this->logger->log('password_reset_request_failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Complete password reset
     */
    public function resetPassword($token, $newPassword) {
        try {
            // Validate token
            $stmt = $this->db->prepare("
                SELECT pr.user_id, u.username
                FROM password_resets pr
                JOIN users u ON pr.user_id = u.user_id
                WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reset) {
                throw new Exception('Invalid or expired reset token');
            }

            // Validate new password
            $this->validatePassword($newPassword);

            // Hash new password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update password
            $stmt = $this->db->prepare("
                UPDATE users 
                SET password_hash = ?, password_changed_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$passwordHash, $reset['user_id']]);

            // Mark reset token as used
            $stmt = $this->db->prepare("
                UPDATE password_resets 
                SET used_at = NOW() 
                WHERE token = ?
            ");
            $stmt->execute([$token]);

            // Invalidate all sessions
            $this->invalidateAllSessions($reset['user_id']);

            $this->logger->log('password_reset_completed', [
                'user_id' => $reset['user_id']
            ], $reset['user_id']);

            return true;

        } catch (Exception $e) {
            $this->logger->log('password_reset_failed', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get user storage usage
     */
    public function getStorageUsage($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(SUM(file_size), 0) as used_storage,
                COUNT(*) as file_count
            FROM files 
            WHERE user_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$userId]);
        $usage = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT storage_limit FROM users WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'used_storage' => (int)$usage['used_storage'],
            'storage_limit' => (int)$user['storage_limit'],
            'file_count' => (int)$usage['file_count'],
            'percentage_used' => $user['storage_limit'] > 0 ? 
                round(($usage['used_storage'] / $user['storage_limit']) * 100, 2) : 0
        ];
    }

    /**
     * Update storage usage
     */
    public function updateStorageUsage($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET storage_used = (
                SELECT COALESCE(SUM(file_size), 0) 
                FROM files 
                WHERE user_id = ? AND deleted_at IS NULL
            )
            WHERE user_id = ?
        ");
        $stmt->execute([$userId, $userId]);
    }

    /**
     * Private helper methods
     */
    private function validateRegistrationData($username, $email, $password) {
        if (strlen($username) < 3 || strlen($username) > 50) {
            throw new Exception('Username must be between 3 and 50 characters');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            throw new Exception('Username can only contain letters, numbers, underscores, and hyphens');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }

        $this->validatePassword($password);
    }

    private function validatePassword($password) {
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }

        if (!preg_match('/[A-Z]/', $password)) {
            throw new Exception('Password must contain at least one uppercase letter');
        }

        if (!preg_match('/[a-z]/', $password)) {
            throw new Exception('Password must contain at least one lowercase letter');
        }

        if (!preg_match('/[0-9]/', $password)) {
            throw new Exception('Password must contain at least one number');
        }
    }

    private function userExists($username, $email) {
        $stmt = $this->db->prepare("
            SELECT user_id FROM users 
            WHERE (username = ? OR email = ?) AND deleted_at IS NULL
        ");
        $stmt->execute([$username, $email]);
        return $stmt->fetch() !== false;
    }

    private function emailExists($email, $excludeUserId = null) {
        $sql = "SELECT user_id FROM users WHERE email = ? AND deleted_at IS NULL";
        $params = [$email];

        if ($excludeUserId) {
            $sql .= " AND user_id != ?";
            $params[] = $excludeUserId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    private function createUserStorage($userId) {
        $uploadDir = $this->config['UPLOAD_PATH'] . '/users/' . $userId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
    }

    private function isAccountLocked($user) {
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return true;
        }
        return false;
    }

    private function handleFailedLogin($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1,
                last_failed_login = NOW(),
                locked_until = CASE 
                    WHEN failed_login_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                    ELSE locked_until
                END
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
    }

    private function resetFailedLoginAttempts($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_login_attempts = 0, 
                locked_until = NULL 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
    }

    private function createSession($userId, $rememberMe = false) {
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = $rememberMe ? 
            date('Y-m-d H:i:s', strtotime('+30 days')) : 
            date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $this->db->prepare("
            INSERT INTO sessions (user_id, session_token, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $sessionToken, $expiresAt]);

        return $sessionToken;
    }

    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("
            UPDATE users SET last_login = NOW() WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
    }

    private function invalidateOtherSessions($userId) {
        $currentSession = $_SESSION['session_token'] ?? null;
        
        $sql = "UPDATE sessions SET is_active = 0 WHERE user_id = ?";
        $params = [$userId];

        if ($currentSession) {
            $sql .= " AND session_token != ?";
            $params[] = $currentSession;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    private function invalidateAllSessions($userId) {
        $stmt = $this->db->prepare("
            UPDATE sessions SET is_active = 0 WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
    }
}
