<?php

/**
 * PluginController
 * 
 * Handles plugin management including installation, activation,
 * deactivation, configuration, and plugin API endpoints.
 */
class PluginController extends BaseController {
    private $db;
    private $auth;
    private $pluginDir;
    
    public function __construct($db, $auth) {
        parent::__construct();
        $this->db = $db;
        $this->auth = $auth;
        $this->pluginDir = getenv('PLUGIN_PATH') ?: './plugins';
        
        // Ensure plugin directory exists
        if (!is_dir($this->pluginDir)) {
            mkdir($this->pluginDir, 0755, true);
        }
    }
    
    /**
     * List all plugins
     */
    public function list() {
        try {
            if (!$this->auth->hasPermission('admin.plugins')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $plugins = [];
            
            // Get installed plugins from database
            $dbPlugins = $this->db->query("SELECT * FROM plugins ORDER BY name")->fetchAll();
            $dbPluginsMap = [];
            
            foreach ($dbPlugins as $plugin) {
                $dbPluginsMap[$plugin['slug']] = $plugin;
            }
            
            // Scan plugin directory
            $pluginDirs = glob($this->pluginDir . '/*', GLOB_ONLYDIR);
            
            foreach ($pluginDirs as $dir) {
                $slug = basename($dir);
                $manifestFile = $dir . '/plugin.json';
                
                if (!file_exists($manifestFile)) {
                    continue;
                }
                
                $manifest = json_decode(file_get_contents($manifestFile), true);
                if (!$manifest) {
                    continue;
                }
                
                $plugin = [
                    'slug' => $slug,
                    'name' => $manifest['name'] ?? $slug,
                    'description' => $manifest['description'] ?? '',
                    'version' => $manifest['version'] ?? '1.0.0',
                    'author' => $manifest['author'] ?? 'Unknown',
                    'website' => $manifest['website'] ?? '',
                    'requires' => $manifest['requires'] ?? [],
                    'hooks' => $manifest['hooks'] ?? [],
                    'api_endpoints' => $manifest['api_endpoints'] ?? [],
                    'settings_schema' => $manifest['settings_schema'] ?? [],
                    'is_installed' => isset($dbPluginsMap[$slug]),
                    'is_active' => isset($dbPluginsMap[$slug]) ? (bool)$dbPluginsMap[$slug]['is_active'] : false,
                    'settings' => isset($dbPluginsMap[$slug]) ? json_decode($dbPluginsMap[$slug]['settings'], true) : [],
                    'installed_at' => isset($dbPluginsMap[$slug]) ? $dbPluginsMap[$slug]['installed_at'] : null,
                    'updated_at' => isset($dbPluginsMap[$slug]) ? $dbPluginsMap[$slug]['updated_at'] : null
                ];
                
                $plugins[] = $plugin;
            }
            
            return $this->success($plugins);
            
        } catch (Exception $e) {
            return $this->error('Failed to list plugins: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get plugin details
     */
    public function get($slug) {
        try {
            if (!$this->auth->hasPermission('admin.plugins')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $pluginDir = $this->pluginDir . '/' . $slug;
            $manifestFile = $pluginDir . '/plugin.json';
            
            if (!is_dir($pluginDir) || !file_exists($manifestFile)) {
                return $this->error('Plugin not found', 404);
            }
            
            $manifest = json_decode(file_get_contents($manifestFile), true);
            if (!$manifest) {
                return $this->error('Invalid plugin manifest', 400);
            }
            
            // Get plugin from database
            $dbPlugin = $this->db->query("SELECT * FROM plugins WHERE slug = ?", [$slug])->fetch();
            
            $plugin = [
                'slug' => $slug,
                'name' => $manifest['name'] ?? $slug,
                'description' => $manifest['description'] ?? '',
                'version' => $manifest['version'] ?? '1.0.0',
                'author' => $manifest['author'] ?? 'Unknown',
                'website' => $manifest['website'] ?? '',
                'license' => $manifest['license'] ?? '',
                'requires' => $manifest['requires'] ?? [],
                'hooks' => $manifest['hooks'] ?? [],
                'api_endpoints' => $manifest['api_endpoints'] ?? [],
                'settings_schema' => $manifest['settings_schema'] ?? [],
                'screenshots' => $manifest['screenshots'] ?? [],
                'changelog' => $manifest['changelog'] ?? [],
                'is_installed' => (bool)$dbPlugin,
                'is_active' => $dbPlugin ? (bool)$dbPlugin['is_active'] : false,
                'settings' => $dbPlugin ? json_decode($dbPlugin['settings'], true) : [],
                'installed_at' => $dbPlugin['installed_at'] ?? null,
                'updated_at' => $dbPlugin['updated_at'] ?? null
            ];
            
            // Check for README file
            $readmeFile = $pluginDir . '/README.md';
            if (file_exists($readmeFile)) {
                $plugin['readme'] = file_get_contents($readmeFile);
            }
            
            return $this->success($plugin);
            
        } catch (Exception $e) {
            return $this->error('Failed to get plugin: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Install a plugin
     */
    public function install($slug) {
        try {
            if (!$this->auth->hasPermission('admin.plugins')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $pluginDir = $this->pluginDir . '/' . $slug;
            $manifestFile = $pluginDir . '/plugin.json';
            
            if (!is_dir($pluginDir) || !file_exists($manifestFile)) {
                return $this->error('Plugin not found', 404);
            }
            
            $manifest = json_decode(file_get_contents($manifestFile), true);
            if (!$manifest) {
                return $this->error('Invalid plugin manifest', 400);
            }
            
            // Check if already installed
            $existing = $this->db->query("SELECT id FROM plugins WHERE slug = ?", [$slug])->fetch();
            if ($existing) {
                return $this->error('Plugin already installed', 400);
            }
            
            // Validate requirements
            $requirements = $manifest['requires'] ?? [];
            foreach ($requirements as $requirement => $version) {
                if (!$this->checkRequirement($requirement, $version)) {
                    return $this->error("Requirement not met: $requirement $version", 400);
                }
            }
            
            $this->db->beginTransaction();
            
            // Install plugin in database
            $this->db->query("
                INSERT INTO plugins (slug, name, version, settings, is_active, installed_at, updated_at) 
                VALUES (?, ?, ?, ?, 0, NOW(), NOW())
            ", [
                $slug,
                $manifest['name'] ?? $slug,
                $manifest['version'] ?? '1.0.0',
                json_encode([])
            ]);
            
            // Run install hook if exists
            $installFile = $pluginDir . '/install.php';
            if (file_exists($installFile)) {
                include $installFile;
                if (function_exists($slug . '_install')) {
                    call_user_func($slug . '_install', $this->db);
                }
            }
            
            $this->db->commit();
            
            // Log plugin installation
            $this->db->query("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())", 
                [$this->auth->getCurrentUser()['id'], 'plugin.install', json_encode(['slug' => $slug]), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            return $this->success(['message' => 'Plugin installed successfully']);
            
        } catch (Exception $e) {
            $this->db->rollback();
            return $this->error('Failed to install plugin: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Uninstall a plugin
     */
    public function uninstall($slug) {
        try {
            if (!$this->auth->hasPermission('admin.plugins')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $plugin = $this->db->query("SELECT * FROM plugins WHERE slug = ?", [$slug])->fetch();
            if (!$plugin) {
                return $this->error('Plugin not installed', 404);
            }
            
            $pluginDir = $this->pluginDir . '/' . $slug;
            
            $this->db->beginTransaction();
            
            // Run uninstall hook if exists
            $uninstallFile = $pluginDir . '/uninstall.php';
            if (file_exists($uninstallFile)) {
                include $uninstallFile;
                if (function_exists($slug . '_uninstall')) {
                    call_user_func($slug . '_uninstall', $this->db);
                }
            }
            
            // Remove from database
            $this->db->query("DELETE FROM plugins WHERE slug = ?", [$slug]);
            
            $this->db->commit();
            
            // Log plugin uninstallation
            $this->db->query("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())", 
                [$this->auth->getCurrentUser()['id'], 'plugin.uninstall', json_encode(['slug' => $slug]), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            return $this->success(['message' => 'Plugin uninstalled successfully']);
            
        } catch (Exception $e) {
            $this->db->rollback();
            return $this->error('Failed to uninstall plugin: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Activate a plugin
     */
    public function activate($slug) {
        try {
            if (!$this->auth->hasPermission('admin.plugins')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $plugin = $this->db->query("SELECT * FROM plugins WHERE slug = ?", [$slug])->fetch();
            if (!$plugin) {
                return $this->error('Plugin not installed', 404);
            }
            
            if ($plugin['is_active']) {
                return $this->error('Plugin already active', 400);
            }
            
            $pluginDir = $this->pluginDir . '/' . $slug;
            
            // Run activation hook if exists
            $activateFile = $pluginDir . '/activate.php';
            if (file_exists($activateFile)) {
                include $activateFile;
                if (function_exists($slug . '_activate')) {
                    call_user_func($slug . '_activate', $this->db);
                }
            }
            
            // Update database
            $this->db->query("UPDATE plugins SET is_active = 1, updated_at = NOW() WHERE slug = ?", [$slug]);
            
            // Log plugin activation
            $this->db->query("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())", 
                [$this->auth->getCurrentUser()['id'], 'plugin.activate', json_encode(['slug' => $slug]), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            return $this->success(['message' => 'Plugin activated successfully']);
            
        } catch (Exception $e) {
            return $this->error('Failed to activate plugin: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Deactivate a plugin
     */
    public function deactivate($slug) {
        try {
            if (!$this->auth->hasPermission('admin.plugins')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $plugin = $this->db->query("SELECT * FROM plugins WHERE slug = ?", [$slug])->fetch();
            if (!$plugin) {
                return $this->error('Plugin not installed', 404);
            }
            
            if (!$plugin['is_active']) {
                return $this->error('Plugin already inactive', 400);
            }
            
            $pluginDir = $this->pluginDir . '/' . $slug;
            
            // Run deactivation hook if exists
            $deactivateFile = $pluginDir . '/deactivate.php';
            if (file_exists($deactivateFile)) {
                include $deactivateFile;
                if (function_exists($slug . '_deactivate')) {
                    call_user_func($slug . '_deactivate', $this->db);
                }
            }
            
            // Update database
            $this->db->query("UPDATE plugins SET is_active = 0, updated_at = NOW() WHERE slug = ?", [$slug]);
            
            // Log plugin deactivation
            $this->db->query("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())", 
                [$this->auth->getCurrentUser()['id'], 'plugin.deactivate', json_encode(['slug' => $slug]), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            return $this->success(['message' => 'Plugin deactivated successfully']);
            
        } catch (Exception $e) {
            return $this->error('Failed to deactivate plugin: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update plugin settings
     */
    public function updateSettings($slug) {
        try {
            if (!$this->auth->hasPermission('admin.plugins')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $plugin = $this->db->query("SELECT * FROM plugins WHERE slug = ?", [$slug])->fetch();
            if (!$plugin) {
                return $this->error('Plugin not installed', 404);
            }
            
            $data = $this->getJsonInput();
            if ($data === null) {
                return $this->error('Invalid JSON data', 400);
            }
            
            // Validate settings against schema if available
            $pluginDir = $this->pluginDir . '/' . $slug;
            $manifestFile = $pluginDir . '/plugin.json';
            
            if (file_exists($manifestFile)) {
                $manifest = json_decode(file_get_contents($manifestFile), true);
                if ($manifest && isset($manifest['settings_schema'])) {
                    $validation = $this->validateSettings($data, $manifest['settings_schema']);
                    if (!$validation['valid']) {
                        return $this->error('Invalid settings: ' . implode(', ', $validation['errors']), 400);
                    }
                }
            }
            
            // Update settings
            $this->db->query("UPDATE plugins SET settings = ?, updated_at = NOW() WHERE slug = ?", 
                [json_encode($data), $slug]);
            
            // Log settings update
            $this->db->query("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())", 
                [$this->auth->getCurrentUser()['id'], 'plugin.settings.update', json_encode(['slug' => $slug, 'settings' => $data]), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            return $this->success(['message' => 'Plugin settings updated successfully']);
            
        } catch (Exception $e) {
            return $this->error('Failed to update plugin settings: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get plugin API endpoints
     */
    public function getApiEndpoints($slug) {
        try {
            $plugin = $this->db->query("SELECT * FROM plugins WHERE slug = ? AND is_active = 1", [$slug])->fetch();
            if (!$plugin) {
                return $this->error('Plugin not found or inactive', 404);
            }
            
            $pluginDir = $this->pluginDir . '/' . $slug;
            $manifestFile = $pluginDir . '/plugin.json';
            
            if (!file_exists($manifestFile)) {
                return $this->error('Plugin manifest not found', 404);
            }
            
            $manifest = json_decode(file_get_contents($manifestFile), true);
            $endpoints = $manifest['api_endpoints'] ?? [];
            
            return $this->success($endpoints);
            
        } catch (Exception $e) {
            return $this->error('Failed to get plugin API endpoints: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Execute plugin API endpoint
     */
    public function executeApi($slug, $endpoint, $method = 'GET') {
        try {
            $plugin = $this->db->query("SELECT * FROM plugins WHERE slug = ? AND is_active = 1", [$slug])->fetch();
            if (!$plugin) {
                return $this->error('Plugin not found or inactive', 404);
            }
            
            $pluginDir = $this->pluginDir . '/' . $slug;
            $apiFile = $pluginDir . '/api.php';
            
            if (!file_exists($apiFile)) {
                return $this->error('Plugin API not found', 404);
            }
            
            // Set up plugin context
            $pluginContext = [
                'db' => $this->db,
                'auth' => $this->auth,
                'settings' => json_decode($plugin['settings'], true),
                'plugin_dir' => $pluginDir,
                'method' => $method,
                'endpoint' => $endpoint,
                'input' => $this->getJsonInput()
            ];
            
            // Include plugin API
            include $apiFile;
            
            // Call plugin API function
            $functionName = $slug . '_api_' . str_replace('-', '_', $endpoint);
            if (function_exists($functionName)) {
                $result = call_user_func($functionName, $pluginContext);
                return $this->success($result);
            } else {
                return $this->error('API endpoint not found', 404);
            }
            
        } catch (Exception $e) {
            return $this->error('Failed to execute plugin API: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Check if requirement is met
     */
    private function checkRequirement($requirement, $version) {
        switch ($requirement) {
            case 'php':
                return version_compare(PHP_VERSION, $version, '>=');
            case 'extension':
                return extension_loaded($version);
            default:
                return true;
        }
    }
    
    /**
     * Validate settings against schema
     */
    private function validateSettings($data, $schema) {
        $errors = [];
        
        foreach ($schema as $field => $rules) {
            $value = $data[$field] ?? null;
            
            // Required check
            if (isset($rules['required']) && $rules['required'] && ($value === null || $value === '')) {
                $errors[] = "Field '$field' is required";
                continue;
            }
            
            // Skip validation if field is not provided and not required
            if ($value === null || $value === '') {
                continue;
            }
            
            // Type validation
            if (isset($rules['type'])) {
                $valid = false;
                switch ($rules['type']) {
                    case 'string':
                        $valid = is_string($value);
                        break;
                    case 'integer':
                        $valid = is_int($value);
                        break;
                    case 'boolean':
                        $valid = is_bool($value);
                        break;
                    case 'array':
                        $valid = is_array($value);
                        break;
                }
                
                if (!$valid) {
                    $errors[] = "Field '$field' must be of type {$rules['type']}";
                }
            }
            
            // Min/max validation for strings and numbers
            if (isset($rules['min']) && (is_string($value) && strlen($value) < $rules['min']) || (is_numeric($value) && $value < $rules['min'])) {
                $errors[] = "Field '$field' must be at least {$rules['min']}";
            }
            
            if (isset($rules['max']) && (is_string($value) && strlen($value) > $rules['max']) || (is_numeric($value) && $value > $rules['max'])) {
                $errors[] = "Field '$field' must be at most {$rules['max']}";
            }
            
            // Enum validation
            if (isset($rules['enum']) && !in_array($value, $rules['enum'])) {
                $errors[] = "Field '$field' must be one of: " . implode(', ', $rules['enum']);
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
