<?php
/**
 * Setup script to fix directory permissions for FileServer
 * Run this script once after uploading to production server
 */

echo "FileServer Permission Setup Script\n";
echo "==================================\n\n";

// Define paths
$baseDir = __DIR__;
$storageDir = $baseDir . '/storage';

// Required directories
$directories = [
    $storageDir,
    $storageDir . '/public',
    $storageDir . '/private', 
    $storageDir . '/temp'
];

echo "Checking and creating directories...\n";

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        echo "Creating directory: $dir\n";
        if (mkdir($dir, 0755, true)) {
            echo "✓ Directory created successfully\n";
        } else {
            echo "✗ Failed to create directory\n";
            exit(1);
        }
    } else {
        echo "Directory exists: $dir\n";
    }
    
    // Set permissions
    echo "Setting permissions for: $dir\n";
    if (chmod($dir, 0755)) {
        echo "✓ Permissions set to 755\n";
    } else {
        echo "⚠ Warning: Could not set permissions (may need manual setting)\n";
    }
}

echo "\nChecking web server write access...\n";

// Test write access
foreach ($directories as $dir) {
    $testFile = $dir . '/test_write.txt';
    if (file_put_contents($testFile, 'test') !== false) {
        echo "✓ Write access OK for: $dir\n";
        unlink($testFile); // Clean up
    } else {
        echo "✗ No write access for: $dir\n";
        echo "  You may need to run: chmod 755 $dir\n";
        echo "  Or contact your hosting provider\n";
    }
}

echo "\nChecking .htaccess files...\n";

// Check .htaccess files exist
$htaccessFiles = [
    $storageDir . '/.htaccess',
    $storageDir . '/private/.htaccess',
    $storageDir . '/temp/.htaccess'
];

foreach ($htaccessFiles as $htaccess) {
    if (file_exists($htaccess)) {
        echo "✓ Security file exists: $htaccess\n";
    } else {
        echo "⚠ Missing security file: $htaccess\n";
        // Create the .htaccess file
        $content = "Order Deny,Allow\nDeny from all\n";
        if (file_put_contents($htaccess, $content)) {
            echo "✓ Created security file: $htaccess\n";
        } else {
            echo "✗ Failed to create security file: $htaccess\n";
        }
    }
}

echo "\nChecking PHP configuration...\n";

// Check upload settings
$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');
$maxExecutionTime = ini_get('max_execution_time');

echo "upload_max_filesize: $uploadMaxFilesize\n";
echo "post_max_size: $postMaxSize\n";
echo "max_execution_time: $maxExecutionTime\n";

// Check for required functions
$requiredFunctions = ['move_uploaded_file', 'is_uploaded_file', 'pathinfo'];
foreach ($requiredFunctions as $func) {
    if (function_exists($func)) {
        echo "✓ Function available: $func\n";
    } else {
        echo "✗ Function missing: $func\n";
    }
}

echo "\nSetup complete!\n";
echo "If you see any ✗ errors above, you may need to:\n";
echo "1. Contact your hosting provider to set directory permissions\n";
echo "2. Run these commands via SSH if available:\n";
echo "   chmod -R 755 $storageDir\n";
echo "   chown -R www-data:www-data $storageDir\n";
echo "\nOr use your hosting panel's file manager to set permissions.\n";

?>
