<?php
/**
 * API Router
 * Request routing and handling for separated API system
 */

class ApiRouter {
    private $routes = [];
    private $middleware = [];
    private $auth;
    
    public function __construct() {
        $this->auth = new ApiAuth();
        $this->registerRoutes();
    }
    
    /**
     * Register all API routes
     */
    private function registerRoutes() {
        // Authentication routes
        $this->post('/auth/login', [$this, 'handleLogin'], false);
        $this->post('/auth/logout', [$this, 'handleLogout'], true);
        $this->get('/auth/me', [$this, 'handleMe'], true);
        $this->post('/auth/token', [$this, 'handleGenerateToken'], true);
        
        // User management routes
        $this->get('/users', [$this, 'handleGetUsers'], true, 'admin');
        $this->get('/users/{id}', [$this, 'handleGetUser'], true);
        $this->post('/users', [$this, 'handleCreateUser'], true, 'admin');
        $this->put('/users/{id}', [$this, 'handleUpdateUser'], true);
        $this->delete('/users/{id}', [$this, 'handleDeleteUser'], true, 'admin');
        
        // File management routes
        $this->get('/files', [$this, 'handleListFiles'], true);
        $this->post('/files/upload', [$this, 'handleUploadFile'], true);
        $this->get('/files/{id}/download', [$this, 'handleDownloadFile'], true);
        $this->delete('/files/{id}', [$this, 'handleDeleteFile'], true);
        $this->get('/files/{id}/info', [$this, 'handleFileInfo'], true);
        
        // Admin routes
        $this->get('/admin/stats', [$this, 'handleAdminStats'], true, 'admin');
        $this->get('/admin/logs', [$this, 'handleAdminLogs'], true, 'admin');
        $this->post('/admin/cleanup', [$this, 'handleAdminCleanup'], true, 'admin');
        
        // System routes
        $this->get('/system/health', [$this, 'handleSystemHealth'], false);
        $this->get('/system/info', [$this, 'handleSystemInfo'], true, 'admin');
    }
    
    /**
     * Add route with GET method
     */
    private function get($path, $handler, $requireAuth = true, $role = null) {
        $this->addRoute('GET', $path, $handler, $requireAuth, $role);
    }
    
    /**
     * Add route with POST method
     */
    private function post($path, $handler, $requireAuth = true, $role = null) {
        $this->addRoute('POST', $path, $handler, $requireAuth, $role);
    }
    
    /**
     * Add route with PUT method
     */
    private function put($path, $handler, $requireAuth = true, $role = null) {
        $this->addRoute('PUT', $path, $handler, $requireAuth, $role);
    }
    
    /**
     * Add route with DELETE method
     */
    private function delete($path, $handler, $requireAuth = true, $role = null) {
        $this->addRoute('DELETE', $path, $handler, $requireAuth, $role);
    }
    
