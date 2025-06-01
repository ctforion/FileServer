<?php
/**
 * Main Web Interface Entry Point
 * Handles web requests and serves the application UI
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/template/TemplateEngine.php';
require_once __DIR__ . '/core/auth/Auth.php';

class WebApplication {
    private $template;
    private $auth;
    private $route;
    private $currentUser;
    
    public function __construct() {
        $this->template = new TemplateEngine();
        $this->auth = new Auth();
        $this->route = $this->parseRoute();
        $this->currentUser = $this->getCurrentUser();
        
        // Set global template variables
        $this->template->assign('currentUser', $this->currentUser);
        $this->template->assign('appName', env('APP_NAME', 'File Storage Server'));
        $this->template->assign('appVersion', env('APP_VERSION', '1.0.0'));
        $this->template->assign('baseUrl', $this->getBaseUrl());
    }
    
    private function parseRoute() {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = trim($path, '/');
        
        // Remove common prefixes
        $path = str_replace(['public/', 'web/'], '', $path);
        
        if (empty($path)) {
            $path = 'home';
        }
        
        return explode('/', $path);
    }
    
    private function getCurrentUser() {
        // Check for session
        session_start();
        
        if (isset($_SESSION['user_id'])) {
            $user = $this->auth->getUserById($_SESSION['user_id']);
            if ($user && $user['email_verified_at']) {
                return $user;
            }
        }
        
        // Check for remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            $user = $this->auth->validateRememberToken($_COOKIE['remember_token']);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                return $user;
            }
        }
        
        return null;
    }
    
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['SCRIPT_NAME']);
        return $protocol . '://' . $host . $path;
    }
    
    public function run() {
        try {
            $page = $this->route[0] ?? 'home';
            $action = $this->route[1] ?? 'index';
            
            // Public routes that don't require authentication
            $publicRoutes = ['home', 'login', 'register', 'forgot-password', 'reset-password', 'verify-email', 'public'];
            
            // Check authentication for protected routes
            if (!in_array($page, $publicRoutes) && !$this->currentUser) {
                $this->redirect('/login');
                return;
            }
            
            // Route to appropriate handler
            switch ($page) {
                case 'home':
                    $this->showHome();
                    break;
                    
                case 'login':
                    $this->showLogin();
                    break;
                    
                case 'register':
                    $this->showRegister();
                    break;
                    
                case 'dashboard':
                    $this->showDashboard();
                    break;
                    
                case 'files':
                    $this->showFiles($action);
                    break;
                    
                case 'upload':
                    $this->showUpload();
                    break;
                    
                case 'profile':
                    $this->showProfile();
                    break;
                    
                case 'admin':
                    $this->showAdmin($action);
                    break;
                    
                case 'search':
                    $this->showSearch();
                    break;
                    
                case 'settings':
                    $this->showSettings();
                    break;
                    
                case 'public':
                    $this->showPublicFile($action);
                    break;
                    
                case 'logout':
                    $this->handleLogout();
                    break;
                    
                default:
                    $this->show404();
                    break;
            }
            
        } catch (Exception $e) {
            error_log('Web Application Error: ' . $e->getMessage());
            $this->showError('An error occurred while processing your request.');
        }
    }
    
    private function showHome() {
        if ($this->currentUser) {
            $this->redirect('/dashboard');
            return;
        }
        
        $this->template->assign('pageTitle', 'Welcome');
        echo $this->template->render('pages/home');
    }
    
    private function showLogin() {
        if ($this->currentUser) {
            $this->redirect('/dashboard');
            return;
        }
        
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);
        
        $this->template->assign('pageTitle', 'Login');
        $this->template->assign('error', $error);
        echo $this->template->render('pages/login');
    }
    
    private function showRegister() {
        if ($this->currentUser) {
            $this->redirect('/dashboard');
            return;
        }
        
        $this->template->assign('pageTitle', 'Register');
        echo $this->template->render('pages/register');
    }
    
    private function showDashboard() {
        // Get user statistics
        $db = Database::getInstance();
        
        $fileStats = $db->query(
            "SELECT 
                COUNT(*) as total_files,
                COALESCE(SUM(file_size), 0) as storage_used,
                COUNT(CASE WHEN is_public = 1 THEN 1 END) as public_files
             FROM files 
             WHERE user_id = ? AND deleted_at IS NULL",
            [$this->currentUser['id']]
        )->fetch(PDO::FETCH_ASSOC);
        
        // Get recent files
        $recentFiles = $db->query(
            "SELECT * FROM files 
             WHERE user_id = ? AND deleted_at IS NULL 
             ORDER BY created_at DESC LIMIT 5",
            [$this->currentUser['id']]
        )->fetchAll(PDO::FETCH_ASSOC);
        
        // Get storage quota
        $storageQuota = (int)env('USER_STORAGE_QUOTA', 1073741824); // 1GB default
        
        $this->template->assign('pageTitle', 'Dashboard');
        $this->template->assign('fileStats', $fileStats);
        $this->template->assign('recentFiles', $recentFiles);
        $this->template->assign('storageQuota', $storageQuota);
        echo $this->template->render('pages/dashboard');
    }
    
    private function showFiles($action = 'list') {
        switch ($action) {
            case 'view':
                $fileId = $this->route[2] ?? null;
                $this->showFileDetails($fileId);
                break;
                
            default:
                $this->showFileList();
                break;
        }
    }
    
    private function showFileList() {
        $this->template->assign('pageTitle', 'My Files');
        echo $this->template->render('pages/files');
    }
    
    private function showFileDetails($fileId) {
        if (!$fileId) {
            $this->redirect('/files');
            return;
        }
        
        $db = Database::getInstance();
        $file = $db->query(
            "SELECT * FROM files WHERE id = ? AND user_id = ? AND deleted_at IS NULL",
            [$fileId, $this->currentUser['id']]
        )->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            $this->show404();
            return;
        }
        
        $this->template->assign('pageTitle', $file['original_name']);
        $this->template->assign('file', $file);
        echo $this->template->render('pages/file-details');
    }
    
    private function showUpload() {
        $this->template->assign('pageTitle', 'Upload Files');
        echo $this->template->render('pages/upload');
    }
    
    private function showProfile() {
        $this->template->assign('pageTitle', 'Profile');
        echo $this->template->render('pages/profile');
    }
    
    private function showAdmin($action = 'dashboard') {
        if ($this->currentUser['role'] !== 'admin') {
            $this->showError('Access denied. Admin privileges required.');
            return;
        }
        
        switch ($action) {
            case 'users':
                $this->template->assign('pageTitle', 'User Management');
                echo $this->template->render('pages/admin/users');
                break;
                
            case 'settings':
                $this->template->assign('pageTitle', 'System Settings');
                echo $this->template->render('pages/admin/settings');
                break;
                
            case 'logs':
                $this->template->assign('pageTitle', 'Audit Logs');
                echo $this->template->render('pages/admin/logs');
                break;
                
            default:
                $this->template->assign('pageTitle', 'Admin Dashboard');
                echo $this->template->render('pages/admin/dashboard');
                break;
        }
    }
    
    private function showSearch() {
        $query = $_GET['q'] ?? '';
        
        $this->template->assign('pageTitle', 'Search Files');
        $this->template->assign('searchQuery', $query);
        echo $this->template->render('pages/search');
    }
    
    private function showSettings() {
        $this->template->assign('pageTitle', 'Settings');
        echo $this->template->render('pages/settings');
    }
    
    private function showPublicFile($shareToken) {
        if (!$shareToken) {
            $this->show404();
            return;
        }
        
        $db = Database::getInstance();
        $file = $db->query(
            "SELECT f.*, u.first_name, u.last_name 
             FROM files f 
             LEFT JOIN users u ON f.user_id = u.id 
             WHERE f.share_token = ? AND f.is_public = 1 AND f.deleted_at IS NULL",
            [$shareToken]
        )->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            $this->show404();
            return;
        }
        
        $this->template->assign('pageTitle', $file['original_name']);
        $this->template->assign('file', $file);
        echo $this->template->render('pages/public-file');
    }
    
    private function handleLogout() {
        if ($this->currentUser) {
            $this->auth->logout($this->currentUser['id']);
        }
        
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/');
        
        $this->redirect('/login');
    }
    
    private function show404() {
        http_response_code(404);
        $this->template->assign('pageTitle', 'Page Not Found');
        echo $this->template->render('pages/404');
    }
    
    private function showError($message) {
        $this->template->assign('pageTitle', 'Error');
        $this->template->assign('errorMessage', $message);
        echo $this->template->render('pages/error');
    }
    
    private function redirect($path) {
        header('Location: ' . $this->getBaseUrl() . $path);
        exit();
    }
}

// Run the application
$app = new WebApplication();
$app->run();
?>
