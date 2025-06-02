<?php
require_once 'includes/config.php';
require_once 'includes/auth-functions.php';
require_once 'includes/json-functions.php';
require_once 'includes/log-functions.php';

// Start session and check admin authentication
session_start();
require_authentication();
require_admin_role();

$current_user = get_current_user();
$action = $_GET['action'] ?? 'view';
$success_message = '';
$error_message = '';

// Handle backup operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $backup_action = $_POST['action'] ?? '';
    
    switch ($backup_action) {
        case 'create_backup':
            $result = create_system_backup();
            if ($result['success']) {
                $success_message = "Backup created successfully.";
                log_admin_action("System backup created by admin: " . $current_user['username']);
            } else {
                $error_message = "Failed to create backup: " . $result['message'];
            }
            break;
            
        case 'restore_backup':
            $backup_file = $_POST['backup_file'] ?? '';
            if (!empty($backup_file)) {
                $result = restore_system_backup($backup_file);
                if ($result['success']) {
                    $success_message = "System restored from backup successfully.";
                    log_admin_action("System restored from backup: " . $backup_file . " by admin: " . $current_user['username']);
                } else {
                    $error_message = "Failed to restore backup: " . $result['message'];
                }
            }
            break;
            
        case 'delete_backup':
            $backup_file = $_POST['backup_file'] ?? '';
            if (!empty($backup_file)) {
                $result = delete_backup_file($backup_file);
                if ($result['success']) {
                    $success_message = "Backup deleted successfully.";
                    log_admin_action("Backup deleted: " . $backup_file . " by admin: " . $current_user['username']);
                } else {
                    $error_message = "Failed to delete backup: " . $result['message'];
                }
            }
            break;
    }
}

// Get available backups
$backup_files = get_backup_files();

