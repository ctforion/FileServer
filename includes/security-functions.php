<?php
// Security and validation functions

function validate_file_upload($file) {
    global $config;
    
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return array('valid' => false, 'error' => 'Upload error occurred');
    }
    
    if ($file['size'] > $config['max_file_size']) {
        return array('valid' => false, 'error' => 'File size exceeds maximum allowed size');
    }
    
    $extension = get_file_extension($file['name']);
    
    if (in_array($extension, $config['quarantine_extensions'])) {
        return array('valid' => false, 'error' => 'File type not allowed', 'quarantine' => true);
    }
    
    if (!in_array($extension, $config['allowed_extensions'])) {
        return array('valid' => false, 'error' => 'File type not supported');
    }
    
    return array('valid' => true);
}

function is_ip_blocked($ip) {
    $blocked_ips = read_json_file('blocked-ips.json');
    
    foreach ($blocked_ips as $blocked_ip) {
        if ($blocked_ip['ip'] === $ip && $blocked_ip['status'] === 'active') {
            return true;
        }
    }
    
    return false;
}

function block_ip($ip, $reason = '') {
    $blocked_ips = read_json_file('blocked-ips.json');
    
    $block_entry = array(
        'id' => get_next_id($blocked_ips),
        'ip' => $ip,
        'reason' => $reason,
        'blocked_at' => get_current_timestamp(),
        'blocked_by' => isset($_SESSION['username']) ? $_SESSION['username'] : 'system',
        'status' => 'active'
    );
    
    $blocked_ips[] = $block_entry;
    
    if (write_json_file('blocked-ips.json', $blocked_ips)) {
        log_security_event('ip_blocked', "IP {$ip} blocked - {$reason}");
        return true;
    }
    
    return false;
}

function unblock_ip($ip) {
    $blocked_ips = read_json_file('blocked-ips.json');
    
    for ($i = 0; $i < count($blocked_ips); $i++) {
        if ($blocked_ips[$i]['ip'] === $ip) {
            $blocked_ips[$i]['status'] = 'inactive';
            $blocked_ips[$i]['unblocked_at'] = get_current_timestamp();
            $blocked_ips[$i]['unblocked_by'] = isset($_SESSION['username']) ? $_SESSION['username'] : 'system';
            break;
        }
    }
    
    if (write_json_file('blocked-ips.json', $blocked_ips)) {
        log_security_event('ip_unblocked', "IP {$ip} unblocked");
        return true;
    }
    
    return false;
}

function check_directory_traversal($path) {
    $safe_path = safe_path($path);
    return $safe_path === $path;
}

function scan_file_for_threats($filepath) {
    $threats = array();
    
    // Check file size
    $file_size = filesize($filepath);
    if ($file_size > 100 * 1024 * 1024) { // 100MB
        $threats[] = 'File size too large';
    }
    
    // Check for suspicious content in text files
    $extension = get_file_extension($filepath);
    $text_extensions = array('txt', 'php', 'html', 'js', 'css', 'xml', 'json');
    
    if (in_array($extension, $text_extensions)) {
        $content = file_get_contents($filepath, false, null, 0, 1024); // Read first 1KB
        
        $suspicious_patterns = array(
            '/<script.*?>.*?<\/script>/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/base64_decode\s*\(/i'
        );
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $threats[] = 'Suspicious code pattern detected';
                break;
            }
        }
    }
    
    return $threats;
}

function quarantine_file($source_path, $reason = '') {
    global $config;
    
    $filename = basename($source_path);
    $quarantine_filename = date('Y-m-d_H-i-s') . '_' . $filename;
    $quarantine_path = $config['quarantine_path'] . $quarantine_filename;
    
    if (move_uploaded_file($source_path, $quarantine_path)) {
        log_security_event('file_quarantined', "File quarantined: {$filename} - {$reason}");
        return $quarantine_path;
    }
    
    return false;
}

function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validate_username($username) {
    if (strlen($username) < 3 || strlen($username) > 50) {
        return false;
    }
    
    return preg_match('/^[a-zA-Z0-9_-]+$/', $username);
}

function validate_password($password) {
    return strlen($password) >= 6;
}
?>
