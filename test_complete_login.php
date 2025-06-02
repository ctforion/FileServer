<?php
/**
 * Comprehensive login test that simulates the entire web form process
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing complete login process...\n\n";

// Start session (simulate browser session)
session_start();

try {
    // Step 1: Load the login page (GET request)
    echo "Step 1: Loading login page (simulating GET request)\n";
    echo "-----------------------------------------------------\n";
    
    // Simulate the GET request part of login.php
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    
    // Include dependencies
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
    
    // Generate CSRF token (simulating the GET part)
    $csrfToken = $security->generateCSRFToken();
    echo "✓ CSRF token generated: " . substr($csrfToken, 0, 20) . "...\n";
    echo "✓ Session CSRF token set: " . ($_SESSION['csrf_token'] ? 'YES' : 'NO') . "\n\n";
    
    // Step 2: Submit login form (POST request)
    echo "Step 2: Submitting login form (simulating POST request)\n";
    echo "-------------------------------------------------------\n";
    
    // Simulate POST request with form data
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'username' => 'admin',
        'password' => 'admin123',
        'csrf_token' => $csrfToken
    ];
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    echo "Username: $username\n";
    echo "Password: " . str_repeat('*', strlen($password)) . "\n";
    echo "CSRF Token: " . substr($submittedCsrfToken, 0, 20) . "...\n";
    echo "Client IP: $clientIp\n\n";
    
    // Validate CSRF token
    echo "Step 3: Validating CSRF token\n";
    echo "-----------------------------\n";
    try {
        $csrfValid = $security->validateCSRFToken($submittedCsrfToken);
        echo "✓ CSRF token validation: PASSED\n\n";
    } catch (Exception $e) {
        echo "✗ CSRF token validation: FAILED - " . $e->getMessage() . "\n\n";
        throw $e;
    }
    
    // Rate limiting check
    echo "Step 4: Checking rate limits\n";
    echo "----------------------------\n";
    if (!$security->checkRateLimit($clientIp, 'login', 5, 900)) {
        echo "✗ Rate limit check: FAILED - Too many attempts\n\n";
        throw new Exception('Too many login attempts. Please try again later.');
    }
    echo "✓ Rate limit check: PASSED\n\n";
    
    // Validate input
    echo "Step 5: Validating input\n";
    echo "------------------------\n";
    if (empty($username) || empty($password)) {
        echo "✗ Input validation: FAILED - Missing username or password\n\n";
        throw new Exception('Username and password are required');
    }
    echo "✓ Input validation: PASSED\n\n";
    
    // Authenticate user
    echo "Step 6: Authenticating user\n";
    echo "---------------------------\n";
    $authResult = $userManager->authenticateUser($username, $password);
    
    if (!$authResult['success']) {
        echo "✗ Authentication: FAILED - " . ($authResult['message'] ?? 'Invalid credentials') . "\n\n";
        throw new Exception($authResult['message'] ?? 'Invalid username or password');
    }
    
    $user = $authResult['user'];
    echo "✓ Authentication: PASSED\n";
    echo "  User ID: " . $user['id'] . "\n";
    echo "  Username: " . $user['username'] . "\n";
    echo "  Role: " . $user['role'] . "\n";
    echo "  Status: " . $user['status'] . "\n\n";
    
    // Set session variables
    echo "Step 7: Setting session variables\n";
    echo "---------------------------------\n";
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    
    echo "✓ Session variables set:\n";
    echo "  user_id: " . $_SESSION['user_id'] . "\n";
    echo "  username: " . $_SESSION['username'] . "\n";
    echo "  role: " . $_SESSION['role'] . "\n";
    echo "  logged_in: " . ($_SESSION['logged_in'] ? 'true' : 'false') . "\n\n";
    
    // Update login statistics (simulating the database update)
    echo "Step 8: Updating login statistics\n";
    echo "---------------------------------\n";
    $users = $dbManager->getAllUsers();
    foreach ($users as $key => $u) {
        if ($u['username'] === $username) {
            $users[$key]['last_login'] = date('c');
            $users[$key]['login_count'] = ($u['login_count'] ?? 0) + 1;
            break;
        }
    }
    
    // Save updated user data
    $result = $dbManager->saveUsers($users);
    if ($result['success']) {
        echo "✓ Login statistics updated successfully\n\n";
    } else {
        echo "✗ Failed to update login statistics: " . $result['message'] . "\n\n";
    }
    
    // Log successful login
    echo "Step 9: Logging successful login\n";
    echo "--------------------------------\n";
    $logger->logAccess('successful_login', [
        'username' => $username,
        'ip' => $clientIp,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'test-script'
    ]);
    echo "✓ Login event logged\n\n";
    
    echo "🎉 LOGIN PROCESS COMPLETED SUCCESSFULLY!\n";
    echo "=======================================\n";
    echo "The user '$username' has been successfully authenticated and logged in.\n";
    echo "Session is active and ready for use.\n\n";
    
    // Test session validation
    echo "Step 10: Testing session validation\n";
    echo "-----------------------------------\n";
    if (isset($_SESSION['user_id'])) {
        $currentUser = $userManager->getUserById($_SESSION['user_id']);
        if ($currentUser && $currentUser['status'] === 'active') {
            echo "✓ Session validation: PASSED\n";
            echo "  Current user: " . $currentUser['username'] . "\n";
            echo "  Status: " . $currentUser['status'] . "\n";
            echo "  Ready to redirect to main application\n\n";
        } else {
            echo "✗ Session validation: FAILED - Invalid user or inactive status\n\n";
        }
    } else {
        echo "✗ Session validation: FAILED - No user_id in session\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ LOGIN FAILED!\n";
    echo "===============\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    // Log failed login attempt
    if (isset($logger)) {
        $logger->logAccess('failed_login', [
            'username' => $username ?? 'unknown',
            'ip' => $clientIp ?? 'unknown',
            'error' => $e->getMessage(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'test-script'
        ]);
    }
}

echo "Test completed.\n";
?>
