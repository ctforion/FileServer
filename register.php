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

$page_title = 'Register';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        show_error('Invalid security token');
    } else {
        // Validate form data
        $errors = array();
        
        if (!validate_username($username)) {
            $errors[] = 'Invalid username (3-50 characters, letters, numbers, _ and - only)';
        }
        
        if (!validate_password($password)) {
            $errors[] = 'Invalid password (minimum 6 characters)';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
        
        // Check if username already exists
        if (get_user_by_username($username)) {
            $errors[] = 'Username already exists';
        }
        
        if (empty($errors)) {
            // Register user with basic permissions
            if (register_user($username, $password, 'user', array('read'))) {
                show_success('Registration successful. You can now login.');
                redirect_to('login.php');
            } else {
                show_error('Registration failed. Please try again.');
            }
        } else {
            foreach ($errors as $error) {
                show_error($error);
            }
        }
    }
}

log_access('register_page', 'Registration page accessed');
?>

<?php include 'templates/header.html'; ?>

<?php include 'templates/register-form.html'; ?>

<?php include 'templates/footer.html'; ?>
