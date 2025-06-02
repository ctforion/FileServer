<?php
// File operation functions

function get_files_list($directory = '') {
    global $config;
    $files = read_json_file('files.json');
    
    if ($directory) {
        $filtered_files = array();
        foreach ($files as $file) {
            if (strpos($file['path'], $directory) === 0) {
                $filtered_files[] = $file;
            }
        }
        return $filtered_files;
    }
    
    return $files;
}

function add_file_record($filename, $original_name, $path, $size, $type = '') {
    $files = read_json_file('files.json');
    
    $file_record = array(
        'id' => get_next_id($files),
        'filename' => $filename,
        'original_name' => $original_name,
        'path' => $path,
        'size' => $size,
        'type' => $type,
        'extension' => get_file_extension($filename),
        'uploaded_by' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
        'uploaded_at' => get_current_timestamp(),
        'downloads' => 0,
        'last_accessed' => null,
        'description' => '',
        'tags' => array(),
        'is_public' => false,
        'is_compressed' => false,
        'has_thumbnail' => false,
        'version' => 1,
        'parent_id' => null,
        'status' => 'active'
    );
    
    $files[] = $file_record;
    
    if (write_json_file('files.json', $files)) {
        log_file_operation('file_added', $filename, "Size: {$size} bytes");
        return $file_record['id'];
    }
    
    return false;
}

function remove_file_record($file_id) {
    $files = read_json_file('files.json');
    $file = find_by_id($files, $file_id);
    
    if ($file) {
        $files = remove_by_id($files, $file_id);
        
        if (write_json_file('files.json', $files)) {
            log_file_operation('file_removed', $file['filename']);
            return true;
        }
    }
    
    return false;
}

function update_file_record($file_id, $updates) {
    $files = read_json_file('files.json');
    $files = update_by_id($files, $file_id, $updates);
    
    if (write_json_file('files.json', $files)) {
        $file = find_by_id($files, $file_id);
        log_file_operation('file_updated', $file['filename']);
        return true;
    }
    
    return false;
}

function increment_download_count($file_id) {
    $files = read_json_file('files.json');
    $file = find_by_id($files, $file_id);
    
    if ($file) {
        $updates = array(
            'downloads' => $file['downloads'] + 1,
            'last_accessed' => get_current_timestamp()
        );
        
        return update_file_record($file_id, $updates);
    }
    
    return false;
}

function search_files($query, $filters = array()) {
    $files = read_json_file('files.json');
    $results = array();
    
    foreach ($files as $file) {
        $match = false;
        
        // Search in filename and original name
        if (stripos($file['filename'], $query) !== false || 
            stripos($file['original_name'], $query) !== false) {
            $match = true;
        }
        
        // Search in description
        if (isset($file['description']) && stripos($file['description'], $query) !== false) {
            $match = true;
        }
        
        // Search in tags
        if (isset($file['tags']) && is_array($file['tags'])) {
            foreach ($file['tags'] as $tag) {
                if (stripos($tag, $query) !== false) {
                    $match = true;
                    break;
                }
            }
        }
        
        if ($match) {
            // Apply filters
            $include = true;
            
            if (isset($filters['extension']) && $filters['extension']) {
                if ($file['extension'] !== $filters['extension']) {
                    $include = false;
                }
            }
            
            if (isset($filters['uploaded_by']) && $filters['uploaded_by']) {
                if ($file['uploaded_by'] != $filters['uploaded_by']) {
                    $include = false;
                }
            }
            
            if (isset($filters['date_from']) && $filters['date_from']) {
                if ($file['uploaded_at'] < $filters['date_from']) {
                    $include = false;
                }
            }
            
            if (isset($filters['date_to']) && $filters['date_to']) {
                if ($file['uploaded_at'] > $filters['date_to']) {
                    $include = false;
                }
            }
            
            if ($include) {
                $results[] = $file;
            }
        }
    }
    
    return $results;
}

