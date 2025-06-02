<?php
/**
 * Simple test to verify the fixed login functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing fixed login.php functionality...\n\n";

// Simulate the exact POST request that would come from the form
session_start();

try {
    // Include the login dependencies
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
    
    // Simulate the GET request first (generate CSRF token)
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $csrfToken = $security->generateCSRFToken();
    echo "Step 1: CSRF token generated: " . substr($csrfToken, 0, 20) . "...\n";
    
    // Now simulate the POST request
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_POST = [
        'username' => 'admin',
        'password' => 'admin123',
        'csrf_token' => $csrfToken
    ];
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    echo "Step 2: Simulating login form submission\n";
    echo "Username: $username\n";
    echo "Password: " . str_repeat('*', strlen($password)) . "\n";
    echo "CSRF Token: " . substr($submittedToken, 0, 20) . "...\n\n";
    
    // Test the FIXED CSRF validation
    echo "Step 3: Testing FIXED CSRF validation\n";
    try {
        if (!$security->validateCSRFToken($submittedToken)) {
            throw new Exception('Invalid security token');
        }
        echo "✓ CSRF validation: PASSED\n\n";
    } catch (Exception $e) {
        echo "✗ CSRF validation: FAILED - " . $e->getMessage() . "\n\n";
        throw $e;
    }
    
    // Test rate limiting
    echo "Step 4: Testing rate limiting\n";
    if (!$security->checkRateLimit($clientIp, 'login', 5, 900)) {
        echo "✗ Rate limit: FAILED\n\n";
        throw new Exception('Too many login attempts. Please try again later.');
    }
    echo "✓ Rate limit: PASSED\n\n";
    
    // Test input validation
    echo "Step 5: Testing input validation\n";
    if (empty($username) || empty($password)) {
        echo "✗ Input validation: FAILED\n\n";
        throw new Exception('Username and password are required');
    }
    echo "✓ Input validation: PASSED\n\n";
    
    // Test authentication
    echo "Step 6: Testing authentication\n";
    $authResult = $userManager->authenticateUser($username, $password);
    
    if (!$authResult['success']) {
        echo "✗ Authentication: FAILED - " . ($authResult['message'] ?? 'Invalid credentials') . "\n\n";
        throw new Exception($authResult['message'] ?? 'Invalid username or password');
    }
    
    $user = $authResult['user'];
    echo "✓ Authentication: PASSED\n";
    echo "  User: " . $user['username'] . " (Role: " . $user['role'] . ")\n\n";
    
    // Set session (simulate successful login)
    echo "Step 7: Setting session variables\n";
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    echo "✓ Session variables set successfully\n\n";
    
    echo "🎉 LOGIN FUNCTIONALITY TEST COMPLETED SUCCESSFULLY!\n";
    echo "==================================================\n";
    echo "The CSRF token issue has been fixed.\n";
    echo "Login form should now work correctly.\n\n";
    
    echo "SUMMARY:\n";
    echo "--------\n";
    echo "• CSRF token generation: ✓ WORKING\n";
    echo "• CSRF token validation: ✓ FIXED\n";
    echo "• Rate limiting: ✓ WORKING\n";
    echo "• Input validation: ✓ WORKING\n";
    echo "• User authentication: ✓ WORKING\n";
    echo "• Session management: ✓ WORKING\n\n";
    
    echo "The login.php form should now accept admin/admin123 credentials correctly.\n";
    
} catch (Exception $e) {
    echo "❌ LOGIN TEST FAILED!\n";
    echo "===================\n";
    echo "Error: " . $e->getMessage() . "\n\n";
}

echo "\nTest completed.\n";
?>
