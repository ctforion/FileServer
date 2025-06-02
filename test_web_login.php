<?php
/**
 * Test script to simulate web login form submission
 */

// Set up the environment to simulate a web form submission
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Test Agent';

// Start session
session_start();

// Include the login page logic
$originalPost = $_POST;
$originalSession = $_SESSION;

echo "=== Testing Web Login Form Submission ===\n\n";

// Test 1: Simulate form submission with admin credentials
echo "Test 1: Testing admin login form submission\n";
echo "----------------------------------------\n";

// Clear session
$_SESSION = [];

try {
    // Set up POST data as if coming from the form
    $_POST = [
        'username' => 'admin',
        'password' => 'admin123',
        'csrf_token' => 'test_token' // We'll need to get a real token
    ];
    
    // Get a real CSRF token first
    require_once 'config.php';
    require_once 'core/utils/SecurityManager.php';
    $security = new SecurityManager();
    $realToken = $security->generateCSRFToken();
    $_POST['csrf_token'] = $realToken;
    
    echo "Simulating POST data:\n";
    echo "Username: " . $_POST['username'] . "\n";
    echo "Password: [hidden]\n";
    echo "CSRF Token: " . substr($_POST['csrf_token'], 0, 10) . "...\n\n";
    
    // Capture output
    ob_start();
    
    // Include the login logic (without the HTML)
    require_once 'config.php';
    require_once 'core/database/DatabaseManager.php';
    require_once 'core/auth/UserManager.php';
    require_once 'core/logging/Logger.php';
    require_once 'core/utils/SecurityManager.php';

    // Initialize managers
    $config = require 'config.php';
    $dbManager = DatabaseManager::getInstance();
    $userManager = new UserManager();
    $logger = new Logger($config['logging']['log_path']);
    $security = new SecurityManager();

    $error = '';
    $success = '';
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Handle login form submission (copy the logic from login.php)
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
            if (!$security->checkRateLimit($clientIp, 'login', 5, 900)) {
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
            
            echo "✅ LOGIN SUCCESSFUL!\n";
            echo "Session data set:\n";
            echo "- User ID: " . $_SESSION['user_id'] . "\n";
            echo "- Username: " . $_SESSION['username'] . "\n";
            echo "- Role: " . $_SESSION['role'] . "\n";
            echo "- Redirect would happen to: ../index.php\n";
            
        } catch (Exception $e) {
            $error = $e->getMessage();
            echo "❌ LOGIN FAILED: " . $error . "\n";
        }
    }
    
    $output = ob_get_clean();
    echo $output;
    
} catch (Exception $e) {
    echo "❌ Error during test: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";

// Restore original state
$_POST = $originalPost;
$_SESSION = $originalSession;
?>
