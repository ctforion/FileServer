<?php
session_start();

require_once '../config.php';
require_once '../core/database/DatabaseManager.php';
require_once '../core/auth/UserManager.php';
require_once '../core/auth/AdminManager.php';
require_once '../core/logging/Logger.php';
require_once '../core/logging/LogAnalyzer.php';
require_once '../core/utils/SecurityManager.php';

// Initialize managers
$config = require '../config.php';
$dbManager = DatabaseManager::getInstance();
$userManager = new UserManager();
$adminManager = new AdminManager();
$logger = new Logger($config['logging']['log_path']);
$logAnalyzer = new LogAnalyzer($config['logging']['log_path']);
$security = new SecurityManager();

// Check authentication and admin role
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $userManager->getUserById($_SESSION['user_id']);
if (!$currentUser || $currentUser['role'] !== 'admin') {
    http_response_code(403);
    die('Access denied: Admin role required');
}

// Get dashboard data
$stats = [
    'total_users' => count($dbManager->getAllUsers()),
    'total_files' => count($dbManager->getFiles()),
    'total_sessions' => count($dbManager->getSessions()),
    'disk_usage' => $adminManager->getDiskUsage(),
    'system_status' => $adminManager->getSystemStatus()
];

// Get recent activity
$recentLogs = $logAnalyzer->getRecentLogs(50);
$errorLogs = $logAnalyzer->getErrorLogs(20);

