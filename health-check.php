<?php
// Health check script to verify all functions are working
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/json-functions.php';
require_once 'includes/auth-functions.php';
require_once 'includes/user-functions.php';
require_once 'includes/file-functions.php';
require_once 'includes/log-functions.php';
require_once 'includes/security-functions.php';
require_once 'includes/validation-functions.php';

// Content type for JSON response
header('Content-Type: application/json');

$health_checks = array();

// Check if all critical functions exist
$required_functions = array(
    'is_logged_in',
    'require_authentication',
    'get_current_user',
    'get_user_files',
    'calculate_user_storage',
    'read_json_file',
    'write_json_file',
    'redirect_to',
    'format_file_size',
    'sanitize_filename',
    'log_activity'
);

foreach ($required_functions as $func) {
    $health_checks['functions'][$func] = function_exists($func);
}

// Check if data files exist and are readable
$data_files = array(
    'users.json',
    'files.json',
    'logs.json',
    'shares.json',
    'config.json',
    'blocked-ips.json'
);

foreach ($data_files as $file) {
    $file_path = STORAGE_DIR . '/data/' . $file;
    $health_checks['data_files'][$file] = array(
        'exists' => file_exists($file_path),
        'readable' => is_readable($file_path),
        'writable' => is_writable($file_path)
    );
}

// Check directories
$directories = array(
    'storage/uploads',
    'storage/compressed',
    'storage/quarantine',
    'storage/thumbnails',
    'storage/versions',
    'data',
    'data/backups',
    'data/locks',
    'logs'
);

foreach ($directories as $dir) {
    $dir_path = STORAGE_DIR . '/' . $dir;
    $health_checks['directories'][$dir] = array(
        'exists' => is_dir($dir_path),
        'readable' => is_readable($dir_path),
        'writable' => is_writable($dir_path)
    );
}

// Check constants
$health_checks['constants']['STORAGE_DIR'] = defined('STORAGE_DIR');

// Overall health status
$all_functions_ok = !in_array(false, $health_checks['functions']);
$all_files_ok = true;
foreach ($health_checks['data_files'] as $file_status) {
    if (!$file_status['exists'] || !$file_status['readable']) {
        $all_files_ok = false;
        break;
    }
}

$all_dirs_ok = true;
foreach ($health_checks['directories'] as $dir_status) {
    if (!$dir_status['exists'] || !$dir_status['readable']) {
        $all_dirs_ok = false;
        break;
    }
}

$health_checks['overall_status'] = array(
    'functions' => $all_functions_ok,
    'data_files' => $all_files_ok,
    'directories' => $all_dirs_ok,
    'healthy' => $all_functions_ok && $all_files_ok && $all_dirs_ok
);

echo json_encode($health_checks, JSON_PRETTY_PRINT);
?>
