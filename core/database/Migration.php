<?php
/**
 * Database Migration System
 * Handles database schema creation and updates
 */

namespace FileServer\Core\Database;

use Exception;

class Migration {
    private $db;
    private $migrationsPath;
    private $migrationTable = 'migrations';

    public function __construct() {
        $this->db = Database::getInstance();
        $this->migrationsPath = __DIR__ . DIRECTORY_SEPARATOR . 'migrations';
        $this->createMigrationsTable();
    }

    /**
     * Create migrations tracking table
     */
    private function createMigrationsTable(): void {
        if (!$this->db->tableExists($this->migrationTable)) {
            $autoIncrement = $this->db->getSQL('auto_increment');
            $currentTimestamp = $this->db->getSQL('current_timestamp');
            
            $sql = "CREATE TABLE {$this->migrationTable} (
                id INTEGER PRIMARY KEY {$autoIncrement},
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMP DEFAULT {$currentTimestamp}
            )";
            
            $this->db->query($sql);
        }
    }

    /**
     * Run all pending migrations
     */
    public function migrate(): array {
        $executedMigrations = $this->getExecutedMigrations();
        $availableMigrations = $this->getAvailableMigrations();
        $pendingMigrations = array_diff($availableMigrations, $executedMigrations);
        
        $results = [];
        
        foreach ($pendingMigrations as $migration) {
            try {
                $this->runMigration($migration);
                $this->markAsExecuted($migration);
                $results[] = ['migration' => $migration, 'status' => 'success'];
            } catch (Exception $e) {
                $results[] = ['migration' => $migration, 'status' => 'failed', 'error' => $e->getMessage()];
                break; // Stop on first failure
            }
        }
        
        return $results;
    }

    /**
     * Get list of executed migrations
     */
    private function getExecutedMigrations(): array {
        $migrations = $this->db->select("SELECT migration FROM {$this->migrationTable} ORDER BY migration");
        return array_column($migrations, 'migration');
    }

    /**
     * Get list of available migrations
     */
    private function getAvailableMigrations(): array {
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
            $this->createInitialMigrations();
        }

        $files = scandir($this->migrationsPath);
        $migrations = [];
        
        foreach ($files as $file) {
            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_.*\.php$/', $file)) {
                $migrations[] = basename($file, '.php');
            }
        }
        
        sort($migrations);
        return $migrations;
    }

    /**
     * Run a specific migration
     */
    private function runMigration(string $migration): void {
        $migrationFile = $this->migrationsPath . DIRECTORY_SEPARATOR . $migration . '.php';
        
        if (!file_exists($migrationFile)) {
            throw new Exception("Migration file not found: {$migrationFile}");
        }
        
        // Execute migration in transaction
        $this->db->transaction(function() use ($migrationFile) {
            require $migrationFile;
        });
    }

    /**
     * Mark migration as executed
     */
    private function markAsExecuted(string $migration): void {
        $this->db->insert($this->migrationTable, [
            'migration' => $migration,
            'executed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Create initial migration files
     */
    private function createInitialMigrations(): void {
        $this->createUsersMigration();
        $this->createFilesMigration();
        $this->createPermissionsMigration();
        $this->createSessionsMigration();
        $this->createAuditLogMigration();
        $this->createSettingsMigration();
        $this->createWebhooksMigration();
        $this->createPluginsMigration();
    }

    /**
     * Create users table migration
     */
    private function createUsersMigration(): void {
        $timestamp = date('Y_m_d_His', strtotime('2024-01-01 00:00:00'));
        $filename = "{$timestamp}_create_users_table.php";
        
        $content = '<?php
/**
 * Create users table
 */

$autoIncrement = $db->getSQL("auto_increment");
$currentTimestamp = $db->getSQL("current_timestamp");
$textType = $db->getSQL("text_type");

$sql = "CREATE TABLE users (
    id INTEGER PRIMARY KEY {$autoIncrement},
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    salt VARCHAR(32) NOT NULL,
    role ENUM(\'admin\', \'moderator\', \'user\') DEFAULT \'user\',
    status ENUM(\'active\', \'inactive\', \'suspended\', \'pending\') DEFAULT \'pending\',
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    avatar VARCHAR(255),
    timezone VARCHAR(50) DEFAULT \'UTC\',
    language VARCHAR(10) DEFAULT \'en\',
    theme VARCHAR(20) DEFAULT \'light\',
    storage_quota BIGINT DEFAULT 0,
    storage_used BIGINT DEFAULT 0,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(32),
    api_key VARCHAR(64) UNIQUE,
    last_login TIMESTAMP NULL,
    last_ip VARCHAR(45),
    login_attempts INTEGER DEFAULT 0,
    locked_until TIMESTAMP NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(64),
    password_reset_token VARCHAR(64),
    password_reset_expires TIMESTAMP NULL,
    preferences {$textType},
    metadata {$textType},
    created_at TIMESTAMP DEFAULT {$currentTimestamp},
    updated_at TIMESTAMP DEFAULT {$currentTimestamp}
)";

$db->query($sql);

// Create default admin user if none exists
$adminExists = $db->selectOne("SELECT COUNT(*) as count FROM users WHERE role = \'admin\'");
if (!$adminExists || $adminExists[\'count\'] == 0) {
    $salt = bin2hex(random_bytes(16));
    $passwordHash = password_hash(ADMIN_DEFAULT_PASSWORD . $salt, PASSWORD_ARGON2ID);
    
    $db->insert("users", [
        "username" => ADMIN_DEFAULT_USERNAME,
        "email" => ADMIN_DEFAULT_EMAIL,
        "password_hash" => $passwordHash,
        "salt" => $salt,
        "role" => "admin",
        "status" => "active",
        "first_name" => "System",
        "last_name" => "Administrator",
        "email_verified" => true,
        "api_key" => bin2hex(random_bytes(32)),
        "storage_quota" => 0, // Unlimited for admin
        "created_at" => date("Y-m-d H:i:s"),
        "updated_at" => date("Y-m-d H:i:s")
    ]);
}
';
        
        file_put_contents($this->migrationsPath . DIRECTORY_SEPARATOR . $filename, $content);
    }

    /**
     * Create files table migration
     */
    private function createFilesMigration(): void {
        $timestamp = date('Y_m_d_His', strtotime('2024-01-01 00:01:00'));
        $filename = "{$timestamp}_create_files_table.php";
        
        $content = '<?php
/**
 * Create files table
 */

$autoIncrement = $db->getSQL("auto_increment");
$currentTimestamp = $db->getSQL("current_timestamp");
$textType = $db->getSQL("text_type");
$jsonType = $db->getSQL("json_type");

$sql = "CREATE TABLE files (
    id INTEGER PRIMARY KEY {$autoIncrement},
    user_id INTEGER NOT NULL,
    parent_id INTEGER NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_hash VARCHAR(64) NOT NULL,
    mime_type VARCHAR(100),
    file_size BIGINT NOT NULL DEFAULT 0,
    compressed_size BIGINT DEFAULT 0,
    is_compressed BOOLEAN DEFAULT FALSE,
    compression_type VARCHAR(20),
    version INTEGER DEFAULT 1,
    is_latest_version BOOLEAN DEFAULT TRUE,
    is_directory BOOLEAN DEFAULT FALSE,
    is_public BOOLEAN DEFAULT FALSE,
    is_shared BOOLEAN DEFAULT FALSE,
    download_count INTEGER DEFAULT 0,
    view_count INTEGER DEFAULT 0,
    status ENUM(\'active\', \'deleted\', \'quarantine\') DEFAULT \'active\',
    description {$textType},
    tags {$textType},
    metadata {$jsonType},
    permissions {$jsonType},
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT {$currentTimestamp},
    updated_at TIMESTAMP DEFAULT {$currentTimestamp},
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES files(id) ON DELETE CASCADE
)";

$db->query($sql);

// Create indexes
$db->query("CREATE INDEX idx_files_user_id ON files(user_id)");
$db->query("CREATE INDEX idx_files_parent_id ON files(parent_id)");
$db->query("CREATE INDEX idx_files_hash ON files(file_hash)");
$db->query("CREATE INDEX idx_files_status ON files(status)");
$db->query("CREATE INDEX idx_files_created_at ON files(created_at)");
';
        
        file_put_contents($this->migrationsPath . DIRECTORY_SEPARATOR . $filename, $content);
    }

    /**
     * Create permissions table migration
     */
    private function createPermissionsMigration(): void {
        $timestamp = date('Y_m_d_His', strtotime('2024-01-01 00:02:00'));
        $filename = "{$timestamp}_create_permissions_table.php";
        
        $content = '<?php
/**
 * Create permissions table
 */

$autoIncrement = $db->getSQL("auto_increment");
$currentTimestamp = $db->getSQL("current_timestamp");

$sql = "CREATE TABLE permissions (
    id INTEGER PRIMARY KEY {$autoIncrement},
    file_id INTEGER NOT NULL,
    user_id INTEGER NULL,
    role VARCHAR(20) NULL,
    permission_type ENUM(\'read\', \'write\', \'delete\', \'share\', \'admin\') NOT NULL,
    granted_by INTEGER NOT NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT {$currentTimestamp},
    
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE CASCADE
)";

$db->query($sql);

// Create indexes
$db->query("CREATE INDEX idx_permissions_file_id ON permissions(file_id)");
$db->query("CREATE INDEX idx_permissions_user_id ON permissions(user_id)");
$db->query("CREATE INDEX idx_permissions_role ON permissions(role)");
';
        
        file_put_contents($this->migrationsPath . DIRECTORY_SEPARATOR . $filename, $content);
    }

    /**
     * Create sessions table migration
     */
    private function createSessionsMigration(): void {
        $timestamp = date('Y_m_d_His', strtotime('2024-01-01 00:03:00'));
        $filename = "{$timestamp}_create_sessions_table.php";
        
        $content = '<?php
/**
 * Create sessions table
 */

$autoIncrement = $db->getSQL("auto_increment");
$currentTimestamp = $db->getSQL("current_timestamp");
$textType = $db->getSQL("text_type");

$sql = "CREATE TABLE sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INTEGER NULL,
    ip_address VARCHAR(45),
    user_agent {$textType},
    payload {$textType},
    last_activity INTEGER,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT {$currentTimestamp},
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

$db->query($sql);

// Create indexes
$db->query("CREATE INDEX idx_sessions_user_id ON sessions(user_id)");
$db->query("CREATE INDEX idx_sessions_last_activity ON sessions(last_activity)");
$db->query("CREATE INDEX idx_sessions_expires_at ON sessions(expires_at)");
';
        
        file_put_contents($this->migrationsPath . DIRECTORY_SEPARATOR . $filename, $content);
    }

    /**
     * Create audit log migration
     */
    private function createAuditLogMigration(): void {
        $timestamp = date('Y_m_d_His', strtotime('2024-01-01 00:04:00'));
        $filename = "{$timestamp}_create_audit_log_table.php";
        
        $content = '<?php
/**
 * Create audit_log table
 */

$autoIncrement = $db->getSQL("auto_increment");
$currentTimestamp = $db->getSQL("current_timestamp");
$textType = $db->getSQL("text_type");
$jsonType = $db->getSQL("json_type");

$sql = "CREATE TABLE audit_log (
    id INTEGER PRIMARY KEY {$autoIncrement},
    user_id INTEGER NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50),
    resource_id INTEGER NULL,
    old_values {$jsonType},
    new_values {$jsonType},
    ip_address VARCHAR(45),
    user_agent {$textType},
    session_id VARCHAR(128),
    success BOOLEAN DEFAULT TRUE,
    error_message {$textType},
    created_at TIMESTAMP DEFAULT {$currentTimestamp},
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)";

$db->query($sql);

// Create indexes
$db->query("CREATE INDEX idx_audit_user_id ON audit_log(user_id)");
$db->query("CREATE INDEX idx_audit_action ON audit_log(action)");
$db->query("CREATE INDEX idx_audit_resource ON audit_log(resource_type, resource_id)");
$db->query("CREATE INDEX idx_audit_created_at ON audit_log(created_at)");
';
        
        file_put_contents($this->migrationsPath . DIRECTORY_SEPARATOR . $filename, $content);
    }

    /**
     * Create settings migration
     */
    private function createSettingsMigration(): void {
        $timestamp = date('Y_m_d_His', strtotime('2024-01-01 00:05:00'));
        $filename = "{$timestamp}_create_settings_table.php";
        
        $content = '<?php
/**
 * Create settings table
 */

$autoIncrement = $db->getSQL("auto_increment");
$currentTimestamp = $db->getSQL("current_timestamp");
$textType = $db->getSQL("text_type");

$sql = "CREATE TABLE settings (
    id INTEGER PRIMARY KEY {$autoIncrement},
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value {$textType},
    setting_type VARCHAR(20) DEFAULT \'string\',
    description {$textType},
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT {$currentTimestamp},
    updated_at TIMESTAMP DEFAULT {$currentTimestamp}
)";

$db->query($sql);

// Insert default settings
$defaultSettings = [
    [\'site_name\', SITE_NAME, \'string\', \'Website name\', true],
    [\'site_description\', SITE_DESCRIPTION, \'string\', \'Website description\', true],
    [\'maintenance_mode\', \'false\', \'boolean\', \'Maintenance mode status\', true],
    [\'registration_enabled\', REGISTRATION_ENABLED ? \'true\' : \'false\', \'boolean\', \'Allow new user registration\', true],
    [\'email_verification_required\', EMAIL_VERIFICATION_REQUIRED ? \'true\' : \'false\', \'boolean\', \'Require email verification\', true],
    [\'max_file_size\', MAX_FILE_SIZE, \'integer\', \'Maximum file size in bytes\', true],
    [\'allowed_extensions\', ALLOWED_FILE_TYPES, \'string\', \'Allowed file extensions\', true],
    [\'storage_path\', STORAGE_PATH, \'string\', \'File storage path\', true],
    [\'backup_enabled\', BACKUP_ENABLED ? \'true\' : \'false\', \'boolean\', \'Automatic backup enabled\', true],
    [\'compression_enabled\', COMPRESSION_ENABLED ? \'true\' : \'false\', \'boolean\', \'File compression enabled\', true]
];

foreach ($defaultSettings as $setting) {
    $db->insert(\'settings\', [
        \'setting_key\' => $setting[0],
        \'setting_value\' => $setting[1],
        \'setting_type\' => $setting[2],
        \'description\' => $setting[3],
        \'is_system\' => $setting[4] ? 1 : 0,
        \'created_at\' => date(\'Y-m-d H:i:s\'),
        \'updated_at\' => date(\'Y-m-d H:i:s\')
    ]);
}
';
        
        file_put_contents($this->migrationsPath . DIRECTORY_SEPARATOR . $filename, $content);
    }

    /**
     * Create webhooks migration
     */
    private function createWebhooksMigration(): void {
        $timestamp = date('Y_m_d_His', strtotime('2024-01-01 00:06:00'));
        $filename = "{$timestamp}_create_webhooks_table.php";
        
        $content = '<?php
/**
 * Create webhooks table
 */

$autoIncrement = $db->getSQL("auto_increment");
$currentTimestamp = $db->getSQL("current_timestamp");
$textType = $db->getSQL("text_type");
$jsonType = $db->getSQL("json_type");

$sql = "CREATE TABLE webhooks (
    id INTEGER PRIMARY KEY {$autoIncrement},
    name VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    events {$jsonType},
    secret VARCHAR(64),
    headers {$jsonType},
    is_active BOOLEAN DEFAULT TRUE,
    last_triggered TIMESTAMP NULL,
    failure_count INTEGER DEFAULT 0,
    max_failures INTEGER DEFAULT 3,
    timeout INTEGER DEFAULT 30,
    created_at TIMESTAMP DEFAULT {$currentTimestamp},
    updated_at TIMESTAMP DEFAULT {$currentTimestamp}
)";

$db->query($sql);

// Create webhook logs table
$sql = "CREATE TABLE webhook_logs (
    id INTEGER PRIMARY KEY {$autoIncrement},
    webhook_id INTEGER NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    payload {$jsonType},
    response_code INTEGER,
    response_body {$textType},
    execution_time FLOAT,
    success BOOLEAN DEFAULT FALSE,
    error_message {$textType},
    created_at TIMESTAMP DEFAULT {$currentTimestamp},
    
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
)";

$db->query($sql);

// Create indexes
$db->query("CREATE INDEX idx_webhook_logs_webhook_id ON webhook_logs(webhook_id)");
$db->query("CREATE INDEX idx_webhook_logs_created_at ON webhook_logs(created_at)");
';
        
        file_put_contents($this->migrationsPath . DIRECTORY_SEPARATOR . $filename, $content);
    }

    /**
     * Create plugins migration
     */
    private function createPluginsMigration(): void {
        $timestamp = date('Y_m_d_His', strtotime('2024-01-01 00:07:00'));
        $filename = "{$timestamp}_create_plugins_table.php";
        
        $content = '<?php
/**
 * Create plugins table
 */

$autoIncrement = $db->getSQL("auto_increment");
$currentTimestamp = $db->getSQL("current_timestamp");
$textType = $db->getSQL("text_type");
$jsonType = $db->getSQL("json_type");

$sql = "CREATE TABLE plugins (
    id INTEGER PRIMARY KEY {$autoIncrement},
    name VARCHAR(100) NOT NULL UNIQUE,
    version VARCHAR(20) NOT NULL,
    description {$textType},
    author VARCHAR(100),
    plugin_path VARCHAR(255) NOT NULL,
    main_file VARCHAR(255) NOT NULL,
    config {$jsonType},
    dependencies {$jsonType},
    is_active BOOLEAN DEFAULT FALSE,
    is_system BOOLEAN DEFAULT FALSE,
    install_date TIMESTAMP DEFAULT {$currentTimestamp},
    last_updated TIMESTAMP DEFAULT {$currentTimestamp}
)";

$db->query($sql);

// Create plugin hooks table
$sql = "CREATE TABLE plugin_hooks (
    id INTEGER PRIMARY KEY {$autoIncrement},
    plugin_id INTEGER NOT NULL,
    hook_name VARCHAR(100) NOT NULL,
    callback_function VARCHAR(100) NOT NULL,
    priority INTEGER DEFAULT 10,
    is_active BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (plugin_id) REFERENCES plugins(id) ON DELETE CASCADE
)";

$db->query($sql);

// Create indexes
$db->query("CREATE INDEX idx_plugin_hooks_plugin_id ON plugin_hooks(plugin_id)");
$db->query("CREATE INDEX idx_plugin_hooks_hook_name ON plugin_hooks(hook_name)");
';
        
        file_put_contents($this->migrationsPath . DIRECTORY_SEPARATOR . $filename, $content);
    }

    /**
     * Rollback last migration
     */
    public function rollback(): bool {
        $lastMigration = $this->db->selectOne(
            "SELECT migration FROM {$this->migrationTable} ORDER BY id DESC LIMIT 1"
        );
        
        if (!$lastMigration) {
            return false;
        }
        
        $migrationName = $lastMigration['migration'];
        $rollbackFile = $this->migrationsPath . DIRECTORY_SEPARATOR . $migrationName . '_rollback.php';
        
        if (file_exists($rollbackFile)) {
            $this->db->transaction(function() use ($rollbackFile) {
                require $rollbackFile;
            });
        }
        
        $this->db->delete($this->migrationTable, 'migration = ?', [$migrationName]);
        
        return true;
    }

    /**
     * Get migration status
     */
    public function getStatus(): array {
        $executed = $this->getExecutedMigrations();
        $available = $this->getAvailableMigrations();
        $pending = array_diff($available, $executed);
        
        return [
            'executed' => $executed,
            'pending' => $pending,
            'total_available' => count($available),
            'total_executed' => count($executed),
            'total_pending' => count($pending)
        ];
    }
}