    /**
     * Add route to routing table
     */
    private function addRoute($method, $path, $handler, $requireAuth = true, $role = null) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'requireAuth' => $requireAuth,
            'role' => $role
        ];
    }
    
    /**
     * Handle incoming request
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getRequestPath();
        
        // Find matching route
        $route = $this->findRoute($method, $path);
        
        if (!$route) {
            ApiResponse::notFound('API endpoint not found');
        }
        
        // Handle authentication
        $user = null;
        if ($route['requireAuth']) {
            $user = $this->auth->authenticate(true);
            
            if ($route['role']) {
                $this->auth->requireRole($user, $route['role']);
            }
        }
        
        // Extract path parameters
        $params = $this->extractParams($route['path'], $path);
        
        // Call route handler
        try {
            call_user_func($route['handler'], $params, $user);
        } catch (Exception $e) {
            ApiResponse::handleException($e);
        }
    }
    
    /**
     * Get clean request path
     */
    private function getRequestPath() {
        $uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($uri, PHP_URL_PATH);
        
        // Remove API base path
        $basePath = ApiConfig::get('base_path', '/api');
        if (strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }
        
        return rtrim($path, '/') ?: '/';
    }
    
    /**
     * Find matching route
     */
    private function findRoute($method, $path) {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchPath($route['path'], $path)) {
                return $route;
            }
        }
        return null;
    }
    
    /**
     * Match path pattern with actual path
     */
    private function matchPath($pattern, $path) {
        // Convert pattern to regex
        $regex = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        
        return preg_match($regex, $path);
    }
    
    /**
     * Extract parameters from path
     */
    private function extractParams($pattern, $path) {
        $params = [];
        
        // Find parameter names in pattern
        preg_match_all('/\{([^}]+)\}/', $pattern, $paramNames);
        
        // Extract values from path
        $regex = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        
        if (preg_match($regex, $path, $matches)) {
            array_shift($matches); // Remove full match
            
            foreach ($paramNames[1] as $index => $name) {
                $params[$name] = $matches[$index] ?? null;
            }
        }
        
        return $params;
    }
    
    // Route Handlers
    
    public function handleLogin($params, $user) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $username = $input['username'] ?? $_POST['username'] ?? '';
        $password = $input['password'] ?? $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            ApiResponse::validationError(['username' => 'Username is required', 'password' => 'Password is required']);
        }
        
        $user = $this->auth->login($username, $password);
        
        if (!$user) {
            ApiResponse::error('Invalid credentials', 401);
        }
        
        $token = $this->auth->generateApiToken($username);
        
        ApiResponse::success([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'token' => $token
        ], 'Login successful');
    }
    
    public function handleLogout($params, $user) {
        $this->auth->logout();
        ApiResponse::success(null, 'Logout successful');
    }
    
    public function handleMe($params, $user) {
        ApiResponse::success([
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'quota' => $user['quota']
        ]);
    }
    
    public function handleGenerateToken($params, $user) {
        $token = $this->auth->generateApiToken($user['username']);
        ApiResponse::success(['token' => $token], 'API token generated');
    }
    
    public function handleSystemHealth($params, $user) {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'uptime' => $this->getUptime(),
            'version' => ApiConfig::get('version'),
            'checks' => [
                'storage' => is_writable(ApiConfig::getStoragePath()),
                'data' => is_writable(ApiConfig::getDataPath()),
                'users' => file_exists(ApiConfig::getUsersFile())
            ]
        ];
        
        ApiResponse::success($health);
    }
    
    public function handleGetUsers($params, $user) {
        require_once dirname(__DIR__, 2) . '/api/endpoints/Users.php';
        $endpoint = new UsersEndpoint();
        $endpoint->handleGetUsers($params, $user);
    }    public function handleListFiles($params, $user) {
        require_once dirname(__DIR__, 2) . '/api/endpoints/Files.php';
        $endpoint = new FilesEndpoint();
        $endpoint->handleListFiles($params, $user);
    }
    
    public function handleUploadFile($params, $user) {
        require_once dirname(__DIR__, 2) . '/api/endpoints/Files.php';
        $endpoint = new FilesEndpoint();
        $endpoint->handleUploadFile($params, $user);
    }
    
    public function handleAdminLogs($params, $user) {
        require_once dirname(__DIR__, 2) . '/api/endpoints/Admin.php';
        $endpoint = new AdminEndpoint();
        $endpoint->handleAdminLogs($params, $user);
    }
    
    public function handleAdminCleanup($params, $user) {
        require_once dirname(__DIR__, 2) . '/api/endpoints/Admin.php';
        $endpoint = new AdminEndpoint();
        $endpoint->handleAdminCleanup($params, $user);
    }
    
    public function handleGetUser($params, $user) {
        require_once dirname(__DIR__, 2) . '/api/endpoints/Users.php';
        $endpoint = new UsersEndpoint();
        $endpoint->handleGetUser($params, $user);
    }
    
    public function handleCreateUser($params, $user) {
        require_once dirname(__DIR__, 2) . '/api/endpoints/Users.php';
        $endpoint = new UsersEndpoint();
        $endpoint->handleCreateUser($params, $user);
    }
    
    public function handleUpdateUser($params, $user) {
        require_once dirname(__DIR__, 2) . '/api/endpoints/Users.php';
        $endpoint = new UsersEndpoint();
        $endpoint->handleUpdateUser($params, $user);
    }
    
    public function handleDeleteUser($params, $user) {
        require_once dirname(__DIR__, 2) . '/api/endpoints/Users.php';
        $endpoint = new UsersEndpoint();
        $endpoint->handleDeleteUser($params, $user);
    }
    
    public function handleDownloadFile($params, $user) {
        require_once dirname(__DIR__, 2) . '/api/endpoints/Files.php';
        $endpoint = new FilesEndpoint();
        $endpoint->handleDownloadFile($params, $user);
    }
    
    public function handleDeleteFile($params, $user) {
        require_once dirname(__DIR__, 2) . '/api/endpoints/Files.php';
        $endpoint = new FilesEndpoint();
        $endpoint->handleDeleteFile($params, $user);
    }
    
    public function handleFileInfo($params, $user) {
        require_once dirname(__DIR__, 2) . '/api/endpoints/Files.php';
        $endpoint = new FilesEndpoint();
        $endpoint->handleFileInfo($params, $user);
    }
    
    public function handleSystemInfo($params, $user) {
        $info = [
            'api_version' => ApiConfig::get('version'),
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'system_time' => date('c'),
            'timezone' => date_default_timezone_get(),
            'memory_limit' => ini_get('memory_limit'),
            'max_file_size' => ini_get('upload_max_filesize'),
            'extensions' => get_loaded_extensions()
        ];
        
        ApiResponse::success($info);
    }
    
    private function getUptime() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return ['load' => $load];
        }
        return ['load' => null];
    }
}
