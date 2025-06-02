<?php
// Basic utility functions

function get_current_timestamp() {
    global $config;
    return date($config['date_format']);
}

function generate_random_string($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function sanitize_filename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    $filename = preg_replace('/_{2,}/', '_', $filename);
    return trim($filename, '_');
}

function get_file_extension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function format_file_size($bytes) {
    if ($bytes == 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $pow = floor(log($bytes) / log(1024));
    return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
}

function redirect_to($url) {
    header('Location: ' . $url);
    exit();
}

function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function create_directory_if_not_exists($path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

function safe_path($path) {
    $path = str_replace(array('..', '\\'), array('', '/'), $path);
    $path = preg_replace('/\/+/', '/', $path);
    return trim($path, '/');
}

function show_error($message) {
    $_SESSION['error_message'] = $message;
}

function show_success($message) {
    $_SESSION['success_message'] = $message;
}

function get_error_message() {
    if (isset($_SESSION['error_message'])) {
        $message = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        return $message;
    }
    return null;
}

function get_success_message() {
    if (isset($_SESSION['success_message'])) {
        $message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        return $message;
    }
    return null;
}
?>
