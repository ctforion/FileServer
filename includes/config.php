<?php
// Basic configuration settings
$config = array(
    'app_name' => 'FileServer',
    'app_version' => '1.0.0',
    'session_name' => 'fileserver_session',
    'max_file_size' => 50 * 1024 * 1024, // 50MB
    'allowed_extensions' => array('txt', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'),
    'quarantine_extensions' => array('exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'vbs', 'js', 'jar'),
    'uploads_path' => 'storage/uploads/',
    'quarantine_path' => 'storage/quarantine/',
    'compressed_path' => 'storage/compressed/',
    'thumbnails_path' => 'storage/thumbnails/',
    'versions_path' => 'storage/versions/',
    'data_path' => 'data/',
    'logs_path' => 'logs/',
    'backup_path' => 'data/backups/',
    'locks_path' => 'data/locks/',
    'timezone' => 'UTC',
    'date_format' => 'Y-m-d H:i:s',
    'items_per_page' => 20,
    'enable_compression' => true,
    'enable_thumbnails' => true,
    'enable_versioning' => true,
    'enable_email_notifications' => false,
    'admin_email' => 'admin@fileserver.local'
);

// Set timezone
date_default_timezone_set($config['timezone']);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_name($config['session_name']);
    session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
