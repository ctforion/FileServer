<?php
/**
 * Database Manager
 * High-level wrapper for JSON database operations
 */

require_once __DIR__ . '/JsonDatabase.php';

class DatabaseManager {
    private $db;
    private static $instance = null;
    
    private function __construct() {
        $this->db = new JsonDatabase();
        $this->initializeDatabase();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize database with default data
     */
    private function initializeDatabase() {
        // Initialize users table if it doesn't exist
        $users = $this->db->load('users');
        if (empty($users)) {
            $this->createDefaultAdmin();
        }
        
        // Initialize other tables
        $this->db->load('files');
        $this->db->load('sessions');
        $this->db->load('settings');
    }
    
    /**
     * Create default admin user
     */
    private function createDefaultAdmin() {
        $adminData = [
            'username' => 'admin',
            'email' => 'admin@fileserver.local',
            'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => 'active',
            'quota' => 1073741824, // 1GB
            'settings' => [
                'theme' => 'default',
                'language' => 'en',
                'timezone' => 'UTC'
            ],
            'last_login' => null,
            'login_count' => 0
        ];
        
        return $this->db->insert('users', 'admin', $adminData);
    }
    
    /**
     * User Management Methods
     */
    
    public function createUser($username, $data) {
        // Validate required fields
        $required = ['email', 'password'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
        
        // Check if username or email already exists
        $users = $this->db->getAll('users');
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                throw new Exception("Username already exists");
            }
            if ($user['email'] === $data['email']) {
                throw new Exception("Email already exists");
            }
        }
        
        // Prepare user data
        $userData = [
            'username' => $username,
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => $data['role'] ?? 'user',
            'status' => $data['status'] ?? 'active',
            'quota' => $data['quota'] ?? 104857600, // 100MB default
            'settings' => $data['settings'] ?? [
                'theme' => 'default',
                'language' => 'en',
                'timezone' => 'UTC'
            ],
            'last_login' => null,
            'login_count' => 0
        ];
        
        return $this->db->insert('users', $username, $userData);
    }
    
    public function getUser($username) {
        return $this->db->get('users', $username);
    }
    
    public function getAllUsers() {
        return $this->db->getAll('users');
    }
    
    public function updateUser($username, $data) {
        // Don't allow updating username or password through this method
        unset($data['username'], $data['password']);
        
        if (isset($data['password_new'])) {
            $data['password_hash'] = password_hash($data['password_new'], PASSWORD_DEFAULT);
            unset($data['password_new']);
        }
        
        return $this->db->update('users', $username, $data);
    }
    
    public function deleteUser($username) {
        if ($username === 'admin') {
            throw new Exception("Cannot delete admin user");
        }
        
        return $this->db->delete('users', $username);
    }
    
    public function authenticateUser($username, $password) {
        $user = $this->getUser($username);
        
        if (!$user || $user['status'] !== 'active') {
            return false;
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }
        
        // Update login statistics
        $this->updateUser($username, [
            'last_login' => date('c'),
            'login_count' => ($user['login_count'] ?? 0) + 1
        ]);
        
        return $user;
    }
    
    /**
     * Session Management Methods
     */
    
    public function createSession($username, $sessionData = []) {
        $sessionId = bin2hex(random_bytes(32));
        
        $data = array_merge([
            'username' => $username,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'expires' => date('c', time() + 86400), // 24 hours
            'active' => true
        ], $sessionData);
        
        $this->db->insert('sessions', $sessionId, $data);
        
        return $sessionId;
    }
    
    public function getSession($sessionId) {
        $session = $this->db->get('sessions', $sessionId);
        
        if (!$session) {
            return null;
        }
        
        // Check if session is expired
        if (strtotime($session['expires']) < time()) {
            $this->deleteSession($sessionId);
            return null;
        }
        
        return $session;
    }
    
    public function updateSession($sessionId, $data) {
        return $this->db->update('sessions', $sessionId, $data);
    }
    
    public function deleteSession($sessionId) {
        return $this->db->delete('sessions', $sessionId);
    }
    
    public function getUserSessions($username) {
        return $this->db->search('sessions', ['username' => $username, 'active' => true]);
    }
    
