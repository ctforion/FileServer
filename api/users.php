<?php
/**
 * Users API Endpoint
 * Handle user management operations
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../core/auth/UserManager.php';
require_once __DIR__ . '/../core/logging/Logger.php';
require_once __DIR__ . '/../core/utils/SecurityManager.php';

try {
    $userManager = new UserManager();
    $logger = new Logger();
    $security = new SecurityManager();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    // Rate limiting check
    $rateLimitCheck = $security->checkRateLimit('api_calls');
    if (!$rateLimitCheck['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded: ' . $rateLimitCheck['reason']
        ]);
        exit;
    }
    
    // Handle different HTTP methods and actions
    switch ($method) {
        case 'GET':
            handleGetRequest($action, $userManager, $logger);
            break;
            
        case 'POST':
            handlePostRequest($action, $userManager, $logger);
            break;
            
        case 'PUT':
            handlePutRequest($action, $userManager, $logger);
            break;
            
        case 'DELETE':
            handleDeleteRequest($action, $userManager, $logger);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    $logger->logError('users_api_error', [
        'error' => $e->getMessage(),
        'method' => $_SERVER['REQUEST_METHOD'],
        'action' => $_GET['action'] ?? 'unknown',
        'user' => $_SESSION['username'] ?? 'anonymous'
    ]);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($action, $userManager, $logger) {
    switch ($action) {
        case 'profile':
            getProfile($userManager, $logger);
            break;
            
        case 'list':
            listUsers($userManager, $logger);
            break;
            
        case 'stats':
            getUserStats($userManager, $logger);
            break;
            
        case 'files':
            getUserFiles($userManager, $logger);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($action, $userManager, $logger) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'register':
            registerUser($input, $userManager, $logger);
            break;
            
        case 'login':
            loginUser($input, $userManager, $logger);
            break;
            
        case 'logout':
            logoutUser($userManager, $logger);
            break;
            
        case 'change_password':
            changePassword($input, $userManager, $logger);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest($action, $userManager, $logger) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update_profile':
            updateProfile($input, $userManager, $logger);
            break;
            
        case 'update_settings':
            updateSettings($input, $userManager, $logger);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($action, $userManager, $logger) {
    switch ($action) {
        case 'delete_account':
            deleteAccount($userManager, $logger);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}

/**
 * Get user profile
 */
function getProfile($userManager, $logger) {
    if (!isset($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }
    
    try {
        $profile = $userManager->getUserProfile($_SESSION['username']);
        
        if ($profile) {
            echo json_encode(['success' => true, 'profile' => $profile]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Profile not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Register new user
 */
function registerUser($input, $userManager, $logger) {
    $required = ['username', 'email', 'password'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Field required: $field"]);
            return;
        }
    }
    
    try {
        $result = $userManager->registerUser(
            $input['username'],
            $input['email'],
            $input['password'],
            $input['role'] ?? 'user'
        );
        
        if ($result['success']) {
            $logger->logAccess('user_registered', [
                'username' => $input['username'],
                'email' => $input['email'],
                'role' => $input['role'] ?? 'user'
            ]);
            
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode($result);
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Login user
 */
function loginUser($input, $userManager, $logger) {
    $required = ['username', 'password'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Field required: $field"]);
            return;
        }
    }
    
    try {
        $result = $userManager->authenticateUser($input['username'], $input['password']);
        
        if ($result['success']) {
            $_SESSION['username'] = $input['username'];
            $_SESSION['role'] = $result['user']['role'];
            $_SESSION['user_id'] = $result['user']['id'];
            
            $logger->logAccess('user_login', [
                'username' => $input['username'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => $result['user']
            ]);
        } else {
            http_response_code(401);
            echo json_encode($result);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Logout user
 */
function logoutUser($userManager, $logger) {
    if (isset($_SESSION['username'])) {
        $username = $_SESSION['username'];
        
        $logger->logAccess('user_logout', [
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logout successful']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
    }
}

/**
 * Change user password
 */
function changePassword($input, $userManager, $logger) {
    if (!isset($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }
    
    $required = ['current_password', 'new_password'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Field required: $field"]);
            return;
        }
    }
    
    try {
        $result = $userManager->changePassword(
            $_SESSION['username'],
            $input['current_password'],
            $input['new_password']
        );
        
        if ($result['success']) {
            $logger->logAccess('password_changed', [
                'username' => $_SESSION['username'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Update user profile
 */
function updateProfile($input, $userManager, $logger) {
    if (!isset($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }
    
    try {
        $result = $userManager->updateUserProfile($_SESSION['username'], $input);
        
        if ($result['success']) {
            $logger->logAccess('profile_updated', [
                'username' => $_SESSION['username'],
                'fields' => array_keys($input)
            ]);
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Update user settings
 */
function updateSettings($input, $userManager, $logger) {
    if (!isset($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }
    
    try {
        $result = $userManager->updateUserSettings($_SESSION['username'], $input);
        
        if ($result['success']) {
            $logger->logAccess('settings_updated', [
                'username' => $_SESSION['username'],
                'settings' => array_keys($input)
            ]);
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Delete user account
 */
function deleteAccount($userManager, $logger) {
    if (!isset($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }
    
    try {
        $result = $userManager->deleteUser($_SESSION['username']);
        
        if ($result['success']) {
            $logger->logAccess('account_deleted', [
                'username' => $_SESSION['username'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            session_destroy();
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * List users (admin only)
 */
function listUsers($userManager, $logger) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        return;
    }
    
    try {
        $users = $userManager->getAllUsers();
        echo json_encode(['success' => true, 'users' => $users]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get user statistics
 */
function getUserStats($userManager, $logger) {
    if (!isset($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }
    
    try {
        $stats = $userManager->getUserStatistics($_SESSION['username']);
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get user files
 */
function getUserFiles($userManager, $logger) {
    if (!isset($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }
    
    try {
        $files = $userManager->getUserFiles($_SESSION['username']);
        echo json_encode(['success' => true, 'files' => $files]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
