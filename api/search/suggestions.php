<?php
/**
 * Search API - Search Suggestions Endpoint
 * Provides autocomplete suggestions for search queries
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/auth/Auth.php';
require_once __DIR__ . '/../controllers/SearchController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Authenticate user
$auth = new Auth();
$user = $auth->getCurrentUser();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Get search query
$query = $_GET['q'] ?? '';
if (strlen($query) < 2) {
    echo json_encode(['suggestions' => []]);
    exit;
}

try {
    $controller = new SearchController();
    $controller->getSuggestions($query);
} catch (Exception $e) {
    error_log("Search Suggestions API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get suggestions: ' . $e->getMessage()]);
}
?>
