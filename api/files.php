<?php
/**
 * Files API Endpoint
 * Handles file listing, details, and file operations
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

// Check authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user = get_current_user();

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
        case 'PATCH':
            handle_patch_request();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    log_error('API Files Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handle_get_request() {
    global $user;
    
    if (isset($_GET['id'])) {
        // Get specific file details
        $file_id = sanitize_input($_GET['id']);
        $file = get_file_by_id($file_id);
        
        if (!$file) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
            return;
        }
        
        // Check permission
        if ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        echo json_encode(['file' => $file]);
        
    } elseif (isset($_GET['search'])) {
        // Search files
        $query = sanitize_input($_GET['search']);
        $type = sanitize_input($_GET['type'] ?? '');
        
        $files = search_files($query, $user['id'], $type);
        echo json_encode(['files' => $files]);
        
    } else {
        // List files
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $sort = sanitize_input($_GET['sort'] ?? 'upload_date');
        $order = sanitize_input($_GET['order'] ?? 'desc');
        $filter = sanitize_input($_GET['filter'] ?? '');
        
        $files = get_user_files($user['id'], $page, $limit, $sort, $order, $filter);
        $total = count_user_files($user['id'], $filter);
        
        echo json_encode([
            'files' => $files,
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
        case 'rename':
            $file_id = sanitize_input($_POST['file_id']);
            $new_name = sanitize_input($_POST['new_name']);
            
            $file = get_file_by_id($file_id);
            if (!$file || ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin')) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            if (rename_file($file_id, $new_name)) {
                log_file_operation('rename', $file['filename'] . ' to ' . $new_name, $user['username']);
                echo json_encode(['success' => true, 'message' => 'File renamed successfully']);
            } else {
                echo json_encode(['error' => 'Failed to rename file']);
            }
            break;
            
        case 'move':
            $file_id = sanitize_input($_POST['file_id']);
            $new_path = sanitize_input($_POST['new_path']);
            
            $file = get_file_by_id($file_id);
            if (!$file || ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin')) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            if (move_file($file_id, $new_path)) {
                log_file_operation('move', $file['filename'] . ' to ' . $new_path, $user['username']);
                echo json_encode(['success' => true, 'message' => 'File moved successfully']);
            } else {
                echo json_encode(['error' => 'Failed to move file']);
            }
            break;
            
        case 'copy':
            $file_id = sanitize_input($_POST['file_id']);
            $new_name = sanitize_input($_POST['new_name'] ?? '');
            
            $file = get_file_by_id($file_id);
            if (!$file || ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin')) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                return;
            }
            
            $new_file_id = copy_file($file_id, $new_name);
            if ($new_file_id) {
                log_file_operation('copy', $file['filename'], $user['username']);
                echo json_encode(['success' => true, 'file_id' => $new_file_id, 'message' => 'File copied successfully']);
            } else {
                echo json_encode(['error' => 'Failed to copy file']);
            }
            break;
            
        case 'create_folder':
            $folder_name = sanitize_input($_POST['folder_name']);
            $parent_path = sanitize_input($_POST['parent_path'] ?? '');
            
            if (create_folder($folder_name, $parent_path, $user['id'])) {
                log_file_operation('create_folder', $folder_name, $user['username']);
                echo json_encode(['success' => true, 'message' => 'Folder created successfully']);
            } else {
                echo json_encode(['error' => 'Failed to create folder']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handle_put_request() {
    global $user;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $file_id = $input['file_id'] ?? '';
    $updates = $input['updates'] ?? [];
    
    $file = get_file_by_id($file_id);
    if (!$file || ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    if (update_file_metadata($file_id, $updates)) {
        log_file_operation('update', $file['filename'], $user['username']);
        echo json_encode(['success' => true, 'message' => 'File updated successfully']);
    } else {
        echo json_encode(['error' => 'Failed to update file']);
    }
}

function handle_patch_request() {
    global $user;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $file_ids = $input['file_ids'] ?? [];
    $action = $input['action'] ?? '';
    
    if (empty($file_ids) || !is_array($file_ids)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file IDs']);
        return;
    }
    
    $results = [];
    $success_count = 0;
    
    foreach ($file_ids as $file_id) {
        $file = get_file_by_id($file_id);
        if (!$file || ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin')) {
            $results[] = ['file_id' => $file_id, 'error' => 'Access denied'];
            continue;
        }
        
        switch ($action) {
            case 'bulk_delete':
                if (delete_file($file_id)) {
                    $results[] = ['file_id' => $file_id, 'success' => true];
                    $success_count++;
                    log_file_operation('bulk_delete', $file['filename'], $user['username']);
                } else {
                    $results[] = ['file_id' => $file_id, 'error' => 'Delete failed'];
                }
                break;
                
            case 'bulk_move':
                $destination = $input['destination'] ?? '';
                if (move_file($file_id, $destination)) {
                    $results[] = ['file_id' => $file_id, 'success' => true];
                    $success_count++;
                    log_file_operation('bulk_move', $file['filename'] . ' to ' . $destination, $user['username']);
                } else {
                    $results[] = ['file_id' => $file_id, 'error' => 'Move failed'];
                }
                break;
                
            case 'change_visibility':
                $visibility = $input['visibility'] ?? 'private';
                if (update_file_metadata($file_id, ['visibility' => $visibility])) {
                    $results[] = ['file_id' => $file_id, 'success' => true];
                    $success_count++;
                    log_file_operation('change_visibility', $file['filename'] . ' to ' . $visibility, $user['username']);
                } else {
                    $results[] = ['file_id' => $file_id, 'error' => 'Update failed'];
                }
                break;
                
            default:
                $results[] = ['file_id' => $file_id, 'error' => 'Invalid action'];
        }
    }
    
    echo json_encode([
        'success' => $success_count > 0,
        'processed' => count($file_ids),
        'successful' => $success_count,
        'results' => $results
    ]);
}
