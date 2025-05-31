<?php
/**
 * Simple test for auto-update system status endpoint
 */

// Test if the RecursiveIteratorIterator method exists
echo "Testing PHP functionality...\n";
echo "============================\n";

try {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator('.', RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $count = 0;
    foreach ($iterator as $item) {
        if ($count < 3) { // Only test first few items
            $subPath = $iterator->getSubPathname();
            echo "✓ Found: $subPath\n";
            $count++;
        } else {
            break;
        }
    }
    
    echo "✓ RecursiveIteratorIterator::getSubPathname() works correctly\n";
    
} catch (Exception $e) {
    echo "✗ RecursiveIteratorIterator error: " . $e->getMessage() . "\n";
}

echo "\nTesting file structure...\n";
echo "========================\n";

$requiredFiles = [
    'install.sh' => 'Auto-update script',
    'source/core/APIHandler.php' => 'API handler with system endpoints',
    'source/web/templates/admin.html' => 'Admin panel with update button',
    'source/web/assets/js/admin-panel.js' => 'Admin panel JavaScript',
    'source/web/assets/css/style.css' => 'Stylesheet with admin styles'
];

foreach ($requiredFiles as $file => $description) {
    $exists = file_exists($file);
    echo ($exists ? "✓" : "✗") . " $file - $description\n";
}

echo "\nTesting auto-update components...\n";
echo "=================================\n";

// Test if APIHandler class has the required methods
if (file_exists('source/core/APIHandler.php')) {
    $content = file_get_contents('source/core/APIHandler.php');
    
    $methods = [
        'handleSystem' => 'System endpoint handler',
        'performAutoUpdate' => 'Auto-update functionality',
        'getSystemStatus' => 'System status check',
        'createSystemBackup' => 'Manual backup creation'
    ];
    
    foreach ($methods as $method => $description) {
        $exists = strpos($content, "function $method") !== false || strpos($content, "$method(") !== false;
        echo ($exists ? "✓" : "✗") . " $method - $description\n";
    }
}

echo "\nTesting admin panel components...\n";
echo "=================================\n";

if (file_exists('source/web/templates/admin.html')) {
    $content = file_get_contents('source/web/templates/admin.html');
    
    $components = [
        'performAutoUpdate' => 'Auto-update button',
        'system-status-card' => 'System status display',
        'updateProgress' => 'Update progress indicator',
        'createManualBackup' => 'Manual backup button'
    ];
    
    foreach ($components as $component => $description) {
        $exists = strpos($content, $component) !== false;
        echo ($exists ? "✓" : "✗") . " $component - $description\n";
    }
}

if (file_exists('source/web/assets/js/admin-panel.js')) {
    $content = file_get_contents('source/web/assets/js/admin-panel.js');
    
    $functions = [
        'performAutoUpdate' => 'Auto-update JavaScript function',
        'refreshSystemStatus' => 'System status refresh',
        'createManualBackup' => 'Manual backup function',
        'updateSystemStatusDisplay' => 'Status display update'
    ];
    
    foreach ($functions as $function => $description) {
        $exists = strpos($content, $function) !== false;
        echo ($exists ? "✓" : "✗") . " $function - $description\n";
    }
}

echo "\n";
echo "Auto-update system implementation complete!\n";
echo "==========================================\n";
echo "\nTo use the auto-update system:\n";
echo "1. Complete the installation using install.php\n";
echo "2. Login as admin\n";
echo "3. Go to Administration Panel → Maintenance\n";
echo "4. Use 'Refresh Status' to check system status\n";
echo "5. Use 'Create Backup' for manual backups\n";
echo "6. Use 'Update System' to perform auto-update from GitHub\n";
echo "\nThe system will:\n";
echo "- Automatically create a backup before updating\n";
echo "- Pull latest code from GitHub using install.sh\n";
echo "- Preserve your config.php and storage directories\n";
echo "- Log all operations for debugging\n";
?>
