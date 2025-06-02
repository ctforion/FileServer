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

// Get user statistics
$userStats = $userManager->getUserStats($currentUser['username']);

// Get user files
$userFiles = $userManager->getUserFiles($currentUser['username']);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
                case 'update_profile':
                    $profileData = [
                        'first_name' => $_POST['first_name'] ?? '',
                        'last_name' => $_POST['last_name'] ?? '',
                    ];
                    
                    $result = $userManager->updateUserProfile($currentUser['username'], ['profile' => $profileData]);
                    if ($result['success']) {
                        $message = 'Profile updated successfully';
                        $messageType = 'success';
                        $currentUser = $userManager->getUserById($_SESSION['user_id']); // Refresh user data
                    } else {
                        $message = $result['message'] ?? 'Failed to update profile';
                        $messageType = 'error';
                    }
                    break;
                    
                case 'update_settings':
                    $settingsData = [
                        'theme' => $_POST['theme'] ?? 'default',
                        'language' => $_POST['language'] ?? 'en',
                        'timezone' => $_POST['timezone'] ?? 'UTC',
                        'notifications' => isset($_POST['notifications']),
                    ];
                    
                    $result = $userManager->updateUserSettings($currentUser['username'], $settingsData);
                    if ($result['success']) {
                        $message = 'Settings updated successfully';
                        $messageType = 'success';
                        $currentUser = $userManager->getUserById($_SESSION['user_id']); // Refresh user data
                    } else {
                        $message = $result['message'] ?? 'Failed to update settings';
                        $messageType = 'error';
                    }
                    break;
                    
                case 'change_password':
                    $currentPassword = $_POST['current_password'] ?? '';
                    $newPassword = $_POST['new_password'] ?? '';
                    $confirmPassword = $_POST['confirm_password'] ?? '';
                    
                    if ($newPassword !== $confirmPassword) {
                        $message = 'New passwords do not match';
                        $messageType = 'error';
                    } else {
                        $result = $userManager->changePassword($currentUser['username'], $currentPassword, $newPassword);
                        if ($result['success']) {
                            $message = 'Password changed successfully';
                            $messageType = 'success';
                        } else {
                            $message = $result['message'] ?? 'Failed to change password';
                            $messageType = 'error';
                        }
                    }
                    break;
            }        } catch (Exception $e) {
            $message = 'An error occurred: ' . $e->getMessage();
            $messageType = 'error';
        }
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    return number_format($bytes / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - FileServer</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/profile.css">
</head>
<body>
    <div class="profile-layout">
        <!-- Header -->
        <header class="profile-header">
            <div class="header-content">
                <h1>User Profile</h1>
                <div class="header-actions">
                    <a href="../index.php" class="btn btn-secondary">← Back to Files</a>
                    <a href="../index.php?logout=1" class="btn btn-secondary">Logout</a>
                </div>
            </div>
        </header>

        <!-- Navigation -->
        <nav class="profile-nav">
            <ul class="nav-tabs">
                <li><a href="#overview" class="active">Overview</a></li>
                <li><a href="#profile" >Profile</a></li>
                <li><a href="#settings">Settings</a></li>
                <li><a href="#password">Password</a></li>
                <li><a href="#files">My Files</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="profile-main">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Overview Tab -->
            <div id="overview" class="tab-content active">
                <div class="overview-section">
                    <h2>Account Overview</h2>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>Total Files</h3>
                            <div class="stat-value"><?= $userStats['file_count'] ?? 0 ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Storage Used</h3>
                            <div class="stat-value"><?= formatFileSize($userStats['total_size'] ?? 0) ?></div>
                            <div class="stat-subtitle">of <?= formatFileSize($currentUser['quota'] ?? 0) ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Active Sessions</h3>
                            <div class="stat-value"><?= $userStats['active_sessions'] ?? 0 ?></div>
                        </div>
                        <div class="stat-card">
                            <h3>Last Login</h3>
                            <div class="stat-value">
                                <?= $userStats['last_login'] ? date('M j, Y', strtotime($userStats['last_login'])) : 'Never' ?>
                            </div>
                        </div>
                    </div>

                    <div class="account-info">
                        <h3>Account Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Username:</label>
                                <span><?= htmlspecialchars($currentUser['username']) ?></span>
                            </div>
                            <div class="info-item">
                                <label>Email:</label>
                                <span><?= htmlspecialchars($currentUser['email']) ?></span>
                            </div>
                            <div class="info-item">
                                <label>Role:</label>
                                <span class="role-badge role-<?= htmlspecialchars($currentUser['role']) ?>">
                                    <?= ucfirst(htmlspecialchars($currentUser['role'])) ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Status:</label>
                                <span class="status-badge status-<?= htmlspecialchars($currentUser['status']) ?>">
                                    <?= ucfirst(htmlspecialchars($currentUser['status'])) ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label>Member Since:</label>
                                <span><?= isset($currentUser['created']) ? date('M j, Y', strtotime($currentUser['created'])) : 'Unknown' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Tab -->
            <div id="profile" class="tab-content">
                <div class="form-section">
                    <h2>Profile Information</h2>                    <form method="POST" class="profile-form">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name:</label>
                                <input type="text" id="first_name" name="first_name" 
                                       value="<?= htmlspecialchars($currentUser['profile']['first_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name:</label>
                                <input type="text" id="last_name" name="last_name" 
                                       value="<?= htmlspecialchars($currentUser['profile']['last_name'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" 
                                   value="<?= htmlspecialchars($currentUser['email']) ?>" readonly>
                            <small>Email cannot be changed. Contact administrator if needed.</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="settings" class="tab-content">
                <div class="form-section">
                    <h2>User Settings</h2>                    <form method="POST" class="profile-form">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="form-group">
                            <label for="theme">Theme:</label>
                            <select id="theme" name="theme">
                                <option value="default" <?= ($currentUser['settings']['theme'] ?? 'default') === 'default' ? 'selected' : '' ?>>Default</option>
                                <option value="dark" <?= ($currentUser['settings']['theme'] ?? 'default') === 'dark' ? 'selected' : '' ?>>Dark</option>
                                <option value="light" <?= ($currentUser['settings']['theme'] ?? 'default') === 'light' ? 'selected' : '' ?>>Light</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="language">Language:</label>
                            <select id="language" name="language">
                                <option value="en" <?= ($currentUser['settings']['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="es" <?= ($currentUser['settings']['language'] ?? 'en') === 'es' ? 'selected' : '' ?>>Spanish</option>
                                <option value="fr" <?= ($currentUser['settings']['language'] ?? 'en') === 'fr' ? 'selected' : '' ?>>French</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="timezone">Timezone:</label>
                            <select id="timezone" name="timezone">
                                <option value="UTC" <?= ($currentUser['settings']['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                <option value="America/New_York" <?= ($currentUser['settings']['timezone'] ?? 'UTC') === 'America/New_York' ? 'selected' : '' ?>>Eastern Time</option>
                                <option value="America/Chicago" <?= ($currentUser['settings']['timezone'] ?? 'UTC') === 'America/Chicago' ? 'selected' : '' ?>>Central Time</option>
                                <option value="America/Denver" <?= ($currentUser['settings']['timezone'] ?? 'UTC') === 'America/Denver' ? 'selected' : '' ?>>Mountain Time</option>
                                <option value="America/Los_Angeles" <?= ($currentUser['settings']['timezone'] ?? 'UTC') === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="notifications" 
                                       <?= ($currentUser['settings']['notifications'] ?? true) ? 'checked' : '' ?>>
                                Enable notifications
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Settings</button>
                    </form>
                </div>
            </div>

            <!-- Password Tab -->
            <div id="password" class="tab-content">
                <div class="form-section">
                    <h2>Change Password</h2>
                    <form method="POST" class="profile-form">                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password:</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password:</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <small>Password must be at least 8 characters long with uppercase, lowercase, number, and special character.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password:</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>

            <!-- Files Tab -->
            <div id="files" class="tab-content">
                <div class="files-section">
                    <h2>My Files</h2>
                    
                    <div class="files-actions">
                        <a href="upload.php" class="btn btn-primary">Upload New File</a>
                        <div class="search-box">
                            <input type="text" id="file-search" placeholder="Search files...">
                        </div>
                    </div>
                    
                    <div class="files-table-container">
                        <table class="files-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Size</th>
                                    <th>Type</th>
                                    <th>Uploaded</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($userFiles)): ?>
                                    <tr>
                                        <td colspan="5" class="no-files">No files found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($userFiles as $file): ?>
                                        <tr>
                                            <td class="file-name">
                                                <span class="file-icon">📄</span>
                                                <?= htmlspecialchars($file['filename'] ?? 'Unknown') ?>
                                            </td>
                                            <td><?= formatFileSize($file['size'] ?? 0) ?></td>
                                            <td><?= htmlspecialchars($file['type'] ?? 'Unknown') ?></td>
                                            <td><?= isset($file['uploaded']) ? date('M j, Y', strtotime($file['uploaded'])) : 'Unknown' ?></td>
                                            <td class="file-actions">                                                <a href="../api/download.php?file=<?= urlencode($file['filename'] ?? '') ?>" 
                                                   class="btn btn-sm btn-secondary">Download</a>
                                                <button class="btn btn-sm btn-danger" onclick="deleteFile('<?= htmlspecialchars($file['filename'] ?? '') ?>')">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/profile.js"></script>
</body>
</html>
