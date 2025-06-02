<?php
require_once '../includes/config.php';
require_once '../includes/auth-functions.php';
require_once '../includes/file-functions.php';
require_once '../includes/log-functions.php';
require_once '../includes/security-functions.php';

// Check authentication
session_start();
require_authentication();

$current_user = get_current_user();
$file_id = $_GET['id'] ?? '';

if (empty($file_id)) {
    http_response_code(400);
    die('File ID is required');
}

// Get file information
$file_info = get_file_by_id($file_id);
if (!$file_info) {
    http_response_code(404);
    die('File not found');
}

// Check permissions
if (!can_user_access_file($current_user['id'], $file_info) && $current_user['role'] !== 'admin') {
    http_response_code(403);
    log_security("Unauthorized download attempt by user: " . $current_user['username'] . " for file: " . $file_info['name']);
    die('Access denied');
}

// Get file path
$file_path = STORAGE_DIR . '/storage/uploads/' . $file_info['filename'];

if (!file_exists($file_path)) {
    http_response_code(404);
    log_error("File not found on disk: " . $file_path);
    die('File not found on disk');
}

// Security check
if (!is_file_safe($file_path)) {
    http_response_code(403);
    log_security("Unsafe file download attempt: " . $file_path . " by user: " . $current_user['username']);
    die('File is not safe for download');
}

// Log download
log_file_operation("File downloaded: " . $file_info['name'] . " by user: " . $current_user['username']);
log_access("Download: " . $file_info['name'] . " by user: " . $current_user['username']);

// Update download count if exists
update_file_download_count($file_id);

// Set download headers
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file_info['name'] . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Output file
readfile($file_path);
exit;

// Helper functions
function update_file_download_count($file_id) {
    $files_data = read_json_file(STORAGE_DIR . '/data/files.json');
    
    foreach ($files_data as &$file) {
        if ($file['id'] === $file_id) {
            $file['downloads'] = ($file['downloads'] ?? 0) + 1;
            $file['last_downloaded'] = date('Y-m-d H:i:s');
            break;
        }
    }
    
    write_json_file(STORAGE_DIR . '/data/files.json', $files_data);
}
?>
