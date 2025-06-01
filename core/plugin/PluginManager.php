<?php

namespace Core\Plugin;

use Core\Database\Database;
use Core\Utils\EnvLoader;

/**
 * Plugin Manager
 * Handles plugin loading, registration, and lifecycle management
 */
class PluginManager {
    private $db;
    private $config;
    private $loadedPlugins = [];
    private $hooks = [];
    private $pluginPath;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = EnvLoader::getConfig();
        $this->pluginPath = $this->config['PLUGIN_PATH'] ?? './plugins';
        
        if (!is_dir($this->pluginPath)) {
            mkdir($this->pluginPath, 0755, true);
        }
    }

    /**
     * Load all enabled plugins
     */
    public function loadPlugins() {
        try {
            $plugins = $this->db->query(
                "SELECT * FROM plugins WHERE enabled = 1 ORDER BY priority ASC"
            );

            foreach ($plugins as $plugin) {
                $this->loadPlugin($plugin);
            }

        } catch (\Exception $e) {
            error_log("Failed to load plugins: " . $e->getMessage());
        }
    }

    /**
     * Load a specific plugin
     */
    private function loadPlugin($pluginData) {
        $pluginDir = $this->pluginPath . '/' . $pluginData['name'];
        $pluginFile = $pluginDir . '/plugin.php';

        if (!file_exists($pluginFile)) {
            error_log("Plugin file not found: {$pluginFile}");
            return false;
        }

        try {
            // Include plugin file
            require_once $pluginFile;
            
            // Get plugin class name
            $className = $this->getPluginClassName($pluginData['name']);
            
            if (!class_exists($className)) {
                error_log("Plugin class not found: {$className}");
                return false;
            }

            // Instantiate plugin
            $plugin = new $className($this);
            
            // Validate plugin interface
            if (!$plugin instanceof PluginInterface) {
                error_log("Plugin must implement PluginInterface: {$className}");
                return false;
            }

            // Initialize plugin
            $plugin->init();
            
            // Store loaded plugin
            $this->loadedPlugins[$pluginData['name']] = [
                'instance' => $plugin,
                'data' => $pluginData
            ];

            error_log("Plugin loaded successfully: {$pluginData['name']}");
            return true;

        } catch (\Exception $e) {
            error_log("Failed to load plugin {$pluginData['name']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Register a plugin
     */
    public function registerPlugin($name, $version, $description, $author, $config = []) {
        try {
            $existingPlugin = $this->db->queryRow(
                "SELECT id FROM plugins WHERE name = ?",
                [$name]
            );

            if ($existingPlugin) {
                // Update existing plugin
                $this->db->update('plugins', [
                    'version' => $version,
                    'description' => $description,
                    'author' => $author,
                    'config' => json_encode($config),
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['name' => $name]);
            } else {
                // Insert new plugin
                $this->db->insert('plugins', [
                    'name' => $name,
                    'version' => $version,
                    'description' => $description,
                    'author' => $author,
                    'config' => json_encode($config),
                    'enabled' => 0,
                    'priority' => 100,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            return true;

        } catch (\Exception $e) {
            error_log("Failed to register plugin: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enable a plugin
     */
    public function enablePlugin($pluginId) {
        try {
            $plugin = $this->db->queryRow(
                "SELECT * FROM plugins WHERE id = ?",
                [$pluginId]
            );

            if (!$plugin) {
                return ['success' => false, 'message' => 'Plugin not found'];
            }

            // Check dependencies
            $dependencyCheck = $this->checkDependencies($plugin);
            if (!$dependencyCheck['success']) {
                return $dependencyCheck;
            }

            // Enable plugin
            $this->db->update('plugins', [
                'enabled' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $pluginId]);

            // Load plugin if not already loaded
            if (!isset($this->loadedPlugins[$plugin['name']])) {
                $this->loadPlugin($plugin);
            }

            return ['success' => true, 'message' => 'Plugin enabled successfully'];

        } catch (\Exception $e) {
            error_log("Failed to enable plugin: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to enable plugin'];
        }
    }

    /**
     * Disable a plugin
     */
    public function disablePlugin($pluginId) {
        try {
            $plugin = $this->db->queryRow(
                "SELECT * FROM plugins WHERE id = ?",
                [$pluginId]
            );

            if (!$plugin) {
                return ['success' => false, 'message' => 'Plugin not found'];
            }

            // Check if other plugins depend on this one
            $dependentPlugins = $this->getDependentPlugins($plugin['name']);
            if (!empty($dependentPlugins)) {
                $names = implode(', ', array_column($dependentPlugins, 'name'));
                return [
                    'success' => false, 
                    'message' => "Cannot disable plugin. The following plugins depend on it: {$names}"
                ];
            }

            // Disable plugin
            $this->db->update('plugins', [
                'enabled' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $pluginId]);

            // Unload plugin
            if (isset($this->loadedPlugins[$plugin['name']])) {
                $pluginInstance = $this->loadedPlugins[$plugin['name']]['instance'];
                if (method_exists($pluginInstance, 'deactivate')) {
                    $pluginInstance->deactivate();
                }
                unset($this->loadedPlugins[$plugin['name']]);
            }

            return ['success' => true, 'message' => 'Plugin disabled successfully'];

        } catch (\Exception $e) {
            error_log("Failed to disable plugin: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to disable plugin'];
        }
    }

    /**
     * Install a plugin from a ZIP file
     */
    public function installPlugin($zipFile) {
        try {
            $zip = new \ZipArchive();
            $result = $zip->open($zipFile);

            if ($result !== TRUE) {
                return ['success' => false, 'message' => 'Invalid ZIP file'];
            }

            // Extract to temporary directory
            $tempDir = sys_get_temp_dir() . '/plugin_' . uniqid();
            mkdir($tempDir, 0755, true);
            
            $zip->extractTo($tempDir);
            $zip->close();

            // Read plugin manifest
            $manifestFile = $tempDir . '/manifest.json';
            if (!file_exists($manifestFile)) {
                $this->cleanup($tempDir);
                return ['success' => false, 'message' => 'Plugin manifest not found'];
            }

            $manifest = json_decode(file_get_contents($manifestFile), true);
            if (!$manifest) {
                $this->cleanup($tempDir);
                return ['success' => false, 'message' => 'Invalid plugin manifest'];
            }

            // Validate manifest
            $validation = $this->validateManifest($manifest);
            if (!$validation['success']) {
                $this->cleanup($tempDir);
                return $validation;
            }

            // Check if plugin already exists
            $existingPlugin = $this->db->queryRow(
                "SELECT id FROM plugins WHERE name = ?",
                [$manifest['name']]
            );

            if ($existingPlugin) {
                $this->cleanup($tempDir);
                return ['success' => false, 'message' => 'Plugin already installed'];
            }

            // Move plugin to plugins directory
            $pluginDir = $this->pluginPath . '/' . $manifest['name'];
            if (is_dir($pluginDir)) {
                $this->cleanup($tempDir);
                return ['success' => false, 'message' => 'Plugin directory already exists'];
            }

            rename($tempDir, $pluginDir);

            // Register plugin
            $this->registerPlugin(
                $manifest['name'],
                $manifest['version'],
                $manifest['description'],
                $manifest['author'],
                $manifest['config'] ?? []
            );

            return ['success' => true, 'message' => 'Plugin installed successfully'];

        } catch (\Exception $e) {
            error_log("Plugin installation failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Installation failed'];
        }
    }

    /**
     * Uninstall a plugin
     */
    public function uninstallPlugin($pluginId) {
        try {
            $plugin = $this->db->queryRow(
                "SELECT * FROM plugins WHERE id = ?",
                [$pluginId]
            );

            if (!$plugin) {
                return ['success' => false, 'message' => 'Plugin not found'];
            }

            // Disable plugin first
            if ($plugin['enabled']) {
                $disableResult = $this->disablePlugin($pluginId);
                if (!$disableResult['success']) {
                    return $disableResult;
                }
            }

            // Remove plugin files
            $pluginDir = $this->pluginPath . '/' . $plugin['name'];
            if (is_dir($pluginDir)) {
                $this->removeDirectory($pluginDir);
            }

            // Remove from database
            $this->db->delete('plugins', ['id' => $pluginId]);

            return ['success' => true, 'message' => 'Plugin uninstalled successfully'];

        } catch (\Exception $e) {
            error_log("Plugin uninstallation failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Uninstallation failed'];
        }
    }

    /**
     * Get all plugins
     */
    public function getPlugins() {
        try {
            return $this->db->query("SELECT * FROM plugins ORDER BY name ASC");
        } catch (\Exception $e) {
            error_log("Failed to get plugins: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get plugin by name
     */
    public function getPlugin($name) {
        try {
            return $this->db->queryRow(
                "SELECT * FROM plugins WHERE name = ?",
                [$name]
            );
        } catch (\Exception $e) {
            error_log("Failed to get plugin: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Register a hook
     */
    public function addHook($hookName, $callback, $priority = 10) {
        if (!isset($this->hooks[$hookName])) {
            $this->hooks[$hookName] = [];
        }

        $this->hooks[$hookName][] = [
            'callback' => $callback,
            'priority' => $priority
        ];

        // Sort by priority
        usort($this->hooks[$hookName], function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }

    /**
     * Execute hooks
     */
    public function doHook($hookName, $data = null) {
        if (!isset($this->hooks[$hookName])) {
            return $data;
        }

        foreach ($this->hooks[$hookName] as $hook) {
            try {
                $result = call_user_func($hook['callback'], $data);
                if ($result !== null) {
                    $data = $result;
                }
            } catch (\Exception $e) {
                error_log("Hook execution failed: " . $e->getMessage());
            }
        }

        return $data;
    }

    /**
     * Check if a hook exists
     */
    public function hasHook($hookName) {
        return isset($this->hooks[$hookName]) && !empty($this->hooks[$hookName]);
    }

    /**
     * Get loaded plugins
     */
    public function getLoadedPlugins() {
        return $this->loadedPlugins;
    }

    /**
     * Private helper methods
     */
    private function getPluginClassName($pluginName) {
        return 'Plugin' . str_replace(['_', '-'], '', ucwords($pluginName, '_-'));
    }

    private function checkDependencies($plugin) {
        $config = json_decode($plugin['config'], true) ?? [];
        $dependencies = $config['dependencies'] ?? [];

        foreach ($dependencies as $dependency) {
            $dependentPlugin = $this->db->queryRow(
                "SELECT * FROM plugins WHERE name = ? AND enabled = 1",
                [$dependency]
            );

            if (!$dependentPlugin) {
                return [
                    'success' => false,
                    'message' => "Missing dependency: {$dependency}"
                ];
            }
        }

        return ['success' => true];
    }

    private function getDependentPlugins($pluginName) {
        try {
            $plugins = $this->db->query(
                "SELECT * FROM plugins WHERE enabled = 1"
            );

            $dependentPlugins = [];
            foreach ($plugins as $plugin) {
                $config = json_decode($plugin['config'], true) ?? [];
                $dependencies = $config['dependencies'] ?? [];
                
                if (in_array($pluginName, $dependencies)) {
                    $dependentPlugins[] = $plugin;
                }
            }

            return $dependentPlugins;

        } catch (\Exception $e) {
            error_log("Failed to get dependent plugins: " . $e->getMessage());
            return [];
        }
    }

    private function validateManifest($manifest) {
        $required = ['name', 'version', 'description', 'author'];
        
        foreach ($required as $field) {
            if (!isset($manifest[$field]) || empty($manifest[$field])) {
                return [
                    'success' => false,
                    'message' => "Missing required field: {$field}"
                ];
            }
        }

        // Validate plugin name
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $manifest['name'])) {
            return [
                'success' => false,
                'message' => 'Invalid plugin name. Use only letters, numbers, hyphens, and underscores.'
            ];
        }

        return ['success' => true];
    }

    private function cleanup($directory) {
        if (is_dir($directory)) {
            $this->removeDirectory($directory);
        }
    }

    private function removeDirectory($directory) {
        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($directory);
    }
}

/**
 * Plugin Interface
 * All plugins must implement this interface
 */
interface PluginInterface {
    /**
     * Initialize the plugin
     */
    public function init();

    /**
     * Get plugin information
     */
    public function getInfo();

    /**
     * Activate the plugin
     */
    public function activate();

    /**
     * Deactivate the plugin
     */
    public function deactivate();
}

/**
 * Base Plugin Class
 * Provides common functionality for plugins
 */
abstract class BasePlugin implements PluginInterface {
    protected $pluginManager;
    protected $config = [];

    public function __construct($pluginManager) {
        $this->pluginManager = $pluginManager;
    }

    /**
     * Add a hook
     */
    protected function addHook($hookName, $method, $priority = 10) {
        $this->pluginManager->addHook($hookName, [$this, $method], $priority);
    }

    /**
     * Get plugin configuration
     */
    protected function getConfig($key = null, $default = null) {
        if ($key === null) {
            return $this->config;
        }
        
        return $this->config[$key] ?? $default;
    }

    /**
     * Set plugin configuration
     */
    protected function setConfig($key, $value) {
        $this->config[$key] = $value;
    }

    /**
     * Default activation
     */
    public function activate() {
        // Override in child classes if needed
    }

    /**
     * Default deactivation
     */
    public function deactivate() {
        // Override in child classes if needed
    }
}
