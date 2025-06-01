<?php
/**
 * Security Manager
 * Security utilities and access control
 */

require_once __DIR__ . '/../database/DatabaseManager.php';
require_once __DIR__ . '/../logging/Logger.php';

class SecurityManager {
    private $db;
    private $logger;
    private $config;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->logger = new Logger();
        $this->config = include __DIR__ . '/../../config.php';
    }
    
    /**
     * Validate file upload
     */
    public function validateFileUpload($file, $targetPath) {
        $errors = [];
        
        try {
            // Check file size
            $maxSize = $this->parseSize($this->config['max_file_size'] ?? '50M');
            if ($file['size'] > $maxSize) {
                $errors[] = "File size exceeds maximum allowed size: " . $this->formatBytes($maxSize);
            }
            
            // Check file extension
            $allowedExtensions = $this->config['allowed_extensions'] ?? ['txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'doc', 'docx'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extension, $allowedExtensions)) {
                $errors[] = "File extension '$extension' is not allowed";
            }
            
            // Check MIME type
            $allowedMimeTypes = $this->getAllowedMimeTypes();
            $mimeType = $this->getMimeType($file['tmp_name']);
            
            if (!in_array($mimeType, $allowedMimeTypes)) {
                $errors[] = "File type '$mimeType' is not allowed";
            }
            
            // Check filename
            if (!$this->isValidFilename($file['name'])) {
                $errors[] = "Invalid filename. Use only alphanumeric characters, spaces, dots, hyphens, and underscores";
            }
            
            // Check for malicious content
            if ($this->containsMaliciousContent($file['tmp_name'])) {
                $errors[] = "File contains potentially malicious content";
            }
            
            // Check path traversal
            if (!$this->isValidPath($targetPath)) {
                $errors[] = "Invalid target path";
            }
            
            // Log validation attempt
            $this->logger->logAccess('file_upload_validation', [
                'filename' => $file['name'],
                'size' => $file['size'],
                'mime_type' => $mimeType,
                'user' => $_SESSION['username'] ?? 'anonymous',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'errors' => $errors
            ]);
            
            return [
                'valid' => empty($errors),
                'errors' => $errors,
                'file_info' => [
                    'original_name' => $file['name'],
                    'size' => $file['size'],
                    'mime_type' => $mimeType,
                    'extension' => $extension
                ]
            ];
            
        } catch (Exception $e) {
            $this->logger->logError('file_validation_error', [
                'filename' => $file['name'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return [
                'valid' => false,
                'errors' => ['Validation failed: ' . $e->getMessage()],
                'file_info' => []
            ];
        }
    }
    
    /**
     * Check file access permissions
     */
    public function checkFileAccess($fileId, $action = 'read') {
        try {
            $file = $this->db->getFile($fileId);
            if (!$file) {
                return ['allowed' => false, 'reason' => 'File not found'];
            }
            
            $user = $_SESSION['username'] ?? null;
            $userRole = $_SESSION['role'] ?? 'guest';
            
            // Admin can access everything
            if ($userRole === 'admin') {
                return ['allowed' => true, 'reason' => 'Admin access'];
            }
            
            // Check if file is public
            if ($file['public'] ?? false) {
                if ($action === 'read' || $action === 'download') {
                    return ['allowed' => true, 'reason' => 'Public file'];
                }
            }
            
            // Check if user is owner
            if ($user && ($file['owner'] ?? '') === $user) {
                return ['allowed' => true, 'reason' => 'File owner'];
            }
            
            // Check explicit permissions
            $permissions = $file['permissions'] ?? [];
            if ($user && in_array($user, $permissions)) {
                return ['allowed' => true, 'reason' => 'Explicit permission'];
            }
            
            // Check role-based permissions
            $rolePermissions = $file['role_permissions'] ?? [];
            if (in_array($userRole, $rolePermissions)) {
                return ['allowed' => true, 'reason' => 'Role permission'];
            }
            
            // Default deny
            $this->logger->logAccess('file_access_denied', [
                'file_id' => $fileId,
                'action' => $action,
                'user' => $user,
                'role' => $userRole,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            return ['allowed' => false, 'reason' => 'Access denied'];
            
        } catch (Exception $e) {
            $this->logger->logError('file_access_check_error', [
                'file_id' => $fileId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            
            return ['allowed' => false, 'reason' => 'Access check failed'];
        }
    }
      /**
     * Rate limiting with flexible parameters
     */
    public function checkRateLimit($param1, $param2 = null, $param3 = null, $param4 = null) {
        // Support both old and new signatures
        if ($param3 !== null && $param4 !== null) {
            // New signature: checkRateLimit($identifier, $action, $maxRequests, $windowSeconds)
            return $this->checkRateLimitNew($param1, $param2, $param3, $param4);
        } else {
            // Old signature: checkRateLimit($action, $identifier = null)
            return $this->checkRateLimitOld($param1, $param2);
        }
    }
    
    private function checkRateLimitNew($identifier, $action, $maxRequests, $windowSeconds) {
        try {
            $key = "rate_limit_{$action}_{$identifier}";
            
            // Get current attempts from session
            $attempts = $_SESSION[$key] ?? [];
            $now = time();
            $windowStart = $now - $windowSeconds;
            
            // Clean old attempts
            $attempts = array_filter($attempts, function($timestamp) use ($windowStart) {
                return $timestamp > $windowStart;
            });
            
            if (count($attempts) >= $maxRequests) {
                $this->logger->warning('Rate limit exceeded', [
                    'action' => $action,
                    'identifier' => $identifier,
                    'attempts' => count($attempts),
                    'limit' => $maxRequests,
                    'window' => $windowSeconds
                ]);
                return false;
            }
            
            // Add current attempt
            $attempts[] = $now;
            $_SESSION[$key] = $attempts;
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Rate limit check failed', [
                'error' => $e->getMessage(),
                'action' => $action,
                'identifier' => $identifier
            ]);
            return false;
        }
    }
    
    private function checkRateLimitOld($action, $identifier = null) {
        try {
            if (!$identifier) {
                $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            }
            
            $limits = $this->config['rate_limits'] ?? [
                'upload' => ['requests' => 10, 'window' => 3600], // 10 uploads per hour
                'download' => ['requests' => 100, 'window' => 3600], // 100 downloads per hour
                'login' => ['requests' => 5, 'window' => 900] // 5 login attempts per 15 minutes
            ];
            
            if (!isset($limits[$action])) {
                return ['allowed' => true, 'reason' => 'No rate limit configured'];
            }
            
            $limit = $limits[$action];
            $key = "rate_limit_{$action}_{$identifier}";
            
            // Get current attempts from session/cache
            $attempts = $_SESSION[$key] ?? [];
            $now = time();
            $windowStart = $now - $limit['window'];
            
            // Clean old attempts
            $attempts = array_filter($attempts, function($timestamp) use ($windowStart) {
                return $timestamp > $windowStart;
            });
            
            if (count($attempts) >= $limit['requests']) {
                $this->logger->logAccess('rate_limit_exceeded', [
                    'action' => $action,
                    'identifier' => $identifier,
                    'attempts' => count($attempts),
                    'limit' => $limit['requests'],
                    'window' => $limit['window']
                ]);
                
                return [
                    'allowed' => false,
                    'reason' => 'Rate limit exceeded',
                    'retry_after' => min($attempts) + $limit['window'] - $now
                ];
            }
            
            // Add current attempt
            $attempts[] = $now;
            $_SESSION[$key] = $attempts;
            
            return [
                'allowed' => true,
                'reason' => 'Within rate limit',
                'remaining' => $limit['requests'] - count($attempts)
            ];
            
        } catch (Exception $e) {
            $this->logger->logError('rate_limit_check_error', [
                'action' => $action,
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            
            // Fail open for non-critical errors
            return ['allowed' => true, 'reason' => 'Rate limit check failed'];
        }
    }
    
    /**
     * Sanitize input
     */
    public function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'string':
                return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
                
            case 'filename':
                // Remove dangerous characters
                $sanitized = preg_replace('/[^a-zA-Z0-9.\-_\s]/', '', $input);
                $sanitized = trim($sanitized);
                
                // Prevent directory traversal
                $sanitized = str_replace(['../', '..\\', './'], '', $sanitized);
                
                return $sanitized;
                
            case 'path':
                // Sanitize path components
                $parts = explode('/', $input);
                $sanitized = [];
                
                foreach ($parts as $part) {
                    $part = $this->sanitizeInput($part, 'filename');
                    if (!empty($part) && $part !== '.' && $part !== '..') {
                        $sanitized[] = $part;
                    }
                }
                
                return implode('/', $sanitized);
                
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
                
            case 'int':
                return (int) $input;
                
            case 'float':
                return (float) $input;
                
            case 'boolean':
                return filter_var($input, FILTER_VALIDATE_BOOLEAN);
                
            default:
                return $input;
        }
    }
    
    /**
     * Generate secure token
     */
    public function generateSecureToken($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        } else {
            // Fallback
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $token = '';
            for ($i = 0; $i < $length; $i++) {
                $token .= $chars[mt_rand(0, strlen($chars) - 1)];
            }
            return $token;
        }
    }
    
    /**
     * Hash password securely
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ]);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encryptData($data, $key = null) {
        if (!$key) {
            $key = $this->config['encryption_key'] ?? $this->generateSecureToken(32);
        }
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decryptData($encryptedData, $key = null) {
        if (!$key) {
            $key = $this->config['encryption_key'] ?? '';
        }
        
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Check for suspicious activity
     */
    public function detectSuspiciousActivity() {
        $alerts = [];
        
        try {
            // Check for multiple failed logins
            $failedLogins = $this->getFailedLoginAttempts(24);
            if ($failedLogins > 10) {
                $alerts[] = [
                    'type' => 'authentication',
                    'severity' => 'medium',
                    'message' => "High number of failed login attempts: $failedLogins in last 24 hours"
                ];
            }
            
            // Check for unusual file access patterns
            $suspiciousAccess = $this->detectSuspiciousFileAccess();
            if (!empty($suspiciousAccess)) {
                $alerts[] = [
                    'type' => 'file_access',
                    'severity' => 'high',
                    'message' => 'Suspicious file access patterns detected',
                    'details' => $suspiciousAccess
                ];
            }
            
            // Check for brute force attempts
            $bruteForce = $this->detectBruteForceAttempts();
            if (!empty($bruteForce)) {
                $alerts[] = [
                    'type' => 'brute_force',
                    'severity' => 'high',
                    'message' => 'Potential brute force attack detected',
                    'details' => $bruteForce
                ];
            }
            
            return $alerts;
            
        } catch (Exception $e) {
            $this->logger->logError('suspicious_activity_detection_error', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Validate session security
     */
    public function validateSession() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout
        $timeout = $this->config['session_timeout'] ?? 3600; // 1 hour default
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
            $this->logger->logAccess('session_timeout', [
                'user' => $_SESSION['username'] ?? 'unknown',
                'last_activity' => $_SESSION['last_activity']
            ]);
            session_destroy();
            return false;
        }
        
        // Check IP consistency (if enabled)
        if ($this->config['check_ip'] ?? false) {
            $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $sessionIp = $_SESSION['ip_address'] ?? '';
            
            if ($currentIp !== $sessionIp) {
                $this->logger->logAccess('session_ip_mismatch', [
                    'user' => $_SESSION['username'] ?? 'unknown',
                    'session_ip' => $sessionIp,
                    'current_ip' => $currentIp
                ]);
                session_destroy();
                return false;
            }
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            throw new Exception('CSRF token not found in session');
        }
        
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            throw new Exception('Invalid CSRF token');
        }
        
        return true;
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;    }
    
    /**
     * Validate file path
     */
    public function validateFilePath($filepath) {
        // Check for null or empty
        if (empty($filepath)) {
            return false;
        }
        
        // Check for path traversal attempts
        if (strpos($filepath, '..') !== false) {
            return false;
        }
        
        // Check for absolute paths
        if (substr($filepath, 0, 1) === '/' || strpos($filepath, ':') !== false) {
            return false;
        }
        
        // Check for invalid characters
        if (preg_match('/[<>:"|*?]/', $filepath)) {
            return false;
        }
        
        // Check for reserved names (Windows)
        $filename = basename($filepath);
        $reserved = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
        if (in_array(strtoupper(pathinfo($filename, PATHINFO_FILENAME)), $reserved)) {
            return false;
        }
        
        return true;
    }

    /**
     * Helper methods
     */
    private function parseSize($size) {
        $units = ['B' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824];
        $size = strtoupper($size);
        $unit = substr($size, -1);
        $value = (int) substr($size, 0, -1);
        
        return $value * ($units[$unit] ?? 1);
    }
    
    private function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = log($size, 1024);
        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
    }
    
    private function getAllowedMimeTypes() {
        return [
            'text/plain',
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/zip',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
    }
    
    private function getMimeType($filePath) {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        } elseif (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            return finfo_file($finfo, $filePath);
        }
        return 'application/octet-stream';
    }
    
    private function isValidFilename($filename) {
        // Check for dangerous patterns
        $dangerous = ['.php', '.asp', '.jsp', '.exe', '.bat', '.cmd', '.sh'];
        foreach ($dangerous as $ext) {
            if (stripos($filename, $ext) !== false) {
                return false;
            }
        }
        
        // Check for valid characters
        return preg_match('/^[a-zA-Z0-9.\-_\s]+$/', $filename);
    }
    
    private function isValidPath($path) {
        // Check for directory traversal
        if (strpos($path, '..') !== false) {
            return false;
        }
        
        // Check for absolute paths
        if (strpos($path, '/') === 0 || strpos($path, '\\') === 0) {
            return false;
        }
        
        // Check for drive letters (Windows)
        if (preg_match('/^[a-zA-Z]:/', $path)) {
            return false;
        }
        
        return true;
    }
    
    private function containsMaliciousContent($filePath) {
        // Basic malicious content detection
        $suspicious = ['<?php', '<%', '<script', 'eval(', 'exec(', 'system('];
        
        $handle = fopen($filePath, 'r');
        if ($handle) {
            $chunk = fread($handle, 8192); // Read first 8KB
            fclose($handle);
            
            foreach ($suspicious as $pattern) {
                if (stripos($chunk, $pattern) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function getFailedLoginAttempts($hours) {
        // Implementation would query logs for failed login attempts
        // This is a placeholder
        return 0;
    }
    
    private function detectSuspiciousFileAccess() {
        // Implementation would analyze access patterns
        // This is a placeholder
        return [];
    }
    
    private function detectBruteForceAttempts() {
        // Implementation would detect brute force patterns
        // This is a placeholder
        return [];
    }
}