// Get CSRF token
$csrfToken = $security->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FileServer</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <nav class="admin-sidebar">
            <div class="sidebar-header">
                <h2>FileServer Admin</h2>
            </div>
            <ul class="sidebar-menu">
                <li><a href="#dashboard" class="active">Dashboard</a></li>
                <li><a href="#users">User Management</a></li>
                <li><a href="#files">File Management</a></li>
                <li><a href="#logs">System Logs</a></li>
                <li><a href="#settings">Settings</a></li>
                <li><a href="#maintenance">Maintenance</a></li>
                <li><a href="../index.php">← Back to Main</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1>Admin Dashboard</h1>
                <div class="header-actions">
                    <span>Welcome, <?= htmlspecialchars($currentUser['username']) ?></span>
                    <a href="../index.php?logout=1" class="btn btn-secondary">Logout</a>
                </div>
            </div>

            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Users</h3>
                        <div class="stat-value"><?= $stats['total_users'] ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Files</h3>
                        <div class="stat-value"><?= $stats['total_files'] ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Active Sessions</h3>
                        <div class="stat-value"><?= $stats['total_sessions'] ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Disk Usage</h3>
                        <div class="stat-value"><?= formatFileSize($stats['disk_usage']['used']) ?></div>
                        <div class="stat-subtitle">of <?= formatFileSize($stats['disk_usage']['total']) ?></div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="dashboard-section">
                        <h3>System Status</h3>
                        <div class="status-indicators">
                            <div class="status-item <?= $stats['system_status']['database'] ? 'status-ok' : 'status-error' ?>">
                                Database: <?= $stats['system_status']['database'] ? 'OK' : 'Error' ?>
                            </div>
                            <div class="status-item <?= $stats['system_status']['storage'] ? 'status-ok' : 'status-error' ?>">
                                Storage: <?= $stats['system_status']['storage'] ? 'OK' : 'Error' ?>
                            </div>
                            <div class="status-item <?= $stats['system_status']['logging'] ? 'status-ok' : 'status-error' ?>">
                                Logging: <?= $stats['system_status']['logging'] ? 'OK' : 'Error' ?>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-section">
                        <h3>Recent Activity</h3>
                        <div class="activity-list">
                            <?php foreach (array_slice($recentLogs, 0, 10) as $log): ?>
                                <div class="activity-item">
                                    <span class="activity-time"><?= date('H:i:s', strtotime($log['timestamp'])) ?></span>
                                    <span class="activity-level activity-<?= $log['level'] ?>"><?= strtoupper($log['level']) ?></span>
                                    <span class="activity-message"><?= htmlspecialchars($log['message']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Tab -->
            <div id="users" class="tab-content">
                <div class="section-header">
                    <h2>User Management</h2>
                    <button class="btn btn-primary" onclick="showCreateUserModal()">Create User</button>
                </div>
                
                <div class="users-table-container">
                    <table class="admin-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Files Tab -->
            <div id="files" class="tab-content">
                <div class="section-header">
                    <h2>File Management</h2>
                    <div class="file-filters">
                        <select id="fileDirectoryFilter">
                            <option value="">All Directories</option>
                            <option value="public">Public</option>
                            <option value="private">Private</option>
                            <option value="temp">Temp</option>
                            <option value="admin">Admin</option>
                        </select>
                        <input type="text" id="fileSearchFilter" placeholder="Search files...">
                    </div>
                </div>
                
                <div class="files-table-container">
                    <table class="admin-table" id="filesTable">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Path</th>
                                <th>Size</th>
                                <th>Owner</th>
                                <th>Upload Date</th>
                                <th>Downloads</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Logs Tab -->
            <div id="logs" class="tab-content">
                <div class="section-header">
                    <h2>System Logs</h2>
                    <div class="log-filters">
                        <select id="logLevelFilter">
                            <option value="">All Levels</option>
                            <option value="error">Error</option>
                            <option value="warning">Warning</option>
                            <option value="info">Info</option>
                            <option value="debug">Debug</option>
                        </select>
                        <button class="btn btn-secondary" onclick="refreshLogs()">Refresh</button>
                        <button class="btn btn-danger" onclick="clearLogs()">Clear Logs</button>
                    </div>
                </div>
                
                <div class="logs-container">
                    <pre id="logsContent">Loading logs...</pre>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="settings" class="tab-content">
                <div class="section-header">
                    <h2>System Settings</h2>
                </div>
                
                <form id="settingsForm" class="settings-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="settings-section">
                        <h3>Upload Settings</h3>
                        <div class="form-group">
                            <label>Max File Size (MB):</label>
                            <input type="number" name="max_file_size" value="<?= $config['upload']['max_file_size'] / 1024 / 1024 ?>">
                        </div>
                        <div class="form-group">
                            <label>Allowed Extensions:</label>
                            <input type="text" name="allowed_extensions" value="<?= implode(', ', $config['upload']['allowed_extensions']) ?>">
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <h3>Security Settings</h3>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="require_login" <?= $config['security']['require_login'] ? 'checked' : '' ?>>
                                Require Login for Private Files
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="enable_public_upload" <?= $config['security']['enable_public_upload'] ? 'checked' : '' ?>>
                                Enable Public Upload
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>

            <!-- Maintenance Tab -->
            <div id="maintenance" class="tab-content">
                <div class="section-header">
                    <h2>System Maintenance</h2>
                </div>
                
                <div class="maintenance-actions">
                    <div class="maintenance-card">
                        <h3>Database Cleanup</h3>
                        <p>Remove orphaned file records and clean up old session data.</p>
                        <button class="btn btn-warning" onclick="runDatabaseCleanup()">Run Cleanup</button>
                    </div>
                    
                    <div class="maintenance-card">
                        <h3>Create Backup</h3>
                        <p>Create a backup of all system data and files.</p>
                        <button class="btn btn-primary" onclick="createBackup()">Create Backup</button>
                    </div>
                    
                    <div class="maintenance-card">
                        <h3>System Information</h3>
                        <p>View detailed system information and diagnostics.</p>
                        <button class="btn btn-secondary" onclick="showSystemInfo()">View Info</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('createUserModal')">&times;</span>
            <h2>Create New User</h2>
            <form id="createUserForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Role:</label>
                    <select name="role">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Create User</button>
            </form>
        </div>
    </div>

    <script src="assets/admin.js"></script>
</body>
</html>

<?php
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $unitIndex = 0;
    
    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }
    
    return round($bytes, 2) . ' ' . $units[$unitIndex];
}
?>
