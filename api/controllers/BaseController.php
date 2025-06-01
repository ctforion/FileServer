<?php

/**
 * BaseController
 * 
 * Base class for all API controllers providing common functionality
 * for response formatting, input handling, and error management.
 */
class BaseController {
    protected $request;
    protected $params;
    
    public function __construct() {
        // Common initialization
    }
    
    /**
     * Set request and parameters
     */
    public function setRequest($request, $params = []) {
        $this->request = $request;
        $this->params = $params;
    }
    
    /**
     * Send success response
     */
    protected function success($data = null, $message = null, $status = 200) {
        $response = ['success' => true];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return $this->respond($response, $status);
    }
    
    /**
     * Send error response
     */
    protected function error($message, $status = 400, $errors = null) {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if ($errors) {
            $response['errors'] = $errors;
        }
        
        return $this->respond($response, $status);
    }
    
    /**
     * Send formatted response
     */
    protected function respond($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Get JSON input from request body
     */
    protected function getJsonInput() {
        if (isset($this->request['body'])) {
            return $this->request['body'];
        }
        
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }
    
    /**
     * Validate required fields
     */
    protected function validateRequired($data, $required) {
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }
    
    /**
     * Sanitize input data
     */
    protected function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        if (is_string($data)) {
            return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
        }
        
        return $data;
    }
    
    /**
     * Get current user from global context
     */
    protected function getCurrentUser() {
        return $GLOBALS['current_user'] ?? null;
    }
    
    /**
     * Check if user has permission
     */
    protected function hasPermission($permission) {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        $auth = new Auth();
        return $auth->hasPermission($permission);
    }
    
    /**
     * Paginate results
     */
    protected function paginate($query, $params = [], $page = 1, $limit = 20) {
        $page = max(1, (int)$page);
        $limit = max(1, min(100, (int)$limit));
        $offset = ($page - 1) * $limit;
        
        // Get total count
        $countQuery = preg_replace('/SELECT.*?FROM/is', 'SELECT COUNT(*) as total FROM', $query, 1);
        $total = Database::getInstance()->query($countQuery, $params)->fetch()['total'];
        
        // Get paginated results
        $paginatedQuery = $query . " LIMIT $limit OFFSET $offset";
        $results = Database::getInstance()->query($paginatedQuery, $params)->fetchAll();
        
        return [
            'data' => $results,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit),
                'has_next' => ($page * $limit) < $total,
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Format file size
     */
    protected function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Generate secure random string
     */
    protected function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Validate email address
     */
    protected function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate password strength
     */
    protected function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Log activity for audit trail
     */
    protected function logActivity($action, $details = null, $user_id = null) {
        $user_id = $user_id ?: ($this->getCurrentUser()['id'] ?? null);
        
        Database::getInstance()->query("
            INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ", [
            $user_id,
            $action,
            $details ? json_encode($details) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
}
