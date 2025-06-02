<?php
include_once 'includes/config.php';
include_once 'includes/functions.php';
include_once 'includes/json-functions.php';
include_once 'includes/auth-functions.php';
include_once 'includes/log-functions.php';

$page_title = 'Home';

// Log page access
log_access('index_page', 'Home page accessed');
?>

<?php include 'templates/header.html'; ?>

<?php include 'templates/navigation.html'; ?>

<div class="welcome-section">
    <?php if (is_logged_in()): ?>
        <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
        <p>You are logged in as <?php echo htmlspecialchars($_SESSION['role']); ?>.</p>
        
        <div class="quick-actions">
            <h3>Quick Actions</h3>
            <div class="action-buttons">
                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                <a href="file-browser.php" class="btn btn-secondary">Browse Files</a>
                <a href="upload.php" class="btn btn-secondary">Upload Files</a>
                <?php if (is_admin()): ?>
                    <a href="admin.php" class="btn btn-admin">Admin Panel</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="recent-activity">
            <h3>Recent Activity</h3>
            <?php
            $recent_logs = get_recent_logs(5);
            if (!empty($recent_logs)):
            ?>
                <ul class="activity-list">
                    <?php foreach ($recent_logs as $log): ?>
                        <?php if ($log['username'] === $_SESSION['username'] || is_admin()): ?>
                            <li class="activity-item">
                                <span class="activity-time"><?php echo htmlspecialchars($log['timestamp']); ?></span>
                                <span class="activity-action"><?php echo htmlspecialchars($log['action']); ?></span>
                                <?php if ($log['details']): ?>
                                    <span class="activity-details"><?php echo htmlspecialchars($log['details']); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No recent activity.</p>
            <?php endif; ?>
        </div>
        
    <?php else: ?>
        <h2>Welcome to FileServer</h2>
        <p>A simple and secure file storage solution.</p>
        
        <div class="features">
            <h3>Features</h3>
            <ul class="feature-list">
                <li>Secure file upload and storage</li>
                <li>User management and permissions</li>
                <li>File sharing capabilities</li>
                <li>Advanced search and filtering</li>
                <li>Admin tools and monitoring</li>
                <li>Automatic backups and versioning</li>
            </ul>
        </div>
        
        <div class="auth-actions">
            <h3>Get Started</h3>
            <div class="action-buttons">
                <a href="login.php" class="btn btn-primary">Login</a>
                <a href="register.php" class="btn btn-secondary">Register</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="system-info">
    <h3>System Information</h3>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Disk Space Available:</span>
            <span class="info-value"><?php echo format_file_size(disk_free_space('.')); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Server Time:</span>
            <span class="info-value"><?php echo get_current_timestamp(); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Version:</span>
            <span class="info-value"><?php echo $config['app_version']; ?></span>
        </div>
    </div>
</div>

<?php include 'templates/footer.html'; ?>
