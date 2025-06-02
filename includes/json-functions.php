<?php
// JSON database functions

function read_json_file($filename) {
    global $config;
    $filepath = $config['data_path'] . $filename;
    
    if (!file_exists($filepath)) {
        return array();
    }
    
    $content = file_get_contents($filepath);
    if ($content === false) {
        return array();
    }
    
    $data = json_decode($content, true);
    if ($data === null) {
        return array();
    }
    
    return $data;
}

function write_json_file($filename, $data) {
    global $config;
    $filepath = $config['data_path'] . $filename;
    
    $json_content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json_content === false) {
        return false;
    }
    
    return file_put_contents($filepath, $json_content) !== false;
}

function backup_json_file($filename) {
    global $config;
    $source = $config['data_path'] . $filename;
    $backup = $config['backup_path'] . str_replace('.json', '-backup.json', $filename);
    
    if (file_exists($source)) {
        return copy($source, $backup);
    }
    
    return false;
}

function restore_json_file($filename) {
    global $config;
    $backup = $config['backup_path'] . str_replace('.json', '-backup.json', $filename);
    $target = $config['data_path'] . $filename;
    
    if (file_exists($backup)) {
        return copy($backup, $target);
    }
    
    return false;
}

function get_next_id($data) {
    if (empty($data)) {
        return 1;
    }
    
    $max_id = 0;
    foreach ($data as $item) {
        if (isset($item['id']) && $item['id'] > $max_id) {
            $max_id = $item['id'];
        }
    }
    
    return $max_id + 1;
}

function find_by_id($data, $id) {
    foreach ($data as $item) {
        if (isset($item['id']) && $item['id'] == $id) {
            return $item;
        }
    }
    return null;
}

function remove_by_id($data, $id) {
    $result = array();
    foreach ($data as $item) {
        if (!isset($item['id']) || $item['id'] != $id) {
            $result[] = $item;
        }
    }
    return $result;
}

function update_by_id($data, $id, $new_data) {
    for ($i = 0; $i < count($data); $i++) {
        if (isset($data[$i]['id']) && $data[$i]['id'] == $id) {
            $data[$i] = array_merge($data[$i], $new_data);
            break;
        }
    }
    return $data;
}
?>
