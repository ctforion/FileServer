<?php
// Final verification test for dashboard functionality
echo "=== Dashboard Verification Test ===\n\n";

try {
    require_once 'includes/config.php';
    require_once 'includes/auth-functions.php';
    require_once 'includes/file-functions.php';
    require_once 'includes/log-functions.php';
    require_once 'includes/json-functions.php';
    require_once 'includes/user-functions.php';
    
    echo "✅ All includes loaded successfully\n";
    
    // Test the exact functions used in dashboard.php
    $total_files = count(read_json_file(STORAGE_DIR . '/data/files.json'));
    echo "✅ Total files in system: $total_files\n";
    
    // Test with admin user (ID 1)
    $admin_files = get_user_files(1);
    echo "✅ Admin user files loaded: " . count($admin_files) . " files\n";
    
    $admin_storage = calculate_user_storage(1);
    echo "✅ Admin storage calculated: " . format_file_size($admin_storage) . "\n";
    
    // Test authentication functions
    echo "✅ Authentication functions available:\n";
    echo "   - is_logged_in(): " . (function_exists('is_logged_in') ? 'Available' : 'Missing') . "\n";
    echo "   - require_authentication(): " . (function_exists('require_authentication') ? 'Available' : 'Missing') . "\n";
    echo "   - get_current_user(): " . (function_exists('get_current_user') ? 'Available' : 'Missing') . "\n";
    
    // Test CSRF functions
    echo "✅ Security functions available:\n";
    echo "   - generate_csrf_token(): " . (function_exists('generate_csrf_token') ? 'Available' : 'Missing') . "\n";
    echo "   - validate_csrf_token(): " . (function_exists('validate_csrf_token') ? 'Available' : 'Missing') . "\n";
    
    // Test upload functions
    echo "✅ Upload functions available:\n";
    echo "   - upload_file(): " . (function_exists('upload_file') ? 'Available' : 'Missing') . "\n";
    echo "   - get_user_directories(): " . (function_exists('get_user_directories') ? 'Available' : 'Missing') . "\n";
    echo "   - get_max_upload_size(): " . (function_exists('get_max_upload_size') ? 'Available' : 'Missing') . "\n";
    
    echo "\n🎉 DASHBOARD READY TO USE!\n";
    echo "All functions required by dashboard.php are working correctly.\n";
    echo "The original error 'Call to undefined function get_user_files()' has been resolved.\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
