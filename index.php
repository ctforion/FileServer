<?php
session_start();

require_once 'config.php';
require_once 'core/database/DatabaseManager.php';
require_once 'core/auth/UserManager.php';
require_once 'core/logging/Logger.php';
require_once 'core/utils/SecurityManager.php';
require_once 'core/utils/EnvLoader.php';

// Initialize managers
$config = require 'config.php';
// Initialize EnvLoader with the config data
foreach ($config as $key => $value) {
    EnvLoader::set($key, $value);
}
$dbManager = DatabaseManager::getInstance();
$userManager = new UserManager();
$logger = new Logger($config['logging']['log_path']);
$security = new SecurityManager();

// Handle logout
if (isset($_GET['logout'])) {
    if (isset($_SESSION['user_id'])) {
        $logger->info('User logged out', [
            'user_id' => $_SESSION['user_id'],
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: web/login.php');
    exit;
}

$currentUser = $userManager->getUserById($_SESSION['user_id']);
if (!$currentUser || $currentUser['status'] !== 'active') {
    session_destroy();
    header('Location: web/login.php');
    exit;
}

// Generate CSRF token for forms
$csrfToken = $security->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Storage Server</title>
    <link rel="stylesheet" href="web/assets/style.css">
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>File Storage Server</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($currentUser['username']); ?>!</span>
                <a href="?logout=1" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Upload Area -->
        <div class="upload-area">
            <h2>Upload Files</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <div id="uploadZone" class="upload-zone">
                    <div class="upload-icon">📁</div>
                    <div class="upload-text">Click to select files or drag and drop</div>
                    <div class="upload-subtext">Maximum file size: <?php echo round(EnvLoader::getMaxFileSize() / 1024 / 1024); ?>MB</div>
                    <input type="file" id="fileInput" name="file" style="display: none;">
                </div>
                <div id="uploadProgress" class="progress hidden">
                    <div class="progress-bar" style="width: 0%"></div>
                </div>
            </form>
        </div>

        <!-- Navigation Tabs -->
        <div class="nav-tabs">
            <a href="#" class="nav-tab active" data-directory="private">Private Files</a>
            <a href="#" class="nav-tab" data-directory="public">Public Files</a>
            <a href="#" class="nav-tab" data-directory="temp">Temporary Files</a>
        </div>

        <!-- File List -->
        <div class="file-list">
            <div class="file-list-header">
                <h2>Files</h2>
                <button onclick="fileManager.loadFiles()" class="btn btn-secondary btn-small">Refresh</button>
            </div>
            <div id="fileList">
                <div class="file-item">
                    <div class="file-info">Loading files...</div>
                </div>
            </div>
            <div id="pagination" class="pagination"></div>
        </div>
    </div>

    <script src="web/assets/script.js"></script>
</body>
</html>
