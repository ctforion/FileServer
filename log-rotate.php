<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

/**
 * Log rotation script to manage log file sizes
 * Can be run manually or via cron job
 */

function rotate_log($log_file, $max_size = 5242880) { // 5MB default
    $log_path = STORAGE_DIR . '/logs/' . $log_file;
    
    if (!file_exists($log_path)) {
        return array('success' => false, 'message' => 'Log file does not exist');
    }
    
    $file_size = filesize($log_path);
    
    if ($file_size < $max_size) {
        return array('success' => true, 'message' => 'No rotation needed', 'size' => $file_size);
    }
    
    // Create rotated filename with timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $rotated_name = pathinfo($log_file, PATHINFO_FILENAME) . '_' . $timestamp . '.log';
    $rotated_path = STORAGE_DIR . '/logs/archive/' . $rotated_name;
    
    // Create archive directory if it doesn't exist
    $archive_dir = STORAGE_DIR . '/logs/archive';
    if (!is_dir($archive_dir)) {
        mkdir($archive_dir, 0755, true);
    }
    
    // Move current log to archive
    if (rename($log_path, $rotated_path)) {
        // Create new empty log file
        touch($log_path);
        chmod($log_path, 0644);
        
        return array(
            'success' => true, 
            'message' => 'Log rotated successfully',
            'original_size' => $file_size,
            'archived_as' => $rotated_name
        );
    }
    
    return array('success' => false, 'message' => 'Failed to rotate log');
}

function cleanup_old_logs($days_to_keep = 30) {
    $archive_dir = STORAGE_DIR . '/logs/archive';
    $deleted_count = 0;
    
    if (!is_dir($archive_dir)) {
        return array('success' => true, 'deleted' => 0);
    }
    
    $files = glob($archive_dir . '/*.log');
    $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff_time) {
            if (unlink($file)) {
                $deleted_count++;
            }
        }
    }
    
    return array('success' => true, 'deleted' => $deleted_count);
}

// CLI usage
if (php_sapi_name() === 'cli') {
    echo "=== Log Rotation Script ===\n\n";
    
    $log_files = array(
        'access.log',
        'error.log',
        'security.log',
        'admin.log',
        'file-operations.log'
    );
    
    foreach ($log_files as $log_file) {
        echo "Checking $log_file...\n";
        $result = rotate_log($log_file);
        
        if ($result['success']) {
            echo "  ✅ " . $result['message'];
            if (isset($result['original_size'])) {
                echo " (Size: " . format_file_size($result['original_size']) . ")";
            }
            echo "\n";
        } else {
            echo "  ❌ " . $result['message'] . "\n";
        }
    }
    
    echo "\nCleaning up old logs...\n";
    $cleanup_result = cleanup_old_logs();
    echo "  ✅ Deleted " . $cleanup_result['deleted'] . " old log files\n";
    
    echo "\nLog rotation complete!\n";
}

// Web API usage
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    
    session_start();
    require_once 'includes/auth-functions.php';
    
    if (!is_logged_in() || get_current_user()['role'] !== 'admin') {
        echo json_encode(array('success' => false, 'message' => 'Unauthorized'));
        exit;
    }
    
    switch ($_POST['action']) {
        case 'rotate':
            $log_file = $_POST['log_file'] ?? '';
            $result = rotate_log($log_file);
            echo json_encode($result);
            break;
            
        case 'cleanup':
            $days = $_POST['days'] ?? 30;
            $result = cleanup_old_logs($days);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(array('success' => false, 'message' => 'Invalid action'));
    }
    exit;
}
?>
