<?php

class SimpleFileAuthenticator {
    private $usersFile;
    
    public function __construct($usersFilePath) {
        $this->usersFile = $usersFilePath;
        $this->initUsers();
    }
    
    private function initUsers() {
        // Ensure users directory exists
        $usersDir = dirname($this->usersFile);
        if (!is_dir($usersDir)) {
            mkdir($usersDir, 0755, true);
        }
        
        // Create default admin user if file doesn't exist
        if (!file_exists($this->usersFile)) {
            $defaultUser = [
                'admin' => [
                    'password' => password_hash('admin123', PASSWORD_DEFAULT),
                    'created' => date('Y-m-d H:i:s')
                ]
            ];
            file_put_contents($this->usersFile, json_encode($defaultUser, JSON_PRETTY_PRINT));
        }
    }
    
    private function getUsers() {
        if (!file_exists($this->usersFile)) {
            return [];
        }
        $content = file_get_contents($this->usersFile);
        return json_decode($content, true) ?: [];
    }
    
    private function saveUsers($users) {
        file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT));
    }
    
    public function createUser($username, $password) {
        $users = $this->getUsers();
        
        if (isset($users[$username])) {
            return false; // User already exists
        }
        
        $users[$username] = [
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created' => date('Y-m-d H:i:s')
        ];
        
        $this->saveUsers($users);
        return true;
    }
      public function login($username, $password) {
        $users = $this->getUsers();
        
        if (!isset($users[$username])) {
            return false;
        }
        
        if (password_verify($password, $users[$username]['password'])) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $username;
            $_SESSION['username'] = $username;
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }
        
        return true;
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
