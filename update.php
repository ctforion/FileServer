<?php
/**
 * Portable PHP File Storage Server - Auto Update System
 * 
 * This file handles automatic updates from GitHub
 * Access: https://0xAhmadYousuf.com/FileServer/update.php
 */

// Load configuration
require_once __DIR__ . '/config.php';

// Security check - only allow admin access
session_start();

// Simple authentication for update access
$UPDATE_KEY = 'your-secure-update-key-change-this'; // Change this!

// Check authentication
$authenticated = false;

if (isset($_GET['key']) && $_GET['key'] === $UPDATE_KEY) {
    $authenticated = true;
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $authenticated = true;
} else {
    // Show authentication form
    if ($_POST['update_key'] ?? '' === $UPDATE_KEY) {
        $authenticated = true;
    }
}

if (!$authenticated && !isset($_POST['update_key'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>FileServer Update</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 500px; margin: 100px auto; padding: 20px; }
            .form-group { margin: 15px 0; }
            input[type="password"] { width: 100%; padding: 10px; margin: 5px 0; }
            button { background: #007cba; color: white; padding: 10px 20px; border: none; cursor: pointer; }
            button:hover { background: #005a87; }
        </style>
    </head>
    <body>
        <h2>FileServer Update Access</h2>
        <form method="post">
            <div class="form-group">
                <label>Update Key:</label>
                <input type="password" name="update_key" required>
            </div>
            <button type="submit">Access Update System</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

if (!$authenticated) {
    die('Access denied. Invalid update key.');
}

// Update system
class AutoUpdater {
    private $repoUrl = 'https://api.github.com/repos/ctforion/FileServer';
    private $backupDir;
    private $tempDir;
    
    public function __construct() {
        $this->backupDir = __DIR__ . '/backups/' . date('Y-m-d_H-i-s');
        $this->tempDir = sys_get_temp_dir() . '/fileserver_update_' . time();
    }
    
    public function checkForUpdates() {
        $current = APP_VERSION;
        $latest = $this->getLatestVersion();
        
        return [
            'current' => $current,
            'latest' => $latest,
            'available' => version_compare($latest, $current, '>')
        ];
    }
    
    private function getLatestVersion() {
        $context = stream_context_create([
            'http' => [
                'header' => 'User-Agent: FileServer-Updater/1.0'
            ]
        ]);
        
        $response = @file_get_contents($this->repoUrl . '/releases/latest', false, $context);
        if (!$response) {
            throw new Exception('Failed to fetch latest version info');
        }
        
        $data = json_decode($response, true);
        return $data['tag_name'] ?? APP_VERSION;
    }
    
    public function performUpdate() {
        try {
            $this->log('Starting update process...');
            
            // Create backup
            $this->createBackup();
            
            // Download latest version
            $this->downloadLatest();
            
            // Extract and replace files
            $this->extractAndReplace();
            
            // Cleanup
            $this->cleanup();
            
            $this->log('Update completed successfully!');
            return true;
            
        } catch (Exception $e) {
            $this->log('Update failed: ' . $e->getMessage());
            $this->rollback();
            throw $e;
        }
    }
    
    private function createBackup() {
        $this->log('Creating backup...');
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        
        // Backup important files
        $important = ['config.php', 'source/storage', 'logs'];
        
        foreach ($important as $item) {
            $source = __DIR__ . '/' . $item;
            $dest = $this->backupDir . '/' . $item;
            
            if (file_exists($source)) {
                if (is_dir($source)) {
                    $this->copyDir($source, $dest);
                } else {
                    $destDir = dirname($dest);
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    copy($source, $dest);
                }
            }
        }
        
        $this->log('Backup created at: ' . $this->backupDir);
    }
    
    private function downloadLatest() {
        $this->log('Downloading latest version...');
        
        $zipUrl = 'https://github.com/ctforion/FileServer/archive/refs/heads/main.zip';
        $zipFile = $this->tempDir . '/latest.zip';
        
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
        
        $context = stream_context_create([
            'http' => [
                'header' => 'User-Agent: FileServer-Updater/1.0'
            ]
        ]);
        
        $zipContent = file_get_contents($zipUrl, false, $context);
        if (!$zipContent) {
            throw new Exception('Failed to download update package');
        }
        
        file_put_contents($zipFile, $zipContent);
        $this->log('Download completed');
    }
    
    private function extractAndReplace() {
        $this->log('Extracting and replacing files...');
        
        $zipFile = $this->tempDir . '/latest.zip';
        $extractDir = $this->tempDir . '/extract';
        
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== TRUE) {
            throw new Exception('Failed to open update package');
        }
        
        $zip->extractTo($extractDir);
        $zip->close();
        
        // Find the extracted directory (usually FileServer-main)
        $dirs = glob($extractDir . '/*', GLOB_ONLYDIR);
        if (empty($dirs)) {
            throw new Exception('Invalid update package structure');
        }
        
        $sourceDir = $dirs[0];
        
        // Copy files (exclude storage and config.php)
        $this->copyUpdateFiles($sourceDir, __DIR__);
        
        $this->log('Files replaced successfully');
    }
    
    private function copyUpdateFiles($source, $dest) {
        $exclude = ['source/storage', 'config.php', 'logs', 'backups'];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            
            // Skip excluded paths
            $skip = false;
            foreach ($exclude as $excludePath) {
                if (strpos($relativePath, $excludePath) === 0) {
                    $skip = true;
                    break;
                }
            }
            
            if ($skip) continue;
            
            $destPath = $dest . '/' . $relativePath;
            
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                copy($item->getPathname(), $destPath);
            }
        }
    }
    
    private function copyDir($source, $dest) {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $destPath = $dest . '/' . substr($item->getPathname(), strlen($source) + 1);
            
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $destPath);
            }
        }
    }
    
    private function cleanup() {
        if (is_dir($this->tempDir)) {
            $this->removeDir($this->tempDir);
        }
    }
    
    private function removeDir($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    private function rollback() {
        // Implementation for rollback if needed
        $this->log('Rollback functionality would be implemented here');
    }
    
    private function log($message) {
        $logFile = __DIR__ . '/logs/update.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
        
        // Also output to browser
        echo "[{$timestamp}] {$message}<br>\n";
        if (ob_get_level()) ob_flush();
        flush();
    }
}

