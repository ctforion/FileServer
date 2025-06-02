<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

echo "Debug log path information:\n";
echo "STORAGE_DIR: " . STORAGE_DIR . "\n";
echo "logs_path config: " . $config['logs_path'] . "\n";
echo "Full log path: " . STORAGE_DIR . '/' . $config['logs_path'] . "\n";

$log_dir = STORAGE_DIR . '/' . $config['logs_path'];
echo "Log directory exists: " . (is_dir($log_dir) ? 'Yes' : 'No') . "\n";
echo "Log directory writable: " . (is_writable($log_dir) ? 'Yes' : 'No') . "\n";

// Test direct file creation
$test_file = $log_dir . 'test.log';
echo "Test file path: $test_file\n";

$result = file_put_contents($test_file, "Test log entry\n");
echo "File write result: " . ($result !== false ? "Success ($result bytes)" : "Failed") . "\n";

if (file_exists($test_file)) {
    echo "Test file created successfully\n";
    echo "Content: " . file_get_contents($test_file);
} else {
    echo "Test file was not created\n";
}
?>
