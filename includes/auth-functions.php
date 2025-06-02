<?php
// Authentication functions

if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}
if (!function_exists('require_login')) {
    function require_login() {
        if (!is_logged_in()) {
            redirect_to('login.php');
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin() {
        if (!is_admin()) {
            redirect_to('index.php');
        }
    }
}

if (!function_exists('require_authentication')) {
    function require_authentication() {
        if (!is_logged_in()) {
            redirect_to('login.php');
        }
    }
}

if (!function_exists('login_user')) {
    function login_user($username, $password) {
        $users = read_json_file('users.json');
        
        foreach ($users as $user) {
            if ($user['username'] === $username && $user['password'] === $password) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['permissions'] = $user['permissions'];
                
                log_activity('login', 'User logged in: ' . $username);
                return true;
            }
        }
        
        log_security_event('failed_login', 'Failed login attempt for username: ' . $username);
        return false;
    }
}

if (!function_exists('logout_user')) {
    function logout_user() {
        $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';
        
        session_destroy();
        session_start();
        
        // Regenerate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        log_activity('logout', 'User logged out: ' . $username);
    }
}

if (!function_exists('register_user')) {
    function register_user($username, $password, $role = 'user', $permissions = array('read')) {
        $users = read_json_file('users.json');
        
        // Check if username already exists
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                return false;
            }
        }
        
        $new_user = array(
            'id' => get_next_id($users),
            'username' => $username,
            'password' => $password,
            'role' => $role,
            'permissions' => $permissions,
            'created_at' => get_current_timestamp(),
            'last_login' => null,
            'status' => 'active'
        );
        
        $users[] = $new_user;
        
        if (write_json_file('users.json', $users)) {
            log_activity('user_created', 'New user registered: ' . $username);
            return true;
        }
        
        return false;
    }
}


if (!function_exists('get_current_user')) {
    function get_current_user() {
        if (!is_logged_in()) {
            return null;
        }
        
        $users = read_json_file('users.json');
        return find_by_id($users, $_SESSION['user_id']);
    }
}

if (!function_exists('has_permission')) {
    function has_permission($permission) {
        if (is_admin()) {
            return true;
        }
        
        if (!isset($_SESSION['permissions'])) {
            return false;
        }
        
        return in_array($permission, $_SESSION['permissions']);
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

?>