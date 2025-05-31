<?php
/**
 * Portable PHP File Storage Server - Main Application Class
 */

class App {
    private $request;
    private $response;
    private $db;
    private $auth;
    private $router;
    
    public function __construct() {
        $this->initializeDatabase();
        $this->initializeAuth();
        $this->initializeRouter();
    }
    
    public function run() {
        try {
            // Start session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Get request path
            $path = $this->getRequestPath();
            
            // Route the request
            $this->router->route($path);
            
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    private function initializeDatabase() {
        try {
            $this->db = new Database();
        } catch (Exception $e) {
            // If database connection fails, redirect to install
            if (!file_exists(__DIR__ . '/../install.php')) {
                die('Database connection failed and installer not found.');
            }
            header('Location: ' . url('install.php'));
            exit;
        }
    }
    
    private function initializeAuth() {
        $this->auth = new Auth($this->db);
    }
    
    private function initializeRouter() {
        $this->router = new Router($this->db, $this->auth);
    }
    
    private function getRequestPath() {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove base path
        if (BASE_PATH && strpos($path, BASE_PATH) === 0) {
            $path = substr($path, strlen(BASE_PATH));
        }
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }
        
        return $path ?: '/';
    }
    
    private function handleError($exception) {
        if (DEBUG) {
            echo '<h1>Error</h1>';
            echo '<p>' . htmlspecialchars($exception->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
        } else {
            // Log error
            error_log($exception->getMessage());
            
            // Show generic error page
            http_response_code(500);
            echo $this->renderTemplate('error', [
                'title' => 'Server Error',
                'message' => 'An internal server error occurred.'
            ]);
        }
    }
    
    private function renderTemplate($template, $data = []) {
        $templateFile = __DIR__ . '/../web/templates/' . $template . '.html';
        
        if (!file_exists($templateFile)) {
            return '<h1>Template Error</h1><p>Template not found: ' . htmlspecialchars($template) . '</p>';
        }
        
        $content = file_get_contents($templateFile);
        
        // Replace template variables
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', htmlspecialchars($value), $content);
        }
        
        // Replace common variables
        $content = str_replace('{{base_url}}', BASE_URL . BASE_PATH, $content);
        $content = str_replace('{{app_name}}', APP_NAME, $content);
        $content = str_replace('{{app_version}}', APP_VERSION, $content);
        
        return $content;
    }
}

/**
 * Simple Database Class
 */
class Database {
    private $connection;
    
    public function __construct() {
        $this->connect();
        $this->createTablesIfNeeded();
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception('Database query failed: ' . $e->getMessage());
        }
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    private function createTablesIfNeeded() {
        // Check if tables exist
        $stmt = $this->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() === 0) {
            $this->createTables();
        }
    }
    
    private function createTables() {
        $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
        $statements = explode(';', $sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $this->query($statement);
            }
        }
        
        // Create default admin user
        $this->createDefaultAdmin();
    }
    
    private function createDefaultAdmin() {
        $hashedPassword = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
        
        $this->query(
            "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin') 
             ON DUPLICATE KEY UPDATE email = VALUES(email)",
            [ADMIN_USERNAME, ADMIN_EMAIL, $hashedPassword]
        );
    }
}

/**
 * Authentication Class
 */
class Auth {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function login($username, $password) {
        $stmt = $this->db->query(
            "SELECT * FROM users WHERE username = ? AND is_active = 1",
            [$username]
        );
        
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            
            // Update last login
            $this->db->query(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$user['id']]
            );
            
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        session_destroy();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $stmt = $this->db->query(
            "SELECT * FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
        
        return $stmt->fetch();
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . url('login'));
            exit;
        }
    }
    
    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            http_response_code(403);
            die('Access denied');
        }
    }
}

/**
 * Simple Router Class
 */
class Router {
    private $db;
    private $auth;
    
    public function __construct($db, $auth) {
        $this->db = $db;
        $this->auth = $auth;
    }
    
