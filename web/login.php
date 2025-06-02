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

$error = '';
$success = '';
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
      try {
        // Validate CSRF token
        if (!$security->validateCSRFToken($csrfToken)) {
            throw new Exception('Invalid security token');
        }
          // Rate limiting for login attempts
        if (!$security->checkRateLimit($clientIp, 'login', 5, 900)) { // 5 attempts per 15 minutes
            throw new Exception('Too many login attempts. Please try again later.');
        }
        
        // Validate input
        if (empty($username) || empty($password)) {
            throw new Exception('Username and password are required');
        }
          // Authenticate user using the updated authentication system
        $authResult = $userManager->authenticateUser($username, $password);
        
        if (!$authResult['success']) {
            throw new Exception($authResult['message'] ?? 'Invalid username or password');
        }
        
        $user = $authResult['user'];
        
        // Update login statistics
        $users = $dbManager->getAllUsers();
        foreach ($users as $key => $u) {
            if ($u['username'] === $username) {
                $users[$key]['last_login'] = date('Y-m-d H:i:s');
                $users[$key]['login_count'] = ($u['login_count'] ?? 0) + 1;
                $users[$key]['last_ip'] = $clientIp;
                break;
            }
        }
        $dbManager->saveData('users', array_values($users));
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        // Log successful login
        $logger->info('User logged in successfully', [
            'user_id' => $user['id'],
            'username' => $username,
            'ip' => $clientIp,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // Redirect to main page
        header('Location: ../index.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        
        // Log failed login attempt
        $logger->warning('Login failed', [
            'username' => $username,
            'error' => $error,
            'ip' => $clientIp,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
}

// If already logged in, redirect to main page
if (isset($_SESSION['user_id'])) {
    $currentUser = $userManager->getUserById($_SESSION['user_id']);
    if ($currentUser && $currentUser['status'] === 'active') {
        header('Location: ../index.php');
        exit;
    } else {
        // Invalid session, clear it
        session_destroy();
    }
}

// Generate CSRF token
$csrfToken = $security->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - File Storage Server</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <h1>File Storage Server</h1>
            <h2>Login</h2>
            
            <?php if (isset($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
              <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            
            <div class="login-info">
                <p><small>Default credentials: admin / admin123</small></p>
            </div>
        </div>
    </div>
</body>
</html>
