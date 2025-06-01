<?php
/**
 * Diagnostic script to identify permission and configuration issues
 * Upload this to your production server and run it to get detailed info
 */

echo "<!DOCTYPE html>\n";
echo "<html><head><title>FileServer Diagnostics</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .error{color:red;} .success{color:green;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;}</style>";
echo "</head><body>\n";

echo "<h1>FileServer Diagnostics</h1>\n";

// Basic info
echo "<h2>Server Information</h2>\n";
echo "<pre>\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Path: " . __FILE__ . "\n";
echo "Current Working Directory: " . getcwd() . "\n";
echo "Script Directory: " . __DIR__ . "\n";
echo "</pre>\n";

// File upload settings
echo "<h2>PHP Upload Configuration</h2>\n";
echo "<pre>\n";
echo "file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "</pre>\n";

// Directory checks
echo "<h2>Directory Structure and Permissions</h2>\n";

$baseDir = __DIR__;
$storageDir = $baseDir . '/storage';

$directories = [
    'Base Directory' => $baseDir,
    'Storage Directory' => $storageDir,
    'Public Storage' => $storageDir . '/public',
    'Private Storage' => $storageDir . '/private',
    'Temp Storage' => $storageDir . '/temp'
];

echo "<table border='1' cellpadding='5'>\n";
echo "<tr><th>Directory</th><th>Path</th><th>Exists</th><th>Permissions</th><th>Readable</th><th>Writable</th><th>Owner</th></tr>\n";

foreach ($directories as $name => $path) {
    echo "<tr>\n";
    echo "<td>$name</td>\n";
    echo "<td>$path</td>\n";
    
    if (is_dir($path)) {
        echo "<td class='success'>Yes</td>\n";
        
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        echo "<td>$perms</td>\n";
        
        echo "<td>" . (is_readable($path) ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>") . "</td>\n";
        echo "<td>" . (is_writable($path) ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>") . "</td>\n";
        
        if (function_exists('posix_getpwuid') && function_exists('fileowner')) {
            $owner = posix_getpwuid(fileowner($path));
            echo "<td>" . ($owner ? $owner['name'] : 'Unknown') . "</td>\n";
        } else {
            echo "<td>N/A</td>\n";
        }
    } else {
        echo "<td class='error'>No</td>\n";
        echo "<td colspan='4' class='error'>Directory does not exist</td>\n";
    }
    
    echo "</tr>\n";
}

echo "</table>\n";

// Test file creation
echo "<h2>Write Test</h2>\n";

foreach (['public', 'private', 'temp'] as $dir) {
    $testDir = $storageDir . '/' . $dir;
    echo "<h3>Testing: $testDir</h3>\n";
    
    if (!is_dir($testDir)) {
        echo "<p class='warning'>Attempting to create directory...</p>\n";
        if (mkdir($testDir, 0755, true)) {
            echo "<p class='success'>Directory created successfully</p>\n";
        } else {
            echo "<p class='error'>Failed to create directory</p>\n";
            continue;
        }
    }
    
    $testFile = $testDir . '/test_' . time() . '.txt';
    if (file_put_contents($testFile, 'Test content ' . date('Y-m-d H:i:s'))) {
        echo "<p class='success'>✓ Write test successful</p>\n";
        if (unlink($testFile)) {
            echo "<p class='success'>✓ Delete test successful</p>\n";
        } else {
            echo "<p class='warning'>⚠ Could not delete test file</p>\n";
        }
    } else {
        echo "<p class='error'>✗ Write test failed</p>\n";
        $error = error_get_last();
        if ($error) {
            echo "<p class='error'>Error: " . htmlspecialchars($error['message']) . "</p>\n";
        }
    }
}

// .htaccess check
echo "<h2>Security Files Check</h2>\n";

$htaccessFiles = [
    'Main .htaccess' => $baseDir . '/.htaccess',
    'Storage .htaccess' => $storageDir . '/.htaccess',
    'Private .htaccess' => $storageDir . '/private/.htaccess',
    'Temp .htaccess' => $storageDir . '/temp/.htaccess'
];

echo "<table border='1' cellpadding='5'>\n";
echo "<tr><th>File</th><th>Path</th><th>Exists</th><th>Size</th><th>Readable</th></tr>\n";

foreach ($htaccessFiles as $name => $path) {
    echo "<tr>\n";
    echo "<td>$name</td>\n";
    echo "<td>$path</td>\n";
    
    if (file_exists($path)) {
        echo "<td class='success'>Yes</td>\n";
        echo "<td>" . filesize($path) . " bytes</td>\n";
        echo "<td>" . (is_readable($path) ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>") . "</td>\n";
    } else {
        echo "<td class='error'>No</td>\n";
        echo "<td colspan='2' class='error'>File missing</td>\n";
    }
    
    echo "</tr>\n";
}

echo "</table>\n";

// Function availability
echo "<h2>Required PHP Functions</h2>\n";

$requiredFunctions = [
    'move_uploaded_file',
    'is_uploaded_file', 
    'pathinfo',
    'file_exists',
    'is_dir',
    'mkdir',
    'chmod',
    'is_writable',
    'is_readable',
    'file_put_contents',
    'unlink'
];

echo "<ul>\n";
foreach ($requiredFunctions as $func) {
    if (function_exists($func)) {
        echo "<li class='success'>✓ $func</li>\n";
    } else {
        echo "<li class='error'>✗ $func</li>\n";
    }
}
echo "</ul>\n";

// Environment variables
echo "<h2>Environment Variables</h2>\n";
echo "<pre>\n";
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'PATH') !== false || strpos($key, 'USER') !== false || strpos($key, 'HOME') !== false) {
        echo htmlspecialchars("$key = $value") . "\n";
    }
}
echo "</pre>\n";

echo "<h2>Recommendations</h2>\n";
echo "<ul>\n";
echo "<li>If directories don't exist, run the setup_permissions.php script</li>\n";
echo "<li>If write tests fail, set directory permissions to 755 or 777</li>\n";
echo "<li>Contact your hosting provider if permission changes don't work</li>\n";
echo "<li>Ensure storage directories are outside the web root for security</li>\n";
echo "</ul>\n";

echo "</body></html>\n";
?>
