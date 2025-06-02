<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/log-functions.php';

echo "Testing log file generation...\n";

// Test different log types
log_access('System test - access log');
log_error('System test - error log');
log_security_event('System test - security log');
log_admin_action('System test - admin log');
log_file_operation('test', 'sample.txt', 'System test - file log');

echo "Log generation test complete!\n";
echo "Check logs directory for generated files.\n";
?>
