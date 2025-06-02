<?php
require_once 'includes/config.php';
require_once 'includes/auth-functions.php';
require_once 'includes/user-functions.php';
require_once 'includes/log-functions.php';
require_once 'includes/validation-functions.php';

// Start session and check admin authentication
session_start();
require_authentication();
require_admin_role();

$current_user = get_current_user();
$action = $_GET['action'] ?? 'list';
$edit_user_id = $_GET['edit'] ?? '';
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['action'] ?? '';
    
    if ($form_action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        
        // Validate input
        if (empty($username) || empty($password)) {
            $error_message = "Username and password are required.";
        } elseif (user_exists($username)) {
            $error_message = "Username already exists.";
        } else {
            $result = create_user($username, $password, $email, $role);
            if ($result['success']) {
                $success_message = "User created successfully.";
                log_admin_action("User created: " . $username . " by admin: " . $current_user['username']);
            } else {
                $error_message = $result['message'];
            }
        }
    } elseif ($form_action === 'update_user') {
        $user_id = $_POST['user_id'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'user';
        $new_password = $_POST['new_password'] ?? '';
        
        if (!empty($user_id)) {
            $update_data = [
                'username' => $username,
                'email' => $email,
                'role' => $role
            ];
            
            if (!empty($new_password)) {
                $update_data['password'] = $new_password;
            }
            
            $result = update_user($user_id, $update_data);
            if ($result['success']) {
                $success_message = "User updated successfully.";
                log_admin_action("User updated: " . $username . " by admin: " . $current_user['username']);
            } else {
                $error_message = $result['message'];
            }
        }
    }
}

// Get user for editing
$edit_user = null;
if (!empty($edit_user_id)) {
    $edit_user = get_user_by_id($edit_user_id);
    $action = 'edit';
}

// Get all users for listing
$all_users = get_all_users();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - FileServer</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/forms.css">
</head>
<body>
    <?php include 'templates/header.html'; ?>
    <?php include 'templates/navigation.html'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>User Management</h1>
            <div class="page-actions">
                <a href="admin.php?action=users" class="btn btn-secondary">Back to Admin</a>
                <?php if ($action !== 'create'): ?>
                <a href="?action=create" class="btn btn-primary">Add New User</a>
                <?php endif; ?>
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

        <?php if ($action === 'create'): ?>
        <!-- Create User Form -->
        <div class="form-section">
            <h2>Create New User</h2>
            <form method="POST" class="user-form">
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required class="form-control" 
                           placeholder="Enter username">
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="Enter email (optional)">
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required class="form-control" 
                           placeholder="Enter password">
                </div>
                
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" class="form-control">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create User</button>
                    <a href="?action=list" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <?php elseif ($action === 'edit' && $edit_user): ?>
        <!-- Edit User Form -->
        <div class="form-section">
            <h2>Edit User: <?php echo htmlspecialchars($edit_user['username']); ?></h2>
            <form method="POST" class="user-form">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($edit_user['id']); ?>">
                
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required class="form-control" 
                           value="<?php echo htmlspecialchars($edit_user['username']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_user['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" 
                           placeholder="Leave blank to keep current password">
                </div>
                
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" class="form-control">
                        <option value="user" <?php echo $edit_user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="admin" <?php echo $edit_user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                
                <div class="user-stats">
                    <h3>User Statistics</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-label">User ID:</span>
                            <span class="stat-value"><?php echo htmlspecialchars($edit_user['id']); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Created:</span>
                            <span class="stat-value"><?php echo date('M j, Y g:i A', strtotime($edit_user['created_at'])); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Files:</span>
                            <span class="stat-value"><?php echo count(get_user_files($edit_user['id'])); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Storage:</span>
                            <span class="stat-value"><?php echo format_file_size(calculate_user_storage($edit_user['id'])); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update User</button>
                    <a href="?action=list" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <?php else: ?>
        <!-- List Users -->
        <div class="users-section">
            <h2>All Users</h2>
            <div class="user-stats-summary">
                <div class="summary-stat">
                    <span class="summary-number"><?php echo count($all_users); ?></span>
                    <span class="summary-label">Total Users</span>
                </div>
                <div class="summary-stat">
                    <span class="summary-number">
                        <?php echo count(array_filter($all_users, function($u) { return $u['role'] === 'admin'; })); ?>
                    </span>
                    <span class="summary-label">Administrators</span>
                </div>
                <div class="summary-stat">
                    <span class="summary-number">
                        <?php echo count(array_filter($all_users, function($u) { return $u['role'] === 'user'; })); ?>
                    </span>
                    <span class="summary-label">Regular Users</span>
                </div>
            </div>
            
            <?php include 'templates/user-list.html'; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'templates/footer.html'; ?>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
