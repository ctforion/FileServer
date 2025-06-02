<?php
/**
 * API Authentication Handler
 * Clean authentication for separated API system
 */

require_once dirname(__DIR__, 2) . '/core/auth/UserManager.php';
require_once dirname(__DIR__, 2) . '/core/utils/SecurityManager.php';
require_once dirname(__DIR__, 2) . '/core/logging/Logger.php';

class ApiAuth {
    private $userManager;
    private $security;
    private $logger;
    
    public function __construct() {
        $this->userManager = new UserManager();
        $this->security = new SecurityManager();
        $this->logger = new Logger();
    }
    
    /**
     * Authenticate API request
     * Returns user data if authenticated, false otherwise
     */
    public function authenticate($requireAuth = true) {
        // Check rate limiting first
        $rateLimitCheck = $this->security->checkRateLimit('api_auth');
        if (!$rateLimitCheck['allowed']) {
            $this->logger->warning('API rate limit exceeded', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'reason' => $rateLimitCheck['reason']
            ]);
            ApiResponse::rateLimited('Authentication rate limit exceeded: ' . $rateLimitCheck['reason']);
        }
        
        // Try different authentication methods
        $user = $this->authenticateByHeader() 
             ?: $this->authenticateBySession() 
             ?: $this->authenticateByToken();
        
        if (!$user && $requireAuth) {
            ApiResponse::unauthorized('Valid authentication required');
        }
        
        return $user;
    }
    
    /**
     * Authenticate via Authorization header
     */
    private function authenticateByHeader() {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($authHeader)) {
            return false;
        }
        
        // Handle Bearer token
        if (strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            return $this->authenticateByApiToken($token);
        }
        
        // Handle Basic auth
        if (strpos($authHeader, 'Basic ') === 0) {
            $credentials = base64_decode(substr($authHeader, 6));
            $parts = explode(':', $credentials, 2);
            
            if (count($parts) === 2) {
                return $this->authenticateByCredentials($parts[0], $parts[1]);
            }
        }
        
        return false;
    }
    
    /**
     * Authenticate via session (for web API calls)
     */
    private function authenticateBySession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Verify session hasn't expired
        $sessionTimeout = ApiConfig::get('auth.session_timeout', 7200);
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > $sessionTimeout) {
            session_destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        
        // Get user data
        $user = $this->userManager->getUser($_SESSION['username']);
        if (!$user || $user['status'] !== 'active') {
            session_destroy();
            return false;
        }
        
        return $user;
    }
    
    /**
     * Authenticate via API token parameter
     */
    private function authenticateByToken() {
        $token = $_GET['token'] ?? $_POST['token'] ?? null;
        
        if (empty($token)) {
            return false;
        }
        
        return $this->authenticateByApiToken($token);
    }
    
    /**
     * Authenticate by username and password
     */
    private function authenticateByCredentials($username, $password) {
        // Check login attempts
        $attempts = $this->security->getLoginAttempts($username);
        $maxAttempts = ApiConfig::get('auth.max_login_attempts', 5);
        
        if ($attempts >= $maxAttempts) {
            $this->logger->warning('Login blocked due to too many attempts', [
                'username' => $username,
                'attempts' => $attempts
            ]);
            return false;
        }
        
        // Verify credentials
        $user = $this->userManager->authenticateUser($username, $password);
        
        if (!$user) {
            $this->security->recordLoginAttempt($username, false);
            $this->logger->warning('Failed API login attempt', [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return false;
        }
        
        if ($user['status'] !== 'active') {
            $this->logger->warning('Login attempt with inactive account', [
                'username' => $username,
                'status' => $user['status']
            ]);
            return false;
        }
        
        // Reset login attempts on successful auth
        $this->security->recordLoginAttempt($username, true);
        
        $this->logger->info('Successful API authentication', [
            'username' => $username,
            'method' => 'credentials'
        ]);
        
        return $user;
    }
    
    /**
     * Authenticate by API token
     */
    private function authenticateByApiToken($token) {
        // For now, we'll use a simple token system
        // This could be enhanced with JWT or other token systems
        
        $users = $this->userManager->getAllUsers();
        
        foreach ($users as $user) {
            // Generate expected token (you might want to store these separately)
            $expectedToken = hash('sha256', $user['username'] . $user['password_hash']);
            
            if (hash_equals($expectedToken, $token)) {
                if ($user['status'] !== 'active') {
                    return false;
                }
                
                $this->logger->info('Successful API token authentication', [
                    'username' => $user['username'],
                    'method' => 'token'
                ]);
                
                return $user;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user has required role
     */
    public function requireRole($user, $requiredRole) {
        if (!$user) {
            ApiResponse::unauthorized();
        }
        
        $userRole = $user['role'] ?? 'user';
        
        // Role hierarchy: admin > user
        $roleHierarchy = ['admin' => 2, 'user' => 1];
        
        $userLevel = $roleHierarchy[$userRole] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 999;
        
        if ($userLevel < $requiredLevel) {
            ApiResponse::forbidden('Insufficient privileges');
        }
        
        return true;
    }
    
    /**
     * Generate API token for user
     */
    public function generateApiToken($username) {
        $user = $this->userManager->getUser($username);
        if (!$user) {
            return false;
        }
        
        return hash('sha256', $user['username'] . $user['password_hash']);
    }
    
    /**
     * Login and create session
     */
    public function login($username, $password) {
        $user = $this->authenticateByCredentials($username, $password);
        
        if (!$user) {
            return false;
        }
        
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        
        return $user;
    }
    
    /**
     * Logout and destroy session
     */
    public function logout() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        return true;
    }
}