    public function cleanupExpiredSessions() {
        $sessions = $this->db->getAll('sessions');
        $cleaned = 0;
        
        foreach ($sessions as $sessionId => $session) {
            if (strtotime($session['expires']) < time()) {
                $this->deleteSession($sessionId);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * File Management Methods
     */
     
    public function getFiles() {
        return ['files' => $this->db->getAll('files')];
    }
    
    public function addFile($fileId, $data) {
        return $this->registerFile($fileId, $data);
    }

    public function registerFile($fileId, $data) {
        $required = ['filename', 'path', 'owner', 'size'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
        
        $fileData = array_merge([
            'mime_type' => 'application/octet-stream',
            'directory' => 'public',
            'permissions' => [$data['owner']],
            'downloads' => 0,
            'metadata' => []
        ], $data);
        
        return $this->db->insert('files', $fileId, $fileData);
    }
    
    public function getFile($fileId) {
        return $this->db->get('files', $fileId);
    }
    
    public function getFileByPath($path) {
        $files = $this->db->search('files', ['path' => $path]);
        return !empty($files) ? reset($files) : null;
    }
    
    public function getUserFiles($username) {
        return $this->db->search('files', ['owner' => $username]);
    }
    
    public function updateFile($fileId, $data) {
        return $this->db->update('files', $fileId, $data);
    }
    
    public function deleteFile($fileId) {
        return $this->db->delete('files', $fileId);
    }
    
    public function incrementDownloadCount($fileId) {
        $file = $this->getFile($fileId);
        if ($file) {
            $this->updateFile($fileId, [
                'downloads' => ($file['downloads'] ?? 0) + 1,
                'last_downloaded' => date('c')
            ]);
        }
    }
    
    /**
     * Settings Management Methods
     */
    
    public function getSetting($key, $default = null) {
        $settings = $this->db->load('settings');
        return $settings[$key] ?? $default;
    }
    
    public function setSetting($key, $value) {
        $settings = $this->db->load('settings');
        $settings[$key] = $value;
        return $this->db->save('settings', $settings);
    }
    
    public function getAllSettings() {
        return $this->db->load('settings');
    }
    
    /**
     * Convenience methods for getting all records
     */
    
    public function getUsers() {
        return ['users' => $this->db->getAll('users')];
    }
    
    public function getSessions() {
        return ['sessions' => $this->db->getAll('sessions')];
    }
    
    public function getSettings() {
        return $this->db->getAll('settings');
    }
    
    /**
     * Statistics and Analytics
     */
    
    public function getSystemStats() {
        $stats = [
            'users' => [
                'total' => $this->db->count('users'),
                'active' => count($this->db->search('users', ['status' => 'active'])),
                'admins' => count($this->db->search('users', ['role' => 'admin']))
            ],
            'files' => [
                'total' => $this->db->count('files'),
                'total_size' => 0,
                'by_directory' => []
            ],
            'sessions' => [
                'active' => count($this->db->search('sessions', ['active' => true])),
                'total' => $this->db->count('sessions')
            ],
            'database' => $this->db->getStats()
        ];
        
        // Calculate file statistics
        $files = $this->db->getAll('files');
        foreach ($files as $file) {
            $stats['files']['total_size'] += $file['size'] ?? 0;
            $dir = $file['directory'] ?? 'unknown';
            $stats['files']['by_directory'][$dir] = ($stats['files']['by_directory'][$dir] ?? 0) + 1;
        }
        
        return $stats;
    }
    
    /**
     * Backup and Restore
     */
    
    public function backupDatabase($backupPath = null) {
        if ($backupPath === null) {
            $backupPath = __DIR__ . '/../../data/backups/full_backup_' . date('Y-m-d_H-i-s') . '.json';
        }
        
        $backup = [
            'timestamp' => date('c'),
            'version' => '1.0',
            'tables' => [
                'users' => $this->db->getAll('users'),
                'files' => $this->db->getAll('files'),
                'sessions' => $this->db->getAll('sessions'),
                'settings' => $this->db->getAll('settings')
            ]
        ];
        
        $backupDir = dirname($backupPath);
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        return file_put_contents($backupPath, $json) !== false;
    }
    
    public function restoreDatabase($backupPath) {
        if (!file_exists($backupPath)) {
            throw new Exception("Backup file not found");
        }
        
        $content = file_get_contents($backupPath);
        $backup = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid backup file");
        }
        
        if (!isset($backup['tables'])) {
            throw new Exception("Invalid backup format");
        }
        
        // Restore each table
        foreach ($backup['tables'] as $table => $data) {
            $this->db->save($table, $data);
        }
        
        return true;
    }
    
    /**
     * Save data to a specific table
     */
    public function saveData($table, $data) {
        return $this->db->saveAll($table, $data);
    }

    /**
     * Get database instance for advanced operations
     */
    public function getDatabase() {
        return $this->db;
    }
}
?>
