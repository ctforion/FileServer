<?php
namespace App\Controllers;

require_once __DIR__ . '/../../core/storage/FileManager.php';
require_once __DIR__ . '/../../core/auth/Auth.php';

/**
 * File Controller
 * Handles all file management API endpoints
 */
class FileController {
    private $fileManager;
    private $auth;
    
    public function __construct() {
        $this->fileManager = new FileManager();
        $this->auth = new Auth();
    }
    
    /**
     * List files for current user
     */
    public function list($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $query = $request['query'];
        $page = (int)($query['page'] ?? 1);
        $limit = min((int)($query['limit'] ?? 20), 100);
        $folder = $query['folder'] ?? '/';
        $sort = $query['sort'] ?? 'created_at';
        $order = $query['order'] ?? 'desc';
        $search = $query['search'] ?? '';
        
        $files = $this->fileManager->listFiles($user['id'], [
            'folder' => $folder,
            'page' => $page,
            'limit' => $limit,
            'sort' => $sort,
            'order' => $order,
            'search' => $search
        ]);
        
        return [
            'success' => true,
            'files' => $files['files'],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $files['total'],
                'pages' => ceil($files['total'] / $limit)
            ]
        ];
    }
    
    /**
     * Upload files
     */
    public function upload($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        if (empty($request['files']['files'])) {
            return ['error' => 'No files uploaded', 'code' => 'NO_FILES'];
        }
        
        $folder = $_POST['folder'] ?? '/';
        $description = $_POST['description'] ?? '';
        $tags = isset($_POST['tags']) ? explode(',', $_POST['tags']) : [];
        $isPublic = isset($_POST['is_public']) ? (bool)$_POST['is_public'] : false;
        
        $results = [];
        $files = $request['files']['files'];
        
        // Handle multiple files
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                
                $result = $this->fileManager->uploadFile($user['id'], $file, [
                    'folder' => $folder,
                    'description' => $description,
                    'tags' => $tags,
                    'is_public' => $isPublic
                ]);
                
                $results[] = $result;
            }
        } else {
            $result = $this->fileManager->uploadFile($user['id'], $files, [
                'folder' => $folder,
                'description' => $description,
                'tags' => $tags,
                'is_public' => $isPublic
            ]);
            
            $results[] = $result;
        }
        
        $successful = array_filter($results, function($r) { return $r['success']; });
        $failed = array_filter($results, function($r) { return !$r['success']; });
        
        return [
            'success' => !empty($successful),
            'uploaded' => count($successful),
            'failed' => count($failed),
            'results' => $results
        ];
    }
    
    /**
     * Get file information
     */
    public function get($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $fileId = $params['id'];
        $file = $this->fileManager->getFile($fileId, $user['id']);
        
        if (!$file) {
            return ['error' => 'File not found', 'code' => 'FILE_NOT_FOUND'];
        }
        
        return [
            'success' => true,
            'file' => $file
        ];
    }
    
    /**
     * Download file
     */
    public function download($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            http_response_code(401);
            return 'Not authenticated';
        }
        
        $fileId = $params['id'];
        $file = $this->fileManager->getFile($fileId, $user['id']);
        
        if (!$file) {
            http_response_code(404);
            return 'File not found';
        }
        
        $result = $this->fileManager->downloadFile($fileId, $user['id']);
        
        if (!$result['success']) {
            http_response_code(403);
            return $result['message'];
        }
        
        $filePath = $result['path'];
        $filename = $file['original_name'];
        $mimeType = $file['mime_type'];
        
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        
        readfile($filePath);
        exit();
    }
    
    /**
     * Get file thumbnail
     */
    public function thumbnail($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            http_response_code(401);
            return 'Not authenticated';
        }
        
        $fileId = $params['id'];
        $size = $request['query']['size'] ?? 'medium';
        
        $thumbnail = $this->fileManager->getThumbnail($fileId, $user['id'], $size);
        
        if (!$thumbnail) {
            http_response_code(404);
            return 'Thumbnail not found';
        }
        
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=3600');
        
        readfile($thumbnail);
        exit();
    }
    
    /**
     * Update file metadata
     */
    public function update($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $fileId = $params['id'];
        $data = $request['body'];
        
        $result = $this->fileManager->updateFile($fileId, $user['id'], $data);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'File updated successfully',
                'file' => $result['file']
            ];
        }
        
        return ['error' => $result['message'], 'code' => 'UPDATE_FAILED'];
    }
    
    /**
     * Delete file
     */
    public function delete($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $fileId = $params['id'];
        $permanent = ($request['query']['permanent'] ?? 'false') === 'true';
        
        $result = $this->fileManager->deleteFile($fileId, $user['id'], $permanent);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => $permanent ? 'File permanently deleted' : 'File moved to trash'
            ];
        }
        
        return ['error' => $result['message'], 'code' => 'DELETE_FAILED'];
    }
    
    /**
     * Copy file
     */
    public function copy($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $fileId = $params['id'];
        $destination = $request['body']['destination'] ?? '/';
        $newName = $request['body']['name'] ?? null;
        
        $result = $this->fileManager->copyFile($fileId, $user['id'], $destination, $newName);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'File copied successfully',
                'file' => $result['file']
            ];
        }
        
        return ['error' => $result['message'], 'code' => 'COPY_FAILED'];
    }
    
    /**
     * Move file
     */
    public function move($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $fileId = $params['id'];
        $destination = $request['body']['destination'] ?? '/';
        $newName = $request['body']['name'] ?? null;
        
        $result = $this->fileManager->moveFile($fileId, $user['id'], $destination, $newName);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'File moved successfully',
                'file' => $result['file']
            ];
        }
        
        return ['error' => $result['message'], 'code' => 'MOVE_FAILED'];
    }
    
    /**
     * Share file
     */
    public function share($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $fileId = $params['id'];
        $shareWith = $request['body']['share_with'] ?? [];
        $permissions = $request['body']['permissions'] ?? 'read';
        $expiry = $request['body']['expiry'] ?? null;
        $isPublic = $request['body']['is_public'] ?? false;
        
        $result = $this->fileManager->shareFile($fileId, $user['id'], [
            'share_with' => $shareWith,
            'permissions' => $permissions,
            'expiry' => $expiry,
            'is_public' => $isPublic
        ]);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'File shared successfully',
                'share_url' => $result['share_url']
            ];
        }
        
        return ['error' => $result['message'], 'code' => 'SHARE_FAILED'];
    }
    
    /**
     * Get file versions
     */
    public function versions($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $fileId = $params['id'];
        $versions = $this->fileManager->getFileVersions($fileId, $user['id']);
        
        return [
            'success' => true,
            'versions' => $versions
        ];
    }
    
    /**
     * Restore file version
     */
    public function restore($request, $params) {
        $user = $GLOBALS['current_user'] ?? null;
        if (!$user) {
            return ['error' => 'Not authenticated', 'code' => 'NOT_AUTHENTICATED'];
        }
        
        $fileId = $params['id'];
        $versionId = $request['body']['version_id'] ?? null;
        
        if (!$versionId) {
            return ['error' => 'Version ID is required', 'code' => 'MISSING_VERSION_ID'];
        }
        
        $result = $this->fileManager->restoreFileVersion($fileId, $versionId, $user['id']);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'File version restored successfully',
                'file' => $result['file']
            ];
        }
        
        return ['error' => $result['message'], 'code' => 'RESTORE_FAILED'];
    }
}
?>
