<?php
/**
 * Compress API Endpoint
 * Handles file compression and archive operations
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
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            handle_compression();
            break;
        case 'GET':
            handle_extraction();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    log_error('API Compress Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handle_compression() {
    global $user;
    
    $file_ids = $_POST['file_ids'] ?? [];
    $format = sanitize_input($_POST['format'] ?? 'zip');
    $archive_name = sanitize_input($_POST['archive_name'] ?? '');
    $compression_level = (int)($_POST['compression_level'] ?? 6);
    
    if (empty($file_ids) || !is_array($file_ids)) {
        echo json_encode(['error' => 'No files selected for compression']);
        return;
    }
    
    if (!in_array($format, ['zip', 'tar', 'tar.gz'])) {
        echo json_encode(['error' => 'Invalid compression format']);
        return;
    }
    
    // Validate files and permissions
    $files_to_compress = [];
    $total_size = 0;
    
    foreach ($file_ids as $file_id) {
        $file = get_file_by_id($file_id);
        if (!$file) {
            echo json_encode(['error' => "File not found: $file_id"]);
            return;
        }
        
        if ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
            echo json_encode(['error' => 'Access denied to one or more files']);
            return;
        }
        
        if (!file_exists($file['file_path'])) {
            echo json_encode(['error' => "File not found on disk: {$file['filename']}"]);
            return;
        }
        
        $files_to_compress[] = $file;
        $total_size += $file['file_size'];
    }
    
    // Check if user has enough storage space for the archive
    $estimated_archive_size = $total_size * 0.8; // Rough estimate
    if (get_user_storage_usage($user['id']) + $estimated_archive_size > $user['storage_limit']) {
        echo json_encode(['error' => 'Insufficient storage space for archive']);
        return;
    }
    
    // Generate archive name if not provided
    if (empty($archive_name)) {
        $archive_name = 'archive_' . date('Y-m-d_H-i-s');
    }
    
    // Create the archive
    $archive_result = create_archive($files_to_compress, $format, $archive_name, $compression_level, $user);
    
    if ($archive_result['success']) {
        log_file_operation('compress', "Created {$format} archive: {$archive_name}", $user['username']);
        echo json_encode([
            'success' => true,
            'archive_id' => $archive_result['file_id'],
            'archive_name' => $archive_result['filename'],
            'archive_size' => $archive_result['size'],
            'compressed_files' => count($files_to_compress),
            'compression_ratio' => round((1 - $archive_result['size'] / $total_size) * 100, 2)
        ]);
    } else {
        echo json_encode(['error' => $archive_result['error']]);
    }
}

function handle_extraction() {
    global $user;
    
    $file_id = $_GET['file_id'] ?? '';
    $extract_path = sanitize_input($_GET['extract_path'] ?? '');
    $action = $_GET['action'] ?? 'extract';
    
    if (empty($file_id)) {
        echo json_encode(['error' => 'File ID required']);
        return;
    }
    
    $file = get_file_by_id($file_id);
    if (!$file) {
        echo json_encode(['error' => 'File not found']);
        return;
    }
    
    if ($file['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    if (!file_exists($file['file_path'])) {
        echo json_encode(['error' => 'File not found on disk']);
        return;
    }
    
    // Check if file is an archive
    $file_ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, ['zip', 'tar', 'gz'])) {
        echo json_encode(['error' => 'File is not a supported archive format']);
        return;
    }
    
    switch ($action) {
        case 'list':
            $contents = list_archive_contents($file['file_path'], $file_ext);
            if ($contents !== false) {
                echo json_encode(['contents' => $contents]);
            } else {
                echo json_encode(['error' => 'Failed to read archive contents']);
            }
            break;
            
        case 'extract':
            $extract_result = extract_archive($file, $extract_path, $user);
            if ($extract_result['success']) {
                log_file_operation('extract', "Extracted archive: {$file['filename']}", $user['username']);
                echo json_encode([
                    'success' => true,
                    'extracted_files' => $extract_result['extracted_files'],
                    'extract_path' => $extract_result['extract_path']
                ]);
            } else {
                echo json_encode(['error' => $extract_result['error']]);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
}

function create_archive($files, $format, $archive_name, $compression_level, $user) {
    $user_dir = UPLOAD_PATH . '/' . $user['id'];
    $archive_filename = $archive_name . '.' . $format;
    $archive_path = $user_dir . '/' . $archive_filename;
    
    // Ensure unique filename
    $counter = 1;
    while (file_exists($archive_path)) {
        $archive_filename = $archive_name . '_' . $counter . '.' . $format;
        $archive_path = $user_dir . '/' . $archive_filename;
        $counter++;
    }
    
    try {
        switch ($format) {
            case 'zip':
                $result = create_zip_archive($files, $archive_path, $compression_level);
                break;
            case 'tar':
                $result = create_tar_archive($files, $archive_path, false);
                break;
            case 'tar.gz':
                $result = create_tar_archive($files, $archive_path, true);
                break;
            default:
                return ['success' => false, 'error' => 'Unsupported format'];
        }
        
        if (!$result) {
            return ['success' => false, 'error' => 'Failed to create archive'];
        }
        
        // Create file record
        $file_id = create_file_record($archive_path, $archive_filename, $user['id'], '', 'Compressed archive', 'private');
        
        if (!$file_id) {
            unlink($archive_path);
            return ['success' => false, 'error' => 'Failed to create file record'];
        }
        
        return [
            'success' => true,
            'file_id' => $file_id,
            'filename' => $archive_filename,
            'size' => filesize($archive_path)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Compression failed: ' . $e->getMessage()];
    }
}

function create_zip_archive($files, $archive_path, $compression_level) {
    if (!class_exists('ZipArchive')) {
        throw new Exception('ZIP extension not available');
    }
    
    $zip = new ZipArchive();
    $result = $zip->open($archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($result !== TRUE) {
        throw new Exception('Cannot create ZIP archive');
    }
    
    foreach ($files as $file) {
        $zip->addFile($file['file_path'], $file['filename']);
    }
    
    $zip->close();
    return true;
}

function create_tar_archive($files, $archive_path, $compress) {
    $tar_path = $compress ? str_replace('.tar.gz', '.tar', $archive_path) : $archive_path;
    
    // Create tar archive
    $tar = new PharData($tar_path);
    
    foreach ($files as $file) {
        $tar->addFile($file['file_path'], $file['filename']);
    }
    
    // Compress if needed
    if ($compress) {
        $tar->compress(Phar::GZ);
        unlink($tar_path); // Remove uncompressed tar
    }
    
    return true;
}

function list_archive_contents($archive_path, $format) {
    try {
        switch ($format) {
            case 'zip':
                return list_zip_contents($archive_path);
            case 'tar':
            case 'gz':
                return list_tar_contents($archive_path);
            default:
                return false;
        }
    } catch (Exception $e) {
        return false;
    }
}

function list_zip_contents($archive_path) {
    if (!class_exists('ZipArchive')) {
        return false;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($archive_path) !== TRUE) {
        return false;
    }
    
    $contents = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $contents[] = [
            'name' => $stat['name'],
            'size' => $stat['size'],
            'compressed_size' => $stat['comp_size'],
            'modified' => date('Y-m-d H:i:s', $stat['mtime'])
        ];
    }
    
    $zip->close();
    return $contents;
}

function list_tar_contents($archive_path) {
    try {
        $phar = new PharData($archive_path);
        $contents = [];
        
        foreach ($phar as $file) {
            $contents[] = [
                'name' => $file->getFileName(),
                'size' => $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime())
            ];
        }
        
        return $contents;
    } catch (Exception $e) {
        return false;
    }
}

function extract_archive($file, $extract_path, $user) {
    $user_dir = UPLOAD_PATH . '/' . $user['id'];
    $destination = $user_dir;
    
    if (!empty($extract_path)) {
        $extract_path = trim($extract_path, '/');
        $destination = $user_dir . '/' . $extract_path;
        if (!file_exists($destination)) {
            mkdir($destination, 0755, true);
        }
    }
    
    $file_ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
    
    try {
        switch ($file_ext) {
            case 'zip':
                $extracted_files = extract_zip($file['file_path'], $destination);
                break;
            case 'tar':
            case 'gz':
                $extracted_files = extract_tar($file['file_path'], $destination);
                break;
            default:
                return ['success' => false, 'error' => 'Unsupported archive format'];
        }
        
        // Create file records for extracted files
        foreach ($extracted_files as $extracted_file) {
            $relative_path = str_replace($user_dir . '/', '', dirname($extracted_file));
            $filename = basename($extracted_file);
            create_file_record($extracted_file, $filename, $user['id'], $relative_path, 'Extracted from archive', 'private');
        }
        
        return [
            'success' => true,
            'extracted_files' => count($extracted_files),
            'extract_path' => $extract_path ?: '/'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Extraction failed: ' . $e->getMessage()];
    }
}

function extract_zip($archive_path, $destination) {
    if (!class_exists('ZipArchive')) {
        throw new Exception('ZIP extension not available');
    }
    
    $zip = new ZipArchive();
    if ($zip->open($archive_path) !== TRUE) {
        throw new Exception('Cannot open ZIP archive');
    }
    
    $zip->extractTo($destination);
    $extracted_files = [];
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $extracted_files[] = $destination . '/' . $stat['name'];
    }
    
    $zip->close();
    return $extracted_files;
}

function extract_tar($archive_path, $destination) {
    try {
        $phar = new PharData($archive_path);
        $phar->extractTo($destination);
        
        $extracted_files = [];
        foreach ($phar as $file) {
            $extracted_files[] = $destination . '/' . $file->getFileName();
        }
        
        return $extracted_files;
    } catch (Exception $e) {
        throw new Exception('Cannot extract TAR archive: ' . $e->getMessage());
    }
}
