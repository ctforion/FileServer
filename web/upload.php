<?php
session_start();

require_once '../config.php';
require_once '../core/database/DatabaseManager.php';
require_once '../core/auth/UserManager.php';
require_once '../core/logging/Logger.php';
require_once '../core/utils/SecurityManager.php';

// Initialize managers
$config = require '../config.php';
$dbManager = DatabaseManager::getInstance();
$userManager = new UserManager();
$logger = new Logger($config['logging']['log_path']);
$security = new SecurityManager();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $userManager->getUserById($_SESSION['user_id']);
if (!$currentUser || $currentUser['status'] !== 'active') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get user statistics for quota checking
$userStats = $userManager->getUserStats($currentUser['username']);

$csrfToken = $security->generateCSRFToken();

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    return number_format($bytes / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
}

// Calculate quota usage
$quotaUsed = $userStats['total_size'] ?? 0;
$quotaTotal = $currentUser['quota'] ?? 1073741824; // 1GB default
$quotaPercent = $quotaTotal > 0 ? ($quotaUsed / $quotaTotal) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Files - FileServer</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/upload.css">
</head>
<body>
    <div class="upload-layout">
        <!-- Header -->
        <header class="upload-header">
            <div class="header-content">
                <h1>Upload Files</h1>
                <div class="header-actions">
                    <a href="../index.php" class="btn btn-secondary">← Back to Files</a>
                    <a href="profile.php" class="btn btn-secondary">Profile</a>
                    <a href="../index.php?logout=1" class="btn btn-secondary">Logout</a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="upload-main">
            <!-- Quota Information -->
            <div class="quota-section">
                <h2>Storage Quota</h2>
                <div class="quota-info">
                    <div class="quota-bar">
                        <div class="quota-used" style="width: <?= min(100, $quotaPercent) ?>%"></div>
                    </div>
                    <div class="quota-details">
                        <span class="quota-text">
                            <?= formatFileSize($quotaUsed) ?> of <?= formatFileSize($quotaTotal) ?> used
                            (<?= number_format($quotaPercent, 1) ?>%)
                        </span>
                        <span class="quota-remaining">
                            <?= formatFileSize($quotaTotal - $quotaUsed) ?> remaining
                        </span>
                    </div>
                </div>
            </div>

            <!-- Upload Section -->
            <div class="upload-section">
                <h2>Upload New Files</h2>
                
                <!-- Upload Area -->
                <div class="upload-area" id="uploadArea">
                    <div class="upload-icon">📁</div>
                    <h3>Drop files here or click to browse</h3>
                    <p>Select one or more files to upload</p>
                    <input type="file" id="fileInput" multiple hidden>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                        Choose Files
                    </button>
                </div>

                <!-- Upload Options -->
                <div class="upload-options">
                    <h3>Upload Options</h3>
                    <form id="uploadForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        
                        <div class="form-group">
                            <label for="directory">Upload to Directory:</label>
                            <select id="directory" name="directory">
                                <option value="private">Private (<?= htmlspecialchars($currentUser['username']) ?> only)</option>
                                <option value="public">Public (Everyone can view)</option>
                                <?php if ($currentUser['role'] === 'admin'): ?>
                                    <option value="admin">Admin (Admin users only)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description (Optional):</label>
                            <textarea id="description" name="description" rows="3" 
                                      placeholder="Add a description for these files..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="overwrite" name="overwrite">
                                Overwrite files with same name
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="notify" name="notify" checked>
                                Send notification on completion
                            </label>
                        </div>
                    </form>
                </div>

                <!-- File List -->
                <div class="file-list" id="fileList" style="display: none;">
                    <h3>Selected Files</h3>
                    <div class="file-items" id="fileItems"></div>
                    <div class="upload-actions">
                        <button type="button" class="btn btn-primary" id="uploadBtn" onclick="startUpload()">
                            Upload Files
                        </button>
                        <button type="button" class="btn btn-secondary" id="clearBtn" onclick="clearFiles()">
                            Clear All
                        </button>
                    </div>
                </div>

                <!-- Upload Progress -->
                <div class="upload-progress" id="uploadProgress" style="display: none;">
                    <h3>Upload Progress</h3>
                    <div class="progress-container">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                        <span class="progress-text" id="progressText">0%</span>
                    </div>
                    <div class="upload-status" id="uploadStatus">
                        <div class="status-item">
                            <span class="status-label">Files:</span>
                            <span id="fileProgress">0 / 0</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Speed:</span>
                            <span id="uploadSpeed">0 KB/s</span>
                        </div>
                        <div class="status-item">
                            <span class="status-label">Time remaining:</span>
                            <span id="timeRemaining">--</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-danger" id="cancelBtn" onclick="cancelUpload()">
                        Cancel Upload
                    </button>
                </div>

                <!-- Upload Results -->
                <div class="upload-results" id="uploadResults" style="display: none;">
                    <h3>Upload Results</h3>
                    <div class="results-summary" id="resultsSummary"></div>
                    <div class="results-list" id="resultsList"></div>
                    <div class="results-actions">
                        <button type="button" class="btn btn-primary" onclick="location.href='../index.php'">
                            View Files
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetUpload()">
                            Upload More
                        </button>
                    </div>
                </div>
            </div>

            <!-- Upload Guidelines -->
            <div class="guidelines-section">
                <h2>Upload Guidelines</h2>
                <div class="guidelines-content">
                    <div class="guideline-item">
                        <h4>File Size Limits</h4>
                        <p>Maximum file size: <?= ini_get('upload_max_filesize') ?></p>
                        <p>Maximum total upload: <?= ini_get('post_max_size') ?></p>
                    </div>
                    
                    <div class="guideline-item">
                        <h4>Allowed File Types</h4>
                        <p>
                            <?php
                            $allowedTypes = $config['upload']['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];
                            echo implode(', ', array_map('strtoupper', $allowedTypes));
                            ?>
                        </p>
                    </div>
                    
                    <div class="guideline-item">
                        <h4>Security</h4>
                        <ul>
                            <li>Files are scanned for viruses and malware</li>
                            <li>Executable files are not allowed for security</li>
                            <li>All uploads are logged for security purposes</li>
                        </ul>
                    </div>
                    
                    <div class="guideline-item">
                        <h4>Privacy</h4>
                        <ul>
                            <li><strong>Private:</strong> Only you can access these files</li>
                            <li><strong>Public:</strong> All users can view and download</li>
                            <?php if ($currentUser['role'] === 'admin'): ?>
                                <li><strong>Admin:</strong> Only administrators can access</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/upload.js"></script>
    <script>
        // Pass configuration to JavaScript
        window.uploadConfig = {
            maxFileSize: <?= json_encode(ini_get('upload_max_filesize')) ?>,
            allowedTypes: <?= json_encode($config['upload']['allowed_extensions'] ?? []) ?>,
            csrfToken: <?= json_encode($csrfToken) ?>,
            quotaUsed: <?= $quotaUsed ?>,
            quotaTotal: <?= $quotaTotal ?>
        };
    </script>
</body>
</html>
