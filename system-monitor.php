<?php
require_once 'includes/config.php';
require_once 'includes/auth-functions.php';
require_once 'includes/file-functions.php';
require_once 'includes/log-functions.php';
require_once 'includes/json-functions.php';

// Start session and check admin authentication
session_start();
require_authentication();
require_admin_role();

$current_user = get_current_user();

// Get system statistics
$disk_free = disk_free_space(STORAGE_DIR);
$disk_total = disk_total_space(STORAGE_DIR);
$disk_used = $disk_total - $disk_free;
$disk_usage_percent = ($disk_used / $disk_total) * 100;

// Get file statistics
$all_files = read_json_file(STORAGE_DIR . '/data/files.json');
$total_files = count($all_files);
$total_file_size = array_sum(array_column($all_files, 'size'));

// Get user statistics
$all_users = get_all_users();
$total_users = count($all_users);
$admin_users = count(array_filter($all_users, function($u) { return $u['role'] === 'admin'; }));

// Get upload statistics for the last 30 days
$upload_stats = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $uploads_on_date = array_filter($all_files, function($file) use ($date) {
        return date('Y-m-d', strtotime($file['uploaded_at'])) === $date;
    });
    $upload_stats[$date] = count($uploads_on_date);
}

// Get recent activity
$recent_access_logs = get_recent_logs('access', 20);
$recent_error_logs = get_recent_logs('error', 10);
$recent_security_logs = get_recent_logs('security', 10);

// Get file type distribution
$file_types = [];
foreach ($all_files as $file) {
    $type = explode('/', $file['type'])[0] ?? 'unknown';
    $file_types[$type] = ($file_types[$type] ?? 0) + 1;
}

