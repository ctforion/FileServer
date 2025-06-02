<?php
/**
 * Simple Admin Management System
 * No password hashing for easy handling
 */

require_once 'core/database/DatabaseManager.php';

// Initialize database
$db = DatabaseManager::getInstance();

echo "=== Simple Admin Management System ===\n\n";

// Function to add/update admin user
function addAdmin($username, $password, $email = null) {
    global $db;
    
    $adminData = [
        'id' => $username,
        'username' => $username,
        'email' => $email ?: $username . '@fileserver.local',
        'password' => $password, // Plain text password - no hashing
        'role' => 'admin',
        'status' => 'active',
        'quota' => 1073741824, // 1GB
        'settings' => [
            'theme' => 'default',
            'language' => 'en',
            'timezone' => 'UTC'
        ],
        'last_login' => null,
        'login_count' => 0,
        'created' => date('c'),
        'updated' => date('c')
    ];
    
    // Check if user exists
    $existingUser = $db->getUser($username);
    if ($existingUser) {
        // Update existing user
        $updateData = [
            'password' => $password,
            'role' => 'admin',
            'status' => 'active',
            'updated' => date('c')
        ];
        $success = $db->updateUser($username, $updateData);
        echo "✓ Admin user '{$username}' updated successfully\n";
    } else {
        // Create new user
        $success = $db->createUser($username, $adminData);
        echo "✓ Admin user '{$username}' created successfully\n";
    }
    
    if ($success) {
        echo "  Username: {$username}\n";
        echo "  Password: {$password} (plain text)\n";
        echo "  Email: " . ($email ?: $username . '@fileserver.local') . "\n\n";
        
        // Test authentication
        $authTest = $db->authenticateUser($username, $password);
        if ($authTest) {
            echo "✓ Authentication test successful!\n\n";
        } else {
            echo "✗ Authentication test failed!\n\n";
        }
    } else {
        echo "✗ Failed to create/update admin user '{$username}'\n\n";
    }
}

// Function to list all admin users
function listAdmins() {
    global $db;
    
    $users = $db->getAllUsers() ?: [];
    $admins = array_filter($users, function($user) {
        return isset($user['role']) && $user['role'] === 'admin';
    });
    
    if (empty($admins)) {
        echo "No admin users found.\n\n";
        return;
    }
    
    echo "Current Admin Users:\n";
    echo "-------------------\n";
    foreach ($admins as $admin) {
        $password = isset($admin['password']) ? $admin['password'] : 'N/A (hashed)';
        echo "Username: {$admin['username']}\n";
        echo "Password: {$password}\n";
        echo "Email: {$admin['email']}\n";
        echo "Status: {$admin['status']}\n";
        echo "Created: {$admin['created']}\n";
        echo "-------------------\n";
    }
    echo "\n";
}

// Command line interface
if ($argc > 1) {
    $command = $argv[1];
    
    switch ($command) {
        case 'add':
            if ($argc < 4) {
                echo "Usage: php admin_manager.php add <username> <password> [email]\n";
                exit(1);
            }
            $username = $argv[2];
            $password = $argv[3];
            $email = $argv[4] ?? null;
            addAdmin($username, $password, $email);
            break;
            
        case 'list':
            listAdmins();
            break;
            
        case 'reset':
            // Reset default admin
            addAdmin('admin', 'admin123', 'admin@fileserver.local');
            break;
            
        default:
            echo "Unknown command: {$command}\n";
            echo "Available commands:\n";
            echo "  add <username> <password> [email] - Add/update admin user\n";
            echo "  list                               - List all admin users\n";
            echo "  reset                              - Reset default admin (admin/admin123)\n";
            exit(1);
    }
} else {
    // Interactive mode
    echo "Choose an option:\n";
    echo "1. Add/Update Admin User\n";
    echo "2. List Admin Users\n";
    echo "3. Reset Default Admin (admin/admin123)\n";
    echo "4. Exit\n\n";
    
    while (true) {
        echo "Enter choice (1-4): ";
        $choice = trim(fgets(STDIN));
        
        switch ($choice) {
            case '1':
                echo "Enter username: ";
                $username = trim(fgets(STDIN));
                echo "Enter password: ";
                $password = trim(fgets(STDIN));
                echo "Enter email (optional): ";
                $email = trim(fgets(STDIN));
                $email = empty($email) ? null : $email;
                
                addAdmin($username, $password, $email);
                break;
                
            case '2':
                listAdmins();
                break;
                
            case '3':
                addAdmin('admin', 'admin123', 'admin@fileserver.local');
                break;
                
            case '4':
                echo "Goodbye!\n";
                exit(0);
                
            default:
                echo "Invalid choice. Please enter 1-4.\n";
        }
        
        echo "\n";
    }
}
?>
