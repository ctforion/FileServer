<?php
/**
 * Test to simulate browser form submission to login.php
 */

// Save current directory
$currentDir = getcwd();

// Change to web directory 
chdir('web');

// Set up environment as browser would
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';
$_SERVER['REQUEST_URI'] = '/web/login.php';
$_SERVER['SCRIPT_NAME'] = '/web/login.php';

// Start fresh session
session_start();
session_destroy();
session_start();

echo "=== Testing Browser Form Submission to login.php ===\n\n";

// First, get a CSRF token like a real form would
echo "Step 1: Getting CSRF token (like when page loads)...\n";

// Load the login page to get CSRF token
ob_start();
require_once '../core/utils/SecurityManager.php';
$security = new SecurityManager();
$csrfToken = $security->generateCSRFToken();
ob_end_clean();

echo "CSRF Token generated: " . substr($csrfToken, 0, 10) . "...\n\n";

// Now simulate form submission
echo "Step 2: Submitting form with admin credentials...\n";

$_POST = [
    'username' => 'admin',
    'password' => 'admin123',
    'csrf_token' => $csrfToken
];

echo "POST data set:\n";
print_r($_POST);
echo "\n";

// Capture any output/redirects
ob_start();

// Include the login.php file
try {
    include 'login.php';
    $output = ob_get_contents();
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    $output = ob_get_contents();
}

ob_end_clean();

// Check if headers were sent (redirect)
$headers = headers_list();
if (!empty($headers)) {
    echo "Headers sent:\n";
    foreach ($headers as $header) {
        echo "  " . $header . "\n";
    }
    echo "\n";
}

// Check session state
echo "Session after login attempt:\n";
print_r($_SESSION);
echo "\n";

// Check if there was any output (error messages, HTML)
if (!empty($output)) {
    echo "Output from login.php:\n";
    echo "======================\n";
    echo $output;
    echo "\n======================\n";
}

// Restore directory
chdir($currentDir);

echo "\n=== Test Complete ===\n";
?>
