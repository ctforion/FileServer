<?php
/**
 * Update Admin User to Plain Text Password
 */

require_once 'core/database/DatabaseManager.php';

echo "=== Updating Admin User to Plain Text Password ===\n\n";

try {
    // Initialize database
    $db = DatabaseManager::getInstance();
    
    // Update admin user with plain text password
    $updateData = [
        'password' => 'admin123', // Plain text password
        'updated' => date('c')
    ];
    
    // Remove old hashed password
    $currentUser = $db->getUser('admin');
    if ($currentUser) {
        $success = $db->updateUser('admin', $updateData);
        
        if ($success) {
            echo "✓ Admin user updated successfully!\n";
            echo "  Username: admin\n";
            echo "  Password: admin123 (plain text)\n\n";
            
            // Verify the update
            $updatedUser = $db->getUser('admin');
            if (isset($updatedUser['password']) && $updatedUser['password'] === 'admin123') {
                echo "✓ Password verification successful - plain text password is working!\n";
            } else {
                echo "✗ Password verification failed!\n";
            }
            
            // Test authentication
            $authResult = $db->authenticateUser('admin', 'admin123');
            if ($authResult) {
                echo "✓ Authentication test successful!\n";
                echo "  User authenticated successfully with plain text password.\n\n";
            } else {
                echo "✗ Authentication test failed!\n";
            }
            
        } else {
            echo "✗ Failed to update admin user!\n";
        }
    } else {
        echo "✗ Admin user not found!\n";
    }

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\nDone!\n";
?>
