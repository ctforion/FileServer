<?php
/**
 * Template Engine System
 * Handles HTML template rendering with multi-language support and component system
 */

namespace FileServer\Core\Template;

use Exception;

class TemplateEngine {
    private static $instance = null;
    private $templatesPath;
    private $cachePath;
    private $language = 'en';
    private $translations = [];
    private $globalVars = [];
    private $components = [];
    private $blocks = [];
    private $extends = null;
    private $cacheEnabled = true;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->templatesPath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'templates';
        $this->cachePath = storage_path('cache' . DIRECTORY_SEPARATOR . 'templates');
        $this->cacheEnabled = TEMPLATE_CACHE_ENABLED;
        
        $this->ensureDirectories();
        $this->loadTranslations();
        $this->registerGlobalVars();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Ensure required directories exist
     */
    private function ensureDirectories(): void {
        $directories = [
            $this->templatesPath,
            $this->templatesPath . DIRECTORY_SEPARATOR . 'layouts',
            $this->templatesPath . DIRECTORY_SEPARATOR . 'components',
            $this->templatesPath . DIRECTORY_SEPARATOR . 'pages',
            $this->templatesPath . DIRECTORY_SEPARATOR . 'emails',
            $this->cachePath,
            storage_path('lang')
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Create default template files if they don't exist
        $this->createDefaultTemplates();
    }

    /**
     * Set current language
     */
    public function setLanguage(string $language): void {
        $this->language = $language;
        $this->loadTranslations();
    }

    /**
     * Load translations for current language
     */
    private function loadTranslations(): void {
        $langPath = storage_path('lang' . DIRECTORY_SEPARATOR . $this->language . '.php');
        
        if (file_exists($langPath)) {
            $this->translations = include $langPath;
        } else {
            // Load default English translations
            $this->translations = $this->getDefaultTranslations();
            
            // Save default translations file
            $this->saveTranslations($this->language, $this->translations);
        }
    }

    /**
     * Register global template variables
     */
    private function registerGlobalVars(): void {
        $this->globalVars = [
            'site_name' => SITE_NAME,
            'site_url' => SITE_URL,
            'version' => APP_VERSION,
            'language' => $this->language,
            'user' => null, // Will be set by Auth system
            'csrf_token' => $this->generateCSRFToken(),
            'current_year' => date('Y'),
            'app_name' => 'FileServer',
            'debug' => DEBUG_MODE
        ];
    }

    /**
     * Render template with data
     */
    public function render(string $template, array $data = []): string {
        try {
            // Merge global variables with provided data
            $templateData = array_merge($this->globalVars, $data);
            
            // Get template path
            $templatePath = $this->getTemplatePath($template);
            
            if (!file_exists($templatePath)) {
                throw new Exception("Template not found: {$template}");
            }

            // Check cache
            if ($this->cacheEnabled) {
                $cacheKey = $this->getCacheKey($template, $templateData);
                $cachedContent = $this->getFromCache($cacheKey);
                
                if ($cachedContent !== false) {
                    return $cachedContent;
                }
            }

            // Process template
            $content = $this->processTemplate($templatePath, $templateData);
            
            // Cache processed content
            if ($this->cacheEnabled) {
                $this->saveToCache($cacheKey, $content);
            }

            return $content;

        } catch (Exception $e) {
            $this->log("Template rendering error: " . $e->getMessage(), 'error');
            return $this->renderError("Template Error: " . $e->getMessage());
        }
    }

    /**
     * Process template file
     */
    private function processTemplate(string $templatePath, array $data): string {
        // Start output buffering
        ob_start();
        
        // Extract variables for template use
        extract($data, EXTR_SKIP);
        
        // Template helper functions
        $t = function(string $key, array $params = []) {
            return $this->translate($key, $params);
        };
        
        $url = function(string $path = '') {
            return url($path);
        };
        
        $asset = function(string $path) {
            return url('assets/' . ltrim($path, '/'));
        };
        
        $component = function(string $name, array $props = []) {
            return $this->renderComponent($name, $props);
        };
        
        $csrf = function() {
            return $this->globalVars['csrf_token'];
        };
        
        $formatDate = function($date, string $format = 'Y-m-d H:i:s') {
            return date($format, is_string($date) ? strtotime($date) : $date);
        };
        
        $formatSize = function(int $bytes) {
            return $this->formatFileSize($bytes);
        };
        
        $escape = function(string $text) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        };

        try {
            // Include template file
            include $templatePath;
            
            // Get content and clean buffer
            $content = ob_get_clean();
            
            // Process template syntax
            $content = $this->processTemplateSyntax($content, $data);
            
            // Handle template inheritance
            if ($this->extends) {
                $content = $this->processInheritance($content, $data);
            }
            
            return $content;
            
        } catch (Exception $e) {
            // Clean buffer on error
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Process custom template syntax
     */
    private function processTemplateSyntax(string $content, array $data): string {
        // Process @extends directive
        $content = preg_replace_callback(
            '/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            function($matches) {
                $this->extends = $matches[1];
                return '';
            },
            $content
        );

        // Process @block directive
        $content = preg_replace_callback(
            '/@block\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)(.*?)@endblock/s',
            function($matches) {
                $this->blocks[$matches[1]] = trim($matches[2]);
                return "<!-- BLOCK:{$matches[1]} -->";
            },
            $content
        );

        // Process @include directive
        $content = preg_replace_callback(
            '/@include\s*\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*(\[.*?\]))?\s*\)/',
            function($matches) use ($data) {
                $templateName = $matches[1];
                $includeData = isset($matches[2]) ? json_decode($matches[2], true) : [];
                $mergedData = array_merge($data, $includeData ?: []);
                
                return $this->render($templateName, $mergedData);
            },
            $content
        );

        // Process @component directive
        $content = preg_replace_callback(
            '/@component\s*\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*(\{.*?\}))?\s*\)/',
            function($matches) {
                $componentName = $matches[1];
                $props = isset($matches[2]) ? json_decode($matches[2], true) : [];
                
                return $this->renderComponent($componentName, $props ?: []);
            },
            $content
        );

        // Process @if directive
        $content = preg_replace_callback(
            '/@if\s*\(\s*([^)]+)\s*\)(.*?)(?:@else(.*?))?@endif/s',
            function($matches) use ($data) {
                $condition = $matches[1];
                $ifContent = $matches[2];
                $elseContent = isset($matches[3]) ? $matches[3] : '';
                
                // Simple condition evaluation (expand as needed)
                $result = $this->evaluateCondition($condition, $data);
                
                return $result ? $ifContent : $elseContent;
            },
            $content
        );

        // Process @foreach directive
        $content = preg_replace_callback(
            '/@foreach\s*\(\s*([^)]+)\s*\)(.*?)@endforeach/s',
            function($matches) use ($data) {
                $loopExpression = $matches[1];
                $loopContent = $matches[2];
                
                return $this->processLoop($loopExpression, $loopContent, $data);
            },
            $content
        );

        // Process {{ }} variables
        $content = preg_replace_callback(
            '/\{\{\s*([^}]+)\s*\}\}/',
            function($matches) use ($data) {
                $variable = trim($matches[1]);
                return $this->evaluateVariable($variable, $data);
            },
            $content
        );

        // Process {!! !!} raw variables
        $content = preg_replace_callback(
            '/\{!!\s*([^}]+)\s*!!\}/',
            function($matches) use ($data) {
                $variable = trim($matches[1]);
                return $this->evaluateVariable($variable, $data, false);
            },
            $content
        );

        return $content;
    }

    /**
     * Process template inheritance
     */
    private function processInheritance(string $content, array $data): string {
        if (!$this->extends) {
            return $content;
        }

        // Render parent template
        $parentContent = $this->render($this->extends, $data);
        
        // Replace block placeholders
        foreach ($this->blocks as $blockName => $blockContent) {
            $placeholder = "<!-- BLOCK:{$blockName} -->";
            $parentContent = str_replace($placeholder, $blockContent, $parentContent);
        }
        
        // Clear blocks for next render
        $this->blocks = [];
        $this->extends = null;
        
        return $parentContent;
    }

    /**
     * Render component
     */
    public function renderComponent(string $name, array $props = []): string {
        $componentPath = $this->templatesPath . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . $name . '.php';
        
        if (!file_exists($componentPath)) {
            return "<!-- Component not found: {$name} -->";
        }

        return $this->processTemplate($componentPath, $props);
    }

    /**
     * Translate text
     */
    public function translate(string $key, array $params = []): string {
        $keys = explode('.', $key);
        $value = $this->translations;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $key; // Return key if translation not found
            }
        }
        
