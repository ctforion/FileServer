<?php
/**
 * Log Analyzer
 * Provides analysis and reporting for log data
 */

require_once __DIR__ . '/Logger.php';

class LogAnalyzer {
    private $logger;
    private $logPath;
    
    public function __construct($logPath = null) {
        $this->logPath = $logPath ?? __DIR__ . '/../../data/logs';
        $this->logger = new Logger($this->logPath);
    }
    
    /**
     * Get comprehensive system analytics
     */
    public function getSystemAnalytics($days = 7) {
        $analytics = [
            'overview' => $this->getOverviewStats($days),
            'user_activity' => $this->getUserActivityStats($days),
            'file_operations' => $this->getFileOperationStats($days),
            'security_events' => $this->getSecurityStats($days),
            'performance' => $this->getPerformanceStats($days),
            'errors' => $this->getErrorStats($days)
        ];
        
        return $analytics;
    }
    
    /**
     * Get overview statistics
     */
    private function getOverviewStats($days) {
        $cutoff = date('Y-m-d', strtotime("-$days days"));
        
        $accessLogs = $this->getRecentLogs('access', $days);
        $errorLogs = $this->getRecentLogs('errors', $days);
        $adminLogs = $this->getRecentLogs('admin', $days);
        
        return [
            'total_requests' => count($accessLogs),
            'total_errors' => count($errorLogs),
            'total_admin_actions' => count($adminLogs),
            'unique_users' => count($this->getUniqueUsers($accessLogs)),
            'unique_ips' => count($this->getUniqueIPs($accessLogs)),
            'date_range' => [
                'from' => $cutoff,
                'to' => date('Y-m-d')
            ]
        ];
    }
    
    /**
     * Get user activity statistics
     */
    private function getUserActivityStats($days) {
        $accessLogs = $this->getRecentLogs('access', $days);
        
        $stats = [
            'most_active_users' => [],
            'login_attempts' => [],
            'user_actions' => [],
            'hourly_activity' => array_fill(0, 24, 0),
            'daily_activity' => []
        ];
        
        $userActions = [];
        $loginAttempts = [];
        
        foreach ($accessLogs as $log) {
            $user = $log['user'] ?? 'anonymous';
            $context = $log['context'] ?? [];
            $hour = (int)date('H', strtotime($log['timestamp']));
            $date = date('Y-m-d', strtotime($log['timestamp']));
            
            // Count user actions
            $userActions[$user] = ($userActions[$user] ?? 0) + 1;
            
            // Track login attempts
            if (isset($context['action']) && in_array($context['action'], ['login', 'logout'])) {
                if (!isset($loginAttempts[$user])) {
                    $loginAttempts[$user] = ['success' => 0, 'failed' => 0];
                }
                
                $success = $context['success'] ?? true;
                $loginAttempts[$user][$success ? 'success' : 'failed']++;
            }
            
            // Hourly activity
            $stats['hourly_activity'][$hour]++;
            
            // Daily activity
            $stats['daily_activity'][$date] = ($stats['daily_activity'][$date] ?? 0) + 1;
        }
        
        // Sort and limit most active users
        arsort($userActions);
        $stats['most_active_users'] = array_slice($userActions, 0, 10, true);
        $stats['login_attempts'] = $loginAttempts;
        
        return $stats;
    }
    
