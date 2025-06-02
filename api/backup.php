<?php
/**
 * Backup API Endpoint
 * Handles system backup and restore operations (admin only)
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth-functions.php';
require_once '../includes/json-functions.php';
require_once '../includes/log-functions.php';
require_once '../includes/security-functions.php';
require_once '../includes/validation-functions.php';

header('Content-Type: application/json');

// Check authentication and admin permission
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$user = get_current_user();
if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handle_get_request();
            break;
        case 'POST':
            handle_post_request();
            break;
        case 'DELETE':
            handle_delete_request();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    log_error('API Backup Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handle_get_request() {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'list':
            handle_list_backups();
            break;
            
        case 'status':
            handle_backup_status();
            break;
            
        case 'download':
            handle_download_backup();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handle_post_request() {
    global $user;
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            handle_create_backup();
            break;
            
        case 'restore':
            handle_restore_backup();
            break;
            
        case 'schedule':
            handle_schedule_backup();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handle_delete_request() {
    global $user;
    
    $backup_id = $_GET['backup_id'] ?? '';
    
    if (empty($backup_id)) {
        echo json_encode(['error' => 'Backup ID required']);
        return;
    }
    
    if (delete_backup($backup_id)) {
        log_admin_action('delete_backup', "Deleted backup: $backup_id", $user['username']);
        echo json_encode(['success' => true, 'message' => 'Backup deleted successfully']);
    } else {
        echo json_encode(['error' => 'Failed to delete backup']);
    }
}

function handle_list_backups() {
    $backups = get_all_backups();
    
    // Add additional info to each backup
    foreach ($backups as &$backup) {
        $backup['size_formatted'] = format_bytes($backup['size']);
        $backup['age'] = time_ago($backup['created_at']);
    }
    
    echo json_encode(['backups' => $backups]);
}

function handle_backup_status() {
    $backup_dir = __DIR__ . '/../backups';
    $disk_usage = get_directory_size($backup_dir);
    $backup_count = count(glob($backup_dir . '/*.zip'));
    
    $status = [
        'backup_directory' => $backup_dir,
        'total_backups' => $backup_count,
        'disk_usage' => $disk_usage,
        'disk_usage_formatted' => format_bytes($disk_usage),
        'last_backup' => get_last_backup_time(),
        'auto_backup_enabled' => get_config_value('auto_backup_enabled', false),
        'backup_retention_days' => get_config_value('backup_retention_days', 30)
    ];
    
    echo json_encode(['status' => $status]);
}

function handle_download_backup() {
    $backup_id = $_GET['backup_id'] ?? '';
    
    if (empty($backup_id)) {
        echo json_encode(['error' => 'Backup ID required']);
        return;
    }
    
    $backup = get_backup_by_id($backup_id);
    if (!$backup) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup not found']);
        return;
    }
    
    if (!file_exists($backup['file_path'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup file not found']);
        return;
    }
    
    // Return download information
    echo json_encode([
        'download_url' => generate_backup_download_url($backup_id),
        'filename' => $backup['filename'],
        'size' => $backup['size'],
        'created_at' => $backup['created_at']
    ]);
}

function handle_create_backup() {
    global $user;
    
    $backup_type = sanitize_input($_POST['backup_type'] ?? 'full');
    $description = sanitize_input($_POST['description'] ?? '');
    $include_files = isset($_POST['include_files']) && $_POST['include_files'] === '1';
    
    if (!in_array($backup_type, ['full', 'data_only', 'config_only'])) {
        echo json_encode(['error' => 'Invalid backup type']);
        return;
    }
    
    // Start backup process
    $backup_id = create_system_backup($backup_type, $description, $include_files, $user['username']);
    
    if ($backup_id) {
        log_admin_action('create_backup', "Created $backup_type backup", $user['username']);
        echo json_encode([
            'success' => true,
            'backup_id' => $backup_id,
            'message' => 'Backup creation started successfully'
        ]);
    } else {
        echo json_encode(['error' => 'Failed to create backup']);
    }
}

function handle_restore_backup() {
    global $user;
    
    $backup_id = sanitize_input($_POST['backup_id']);
    $restore_type = sanitize_input($_POST['restore_type'] ?? 'full');
    $confirm = isset($_POST['confirm']) && $_POST['confirm'] === '1';
    
    if (empty($backup_id)) {
        echo json_encode(['error' => 'Backup ID required']);
        return;
    }
    
    if (!$confirm) {
        echo json_encode(['error' => 'Restore confirmation required']);
        return;
    }
    
    $backup = get_backup_by_id($backup_id);
    if (!$backup) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup not found']);
        return;
    }
    
    if (!file_exists($backup['file_path'])) {
        echo json_encode(['error' => 'Backup file not found']);
        return;
    }
    
    // Start restore process
    $restore_result = restore_system_backup($backup_id, $restore_type, $user['username']);
    
    if ($restore_result['success']) {
        log_admin_action('restore_backup', "Restored from backup: $backup_id", $user['username']);
        echo json_encode([
            'success' => true,
            'message' => 'Restore completed successfully',
            'restored_items' => $restore_result['restored_items']
        ]);
    } else {
        echo json_encode(['error' => $restore_result['error']]);
    }
}

function handle_schedule_backup() {
    global $user;
    
    $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    $frequency = sanitize_input($_POST['frequency'] ?? 'daily');
    $time = sanitize_input($_POST['time'] ?? '02:00');
    $backup_type = sanitize_input($_POST['backup_type'] ?? 'full');
    $retention_days = (int)($_POST['retention_days'] ?? 30);
    
    if (!in_array($frequency, ['daily', 'weekly', 'monthly'])) {
        echo json_encode(['error' => 'Invalid frequency']);
        return;
    }
    
    $schedule_config = [
        'auto_backup_enabled' => $enabled,
        'backup_frequency' => $frequency,
        'backup_time' => $time,
        'backup_type' => $backup_type,
        'backup_retention_days' => $retention_days
    ];
    
    if (update_backup_schedule($schedule_config)) {
        log_admin_action('schedule_backup', "Updated backup schedule", $user['username']);
        echo json_encode(['success' => true, 'message' => 'Backup schedule updated successfully']);
    } else {
        echo json_encode(['error' => 'Failed to update backup schedule']);
    }
}

function create_system_backup($backup_type, $description, $include_files, $username) {
    $backup_id = 'backup_' . date('Y-m-d_H-i-s') . '_' . bin2hex(random_bytes(4));
    $backup_dir = __DIR__ . '/../backups';
    
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $backup_filename = $backup_id . '.zip';
    $backup_path = $backup_dir . '/' . $backup_filename;
    
    try {
        $zip = new ZipArchive();
        $result = $zip->open($backup_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        if ($result !== TRUE) {
            return false;
        }
        
        // Add data files
        if ($backup_type === 'full' || $backup_type === 'data_only') {
            $data_dir = __DIR__ . '/../data';
            add_directory_to_zip($zip, $data_dir, 'data/');
        }
        
        // Add config files
        if ($backup_type === 'full' || $backup_type === 'config_only') {
            $config_files = [
                __DIR__ . '/../includes/config.php',
                __DIR__ . '/../.htaccess'
            ];
            
            foreach ($config_files as $file) {
                if (file_exists($file)) {
                    $zip->addFile($file, 'config/' . basename($file));
                }
            }
        }
        
        // Add user files if requested
        if ($include_files && ($backup_type === 'full')) {
            $uploads_dir = __DIR__ . '/../uploads';
            if (file_exists($uploads_dir)) {
                add_directory_to_zip($zip, $uploads_dir, 'uploads/');
            }
        }
        
        // Add backup metadata
        $metadata = [
            'backup_id' => $backup_id,
            'backup_type' => $backup_type,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $username,
            'include_files' => $include_files,
            'system_info' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
            ]
        ];
        
        $zip->addFromString('backup_metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
        $zip->close();
        
        // Save backup record
        $backup_data = [
            'id' => $backup_id,
            'filename' => $backup_filename,
            'file_path' => $backup_path,
            'backup_type' => $backup_type,
            'description' => $description,
            'size' => filesize($backup_path),
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $username
        ];
        
        save_backup_record($backup_data);
        
        return $backup_id;
        
    } catch (Exception $e) {
        log_error('Backup creation failed: ' . $e->getMessage());
        return false;
    }
}

function restore_system_backup($backup_id, $restore_type, $username) {
    $backup = get_backup_by_id($backup_id);
    if (!$backup || !file_exists($backup['file_path'])) {
        return ['success' => false, 'error' => 'Backup file not found'];
    }
    
    try {
        $zip = new ZipArchive();
        if ($zip->open($backup['file_path']) !== TRUE) {
            return ['success' => false, 'error' => 'Cannot open backup file'];
        }
        
        $restored_items = [];
        
        // Extract to temporary directory first
        $temp_dir = sys_get_temp_dir() . '/fileserver_restore_' . time();
        mkdir($temp_dir, 0755, true);
        
        $zip->extractTo($temp_dir);
        $zip->close();
        
        // Restore data files
        if ($restore_type === 'full' || $restore_type === 'data_only') {
            $data_source = $temp_dir . '/data';
            $data_dest = __DIR__ . '/../data';
            
            if (file_exists($data_source)) {
                copy_directory($data_source, $data_dest);
                $restored_items[] = 'Database files';
            }
        }
        
        // Restore config files
        if ($restore_type === 'full' || $restore_type === 'config_only') {
            $config_source = $temp_dir . '/config';
            
            if (file_exists($config_source)) {
                $config_files = scandir($config_source);
                foreach ($config_files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        $source_file = $config_source . '/' . $file;
                        $dest_file = __DIR__ . '/../' . $file;
                        copy($source_file, $dest_file);
                        $restored_items[] = "Config: $file";
                    }
                }
            }
        }
        
        // Restore user files
        if ($restore_type === 'full' && file_exists($temp_dir . '/uploads')) {
            $uploads_source = $temp_dir . '/uploads';
            $uploads_dest = __DIR__ . '/../uploads';
            
            copy_directory($uploads_source, $uploads_dest);
            $restored_items[] = 'User files';
        }
        
        // Clean up temp directory
        remove_directory($temp_dir);
        
        return [
            'success' => true,
            'restored_items' => $restored_items
        ];
        
    } catch (Exception $e) {
        log_error('Backup restore failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Restore failed: ' . $e->getMessage()];
    }
}

function add_directory_to_zip($zip, $directory, $zip_path = '') {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $file_path = $file->getRealPath();
            $relative_path = $zip_path . substr($file_path, strlen($directory) + 1);
            $zip->addFile($file_path, $relative_path);
        }
    }
}

function copy_directory($source, $destination) {
    if (!file_exists($destination)) {
        mkdir($destination, 0755, true);
    }
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($files as $file) {
        $dest_path = $destination . '/' . substr($file, strlen($source) + 1);
        
        if ($file->isDir()) {
            if (!file_exists($dest_path)) {
                mkdir($dest_path, 0755, true);
            }
        } else {
            copy($file, $dest_path);
        }
    }
}

function remove_directory($directory) {
    if (!file_exists($directory)) {
        return;
    }
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    
    rmdir($directory);
}

function generate_backup_download_url($backup_id) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return "{$protocol}://{$host}/api/backup.php?action=download&backup_id={$backup_id}";
}
