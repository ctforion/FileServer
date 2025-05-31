<?php
/**
 * Test script for auto-update functionality
 */

// Include necessary files
require_once 'config.php';
require_once 'source/core/App.php';

// Initialize app
$app = new App();

// Test system status endpoint
echo "Testing System Status Endpoint...\n";
echo "=================================\n";

try {
    // Simulate API request
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $api = $app->getAPIHandler();
    
    // Test system status
    ob_start();
    $api->handleRequest('GET', 'system/status');
    $response = ob_get_clean();
    
    echo "Response: " . $response . "\n\n";
    
    // Parse JSON response
    $data = json_decode($response, true);
    if ($data && $data['success']) {
        echo "✓ System status endpoint working\n";
        echo "Current Version: " . ($data['data']['current_version'] ?? 'Unknown') . "\n";
        echo "Git Available: " . ($data['data']['git_available'] ? 'Yes' : 'No') . "\n";
        echo "Update Script: " . ($data['data']['update_script_exists'] ? 'Available' : 'Missing') . "\n";
        echo "PHP Version: " . ($data['data']['php_version'] ?? 'Unknown') . "\n";
    } else {
        echo "✗ System status endpoint failed\n";
        if (isset($data['error'])) {
            echo "Error: " . $data['error'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ Error testing system status: " . $e->getMessage() . "\n";
}

echo "\n";
echo "Testing File Existence...\n";
echo "========================\n";

$files = [
    'install.sh' => file_exists('install.sh'),
    'config.php' => file_exists('config.php'),
    'source/core/APIHandler.php' => file_exists('source/core/APIHandler.php'),
    'source/web/templates/admin.html' => file_exists('source/web/templates/admin.html'),
    'source/web/assets/js/admin-panel.js' => file_exists('source/web/assets/js/admin-panel.js'),
    'source/web/assets/css/style.css' => file_exists('source/web/assets/css/style.css')
];

foreach ($files as $file => $exists) {
    echo ($exists ? "✓" : "✗") . " $file\n";
}

echo "\n";
echo "Auto-update system ready for testing!\n";
echo "=====================================\n";
echo "1. Access your admin panel at: http://your-domain.com/admin\n";
echo "2. Navigate to the Maintenance section\n";
echo "3. Check system status with 'Refresh Status' button\n";
echo "4. Test manual backup with 'Create Backup' button\n";
echo "5. Test auto-update with 'Update System' button (use carefully!)\n";
?>
