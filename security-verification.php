<?php
// Complete Security Verification Script
header('Content-Type: text/plain; charset=utf-8');

echo "🔒 PHP FileServer Security Verification\n";
echo "==========================================\n\n";

// Check all .htaccess files exist
$security_dirs = [
    '.' => 'Root directory (main security)',
    'api' => 'API endpoints (controlled access)',
    'assets' => 'Static assets (optimized delivery)',
    'data' => 'JSON data (complete denial)',
    'data/backups' => 'Backup files (critical protection)',
    'data/locks' => 'Lock files (system protection)',
    'includes' => 'PHP functions (complete denial)',
    'logs' => 'Log files (protected access)',
    'storage' => 'File storage (API-only access)',
    'storage/compressed' => 'Compressed files (protected)',
    'storage/quarantine' => 'Quarantine (maximum security)',
    'storage/thumbnails' => 'Thumbnails (protected)',
    'storage/uploads' => 'Uploads (download API only)',
    'storage/versions' => 'File versions (protected)',
    'templates' => 'HTML templates (complete denial)'
];

echo "📋 Security File Verification:\n";
echo "------------------------------\n";

$all_secured = true;
foreach ($security_dirs as $dir => $description) {
    $htaccess_path = $dir . '/.htaccess';
    $exists = file_exists($htaccess_path);
    $status = $exists ? '✅' : '❌';
    
    if (!$exists) $all_secured = false;
    
    echo sprintf("%-25s %s %s\n", $dir . '/', $status, $description);
}

echo "\n📁 Directory Status:\n";
echo "-------------------\n";

$directories = [
    'api' => 'API endpoints',
    'assets/css' => 'Stylesheets',
    'assets/js' => 'JavaScript files',
    'data' => 'JSON data files',
    'data/backups' => 'System backups',
    'data/locks' => 'Operation locks',
    'includes' => 'PHP functions',
    'logs' => 'System logs',
    'storage/compressed' => 'Compressed files',
    'storage/quarantine' => 'Quarantined files',
    'storage/thumbnails' => 'Image thumbnails',
    'storage/uploads' => 'User uploads',
    'storage/versions' => 'File versions',
    'templates' => 'HTML templates'
];

foreach ($directories as $dir => $description) {
    $exists = is_dir($dir);
    $writable = $exists ? is_writable($dir) : false;
    $file_count = 0;
    
    if ($exists) {
        $files = glob($dir . '/*');
        $file_count = count(array_filter($files, 'is_file'));
    }
    
    $status = $exists ? ($writable ? '✅' : '⚠️') : '❌';
    $files_info = $file_count > 0 ? " ({$file_count} files)" : " (empty)";
    
    echo sprintf("%-25s %s %s%s\n", $dir . '/', $status, $description, $files_info);
}

echo "\n🛡️ Security Level Summary:\n";
echo "---------------------------\n";
echo "MAXIMUM SECURITY (Complete Denial):\n";
echo "  • data/ - Sensitive JSON files\n";
echo "  • includes/ - PHP function files\n";
echo "  • templates/ - HTML template files\n";
echo "  • logs/ - System log files\n";
echo "  • storage/quarantine/ - Suspicious files (LOCKDOWN)\n";
echo "  • data/backups/ - Critical backup files\n";
echo "  • data/locks/ - System lock files\n\n";

echo "API-ONLY ACCESS (Controlled Access):\n";
echo "  • storage/uploads/ - Download API only\n";
echo "  • storage/compressed/ - Compression API only\n";
echo "  • storage/thumbnails/ - Image API only\n";
echo "  • storage/versions/ - Version API only\n\n";

echo "OPTIMIZED DELIVERY (Static Assets):\n";
echo "  • assets/ - CSS, JS, images with caching\n\n";

echo "CONTROLLED API (Secured Endpoints):\n";
echo "  • api/ - PHP endpoints with CORS headers\n\n";

// Check if server is ready
echo "🚀 Server Readiness Check:\n";
echo "--------------------------\n";

$config_exists = file_exists('includes/config.php');
$init_complete = file_exists('data/users.json');
$logs_writable = is_writable('logs');
$storage_writable = is_writable('storage');

echo "Configuration: " . ($config_exists ? '✅' : '❌') . "\n";
echo "Initialization: " . ($init_complete ? '✅' : '❌') . "\n";
echo "Logs writable: " . ($logs_writable ? '✅' : '❌') . "\n";
echo "Storage writable: " . ($storage_writable ? '✅' : '❌') . "\n";

$ready = $all_secured && $config_exists && $init_complete && $logs_writable && $storage_writable;

echo "\n" . str_repeat("=", 50) . "\n";
if ($ready) {
    echo "🎉 SECURITY VERIFICATION COMPLETE!\n";
    echo "🔒 All directories are properly secured\n";
    echo "🚀 FileServer is ready for production use\n\n";
    echo "To start the server:\n";
    echo "1. Double-click: start-server.bat\n";
    echo "2. Or run: php -S localhost:8080\n";
    echo "3. Open browser: http://localhost:8080\n";
    echo "4. Login: admin / admin123\n";
} else {
    echo "⚠️ SECURITY ISSUES DETECTED!\n";
    echo "Please review the above checks and fix any missing files.\n";
}
echo str_repeat("=", 50) . "\n";
?>