    /**
     * Get file operation statistics
     */
    private function getFileOperationStats($days) {
        $accessLogs = $this->getRecentLogs('access', $days);
        
        $stats = [
            'operations' => [],
            'file_types' => [],
            'directories' => [],
            'upload_stats' => [
                'total_uploads' => 0,
                'total_size' => 0,
                'by_user' => []
            ],
            'download_stats' => [
                'total_downloads' => 0,
                'popular_files' => []
            ]
        ];
        
        foreach ($accessLogs as $log) {
            $context = $log['context'] ?? [];
            
            if (isset($context['operation'])) {
                $operation = $context['operation'];
                $stats['operations'][$operation] = ($stats['operations'][$operation] ?? 0) + 1;
                
                $filename = $context['filename'] ?? '';
                $user = $log['user'] ?? 'anonymous';
                
                if ($operation === 'upload') {
                    $stats['upload_stats']['total_uploads']++;
                    $stats['upload_stats']['by_user'][$user] = ($stats['upload_stats']['by_user'][$user] ?? 0) + 1;
                    
                    if (isset($context['size'])) {
                        $stats['upload_stats']['total_size'] += $context['size'];
                    }
                    
                    // Track file types
                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if ($extension) {
                        $stats['file_types'][$extension] = ($stats['file_types'][$extension] ?? 0) + 1;
                    }
                }
                
                if ($operation === 'download') {
                    $stats['download_stats']['total_downloads']++;
                    $stats['download_stats']['popular_files'][$filename] = 
                        ($stats['download_stats']['popular_files'][$filename] ?? 0) + 1;
                }
                
                // Track directories
                if (isset($context['directory'])) {
                    $dir = $context['directory'];
                    $stats['directories'][$dir] = ($stats['directories'][$dir] ?? 0) + 1;
                }
            }
        }
        
        // Sort popular files
        arsort($stats['download_stats']['popular_files']);
        $stats['download_stats']['popular_files'] = 
            array_slice($stats['download_stats']['popular_files'], 0, 10, true);
        
        return $stats;
    }
    
    /**
     * Get security statistics
     */
    private function getSecurityStats($days) {
        $errorLogs = $this->getRecentLogs('errors', $days);
        
        $stats = [
            'failed_logins' => [],
            'suspicious_ips' => [],
            'security_events' => [],
            'threat_level' => 'low'
        ];
        
        $failedLogins = [];
        $ipCounts = [];
        $securityEvents = [];
        
        foreach ($errorLogs as $log) {
            $context = $log['context'] ?? [];
            $ip = $log['ip'] ?? 'unknown';
            
            // Track failed logins
            if (isset($context['action']) && $context['action'] === 'login' && 
                isset($context['success']) && !$context['success']) {
                $username = $context['username'] ?? 'unknown';
                $failedLogins[$username] = ($failedLogins[$username] ?? 0) + 1;
                $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
            }
            
            // Track security events
            if (isset($context['event']) && isset($context['severity'])) {
                $event = $context['event'];
                $severity = $context['severity'];
                
                if (!isset($securityEvents[$severity])) {
                    $securityEvents[$severity] = [];
                }
                $securityEvents[$severity][$event] = ($securityEvents[$severity][$event] ?? 0) + 1;
            }
        }
        
        // Identify suspicious IPs (more than 5 failed attempts)
        $stats['suspicious_ips'] = array_filter($ipCounts, function($count) {
            return $count > 5;
        });
        
        $stats['failed_logins'] = $failedLogins;
        $stats['security_events'] = $securityEvents;
        
        // Determine threat level
        $highThreats = $securityEvents['high'] ?? [];
        $suspiciousCount = count($stats['suspicious_ips']);
        
        if (!empty($highThreats) || $suspiciousCount > 10) {
            $stats['threat_level'] = 'high';
        } elseif ($suspiciousCount > 3 || !empty($securityEvents['medium'] ?? [])) {
            $stats['threat_level'] = 'medium';
        }
        
        return $stats;
    }
    