        if (is_string($value)) {
            // Replace parameters
            foreach ($params as $param => $replacement) {
                $value = str_replace(":$param", $replacement, $value);
            }
            return $value;
        }
        
        return $key;
    }

    /**
     * Set global variable
     */
    public function setGlobal(string $key, $value): void {
        $this->globalVars[$key] = $value;
    }

    /**
     * Evaluate template condition
     */
    private function evaluateCondition(string $condition, array $data): bool {
        // Simple condition evaluation - expand as needed
        // This is a basic implementation for common conditions
        
        // Check for isset() function
        if (preg_match('/isset\s*\(\s*([^)]+)\s*\)/', $condition, $matches)) {
            $variable = trim($matches[1], '$');
            return isset($data[$variable]);
        }
        
        // Check for empty() function
        if (preg_match('/empty\s*\(\s*([^)]+)\s*\)/', $condition, $matches)) {
            $variable = trim($matches[1], '$');
            return empty($data[$variable]);
        }
        
        // Check for simple variable
        if (preg_match('/^\$?(\w+)$/', $condition, $matches)) {
            $variable = $matches[1];
            return !empty($data[$variable]);
        }
        
        // Check for equality
        if (preg_match('/^\$?(\w+)\s*==\s*[\'"]([^\'"]*)[\'"]$/', $condition, $matches)) {
            $variable = $matches[1];
            $value = $matches[2];
            return isset($data[$variable]) && $data[$variable] == $value;
        }
        
        return false;
    }

    /**
     * Process loop directive
     */
    private function processLoop(string $expression, string $content, array $data): string {
        // Parse foreach expression: $items as $item or $items as $key => $value
        if (preg_match('/\$(\w+)\s+as\s+\$(\w+)(?:\s*=>\s*\$(\w+))?/', $expression, $matches)) {
            $arrayVar = $matches[1];
            $keyVar = $matches[2];
            $valueVar = isset($matches[3]) ? $matches[3] : null;
            
            if (!isset($data[$arrayVar]) || !is_array($data[$arrayVar])) {
                return '';
            }
            
            $output = '';
            foreach ($data[$arrayVar] as $key => $value) {
                $loopData = $data;
                
                if ($valueVar) {
                    $loopData[$keyVar] = $key;
                    $loopData[$valueVar] = $value;
                } else {
                    $loopData[$keyVar] = $value;
                }
                
                // Process content with loop variables
                $loopContent = $this->processTemplateSyntax($content, $loopData);
                $output .= $loopContent;
            }
            
            return $output;
        }
        
        return '';
    }

    /**
     * Evaluate template variable
     */
    private function evaluateVariable(string $variable, array $data, bool $escape = true): string {
        // Handle function calls
        if (preg_match('/^(\w+)\s*\((.*?)\)$/', $variable, $matches)) {
            $function = $matches[1];
            $args = $matches[2];
            
            switch ($function) {
                case 't':
                case 'trans':
                    $key = trim($args, '\'"');
                    $value = $this->translate($key);
                    break;
                case 'url':
                    $path = trim($args, '\'"');
                    $value = url($path);
                    break;
                case 'asset':
                    $path = trim($args, '\'"');
                    $value = url('assets/' . ltrim($path, '/'));
                    break;
                default:
                    $value = '';
            }
        } else {
            // Handle simple variables
            $varName = ltrim($variable, '$');
            $value = $data[$varName] ?? '';
        }
        
        return $escape ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') : (string)$value;
    }

    /**
     * Get template file path
     */
    private function getTemplatePath(string $template): string {
        // Handle different template locations
        if (strpos($template, 'layouts/') === 0) {
            return $this->templatesPath . DIRECTORY_SEPARATOR . $template . '.php';
        } elseif (strpos($template, 'components/') === 0) {
            return $this->templatesPath . DIRECTORY_SEPARATOR . $template . '.php';
        } elseif (strpos($template, 'emails/') === 0) {
            return $this->templatesPath . DIRECTORY_SEPARATOR . $template . '.php';
        } else {
            return $this->templatesPath . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $template . '.php';
        }
    }

    /**
     * Cache management
     */
    private function getCacheKey(string $template, array $data): string {
        $dataHash = md5(serialize($data));
        return md5($template . $this->language . $dataHash);
    }

    private function getFromCache(string $key): string|false {
        $cacheFile = $this->cachePath . DIRECTORY_SEPARATOR . $key . '.cache';
        
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            
            if ($cacheData && $cacheData['expires'] > time()) {
                return $cacheData['content'];
            } else {
                unlink($cacheFile);
            }
        }
        
        return false;
    }

    private function saveToCache(string $key, string $content): void {
        $cacheFile = $this->cachePath . DIRECTORY_SEPARATOR . $key . '.cache';
        $cacheData = [
            'content' => $content,
            'expires' => time() + TEMPLATE_CACHE_TTL,
            'created' => time()
        ];
        
        file_put_contents($cacheFile, json_encode($cacheData), LOCK_EX);
    }

    /**
     * Clear template cache
     */
    public function clearCache(): bool {
        $files = glob($this->cachePath . DIRECTORY_SEPARATOR . '*.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }

    /**
     * Render error template
     */
    private function renderError(string $message): string {
        return '<!DOCTYPE html>
<html>
<head>
    <title>Template Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; }
        .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="error">
        <h2>Template Error</h2>
        <p>' . htmlspecialchars($message) . '</p>
    </div>
</body>
</html>';
    }

    /**
     * Generate CSRF token
     */
    private function generateCSRFToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Format file size
     */
    private function formatFileSize(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get default translations
     */
    private function getDefaultTranslations(): array {
        return [
            'common' => [
                'home' => 'Home',
                'login' => 'Login',
                'logout' => 'Logout',
                'register' => 'Register',
                'dashboard' => 'Dashboard',
                'profile' => 'Profile',
                'settings' => 'Settings',
                'admin' => 'Admin',
                'files' => 'Files',
                'upload' => 'Upload',
                'download' => 'Download',
                'delete' => 'Delete',
                'edit' => 'Edit',
                'save' => 'Save',
                'cancel' => 'Cancel',
                'search' => 'Search',
                'loading' => 'Loading...',
                'error' => 'Error',
                'success' => 'Success',
                'warning' => 'Warning',
                'info' => 'Information'
            ],
            'auth' => [
                'username' => 'Username',
                'email' => 'Email',
                'password' => 'Password',
                'confirm_password' => 'Confirm Password',
                'remember_me' => 'Remember Me',
                'forgot_password' => 'Forgot Password?',
                'login_success' => 'Login successful',
                'login_failed' => 'Invalid credentials',
                'logout_success' => 'Logged out successfully',
                'register_success' => 'Registration successful',
                'register_failed' => 'Registration failed'
            ],
            'files' => [
                'upload_file' => 'Upload File',
                'upload_success' => 'File uploaded successfully',
                'upload_failed' => 'File upload failed',
                'delete_confirm' => 'Are you sure you want to delete this file?',
                'delete_success' => 'File deleted successfully',
                'delete_failed' => 'File deletion failed',
                'file_not_found' => 'File not found',
                'access_denied' => 'Access denied',
                'file_size' => 'File Size',
                'file_type' => 'File Type',
                'created_at' => 'Created',
                'updated_at' => 'Updated'
            ],
            'validation' => [
                'required' => 'This field is required',
                'email_invalid' => 'Please enter a valid email address',
                'password_too_short' => 'Password must be at least :min characters',
                'passwords_not_match' => 'Passwords do not match',
                'file_too_large' => 'File size exceeds the maximum allowed size',
                'file_type_not_allowed' => 'File type is not allowed'
            ]
        ];
    }

    /**
     * Save translations to file
     */
    public function saveTranslations(string $language, array $translations): bool {
        $langPath = storage_path('lang' . DIRECTORY_SEPARATOR . $language . '.php');
        $content = '<?php' . PHP_EOL . 'return ' . var_export($translations, true) . ';' . PHP_EOL;
        
        return file_put_contents($langPath, $content, LOCK_EX) !== false;
    }

    /**
     * Create default template files
     */
    private function createDefaultTemplates(): void {
        $this->createLayoutTemplate();
        $this->createHomeTemplate();
        $this->createLoginTemplate();
        $this->createDashboardTemplate();
        $this->createErrorTemplate();
        $this->createComponentTemplates();
    }

    private function createLayoutTemplate(): void {
        $layoutPath = $this->templatesPath . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.php';
        
        if (!file_exists($layoutPath)) {
            $content = '<!DOCTYPE html>
<html lang="<?= $language ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? $escape($title) . " - " . $site_name : $site_name ?></title>
    <meta name="csrf-token" content="<?= $csrf() ?>">
    <link href="<?= $asset("css/app.css") ?>" rel="stylesheet">
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link href="<?= $asset($css) ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="<?= $theme ?? "light" ?>">
    <div id="app">
        @include("components/header")
        
        <main class="main-content">
            @if(isset($breadcrumbs))
                @component("breadcrumbs", ["items" => $breadcrumbs])
            @endif
            
            @if(isset($flash_message))
                @component("alert", ["type" => $flash_type ?? "info", "message" => $flash_message])
            @endif
            
            @block("content")
                <p>No content defined</p>
            @endblock
        </main>
        
        @include("components/footer")
    </div>
    
    <script src="<?= $asset("js/app.js") ?>"></script>
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js): ?>
            <script src="<?= $asset($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>';
            
            file_put_contents($layoutPath, $content);
        }
    }

    private function createHomeTemplate(): void {
        $homePath = $this->templatesPath . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'home.php';
        
        if (!file_exists($homePath)) {
            $content = '@extends("layouts/app")

@block("content")
<div class="hero-section">
    <div class="container">
        <h1><?= $t("common.welcome") ?> <?= $site_name ?></h1>
        <p class="lead"><?= $t("home.description") ?></p>
        
        @if(!$user)
            <div class="cta-buttons">
                <a href="<?= $url("login") ?>" class="btn btn-primary"><?= $t("common.login") ?></a>
                <a href="<?= $url("register") ?>" class="btn btn-secondary"><?= $t("common.register") ?></a>
            </div>
        @else
            <div class="cta-buttons">
                <a href="<?= $url("dashboard") ?>" class="btn btn-primary"><?= $t("common.dashboard") ?></a>
                <a href="<?= $url("upload") ?>" class="btn btn-success"><?= $t("files.upload_file") ?></a>
            </div>
        @endif
    </div>
</div>

<div class="features-section">
    <div class="container">
        <h2><?= $t("home.features") ?></h2>
        <div class="features-grid">
            @component("feature-card", [
                "icon" => "upload",
                "title" => $t("features.easy_upload"),
                "description" => $t("features.easy_upload_desc")
            ])
            
            @component("feature-card", [
                "icon" => "secure",
                "title" => $t("features.secure"),
                "description" => $t("features.secure_desc")
            ])
            
            @component("feature-card", [
                "icon" => "organize",
                "title" => $t("features.organize"),
                "description" => $t("features.organize_desc")
            ])
        </div>
    </div>
</div>
@endblock';
            
            file_put_contents($homePath, $content);
        }
    }

    private function createLoginTemplate(): void {
        $loginPath = $this->templatesPath . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'login.php';
        
        if (!file_exists($loginPath)) {
            $content = '@extends("layouts/app")

@block("content")
<div class="auth-container">
    <div class="auth-card">
        <h2><?= $t("common.login") ?></h2>
        
        <form method="POST" action="<?= $url("api/auth/login") ?>" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf() ?>">
            
            <div class="form-group">
                <label for="identifier"><?= $t("auth.username") ?> / <?= $t("auth.email") ?></label>
                <input type="text" id="identifier" name="identifier" required>
            </div>
            
            <div class="form-group">
                <label for="password"><?= $t("auth.password") ?></label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember_me" value="1">
                    <?= $t("auth.remember_me") ?>
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary btn-full">
                <?= $t("common.login") ?>
            </button>
        </form>
        
        <div class="auth-links">
            <a href="<?= $url("forgot-password") ?>"><?= $t("auth.forgot_password") ?></a>
            <a href="<?= $url("register") ?>"><?= $t("common.register") ?></a>
        </div>
    </div>
</div>
@endblock';
            
            file_put_contents($loginPath, $content);
        }
    }

    private function createDashboardTemplate(): void {
        $dashboardPath = $this->templatesPath . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'dashboard.php';
        
        if (!file_exists($dashboardPath)) {
            $content = '@extends("layouts/app")

@block("content")
<div class="dashboard">
    <div class="dashboard-header">
        <h1><?= $t("common.dashboard") ?></h1>
        <div class="dashboard-actions">
            <a href="<?= $url("upload") ?>" class="btn btn-primary">
                <?= $t("files.upload_file") ?>
            </a>
        </div>
    </div>
    
    <div class="dashboard-stats">
        @component("stat-card", [
            "title" => $t("dashboard.total_files"),
            "value" => $stats["total_files"] ?? 0,
            "icon" => "files"
        ])
        
        @component("stat-card", [
            "title" => $t("dashboard.storage_used"),
            "value" => $formatSize($stats["storage_used"] ?? 0),
            "icon" => "storage"
        ])
        
        @component("stat-card", [
            "title" => $t("dashboard.recent_uploads"),
            "value" => $stats["recent_uploads"] ?? 0,
            "icon" => "upload"
        ])
    </div>
    
    <div class="dashboard-content">
        <div class="recent-files">
            <h3><?= $t("dashboard.recent_files") ?></h3>
            @if(!empty($recent_files))
                @component("file-list", ["files" => $recent_files])
            @else
                <p><?= $t("dashboard.no_files") ?></p>
            @endif
        </div>
    </div>
</div>
@endblock';
            
            file_put_contents($dashboardPath, $content);
        }
    }

    private function createErrorTemplate(): void {
        $errorPath = $this->templatesPath . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'error.php';
        
        if (!file_exists($errorPath)) {
            $content = '@extends("layouts/app")

@block("content")
<div class="error-page">
    <div class="error-content">
        <h1 class="error-code"><?= $code ?? "500" ?></h1>
        <h2 class="error-title"><?= $title ?? $t("error.something_wrong") ?></h2>
        <p class="error-message"><?= $message ?? $t("error.try_again") ?></p>
        
        <div class="error-actions">
            <a href="<?= $url() ?>" class="btn btn-primary"><?= $t("common.home") ?></a>
            <button onclick="history.back()" class="btn btn-secondary"><?= $t("common.back") ?></button>
        </div>
    </div>
</div>
@endblock';
            
            file_put_contents($errorPath, $content);
        }
    }

    private function createComponentTemplates(): void {
        // Create header component
        $headerPath = $this->templatesPath . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'header.php';
        if (!file_exists($headerPath)) {
            $content = '<header class="main-header">
    <nav class="navbar">
        <div class="nav-brand">
            <a href="<?= $url() ?>"><?= $site_name ?></a>
        </div>
        
        <div class="nav-menu">
            @if($user)
                <a href="<?= $url("dashboard") ?>"><?= $t("common.dashboard") ?></a>
                <a href="<?= $url("files") ?>"><?= $t("common.files") ?></a>
                @if($user["role"] === "admin")
                    <a href="<?= $url("admin") ?>"><?= $t("common.admin") ?></a>
                @endif
                <div class="nav-dropdown">
                    <button class="nav-dropdown-toggle"><?= $escape($user["username"]) ?></button>
                    <div class="nav-dropdown-menu">
                        <a href="<?= $url("profile") ?>"><?= $t("common.profile") ?></a>
                        <a href="<?= $url("settings") ?>"><?= $t("common.settings") ?></a>
                        <hr>
                        <a href="<?= $url("logout") ?>"><?= $t("common.logout") ?></a>
                    </div>
                </div>
            @else
                <a href="<?= $url("login") ?>"><?= $t("common.login") ?></a>
                <a href="<?= $url("register") ?>"><?= $t("common.register") ?></a>
            @endif
        </div>
    </nav>
</header>';
            
            file_put_contents($headerPath, $content);
        }

        // Create footer component
        $footerPath = $this->templatesPath . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'footer.php';
        if (!file_exists($footerPath)) {
            $content = '<footer class="main-footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-brand">
                <h4><?= $site_name ?></h4>
                <p><?= $t("footer.description") ?></p>
            </div>
            
            <div class="footer-links">
                <h5><?= $t("footer.quick_links") ?></h5>
                <ul>
                    <li><a href="<?= $url() ?>"><?= $t("common.home") ?></a></li>
                    <li><a href="<?= $url("about") ?>"><?= $t("footer.about") ?></a></li>
                    <li><a href="<?= $url("contact") ?>"><?= $t("footer.contact") ?></a></li>
                    <li><a href="<?= $url("privacy") ?>"><?= $t("footer.privacy") ?></a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?= $current_year ?> <?= $site_name ?>. <?= $t("footer.rights_reserved") ?></p>
            <p>Powered by FileServer v<?= $version ?></p>
        </div>
    </div>
</footer>';
            
            file_put_contents($footerPath, $content);
        }
    }

    /**
     * Log template events
     */
    private function log(string $message, string $level = 'info'): void {
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            $logMessage = "[" . date('Y-m-d H:i:s') . "] [TEMPLATE] [{$level}] {$message}" . PHP_EOL;
            $logFile = storage_path('logs' . DIRECTORY_SEPARATOR . 'template.log');
            
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Prevent cloning and unserialization
     */
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
    }
}
