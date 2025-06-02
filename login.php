<?php
include_once 'includes/config.php';
include_once 'includes/functions.php';
include_once 'includes/json-functions.php';
include_once 'includes/auth-functions.php';
include_once 'includes/log-functions.php';
include_once 'includes/security-functions.php';
include_once 'includes/validation-functions.php';

// Check if user is already logged in
if (is_logged_in()) {
    redirect_to('dashboard.php');
}

// Check for blocked IP
$client_ip = get_client_ip();
if (is_ip_blocked($client_ip)) {
    log_security_event('blocked_ip_access', "Blocked IP attempted login: {$client_ip}");
    show_error('Access denied');
    redirect_to('index.php');
}

$page_title = 'Login';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    // Validate CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        show_error('Invalid security token');
    } else {
        // Attempt login
        if (login_user($username, $password)) {
            show_success('Login successful');
            redirect_to('dashboard.php');
        } else {
            show_error('Invalid username or password');
            log_security_event('failed_login', "Failed login for username: {$username} from IP: {$client_ip}");
        }
    }
}

log_access('login_page', 'Login page accessed');
?>

<?php include 'templates/header.html'; ?>

<?php include 'templates/login-form.html'; ?>

<?php include 'templates/footer.html'; ?>