function get_file_stats() {
    global $config;
    $files = read_json_file('files.json');
    
    $stats = array(
        'total_files' => count($files),
        'total_size' => 0,
        'file_types' => array(),
        'uploads_today' => 0,
        'downloads_total' => 0
    );
    
    $today = date('Y-m-d');
    
    foreach ($files as $file) {
        $stats['total_size'] += $file['size'];
        $stats['downloads_total'] += $file['downloads'];
        
        if (isset($file['extension'])) {
            if (!isset($stats['file_types'][$file['extension']])) {
                $stats['file_types'][$file['extension']] = 0;
            }
            $stats['file_types'][$file['extension']]++;
        }
        
        if (strpos($file['uploaded_at'], $today) === 0) {
            $stats['uploads_today']++;
        }
    }
    
    $stats['disk_free'] = disk_free_space('.');
    $stats['disk_total'] = disk_total_space('.');
    $stats['disk_used'] = $stats['disk_total'] - $stats['disk_free'];
    
    return $stats;
}

function create_file_thumbnail($filepath, $filename) {
    global $config;
    
    $extension = get_file_extension($filename);
    $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'bmp');
    
    if (!in_array($extension, $image_extensions)) {
        return false;
    }
    
    $thumbnail_path = $config['thumbnails_path'] . $filename;
    
    // Simple thumbnail creation using GD
    $image = null;
    
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($filepath);
            break;
        case 'png':
            $image = imagecreatefrompng($filepath);
            break;
        case 'gif':
            $image = imagecreatefromgif($filepath);
            break;
    }
    
    if ($image) {
        $original_width = imagesx($image);
        $original_height = imagesy($image);
        
        $thumbnail_width = 150;
        $thumbnail_height = 150;
        
        $ratio = min($thumbnail_width / $original_width, $thumbnail_height / $original_height);
        $new_width = $original_width * $ratio;
        $new_height = $original_height * $ratio;
        
        $thumbnail = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
        
        $success = imagejpeg($thumbnail, $thumbnail_path, 80);
        
        imagedestroy($image);
        imagedestroy($thumbnail);
        
        return $success;
    }
    
    return false;
}

function get_directory_tree($base_path = '') {
    global $config;
    $files = read_json_file('files.json');
    $tree = array();
    
    foreach ($files as $file) {
        $path_parts = explode('/', $file['path']);
        $current = &$tree;
        
        foreach ($path_parts as $part) {
            if (!isset($current[$part])) {
                $current[$part] = array();
            }
            $current = &$current[$part];
        }    }
    
    return $tree;
}

function upload_file($file, $target_directory = '') {
    global $config;
    
    // Validate the file upload
    $validation = validate_file_upload($file);
    if (!$validation['valid']) {
        if (isset($validation['quarantine']) && $validation['quarantine']) {
            // Move to quarantine
            $quarantine_path = STORAGE_DIR . '/' . $config['quarantine_path'];
            $quarantine_filename = sanitize_filename($file['name']) . '_' . time();
            
            if (move_uploaded_file($file['tmp_name'], $quarantine_path . $quarantine_filename)) {
                log_security_event('file_quarantined', "File quarantined: {$file['name']}");
            }
        }
        return array('success' => false, 'message' => $validation['error']);
    }
    
    // Generate unique filename
    $extension = get_file_extension($file['name']);
    $filename = sanitize_filename(pathinfo($file['name'], PATHINFO_FILENAME)) . '_' . time() . '.' . $extension;
    
    // Set target path
    $upload_path = STORAGE_DIR . '/' . $config['uploads_path'];
    if ($target_directory) {
        $upload_path .= $target_directory . '/';
        create_directory_if_not_exists($upload_path);
    }
    
    $target_file = $upload_path . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Add file record to database
        $file_id = add_file_record(
            $filename,
            $file['name'],
            $target_directory . '/' . $filename,
            $file['size'],
            $file['type']
        );
        
        if ($file_id) {
            return array(
                'success' => true,
                'message' => 'File uploaded successfully',
                'file_id' => $file_id,
                'filename' => $filename
            );
        }
    }
    
    return array('success' => false, 'message' => 'Failed to upload file');
}

function get_user_directories($user_id) {
    $files = read_json_file('files.json');
    $directories = array();
    
    foreach ($files as $file) {
        if ($file['uploaded_by'] == $user_id && !empty($file['path'])) {
            $path_parts = explode('/', dirname($file['path']));
            foreach ($path_parts as $part) {
                if (!empty($part) && !in_array($part, $directories)) {
                    $directories[] = $part;
                }
            }
        }
    }
    
    return array_unique($directories);
}
?>
