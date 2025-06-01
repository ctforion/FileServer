<?php

namespace Core\Monitoring;

use Core\Database\Database;
use Core\Utils\EnvLoader;

/**
 * System Monitoring and Analytics
 * Handles system health monitoring, performance metrics, and usage analytics
 */
class Monitor {
    private $db;
    private $config;
    private $metrics = [];
    private $alerts = [];
    private $thresholds;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = EnvLoader::getConfig();
        $this->initializeThresholds();
    }

    private function initializeThresholds() {
        $this->thresholds = [
            'cpu' => [
                'warning' => (float)($this->config['MONITOR_CPU_WARNING'] ?? 70),
                'critical' => (float)($this->config['MONITOR_CPU_CRITICAL'] ?? 85)
            ],
            'memory' => [
                'warning' => (float)($this->config['MONITOR_MEMORY_WARNING'] ?? 75),
                'critical' => (float)($this->config['MONITOR_MEMORY_CRITICAL'] ?? 90)
            ],
            'disk' => [
                'warning' => (float)($this->config['MONITOR_DISK_WARNING'] ?? 80),
                'critical' => (float)($this->config['MONITOR_DISK_CRITICAL'] ?? 95)
            ],
            'response_time' => [
                'warning' => (float)($this->config['MONITOR_RESPONSE_WARNING'] ?? 2000),
                'critical' => (float)($this->config['MONITOR_RESPONSE_CRITICAL'] ?? 5000)
            ]
        ];
    }

    /**
     * Get comprehensive system health status
     */
    public function getSystemHealth() {
        $health = [
            'overall' => 'healthy',
            'cpu' => $this->getCpuUsage(),
            'memory' => $this->getMemoryUsage(),
            'disk' => $this->getDiskUsage(),
            'database' => $this->getDatabaseHealth(),
            'services' => $this->getServicesHealth(),
            'last_check' => time()
        ];

        // Determine overall health
        $criticalIssues = 0;
        $warningIssues = 0;

        foreach (['cpu', 'memory', 'disk'] as $metric) {
            $usage = $health[$metric]['usage'];
            if ($usage >= $this->thresholds[$metric]['critical']) {
                $criticalIssues++;
            } elseif ($usage >= $this->thresholds[$metric]['warning']) {
                $warningIssues++;
            }
        }

        if ($criticalIssues > 0) {
            $health['overall'] = 'critical';
        } elseif ($warningIssues > 0) {
            $health['overall'] = 'warning';
        }

        return $health;
    }

    /**
     * Get CPU usage percentage
     */
    private function getCpuUsage() {
        $usage = 0;
        $status = 'healthy';

        // Try to get CPU usage on different OS
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'wmic cpu get loadpercentage /value';
            $output = shell_exec($cmd);
            if ($output && preg_match('/LoadPercentage=(\d+)/', $output, $matches)) {
                $usage = (int)$matches[1];
            }
        } else {
            // Linux/Unix
            $load = sys_getloadavg();
            if ($load !== false) {
                $usage = min(100, ($load[0] / $this->getCpuCores()) * 100);
            }
        }

        if ($usage >= $this->thresholds['cpu']['critical']) {
            $status = 'critical';
        } elseif ($usage >= $this->thresholds['cpu']['warning']) {
            $status = 'warning';
        }

        return [
            'usage' => round($usage, 1),
            'status' => $status,
            'cores' => $this->getCpuCores()
        ];
    }

    /**
     * Get memory usage percentage
     */
    private function getMemoryUsage() {
        $total = 0;
        $used = 0;
        $status = 'healthy';

        if (PHP_OS_FAMILY === 'Windows') {
            // Windows memory check
            $cmd = 'wmic OS get TotalVisibleMemorySize,FreePhysicalMemory /value';
            $output = shell_exec($cmd);
            if ($output) {
                preg_match('/TotalVisibleMemorySize=(\d+)/', $output, $totalMatches);
                preg_match('/FreePhysicalMemory=(\d+)/', $output, $freeMatches);
                
                if (isset($totalMatches[1]) && isset($freeMatches[1])) {
                    $total = (int)$totalMatches[1] * 1024; // Convert KB to bytes
                    $free = (int)$freeMatches[1] * 1024;
                    $used = $total - $free;
                }
            }
        } else {
            // Linux/Unix
            $meminfo = file_get_contents('/proc/meminfo');
            if ($meminfo) {
                preg_match('/MemTotal:\s+(\d+)/', $meminfo, $totalMatches);
                preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $availableMatches);
                
                if (isset($totalMatches[1]) && isset($availableMatches[1])) {
                    $total = (int)$totalMatches[1] * 1024; // Convert KB to bytes
                    $available = (int)$availableMatches[1] * 1024;
                    $used = $total - $available;
                }
            }
        }

        $usage = $total > 0 ? ($used / $total) * 100 : 0;

        if ($usage >= $this->thresholds['memory']['critical']) {
            $status = 'critical';
        } elseif ($usage >= $this->thresholds['memory']['warning']) {
            $status = 'warning';
        }

        return [
            'usage' => round($usage, 1),
            'total' => $total,
            'used' => $used,
            'free' => $total - $used,
            'status' => $status
        ];
    }

    /**
     * Get disk usage percentage
     */
    private function getDiskUsage() {
        $path = $this->config['STORAGE_PATH'] ?? './storage';
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;
        $usage = $total > 0 ? ($used / $total) * 100 : 0;
        $status = 'healthy';

        if ($usage >= $this->thresholds['disk']['critical']) {
            $status = 'critical';
        } elseif ($usage >= $this->thresholds['disk']['warning']) {
            $status = 'warning';
        }

        return [
            'usage' => round($usage, 1),
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'status' => $status
        ];
    }

    /**
     * Get database health
     */
    private function getDatabaseHealth() {
        $health = [
            'status' => 'healthy',
            'connections' => 0,
            'slow_queries' => 0,
            'size' => 0
        ];

        try {
            // Test database connection
            $start = microtime(true);
            $this->db->query("SELECT 1");
            $responseTime = (microtime(true) - $start) * 1000;

            $health['response_time'] = round($responseTime, 2);

            if ($responseTime >= $this->thresholds['response_time']['critical']) {
                $health['status'] = 'critical';
            } elseif ($responseTime >= $this->thresholds['response_time']['warning']) {
                $health['status'] = 'warning';
            }

            // Get database size
            $dbType = $this->config['DB_TYPE'] ?? 'sqlite';
            if ($dbType === 'sqlite') {
                $dbPath = $this->config['DB_PATH'] ?? './database.sqlite';
                if (file_exists($dbPath)) {
                    $health['size'] = filesize($dbPath);
                }
            } else {
                // For MySQL/PostgreSQL, we'd need specific queries
                $health['size'] = 0; // Placeholder
            }

        } catch (\Exception $e) {
            $health['status'] = 'critical';
            $health['error'] = $e->getMessage();
        }

        return $health;
    }

    /**
     * Get services health status
     */
    private function getServicesHealth() {
        $services = [
            'web_server' => $this->checkWebServer(),
            'file_storage' => $this->checkFileStorage(),
            'cache' => $this->checkCache(),
            'email' => $this->checkEmail()
        ];

        return $services;
    }

    /**
     * Get system analytics
     */
    public function getAnalytics($period = '7days') {
        $analytics = [
            'period' => $period,
            'storage' => $this->getStorageAnalytics(),
            'usage' => $this->getUsageAnalytics($period),
            'activity' => $this->getActivityAnalytics($period),
            'performance' => $this->getPerformanceAnalytics($period),
            'users' => $this->getUserAnalytics($period)
        ];

        return $analytics;
    }

    /**
     * Get storage analytics
     */
    private function getStorageAnalytics() {
        try {
            // Total files and storage
            $totalFiles = $this->db->queryRow("SELECT COUNT(*) as count FROM files WHERE deleted_at IS NULL")['count'] ?? 0;
            $totalSize = $this->db->queryRow("SELECT SUM(size) as size FROM files WHERE deleted_at IS NULL")['size'] ?? 0;

            // Storage by file type
            $typeQuery = "
                SELECT 
                    LOWER(SUBSTR(name, INSTR(name, '.') + 1)) as extension,
                    COUNT(*) as count,
                    SUM(size) as size
                FROM files 
                WHERE deleted_at IS NULL 
                    AND INSTR(name, '.') > 0
                GROUP BY LOWER(SUBSTR(name, INSTR(name, '.') + 1))
                ORDER BY size DESC
                LIMIT 10
            ";
            $typeStats = $this->db->query($typeQuery);

            // Storage by user
            $userQuery = "
                SELECT 
                    u.name,
                    COUNT(f.id) as file_count,
                    SUM(f.size) as storage_used
                FROM users u
                LEFT JOIN files f ON u.id = f.user_id AND f.deleted_at IS NULL
                GROUP BY u.id, u.name
                ORDER BY storage_used DESC
                LIMIT 10
            ";
            $userStats = $this->db->query($userQuery);

            return [
                'total_files' => (int)$totalFiles,
                'total_size' => (int)$totalSize,
                'by_type' => $typeStats,
                'by_user' => $userStats,
                'disk_usage' => $this->getDiskUsage()
            ];

        } catch (\Exception $e) {
            error_log("Storage analytics error: " . $e->getMessage());
            return [
                'total_files' => 0,
                'total_size' => 0,
                'by_type' => [],
                'by_user' => [],
                'disk_usage' => $this->getDiskUsage()
            ];
        }
    }

    /**
     * Get usage analytics for specified period
     */
    private function getUsageAnalytics($period) {
        try {
            $startDate = $this->getPeriodStartDate($period);
            
            // Daily upload/download counts
            $dailyQuery = "
                SELECT 
                    DATE(created_at) as date,
                    SUM(CASE WHEN action = 'upload' THEN 1 ELSE 0 END) as uploads,
                    SUM(CASE WHEN action = 'download' THEN 1 ELSE 0 END) as downloads
                FROM audit_logs 
                WHERE created_at >= ? 
                    AND action IN ('upload', 'download')
                GROUP BY DATE(created_at)
                ORDER BY date
            ";
            $dailyStats = $this->db->query($dailyQuery, [$startDate]);

            // Total counts
            $totalQuery = "
                SELECT 
                    SUM(CASE WHEN action = 'upload' THEN 1 ELSE 0 END) as total_uploads,
                    SUM(CASE WHEN action = 'download' THEN 1 ELSE 0 END) as total_downloads
                FROM audit_logs 
                WHERE created_at >= ?
                    AND action IN ('upload', 'download')
            ";
            $totals = $this->db->queryRow($totalQuery, [$startDate]);

            return [
                'daily' => $dailyStats,
                'totals' => $totals,
                'labels' => array_column($dailyStats, 'date'),
                'uploads' => array_column($dailyStats, 'uploads'),
                'downloads' => array_column($dailyStats, 'downloads')
            ];

        } catch (\Exception $e) {
            error_log("Usage analytics error: " . $e->getMessage());
            return [
                'daily' => [],
                'totals' => ['total_uploads' => 0, 'total_downloads' => 0],
                'labels' => [],
                'uploads' => [],
                'downloads' => []
            ];
        }
    }

    /**
     * Get activity analytics
     */
    private function getActivityAnalytics($period) {
        try {
            $startDate = $this->getPeriodStartDate($period);
            
            // Weekly activity pattern
            $weeklyQuery = "
                SELECT 
                    CASE 
                        WHEN strftime('%w', created_at) = '0' THEN 'Sunday'
                        WHEN strftime('%w', created_at) = '1' THEN 'Monday'
                        WHEN strftime('%w', created_at) = '2' THEN 'Tuesday'
                        WHEN strftime('%w', created_at) = '3' THEN 'Wednesday'
                        WHEN strftime('%w', created_at) = '4' THEN 'Thursday'
                        WHEN strftime('%w', created_at) = '5' THEN 'Friday'
                        WHEN strftime('%w', created_at) = '6' THEN 'Saturday'
                    END as day_name,
                    strftime('%w', created_at) as day_number,
                    COUNT(*) as activity_count
                FROM audit_logs 
                WHERE created_at >= ?
                GROUP BY strftime('%w', created_at)
                ORDER BY day_number
            ";
            $weeklyStats = $this->db->query($weeklyQuery, [$startDate]);

            // Hourly activity pattern
            $hourlyQuery = "
                SELECT 
                    strftime('%H', created_at) as hour,
                    COUNT(*) as activity_count
                FROM audit_logs 
                WHERE created_at >= ?
                GROUP BY strftime('%H', created_at)
                ORDER BY hour
            ";
            $hourlyStats = $this->db->query($hourlyQuery, [$startDate]);

            return [
                'weekly' => array_column($weeklyStats, 'activity_count'),
                'hourly' => $hourlyStats,
                'peak_day' => $this->findPeakDay($weeklyStats),
                'peak_hour' => $this->findPeakHour($hourlyStats)
            ];

        } catch (\Exception $e) {
            error_log("Activity analytics error: " . $e->getMessage());
            return [
                'weekly' => [0, 0, 0, 0, 0, 0, 0],
                'hourly' => [],
                'peak_day' => 'Unknown',
                'peak_hour' => 'Unknown'
            ];
        }
    }

    /**
     * Get performance analytics
     */
    private function getPerformanceAnalytics($period) {
        try {
            $startDate = $this->getPeriodStartDate($period);
            
            // Average response times
            $performanceQuery = "
                SELECT 
                    DATE(created_at) as date,
                    AVG(CAST(JSON_EXTRACT(context, '$.response_time') AS REAL)) as avg_response_time,
                    MIN(CAST(JSON_EXTRACT(context, '$.response_time') AS REAL)) as min_response_time,
                    MAX(CAST(JSON_EXTRACT(context, '$.response_time') AS REAL)) as max_response_time
                FROM audit_logs 
                WHERE created_at >= ?
                    AND JSON_EXTRACT(context, '$.response_time') IS NOT NULL
                GROUP BY DATE(created_at)
                ORDER BY date
            ";
            $performanceStats = $this->db->query($performanceQuery, [$startDate]);

            // Error rates
            $errorQuery = "
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN action LIKE '%error%' OR action LIKE '%fail%' THEN 1 ELSE 0 END) as errors
                FROM audit_logs 
                WHERE created_at >= ?
                GROUP BY DATE(created_at)
                ORDER BY date
            ";
            $errorStats = $this->db->query($errorQuery, [$startDate]);

            return [
                'response_times' => $performanceStats,
                'error_rates' => $errorStats,
                'avg_response_time' => $this->calculateAverageResponseTime($performanceStats)
            ];

        } catch (\Exception $e) {
            error_log("Performance analytics error: " . $e->getMessage());
            return [
                'response_times' => [],
                'error_rates' => [],
                'avg_response_time' => 0
            ];
        }
    }

    /**
     * Get user analytics
     */
    private function getUserAnalytics($period) {
        try {
            $startDate = $this->getPeriodStartDate($period);
            
            // User registrations
            $registrationQuery = "
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as new_users
                FROM users 
                WHERE created_at >= ?
                GROUP BY DATE(created_at)
                ORDER BY date
            ";
            $registrations = $this->db->query($registrationQuery, [$startDate]);

            // Active users
            $activeQuery = "
                SELECT COUNT(DISTINCT user_id) as active_users
                FROM audit_logs 
                WHERE created_at >= ?
                    AND user_id IS NOT NULL
            ";
            $activeUsers = $this->db->queryRow($activeQuery, [$startDate])['active_users'] ?? 0;

            // User growth
            $totalUsers = $this->db->queryRow("SELECT COUNT(*) as count FROM users")['count'] ?? 0;
            $previousPeriodStart = $this->getPeriodStartDate($period, true);
            $previousTotal = $this->db->queryRow(
                "SELECT COUNT(*) as count FROM users WHERE created_at < ?", 
                [$startDate]
            )['count'] ?? 0;
            
            $growth = $previousTotal > 0 ? (($totalUsers - $previousTotal) / $previousTotal) * 100 : 0;

            return [
                'daily_registrations' => $registrations,
                'active_users' => (int)$activeUsers,
                'total_users' => (int)$totalUsers,
                'growth_rate' => round($growth, 2)
            ];

        } catch (\Exception $e) {
            error_log("User analytics error: " . $e->getMessage());
            return [
                'daily_registrations' => [],
                'active_users' => 0,
                'total_users' => 0,
                'growth_rate' => 0
            ];
        }
    }

    /**
     * Record performance metric
     */
    public function recordMetric($metric, $value, $context = []) {
        try {
            $this->db->insert('metrics', [
                'metric' => $metric,
                'value' => $value,
                'context' => json_encode($context),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log("Failed to record metric: " . $e->getMessage());
        }
    }

    /**
     * Check for alerts based on thresholds
     */
    public function checkAlerts() {
        $health = $this->getSystemHealth();
        $alerts = [];

        // CPU alerts
        if ($health['cpu']['usage'] >= $this->thresholds['cpu']['critical']) {
            $alerts[] = [
                'type' => 'critical',
                'metric' => 'cpu',
                'message' => "CPU usage is critically high: {$health['cpu']['usage']}%",
                'value' => $health['cpu']['usage'],
                'threshold' => $this->thresholds['cpu']['critical']
            ];
        } elseif ($health['cpu']['usage'] >= $this->thresholds['cpu']['warning']) {
            $alerts[] = [
                'type' => 'warning',
                'metric' => 'cpu',
                'message' => "CPU usage is high: {$health['cpu']['usage']}%",
                'value' => $health['cpu']['usage'],
                'threshold' => $this->thresholds['cpu']['warning']
            ];
        }

        // Memory alerts
        if ($health['memory']['usage'] >= $this->thresholds['memory']['critical']) {
            $alerts[] = [
                'type' => 'critical',
                'metric' => 'memory',
                'message' => "Memory usage is critically high: {$health['memory']['usage']}%",
                'value' => $health['memory']['usage'],
                'threshold' => $this->thresholds['memory']['critical']
            ];
        } elseif ($health['memory']['usage'] >= $this->thresholds['memory']['warning']) {
            $alerts[] = [
                'type' => 'warning',
                'metric' => 'memory',
                'message' => "Memory usage is high: {$health['memory']['usage']}%",
                'value' => $health['memory']['usage'],
                'threshold' => $this->thresholds['memory']['warning']
            ];
        }

        // Disk alerts
        if ($health['disk']['usage'] >= $this->thresholds['disk']['critical']) {
            $alerts[] = [
                'type' => 'critical',
                'metric' => 'disk',
                'message' => "Disk usage is critically high: {$health['disk']['usage']}%",
                'value' => $health['disk']['usage'],
                'threshold' => $this->thresholds['disk']['critical']
            ];
        } elseif ($health['disk']['usage'] >= $this->thresholds['disk']['warning']) {
            $alerts[] = [
                'type' => 'warning',
                'metric' => 'disk',
                'message' => "Disk usage is high: {$health['disk']['usage']}%",
                'value' => $health['disk']['usage'],
                'threshold' => $this->thresholds['disk']['warning']
            ];
        }

        return $alerts;
    }

    /**
     * Helper methods
     */
    private function getCpuCores() {
        if (PHP_OS_FAMILY === 'Windows') {
            return (int)shell_exec('echo %NUMBER_OF_PROCESSORS%') ?: 1;
        } else {
            return (int)shell_exec('nproc') ?: 1;
        }
    }

    private function checkWebServer() {
        return ['status' => 'healthy', 'uptime' => $this->getUptime()];
    }

    private function checkFileStorage() {
        $storagePath = $this->config['STORAGE_PATH'] ?? './storage';
        return [
            'status' => is_writable($storagePath) ? 'healthy' : 'critical',
            'writable' => is_writable($storagePath)
        ];
    }

    private function checkCache() {
        // Placeholder for cache service check
        return ['status' => 'healthy'];
    }

    private function checkEmail() {
        // Placeholder for email service check
        return ['status' => 'healthy'];
    }

    private function getUptime() {
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'wmic os get lastbootuptime /value';
            $output = shell_exec($cmd);
            if ($output && preg_match('/LastBootUpTime=(\d{14})/', $output, $matches)) {
                $bootTime = \DateTime::createFromFormat('YmdHis', $matches[1]);
                if ($bootTime) {
                    return time() - $bootTime->getTimestamp();
                }
            }
        } else {
            $uptime = file_get_contents('/proc/uptime');
            if ($uptime) {
                return (int)floatval(explode(' ', $uptime)[0]);
            }
        }
        return 0;
    }

    private function getPeriodStartDate($period, $previous = false) {
        $modifier = $previous ? 2 : 1;
        
        switch ($period) {
            case '24hours':
                return date('Y-m-d H:i:s', strtotime("-" . (24 * $modifier) . " hours"));
            case '7days':
                return date('Y-m-d H:i:s', strtotime("-" . (7 * $modifier) . " days"));
            case '30days':
                return date('Y-m-d H:i:s', strtotime("-" . (30 * $modifier) . " days"));
            case '90days':
                return date('Y-m-d H:i:s', strtotime("-" . (90 * $modifier) . " days"));
            default:
                return date('Y-m-d H:i:s', strtotime("-" . (7 * $modifier) . " days"));
        }
    }

    private function findPeakDay($weeklyStats) {
        if (empty($weeklyStats)) return 'Unknown';
        
        $maxActivity = max(array_column($weeklyStats, 'activity_count'));
        foreach ($weeklyStats as $day) {
            if ($day['activity_count'] == $maxActivity) {
                return $day['day_name'];
            }
        }
        return 'Unknown';
    }

    private function findPeakHour($hourlyStats) {
        if (empty($hourlyStats)) return 'Unknown';
        
        $maxActivity = max(array_column($hourlyStats, 'activity_count'));
        foreach ($hourlyStats as $hour) {
            if ($hour['activity_count'] == $maxActivity) {
                return $hour['hour'] . ':00';
            }
        }
        return 'Unknown';
    }

    private function calculateAverageResponseTime($performanceStats) {
        if (empty($performanceStats)) return 0;
        
        $total = array_sum(array_column($performanceStats, 'avg_response_time'));
        return round($total / count($performanceStats), 2);
    }
}
