<?php
namespace App\Controllers;

require_once __DIR__ . '/../../core/database/Database.php';

/**
 * Search Controller
 * Handles file search and indexing functionality
 */
class SearchController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Search files
     */
    public function search($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $query = $request['query'];
        $searchTerm = $query['q'] ?? '';
        $type = $query['type'] ?? 'all';
        $folder = $query['folder'] ?? '';
        $tags = isset($query['tags']) ? explode(',', $query['tags']) : [];
        $dateFrom = $query['date_from'] ?? '';
        $dateTo = $query['date_to'] ?? '';
        $sizeMin = (int)($query['size_min'] ?? 0);
        $sizeMax = (int)($query['size_max'] ?? 0);
        $page = (int)($query['page'] ?? 1);
        $limit = min((int)($query['limit'] ?? 20), 100);
        
        $sql = "SELECT f.*, u.email as owner_email, u.first_name, u.last_name 
                FROM files f 
                LEFT JOIN users u ON f.user_id = u.id 
                WHERE f.deleted_at IS NULL";
        $params_sql = [];
        
        // Check permissions
        if ($user['role'] !== 'admin') {
            $sql .= " AND (f.user_id = ? OR f.is_public = 1 OR f.id IN (
                        SELECT file_id FROM file_shares 
                        WHERE shared_with_user_id = ? OR shared_with_role = ?
                      ))";
            $params_sql[] = $user['id'];
            $params_sql[] = $user['id'];
            $params_sql[] = $user['role'];
        }
        
        // Search in filename, description, and content
        if ($searchTerm) {
            $sql .= " AND (f.original_name LIKE ? OR f.description LIKE ? OR f.content_text LIKE ?)";
            $searchPattern = "%$searchTerm%";
            $params_sql[] = $searchPattern;
            $params_sql[] = $searchPattern;
            $params_sql[] = $searchPattern;
        }
        
        // Filter by file type
        if ($type && $type !== 'all') {
            switch ($type) {
                case 'image':
                    $sql .= " AND f.mime_type LIKE 'image/%'";
                    break;
                case 'video':
                    $sql .= " AND f.mime_type LIKE 'video/%'";
                    break;
                case 'audio':
                    $sql .= " AND f.mime_type LIKE 'audio/%'";
                    break;
                case 'document':
                    $sql .= " AND (f.mime_type LIKE 'application/pdf' OR 
                                   f.mime_type LIKE 'application/msword' OR 
                                   f.mime_type LIKE 'application/vnd.openxmlformats%' OR
                                   f.mime_type LIKE 'text/%')";
                    break;
                case 'archive':
                    $sql .= " AND (f.mime_type LIKE 'application/zip' OR 
                                   f.mime_type LIKE 'application/x-rar%' OR 
                                   f.mime_type LIKE 'application/x-tar%')";
                    break;
            }
        }
        
        // Filter by folder
        if ($folder) {
            $sql .= " AND f.folder LIKE ?";
            $params_sql[] = "$folder%";
        }
        
        // Filter by tags
        if (!empty($tags)) {
            $tagPlaceholders = str_repeat('?,', count($tags) - 1) . '?';
            $sql .= " AND f.id IN (SELECT file_id FROM file_tags WHERE tag IN ($tagPlaceholders))";
            $params_sql = array_merge($params_sql, $tags);
        }
        
        // Filter by date range
        if ($dateFrom) {
            $sql .= " AND f.created_at >= ?";
            $params_sql[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND f.created_at <= ?";
            $params_sql[] = $dateTo . ' 23:59:59';
        }
        
        // Filter by file size
        if ($sizeMin > 0) {
            $sql .= " AND f.file_size >= ?";
            $params_sql[] = $sizeMin;
        }
        
        if ($sizeMax > 0) {
            $sql .= " AND f.file_size <= ?";
            $params_sql[] = $sizeMax;
        }
        
        // Count total results
        $countSql = str_replace('SELECT f.*, u.email as owner_email, u.first_name, u.last_name', 'SELECT COUNT(*)', $sql);
        $totalResults = $this->db->query($countSql, $params_sql)->fetchColumn();
        
        // Add ordering and pagination
        $sql .= " ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
        $params_sql[] = $limit;
        $params_sql[] = ($page - 1) * $limit;
        
        $files = $this->db->query($sql, $params_sql)->fetchAll(PDO::FETCH_ASSOC);
        
        // Add tags to each file
        foreach ($files as &$file) {
            $file['tags'] = $this->getFileTags($file['id']);
        }
        
        return [
            'success' => true,
            'results' => $files,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalResults,
                'pages' => ceil($totalResults / $limit)
            ],
            'filters' => [
                'query' => $searchTerm,
                'type' => $type,
                'folder' => $folder,
                'tags' => $tags,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'size_min' => $sizeMin,
                'size_max' => $sizeMax
            ]
        ];
    }
    
    /**
     * Get search suggestions
     */
    public function suggestions($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $query = $request['query']['q'] ?? '';
        $type = $request['query']['type'] ?? 'all';
        
        if (strlen($query) < 2) {
            return [
                'success' => true,
                'suggestions' => []
            ];
        }
        
        $suggestions = [];
        
        // File name suggestions
        $sql = "SELECT DISTINCT original_name FROM files 
                WHERE original_name LIKE ? AND deleted_at IS NULL";
        $params_sql = ["%$query%"];
        
        if ($user['role'] !== 'admin') {
            $sql .= " AND (user_id = ? OR is_public = 1)";
            $params_sql[] = $user['id'];
        }
        
        $sql .= " LIMIT 5";
        
        $fileNames = $this->db->query($sql, $params_sql)->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($fileNames as $name) {
            $suggestions[] = [
                'text' => $name,
                'type' => 'filename'
            ];
        }
        
        // Tag suggestions
        $sql = "SELECT DISTINCT tag FROM file_tags 
                WHERE tag LIKE ? 
                AND file_id IN (SELECT id FROM files WHERE deleted_at IS NULL";
        
        if ($user['role'] !== 'admin') {
            $sql .= " AND (user_id = ? OR is_public = 1)";
        }
        
        $sql .= ") LIMIT 5";
        
        $tags = $this->db->query($sql, $params_sql)->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tags as $tag) {
            $suggestions[] = [
                'text' => $tag,
                'type' => 'tag'
            ];
        }
        
        // Content suggestions (if full-text search is enabled)
        if (env('SEARCH_FULLTEXT_ENABLED', false)) {
            $sql = "SELECT DISTINCT SUBSTRING(content_text, 1, 50) as snippet 
                    FROM files 
                    WHERE content_text LIKE ? AND deleted_at IS NULL";
            
            if ($user['role'] !== 'admin') {
                $sql .= " AND (user_id = ? OR is_public = 1)";
            }
            
            $sql .= " LIMIT 3";
            
            $snippets = $this->db->query($sql, $params_sql)->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($snippets as $snippet) {
                $suggestions[] = [
                    'text' => trim($snippet) . '...',
                    'type' => 'content'
                ];
            }
        }
        
        return [
            'success' => true,
            'suggestions' => array_slice($suggestions, 0, 10)
        ];
    }
    
    /**
     * Get tags for a file
     */
    private function getFileTags($fileId) {
        $sql = "SELECT tag FROM file_tags WHERE file_id = ?";
        return $this->db->query($sql, [$fileId])->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
