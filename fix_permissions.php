<?php
/**
 * Alternative permission fixer using different approaches
 * Use this if the main setup script doesn't work
 */

echo "Alternative Permission Fixer\n";
echo "============================\n\n";

$baseDir = __DIR__;
$storageDir = $baseDir . '/storage';

// Create directories if they don't exist
$directories = [
    $storageDir,
    $storageDir . '/public',
    $storageDir . '/private',
    $storageDir . '/temp'
];

echo "Creating directories and setting permissions...\n\n";

foreach ($directories as $dir) {
    echo "Processing: $dir\n";
    
    // Method 1: Standard mkdir
    if (!is_dir($dir)) {
        echo "  Creating directory...\n";
        if (@mkdir($dir, 0755, true)) {
            echo "  ✓ Created with mkdir()\n";
        } else {
            echo "  ✗ mkdir() failed\n";
            
            // Method 2: Create with different permissions
            echo "  Trying alternative creation...\n";
            if (@mkdir($dir, 0777, true)) {
                echo "  ✓ Created with 0777 permissions\n";
            } else {
                echo "  ✗ Alternative creation failed\n";
            }
        }
    } else {
        echo "  Directory already exists\n";
    }
    
    // Test write access
    $testFile = $dir . '/.write_test';
    if (@file_put_contents($testFile, 'test')) {
        echo "  ✓ Write access confirmed\n";
        @unlink($testFile);
    } else {
        echo "  ✗ No write access\n";
        
        // Try to fix permissions
        echo "  Attempting to fix permissions...\n";
        
        // Method 1: chmod 755
        if (@chmod($dir, 0755)) {
            echo "  ✓ Set to 755\n";
        } else {
            // Method 2: chmod 777
            if (@chmod($dir, 0777)) {
                echo "  ✓ Set to 777\n";
            } else {
                echo "  ✗ Could not change permissions\n";
                echo "  Manual action required!\n";
            }
        }
        
        // Test again
        if (@file_put_contents($testFile, 'test')) {
            echo "  ✓ Write access now working\n";
            @unlink($testFile);
        } else {
            echo "  ✗ Still no write access\n";
        }
    }
    
    echo "\n";
}

// Create .htaccess files for security
echo "Creating security files...\n\n";

$htaccessContent = "Order Deny,Allow\nDeny from all\n";

$securityFiles = [
    $storageDir . '/.htaccess' => $htaccessContent,
    $storageDir . '/private/.htaccess' => $htaccessContent,
    $storageDir . '/temp/.htaccess' => $htaccessContent
];

foreach ($securityFiles as $file => $content) {
    echo "Creating: $file\n";
    if (@file_put_contents($file, $content)) {
        echo "  ✓ Security file created\n";
    } else {
        echo "  ✗ Failed to create security file\n";
    }
}

echo "\nManual Commands (if automatic fixing failed):\n";
echo "============================================\n";
echo "If you have SSH access, run these commands:\n\n";
echo "cd " . dirname($storageDir) . "\n";
echo "mkdir -p storage/public storage/private storage/temp\n";
echo "chmod -R 755 storage/\n";
echo "chown -R www-data:www-data storage/\n\n";

echo "Or using your hosting panel's file manager:\n";
echo "1. Create folders: storage, storage/public, storage/private, storage/temp\n";
echo "2. Set permissions to 755 or 777 for all storage folders\n";
echo "3. Upload .htaccess files to protect private directories\n\n";

echo "Setup complete! Check the diagnostics.php file for detailed status.\n";
?>
