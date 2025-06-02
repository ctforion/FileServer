<?php
require_once '../includes/config.php';
require_once '../includes/auth-functions.php';
require_once '../includes/log-functions.php';
require_once '../includes/json-functions.php';
require_once '../includes/validation-functions.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Start session
session_start();

$response = ['success' => false, 'message' => 'Invalid request'];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'login':
            if ($method === 'POST') {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                
                if (empty($username) || empty($password)) {
                    $response = ['success' => false, 'message' => 'Username and password are required'];
                } else {
                    $result = authenticate_user($username, $password);
                    if ($result['success']) {
                        $_SESSION['user_id'] = $result['user']['id'];
                        $_SESSION['username'] = $result['user']['username'];
                        $_SESSION['role'] = $result['user']['role'];
                        $_SESSION['csrf_token'] = generate_csrf_token();
                        
                        log_access("User logged in: " . $username);
                        
                        $response = [
                            'success' => true,
                            'message' => 'Login successful',
                            'user' => [
                                'id' => $result['user']['id'],
                                'username' => $result['user']['username'],
                                'role' => $result['user']['role']
                            ],
                            'redirect' => 'dashboard.php'
                        ];
                    } else {
                        log_security("Failed login attempt for username: " . $username . " from IP: " . get_client_ip());
                        $response = ['success' => false, 'message' => $result['message']];
                    }
                }
            }
            break;
            
        case 'logout':
            if (isset($_SESSION['username'])) {
                log_access("User logged out: " . $_SESSION['username']);
            }
            
            session_destroy();
            $response = [
                'success' => true,
                'message' => 'Logged out successfully',
                'redirect' => 'index.php'
            ];
            break;
            
        case 'register':
            if ($method === 'POST') {
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                // Basic validation
                if (empty($username) || empty($password)) {
                    $response = ['success' => false, 'message' => 'Username and password are required'];
                } elseif ($password !== $confirm_password) {
                    $response = ['success' => false, 'message' => 'Passwords do not match'];
                } elseif (user_exists($username)) {
                    $response = ['success' => false, 'message' => 'Username already exists'];
                } else {
                    $result = create_user($username, $password, $email, 'user');
                    if ($result['success']) {
                        log_access("New user registered: " . $username);
                        $response = [
                            'success' => true,
                            'message' => 'Account created successfully. You can now log in.',
                            'redirect' => 'login.php'
                        ];
                    } else {
                        $response = ['success' => false, 'message' => $result['message']];
                    }
                }
            }
            break;
            
        case 'check_session':
            if (is_user_logged_in()) {
                $current_user = get_current_user();
                $response = [
                    'success' => true,
                    'logged_in' => true,
                    'user' => [
                        'id' => $current_user['id'],
                        'username' => $current_user['username'],
                        'role' => $current_user['role']
                    ]
                ];
            } else {
                $response = [
                    'success' => true,
                    'logged_in' => false
                ];
            }
            break;
            
        case 'csrf_token':
            $response = [
                'success' => true,
                'csrf_token' => generate_csrf_token()
            ];
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Unknown action'];
            break;
    }
    
} catch (Exception $e) {
    log_error("Auth API error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Server error occurred'];
}

echo json_encode($response);
?>