    /**
     * Get performance statistics
     */
    private function getPerformanceStats($days) {
        $accessLogs = $this->getRecentLogs('access', $days);
        $systemLogs = $this->getRecentLogs('system', $days);
        
        $stats = [
            'response_times' => [],
            'memory_usage' => [],
            'slow_requests' => [],
            'error_rate' => 0
        ];
        
        $responseTimes = [];
        $memoryUsage = [];
        $slowRequests = [];
        $totalRequests = 0;
        $errorRequests = 0;
        
        $allLogs = array_merge($accessLogs, $systemLogs);
        
        foreach ($allLogs as $log) {
            $executionTime = $log['execution_time'] ?? 0;
            $memoryUsed = $log['memory_usage'] ?? 0;
            $level = $log['level'] ?? 'INFO';
            
            if ($executionTime > 0) {
                $responseTimes[] = $executionTime;
                $totalRequests++;
                
                if ($executionTime > 5.0) { // Slow request threshold: 5 seconds
                    $slowRequests[] = [
                        'timestamp' => $log['timestamp'],
                        'execution_time' => $executionTime,
                        'message' => $log['message'],
                        'user' => $log['user'] ?? 'unknown'
                    ];
                }
            }
            
            if ($memoryUsed > 0) {
                $memoryUsage[] = $memoryUsed;
            }
            
            if (in_array($level, ['ERROR', 'CRITICAL'])) {
                $errorRequests++;
            }
        }
        
        if (!empty($responseTimes)) {
            $stats['response_times'] = [
                'avg' => array_sum($responseTimes) / count($responseTimes),
                'min' => min($responseTimes),
                'max' => max($responseTimes),
                'median' => $this->calculateMedian($responseTimes)
            ];
        }
        
        if (!empty($memoryUsage)) {
            $stats['memory_usage'] = [
                'avg' => array_sum($memoryUsage) / count($memoryUsage),
                'min' => min($memoryUsage),
                'max' => max($memoryUsage),
                'median' => $this->calculateMedian($memoryUsage)
            ];
        }
        
        $stats['slow_requests'] = array_slice($slowRequests, 0, 10);
        $stats['error_rate'] = $totalRequests > 0 ? ($errorRequests / $totalRequests) * 100 : 0;
        
        return $stats;
    }
    
    /**
     * Get error statistics
     */
    private function getErrorStats($days) {
        $errorLogs = $this->getRecentLogs('errors', $days);
        
        $stats = [
            'by_type' => [],
            'by_level' => [],
            'recent_errors' => [],
            'error_trends' => []
        ];
        
        foreach ($errorLogs as $log) {
            $level = $log['level'] ?? 'ERROR';
            $context = $log['context'] ?? [];
            $errorType = $context['error_type'] ?? 'general';
            $date = date('Y-m-d', strtotime($log['timestamp']));
            
            // Count by type
            $stats['by_type'][$errorType] = ($stats['by_type'][$errorType] ?? 0) + 1;
            
            // Count by level
            $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;
            
            // Track daily trends
            $stats['error_trends'][$date] = ($stats['error_trends'][$date] ?? 0) + 1;
        }
        
        // Recent errors (last 10)
        $stats['recent_errors'] = array_slice($errorLogs, 0, 10);
        
        return $stats;
    }
    
    /**
     * Helper: Get recent logs within specified days
     */
    private function getRecentLogs($type, $days) {
        $cutoff = time() - ($days * 24 * 60 * 60);
        $logs = $this->logger->getLogs($type, 10000);
        
        return array_filter($logs, function($log) use ($cutoff) {
            return strtotime($log['timestamp']) >= $cutoff;
        });
    }
    
    /**
     * Helper: Get unique users from logs
     */
    private function getUniqueUsers($logs) {
        $users = [];
        foreach ($logs as $log) {
            $user = $log['user'] ?? 'anonymous';
            $users[$user] = true;
        }
        return array_keys($users);
    }
    
    /**
     * Helper: Get unique IPs from logs
     */
    private function getUniqueIPs($logs) {
        $ips = [];
        foreach ($logs as $log) {
            $ip = $log['ip'] ?? 'unknown';
            $ips[$ip] = true;
        }
        return array_keys($ips);
    }
    
