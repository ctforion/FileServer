<?php
/**
 * Test CSRF token functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once 'core/utils/SecurityManager.php';

echo "Testing CSRF token functionality...\n\n";

try {
    $security = new SecurityManager();
    
    // Test 1: Generate token
    echo "Test 1: Generating CSRF token\n";
    $token = $security->generateCSRFToken();
    echo "Generated token: $token\n";
    echo "Session token: " . ($_SESSION['csrf_token'] ?? 'NOT SET') . "\n";
    echo "Tokens match: " . ($token === ($_SESSION['csrf_token'] ?? '') ? 'YES' : 'NO') . "\n\n";
    
    // Test 2: Validate same token
    echo "Test 2: Validating the same token\n";
    try {
        $result = $security->validateCSRFToken($token);
        echo "Validation result: " . ($result ? 'VALID' : 'INVALID') . "\n";
    } catch (Exception $e) {
        echo "Validation error: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Test 3: Validate wrong token
    echo "Test 3: Validating wrong token\n";
    try {
        $result = $security->validateCSRFToken('wrong_token');
        echo "Validation result: " . ($result ? 'VALID' : 'INVALID') . "\n";
    } catch (Exception $e) {
        echo "Validation error: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Test 4: Validate empty token
    echo "Test 4: Validating empty token\n";
    try {
        $result = $security->validateCSRFToken('');
        echo "Validation result: " . ($result ? 'VALID' : 'INVALID') . "\n";
    } catch (Exception $e) {
        echo "Validation error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
?>
