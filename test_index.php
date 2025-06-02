<?php
/**
 * Test script to verify index.php components work correctly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing index.php components...\n\n";

try {
    // Test includes
    require_once 'config.php';
    require_once 'core/database/DatabaseManager.php';
    require_once 'core/auth/UserManager.php';
    require_once 'core/logging/Logger.php';
    require_once 'core/utils/SecurityManager.php';
    require_once 'core/utils/EnvLoader.php';
    
    echo "✓ All required files loaded successfully\n";
    
    // Test config loading
    $config = require 'config.php';
    echo "✓ Config loaded successfully\n";
    
    // Test EnvLoader initialization
    foreach ($config as $key => $value) {
        EnvLoader::set($key, $value);
    }
    echo "✓ EnvLoader initialized successfully\n";
    
    // Test EnvLoader methods
    $maxFileSize = EnvLoader::getMaxFileSize();
    $maxFileSizeMB = round($maxFileSize / 1024 / 1024);
    echo "✓ Max file size: {$maxFileSizeMB}MB\n";
    
    $allowedExtensions = EnvLoader::getAllowedExtensions();
    echo "✓ Allowed extensions: " . implode(', ', array_slice($allowedExtensions, 0, 5)) . "...\n";
    
    // Test manager initialization
    $dbManager = DatabaseManager::getInstance();
    echo "✓ Database manager initialized\n";
    
    $userManager = new UserManager();
    echo "✓ User manager initialized\n";
    
    $logger = new Logger($config['logging']['log_path']);
    echo "✓ Logger initialized\n";
    
    $security = new SecurityManager();
    echo "✓ Security manager initialized\n";
    
    echo "\n✓ All components working correctly!\n";
    echo "The index.php file should now work without errors.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "✗ Fatal Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
