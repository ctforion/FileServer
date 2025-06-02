<?php
// Validation functions

function validate_csrf($token) {
    return verify_csrf_token($token);
}

function validate_file_name($filename) {
    if (empty($filename) || strlen($filename) > 255) {
        return false;
    }
    
    // Check for dangerous characters
    $dangerous_chars = array('/', '\\', ':', '*', '?', '"', '<', '>', '|');
    
    foreach ($dangerous_chars as $char) {
        if (strpos($filename, $char) !== false) {
            return false;
        }
    }
    
    return true;
}

function validate_file_path($path) {
    if (empty($path)) {
        return true; // Empty path is valid (root)
    }
    
    // Check for directory traversal
    if (strpos($path, '..') !== false) {
        return false;
    }
    
    // Check for dangerous characters
    $dangerous_chars = array('\\', ':', '*', '?', '"', '<', '>', '|');
    
    foreach ($dangerous_chars as $char) {
        if (strpos($path, $char) !== false) {
            return false;
        }
    }
    
    return true;
}

function validate_search_query($query) {
    if (strlen($query) < 1 || strlen($query) > 100) {
        return false;
    }
    
    // Allow alphanumeric, spaces, and basic punctuation
    return preg_match('/^[a-zA-Z0-9\s\.\-_]+$/', $query);
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_integer($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return false;
    }
    
    $int_value = intval($value);
    
    if ($min !== null && $int_value < $min) {
        return false;
    }
    
    if ($max !== null && $int_value > $max) {
        return false;
    }
    
    return true;
}

function validate_date($date, $format = 'Y-m-d H:i:s') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function validate_array_values($array, $allowed_values) {
    if (!is_array($array)) {
        return false;
    }
    
    foreach ($array as $value) {
        if (!in_array($value, $allowed_values)) {
            return false;
        }
    }
    
    return true;
}

function sanitize_array($array) {
    $sanitized = array();
    
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $sanitized[sanitize_input($key)] = sanitize_array($value);
        } else {
            $sanitized[sanitize_input($key)] = sanitize_input($value);
        }
    }
    
    return $sanitized;
}

function validate_upload_form($post_data, $files_data) {
    $errors = array();
    
    // Validate CSRF token
    if (!isset($post_data['csrf_token']) || !validate_csrf($post_data['csrf_token'])) {
        $errors[] = 'Invalid security token';
    }
    
    // Validate file
    if (!isset($files_data['file']) || $files_data['file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'No file uploaded or upload error occurred';
    } else {
        $validation = validate_file_upload($files_data['file']);
        if (!$validation['valid']) {
            $errors[] = $validation['error'];
        }
    }
    
    // Validate path if provided
    if (isset($post_data['path']) && !validate_file_path($post_data['path'])) {
        $errors[] = 'Invalid file path';
    }
    
    // Validate description if provided
    if (isset($post_data['description']) && strlen($post_data['description']) > 500) {
        $errors[] = 'Description too long (max 500 characters)';
    }
    
    return $errors;
}

function validate_user_form($post_data, $is_edit = false) {
    $errors = array();
    
    // Validate CSRF token
    if (!isset($post_data['csrf_token']) || !validate_csrf($post_data['csrf_token'])) {
        $errors[] = 'Invalid security token';
    }
    
    // Validate username
    if (!isset($post_data['username']) || !validate_username($post_data['username'])) {
        $errors[] = 'Invalid username (3-50 characters, letters, numbers, _ and - only)';
    }
    
    // Validate password (only for new users or if password is being changed)
    if (!$is_edit || (isset($post_data['password']) && !empty($post_data['password']))) {
        if (!isset($post_data['password']) || !validate_password($post_data['password'])) {
            $errors[] = 'Invalid password (minimum 6 characters)';
        }
    }
    
    // Validate role
    if (isset($post_data['role']) && !validate_user_role($post_data['role'])) {
        $errors[] = 'Invalid user role';
    }
    
    // Validate permissions
    if (isset($post_data['permissions']) && !validate_user_permissions($post_data['permissions'])) {
        $errors[] = 'Invalid permissions';
    }
    
    return $errors;
}

function validate_search_form($post_data) {
    $errors = array();
    
    // Validate query
    if (!isset($post_data['query']) || !validate_search_query($post_data['query'])) {
        $errors[] = 'Invalid search query';
    }
    
    // Validate date filters if provided
    if (isset($post_data['date_from']) && !empty($post_data['date_from'])) {
        if (!validate_date($post_data['date_from'], 'Y-m-d')) {
            $errors[] = 'Invalid date format for date from';
        }
    }
    
    if (isset($post_data['date_to']) && !empty($post_data['date_to'])) {
        if (!validate_date($post_data['date_to'], 'Y-m-d')) {
            $errors[] = 'Invalid date format for date to';
        }
    }
    
    return $errors;
}

function clean_form_data($data) {
    $cleaned = array();
    
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $cleaned[$key] = clean_form_data($value);
        } else {
            $cleaned[$key] = trim($value);
        }
    }
    
    return $cleaned;
}
?>
