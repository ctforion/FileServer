<?php
/**
 * Authentication System
 * Handles user authentication, JWT tokens, and session management
 */

namespace FileServer\Core\Auth;

use FileServer\Core\Database\Database;
use Exception;

class Auth {
    private static $instance = null;
    private $db;
    private $currentUser = null;
    private $sessionId = null;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->db = Database::getInstance();
        $this->initializeSession();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize session management
     */
    private function initializeSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Configure session settings
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', HTTPS_ONLY ? 1 : 0);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', 1);
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
            
            session_start();
            $this->sessionId = session_id();
        }

        // Load current user from session
        if (isset($_SESSION['user_id'])) {
            $this->loadUser($_SESSION['user_id']);
        }

        // Update session activity
        $this->updateSessionActivity();
    }

    /**
     * Authenticate user with username/email and password
     */
    public function login(string $identifier, string $password, bool $rememberMe = false): array {
        try {
            // Clean previous failed attempts
            $this->cleanFailedAttempts();

            // Check rate limiting
            if ($this->isRateLimited($identifier)) {
                return [
                    'success' => false,
                    'message' => 'Too many failed login attempts. Please try again later.',
                    'locked_until' => $this->getLockedUntil($identifier)
                ];
            }

            // Find user by username or email
            $user = $this->findUser($identifier);
            
            if (!$user) {
                $this->recordFailedAttempt($identifier);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }

            // Check if account is locked
            if ($this->isAccountLocked($user)) {
                return [
                    'success' => false,
                    'message' => 'Account is temporarily locked due to too many failed attempts',
                    'locked_until' => $user['locked_until']
                ];
            }

            // Verify password
            if (!$this->verifyPassword($password, $user['password_hash'], $user['salt'])) {
                $this->recordFailedAttempt($identifier, $user['id']);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }

            // Check account status
            if (!$this->isAccountActive($user)) {
                return ['success' => false, 'message' => 'Account is not active'];
            }

            // Check email verification if required
            if (EMAIL_VERIFICATION_REQUIRED && !$user['email_verified']) {
                return ['success' => false, 'message' => 'Email verification required'];
            }

            // Check 2FA if enabled
            if ($user['two_factor_enabled']) {
                $_SESSION['2fa_user_id'] = $user['id'];
                return [
                    'success' => true,
                    'requires_2fa' => true,
                    'message' => 'Two-factor authentication required'
                ];
            }

            // Complete login
            $this->completeLogin($user, $rememberMe);

            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => $this->sanitizeUserData($user),
                'token' => $this->generateJWT($user)
            ];

        } catch (Exception $e) {
            $this->log("Login error: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'An error occurred during login'];
        }
    }

    /**
     * Verify 2FA token
     */
    public function verify2FA(string $token): array {
        if (!isset($_SESSION['2fa_user_id'])) {
            return ['success' => false, 'message' => 'No 2FA session found'];
        }

        $user = $this->getUserById($_SESSION['2fa_user_id']);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        if (!$this->verifyTOTP($token, $user['two_factor_secret'])) {
            return ['success' => false, 'message' => 'Invalid 2FA token'];
        }

        // Complete login
        unset($_SESSION['2fa_user_id']);
        $this->completeLogin($user, false);

        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => $this->sanitizeUserData($user),
            'token' => $this->generateJWT($user)
        ];
    }

    /**
     * Complete the login process
     */
    private function completeLogin(array $user, bool $rememberMe): void {
        // Reset failed attempts
        $this->resetFailedAttempts($user['id']);

        // Update user login info
        $this->db->update('users', [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip' => $this->getClientIP(),
            'login_attempts' => 0,
            'locked_until' => null
        ], 'id = ?', [$user['id']]);

        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();

        // Set remember me cookie if requested
        if ($rememberMe) {
            $this->setRememberMeCookie($user);
        }

        // Store session in database
        $this->storeSession($user['id']);

        // Load current user
        $this->loadUser($user['id']);

        // Log successful login
        $this->log("User {$user['username']} logged in successfully", 'info');
        $this->auditLog('user.login', 'user', $user['id'], null, [
            'username' => $user['username'],
            'ip' => $this->getClientIP()
        ]);
    }

    /**
     * Logout current user
     */
    public function logout(): bool {
        try {
            if ($this->currentUser) {
                // Log logout
                $this->log("User {$this->currentUser['username']} logged out", 'info');
                $this->auditLog('user.logout', 'user', $this->currentUser['id']);

                // Remove session from database
                $this->removeSession();

                // Clear remember me cookie
                $this->clearRememberMeCookie();
            }

            // Clear session data
            $_SESSION = [];
            session_destroy();

            $this->currentUser = null;
            return true;

        } catch (Exception $e) {
            $this->log("Logout error: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Register new user
     */
    public function register(array $userData): array {
        try {
            // Check if registration is enabled
            if (!REGISTRATION_ENABLED) {
                return ['success' => false, 'message' => 'Registration is currently disabled'];
            }

            // Validate required fields
            $required = ['username', 'email', 'password'];
            foreach ($required as $field) {
                if (empty($userData[$field])) {
                    return ['success' => false, 'message' => ucfirst($field) . ' is required'];
                }
            }

            // Validate username format
            if (!$this->isValidUsername($userData['username'])) {
                return ['success' => false, 'message' => 'Invalid username format'];
            }

            // Validate email format
            if (!$this->isValidEmail($userData['email'])) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }

            // Validate password strength
            if (!$this->isValidPassword($userData['password'])) {
                return ['success' => false, 'message' => 'Password does not meet requirements'];
            }

            // Check if username exists
            if ($this->userExists($userData['username'], 'username')) {
                return ['success' => false, 'message' => 'Username already exists'];
            }

            // Check if email exists
            if ($this->userExists($userData['email'], 'email')) {
                return ['success' => false, 'message' => 'Email already exists'];
            }

            // Create user
            $salt = bin2hex(random_bytes(16));
            $passwordHash = password_hash($userData['password'] . $salt, PASSWORD_ARGON2ID);
            
            $userRecord = [
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password_hash' => $passwordHash,
                'salt' => $salt,
                'role' => 'user',
                'status' => EMAIL_VERIFICATION_REQUIRED ? 'pending' : 'active',
                'first_name' => $userData['first_name'] ?? '',
                'last_name' => $userData['last_name'] ?? '',
                'timezone' => $userData['timezone'] ?? 'UTC',
                'language' => $userData['language'] ?? 'en',
                'storage_quota' => DEFAULT_STORAGE_QUOTA,
                'api_key' => bin2hex(random_bytes(32)),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Add email verification token if required
            if (EMAIL_VERIFICATION_REQUIRED) {
                $userRecord['email_verification_token'] = bin2hex(random_bytes(32));
            } else {
                $userRecord['email_verified'] = true;
            }

            $userId = $this->db->insert('users', $userRecord);

            if (!$userId) {
                return ['success' => false, 'message' => 'Failed to create user account'];
            }

            // Send verification email if required
            if (EMAIL_VERIFICATION_REQUIRED) {
                $this->sendVerificationEmail($userData['email'], $userRecord['email_verification_token']);
            }

            // Log registration
            $this->log("New user registered: {$userData['username']}", 'info');
            $this->auditLog('user.register', 'user', $this->db->getLastInsertId(), null, [
                'username' => $userData['username'],
                'email' => $userData['email']
            ]);

            return [
                'success' => true,
                'message' => EMAIL_VERIFICATION_REQUIRED ? 
                    'Registration successful. Please check your email for verification.' :
                    'Registration successful. You can now login.',
                'user_id' => $this->db->getLastInsertId()
            ];

        } catch (Exception $e) {
            $this->log("Registration error: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'An error occurred during registration'];
        }
    }

    /**
     * Verify email address
     */
    public function verifyEmail(string $token): bool {
        $user = $this->db->selectOne(
            "SELECT id, email FROM users WHERE email_verification_token = ? AND email_verified = FALSE",
            [$token]
        );

        if (!$user) {
            return false;
        }

        $updated = $this->db->update('users', [
            'email_verified' => true,
            'email_verification_token' => null,
            'status' => 'active',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$user['id']]);

        if ($updated) {
            $this->auditLog('user.email_verified', 'user', $user['id']);
            return true;
        }

        return false;
    }

    /**
     * Generate JWT token
     */
    public function generateJWT(array $user): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'iss' => SITE_URL,
            'aud' => SITE_URL,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRY,
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]);

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }

    /**
     * Verify JWT token
     */
    public function verifyJWT(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        // Verify signature
        $expectedSignature = hash_hmac('sha256', $header . "." . $payload, JWT_SECRET, true);
        $expectedBase64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));

        if (!hash_equals($expectedBase64Signature, $signature)) {
            return null;
        }

        // Decode payload
        $decodedPayload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);

        // Check expiration
        if ($decodedPayload['exp'] < time()) {
            return null;
        }

        return $decodedPayload;
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(string $permission, int $resourceId = null): bool {
        if (!$this->currentUser) {
            return false;
        }

        // Admin has all permissions
        if ($this->currentUser['role'] === 'admin') {
            return true;
        }

        // Check role-based permissions
        $rolePermissions = $this->getRolePermissions($this->currentUser['role']);
        if (in_array($permission, $rolePermissions)) {
            return true;
        }

        // Check resource-specific permissions
        if ($resourceId) {
            return $this->hasResourcePermission($permission, $resourceId);
        }

        return false;
    }

    /**
     * Check resource-specific permissions
     */
    private function hasResourcePermission(string $permission, int $resourceId): bool {
        $userPermission = $this->db->selectOne(
            "SELECT id FROM permissions 
             WHERE (user_id = ? OR role = ?) 
             AND file_id = ? 
             AND permission_type = ? 
             AND (expires_at IS NULL OR expires_at > NOW())",
            [$this->currentUser['id'], $this->currentUser['role'], $resourceId, $permission]
        );

        return $userPermission !== null;
    }

    /**
     * Get role permissions
     */
    private function getRolePermissions(string $role): array {
        $permissions = [
            'admin' => ['*'], // All permissions
            'moderator' => ['read', 'write', 'delete', 'share', 'manage_users'],
            'user' => ['read', 'write', 'share']
        ];

        return $permissions[$role] ?? [];
    }

    /**
     * Get current user
     */
    public function getCurrentUser(): ?array {
        return $this->currentUser;
    }

    /**
     * Load user by ID
     */
    private function loadUser(int $userId): void {
        $user = $this->getUserById($userId);
        if ($user && $user['status'] === 'active') {
            $this->currentUser = $user;
        }
    }

    /**
     * Get user by ID
     */
    private function getUserById(int $userId): ?array {
        return $this->db->selectOne("SELECT * FROM users WHERE id = ?", [$userId]);
    }

    /**
     * Find user by username or email
     */
    private function findUser(string $identifier): ?array {
        return $this->db->selectOne(
            "SELECT * FROM users WHERE username = ? OR email = ?",
            [$identifier, $identifier]
        );
    }

    /**
     * Verify password
     */
    private function verifyPassword(string $password, string $hash, string $salt): bool {
        return password_verify($password . $salt, $hash);
    }

    /**
     * Check if account is active
     */
    private function isAccountActive(array $user): bool {
        return $user['status'] === 'active';
    }

    /**
     * Check if account is locked
     */
    private function isAccountLocked(array $user): bool {
        return $user['locked_until'] && strtotime($user['locked_until']) > time();
    }

    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt(string $identifier, int $userId = null): void {
        if ($userId) {
            $attempts = ($this->db->selectOne("SELECT login_attempts FROM users WHERE id = ?", [$userId])['login_attempts'] ?? 0) + 1;
            
            $updateData = ['login_attempts' => $attempts];
            
            // Lock account if too many attempts
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $updateData['locked_until'] = date('Y-m-d H:i:s', time() + ACCOUNT_LOCK_TIME);
            }
            
            $this->db->update('users', $updateData, 'id = ?', [$userId]);
        }

        $this->auditLog('user.login_failed', null, $userId, null, [
            'identifier' => $identifier,
            'ip' => $this->getClientIP()
        ]);
    }

    /**
     * Reset failed attempts
     */
    private function resetFailedAttempts(int $userId): void {
        $this->db->update('users', [
            'login_attempts' => 0,
            'locked_until' => null
        ], 'id = ?', [$userId]);
    }

    /**
     * Additional helper methods continue...
     */

    /**
     * Store session in database
     */
    private function storeSession(int $userId): void {
        $sessionData = [
            'id' => $this->sessionId,
            'user_id' => $userId,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'payload' => serialize($_SESSION),
            'last_activity' => time(),
            'expires_at' => date('Y-m-d H:i:s', time() + SESSION_LIFETIME),
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Delete existing session if exists
        $this->db->delete('sessions', 'id = ?', [$this->sessionId]);
        
        // Insert new session
        $this->db->insert('sessions', $sessionData);
    }

    /**
     * Update session activity
     */
    private function updateSessionActivity(): void {
        if ($this->sessionId) {
            $this->db->update('sessions', [
                'last_activity' => time(),
                'payload' => serialize($_SESSION ?? [])
            ], 'id = ?', [$this->sessionId]);
        }
    }

    /**
     * Remove session from database
     */
    private function removeSession(): void {
        if ($this->sessionId) {
            $this->db->delete('sessions', 'id = ?', [$this->sessionId]);
        }
    }

    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions(): int {
        return $this->db->delete('sessions', 'expires_at < NOW()');
    }

    /**
     * Get client IP address
     */
    private function getClientIP(): string {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Sanitize user data for API responses
     */
    private function sanitizeUserData(array $user): array {
        unset($user['password_hash'], $user['salt'], $user['two_factor_secret'], $user['email_verification_token'], $user['password_reset_token']);
        return $user;
    }

    /**
     * Validation methods
     */
    private function isValidUsername(string $username): bool {
        return preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $username);
    }

    private function isValidEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function isValidPassword(string $password): bool {
        return strlen($password) >= PASSWORD_MIN_LENGTH && 
               preg_match('/[A-Z]/', $password) && 
               preg_match('/[a-z]/', $password) && 
               preg_match('/[0-9]/', $password);
    }

    private function userExists(string $value, string $field): bool {
        $user = $this->db->selectOne("SELECT id FROM users WHERE {$field} = ?", [$value]);
        return $user !== null;
    }

    /**
     * Rate limiting and security methods
     */
    private function isRateLimited(string $identifier): bool {
        // Implementation for rate limiting based on IP and identifier
        return false; // Simplified for now
    }

    private function getLockedUntil(string $identifier): ?string {
        // Get lock expiry time
        return null; // Simplified for now
    }

    private function cleanFailedAttempts(): void {
        // Clean old failed attempts
        $this->db->query("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE locked_until < NOW()");
    }

    /**
     * 2FA methods
     */
    private function verifyTOTP(string $token, string $secret): bool {
        // TOTP verification implementation
        // This is a simplified version - in production, use a proper TOTP library
        return strlen($token) === 6 && ctype_digit($token);
    }

    /**
     * Remember me functionality
     */
    private function setRememberMeCookie(array $user): void {
        $token = bin2hex(random_bytes(32));
        $expires = time() + (86400 * 30); // 30 days
        
        setcookie('remember_token', $token, $expires, '/', '', HTTPS_ONLY, true);
        
        // Store token in database (hashed)
        $this->db->update('users', [
            'remember_token' => password_hash($token, PASSWORD_DEFAULT)
        ], 'id = ?', [$user['id']]);
    }

    private function clearRememberMeCookie(): void {
        setcookie('remember_token', '', time() - 3600, '/', '', HTTPS_ONLY, true);
    }

    /**
     * Email verification
     */
    private function sendVerificationEmail(string $email, string $token): void {
        // Email sending implementation
        // This would integrate with your email system
    }

    /**
     * Logging and auditing
     */
    private function log(string $message, string $level = 'info'): void {
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            $logMessage = "[" . date('Y-m-d H:i:s') . "] [AUTH] [{$level}] {$message}" . PHP_EOL;
            $logFile = storage_path('logs' . DIRECTORY_SEPARATOR . 'auth.log');
            
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }

    private function auditLog(string $action, string $resourceType = null, int $resourceId = null, array $oldValues = null, array $newValues = null): void {
        $this->db->insert('audit_log', [
            'user_id' => $this->currentUser['id'] ?? null,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => $this->sessionId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Prevent cloning and unserialization
     */
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
    }
}
