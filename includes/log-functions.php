<?php
// Logging functions

function write_log($filename, $message, $level = 'INFO') {
    global $config;
    $filepath = STORAGE_DIR . '/' . $config['logs_path'] . $filename;
    
    $timestamp = get_current_timestamp();
    $ip = get_client_ip();
    $user = isset($_SESSION['username']) ? $_SESSION['username'] : 'anonymous';
    
    $log_entry = "[{$timestamp}] [{$level}] [{$ip}] [{$user}] {$message}" . PHP_EOL;
    
    return file_put_contents($filepath, $log_entry, FILE_APPEND | LOCK_EX) !== false;
}

function log_access($action, $details = '') {
    $message = "ACCESS: {$action}";
    if ($details) {
        $message .= " - {$details}";
    }
    return write_log('access.log', $message);
}

function log_error($error, $details = '') {
    $message = "ERROR: {$error}";
    if ($details) {
        $message .= " - {$details}";
    }
    return write_log('error.log', $message, 'ERROR');
}

function log_security_event($event, $details = '') {
    $message = "SECURITY: {$event}";
    if ($details) {
        $message .= " - {$details}";
    }
    return write_log('security.log', $message, 'SECURITY');
}

function log_admin_action($action, $details = '') {
    $message = "ADMIN: {$action}";
    if ($details) {
        $message .= " - {$details}";
    }
    return write_log('admin.log', $message, 'ADMIN');
}

function log_file_operation($operation, $filename, $details = '') {
    $message = "FILE: {$operation} - {$filename}";
    if ($details) {
        $message .= " - {$details}";
    }
    return write_log('file-operations.log', $message);
}

function log_activity($action, $details = '') {
    $logs = read_json_file('logs.json');
    
    $log_entry = array(
        'id' => get_next_id($logs),
        'timestamp' => get_current_timestamp(),
        'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
        'username' => isset($_SESSION['username']) ? $_SESSION['username'] : 'anonymous',
        'ip_address' => get_client_ip(),
        'action' => $action,
        'details' => $details,
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
    );
    
    $logs[] = $log_entry;
    
    // Keep only last 1000 entries to prevent file from getting too large
    if (count($logs) > 1000) {
        $logs = array_slice($logs, -1000);
    }
    
    return write_json_file('logs.json', $logs);
}

function get_recent_logs($limit = 50) {
    $logs = read_json_file('logs.json');
    return array_slice(array_reverse($logs), 0, $limit);
}

function clean_old_logs($days = 30) {
    global $config;
    $log_files = array('access.log', 'error.log', 'security.log', 'admin.log', 'file-operations.log');
    
    foreach ($log_files as $log_file) {
        $filepath = $config['logs_path'] . $log_file;
        if (file_exists($filepath)) {
            $modified_time = filemtime($filepath);
            if ($modified_time < (time() - ($days * 24 * 60 * 60))) {
                unlink($filepath);
            }
        }
    }
}
?>
