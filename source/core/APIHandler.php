<?php
/**
 * APIHandler - REST API Endpoints
 * 
 * Handles all API requests for the file storage server including:
 * - File operations (upload, download, delete, list)
 * - User operations (login, register, profile)
 * - Share operations (create, manage, access)
 * - Admin operations (user management, system stats)
 */

class APIHandler {
    private $db;
    private $config;
    private $auth;
    private $fileManager;
    private $userManager;
    private $shareManager;
    private $adminManager;
    private $logger;

    public function __construct($database, $config, $auth) {
        $this->db = $database;
        $this->config = $config;
        $this->auth = $auth;
        $this->logger = new Logger($database);
        
        // Initialize managers
        $this->fileManager = new FileManager($database, $config);
        $this->userManager = new UserManager($database, $config);
        $this->shareManager = new ShareManager($database, $config);
        $this->adminManager = new AdminManager($database, $config, $this->userManager, $this->fileManager);
    }

    /**
     * Handle API requests
     */
    public function handleRequest($method, $endpoint, $data = []) {
        try {
            // Set JSON response header
            header('Content-Type: application/json');

            // Parse endpoint
            $parts = explode('/', trim($endpoint, '/'));
            $resource = $parts[0] ?? '';
            $action = $parts[1] ?? '';
            $id = $parts[2] ?? null;

            // Route to appropriate handler
            switch ($resource) {
                case 'auth':
                    return $this->handleAuth($method, $action, $data);
                
                case 'files':
                    return $this->handleFiles($method, $action, $id, $data);
                
                case 'shares':
                    return $this->handleShares($method, $action, $id, $data);
                
                case 'user':
                    return $this->handleUser($method, $action, $data);
                  case 'admin':
                    return $this->handleAdmin($method, $action, $id, $data);
                
                case 'system':
                    return $this->handleSystem($method, $action, $data);
                
                default:
                    return $this->errorResponse('Invalid endpoint', 404);
            }

        } catch (Exception $e) {
            $this->logger->log('api_error', [
                'endpoint' => $endpoint,
                'method' => $method,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Authentication endpoints
     */
    private function handleAuth($method, $action, $data) {
        switch ($action) {
            case 'login':
                if ($method !== 'POST') {
                    return $this->errorResponse('Method not allowed', 405);
                }
                
                $username = $data['username'] ?? '';
                $password = $data['password'] ?? '';
                $rememberMe = $data['remember_me'] ?? false;

                $result = $this->userManager->authenticateUser($username, $password, $rememberMe);
                
                // Set session cookie
                if (isset($result['session_token'])) {
                    $expires = $rememberMe ? time() + (30 * 24 * 60 * 60) : 0;
                    setcookie('session_token', $result['session_token'], $expires, '/', '', true, true);
                }

                return $this->successResponse($result);

            case 'register':
                if ($method !== 'POST') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $username = $data['username'] ?? '';
                $email = $data['email'] ?? '';
                $password = $data['password'] ?? '';
                $fullName = $data['full_name'] ?? null;

                $result = $this->userManager->registerUser($username, $email, $password, $fullName);
                return $this->successResponse($result);

            case 'logout':
                if ($method !== 'POST') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $this->auth->logout();
                return $this->successResponse(['message' => 'Logged out successfully']);

            case 'verify':
                if ($method !== 'GET') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $user = $this->auth->getCurrentUser();
                if ($user) {
                    return $this->successResponse(['user' => $user, 'authenticated' => true]);
                } else {
                    return $this->errorResponse('Not authenticated', 401);
                }

            default:
                return $this->errorResponse('Invalid auth action', 404);
        }
    }

    /**
     * File management endpoints
     */
    private function handleFiles($method, $action, $id, $data) {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        switch ($action) {
            case 'list':
                if ($method !== 'GET') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $page = (int)($_GET['page'] ?? 1);
                $limit = (int)($_GET['limit'] ?? 20);
                $search = $_GET['search'] ?? '';

                $files = $this->fileManager->getUserFiles($user['user_id'], $page, $limit, $search);
                return $this->successResponse($files);

            case 'upload':
                if ($method !== 'POST') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                if (!isset($_FILES['file'])) {
                    return $this->errorResponse('No file uploaded', 400);
                }

                $result = $this->fileManager->uploadFile($_FILES['file'], $user['user_id']);
                return $this->successResponse($result);

            case 'download':
                if ($method !== 'GET') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                if (!$id) {
                    return $this->errorResponse('File ID required', 400);
                }

                $this->fileManager->downloadFile($id, $user['user_id']);
                return; // Download handles response

            case 'delete':
                if ($method !== 'DELETE') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                if (!$id) {
                    return $this->errorResponse('File ID required', 400);
                }

                $this->fileManager->deleteFile($id, $user['user_id']);
                return $this->successResponse(['message' => 'File deleted successfully']);

            case 'info':
                if ($method !== 'GET') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                if (!$id) {
                    return $this->errorResponse('File ID required', 400);
                }

                $file = $this->fileManager->getFileInfo($id, $user['user_id']);
                return $this->successResponse($file);

            case 'rename':
                if ($method !== 'PUT') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                if (!$id) {
                    return $this->errorResponse('File ID required', 400);
                }

                $newName = $data['name'] ?? '';
                if (empty($newName)) {
                    return $this->errorResponse('New name required', 400);
                }

                $this->fileManager->renameFile($id, $user['user_id'], $newName);
                return $this->successResponse(['message' => 'File renamed successfully']);

            default:
                return $this->errorResponse('Invalid file action', 404);
        }
    }

    /**
     * Share management endpoints
     */
    private function handleShares($method, $action, $id, $data) {
        switch ($action) {
            case 'create':
                $user = $this->auth->getCurrentUser();
                if (!$user) {
                    return $this->errorResponse('Authentication required', 401);
                }

                if ($method !== 'POST') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $fileId = $data['file_id'] ?? '';
                if (empty($fileId)) {
                    return $this->errorResponse('File ID required', 400);
                }

                $options = [
                    'expires_at' => $data['expires_at'] ?? null,
                    'password' => $data['password'] ?? null,
                    'download_limit' => $data['download_limit'] ?? null,
                    'allow_preview' => $data['allow_preview'] ?? true
                ];

                $share = $this->shareManager->createShare($fileId, $user['user_id'], $options);
                return $this->successResponse($share);

            case 'list':
                $user = $this->auth->getCurrentUser();
                if (!$user) {
                    return $this->errorResponse('Authentication required', 401);
                }

                if ($method !== 'GET') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $page = (int)($_GET['page'] ?? 1);
                $limit = (int)($_GET['limit'] ?? 20);

                $shares = $this->shareManager->getUserShares($user['user_id'], $page, $limit);
                return $this->successResponse($shares);

            case 'access':
                if ($method !== 'POST') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $token = $data['token'] ?? '';
                $password = $data['password'] ?? null;

                if (empty($token)) {
                    return $this->errorResponse('Share token required', 400);
                }

                $validation = $this->shareManager->validateShareAccess($token, $password);
                if (!$validation['valid']) {
                    return $this->errorResponse($validation['error'], 403);
                }

                return $this->successResponse($validation['share']);

            case 'download':
                if ($method !== 'POST') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $token = $data['token'] ?? '';
                $password = $data['password'] ?? null;

                if (empty($token)) {
                    return $this->errorResponse('Share token required', 400);
                }

                $file = $this->shareManager->downloadViaShare(
                    $token, 
                    $password, 
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    $_SERVER['REMOTE_ADDR'] ?? null
                );

                // Send file
                $this->sendFile($file);
                return;

            case 'update':
                $user = $this->auth->getCurrentUser();
                if (!$user) {
                    return $this->errorResponse('Authentication required', 401);
                }

                if ($method !== 'PUT') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                if (!$id) {
                    return $this->errorResponse('Share ID required', 400);
                }

                $this->shareManager->updateShare($id, $user['user_id'], $data);
                return $this->successResponse(['message' => 'Share updated successfully']);

            case 'delete':
                $user = $this->auth->getCurrentUser();
                if (!$user) {
                    return $this->errorResponse('Authentication required', 401);
                }

                if ($method !== 'DELETE') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                if (!$id) {
                    return $this->errorResponse('Share ID required', 400);
                }

                $this->shareManager->deleteShare($id, $user['user_id']);
                return $this->successResponse(['message' => 'Share deleted successfully']);

            default:
                return $this->errorResponse('Invalid share action', 404);
        }
    }

    /**
     * User management endpoints
     */
    private function handleUser($method, $action, $data) {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            return $this->errorResponse('Authentication required', 401);
        }

        switch ($action) {
            case 'profile':
                if ($method === 'GET') {
                    $profile = $this->userManager->getUserById($user['user_id']);
                    return $this->successResponse($profile);
                } elseif ($method === 'PUT') {
                    $this->userManager->updateProfile($user['user_id'], $data);
                    return $this->successResponse(['message' => 'Profile updated successfully']);
                }
                return $this->errorResponse('Method not allowed', 405);

            case 'password':
                if ($method !== 'PUT') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $currentPassword = $data['current_password'] ?? '';
                $newPassword = $data['new_password'] ?? '';

                if (empty($currentPassword) || empty($newPassword)) {
                    return $this->errorResponse('Current and new passwords required', 400);
                }

                $this->userManager->changePassword($user['user_id'], $currentPassword, $newPassword);
                return $this->successResponse(['message' => 'Password changed successfully']);

            case 'storage':
                if ($method !== 'GET') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $usage = $this->userManager->getStorageUsage($user['user_id']);
                return $this->successResponse($usage);

            default:
                return $this->errorResponse('Invalid user action', 404);
        }
    }

    /**
     * Admin endpoints
     */
    private function handleAdmin($method, $action, $id, $data) {
        $user = $this->auth->getCurrentUser();
        if (!$user || !$this->adminManager->isAdmin($user['user_id'])) {
            return $this->errorResponse('Admin access required', 403);
        }

        switch ($action) {
            case 'dashboard':
                if ($method !== 'GET') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $stats = $this->adminManager->getDashboardStats();
                return $this->successResponse($stats);

            case 'users':
                if ($method === 'GET') {
                    $page = (int)($_GET['page'] ?? 1);
                    $limit = (int)($_GET['limit'] ?? 20);
                    $filters = $_GET;

                    $users = $this->adminManager->getUsers($page, $limit, $filters);
                    return $this->successResponse($users);
                } elseif ($method === 'PUT' && $id) {
                    $this->adminManager->updateUser($id, $data, $user['user_id']);
                    return $this->successResponse(['message' => 'User updated successfully']);
                } elseif ($method === 'DELETE' && $id) {
                    $this->adminManager->deleteUser($id, $user['user_id']);
                    return $this->successResponse(['message' => 'User deleted successfully']);
                }
                return $this->errorResponse('Method not allowed', 405);

            case 'files':
                if ($method === 'GET') {
                    $page = (int)($_GET['page'] ?? 1);
                    $limit = (int)($_GET['limit'] ?? 20);
                    $filters = $_GET;

                    $files = $this->adminManager->getFiles($page, $limit, $filters);
                    return $this->successResponse($files);
                } elseif ($method === 'DELETE' && $id) {
                    $this->adminManager->deleteFile($id, $user['user_id']);
                    return $this->successResponse(['message' => 'File deleted successfully']);
                }
                return $this->errorResponse('Method not allowed', 405);

            case 'logs':
                if ($method !== 'GET') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $page = (int)($_GET['page'] ?? 1);
                $limit = (int)($_GET['limit'] ?? 50);
                $filters = $_GET;

                $logs = $this->adminManager->getLogs($page, $limit, $filters);
                return $this->successResponse($logs);

            case 'settings':
                if ($method === 'GET') {
                    $settings = $this->adminManager->getSettings();
                    return $this->successResponse($settings);
                } elseif ($method === 'PUT') {
                    $this->adminManager->updateSettings($data, $user['user_id']);
                    return $this->successResponse(['message' => 'Settings updated successfully']);
                }
                return $this->errorResponse('Method not allowed', 405);

            case 'maintenance':
                if ($method !== 'POST') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $tasks = $data['tasks'] ?? [];
                if (empty($tasks)) {
                    return $this->errorResponse('No maintenance tasks specified', 400);
                }

                $results = $this->adminManager->runMaintenance($tasks, $user['user_id']);
                return $this->successResponse($results);

            default:
                return $this->errorResponse('Invalid admin action', 404);
        }
    }

    /**
     * System endpoints (update, maintenance, etc.)
     */
    private function handleSystem($method, $action, $data) {
        $user = $this->auth->getCurrentUser();
        if (!$user || !$this->adminManager->isAdmin($user['user_id'])) {
            return $this->errorResponse('Admin access required', 403);
        }

        switch ($action) {
            case 'update':
                if ($method !== 'POST') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $result = $this->performAutoUpdate($user['user_id']);
                return $this->successResponse($result);

            case 'status':
                if ($method !== 'GET') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $status = $this->getSystemStatus();
                return $this->successResponse($status);

            case 'backup':
                if ($method !== 'POST') {
                    return $this->errorResponse('Method not allowed', 405);
                }

                $result = $this->createSystemBackup($user['user_id']);
                return $this->successResponse($result);

            default:
                return $this->errorResponse('Invalid system action', 404);
        }
    }

    /**
     * Perform auto-update from GitHub
     */
    private function performAutoUpdate($adminId) {
        try {
            $this->logger->log('auto_update_started', [
                'admin_id' => $adminId,
                'timestamp' => date('Y-m-d H:i:s')
            ], $adminId);

            // Get current directory
            $currentDir = dirname(dirname(dirname(__DIR__)));
            
            // Create backup before update
            $backupDir = $currentDir . '/backup_' . date('Ymd_His');
            $this->createBackup($currentDir, $backupDir);

            // Execute update script
            $scriptPath = $currentDir . '/install.sh';
            if (!file_exists($scriptPath)) {
                throw new Exception('Update script not found');
            }

            // Make script executable (for Unix systems)
            if (function_exists('chmod')) {
                chmod($scriptPath, 0755);
            }

            // Execute update command
            $command = "cd " . escapeshellarg($currentDir) . " && bash install.sh " . escapeshellarg($currentDir) . " update 2>&1";
            $output = [];
            $returnCode = 0;
            
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new Exception('Update script failed: ' . implode("\n", $output));
            }

            $this->logger->log('auto_update_completed', [
                'admin_id' => $adminId,
                'backup_dir' => $backupDir,
                'output' => implode("\n", $output)
            ], $adminId);

            return [
                'success' => true,
                'message' => 'System updated successfully',
                'backup_location' => $backupDir,
                'output' => $output
            ];

        } catch (Exception $e) {
            $this->logger->log('auto_update_failed', [
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ], $adminId);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get system status and update information
     */
    private function getSystemStatus() {
        $currentDir = dirname(dirname(dirname(__DIR__)));
        
        $status = [
            'current_version' => $this->getCurrentVersion(),
            'last_update' => $this->getLastUpdateTime(),
            'git_available' => $this->isGitAvailable(),
            'update_script_exists' => file_exists($currentDir . '/install.sh'),
            'disk_space' => [
                'free' => disk_free_space($currentDir),
                'total' => disk_total_space($currentDir)
            ],
            'php_version' => PHP_VERSION,
            'server_time' => date('Y-m-d H:i:s')
        ];

        return $status;
    }

    /**
     * Create system backup
     */
    private function createSystemBackup($adminId) {
        try {
            $currentDir = dirname(dirname(dirname(__DIR__)));
            $backupDir = $currentDir . '/backup_manual_' . date('Ymd_His');
            
            $this->createBackup($currentDir, $backupDir);

            $this->logger->log('manual_backup_created', [
                'admin_id' => $adminId,
                'backup_dir' => $backupDir
            ], $adminId);

            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'backup_location' => $backupDir
            ];

        } catch (Exception $e) {
            $this->logger->log('manual_backup_failed', [
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ], $adminId);

            throw $e;
        }
    }

    /**
     * Helper method to create backup
     */
    private function createBackup($sourceDir, $backupDir) {
        if (!mkdir($backupDir, 0755, true)) {
            throw new Exception('Failed to create backup directory');
        }

        // Copy important files and directories
        $itemsToBackup = [
            'config.php',
            'source',
            'storage',
            'logs'
        ];

        foreach ($itemsToBackup as $item) {
            $sourcePath = $sourceDir . '/' . $item;
            $backupPath = $backupDir . '/' . $item;

            if (file_exists($sourcePath)) {
                if (is_dir($sourcePath)) {
                    $this->copyDirectory($sourcePath, $backupPath);
                } else {
                    copy($sourcePath, $backupPath);
                }
            }
        }

        // Create backup manifest
        file_put_contents($backupDir . '/backup_info.txt', json_encode([
            'created_at' => date('Y-m-d H:i:s'),
            'source_dir' => $sourceDir,
            'backup_type' => 'system_backup',
            'items_backed_up' => $itemsToBackup
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Recursively copy directory
     */
    private function copyDirectory($source, $destination) {
        if (!is_dir($source)) {
            return false;
        }

        if (!mkdir($destination, 0755, true)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );        foreach ($iterator as $item) {
            $destPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
            
            if ($item->isDir()) {
                if (!mkdir($destPath, 0755, true)) {
                    return false;
                }
            } else {
                if (!copy($item, $destPath)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get current version information
     */
    private function getCurrentVersion() {
        $versionFile = dirname(dirname(dirname(__DIR__))) . '/last_update.txt';
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }
        return 'Unknown';
    }

    /**
     * Get last update time
     */
    private function getLastUpdateTime() {
        $updateFile = dirname(dirname(dirname(__DIR__))) . '/last_update.txt';
        if (file_exists($updateFile)) {
            return filemtime($updateFile);
        }
        return null;
    }

    /**
     * Check if Git is available
     */
    private function isGitAvailable() {
        $output = [];
        $returnCode = 0;
        exec('git --version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Response helpers
     */
    private function successResponse($data, $code = 200) {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    }

    private function errorResponse($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
    }

    /**
     * Send file for download
     */
    private function sendFile($file) {
        if (!file_exists($file['file_path'])) {
            $this->errorResponse('File not found', 404);
            return;
        }

        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . $file['file_size']);
        header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');

        // Output file
        readfile($file['file_path']);
        exit;
    }
}