// Handle actions
$action = $_GET['action'] ?? 'check';
$updater = new AutoUpdater();

if ($action === 'update') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre>';
    try {
        $updater->performUpdate();
        echo "\n<strong>Update completed successfully!</strong>\n";
        echo '<a href="' . url() . '">Return to FileServer</a>';
    } catch (Exception $e) {
        echo "\n<strong>Update failed: " . htmlspecialchars($e->getMessage()) . "</strong>\n";
    }
    echo '</pre>';
    exit;
}

// Default: Show update interface
$updateInfo = $updater->checkForUpdates();
?>
<!DOCTYPE html>
<html>
<head>
    <title>FileServer Auto Update</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .status { padding: 15px; margin: 15px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; cursor: pointer; margin: 5px; }
        button:hover { background: #005a87; }
        .update-btn { background: #28a745; }
        .update-btn:hover { background: #218838; }
        .version { font-size: 1.2em; font-weight: bold; }
    </style>
</head>
<body>
    <h1>FileServer Auto Update System</h1>
    
    <div class="status info">
        <h3>Current Installation</h3>
        <div class="version">Version: <?= htmlspecialchars($updateInfo['current']) ?></div>
        <div>Last Updated: <?= file_exists(__DIR__ . '/last_update.txt') ? htmlspecialchars(file_get_contents(__DIR__ . '/last_update.txt')) : 'Unknown' ?></div>
    </div>
    
    <?php if ($updateInfo['available']): ?>
        <div class="status warning">
            <h3>Update Available!</h3>
            <div class="version">Latest Version: <?= htmlspecialchars($updateInfo['latest']) ?></div>
            <p>A new version of FileServer is available. Click the button below to update automatically.</p>
            <button class="update-btn" onclick="if(confirm('Are you sure you want to update? This will replace all files except your configuration and storage.')) { window.location.href='?action=update&key=<?= urlencode($UPDATE_KEY) ?>'; }">
                Update Now
            </button>
        </div>
    <?php else: ?>
        <div class="status success">
            <h3>Up to Date!</h3>
            <p>Your FileServer installation is up to date.</p>
        </div>
    <?php endif; ?>
    
    <div class="status info">
        <h3>Manual Update</h3>
        <p>You can also update manually using the install.sh script:</p>
        <code>./install.sh /path/to/FileServer update</code>
    </div>
    
    <div style="margin-top: 30px;">
        <button onclick="window.location.href='<?= url() ?>'">Return to FileServer</button>
        <button onclick="window.location.reload()">Check for Updates</button>
    </div>
    
    <hr style="margin: 30px 0;">
    <p><small>
        Update URL: <code><?= url('update.php?key=' . $UPDATE_KEY) ?></code><br>
        Repository: <a href="https://github.com/ctforion/FileServer" target="_blank">https://github.com/ctforion/FileServer</a>
    </small></p>
</body>
</html>
