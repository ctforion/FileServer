<?php
// User management functions

function get_all_users() {
    return read_json_file('users.json');
}

function get_user_by_id($user_id) {
    $users = read_json_file('users.json');
    return find_by_id($users, $user_id);
}

function get_user_by_username($username) {
    $users = read_json_file('users.json');
    
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            return $user;
        }
    }
    
    return null;
}

function update_user($user_id, $updates) {
    $users = read_json_file('users.json');
    $users = update_by_id($users, $user_id, $updates);
    
    if (write_json_file('users.json', $users)) {
        $user = find_by_id($users, $user_id);
        log_admin_action('user_updated', "User updated: {$user['username']}");
        return true;
    }
    
    return false;
}

function delete_user($user_id) {
    $users = read_json_file('users.json');
    $user = find_by_id($users, $user_id);
    
    if ($user) {
        $users = remove_by_id($users, $user_id);
        
        if (write_json_file('users.json', $users)) {
            log_admin_action('user_deleted', "User deleted: {$user['username']}");
            return true;
        }
    }
    
    return false;
}

function change_user_password($user_id, $new_password) {
    $updates = array(
        'password' => $new_password,
        'password_changed_at' => get_current_timestamp()
    );
    
    return update_user($user_id, $updates);
}

function set_user_permissions($user_id, $permissions) {
    $updates = array(
        'permissions' => $permissions,
        'permissions_updated_at' => get_current_timestamp()
    );
    
    return update_user($user_id, $updates);
}

function set_user_role($user_id, $role) {
    $updates = array(
        'role' => $role,
        'role_updated_at' => get_current_timestamp()
    );
    
    return update_user($user_id, $updates);
}

function activate_user($user_id) {
    $updates = array(
        'status' => 'active',
        'activated_at' => get_current_timestamp()
    );
    
    return update_user($user_id, $updates);
}

function deactivate_user($user_id) {
    $updates = array(
        'status' => 'inactive',
        'deactivated_at' => get_current_timestamp()
    );
    
    return update_user($user_id, $updates);
}

function get_user_files($user_id) {
    $files = read_json_file('files.json');
    $user_files = array();
    
    foreach ($files as $file) {
        if ($file['uploaded_by'] == $user_id) {
            $user_files[] = $file;
        }
    }
    
    return $user_files;
}

function get_user_activity($user_id, $limit = 50) {
    $logs = read_json_file('logs.json');
    $user_activity = array();
    
    foreach ($logs as $log) {
        if ($log['user_id'] == $user_id) {
            $user_activity[] = $log;
        }
    }
    
    // Sort by timestamp descending
    usort($user_activity, function($a, $b) {
        return strcmp($b['timestamp'], $a['timestamp']);
    });
    
    return array_slice($user_activity, 0, $limit);
}

function get_user_stats($user_id) {
    $files = get_user_files($user_id);
    $activity = get_user_activity($user_id, 100);
    
    $stats = array(
        'total_files' => count($files),
        'total_size' => 0,
        'total_downloads' => 0,
        'recent_uploads' => 0,
        'last_login' => null,
        'total_logins' => 0
    );
    
    foreach ($files as $file) {
        $stats['total_size'] += $file['size'];
        $stats['total_downloads'] += $file['downloads'];
        
        // Count uploads in last 30 days
        $upload_date = strtotime($file['uploaded_at']);
        if ($upload_date > (time() - (30 * 24 * 60 * 60))) {
            $stats['recent_uploads']++;
        }
    }
    
    foreach ($activity as $log) {
        if ($log['action'] === 'login') {
            $stats['total_logins']++;
            if (!$stats['last_login'] || $log['timestamp'] > $stats['last_login']) {
                $stats['last_login'] = $log['timestamp'];
            }
        }
    }
    
    return $stats;
}

function validate_user_permissions($permissions) {
    $valid_permissions = array('read', 'write', 'admin');
    
    if (!is_array($permissions)) {
        return false;
    }
    
    foreach ($permissions as $permission) {
        if (!in_array($permission, $valid_permissions)) {
            return false;
        }
    }
    
    return true;
}

function validate_user_role($role) {
    $valid_roles = array('user', 'admin');
    return in_array($role, $valid_roles);
}

function can_user_access_file($user_id, $file_id) {
    $user = get_user_by_id($user_id);
    $file = find_by_id(read_json_file('files.json'), $file_id);
    
    if (!$user || !$file) {
        return false;
    }
    
    // Admin can access all files
    if ($user['role'] === 'admin') {
        return true;
    }
    
    // User can access their own files
    if ($file['uploaded_by'] == $user_id) {
        return true;
    }
    
    // Check if file is public
    if (isset($file['is_public']) && $file['is_public']) {
        return true;
    }
    
    return false;
}

function get_users_summary() {
    $users = read_json_file('users.json');
    
    $summary = array(
        'total_users' => count($users),
        'active_users' => 0,
        'admin_users' => 0,
        'new_users_today' => 0
    );
    
    $today = date('Y-m-d');
    
    foreach ($users as $user) {
        if ($user['status'] === 'active') {
            $summary['active_users']++;
        }
        
        if ($user['role'] === 'admin') {
            $summary['admin_users']++;
        }
        
        if (strpos($user['created_at'], $today) === 0) {
            $summary['new_users_today']++;
        }
    }
    
    return $summary;
}

function calculate_user_storage($user_id) {
    $files = get_user_files($user_id);
    $total_size = 0;
    
    foreach ($files as $file) {
        $total_size += $file['size'];
    }
    
    return $total_size;
}
?>
