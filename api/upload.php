<?php
/**
 * Upload API Endpoint
 * Handles file uploads and chunked uploads
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

$user = get_current_user();

// Check if uploads are enabled
$config = get_config();
if (!$config['allow_uploads']) {
    http_response_code(403);
    echo json_encode(['error' => 'Uploads are currently disabled']);
    exit;
}

// Check user storage limit
if (get_user_storage_usage($user['id']) >= $user['storage_limit']) {
    http_response_code(413);
    echo json_encode(['error' => 'Storage limit exceeded']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            handle_upload();
            break;
        case 'PUT':
            handle_chunked_upload();
            break;
        case 'GET':
            handle_upload_status();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    log_error('API Upload Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handle_upload() {
    global $user, $config;
    
    // Check if files were uploaded
    if (empty($_FILES['files']['name'][0])) {
        echo json_encode(['error' => 'No files selected']);
        return;
    }
    
    $upload_path = sanitize_input($_POST['upload_path'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $visibility = sanitize_input($_POST['visibility'] ?? 'private');
    $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === '1';
    
    $results = [];
    $success_count = 0;
    $total_size = 0;
    
    // Process each uploaded file
    for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
        $file_data = [
            'name' => $_FILES['files']['name'][$i],
            'type' => $_FILES['files']['type'][$i],
            'tmp_name' => $_FILES['files']['tmp_name'][$i],
            'error' => $_FILES['files']['error'][$i],
            'size' => $_FILES['files']['size'][$i]
        ];
        
        // Skip empty files
        if (empty($file_data['name']) || $file_data['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        
        // Check for upload errors
        if ($file_data['error'] !== UPLOAD_ERR_OK) {
            $results[] = [
                'filename' => $file_data['name'],
                'error' => get_upload_error_message($file_data['error'])
            ];
            continue;
        }
        
        // Validate file
        $validation_result = validate_uploaded_file($file_data, $user);
        if ($validation_result !== true) {
            $results[] = [
                'filename' => $file_data['name'],
                'error' => $validation_result
            ];
            continue;
        }
        
        // Check storage limits
        $total_size += $file_data['size'];
        if (get_user_storage_usage($user['id']) + $total_size > $user['storage_limit']) {
            $results[] = [
                'filename' => $file_data['name'],
                'error' => 'Would exceed storage limit'
            ];
            continue;
        }
        
        // Process the upload
        $upload_result = process_file_upload($file_data, $user, $upload_path, $description, $visibility, $overwrite);
        
        if ($upload_result['success']) {
            $results[] = [
                'filename' => $file_data['name'],
                'file_id' => $upload_result['file_id'],
                'size' => $file_data['size'],
                'success' => true
            ];
            $success_count++;
            
            log_file_operation('upload', $file_data['name'], $user['username']);
        } else {
            $results[] = [
                'filename' => $file_data['name'],
                'error' => $upload_result['error']
            ];
        }
    }
    
    echo json_encode([
        'success' => $success_count > 0,
        'uploaded' => $success_count,
        'total' => count($results),
        'total_size' => $total_size,
        'results' => $results
    ]);
}

function handle_chunked_upload() {
    global $user;
    
    $chunk_id = $_GET['chunk_id'] ?? '';
    $chunk_index = (int)($_GET['chunk_index'] ?? 0);
    $total_chunks = (int)($_GET['total_chunks'] ?? 1);
    $filename = sanitize_input($_GET['filename'] ?? '');
    
    if (empty($chunk_id) || empty($filename)) {
        echo json_encode(['error' => 'Invalid chunk upload parameters']);
        return;
    }
    
    // Read chunk data
    $chunk_data = file_get_contents('php://input');
    if ($chunk_data === false) {
        echo json_encode(['error' => 'Failed to read chunk data']);
        return;
    }
    
    // Store chunk
    $chunk_dir = UPLOAD_PATH . '/chunks/' . $user['id'];
    if (!file_exists($chunk_dir)) {
        mkdir($chunk_dir, 0755, true);
    }
    
    $chunk_file = $chunk_dir . '/' . $chunk_id . '_' . $chunk_index;
    if (file_put_contents($chunk_file, $chunk_data) === false) {
        echo json_encode(['error' => 'Failed to store chunk']);
        return;
    }
    
    // Check if all chunks are received
    $received_chunks = 0;
    for ($i = 0; $i < $total_chunks; $i++) {
        if (file_exists($chunk_dir . '/' . $chunk_id . '_' . $i)) {
            $received_chunks++;
        }
    }
    
    if ($received_chunks === $total_chunks) {
        // Combine chunks
        $combined_file = combine_chunks($chunk_id, $total_chunks, $user['id']);
        if ($combined_file) {
            // Create file record
            $upload_path = sanitize_input($_GET['upload_path'] ?? '');
            $description = sanitize_input($_GET['description'] ?? '');
            $visibility = sanitize_input($_GET['visibility'] ?? 'private');
            
            $file_id = create_file_record($combined_file, $filename, $user['id'], $upload_path, $description, $visibility);
            
            // Clean up chunks
            cleanup_chunks($chunk_id, $total_chunks, $user['id']);
            
            log_file_operation('chunked_upload', $filename, $user['username']);
            
            echo json_encode([
                'success' => true,
                'file_id' => $file_id,
                'message' => 'File uploaded successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to combine chunks']);
        }
    } else {
        echo json_encode([
            'success' => true,
            'chunk_received' => $chunk_index,
            'total_received' => $received_chunks,
            'total_chunks' => $total_chunks
        ]);
    }
}

function handle_upload_status() {
    global $user;
    
    $file_id = $_GET['file_id'] ?? '';
    
    if (empty($file_id)) {
        // Return upload statistics
        $stats = [
            'storage_used' => get_user_storage_usage($user['id']),
            'storage_limit' => $user['storage_limit'],
            'file_count' => count_user_files($user['id']),
            'recent_uploads' => get_recent_uploads($user['id'], 10)
        ];
        
        echo json_encode(['stats' => $stats]);
    } else {
        // Return specific file status
        $file = get_file_by_id($file_id);
        if (!$file || $file['user_id'] !== $user['id']) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
            return;
        }
        
        echo json_encode(['file' => $file]);
    }
}

function validate_uploaded_file($file_data, $user) {
    global $config;
    
    // Check file size
    if ($file_data['size'] > $config['max_file_size']) {
        return 'File too large (max: ' . format_bytes($config['max_file_size']) . ')';
    }
    
    // Check file type
    $allowed_types = $config['allowed_file_types'];
    if (!empty($allowed_types)) {
        $file_ext = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_types)) {
            return 'File type not allowed';
        }
    }
    
    // Check for dangerous files
    if (is_dangerous_file($file_data['name'])) {
        return 'File type is restricted for security reasons';
    }
    
    // Scan for malware if enabled
    if ($config['enable_virus_scan'] && scan_file_for_malware($file_data['tmp_name'])) {
        return 'File failed security scan';
    }
    
    return true;
}

function process_file_upload($file_data, $user, $upload_path, $description, $visibility, $overwrite) {
    // Generate unique filename
    $original_name = $file_data['name'];
    $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
    $base_name = pathinfo($original_name, PATHINFO_FILENAME);
    
    // Sanitize filename
    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $base_name);
    $filename = $safe_name . '.' . $file_ext;
    
    // Create user directory if it doesn't exist
    $user_dir = UPLOAD_PATH . '/' . $user['id'];
    if (!file_exists($user_dir)) {
        mkdir($user_dir, 0755, true);
    }
    
    // Handle upload path
    if (!empty($upload_path)) {
        $upload_path = trim($upload_path, '/');
        $full_path = $user_dir . '/' . $upload_path;
        if (!file_exists($full_path)) {
            mkdir($full_path, 0755, true);
        }
        $destination = $full_path . '/' . $filename;
    } else {
        $destination = $user_dir . '/' . $filename;
    }
    
    // Handle file conflicts
    if (file_exists($destination) && !$overwrite) {
        $counter = 1;
        do {
            $new_filename = $safe_name . '_' . $counter . '.' . $file_ext;
            $destination = dirname($destination) . '/' . $new_filename;
            $filename = $new_filename;
            $counter++;
        } while (file_exists($destination));
    }
    
    // Move uploaded file
    if (!move_uploaded_file($file_data['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }
    
    // Create file record
    $file_id = create_file_record($destination, $filename, $user['id'], $upload_path, $description, $visibility);
    
    if (!$file_id) {
        unlink($destination); // Clean up file if database insert failed
        return ['success' => false, 'error' => 'Failed to create file record'];
    }
    
    return ['success' => true, 'file_id' => $file_id];
}

function combine_chunks($chunk_id, $total_chunks, $user_id) {
    $chunk_dir = UPLOAD_PATH . '/chunks/' . $user_id;
    $combined_file = $chunk_dir . '/' . $chunk_id . '_combined';
    
    $output = fopen($combined_file, 'wb');
    if (!$output) {
        return false;
    }
    
    for ($i = 0; $i < $total_chunks; $i++) {
        $chunk_file = $chunk_dir . '/' . $chunk_id . '_' . $i;
        if (!file_exists($chunk_file)) {
            fclose($output);
            unlink($combined_file);
            return false;
        }
        
        $chunk_data = file_get_contents($chunk_file);
        fwrite($output, $chunk_data);
    }
    
    fclose($output);
    return $combined_file;
}

function cleanup_chunks($chunk_id, $total_chunks, $user_id) {
    $chunk_dir = UPLOAD_PATH . '/chunks/' . $user_id;
    
    for ($i = 0; $i < $total_chunks; $i++) {
        $chunk_file = $chunk_dir . '/' . $chunk_id . '_' . $i;
        if (file_exists($chunk_file)) {
            unlink($chunk_file);
        }
    }
}

function get_upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File too large';
        case UPLOAD_ERR_PARTIAL:
            return 'File upload incomplete';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Temporary directory missing';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload blocked by extension';
        default:
            return 'Unknown upload error';
    }
}
