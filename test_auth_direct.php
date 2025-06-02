<?php
/**
 * Direct test of authentication system components
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing authentication system components directly...\n\n";

try {
    // Load required files
    require_once 'config.php';
    require_once 'core/database/DatabaseManager.php';
    require_once 'core/auth/UserManager.php';
    require_once 'core/logging/Logger.php';
    
    echo "✓ All required files loaded\n";
    
    // Initialize managers
    $config = require 'config.php';
    $dbManager = DatabaseManager::getInstance();
    $userManager = new UserManager();
    $logger = new Logger($config['logging']['log_path']);
    
    echo "✓ All managers initialized\n\n";
    
    // Test 1: Authenticate admin/admin123
    echo "Test 1: Authenticating admin/admin123\n";
    echo "------------------------------------\n";
    
    $result1 = $userManager->authenticateUser('admin', 'admin123');
    
    if ($result1['success']) {
        echo "✓ Authentication SUCCESSFUL\n";
        echo "  User: " . $result1['user']['username'] . "\n";
        echo "  Role: " . $result1['user']['role'] . "\n";
        echo "  Status: " . $result1['user']['status'] . "\n";
    } else {
        echo "✗ Authentication FAILED\n";
        echo "  Message: " . ($result1['message'] ?? 'Unknown error') . "\n";
    }
    
    echo "\n";
    
    // Test 2: Authenticate testadmin/mypassword123
    echo "Test 2: Authenticating testadmin/mypassword123\n";
    echo "--------------------------------------------\n";
    
    $result2 = $userManager->authenticateUser('testadmin', 'mypassword123');
    
    if ($result2['success']) {
        echo "✓ Authentication SUCCESSFUL\n";
        echo "  User: " . $result2['user']['username'] . "\n";
        echo "  Role: " . $result2['user']['role'] . "\n";
        echo "  Status: " . $result2['user']['status'] . "\n";
    } else {
        echo "✗ Authentication FAILED\n";
        echo "  Message: " . ($result2['message'] ?? 'Unknown error') . "\n";
    }
    
    echo "\n";
    
    // Test 3: Test wrong password
    echo "Test 3: Testing wrong password\n";
    echo "-----------------------------\n";
    
    $result3 = $userManager->authenticateUser('admin', 'wrongpassword');
    
    if ($result3['success']) {
        echo "✗ Authentication should have FAILED but succeeded\n";
    } else {
        echo "✓ Authentication correctly FAILED\n";
        echo "  Message: " . ($result3['message'] ?? 'Unknown error') . "\n";
    }
    
    echo "\n";
    
    // Test 4: Check user data directly
    echo "Test 4: Direct user data check\n";
    echo "-----------------------------\n";
    
    $allUsers = $dbManager->getAllUsers();
    
    foreach ($allUsers as $username => $user) {
        echo "User: $username\n";
        echo "  Password field: " . (isset($user['password']) ? 'YES' : 'NO') . "\n";
        echo "  Password hash field: " . (isset($user['password_hash']) ? 'YES' : 'NO') . "\n";
        if (isset($user['password'])) {
            echo "  Password value: " . $user['password'] . "\n";
        }
        echo "  Status: " . ($user['status'] ?? 'unknown') . "\n";
        echo "  Role: " . ($user['role'] ?? 'unknown') . "\n";
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . "\n";
    echo "  Line: " . $e->getLine() . "\n";
}

echo "Test completed.\n";
?>
