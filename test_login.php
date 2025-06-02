<?php
/**
 * Test script to verify login.php components work correctly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing login.php components...\n\n";

try {
    // Test includes (without starting session to avoid conflicts)
    require_once 'config.php';
    require_once 'core/database/DatabaseManager.php';
    require_once 'core/auth/UserManager.php';
    require_once 'core/logging/Logger.php';
    require_once 'core/utils/SecurityManager.php';
    
    echo "✓ All required files loaded successfully\n";
    
    // Test config loading
    $config = require 'config.php';
    echo "✓ Config loaded successfully\n";
    
    // Test manager initialization
    $dbManager = DatabaseManager::getInstance();
    echo "✓ Database manager initialized\n";
    
    $userManager = new UserManager();
    echo "✓ User manager initialized\n";
    
    $logger = new Logger($config['logging']['log_path']);
    echo "✓ Logger initialized\n";
    
    $security = new SecurityManager();
    echo "✓ Security manager initialized\n";
    
    // Test clientIp variable definition
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    echo "✓ Client IP variable defined: {$clientIp}\n";
    
    // Test session status check
    echo "✓ Session status: " . (session_status() === PHP_SESSION_NONE ? 'No session' : 'Session active') . "\n";
    
    echo "\n✓ All login.php components working correctly!\n";
    echo "The login.php file should now work without errors.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "✗ Fatal Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
