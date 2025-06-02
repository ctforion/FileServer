<?php
require_once 'includes/config.php';
require_once 'includes/auth-functions.php';
require_once 'includes/file-functions.php';
require_once 'includes/log-functions.php';
require_once 'includes/json-functions.php';
require_once 'includes/user-functions.php';

// Start session and check authentication
session_start();
require_authentication();

$current_user = get_current_user();
$user_role = $current_user['role'] ?? 'user';

// Get dashboard statistics
$total_files = count(read_json_file(STORAGE_DIR . '/data/files.json'));
$user_files = get_user_files($current_user['id']);
$total_uploads = count($user_files);
$total_size = calculate_user_storage($current_user['id']);

// Get recent activity
$recent_files = array_slice(array_reverse($user_files), 0, 5);

// Log dashboard access
log_access("Dashboard viewed by user: " . $current_user['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FileServer</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/forms.css">
</head>
<body>
    <?php include 'templates/header.html'; ?>
    <?php include 'templates/navigation.html'; ?>
    
    <div class="container">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($current_user['username']); ?>!</h1>
            <p class="user-role">Role: <?php echo ucfirst(htmlspecialchars($user_role)); ?></p>
        </div>

        <div class="dashboard-stats">
            <div class="stat-card">
                <h3>Your Files</h3>
                <div class="stat-number"><?php echo $total_uploads; ?></div>
                <p>Files uploaded</p>
            </div>
            
            <div class="stat-card">
                <h3>Storage Used</h3>
                <div class="stat-number"><?php echo format_file_size($total_size); ?></div>
                <p>Total storage</p>
            </div>
            
            <?php if ($user_role === 'admin'): ?>
            <div class="stat-card">
                <h3>Total Files</h3>
                <div class="stat-number"><?php echo $total_files; ?></div>
                <p>System-wide</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-actions">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <a href="upload.php" class="btn btn-primary">
                    <span class="icon">📁</span>
                    Upload Files
                </a>
                <a href="file-browser.php" class="btn btn-secondary">
                    <span class="icon">🗂️</span>
                    Browse Files
                </a>
                <a href="search.php" class="btn btn-secondary">
                    <span class="icon">🔍</span>
                    Search Files
                </a>
                <?php if ($user_role === 'admin'): ?>
                <a href="admin.php" class="btn btn-warning">
                    <span class="icon">⚙️</span>
                    Admin Panel
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($recent_files)): ?>
        <div class="recent-files">
            <h2>Recent Files</h2>
            <div class="file-list">
                <?php foreach ($recent_files as $file): ?>
                <div class="file-item">
                    <div class="file-info">
                        <span class="file-name"><?php echo htmlspecialchars($file['name']); ?></span>
                        <span class="file-size"><?php echo format_file_size($file['size']); ?></span>
                    </div>
                    <div class="file-date">
                        <?php echo date('M j, Y g:i A', strtotime($file['uploaded_at'])); ?>
                    </div>
                    <div class="file-actions">
                        <a href="api/download.php?id=<?php echo $file['id']; ?>" class="btn-small">Download</a>
                        <a href="file-browser.php?id=<?php echo $file['id']; ?>" class="btn-small">Details</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'templates/footer.html'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>
