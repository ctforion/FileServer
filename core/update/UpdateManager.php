<?php

namespace Core\Update;

use Core\Database\Database;
use Core\Utils\EnvLoader;

/**
 * Auto-Update System with GitHub Integration
 * Handles version checking, downloading, backup, and rollback functionality
 */
class UpdateManager
{
    private $db;
    private $config;
    private $currentVersion;
    private $githubRepo;
    private $githubToken;
    private $updateDir;
    private $backupDir;
    
    public function __construct()
    {
        $this->db = new Database();
        $this->config = EnvLoader::getEnv();
        $this->currentVersion = $this->config['APP_VERSION'] ?? '1.0.0';
        $this->githubRepo = $this->config['GITHUB_REPO'] ?? 'owner/repo';
        $this->githubToken = $this->config['GITHUB_TOKEN'] ?? '';
        $this->updateDir = dirname(__DIR__, 2) . '/tmp/updates';
        $this->backupDir = dirname(__DIR__, 2) . '/backups';
        
        $this->ensureDirectories();
    }
    
    /**
     * Check for available updates from GitHub
     */
    public function checkForUpdates()
    {
        try {
            $latestRelease = $this->getLatestRelease();
            
            if (!$latestRelease) {
                return [
                    'success' => false,
                    'message' => 'Unable to fetch release information'
                ];
            }
            
            $latestVersion = ltrim($latestRelease['tag_name'], 'v');
            $updateAvailable = version_compare($latestVersion, $this->currentVersion, '>');
            
            return [
                'success' => true,
                'update_available' => $updateAvailable,
                'current_version' => $this->currentVersion,
                'latest_version' => $latestVersion,
                'release_info' => $latestRelease
            ];
            
        } catch (Exception $e) {
            error_log("Update check failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Update check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Download and install update
     */
    public function performUpdate($version = null)
    {
        try {
            // Create backup before update
            $backupId = $this->createBackup();
            if (!$backupId) {
                throw new Exception('Failed to create backup');
            }
            
            // Get release information
            $release = $version ? $this->getRelease($version) : $this->getLatestRelease();
            if (!$release) {
                throw new Exception('Release not found');
            }
            
            // Download update
            $downloadPath = $this->downloadUpdate($release);
            if (!$downloadPath) {
                throw new Exception('Failed to download update');
            }
            
            // Validate downloaded file
            if (!$this->validateUpdate($downloadPath, $release)) {
                throw new Exception('Update validation failed');
            }
            
            // Extract and prepare update
            $extractPath = $this->extractUpdate($downloadPath);
            if (!$extractPath) {
                throw new Exception('Failed to extract update');
            }
            
            // Apply update
            if (!$this->applyUpdate($extractPath)) {
                // Rollback on failure
                $this->rollback($backupId);
                throw new Exception('Failed to apply update');
            }
            
            // Run post-update tasks
            $this->runPostUpdateTasks($release);
            
            // Clean up
            $this->cleanup($downloadPath, $extractPath);
            
            // Log successful update
            $this->logUpdate($release, $backupId, true);
            
            return [
                'success' => true,
                'message' => 'Update completed successfully',
                'version' => ltrim($release['tag_name'], 'v'),
                'backup_id' => $backupId
            ];
            
        } catch (Exception $e) {
            error_log("Update failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get latest release from GitHub
     */
    private function getLatestRelease()
    {
        $url = "https://api.github.com/repos/{$this->githubRepo}/releases/latest";
        return $this->githubApiRequest($url);
    }
    
    /**
     * Get specific release from GitHub
     */
    private function getRelease($version)
    {
        $url = "https://api.github.com/repos/{$this->githubRepo}/releases/tags/v{$version}";
        return $this->githubApiRequest($url);
    }
    
    /**
     * Make GitHub API request
     */
    private function githubApiRequest($url)
    {
        $headers = [
            'User-Agent: FileServer-Update-Manager',
            'Accept: application/vnd.github.v3+json'
        ];
        
        if ($this->githubToken) {
            $headers[] = "Authorization: token {$this->githubToken}";
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    /**
     * Download update file
     */
    private function downloadUpdate($release)
    {
        // Find source code archive
        $downloadUrl = null;
        foreach ($release['assets'] as $asset) {
            if (strpos($asset['name'], 'source') !== false || 
                strpos($asset['name'], '.zip') !== false) {
                $downloadUrl = $asset['browser_download_url'];
                break;
            }
        }
        
        // Fallback to zipball
        if (!$downloadUrl) {
            $downloadUrl = $release['zipball_url'];
        }
        
        $fileName = 'update_' . time() . '.zip';
        $filePath = $this->updateDir . '/' . $fileName;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $downloadUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        if ($this->githubToken) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: token {$this->githubToken}"
            ]);
        }
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $data) {
            file_put_contents($filePath, $data);
            return $filePath;
        }
        
        return false;
    }
    
    /**
     * Validate downloaded update
     */
    private function validateUpdate($filePath, $release)
    {
        // Check file exists and is readable
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }
        
        // Check file size
        if (filesize($filePath) < 1024) {
            return false;
        }
        
        // Check if it's a valid ZIP file
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== TRUE) {
            return false;
        }
        $zip->close();
        
        return true;
    }
    
    /**
     * Extract update archive
     */
    private function extractUpdate($filePath)
    {
        $extractPath = $this->updateDir . '/extract_' . time();
        
        $zip = new ZipArchive();
        if ($zip->open($filePath) === TRUE) {
            $zip->extractTo($extractPath);
            $zip->close();
            
            // Find the actual source directory (GitHub adds repo name)
            $contents = scandir($extractPath);
            foreach ($contents as $item) {
                if ($item !== '.' && $item !== '..' && is_dir($extractPath . '/' . $item)) {
                    return $extractPath . '/' . $item;
                }
            }
            
            return $extractPath;
        }
        
        return false;
    }
    
    /**
     * Apply update to application
     */
    private function applyUpdate($sourcePath)
    {
        $appRoot = dirname(__DIR__, 2);
        
        // List of critical files that should not be overwritten
        $protectedFiles = [
            '.env',
            'data/',
            'backups/',
            'uploads/',
            'cache/',
            'logs/'
        ];
        
        return $this->copyDirectory($sourcePath, $appRoot, $protectedFiles);
    }
    
    /**
     * Copy directory recursively with protection
     */
    private function copyDirectory($source, $dest, $protected = [])
    {
        if (!is_dir($source)) {
            return false;
        }
        
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            
            // Check if file is protected
            $isProtected = false;
            foreach ($protected as $protectedPath) {
                if (strpos($relativePath, $protectedPath) === 0) {
                    $isProtected = true;
                    break;
                }
            }
            
            if ($isProtected) {
                continue;
            }
            
            $destPath = $dest . DIRECTORY_SEPARATOR . $relativePath;
            
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                // Backup existing file if it exists
                if (file_exists($destPath)) {
                    copy($destPath, $destPath . '.bak');
                }
                
                if (!copy($item->getPathname(), $destPath)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Create backup of current application
     */
    private function createBackup()
    {
        $backupId = 'backup_' . date('Y-m-d_H-i-s') . '_' . $this->currentVersion;
        $backupPath = $this->backupDir . '/' . $backupId;
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        
        $appRoot = dirname(__DIR__, 2);
        
        if ($this->copyDirectory($appRoot, $backupPath)) {
            // Store backup info in database
            $this->db->insert('backups', [
                'backup_id' => $backupId,
                'version' => $this->currentVersion,
                'backup_path' => $backupPath,
                'created_at' => date('Y-m-d H:i:s'),
                'type' => 'pre_update'
            ]);
            
            return $backupId;
        }
        
        return false;
    }
    
    /**
     * Rollback to previous backup
     */
    public function rollback($backupId)
    {
        try {
            $backup = $this->db->query(
                "SELECT * FROM backups WHERE backup_id = ? AND type = 'pre_update'",
                [$backupId]
            )->fetch();
            
            if (!$backup || !is_dir($backup['backup_path'])) {
                throw new Exception('Backup not found');
            }
            
            $appRoot = dirname(__DIR__, 2);
            
            if (!$this->copyDirectory($backup['backup_path'], $appRoot)) {
                throw new Exception('Failed to restore from backup');
            }
            
            // Log rollback
            $this->db->insert('update_log', [
                'version_from' => $this->currentVersion,
                'version_to' => $backup['version'],
                'backup_id' => $backupId,
                'success' => 1,
                'type' => 'rollback',
                'performed_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'message' => 'Rollback completed successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Rollback failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Rollback failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Run post-update tasks
     */
    private function runPostUpdateTasks($release)
    {
        // Update version in .env file
        $this->updateVersion(ltrim($release['tag_name'], 'v'));
        
        // Run database migrations if needed
        $migration = new \Core\Database\Migration();
        $migration->runMigrations();
        
        // Clear caches
        $this->clearCaches();
        
        // Update .htaccess if needed
        $this->updateHtaccess();
        
        // Send notification to admin
        $this->notifyAdmins($release);
    }
    
    /**
     * Update version in environment file
     */
    private function updateVersion($version)
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            $content = preg_replace(
                '/^APP_VERSION=.*/m',
                "APP_VERSION={$version}",
                $content
            );
            file_put_contents($envFile, $content);
        }
    }
    
    /**
     * Clear application caches
     */
    private function clearCaches()
    {
        $cacheDir = dirname(__DIR__, 2) . '/cache';
        
        if (is_dir($cacheDir)) {
            $this->deleteDirectory($cacheDir);
            mkdir($cacheDir, 0755, true);
        }
    }
    
    /**
     * Update .htaccess file if needed
     */
    private function updateHtaccess()
    {
        $htaccessPath = dirname(__DIR__, 2) . '/.htaccess';
        $htaccessTemplate = dirname(__DIR__, 2) . '/htaccess.template';
        
        if (file_exists($htaccessTemplate)) {
            copy($htaccessTemplate, $htaccessPath);
        }
    }
    
    /**
     * Notify administrators about update
     */
    private function notifyAdmins($release)
    {
        // This would integrate with the email system
        // For now, just log the update
        error_log("Update completed: " . $release['tag_name']);
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanup($downloadPath, $extractPath)
    {
        if (file_exists($downloadPath)) {
            unlink($downloadPath);
        }
        
        if (is_dir($extractPath)) {
            $this->deleteDirectory($extractPath);
        }
    }
    
    /**
     * Delete directory recursively
     */
    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
    
    /**
     * Log update operation
     */
    private function logUpdate($release, $backupId, $success)
    {
        $this->db->insert('update_log', [
            'version_from' => $this->currentVersion,
            'version_to' => ltrim($release['tag_name'], 'v'),
            'backup_id' => $backupId,
            'success' => $success ? 1 : 0,
            'type' => 'update',
            'performed_at' => date('Y-m-d H:i:s'),
            'release_notes' => $release['body'] ?? ''
        ]);
    }
    
    /**
     * Get update history
     */
    public function getUpdateHistory($limit = 50)
    {
        return $this->db->query(
            "SELECT * FROM update_log ORDER BY performed_at DESC LIMIT ?",
            [$limit]
        )->fetchAll();
    }
    
    /**
     * Get available backups
     */
    public function getBackups()
    {
        return $this->db->query(
            "SELECT * FROM backups ORDER BY created_at DESC"
        )->fetchAll();
    }
    
    /**
     * Ensure required directories exist
     */
    private function ensureDirectories()
    {
        $dirs = [$this->updateDir, $this->backupDir];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Get system information
     */
    public function getSystemInfo()
    {
        return [
            'current_version' => $this->currentVersion,
            'php_version' => PHP_VERSION,
            'last_check' => $this->getLastUpdateCheck(),
            'auto_update_enabled' => $this->config['AUTO_UPDATE_ENABLED'] ?? false,
            'github_repo' => $this->githubRepo,
            'backup_count' => $this->db->query("SELECT COUNT(*) as count FROM backups")->fetch()['count']
        ];
    }
    
    /**
     * Get last update check time
     */
    private function getLastUpdateCheck()
    {
        $setting = $this->db->query(
            "SELECT value FROM settings WHERE key = 'last_update_check'"
        )->fetch();
        
        return $setting ? $setting['value'] : null;
    }
    
    /**
     * Set last update check time
     */
    public function setLastUpdateCheck()
    {
        $this->db->query(
            "INSERT OR REPLACE INTO settings (key, value) VALUES ('last_update_check', ?)",
            [date('Y-m-d H:i:s')]
        );
    }
}
