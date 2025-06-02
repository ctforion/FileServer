<?php
/**
 * Comprehensive test of login flow without web server
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing complete login flow...\n\n";

// Test 1: Test web login.php directly
echo "Test 1: Testing web/login.php functionality\n";
echo "==========================================\n";

// Simulate POST request to login.php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['username'] = 'admin';
$_POST['password'] = 'admin123';
$_POST['csrf_token'] = 'test_token'; // We'll need to handle CSRF validation
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Capture output
ob_start();

// Start session for the test
session_start();
$_SESSION['csrf_token'] = 'test_token'; // Set the CSRF token

try {
    // Include the login script (but don't execute the HTML part)
    $loginScript = file_get_contents('web/login.php');
    
    // Extract just the PHP login logic part
    $phpStartPos = strpos($loginScript, '<?php');
    $htmlStartPos = strpos($loginScript, '<!DOCTYPE html>');
    
    if ($phpStartPos !== false && $htmlStartPos !== false) {
        $phpCode = substr($loginScript, $phpStartPos, $htmlStartPos - $phpStartPos);
        $phpCode = str_replace('<?php', '', $phpCode);
        $phpCode = str_replace('?>', '', $phpCode);
        
        // Execute the PHP logic
        eval($phpCode);
        
        echo "✓ Login script executed without errors\n";
        
        // Check if login was successful
        if (isset($_SESSION['user_id'])) {
            echo "✓ Login SUCCESSFUL\n";
            echo "  User ID: " . $_SESSION['user_id'] . "\n";
            echo "  Username: " . ($_SESSION['username'] ?? 'unknown') . "\n";
            
            // Test redirect would happen
            if (headers_sent()) {
                echo "  Note: Headers already sent (expected in test)\n";
            }
        } else {
            echo "✗ Login FAILED\n";
            if (isset($error)) {
                echo "  Error: $error\n";
            }
        }
    } else {
        echo "✗ Could not parse login.php file\n";
    }
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . "\n";
    echo "  Line: " . $e->getLine() . "\n";
}

$output = ob_get_clean();
echo $output;

echo "\n";

// Test 2: Test API login endpoint directly
echo "Test 2: Testing API users.php login endpoint\n";
echo "===========================================\n";

// Reset session for API test
session_destroy();
session_start();

// Simulate API request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'login';

// Simulate JSON input
$jsonInput = json_encode([
    'username' => 'admin',
    'password' => 'admin123'
]);

// Override php://input for testing
file_put_contents('php://temp', $jsonInput);

ob_start();

try {
    // Include the users API
    include 'api/users.php';
    
    $apiOutput = ob_get_clean();
    
    echo "API Response:\n";
    echo $apiOutput . "\n";
    
    // Parse response
    $responseData = json_decode($apiOutput, true);
    if ($responseData && isset($responseData['success'])) {
        if ($responseData['success']) {
            echo "\n✓ API Login SUCCESSFUL\n";
            echo "  Username: " . ($_SESSION['username'] ?? 'unknown') . "\n";
            echo "  User ID: " . ($_SESSION['user_id'] ?? 'unknown') . "\n";
            echo "  Role: " . ($_SESSION['role'] ?? 'unknown') . "\n";
        } else {
            echo "\n✗ API Login FAILED\n";
            echo "  Error: " . ($responseData['error'] ?? 'unknown') . "\n";
        }
    } else {
        echo "\n⚠ Unexpected API response format\n";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "✗ API Exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Manual step-by-step login process
echo "Test 3: Manual step-by-step login verification\n";
echo "=============================================\n";

try {
    require_once 'config.php';
    require_once 'core/auth/UserManager.php';
    require_once 'core/logging/Logger.php';
    require_once 'core/utils/SecurityManager.php';
    
    $config = require 'config.php';
    $userManager = new UserManager();
    $logger = new Logger($config['logging']['log_path']);
    $security = new SecurityManager();
    
    echo "Step 1: Authenticate user credentials\n";
    $authResult = $userManager->authenticateUser('admin', 'admin123');
    
    if ($authResult['success']) {
        echo "✓ User authentication successful\n";
        
        echo "Step 2: Set session variables\n";
        $_SESSION['user_id'] = $authResult['user']['id'];
        $_SESSION['username'] = $authResult['user']['username'];
        $_SESSION['role'] = $authResult['user']['role'];
        
        echo "✓ Session variables set\n";
        echo "  User ID: " . $_SESSION['user_id'] . "\n";
        echo "  Username: " . $_SESSION['username'] . "\n";
        echo "  Role: " . $_SESSION['role'] . "\n";
        
        echo "Step 3: Verify user status\n";
        $user = $authResult['user'];
        if ($user['status'] === 'active') {
            echo "✓ User status is active\n";
            echo "✓ Complete login flow SUCCESSFUL\n";
        } else {
            echo "✗ User status is not active: " . $user['status'] . "\n";
        }
        
    } else {
        echo "✗ User authentication failed\n";
        echo "  Message: " . ($authResult['message'] ?? 'unknown') . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Step-by-step test failed: " . $e->getMessage() . "\n";
}

echo "\nAll tests completed.\n";
?>
