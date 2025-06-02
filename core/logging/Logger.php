<?php
/**
 * Comprehensive Logging System
 * Provides structured logging with multiple levels and targets
 */

class Logger {
    private $logPath;
    private $logLevel;
    private $maxFileSize;
    private $maxFiles;
    
    const LEVEL_DEBUG = 0;
    const LEVEL_INFO = 1;
    const LEVEL_WARNING = 2;
    const LEVEL_ERROR = 3;
    const LEVEL_CRITICAL = 4;
    
    private $levelNames = [
        self::LEVEL_DEBUG => 'DEBUG',
        self::LEVEL_INFO => 'INFO',
        self::LEVEL_WARNING => 'WARNING',
        self::LEVEL_ERROR => 'ERROR',
        self::LEVEL_CRITICAL => 'CRITICAL'
    ];
    
    public function __construct($logPath = null, $logLevel = self::LEVEL_INFO) {
        $this->logPath = $logPath ?? __DIR__ . '/../../data/logs';
        $this->logLevel = $logLevel;
        $this->maxFileSize = 10 * 1024 * 1024; // 10MB
        $this->maxFiles = 10;
        
        $this->ensureLogDirectory();
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
        
        // Create .htaccess to protect logs
        $htaccessPath = $this->logPath . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "Order Deny,Allow\nDeny from all\n");
        }
    }
    
    /**
     * Get log file path for a specific type
     */
    private function getLogFile($type) {
        return $this->logPath . '/' . $type . '.json';
    }
    
    /**
     * Rotate log file if it's too large
     */
    private function rotateLogFile($logFile) {
        if (!file_exists($logFile) || filesize($logFile) < $this->maxFileSize) {
            return;
        }
        
        // Rotate existing files
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $logFile . '.' . $i;
            $newFile = $logFile . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                if ($i >= $this->maxFiles - 1) {
                    unlink($oldFile); // Remove oldest file
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }
        
        // Move current log to .1
        rename($logFile, $logFile . '.1');
    }
    
    /**
     * Write log entry to file
     */
    private function writeLogEntry($type, $level, $message, $context = []) {
        if ($level < $this->logLevel) {
            return; // Skip logs below configured level
        }
        
        $logFile = $this->getLogFile($type);
        $this->rotateLogFile($logFile);
        
        $entry = [
            'timestamp' => date('c'),
            'level' => $this->levelNames[$level],
            'message' => $message,
            'context' => $context,
            'request_id' => $this->getRequestId(),
            'user' => $this->getCurrentUser(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'memory_usage' => memory_get_usage(true),
            'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
        ];
        
        // Load existing logs
        $logs = [];
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $logs = json_decode($content, true) ?? [];
        }
        
        // Add new entry
        $logs[] = $entry;
        
        // Save logs
        $json = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($logFile, $json);
        
        return $entry;
    }
    
    /**
     * Get unique request ID
     */
    private function getRequestId() {
        static $requestId = null;
        if ($requestId === null) {
            $requestId = bin2hex(random_bytes(8));
        }
        return $requestId;
    }
      /**
     * Get current user from session
     */
    private function getCurrentUser() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['username'] ?? 'anonymous';
    }
    
    /**
     * Access logging
     */
    public function logAccess($action, $resource, $context = []) {
        $message = "User accessed: $action on $resource";
        
        $accessContext = array_merge([
            'action' => $action,
            'resource' => $resource,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ], $context);
        
        return $this->writeLogEntry('access', self::LEVEL_INFO, $message, $accessContext);
    }
    
    /**
     * Error logging
     */
    public function logError($message, $context = []) {
        $errorContext = array_merge([
            'error_type' => 'application_error',
            'file' => $context['file'] ?? 'unknown',
            'line' => $context['line'] ?? 0,
            'trace' => $context['trace'] ?? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ], $context);
        
        return $this->writeLogEntry('errors', self::LEVEL_ERROR, $message, $errorContext);
    }
    
    /**
     * Admin activity logging
     */
    public function logAdmin($action, $target, $context = []) {
        $message = "Admin action: $action on $target";
        
        $adminContext = array_merge([
            'action' => $action,
            'target' => $target,
            'admin_user' => $this->getCurrentUser()
        ], $context);
        
        return $this->writeLogEntry('admin', self::LEVEL_INFO, $message, $adminContext);
    }
    
    /**
     * System event logging
     */
    public function logSystem($event, $context = []) {
        $message = "System event: $event";
        
        $systemContext = array_merge([
            'event' => $event,
            'system_info' => [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ]
        ], $context);
        
        return $this->writeLogEntry('system', self::LEVEL_INFO, $message, $systemContext);
    }
    
    /**
     * Debug logging
     */
    public function debug($message, $context = []) {
        return $this->writeLogEntry('system', self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Info logging
     */
    public function info($message, $context = []) {
        return $this->writeLogEntry('system', self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Warning logging
     */
    public function warning($message, $context = []) {
        return $this->writeLogEntry('system', self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Error logging (general)
     */
    public function error($message, $context = []) {
        return $this->logError($message, $context);
    }
    
    /**
     * Critical logging
     */
    public function critical($message, $context = []) {
        return $this->writeLogEntry('system', self::LEVEL_CRITICAL, $message, $context);
    }
    
    /**
     * File operation logging
     */
    public function logFileOperation($operation, $filename, $user, $context = []) {
        $message = "File operation: $operation on $filename by $user";
        
        $fileContext = array_merge([
            'operation' => $operation,
            'filename' => $filename,
            'user' => $user
        ], $context);
        
        return $this->writeLogEntry('access', self::LEVEL_INFO, $message, $fileContext);
    }
    
    /**
     * Authentication logging
     */
    public function logAuth($action, $username, $success = true, $context = []) {
        $level = $success ? self::LEVEL_INFO : self::LEVEL_WARNING;
        $message = "Authentication: $action for $username " . ($success ? 'succeeded' : 'failed');
        
        $authContext = array_merge([
            'action' => $action,
            'username' => $username,
            'success' => $success,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], $context);
        
        return $this->writeLogEntry('access', $level, $message, $authContext);
    }
    
    /**
     * Security event logging
     */
    public function logSecurity($event, $severity = 'medium', $context = []) {
        $level = $severity === 'high' ? self::LEVEL_CRITICAL : 
                ($severity === 'medium' ? self::LEVEL_WARNING : self::LEVEL_INFO);
        
        $message = "Security event: $event (severity: $severity)";
        
        $securityContext = array_merge([
            'event' => $event,
            'severity' => $severity,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], $context);
        
        return $this->writeLogEntry('errors', $level, $message, $securityContext);
    }
    
    /**
     * Get log entries
     */
    public function getLogs($type, $limit = 100, $offset = 0) {
        $logFile = $this->getLogFile($type);
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $content = file_get_contents($logFile);
        $logs = json_decode($content, true) ?? [];
        
        // Sort by timestamp (newest first)
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($logs, $offset, $limit);
    }
    
    /**
     * Search logs
     */
    public function searchLogs($type, $criteria = []) {
        $logs = $this->getLogs($type, 1000); // Get more entries for searching
        
        if (empty($criteria)) {
            return $logs;
        }
        
        return array_filter($logs, function($log) use ($criteria) {
            foreach ($criteria as $field => $value) {
                if ($field === 'message' && isset($log['message'])) {
                    if (stripos($log['message'], $value) === false) {
                        return false;
                    }
                } elseif ($field === 'level' && isset($log['level'])) {
                    if ($log['level'] !== $value) {
                        return false;
                    }
                } elseif ($field === 'user' && isset($log['user'])) {
                    if ($log['user'] !== $value) {
                        return false;
                    }
                } elseif ($field === 'date') {
                    $logDate = date('Y-m-d', strtotime($log['timestamp']));
                    if ($logDate !== $value) {
                        return false;
                    }
                }
            }
            return true;
        });
    }
    
    /**
     * Get log statistics
     */
    public function getLogStats($type) {
        $logs = $this->getLogs($type, 10000); // Get many entries for stats
        
        $stats = [
            'total_entries' => count($logs),
            'by_level' => [],
            'by_user' => [],
            'by_date' => [],
            'recent_activity' => array_slice($logs, 0, 10)
        ];
        
        foreach ($logs as $log) {
            // Count by level
            $level = $log['level'] ?? 'unknown';
            $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;
            
            // Count by user
            $user = $log['user'] ?? 'unknown';
            $stats['by_user'][$user] = ($stats['by_user'][$user] ?? 0) + 1;
            
            // Count by date
            $date = date('Y-m-d', strtotime($log['timestamp']));
            $stats['by_date'][$date] = ($stats['by_date'][$date] ?? 0) + 1;
        }
        
        return $stats;
    }
    
    /**
     * Clean old logs
     */
    public function cleanOldLogs($days = 30) {
        $cutoff = time() - ($days * 24 * 60 * 60);
        $types = ['access', 'errors', 'admin', 'system'];
        $cleaned = 0;
        
        foreach ($types as $type) {
            $logFile = $this->getLogFile($type);
            
            if (!file_exists($logFile)) {
                continue;
            }
            
            $logs = $this->getLogs($type, 10000);
            $filteredLogs = array_filter($logs, function($log) use ($cutoff) {
                return strtotime($log['timestamp']) >= $cutoff;
            });
            
            $removed = count($logs) - count($filteredLogs);
            $cleaned += $removed;
            
            if ($removed > 0) {
                $json = json_encode(array_values($filteredLogs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                file_put_contents($logFile, $json);
            }
        }
        
        return $cleaned;
    }
}
?>