    public function route($path) {
        // Remove leading slash
        $path = ltrim($path, '/');
        
        // Default route
        if (empty($path)) {
            $path = 'dashboard';
        }
        
        // API routes
        if (strpos($path, 'api/') === 0) {
            $this->handleApiRequest($path);
            return;
        }
        
        // File routes
        switch ($path) {
            case 'login':
                $this->showLogin();
                break;
            case 'logout':
                $this->handleLogout();
                break;
            case 'register':
                $this->showRegister();
                break;
            case 'dashboard':
                $this->showDashboard();
                break;
            case 'upload':
                $this->showUpload();
                break;
            case 'files':
                $this->showFiles();
                break;
            case 'admin':
                $this->showAdmin();
                break;
            default:
                $this->show404();
        }
    }
    
    private function handleApiRequest($path) {
        header('Content-Type: application/json');
        
        // Remove 'api/' prefix
        $apiPath = substr($path, 4);
        $parts = explode('/', $apiPath);
        
        $controller = $parts[0] ?? '';
        $action = $parts[1] ?? '';
        
        try {
            switch ($controller) {
                case 'auth':
                    $this->handleAuthApi($action);
                    break;
                case 'files':
                    $this->handleFilesApi($action);
                    break;
                default:
                    throw new Exception('API endpoint not found');
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    private function handleAuthApi($action) {
        switch ($action) {
            case 'login':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Method not allowed');
                }
                
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                
                if ($this->auth->login($username, $password)) {
                    echo json_encode(['success' => true, 'redirect' => url('dashboard')]);
                } else {
                    throw new Exception('Invalid credentials');
                }
                break;
                
            case 'register':
                if (!ALLOW_REGISTRATION) {
                    throw new Exception('Registration is disabled');
                }
                
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Method not allowed');
                }
                
                $username = $_POST['username'] ?? '';
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                
                if (strlen($password) < PASSWORD_MIN_LENGTH) {
                    throw new Exception('Password too short');
                }
                
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $this->db->query(
                    "INSERT INTO users (username, email, password) VALUES (?, ?, ?)",
                    [$username, $email, $hashedPassword]
                );
                
                echo json_encode(['success' => true, 'message' => 'Account created successfully']);
                break;
                
            default:
                throw new Exception('Unknown auth action');
        }
    }
    
    private function handleFilesApi($action) {
        $this->auth->requireLogin();
        
        switch ($action) {
            case 'upload':
                $this->handleFileUpload();
                break;
            case 'list':
                $this->handleFileList();
                break;
            case 'delete':
                $this->handleFileDelete();
                break;
            default:
                throw new Exception('Unknown files action');
        }
    }
    
    private function handleFileUpload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Method not allowed');
        }
        
        if (!isset($_FILES['file'])) {
            throw new Exception('No file uploaded');
        }
        
