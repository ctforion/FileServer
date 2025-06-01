<?php
/**
 * REST API Router - Main entry point for all API requests
 * Handles request routing, middleware, and response formatting
 */

require_once __DIR__ . '/../config.php';

// Helper function to get environment values
function env($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

// Load core classes
require_once __DIR__ . '/../core/database/Database.php';
require_once __DIR__ . '/../core/auth/Auth.php';

class ApiRouter {
    private $routes = [];
    private $middleware = [];
    private $request;
    private $headers;
    
    public function __construct() {
        $this->request = $this->parseRequest();
        $this->headers = $this->parseHeaders();
        $this->setupCORS();
        $this->registerRoutes();
    }
    
    private function parseRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = str_replace('/api', '', $path); // Remove /api prefix
        $path = trim($path, '/');
        
        $body = null;
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $input = file_get_contents('php://input');
            $body = json_decode($input, true) ?: [];
        }
        
        return [
            'method' => $method,
            'path' => $path,
            'query' => $_GET,
            'body' => $body,
            'files' => $_FILES
        ];
    }
    
    private function parseHeaders() {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('HTTP_', '', $key);
                $header = str_replace('_', '-', $header);
                $headers[strtolower($header)] = $value;
            }
        }
        return $headers;
    }
    
    private function setupCORS() {
        $allowedOrigins = explode(',', env('CORS_ALLOWED_ORIGINS', '*'));
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        
        if ($this->request['method'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    private function registerRoutes() {
        // Authentication routes
        $this->post('auth/login', 'AuthController@login');
        $this->post('auth/register', 'AuthController@register');
        $this->post('auth/logout', 'AuthController@logout');
        $this->post('auth/refresh', 'AuthController@refresh');
        $this->post('auth/verify-email', 'AuthController@verifyEmail');
        $this->post('auth/forgot-password', 'AuthController@forgotPassword');
        $this->post('auth/reset-password', 'AuthController@resetPassword');
        $this->get('auth/me', 'AuthController@me');
        
        // 2FA routes
        $this->post('auth/2fa/enable', 'AuthController@enable2FA');
        $this->post('auth/2fa/verify', 'AuthController@verify2FA');
        $this->delete('auth/2fa/disable', 'AuthController@disable2FA');
        
        // File management routes
        $this->get('files', 'FileController@list');
        $this->post('files/upload', 'FileController@upload');
        $this->get('files/{id}', 'FileController@get');
        $this->get('files/{id}/download', 'FileController@download');
        $this->get('files/{id}/thumbnail', 'FileController@thumbnail');
        $this->put('files/{id}', 'FileController@update');
        $this->delete('files/{id}', 'FileController@delete');
        $this->post('files/{id}/copy', 'FileController@copy');
        $this->post('files/{id}/move', 'FileController@move');
        $this->post('files/{id}/share', 'FileController@share');
        $this->get('files/{id}/versions', 'FileController@versions');
        $this->post('files/{id}/restore', 'FileController@restore');
        
        // Search routes
        $this->get('search', 'SearchController@search');
        $this->get('search/suggestions', 'SearchController@suggestions');
        
        // User management routes
        $this->get('users', 'UserController@list');
        $this->get('users/{id}', 'UserController@get');
        $this->put('users/{id}', 'UserController@update');
        $this->delete('users/{id}', 'UserController@delete');
        $this->get('users/{id}/files', 'UserController@files');
        $this->put('users/{id}/role', 'UserController@updateRole');
        
        // Admin routes
        $this->get('admin/stats', 'AdminController@stats');
        $this->get('admin/audit-log', 'AdminController@auditLog');
        $this->get('admin/settings', 'AdminController@getSettings');
        $this->put('admin/settings', 'AdminController@updateSettings');
        $this->post('admin/maintenance', 'AdminController@maintenance');
        $this->get('admin/health', 'AdminController@health');
        
        // Plugin routes
        $this->get('plugins', 'PluginController@list');
        $this->post('plugins/install', 'PluginController@install');
        $this->put('plugins/{id}/toggle', 'PluginController@toggle');
        $this->delete('plugins/{id}', 'PluginController@uninstall');
        
        // Webhook routes
        $this->get('webhooks', 'WebhookController@list');
        $this->post('webhooks', 'WebhookController@create');
        $this->put('webhooks/{id}', 'WebhookController@update');
        $this->delete('webhooks/{id}', 'WebhookController@delete');
        $this->post('webhooks/{id}/test', 'WebhookController@test');
        
        // System routes
        $this->get('system/info', 'SystemController@info');
        $this->post('system/backup', 'SystemController@backup');
        $this->post('system/update', 'SystemController@update');
        $this->get('system/logs', 'SystemController@logs');
    }
    
    public function get($path, $handler) {
        $this->addRoute('GET', $path, $handler);
    }
    
    public function post($path, $handler) {
        $this->addRoute('POST', $path, $handler);
    }
    
    public function put($path, $handler) {
        $this->addRoute('PUT', $path, $handler);
    }
    
    public function delete($path, $handler) {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    private function addRoute($method, $path, $handler) {
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';
        
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'path' => $path,
            'handler' => $handler
        ];
    }
    
    public function addMiddleware($middleware) {
        $this->middleware[] = $middleware;
    }
    
    public function run() {
        try {
            $route = $this->findRoute();
            
            if (!$route) {
                $this->respond(['error' => 'Route not found'], 404);
                return;
            }
            
            // Run middleware
            foreach ($this->middleware as $middleware) {
                $result = $this->runMiddleware($middleware);
                if ($result !== true) {
                    $this->respond(['error' => $result], 403);
                    return;
                }
            }
            
            // Execute route handler
            $response = $this->executeHandler($route);
            
            if (is_array($response)) {
                $this->respond($response);
            } else {
                echo $response;
            }
            
        } catch (Exception $e) {
            error_log('API Error: ' . $e->getMessage());
            $this->respond([
                'error' => 'Internal server error',
                'message' => env('APP_DEBUG', false) ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }
    
    private function findRoute() {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $this->request['method']) {
                continue;
            }
            
            if (preg_match($route['pattern'], $this->request['path'], $matches)) {
                array_shift($matches); // Remove full match
                $route['params'] = $this->extractParams($route['path'], $matches);
                return $route;
            }
        }
        return null;
    }
    
    private function extractParams($path, $matches) {
        $params = [];
        preg_match_all('/\{([^}]+)\}/', $path, $paramNames);
        
        foreach ($paramNames[1] as $index => $name) {
            $params[$name] = $matches[$index] ?? null;
        }
        
        return $params;
    }
    
    private function runMiddleware($middleware) {
        if (is_string($middleware)) {
            $middleware = new $middleware();
        }
        
        return $middleware->handle($this->request, $this->headers);
    }
      private function executeHandler($route) {
        list($controller, $method) = explode('@', $route['handler']);
        
        // Include BaseController first
        $baseControllerFile = __DIR__ . '/controllers/BaseController.php';
        if (!file_exists($baseControllerFile)) {
            throw new Exception("BaseController not found: $baseControllerFile");
        }
        require_once $baseControllerFile;
        
        $controllerFile = __DIR__ . '/controllers/' . $controller . '.php';
        
        if (!file_exists($controllerFile)) {
            throw new Exception("Controller file not found: $controllerFile");
        }
        
        require_once $controllerFile;
        
        if (!class_exists($controller)) {
            throw new Exception("Controller class not found: $controller");
        }        // Instantiate controller with dependencies
        $db = \FileServer\Core\Database\Database::getInstance();
        $auth = \FileServer\Core\Auth\Auth::getInstance();
        
        $instance = new $controller($db, $auth);
        $instance->setRequest($this->request, $route['params'] ?? []);
        
        if (!method_exists($instance, $method)) {
            throw new Exception("Method not found: $method");
        }
        
        // Extract URL parameters for method call
        $params = array_values($route['params'] ?? []);
        
        return call_user_func_array([$instance, $method], $params);
    }
    
    private function respond($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

// Rate limiting middleware
class RateLimitMiddleware {    public function handle($request, $headers) {
        $key = $_SERVER['REMOTE_ADDR'];
        $limit = (int)(env('RATE_LIMIT_REQUESTS', 1000));
        $window = (int)(env('RATE_LIMIT_WINDOW', 3600));
        
        $cacheDir = env('CACHE_PATH', './cache');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cache_file = $cacheDir . '/rate_limit_' . md5($key);
        $requests = [];
        
        if (file_exists($cache_file)) {
            $data = json_decode(file_get_contents($cache_file), true);
            $requests = $data['requests'] ?? [];
        }
        
        $now = time();
        $requests = array_filter($requests, function($time) use ($now, $window) {
            return ($now - $time) < $window;
        });
        
        if (count($requests) >= $limit) {
            return 'Rate limit exceeded';
        }
        
        $requests[] = $now;
        file_put_contents($cache_file, json_encode(['requests' => $requests]));
        
        return true;
    }
}

// Authentication middleware
class AuthMiddleware {
    public function handle($request, $headers) {
        $publicRoutes = [
            'auth/login',
            'auth/register',
            'auth/forgot-password',
            'auth/reset-password',
            'system/info'
        ];
        
        if (in_array($request['path'], $publicRoutes)) {
            return true;
        }
        
        $token = $headers['authorization'] ?? '';
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }
          if (!$token) {
            return 'Authentication required';
        }        try {
            $auth = \FileServer\Core\Auth\Auth::getInstance();
            $user = $auth->verifyJWT($token);
            
            if (!$user) {
                return 'Invalid or expired token';
            }
        } catch (Exception $e) {
            return 'Authentication error: ' . $e->getMessage();
        }
        
        // Store user in global context
        $GLOBALS['current_user'] = $user;
        
        return true;
    }
}

// Initialize and run API
$router = new ApiRouter();
$router->addMiddleware(new RateLimitMiddleware());
$router->addMiddleware(new AuthMiddleware());
$router->run();
?>
