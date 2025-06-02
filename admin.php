<?php
require_once 'includes/config.php';
require_once 'includes/auth-functions.php';
require_once 'includes/user-functions.php';
require_once 'includes/file-functions.php';
require_once 'includes/log-functions.php';
require_once 'includes/json-functions.php';
require_once 'includes/security-functions.php';

// Start session and check admin authentication
session_start();
require_authentication();
require_admin_role();

$current_user = get_current_user();
$admin_action = $_GET['action'] ?? 'dashboard';
$success_message = '';
$error_message = '';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete_user':
            $user_id = $_POST['user_id'] ?? '';
            if (!empty($user_id) && $user_id !== $current_user['id']) {
                if (delete_user($user_id)) {
                    $success_message = "User deleted successfully.";
                    log_admin_action("User deleted: ID " . $user_id . " by admin: " . $current_user['username']);
                } else {
                    $error_message = "Failed to delete user.";
                }
            }
            break;
            
        case 'block_ip':
            $ip_address = $_POST['ip_address'] ?? '';
            if (!empty($ip_address)) {
                if (block_ip_address($ip_address)) {
                    $success_message = "IP address blocked successfully.";
                    log_security("IP blocked: " . $ip_address . " by admin: " . $current_user['username']);
                } else {
                    $error_message = "Failed to block IP address.";
                }
            }
            break;
            
        case 'unblock_ip':
            $ip_address = $_POST['ip_address'] ?? '';
            if (!empty($ip_address)) {
                if (unblock_ip_address($ip_address)) {
                    $success_message = "IP address unblocked successfully.";
                    log_security("IP unblocked: " . $ip_address . " by admin: " . $current_user['username']);
                } else {
                    $error_message = "Failed to unblock IP address.";
                }
            }
            break;
            
        case 'delete_file':
            $file_id = $_POST['file_id'] ?? '';
            if (!empty($file_id)) {
                if (admin_delete_file($file_id)) {
                    $success_message = "File deleted successfully.";
                    log_admin_action("File deleted: ID " . $file_id . " by admin: " . $current_user['username']);
                } else {
                    $error_message = "Failed to delete file.";
                }
            }
            break;
    }
}

// Get system statistics
$all_users = get_all_users();
$all_files = read_json_file(STORAGE_DIR . '/data/files.json');
$total_users = count($all_users);
$total_files = count($all_files);
$total_storage = array_sum(array_column($all_files, 'size'));
$blocked_ips = get_blocked_ips();

// Get recent logs
$recent_access_logs = get_recent_logs('access', 10);
$recent_error_logs = get_recent_logs('error', 10);
$recent_security_logs = get_recent_logs('security', 10);

// Log admin panel access
log_admin_action("Admin panel accessed by: " . $current_user['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - FileServer</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/forms.css">
</head>
<body>
    <?php include 'templates/header.html'; ?>
    <?php include 'templates/navigation.html'; ?>
    
    <div class="container">
        <div class="admin-header">
            <h1>Admin Panel</h1>
            <div class="admin-nav">
                <a href="?action=dashboard" class="<?php echo $admin_action === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
                <a href="?action=users" class="<?php echo $admin_action === 'users' ? 'active' : ''; ?>">Users</a>
                <a href="?action=files" class="<?php echo $admin_action === 'files' ? 'active' : ''; ?>">Files</a>
                <a href="?action=security" class="<?php echo $admin_action === 'security' ? 'active' : ''; ?>">Security</a>
                <a href="?action=logs" class="<?php echo $admin_action === 'logs' ? 'active' : ''; ?>">Logs</a>
                <a href="system-monitor.php">System Monitor</a>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <?php if ($admin_action === 'dashboard'): ?>
        <!-- Dashboard View -->
        <div class="admin-dashboard">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <p>Registered users</p>
                </div>
                
                <div class="stat-card">
                    <h3>Total Files</h3>
                    <div class="stat-number"><?php echo $total_files; ?></div>
                    <p>Files uploaded</p>
                </div>
                
                <div class="stat-card">
                    <h3>Storage Used</h3>
                    <div class="stat-number"><?php echo format_file_size($total_storage); ?></div>
                    <p>Total storage</p>
                </div>
                
                <div class="stat-card">
                    <h3>Blocked IPs</h3>
                    <div class="stat-number"><?php echo count($blocked_ips); ?></div>
                    <p>Security blocks</p>
                </div>
            </div>

            <div class="recent-activity">
                <h2>Recent Activity</h2>
                <div class="activity-tabs">
                    <div class="tab-content">
                        <h3>Recent Access Logs</h3>
                        <div class="log-list">
                            <?php foreach ($recent_access_logs as $log): ?>
                            <div class="log-entry">
                                <span class="log-time"><?php echo date('M j, H:i', strtotime($log['timestamp'])); ?></span>
                                <span class="log-message"><?php echo htmlspecialchars($log['message']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($admin_action === 'users'): ?>
        <!-- Users Management -->
        <div class="admin-users">
            <div class="section-header">
                <h2>User Management</h2>
                <a href="user-management.php" class="btn btn-primary">Add New User</a>
            </div>
            
            <?php include 'templates/user-list.html'; ?>
        </div>

        <?php elseif ($admin_action === 'files'): ?>
        <!-- Files Management -->
        <div class="admin-files">
            <div class="section-header">
                <h2>File Management</h2>
                <p>Manage all files in the system</p>
            </div>
            
            <div class="file-list">
                <?php 
                $files = $all_files; // Set for template inclusion
                include 'templates/file-list.html'; 
                ?>
            </div>
        </div>

        <?php elseif ($admin_action === 'security'): ?>
        <!-- Security Management -->
        <div class="admin-security">
            <div class="section-header">
                <h2>Security Management</h2>
            </div>
            
            <div class="security-sections">
                <div class="security-card">
                    <h3>Block IP Address</h3>
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="block_ip">
                        <input type="text" name="ip_address" placeholder="IP Address" required>
                        <button type="submit" class="btn btn-danger">Block IP</button>
                    </form>
                </div>
                
                <div class="security-card">
                    <h3>Blocked IP Addresses</h3>
                    <?php if (empty($blocked_ips)): ?>
                    <p>No IP addresses are currently blocked.</p>
                    <?php else: ?>
                    <div class="blocked-ips">
                        <?php foreach ($blocked_ips as $ip): ?>
                        <div class="blocked-ip">
                            <span><?php echo htmlspecialchars($ip); ?></span>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="unblock_ip">
                                <input type="hidden" name="ip_address" value="<?php echo htmlspecialchars($ip); ?>">
                                <button type="submit" class="btn btn-small btn-secondary">Unblock</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php elseif ($admin_action === 'logs'): ?>
        <!-- Logs View -->
        <div class="admin-logs">
            <div class="section-header">
                <h2>System Logs</h2>
            </div>
            
            <div class="logs-sections">
                <div class="log-section">
                    <h3>Error Logs</h3>
                    <div class="log-list">
                        <?php foreach ($recent_error_logs as $log): ?>
                        <div class="log-entry error">
                            <span class="log-time"><?php echo date('M j, H:i', strtotime($log['timestamp'])); ?></span>
                            <span class="log-message"><?php echo htmlspecialchars($log['message']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="log-section">
                    <h3>Security Logs</h3>
                    <div class="log-list">
                        <?php foreach ($recent_security_logs as $log): ?>
                        <div class="log-entry security">
                            <span class="log-time"><?php echo date('M j, H:i', strtotime($log['timestamp'])); ?></span>
                            <span class="log-message"><?php echo htmlspecialchars($log['message']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'templates/footer.html'; ?>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
