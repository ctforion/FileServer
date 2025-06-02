<?php
/**
 * Users API Endpoint
 * Handles user management operations (admin only)
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth-functions.php';
require_once '../includes/user-functions.php';
require_once '../includes/json-functions.php';
require_once '../includes/log-functions.php';
require_once '../includes/security-functions.php';
require_once '../includes/validation-functions.php';

header('Content-Type: application/json');

// Check authentication and admin permission
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$user = get_current_user();
if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handle_get_request();
            break;
        case 'POST':
            handle_post_request();
            break;
        case 'PUT':
            handle_put_request();
            break;
        case 'DELETE':
            handle_delete_request();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    log_error('API Users Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handle_get_request() {
    if (isset($_GET['id'])) {
        // Get specific user
        $user_id = sanitize_input($_GET['id']);
        $user = get_user_by_id($user_id);
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
        
        // Remove sensitive data
        unset($user['password']);
        echo json_encode(['user' => $user]);
        
    } elseif (isset($_GET['stats'])) {
        // Get user statistics
        $stats = get_user_statistics();
        echo json_encode(['stats' => $stats]);
        
    } else {
        // List all users
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $search = sanitize_input($_GET['search'] ?? '');
        $role = sanitize_input($_GET['role'] ?? '');
        $status = sanitize_input($_GET['status'] ?? '');
        
        $users = get_all_users($page, $limit, $search, $role, $status);
        $total = count_users($search, $role, $status);
        
        // Remove passwords from all users
        foreach ($users as &$user_data) {
            unset($user_data['password']);
        }
        
        echo json_encode([
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
}

function handle_post_request() {
    global $user;
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_user':
            $username = sanitize_input($_POST['username']);
            $email = sanitize_input($_POST['email']);
            $password = $_POST['password'];
            $role = sanitize_input($_POST['role'] ?? 'user');
            
            // Validate input
            if (empty($username) || empty($email) || empty($password)) {
                echo json_encode(['error' => 'All fields are required']);
                return;
            }
            
            if (!validate_email($email)) {
                echo json_encode(['error' => 'Invalid email format']);
                return;
            }
            
            if (!validate_password($password)) {
                echo json_encode(['error' => 'Password must be at least 8 characters']);
                return;
            }
            
            // Check if user exists
            if (get_user_by_username($username) || get_user_by_email($email)) {
                echo json_encode(['error' => 'Username or email already exists']);
                return;
            }
            
            $new_user_id = create_user($username, $email, $password, $role);
            if ($new_user_id) {
                log_admin_action('create_user', "Created user: $username", $user['username']);
                echo json_encode(['success' => true, 'user_id' => $new_user_id, 'message' => 'User created successfully']);
            } else {
                echo json_encode(['error' => 'Failed to create user']);
            }
            break;
            
        case 'bulk_action':
            $user_ids = $_POST['user_ids'] ?? [];
            $bulk_action = sanitize_input($_POST['bulk_action']);
            
            if (!is_array($user_ids) || empty($user_ids)) {
                echo json_encode(['error' => 'No users selected']);
                return;
            }
            
            $results = [];
            $success_count = 0;
            
            foreach ($user_ids as $user_id) {
                $target_user = get_user_by_id($user_id);
                if (!$target_user) {
                    $results[] = ['user_id' => $user_id, 'error' => 'User not found'];
                    continue;
                }
                
                // Prevent admin from acting on themselves
                if ($user_id === $user['id']) {
                    $results[] = ['user_id' => $user_id, 'error' => 'Cannot perform action on yourself'];
                    continue;
                }
                
                switch ($bulk_action) {
                    case 'activate':
                        if (update_user_status($user_id, 'active')) {
                            $results[] = ['user_id' => $user_id, 'success' => true];
                            $success_count++;
                            log_admin_action('activate_user', "Activated user: {$target_user['username']}", $user['username']);
                        } else {
                            $results[] = ['user_id' => $user_id, 'error' => 'Failed to activate'];
                        }
                        break;
                        
                    case 'suspend':
                        if (update_user_status($user_id, 'suspended')) {
                            $results[] = ['user_id' => $user_id, 'success' => true];
                            $success_count++;
                            log_admin_action('suspend_user', "Suspended user: {$target_user['username']}", $user['username']);
                        } else {
                            $results[] = ['user_id' => $user_id, 'error' => 'Failed to suspend'];
                        }
                        break;
                        
                    case 'delete':
                        if (delete_user($user_id)) {
                            $results[] = ['user_id' => $user_id, 'success' => true];
                            $success_count++;
                            log_admin_action('delete_user', "Deleted user: {$target_user['username']}", $user['username']);
                        } else {
                            $results[] = ['user_id' => $user_id, 'error' => 'Failed to delete'];
                        }
                        break;
                        
                    default:
                        $results[] = ['user_id' => $user_id, 'error' => 'Invalid action'];
                }
            }
            
            echo json_encode([
                'success' => $success_count > 0,
                'processed' => count($user_ids),
                'successful' => $success_count,
                'results' => $results
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handle_put_request() {
    global $user;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = $input['user_id'] ?? '';
    $updates = $input['updates'] ?? [];
    
    if (empty($user_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required']);
        return;
    }
    
    $target_user = get_user_by_id($user_id);
    if (!$target_user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }
    
    // Prevent admin from changing their own role
    if ($user_id === $user['id'] && isset($updates['role'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot change your own role']);
        return;
    }
    
    // Validate updates
    $allowed_fields = ['username', 'email', 'role', 'status', 'storage_limit'];
    $validated_updates = [];
    
    foreach ($updates as $field => $value) {
        if (!in_array($field, $allowed_fields)) {
            continue;
        }
        
        switch ($field) {
            case 'email':
                if (!validate_email($value)) {
                    echo json_encode(['error' => 'Invalid email format']);
                    return;
                }
                break;
            case 'role':
                if (!in_array($value, ['user', 'admin'])) {
                    echo json_encode(['error' => 'Invalid role']);
                    return;
                }
                break;
            case 'status':
                if (!in_array($value, ['active', 'suspended', 'inactive'])) {
                    echo json_encode(['error' => 'Invalid status']);
                    return;
                }
                break;
            case 'storage_limit':
                if (!is_numeric($value) || $value < 0) {
                    echo json_encode(['error' => 'Invalid storage limit']);
                    return;
                }
                break;
        }
        
        $validated_updates[$field] = sanitize_input($value);
    }
    
    if (update_user($user_id, $validated_updates)) {
        log_admin_action('update_user', "Updated user: {$target_user['username']}", $user['username']);
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        echo json_encode(['error' => 'Failed to update user']);
    }
}

function handle_delete_request() {
    global $user;
    
    $user_id = $_GET['id'] ?? '';
    
    if (empty($user_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required']);
        return;
    }
    
    if ($user_id === $user['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot delete yourself']);
        return;
    }
    
    $target_user = get_user_by_id($user_id);
    if (!$target_user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }
    
    if (delete_user($user_id)) {
        log_admin_action('delete_user', "Deleted user: {$target_user['username']}", $user['username']);
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['error' => 'Failed to delete user']);
    }
}