// System backup functions
function create_system_backup() {
    $timestamp = date('Y-m-d_H-i-s');
    $backup_dir = STORAGE_DIR . '/data/backups';
    
    try {
        // Create backup files
        $users_backup = $backup_dir . '/users-backup-' . $timestamp . '.json';
        $files_backup = $backup_dir . '/files-backup-' . $timestamp . '.json';
        $logs_backup = $backup_dir . '/logs-backup-' . $timestamp . '.json';
        
        // Copy current data files
        copy(STORAGE_DIR . '/data/users.json', $users_backup);
        copy(STORAGE_DIR . '/data/files.json', $files_backup);
        copy(STORAGE_DIR . '/data/logs.json', $logs_backup);
        
        return ['success' => true, 'timestamp' => $timestamp];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function get_backup_files() {
    $backup_dir = STORAGE_DIR . '/data/backups';
    $backups = [];
    
    if (is_dir($backup_dir)) {
        $files = scandir($backup_dir);
        foreach ($files as $file) {
            if (strpos($file, 'users-backup-') === 0) {
                $timestamp = str_replace(['users-backup-', '.json'], '', $file);
                $backups[$timestamp] = [
                    'timestamp' => $timestamp,
                    'users_file' => $file,
                    'files_file' => 'files-backup-' . $timestamp . '.json',
                    'logs_file' => 'logs-backup-' . $timestamp . '.json',
                    'date' => date('M j, Y g:i A', strtotime(str_replace('_', ' ', str_replace('-', ':', $timestamp))))
                ];
            }
        }
    }
    
    krsort($backups); // Sort by timestamp descending
    return $backups;
}

function restore_system_backup($timestamp) {
    $backup_dir = STORAGE_DIR . '/data/backups';
    
    try {
        $users_backup = $backup_dir . '/users-backup-' . $timestamp . '.json';
        $files_backup = $backup_dir . '/files-backup-' . $timestamp . '.json';
        $logs_backup = $backup_dir . '/logs-backup-' . $timestamp . '.json';
        
        if (file_exists($users_backup) && file_exists($files_backup)) {
            // Create current backup before restore
            create_system_backup();
            
            // Restore files
            copy($users_backup, STORAGE_DIR . '/data/users.json');
            copy($files_backup, STORAGE_DIR . '/data/files.json');
            if (file_exists($logs_backup)) {
                copy($logs_backup, STORAGE_DIR . '/data/logs.json');
            }
            
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Backup files not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function delete_backup_file($timestamp) {
    $backup_dir = STORAGE_DIR . '/data/backups';
    
    try {
        $files = [
            $backup_dir . '/users-backup-' . $timestamp . '.json',
            $backup_dir . '/files-backup-' . $timestamp . '.json',
            $backup_dir . '/logs-backup-' . $timestamp . '.json'
        ];
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore - FileServer</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/forms.css">
</head>
<body>
    <?php include 'templates/header.html'; ?>
    <?php include 'templates/navigation.html'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Backup & Restore</h1>
            <div class="page-actions">
                <a href="admin.php" class="btn btn-secondary">Back to Admin</a>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Create Backup Section -->
        <div class="backup-section">
            <h2>Create New Backup</h2>
            <p>Create a backup of all system data including users, files metadata, and logs.</p>
            
            <form method="POST" class="backup-form" onsubmit="return confirmBackup()">
                <input type="hidden" name="action" value="create_backup">
                <button type="submit" class="btn btn-primary btn-large">
                    <span class="icon">💾</span>
                    Create System Backup
                </button>
            </form>
            
            <div class="backup-info">
                <h3>What gets backed up:</h3>
                <ul>
                    <li>User accounts and permissions</li>
                    <li>File metadata and organization</li>
                    <li>System logs and activity</li>
                    <li>Configuration settings</li>
                </ul>
                <p><strong>Note:</strong> Actual file content is not backed up, only metadata.</p>
            </div>
        </div>

        <!-- Existing Backups Section -->
        <div class="backups-section">
            <h2>Available Backups</h2>
            
            <?php if (empty($backup_files)): ?>
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <h3>No backups found</h3>
                <p>Create your first backup to get started.</p>
            </div>
            <?php else: ?>
            <div class="backup-list">
                <?php foreach ($backup_files as $backup): ?>
                <div class="backup-item">
                    <div class="backup-info">
                        <div class="backup-name">
                            <strong>Backup <?php echo htmlspecialchars($backup['timestamp']); ?></strong>
                        </div>
                        <div class="backup-date">
                            <?php echo htmlspecialchars($backup['date']); ?>
                        </div>
                        <div class="backup-files">
                            <span class="file-tag">Users</span>
                            <span class="file-tag">Files</span>
                            <span class="file-tag">Logs</span>
                        </div>
                    </div>
                    
                    <div class="backup-actions">
                        <form method="POST" style="display: inline;" onsubmit="return confirmRestore()">
                            <input type="hidden" name="action" value="restore_backup">
                            <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['timestamp']); ?>">
                            <button type="submit" class="btn btn-warning btn-small">
                                <span class="icon">🔄</span>
                                Restore
                            </button>
                        </form>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirmDelete()">
                            <input type="hidden" name="action" value="delete_backup">
                            <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['timestamp']); ?>">
                            <button type="submit" class="btn btn-danger btn-small">
                                <span class="icon">🗑️</span>
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Backup Guidelines -->
        <div class="guidelines-section">
            <h2>Backup Guidelines</h2>
            <div class="guidelines-grid">
                <div class="guideline-card">
                    <h3>Regular Backups</h3>
                    <p>Create backups regularly, especially before major changes or updates.</p>
                </div>
                
                <div class="guideline-card">
                    <h3>Test Restores</h3>
                    <p>Periodically test backup restoration to ensure data integrity.</p>
                </div>
                
                <div class="guideline-card">
                    <h3>Storage Limitation</h3>
                    <p>Only metadata is backed up. Actual files must be backed up separately.</p>
                </div>
                
                <div class="guideline-card">
                    <h3>Before Restore</h3>
                    <p>A new backup is automatically created before each restoration.</p>
                </div>
            </div>
        </div>
    </div>

    <?php include 'templates/footer.html'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        function confirmBackup() {
            return confirm('Are you sure you want to create a new backup? This will save the current system state.');
        }
        
        function confirmRestore() {
            return confirm('Are you sure you want to restore from this backup? Current data will be backed up first, but this action should be used carefully.');
        }
        
        function confirmDelete() {
            return confirm('Are you sure you want to delete this backup? This action cannot be undone.');
        }
    </script>
</body>
</html>
