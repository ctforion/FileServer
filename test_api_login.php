<?php
/**
 * Test script to verify API login functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing API login functionality...\n\n";

// Simulate API request to users.php login endpoint
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'login';

// Start output buffering to capture JSON response
ob_start();

// Set up POST data
$postData = json_encode([
    'username' => 'admin',
    'password' => 'admin123'
]);

// Simulate php://input for the API
$temp_file = tmpfile();
fwrite($temp_file, $postData);
rewind($temp_file);

// Override php://input temporarily
$_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';

try {
    // Include the users API file
    include 'api/users.php';
    
    $response = ob_get_contents();
    ob_end_clean();
    
    echo "API Response:\n";
    echo $response . "\n\n";
    
    $responseData = json_decode($response, true);
    if ($responseData && isset($responseData['success'])) {
        if ($responseData['success']) {
            echo "✓ Login API test PASSED\n";
            echo "  User authenticated successfully\n";
            if (isset($responseData['user'])) {
                echo "  User role: " . ($responseData['user']['role'] ?? 'unknown') . "\n";
            }
        } else {
            echo "✗ Login API test FAILED\n";
            echo "  Error: " . ($responseData['error'] ?? 'unknown error') . "\n";
        }
    } else {
        echo "✗ Invalid API response format\n";
        echo "  Raw response: " . $response . "\n";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "✗ Exception occurred: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . "\n";
    echo "  Line: " . $e->getLine() . "\n";
}

fclose($temp_file);

echo "\nTest completed.\n";
?>
