<?php
/**
 * Share API Endpoint
 * Handles file sharing operations
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth-functions.php';
require_once '../includes/file-functions.php';
require_once '../includes/json-functions.php';
require_once '../includes/log-functions.php';
require_once '../includes/security-functions.php';
require_once '../includes/validation-functions.php';

header('Content-Type: application/json');

// Check authentication for most operations
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Public access allowed for share access
if ($action !== 'access' && !is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$user = is_logged_in() ? get_current_user() : null;

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
    log_error('API Share Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handle_get_request() {
    global $user;
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'access':
            handle_share_access();
            break;
            
        case 'list':
            handle_list_shares();
            break;
            
        case 'details':
            handle_share_details();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handle_post_request() {
    global $user;
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            handle_create_share();
            break;
            
        case 'update_access':
            handle_update_access();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handle_put_request() {
    global $user;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $share_id = $input['share_id'] ?? '';
    $updates = $input['updates'] ?? [];
    
    if (empty($share_id)) {
        echo json_encode(['error' => 'Share ID required']);
        return;
    }
    
    $share = get_share_by_id($share_id);
    if (!$share) {
        http_response_code(404);
        echo json_encode(['error' => 'Share not found']);
        return;
    }
    
    // Check permission
    $file = get_file_by_id($share['file_id']);
    if (!$file || ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    if (update_share($share_id, $updates)) {
        log_file_operation('update_share', "Updated share for file: {$file['filename']}", $user['username']);
        echo json_encode(['success' => true, 'message' => 'Share updated successfully']);
    } else {
        echo json_encode(['error' => 'Failed to update share']);
    }
}

function handle_delete_request() {
    global $user;
    
    $share_id = $_GET['share_id'] ?? '';
    
    if (empty($share_id)) {
        echo json_encode(['error' => 'Share ID required']);
        return;
    }
    
    $share = get_share_by_id($share_id);
    if (!$share) {
        http_response_code(404);
        echo json_encode(['error' => 'Share not found']);
        return;
    }
    
    // Check permission
    $file = get_file_by_id($share['file_id']);
    if (!$file || ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    if (delete_share($share_id)) {
        log_file_operation('delete_share', "Deleted share for file: {$file['filename']}", $user['username']);
        echo json_encode(['success' => true, 'message' => 'Share deleted successfully']);
    } else {
        echo json_encode(['error' => 'Failed to delete share']);
    }
}

function handle_share_access() {
    $share_token = $_GET['token'] ?? '';
    $password = $_GET['password'] ?? '';
    
    if (empty($share_token)) {
        echo json_encode(['error' => 'Share token required']);
        return;
    }
    
    $share = get_share_by_token($share_token);
    if (!$share) {
        http_response_code(404);
        echo json_encode(['error' => 'Share not found']);
        return;
    }
    
    // Check if share is active
    if ($share['status'] !== 'active') {
        echo json_encode(['error' => 'Share is not active']);
        return;
    }
    
    // Check expiration
    if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
        echo json_encode(['error' => 'Share has expired']);
        return;
    }
    
    // Check download limit
    if ($share['download_limit'] && $share['download_count'] >= $share['download_limit']) {
        echo json_encode(['error' => 'Download limit exceeded']);
        return;
    }
    
    // Check password
    if ($share['password'] && $share['password'] !== $password) {
        echo json_encode(['error' => 'Invalid password', 'requires_password' => true]);
        return;
    }
    
    // Get file information
    $file = get_file_by_id($share['file_id']);
    if (!$file || !file_exists($file['file_path'])) {
        echo json_encode(['error' => 'File not found']);
        return;
    }
    
    // Update access count
    increment_share_access($share['id']);
    
    // Log access
    $ip = get_client_ip();
    log_file_operation('share_access', "Accessed shared file: {$file['filename']} (Token: {$share_token})", "Anonymous ($ip)");
    
    echo json_encode([
        'success' => true,
        'file' => [
            'id' => $file['id'],
            'filename' => $file['filename'],
            'file_size' => $file['file_size'],
            'mime_type' => $file['mime_type'],
            'upload_date' => $file['upload_date']
        ],
        'share' => [
            'id' => $share['id'],
            'download_count' => $share['download_count'] + 1,
            'download_limit' => $share['download_limit'],
            'expires_at' => $share['expires_at']
        ],
        'download_url' => generate_download_url($share_token)
    ]);
}

function handle_list_shares() {
    global $user;
    
    $file_id = $_GET['file_id'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    
    if (!empty($file_id)) {
        // List shares for specific file
        $file = get_file_by_id($file_id);
        if (!$file || ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin')) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        $shares = get_file_shares($file_id);
        echo json_encode(['shares' => $shares]);
    } else {
        // List all user shares
        $shares = get_user_shares($user['id'], $page, $limit);
        $total = count_user_shares($user['id']);
        
        echo json_encode([
            'shares' => $shares,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
}

function handle_share_details() {
    global $user;
    
    $share_id = $_GET['share_id'] ?? '';
    
    if (empty($share_id)) {
        echo json_encode(['error' => 'Share ID required']);
        return;
    }
    
    $share = get_share_by_id($share_id);
    if (!$share) {
        http_response_code(404);
        echo json_encode(['error' => 'Share not found']);
        return;
    }
    
    // Check permission
    $file = get_file_by_id($share['file_id']);
    if (!$file || ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    // Get share statistics
    $stats = get_share_statistics($share_id);
    
    echo json_encode([
        'share' => $share,
        'file' => $file,
        'statistics' => $stats
    ]);
}

function handle_create_share() {
    global $user;
    
    $file_id = sanitize_input($_POST['file_id']);
    $share_type = sanitize_input($_POST['share_type'] ?? 'public');
    $password = $_POST['password'] ?? '';
    $expires_at = $_POST['expires_at'] ?? '';
    $download_limit = (int)($_POST['download_limit'] ?? 0);
    $description = sanitize_input($_POST['description'] ?? '');
    
    if (empty($file_id)) {
        echo json_encode(['error' => 'File ID required']);
        return;
    }
    
    $file = get_file_by_id($file_id);
    if (!$file) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        return;
    }
    
    if ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    // Validate expiration date
    if (!empty($expires_at) && strtotime($expires_at) <= time()) {
        echo json_encode(['error' => 'Expiration date must be in the future']);
        return;
    }
    
    // Create share
    $share_data = [
        'file_id' => $file_id,
        'user_id' => $user['id'],
        'share_token' => generate_share_token(),
        'share_type' => $share_type,
        'password' => $password,
        'expires_at' => $expires_at ?: null,
        'download_limit' => $download_limit > 0 ? $download_limit : null,
        'description' => $description,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'download_count' => 0
    ];
    
    $share_id = create_share($share_data);
    
    if ($share_id) {
        log_file_operation('create_share', "Created share for file: {$file['filename']}", $user['username']);
        
        $share_url = generate_share_url($share_data['share_token']);
        
        echo json_encode([
            'success' => true,
            'share_id' => $share_id,
            'share_token' => $share_data['share_token'],
            'share_url' => $share_url,
            'message' => 'Share created successfully'
        ]);
    } else {
        echo json_encode(['error' => 'Failed to create share']);
    }
}

function handle_update_access() {
    global $user;
    
    $share_id = sanitize_input($_POST['share_id']);
    $action = sanitize_input($_POST['access_action']);
    
    if (empty($share_id)) {
        echo json_encode(['error' => 'Share ID required']);
        return;
    }
    
    $share = get_share_by_id($share_id);
    if (!$share) {
        http_response_code(404);
        echo json_encode(['error' => 'Share not found']);
        return;
    }
    
    // Check permission
    $file = get_file_by_id($share['file_id']);
    if (!$file || ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    switch ($action) {
        case 'activate':
            $success = update_share_status($share_id, 'active');
            $message = 'Share activated successfully';
            break;
            
        case 'deactivate':
            $success = update_share_status($share_id, 'inactive');
            $message = 'Share deactivated successfully';
            break;
            
        case 'reset_count':
            $success = reset_share_download_count($share_id);
            $message = 'Download count reset successfully';
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
            return;
    }
    
    if ($success) {
        log_file_operation('update_share_access', "Updated share access for file: {$file['filename']} (Action: $action)", $user['username']);
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['error' => 'Failed to update share access']);
    }
}

function generate_share_token() {
    return bin2hex(random_bytes(16));
}

function generate_share_url($token) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return "{$protocol}://{$host}/share.php?token={$token}";
}

function generate_download_url($token) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return "{$protocol}://{$host}/api/download.php?token={$token}";
}
