<?php
require_once '../includes/config.php';
require_once '../includes/auth-functions.php';
require_once '../includes/file-functions.php';
require_once '../includes/log-functions.php';
require_once '../includes/validation-functions.php';

// Set JSON response headers
header('Content-Type: application/json');

// Start session and check authentication
session_start();
require_authentication();

$current_user = get_current_user();
$response = ['success' => false, 'message' => 'Invalid request'];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        // Validate CSRF token
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            $response = ['success' => false, 'message' => 'Invalid security token'];
        } else {
            $file_id = $_POST['file_id'] ?? '';
            
            if (empty($file_id)) {
                $response = ['success' => false, 'message' => 'File ID is required'];
            } else {
                // Get file information
                $file_info = get_file_by_id($file_id);
                if (!$file_info) {
                    $response = ['success' => false, 'message' => 'File not found'];
                } else {
                    // Check permissions
                    if (!can_user_modify_file($current_user['id'], $file_info) && $current_user['role'] !== 'admin') {
                        $response = ['success' => false, 'message' => 'Access denied'];
                        log_security("Unauthorized delete attempt by user: " . $current_user['username'] . " for file: " . $file_info['name']);
                    } else {
                        // Delete file
                        $result = delete_file($file_id);
                        if ($result['success']) {
                            $response = [
                                'success' => true,
                                'message' => 'File deleted successfully'
                            ];
                            log_file_operation("File deleted: " . $file_info['name'] . " by user: " . $current_user['username']);
                        } else {
                            $response = ['success' => false, 'message' => $result['message']];
                            log_error("File deletion failed: " . $result['message']);
                        }
                    }
                }
            }
        }
    } elseif ($method === 'GET') {
        // Get file information for deletion confirmation
        $file_id = $_GET['id'] ?? '';
        
        if (empty($file_id)) {
            $response = ['success' => false, 'message' => 'File ID is required'];
        } else {
            $file_info = get_file_by_id($file_id);
            if (!$file_info) {
                $response = ['success' => false, 'message' => 'File not found'];
            } else {
                // Check permissions
                if (!can_user_modify_file($current_user['id'], $file_info) && $current_user['role'] !== 'admin') {
                    $response = ['success' => false, 'message' => 'Access denied'];
                } else {
                    $response = [
                        'success' => true,
                        'file' => [
                            'id' => $file_info['id'],
                            'name' => $file_info['name'],
                            'size' => $file_info['size'],
                            'type' => $file_info['type'],
                            'uploaded_at' => $file_info['uploaded_at']
                        ]
                    ];
                }
            }
        }
    }
    
} catch (Exception $e) {
    log_error("Delete API error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Server error occurred'];
}

echo json_encode($response);
?>
