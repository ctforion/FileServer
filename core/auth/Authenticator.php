<?php

class Authenticator {
    private $db;
      public function __construct($database_path) {
        // Check if SQLite3 is available
        if (!class_exists('SQLite3')) {
            throw new Exception('SQLite3 extension is not available. Please enable it in php.ini');
        }
        
        // Ensure database directory exists
        $dbDir = dirname($database_path);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        $this->db = new SQLite3($database_path);
        $this->initDatabase();
    }
    
    private function initDatabase() {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->exec($sql);
        
        // Create default admin user if not exists
        $this->createDefaultUser();
    }
    
    private function createDefaultUser() {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
        $stmt->bindValue(1, 'admin');
        $result = $stmt->execute()->fetchArray();
        
        if ($result['count'] == 0) {
            $this->createUser('admin', 'admin123'); // Default credentials
        }
    }
    
    public function createUser($username, $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bindValue(1, $username);
        $stmt->bindValue(2, $hashedPassword);
        
        try {
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function login($username, $password) {
        $stmt = $this->db->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bindValue(1, $username);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['logged_in'] = true;
            return true;
        }
        return false;
    }
    
    public function logout() {
        session_start();
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        session_start();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username']
            ];
        }
        return null;
    }
    
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
    }
}