        $file = $_FILES['file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error: ' . $file['error']);
        }
        
        if ($file['size'] > MAX_FILE_SIZE_BYTES) {
            throw new Exception('File too large');
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS_ARRAY)) {
            throw new Exception('File type not allowed');
        }
        
        // Generate unique filename
        $storedName = uniqid() . '_' . time() . '.' . $ext;
        $storagePath = 'private/' . $storedName;
        $fullPath = STORAGE_PATH . '/' . $storagePath;
        
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        // Save to database
        $this->db->query(
            "INSERT INTO files (user_id, filename, stored_name, file_type, size, mime_type, file_hash, storage_path) 
             VALUES (?, ?, ?, 'private', ?, ?, ?, ?)",
            [
                $_SESSION['user_id'],
                $file['name'],
                $storedName,
                $file['size'],
                $file['type'],
                hash_file('sha256', $fullPath),
                $storagePath
            ]
        );
        
        echo json_encode([
            'success' => true,
            'file_id' => $this->db->lastInsertId(),
            'message' => 'File uploaded successfully'
        ]);
    }
    
    private function handleFileList() {
        $stmt = $this->db->query(
            "SELECT id, filename, size, mime_type, created_at, download_count 
             FROM files 
             WHERE user_id = ? AND is_deleted = 0 
             ORDER BY created_at DESC",
            [$_SESSION['user_id']]
        );
        
        $files = $stmt->fetchAll();
        
        foreach ($files as &$file) {
            $file['size_formatted'] = format_bytes($file['size']);
        }
        
        echo json_encode(['files' => $files]);
    }
    
    private function handleFileDelete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Method not allowed');
        }
        
        $fileId = $_POST['file_id'] ?? '';
        
        // Verify ownership
        $stmt = $this->db->query(
            "SELECT storage_path FROM files WHERE id = ? AND user_id = ? AND is_deleted = 0",
            [$fileId, $_SESSION['user_id']]
        );
        
        $file = $stmt->fetch();
        if (!$file) {
            throw new Exception('File not found');
        }
        
        // Mark as deleted
        $this->db->query(
            "UPDATE files SET is_deleted = 1, deleted_at = NOW() WHERE id = ?",
            [$fileId]
        );
        
        // Delete physical file
        $fullPath = STORAGE_PATH . '/' . $file['storage_path'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        
        echo json_encode(['success' => true, 'message' => 'File deleted']);
    }
    
    private function showLogin() {
        if ($this->auth->isLoggedIn()) {
            header('Location: ' . url('dashboard'));
            exit;
        }
        
        echo $this->renderTemplate('login', [
            'title' => 'Login',
            'allow_registration' => ALLOW_REGISTRATION ? 'true' : 'false'
        ]);
    }
    
    private function showRegister() {
        if (!ALLOW_REGISTRATION) {
            $this->show404();
            return;
        }
        
        if ($this->auth->isLoggedIn()) {
            header('Location: ' . url('dashboard'));
            exit;
        }
        
        echo $this->renderTemplate('register', [
            'title' => 'Register',
            'min_password_length' => PASSWORD_MIN_LENGTH
        ]);
    }
    
    private function handleLogout() {
        $this->auth->logout();
        header('Location: ' . url('login'));
        exit;
    }
    
    private function showDashboard() {
        $this->auth->requireLogin();
        
        $user = $this->auth->getCurrentUser();
        
        echo $this->renderTemplate('dashboard', [
            'title' => 'Dashboard',
            'username' => $user['username'],
            'storage_used' => format_bytes($user['storage_used']),
            'storage_limit' => format_bytes($user['storage_limit'])
        ]);
    }
    
    private function showUpload() {
        $this->auth->requireLogin();
        
        echo $this->renderTemplate('upload', [
            'title' => 'Upload Files',
            'max_file_size' => MAX_FILE_SIZE,
            'allowed_extensions' => ALLOWED_EXTENSIONS
        ]);
    }
    
    private function showFiles() {
        $this->auth->requireLogin();
        
        echo $this->renderTemplate('files', [
            'title' => 'My Files'
        ]);
    }
    
    private function showAdmin() {
        $this->auth->requireAdmin();
        
        echo $this->renderTemplate('admin', [
            'title' => 'Admin Panel'
        ]);
    }
    
    private function show404() {
        http_response_code(404);
        echo $this->renderTemplate('error', [
            'title' => '404 Not Found',
            'message' => 'The requested page was not found.'
        ]);
    }
    
    private function renderTemplate($template, $data = []) {
        $templateFile = __DIR__ . '/../web/templates/' . $template . '.html';
        
        if (!file_exists($templateFile)) {
            return '<h1>Template Error</h1><p>Template not found: ' . htmlspecialchars($template) . '</p>';
        }
        
        $content = file_get_contents($templateFile);
        
        // Replace template variables
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        // Replace common variables
        $content = str_replace('{{base_url}}', BASE_URL . BASE_PATH, $content);
        $content = str_replace('{{app_name}}', APP_NAME, $content);
        $content = str_replace('{{app_version}}', APP_VERSION, $content);
        
        return $content;
    }
}
?>
