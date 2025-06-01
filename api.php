<?php
require_once 'config.php';

// Set JSON response header
header('Content-Type: application/json');

// Get the request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

// Route API requests
switch ($path) {
    case 'upload':
        require_once 'api/upload.php';
        break;
    
    case 'download':
        require_once 'api/download.php';
        break;
    
    case 'delete':
        require_once 'api/delete.php';
        break;
    
    case 'list':
        require_once 'api/list.php';
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
?>