// Log system monitor access
log_admin_action("System monitor accessed by admin: " . $current_user['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitor - FileServer</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <meta http-equiv="refresh" content="60">
</head>
<body>
    <?php include 'templates/header.html'; ?>
    <?php include 'templates/navigation.html'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>System Monitor</h1>
            <div class="page-actions">
                <a href="admin.php" class="btn btn-secondary">Back to Admin</a>
                <button onclick="refreshPage()" class="btn btn-primary">🔄 Refresh</button>
            </div>
        </div>

        <!-- System Overview -->
        <div class="monitor-section">
            <h2>System Overview</h2>
            <div class="stats-grid">
                <div class="stat-card system">
                    <h3>Disk Usage</h3>
                    <div class="disk-usage">
                        <div class="disk-bar">
                            <div class="disk-fill" style="width: <?php echo $disk_usage_percent; ?>%"></div>
                        </div>
                        <div class="disk-stats">
                            <span>Used: <?php echo format_file_size($disk_used); ?></span>
                            <span>Free: <?php echo format_file_size($disk_free); ?></span>
                            <span>Total: <?php echo format_file_size($disk_total); ?></span>
                        </div>
                        <div class="disk-percent"><?php echo number_format($disk_usage_percent, 1); ?>% used</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Total Files</h3>
                    <div class="stat-number"><?php echo $total_files; ?></div>
                    <p>Files in system</p>
                </div>
                
                <div class="stat-card">
                    <h3>Total Storage</h3>
                    <div class="stat-number"><?php echo format_file_size($total_file_size); ?></div>
                    <p>File data stored</p>
                </div>
                
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <p><?php echo $admin_users; ?> admin(s)</p>
                </div>
            </div>
        </div>

        <!-- Upload Activity Chart -->
        <div class="monitor-section">
            <h2>Upload Activity (Last 30 Days)</h2>
            <div class="chart-container">
                <div class="upload-chart">
                    <?php foreach ($upload_stats as $date => $count): ?>
                    <div class="chart-bar">
                        <div class="bar-fill" style="height: <?php echo $count > 0 ? min(100, ($count / max($upload_stats)) * 100) : 0; ?>%"></div>
                        <div class="bar-label"><?php echo date('m/d', strtotime($date)); ?></div>
                        <div class="bar-value"><?php echo $count; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- File Type Distribution -->
        <div class="monitor-section">
            <h2>File Type Distribution</h2>
            <div class="file-types">
                <?php foreach ($file_types as $type => $count): ?>
                <div class="file-type-item">
                    <span class="type-name"><?php echo ucfirst(htmlspecialchars($type)); ?></span>
                    <span class="type-count"><?php echo $count; ?> files</span>
                    <div class="type-bar">
                        <div class="type-fill" style="width: <?php echo ($count / $total_files) * 100; ?>%"></div>
                    </div>
                    <span class="type-percent"><?php echo number_format(($count / $total_files) * 100, 1); ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="monitor-section">
            <h2>Recent Activity</h2>
            <div class="activity-logs">
                <div class="log-section">
                    <h3>Recent Access</h3>
                    <div class="log-list">
                        <?php foreach (array_slice($recent_access_logs, 0, 10) as $log): ?>
                        <div class="log-entry access">
                            <span class="log-time"><?php echo date('M j, H:i', strtotime($log['timestamp'])); ?></span>
                            <span class="log-message"><?php echo htmlspecialchars($log['message']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if (!empty($recent_error_logs)): ?>
                <div class="log-section">
                    <h3>Recent Errors</h3>
                    <div class="log-list">
                        <?php foreach ($recent_error_logs as $log): ?>
                        <div class="log-entry error">
                            <span class="log-time"><?php echo date('M j, H:i', strtotime($log['timestamp'])); ?></span>
                            <span class="log-message"><?php echo htmlspecialchars($log['message']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($recent_security_logs)): ?>
                <div class="log-section">
                    <h3>Security Events</h3>
                    <div class="log-list">
                        <?php foreach ($recent_security_logs as $log): ?>
                        <div class="log-entry security">
                            <span class="log-time"><?php echo date('M j, H:i', strtotime($log['timestamp'])); ?></span>
                            <span class="log-message"><?php echo htmlspecialchars($log['message']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Health -->
        <div class="monitor-section">
            <h2>System Health</h2>
            <div class="health-checks">
                <div class="health-item">
                    <span class="health-label">Storage Directory:</span>
                    <span class="health-status <?php echo is_writable(STORAGE_DIR . '/storage/uploads') ? 'healthy' : 'error'; ?>">
                        <?php echo is_writable(STORAGE_DIR . '/storage/uploads') ? '✅ Writable' : '❌ Not Writable'; ?>
                    </span>
                </div>
                
                <div class="health-item">
                    <span class="health-label">Data Directory:</span>
                    <span class="health-status <?php echo is_writable(STORAGE_DIR . '/data') ? 'healthy' : 'error'; ?>">
                        <?php echo is_writable(STORAGE_DIR . '/data') ? '✅ Writable' : '❌ Not Writable'; ?>
                    </span>
                </div>
                
                <div class="health-item">
                    <span class="health-label">Log Directory:</span>
                    <span class="health-status <?php echo is_writable(STORAGE_DIR . '/logs') ? 'healthy' : 'error'; ?>">
                        <?php echo is_writable(STORAGE_DIR . '/logs') ? '✅ Writable' : '❌ Not Writable'; ?>
                    </span>
                </div>
                
                <div class="health-item">
                    <span class="health-label">PHP File Uploads:</span>
                    <span class="health-status <?php echo ini_get('file_uploads') ? 'healthy' : 'error'; ?>">
                        <?php echo ini_get('file_uploads') ? '✅ Enabled' : '❌ Disabled'; ?>
                    </span>
                </div>
                
                <div class="health-item">
                    <span class="health-label">Max Upload Size:</span>
                    <span class="health-status healthy">
                        ✅ <?php echo format_file_size(get_max_upload_size()); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php include 'templates/footer.html'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        function refreshPage() {
            window.location.reload();
        }
        
        // Auto-refresh timestamp
        const lastUpdate = document.createElement('div');
        lastUpdate.className = 'last-update';
        lastUpdate.textContent = 'Last updated: ' + new Date().toLocaleTimeString();
        document.querySelector('.page-header').appendChild(lastUpdate);
    </script>
</body>
</html>
