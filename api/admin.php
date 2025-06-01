<?php
/**
 * Admin API Endpoint
 * Handle administrative operations
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../core/auth/AdminManager.php';
require_once __DIR__ . '/../core/auth/UserManager.php';
require_once __DIR__ . '/../core/logging/Logger.php';
require_once __DIR__ . '/../core/utils/SecurityManager.php';

try {
    // Check admin authentication
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }
    
    $adminManager = new AdminManager();
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
            handleGetRequest($action, $adminManager, $userManager, $logger);
            break;
            
        case 'POST':
            handlePostRequest($action, $adminManager, $userManager, $logger);
            break;
            
        case 'PUT':
            handlePutRequest($action, $adminManager, $userManager, $logger);
            break;
            
        case 'DELETE':
            handleDeleteRequest($action, $adminManager, $userManager, $logger);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    $logger->logError('admin_api_error', [
        'error' => $e->getMessage(),
        'method' => $_SERVER['REQUEST_METHOD'],
        'action' => $_GET['action'] ?? 'unknown',
        'admin' => $_SESSION['username'] ?? 'unknown'
    ]);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * Handle GET requests
 */
function handleGetRequest($action, $adminManager, $userManager, $logger) {
    switch ($action) {
        case 'dashboard':
            getDashboard($adminManager, $logger);
            break;
            
        case 'users':
            getUsers($userManager, $logger);
            break;
            
        case 'user':
            getUser($userManager, $logger);
            break;
            
        case 'system_info':
            getSystemInfo($adminManager, $logger);
            break;
            
        case 'logs':
            getLogs($adminManager, $logger);
            break;
            
        case 'security_alerts':
            getSecurityAlerts($adminManager, $logger);
            break;
            
        case 'settings':
            getSettings($adminManager, $logger);
            break;
            
        case 'file_stats':
            getFileStats($adminManager, $logger);
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
function handlePostRequest($action, $adminManager, $userManager, $logger) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create_user':
            createUser($input, $userManager, $logger);
            break;
            
        case 'maintenance':
            performMaintenance($input, $adminManager, $logger);
            break;
            
        case 'backup':
            createBackup($input, $adminManager, $logger);
            break;
            
        case 'restore':
            restoreBackup($input, $adminManager, $logger);
            break;
            
        case 'clear_logs':
            clearLogs($input, $adminManager, $logger);
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
function handlePutRequest($action, $adminManager, $userManager, $logger) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update_user':
            updateUser($input, $userManager, $logger);
            break;
            
        case 'update_settings':
            updateSettings($input, $adminManager, $logger);
            break;
            
        case 'user_status':
            updateUserStatus($input, $userManager, $logger);
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
function handleDeleteRequest($action, $adminManager, $userManager, $logger) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'delete_user':
            deleteUser($input, $userManager, $logger);
            break;
            
        case 'delete_file':
            deleteFile($input, $adminManager, $logger);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}

/**
 * Get admin dashboard data
 */
function getDashboard($adminManager, $logger) {
    try {
        $dashboard = $adminManager->getDashboardData();
        echo json_encode(['success' => true, 'dashboard' => $dashboard]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get all users
 */
function getUsers($userManager, $logger) {
    try {
        $users = $userManager->getAllUsers();
        echo json_encode(['success' => true, 'users' => $users]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get specific user
 */
function getUser($userManager, $logger) {
    $username = $_GET['username'] ?? '';
    if (empty($username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username required']);
        return;
    }
    
    try {
        $user = $userManager->getUserProfile($username);
        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get system information
 */
function getSystemInfo($adminManager, $logger) {
    try {
        $systemInfo = $adminManager->getSystemInfo();
        echo json_encode(['success' => true, 'system_info' => $systemInfo]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get system logs
 */
function getLogs($adminManager, $logger) {
    $type = $_GET['type'] ?? 'all';
    $limit = intval($_GET['limit'] ?? 100);
    $offset = intval($_GET['offset'] ?? 0);
    
    try {
        $logs = $adminManager->getSystemLogs($type, $limit, $offset);
        echo json_encode(['success' => true, 'logs' => $logs]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get security alerts
 */
function getSecurityAlerts($adminManager, $logger) {
    try {
        $alerts = $adminManager->getSecurityAlerts();
        echo json_encode(['success' => true, 'alerts' => $alerts]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get system settings
 */
function getSettings($adminManager, $logger) {
    try {
        $settings = $adminManager->getSystemSettings();
        echo json_encode(['success' => true, 'settings' => $settings]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get file statistics
 */
function getFileStats($adminManager, $logger) {
    try {
        $stats = $adminManager->getFileStatistics();
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Create new user
 */
function createUser($input, $userManager, $logger) {
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
            $input['role'] ?? 'user',
            $input['quota'] ?? null
        );
        
        if ($result['success']) {
            $logger->logAdmin('user_created_by_admin', [
                'username' => $input['username'],
                'email' => $input['email'],
                'role' => $input['role'] ?? 'user',
                'created_by' => $_SESSION['username']
            ]);
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Update user
 */
function updateUser($input, $userManager, $logger) {
    $username = $input['username'] ?? '';
    if (empty($username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username required']);
        return;
    }
    
    try {
        $result = $userManager->updateUserProfile($username, $input);
        
        if ($result['success']) {
            $logger->logAdmin('user_updated_by_admin', [
                'username' => $username,
                'fields' => array_keys($input),
                'updated_by' => $_SESSION['username']
            ]);
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Update user status
 */
function updateUserStatus($input, $userManager, $logger) {
    $username = $input['username'] ?? '';
    $status = $input['status'] ?? '';
    
    if (empty($username) || empty($status)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username and status required']);
        return;
    }
    
    try {
        $result = $userManager->updateUserStatus($username, $status);
        
        if ($result['success']) {
            $logger->logAdmin('user_status_changed', [
                'username' => $username,
                'new_status' => $status,
                'changed_by' => $_SESSION['username']
            ]);
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Delete user
 */
function deleteUser($input, $userManager, $logger) {
    $username = $input['username'] ?? '';
    if (empty($username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username required']);
        return;
    }
    
    if ($username === 'admin') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot delete admin user']);
        return;
    }
    
    try {
        $result = $userManager->deleteUser($username);
        
        if ($result['success']) {
            $logger->logAdmin('user_deleted_by_admin', [
                'username' => $username,
                'deleted_by' => $_SESSION['username']
            ]);
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Perform maintenance operation
 */
function performMaintenance($input, $adminManager, $logger) {
    $operation = $input['operation'] ?? '';
    $options = $input['options'] ?? [];
    
    if (empty($operation)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Operation required']);
        return;
    }
    
    try {
        $result = $adminManager->performMaintenance($operation, $options);
        
        $logger->logAdmin('maintenance_performed', [
            'operation' => $operation,
            'options' => $options,
            'performed_by' => $_SESSION['username']
        ]);
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Create backup
 */
function createBackup($input, $adminManager, $logger) {
    try {
        $result = $adminManager->createSystemBackup($input['path'] ?? null);
        
        $logger->logAdmin('backup_created', [
            'backup_path' => $result['backup_file'] ?? 'unknown',
            'created_by' => $_SESSION['username']
        ]);
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Restore backup
 */
function restoreBackup($input, $adminManager, $logger) {
    $backupPath = $input['backup_path'] ?? '';
    if (empty($backupPath)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Backup path required']);
        return;
    }
    
    try {
        $result = $adminManager->restoreSystemBackup($backupPath);
        
        $logger->logAdmin('backup_restored', [
            'backup_path' => $backupPath,
            'restored_by' => $_SESSION['username']
        ]);
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Clear logs
 */
function clearLogs($input, $adminManager, $logger) {
    $logType = $input['log_type'] ?? 'all';
    
    try {
        $result = $adminManager->clearLogs($logType);
        
        $logger->logAdmin('logs_cleared', [
            'log_type' => $logType,
            'cleared_by' => $_SESSION['username']
        ]);
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Update system settings
 */
function updateSettings($input, $adminManager, $logger) {
    try {
        $result = $adminManager->updateSystemSettings($input);
        
        if ($result['success']) {
            $logger->logAdmin('settings_updated', [
                'settings' => array_keys($input),
                'updated_by' => $_SESSION['username']
            ]);
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Delete file (admin override)
 */
function deleteFile($input, $adminManager, $logger) {
    $fileId = $input['file_id'] ?? '';
    if (empty($fileId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File ID required']);
        return;
    }
    
    try {
        $result = $adminManager->deleteFile($fileId);
        
        if ($result['success']) {
            $logger->logAdmin('file_deleted_by_admin', [
                'file_id' => $fileId,
                'deleted_by' => $_SESSION['username']
            ]);
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