    /**
     * Helper: Calculate median value
     */
    private function calculateMedian($values) {
        sort($values);
        $count = count($values);
        
        if ($count % 2 === 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        } else {
            return $values[floor($count / 2)];
        }
    }
    
    /**
     * Generate detailed report
     */
    public function generateReport($days = 7, $format = 'array') {
        $analytics = $this->getSystemAnalytics($days);
        
        if ($format === 'json') {
            return json_encode($analytics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        
        if ($format === 'html') {
            return $this->generateHtmlReport($analytics);
        }
        
        return $analytics;
    }
    
    /**
     * Generate HTML report
     */
    private function generateHtmlReport($analytics) {
        $html = "<!DOCTYPE html>\n<html>\n<head>\n";
        $html .= "<title>System Analytics Report</title>\n";
        $html .= "<style>body{font-family:Arial,sans-serif;margin:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;}</style>\n";
        $html .= "</head>\n<body>\n";
        
        $html .= "<h1>System Analytics Report</h1>\n";
        $html .= "<p>Generated: " . date('Y-m-d H:i:s') . "</p>\n";
        
        // Overview
        $overview = $analytics['overview'];
        $html .= "<h2>Overview</h2>\n";
        $html .= "<table>\n";
        $html .= "<tr><th>Metric</th><th>Value</th></tr>\n";
        $html .= "<tr><td>Total Requests</td><td>{$overview['total_requests']}</td></tr>\n";
        $html .= "<tr><td>Total Errors</td><td>{$overview['total_errors']}</td></tr>\n";
        $html .= "<tr><td>Admin Actions</td><td>{$overview['total_admin_actions']}</td></tr>\n";
        $html .= "<tr><td>Unique Users</td><td>{$overview['unique_users']}</td></tr>\n";
        $html .= "<tr><td>Unique IPs</td><td>{$overview['unique_ips']}</td></tr>\n";
        $html .= "</table>\n";
        
        // File Operations
        $fileOps = $analytics['file_operations'];
        $html .= "<h2>File Operations</h2>\n";
        $html .= "<table>\n";
        $html .= "<tr><th>Operation</th><th>Count</th></tr>\n";
        foreach ($fileOps['operations'] as $op => $count) {
            $html .= "<tr><td>$op</td><td>$count</td></tr>\n";
        }
        $html .= "</table>\n";
        
        // Security
        $security = $analytics['security_events'];
        $html .= "<h2>Security Status</h2>\n";
        $html .= "<p><strong>Threat Level:</strong> {$security['threat_level']}</p>\n";
        $html .= "<p><strong>Suspicious IPs:</strong> " . count($security['suspicious_ips']) . "</p>\n";
        
        $html .= "</body>\n</html>";
        
        return $html;
    }
    
    /**
     * Get real-time alerts
     */
    public function getRealTimeAlerts() {
        $alerts = [];
        
        // Check for recent errors
        $recentErrors = $this->getRecentLogs('errors', 1);
        $criticalErrors = array_filter($recentErrors, function($log) {
            return $log['level'] === 'CRITICAL';
        });
        
        if (!empty($criticalErrors)) {
            $alerts[] = [
                'type' => 'critical_error',
                'message' => 'Critical errors detected in the last 24 hours',
                'count' => count($criticalErrors),
                'severity' => 'high'
            ];
        }
        
        // Check for failed login attempts
        $recentAccess = $this->getRecentLogs('access', 1);
        $failedLogins = 0;
        foreach ($recentAccess as $log) {
            $context = $log['context'] ?? [];
            if (isset($context['action']) && $context['action'] === 'login' && 
                isset($context['success']) && !$context['success']) {
                $failedLogins++;
            }
        }
        
        if ($failedLogins > 10) {
            $alerts[] = [
                'type' => 'security_threat',
                'message' => 'High number of failed login attempts',
                'count' => $failedLogins,
                'severity' => 'medium'
            ];
        }
        
        return $alerts;
    }
}
?>
