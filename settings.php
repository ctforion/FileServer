<?php
require_once 'includes/config.php';
require_once 'includes/auth-functions.php';
require_once 'includes/json-functions.php';
require_once 'includes/log-functions.php';
require_once 'includes/validation-functions.php';

// Start session and check authentication
session_start();
require_authentication();

$current_user = get_current_user();
$user_role = $current_user['role'] ?? 'user';
$success_message = '';
$error_message = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Update user profile
        $new_username = trim($_POST['username'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $update_data = [];
        
        // Validate current password if changing sensitive data
        if ($current_user['password'] !== $current_password) {
            $error_message = "Current password is incorrect.";
        } else {
            // Update username if changed
            if ($new_username !== $current_user['username']) {
                if (user_exists($new_username)) {
                    $error_message = "Username already exists.";
                } else {
                    $update_data['username'] = $new_username;
                }
            }
            
            // Update email
            if ($new_email !== ($current_user['email'] ?? '')) {
                $update_data['email'] = $new_email;
            }
            
            // Update password if provided
            if (!empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    $error_message = "New passwords do not match.";
                } else {
                    $update_data['password'] = $new_password;
                }
            }
            
            // Apply updates
            if (empty($error_message) && !empty($update_data)) {
                $result = update_user($current_user['id'], $update_data);
                if ($result['success']) {
                    $success_message = "Profile updated successfully.";
                    log_access("Profile updated by user: " . $current_user['username']);
                    // Refresh current user data
                    $current_user = get_current_user();
                } else {
                    $error_message = $result['message'];
                }
            } elseif (empty($update_data)) {
                $error_message = "No changes were made.";
            }
        }
    } elseif ($action === 'update_system_settings' && $user_role === 'admin') {
        // Update system settings (admin only)
        $site_name = trim($_POST['site_name'] ?? '');
        $max_file_size = (int)($_POST['max_file_size'] ?? 0);
        $allowed_types = $_POST['allowed_types'] ?? [];
        $enable_registration = isset($_POST['enable_registration']);
        
        $config_data = read_json_file(STORAGE_DIR . '/data/config.json');
        $config_data['site_name'] = $site_name;
        $config_data['max_file_size'] = $max_file_size;
        $config_data['allowed_file_types'] = $allowed_types;
        $config_data['enable_registration'] = $enable_registration;
        $config_data['updated_at'] = date('Y-m-d H:i:s');
        
        if (write_json_file(STORAGE_DIR . '/data/config.json', $config_data)) {
            $success_message = "System settings updated successfully.";
            log_admin_action("System settings updated by admin: " . $current_user['username']);
        } else {
            $error_message = "Failed to update system settings.";
        }
    }
}

// Get current system configuration
$system_config = read_json_file(STORAGE_DIR . '/data/config.json');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - FileServer</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/forms.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <?php include 'templates/header.html'; ?>
    <?php include 'templates/navigation.html'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Settings</h1>
            <div class="page-actions">
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
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

        <div class="settings-container">
            <!-- User Profile Settings -->
            <div class="settings-section">
                <h2>Profile Settings</h2>
                
                <form method="POST" class="settings-form">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" 
                               value="<?php echo htmlspecialchars($current_user['username']); ?>" 
                               class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>" 
                               class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="current_password">Current Password:</label>
                        <input type="password" id="current_password" name="current_password" 
                               class="form-control" required 
                               placeholder="Enter current password to confirm changes">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" 
                               class="form-control" 
                               placeholder="Leave blank to keep current password">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="form-control" 
                               placeholder="Confirm new password">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>

            <!-- Account Information -->
            <div class="settings-section">
                <h2>Account Information</h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">User ID:</span>
                        <span class="info-value"><?php echo htmlspecialchars($current_user['id']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Role:</span>
                        <span class="info-value"><?php echo ucfirst(htmlspecialchars($user_role)); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Account Created:</span>
                        <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($current_user['created_at'])); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Files Uploaded:</span>
                        <span class="info-value"><?php echo count(get_user_files($current_user['id'])); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Storage Used:</span>
                        <span class="info-value"><?php echo format_file_size(calculate_user_storage($current_user['id'])); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($user_role === 'admin'): ?>
            <!-- System Settings (Admin Only) -->
            <div class="settings-section admin-settings">
                <h2>System Settings</h2>
                <p class="section-description">Configure global system settings (Admin only)</p>
                
                <form method="POST" class="settings-form">
                    <input type="hidden" name="action" value="update_system_settings">
                    
                    <div class="form-group">
                        <label for="site_name">Site Name:</label>
                        <input type="text" id="site_name" name="site_name" 
                               value="<?php echo htmlspecialchars($system_config['site_name'] ?? 'FileServer'); ?>" 
                               class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="max_file_size">Max File Size (MB):</label>
                        <input type="number" id="max_file_size" name="max_file_size" 
                               value="<?php echo (int)($system_config['max_file_size'] ?? 10); ?>" 
                               class="form-control" min="1" max="1000">
                    </div>
                    
                    <div class="form-group">
                        <label>Allowed File Types:</label>
                        <div class="checkbox-group">
                            <?php 
                            $allowed_types = $system_config['allowed_file_types'] ?? ['image', 'document', 'archive'];
                            $file_types = [
                                'image' => 'Images (jpg, png, gif, etc.)',
                                'document' => 'Documents (pdf, doc, txt, etc.)',
                                'archive' => 'Archives (zip, rar, etc.)',
                                'video' => 'Videos (mp4, avi, etc.)',
                                'audio' => 'Audio (mp3, wav, etc.)'
                            ];
                            ?>
                            <?php foreach ($file_types as $type => $description): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="allowed_types[]" value="<?php echo $type; ?>"
                                       <?php echo in_array($type, $allowed_types) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                <?php echo htmlspecialchars($description); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="enable_registration" value="1"
                                   <?php echo ($system_config['enable_registration'] ?? true) ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Enable user registration
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Update System Settings</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'templates/footer.html'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword && confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
