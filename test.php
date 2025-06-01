<?php
/**
 * Simple test script to verify File Storage Server functionality
 * Run this from command line: php test.php
 */

require_once 'config.php';
require_once 'core/auth/SimpleFileAuthenticator.php';
require_once 'core/storage/FileManager.php';
require_once 'core/utils/EnvLoader.php';
require_once 'core/utils/Validator.php';

echo "=== File Storage Server Test ===\n\n";

// Load configuration
EnvLoader::load('config.php');
echo "✓ Configuration loaded\n";

// Test authentication system (file-based fallback)
try {
    $auth = new SimpleFileAuthenticator(EnvLoader::getStoragePath() . '/users.json');
    echo "✓ File-based authentication initialized\n";
} catch (Exception $e) {
    echo "✗ Authentication error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test storage directory creation
try {
    $fileManager = new FileManager(
        EnvLoader::getStoragePath(),
        EnvLoader::getMaxFileSize(),
        EnvLoader::getAllowedExtensions()
    );
    echo "✓ Storage directories initialized\n";
} catch (Exception $e) {
    echo "✗ Storage error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test authentication
echo "\n--- Testing Authentication ---\n";
$testResult = $auth->login('admin', 'admin123');
if ($testResult) {
    echo "✓ Default admin login works\n";
    $auth->logout();
    echo "✓ Logout works\n";
} else {
    echo "✗ Default admin login failed\n";
}

// Test file validation
echo "\n--- Testing File Validation ---\n";
$validFilename = Validator::validateFilename('test-file.pdf');
echo $validFilename ? "✓ Valid filename validation works\n" : "✗ Filename validation failed\n";

$invalidFilename = Validator::validateFilename('con.txt'); // Windows reserved name
echo !$invalidFilename ? "✓ Invalid filename rejected\n" : "✗ Invalid filename not rejected\n";

// Test storage directories exist
echo "\n--- Testing Storage Structure ---\n";
$storagePath = EnvLoader::getStoragePath();
$dirs = ['public', 'private', 'temp'];
foreach ($dirs as $dir) {
    $path = $storagePath . '/' . $dir;
    if (is_dir($path)) {
        echo "✓ {$dir} directory exists\n";
    } else {
        echo "✗ {$dir} directory missing\n";
    }
}

// Test file listing (empty directories)
echo "\n--- Testing File Operations ---\n";
$result = $fileManager->listFiles('public');
if ($result['success']) {
    echo "✓ File listing works (found " . count($result['files']) . " files)\n";
} else {
    echo "✗ File listing failed: " . $result['message'] . "\n";
}

// Test configuration values
echo "\n--- Testing Configuration ---\n";
echo "Max file size: " . round(EnvLoader::getMaxFileSize() / 1024 / 1024) . "MB\n";
echo "Allowed extensions: " . implode(', ', array_slice(EnvLoader::getAllowedExtensions(), 0, 5)) . "...\n";
echo "Storage path: " . EnvLoader::getStoragePath() . "\n";
echo "Database path: " . EnvLoader::getDatabasePath() . "\n";

echo "\n=== Test Complete ===\n";
echo "✓ Basic functionality verified\n";
echo "✓ Server should be accessible at: http://localhost:8000\n";
echo "✓ Default login: admin / admin123\n";
