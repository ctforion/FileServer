<?php
/**
 * PHP File Storage Server - Installation Wizard
 * Comprehensive setup system with database initialization and configuration
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security check - prevent running setup if already installed
if (file_exists(__DIR__ . '/.env') && !isset($_GET['force'])) {
    die('Installation already completed. Add ?force=1 to URL to run setup again.');
}

class SetupWizard {
    private $steps = ['welcome', 'requirements', 'database', 'admin', 'settings', 'complete'];
    private $currentStep = 0;
    private $config = [];
    private $errors = [];
    private $warnings = [];

    public function __construct() {
        session_start();
        $this->currentStep = $_SESSION['setup_step'] ?? 0;
        $this->config = $_SESSION['setup_config'] ?? [];
    }

    public function run() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }

        $this->render();
    }

    private function handlePost() {
        $step = $this->steps[$this->currentStep];
        $method = 'handle' . ucfirst($step);
        
        if (method_exists($this, $method)) {
            $this->$method();
        }
    }

    private function handleRequirements() {
        if ($this->checkRequirements()) {
            $this->nextStep();
        }
    }

    private function handleDatabase() {
        $this->config['db_type'] = $_POST['db_type'] ?? 'sqlite';
        $this->config['db_host'] = $_POST['db_host'] ?? 'localhost';
        $this->config['db_port'] = $_POST['db_port'] ?? '';
        $this->config['db_name'] = $_POST['db_name'] ?? 'fileserver';
        $this->config['db_user'] = $_POST['db_user'] ?? '';
        $this->config['db_pass'] = $_POST['db_pass'] ?? '';

        if ($this->testDatabaseConnection()) {
            $this->nextStep();
        }
    }

    private function handleAdmin() {
        $this->config['admin_email'] = $_POST['admin_email'] ?? '';
        $this->config['admin_password'] = $_POST['admin_password'] ?? '';
        $this->config['admin_name'] = $_POST['admin_name'] ?? 'Administrator';
        $this->config['site_name'] = $_POST['site_name'] ?? 'File Storage Server';
        $this->config['site_url'] = $_POST['site_url'] ?? 'http://localhost';

        if ($this->validateAdminData()) {
            $this->nextStep();
        }
    }

    private function handleSettings() {
        $this->config['upload_max_size'] = $_POST['upload_max_size'] ?? '100';
        $this->config['storage_quota'] = $_POST['storage_quota'] ?? '1000';
        $this->config['timezone'] = $_POST['timezone'] ?? 'UTC';
        $this->config['language'] = $_POST['language'] ?? 'en';
        $this->config['enable_registration'] = isset($_POST['enable_registration']);
        $this->config['enable_2fa'] = isset($_POST['enable_2fa']);
        $this->config['enable_compression'] = isset($_POST['enable_compression']);
        $this->config['enable_versioning'] = isset($_POST['enable_versioning']);

        $this->nextStep();
    }

    private function handleComplete() {
        if ($this->performInstallation()) {
            session_destroy();
            header('Location: index.php?installed=1');
            exit;
        }
    }

    private function nextStep() {
        $this->currentStep++;
        $_SESSION['setup_step'] = $this->currentStep;
        $_SESSION['setup_config'] = $this->config;
    }

    private function checkRequirements() {
        $requirements = [
            'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'JSON Extension' => extension_loaded('json'),
            'PDO Extension' => extension_loaded('pdo'),
            'OpenSSL Extension' => extension_loaded('openssl'),
            'cURL Extension' => extension_loaded('curl'),
            'GD Extension' => extension_loaded('gd'),
            'Writable storage directory' => is_writable(__DIR__ . '/storage') || mkdir(__DIR__ . '/storage', 0755, true),
            'Writable logs directory' => is_writable(__DIR__ . '/logs') || mkdir(__DIR__ . '/logs', 0755, true),
            'Writable cache directory' => is_writable(__DIR__ . '/cache') || mkdir(__DIR__ . '/cache', 0755, true),
        ];

        $optional = [
            'SQLite PDO Driver' => extension_loaded('pdo_sqlite'),
            'MySQL PDO Driver' => extension_loaded('pdo_mysql'),
            'PostgreSQL PDO Driver' => extension_loaded('pdo_pgsql'),
            'Zip Extension' => extension_loaded('zip'),
            'Imagick Extension' => extension_loaded('imagick'),
        ];

        $allRequired = true;
        foreach ($requirements as $name => $met) {
            if (!$met) {
                $this->errors[] = "Required: $name";
                $allRequired = false;
            }
        }

        foreach ($optional as $name => $met) {
            if (!$met) {
                $this->warnings[] = "Optional: $name";
            }
        }

        return $allRequired;
    }

    private function testDatabaseConnection() {
        try {
            switch ($this->config['db_type']) {
                case 'sqlite':
                    $dsn = 'sqlite:' . __DIR__ . '/storage/system/database.sqlite';
                    $pdo = new PDO($dsn);
                    break;
                
                case 'mysql':
                    $dsn = "mysql:host={$this->config['db_host']};port={$this->config['db_port']};dbname={$this->config['db_name']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $this->config['db_user'], $this->config['db_pass']);
                    break;
                
                case 'postgresql':
                    $dsn = "pgsql:host={$this->config['db_host']};port={$this->config['db_port']};dbname={$this->config['db_name']}";
                    $pdo = new PDO($dsn, $this->config['db_user'], $this->config['db_pass']);
                    break;
                
                default:
                    throw new Exception('Unsupported database type');
            }

            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return true;

        } catch (Exception $e) {
            $this->errors[] = 'Database connection failed: ' . $e->getMessage();
            return false;
        }
    }

    private function validateAdminData() {
        if (empty($this->config['admin_email'])) {
            $this->errors[] = 'Admin email is required';
        } elseif (!filter_var($this->config['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = 'Invalid email format';
        }

        if (empty($this->config['admin_password'])) {
            $this->errors[] = 'Admin password is required';
        } elseif (strlen($this->config['admin_password']) < 8) {
            $this->errors[] = 'Password must be at least 8 characters';
        }

        if (empty($this->config['site_name'])) {
            $this->errors[] = 'Site name is required';
        }

        return empty($this->errors);
    }

    private function performInstallation() {
        try {
            // Create .env file
            $this->createEnvironmentFile();
            
            // Initialize database
            $this->initializeDatabase();
            
            // Create admin user
            $this->createAdminUser();
            
            // Set up default configurations
            $this->setupDefaults();
            
            return true;

        } catch (Exception $e) {
            $this->errors[] = 'Installation failed: ' . $e->getMessage();
            return false;
        }
    }

    private function createEnvironmentFile() {
        $envContent = $this->generateEnvironmentContent();
        if (!file_put_contents(__DIR__ . '/.env', $envContent)) {
            throw new Exception('Failed to create .env file');
        }
    }

    private function generateEnvironmentContent() {
        $dbPort = '';
        if ($this->config['db_type'] === 'mysql') {
            $dbPort = $this->config['db_port'] ?: '3306';
        } elseif ($this->config['db_type'] === 'postgresql') {
            $dbPort = $this->config['db_port'] ?: '5432';
        }

        return "# PHP File Storage Server Configuration
# Generated by Setup Wizard on " . date('Y-m-d H:i:s') . "

# Application
APP_NAME=\"{$this->config['site_name']}\"
APP_URL=\"{$this->config['site_url']}\"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE={$this->config['timezone']}
APP_LANGUAGE={$this->config['language']}

# Security
JWT_SECRET=" . bin2hex(random_bytes(32)) . "
ENCRYPTION_KEY=" . bin2hex(random_bytes(32)) . "
CSRF_TOKEN_NAME=_token
SESSION_LIFETIME=3600

# Database
DB_TYPE={$this->config['db_type']}
DB_HOST={$this->config['db_host']}
DB_PORT=$dbPort
DB_NAME={$this->config['db_name']}
DB_USER={$this->config['db_user']}
DB_PASS={$this->config['db_pass']}
DB_CHARSET=utf8mb4

# File Storage
UPLOAD_MAX_SIZE={$this->config['upload_max_size']}
STORAGE_QUOTA_MB={$this->config['storage_quota']}
ALLOWED_EXTENSIONS=jpg,jpeg,png,gif,pdf,doc,docx,txt,zip,mp4,mp3
ENABLE_COMPRESSION=" . ($this->config['enable_compression'] ? 'true' : 'false') . "
ENABLE_VERSIONING=" . ($this->config['enable_versioning'] ? 'true' : 'false') . "
ENABLE_THUMBNAILS=true

# Features
ENABLE_REGISTRATION=" . ($this->config['enable_registration'] ? 'true' : 'false') . "
ENABLE_2FA=" . ($this->config['enable_2fa'] ? 'true' : 'false') . "
ENABLE_API=true
ENABLE_WEBHOOKS=true
ENABLE_MONITORING=true

# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=3600

# Email
MAIL_ENABLED=false
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@localhost
MAIL_FROM_NAME=\"{$this->config['site_name']}\"

# Updates
AUTO_UPDATE_ENABLED=true
UPDATE_CHANNEL=stable
GITHUB_REPO=fileserver/core

# Cache
CACHE_ENABLED=true
CACHE_DRIVER=file
CACHE_TTL=3600

# Logging
LOG_LEVEL=info
LOG_MAX_FILES=10
LOG_MAX_SIZE=10MB
";
    }

    private function initializeDatabase() {
        require_once __DIR__ . '/core/utils/EnvLoader.php';
        require_once __DIR__ . '/config.php';
        require_once __DIR__ . '/core/database/Database.php';
        require_once __DIR__ . '/core/database/Migration.php';

        $db = new Database();
        $migration = new Migration($db);
        $migration->runMigrations();
    }

    private function createAdminUser() {
        require_once __DIR__ . '/core/auth/Auth.php';
        
        $auth = new Auth();
        $hashedPassword = password_hash($this->config['admin_password'], PASSWORD_DEFAULT);
        
        $db = new Database();
        $stmt = $db->prepare("
            INSERT INTO users (email, password, name, role, is_active, email_verified_at, created_at) 
            VALUES (?, ?, ?, 'admin', 1, NOW(), NOW())
        ");
        
        $stmt->execute([
            $this->config['admin_email'],
            $hashedPassword,
            $this->config['admin_name']
        ]);
    }

    private function setupDefaults() {
        // Create default directories
        $dirs = [
            'storage/uploads',
            'storage/private',
            'storage/thumbnails',
            'storage/temp',
            'storage/backups',
            'logs',
            'cache/templates',
            'cache/files',
            'plugins'
        ];

        foreach ($dirs as $dir) {
            $path = __DIR__ . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        // Create .htaccess files for security
        $htaccessFiles = [
            'storage/.htaccess' => "deny from all\n",
            'logs/.htaccess' => "deny from all\n",
            'cache/.htaccess' => "deny from all\n",
            'core/.htaccess' => "deny from all\n"
        ];

        foreach ($htaccessFiles as $file => $content) {
            file_put_contents(__DIR__ . '/' . $file, $content);
        }
    }

    private function render() {
        $step = $this->steps[$this->currentStep];
        $method = 'render' . ucfirst($step);
        
        if (method_exists($this, $method)) {
            $this->$method();
        }
    }

    private function renderWelcome() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>PHP File Storage Server - Setup</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
                .container { max-width: 800px; margin: 50px auto; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
                .content { padding: 40px; }
                .step-indicator { display: flex; justify-content: center; margin-bottom: 30px; }
                .step { width: 40px; height: 40px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; margin: 0 10px; font-weight: bold; }
                .step.active { background: #667eea; color: white; }
                .step.completed { background: #28a745; color: white; }
                .form-group { margin-bottom: 20px; }
                label { display: block; margin-bottom: 8px; font-weight: 600; }
                input, select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
                .btn { background: #667eea; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
                .btn:hover { background: #5a6fd8; }
                .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
                .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
                .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
                .feature-list { list-style: none; }
                .feature-list li { padding: 10px 0; border-bottom: 1px solid #eee; }
                .feature-list li:before { content: "✓"; color: #28a745; font-weight: bold; margin-right: 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>PHP File Storage Server</h1>
                    <p>Comprehensive Setup Wizard</p>
                </div>
                <div class="content">
                    <?php $this->renderStepIndicator(); ?>
                    
                    <h2>Welcome to PHP File Storage Server</h2>
                    <p>This setup wizard will guide you through the installation process. The server includes:</p>
                    
                    <ul class="feature-list">
                        <li>Role-based file storage with permissions</li>
                        <li>RESTful API with JWT authentication</li>
                        <li>Multi-language template engine</li>
                        <li>Auto-update system with GitHub integration</li>
                        <li>Advanced metadata management and search</li>
                        <li>File compression and versioning</li>
                        <li>Two-factor authentication (2FA)</li>
                        <li>Rate limiting and audit trails</li>
                        <li>Monitoring and analytics</li>
                        <li>Plugin architecture</li>
                        <li>Webhook integration</li>
                        <li>Modern responsive UI</li>
                    </ul>
                    
                    <form method="post">
                        <button type="submit" class="btn">Start Installation</button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    private function renderRequirements() {
        $this->checkRequirements();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Requirements Check - Setup</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
                .container { max-width: 800px; margin: 50px auto; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
                .content { padding: 40px; }
                .step-indicator { display: flex; justify-content: center; margin-bottom: 30px; }
                .step { width: 40px; height: 40px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; margin: 0 10px; font-weight: bold; }
                .step.active { background: #667eea; color: white; }
                .step.completed { background: #28a745; color: white; }
                .requirement { display: flex; justify-content: space-between; align-items: center; padding: 15px; margin-bottom: 10px; border-radius: 5px; }
                .requirement.met { background: #d4edda; color: #155724; }
                .requirement.not-met { background: #f8d7da; color: #721c24; }
                .requirement.optional { background: #fff3cd; color: #856404; }
                .status { font-weight: bold; }
                .btn { background: #667eea; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-right: 10px; }
                .btn:hover { background: #5a6fd8; }
                .btn:disabled { background: #ccc; cursor: not-allowed; }
                .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
                .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
                .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Requirements Check</h1>
                    <p>Verifying system requirements</p>
                </div>
                <div class="content">
                    <?php $this->renderStepIndicator(); ?>
                    
                    <?php if (!empty($this->errors)): ?>
                        <div class="alert alert-danger">
                            <strong>Required components missing:</strong>
                            <ul>
                                <?php foreach ($this->errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($this->warnings)): ?>
                        <div class="alert alert-warning">
                            <strong>Optional components missing:</strong>
                            <ul>
                                <?php foreach ($this->warnings as $warning): ?>
                                    <li><?php echo htmlspecialchars($warning); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <h3>System Requirements</h3>
                    
                    <?php
                    $requirements = [
                        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
                        'JSON Extension' => extension_loaded('json'),
                        'PDO Extension' => extension_loaded('pdo'),
                        'OpenSSL Extension' => extension_loaded('openssl'),
                        'cURL Extension' => extension_loaded('curl'),
                        'GD Extension' => extension_loaded('gd'),
                        'Writable storage directory' => is_writable(__DIR__ . '/storage') || mkdir(__DIR__ . '/storage', 0755, true),
                        'Writable logs directory' => is_writable(__DIR__ . '/logs') || mkdir(__DIR__ . '/logs', 0755, true),
                        'Writable cache directory' => is_writable(__DIR__ . '/cache') || mkdir(__DIR__ . '/cache', 0755, true),
                    ];

                    $optional = [
                        'SQLite PDO Driver' => extension_loaded('pdo_sqlite'),
                        'MySQL PDO Driver' => extension_loaded('pdo_mysql'),
                        'PostgreSQL PDO Driver' => extension_loaded('pdo_pgsql'),
                        'Zip Extension' => extension_loaded('zip'),
                        'Imagick Extension' => extension_loaded('imagick'),
                    ];

                    foreach ($requirements as $name => $met):
                    ?>
                        <div class="requirement <?php echo $met ? 'met' : 'not-met'; ?>">
                            <span><?php echo $name; ?></span>
                            <span class="status"><?php echo $met ? '✓ OK' : '✗ Missing'; ?></span>
                        </div>
                    <?php endforeach; ?>
                    
                    <h3>Optional Components</h3>
                    
                    <?php foreach ($optional as $name => $met): ?>
                        <div class="requirement <?php echo $met ? 'met' : 'optional'; ?>">
                            <span><?php echo $name; ?></span>
                            <span class="status"><?php echo $met ? '✓ Available' : '- Not Available'; ?></span>
                        </div>
                    <?php endforeach; ?>
                    
                    <form method="post">
                        <button type="submit" class="btn" <?php echo empty($this->errors) ? '' : 'disabled'; ?>>
                            Continue
                        </button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    private function renderDatabase() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Database Configuration - Setup</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
                .container { max-width: 800px; margin: 50px auto; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
                .content { padding: 40px; }
                .step-indicator { display: flex; justify-content: center; margin-bottom: 30px; }
                .step { width: 40px; height: 40px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; margin: 0 10px; font-weight: bold; }
                .step.active { background: #667eea; color: white; }
                .step.completed { background: #28a745; color: white; }
                .form-group { margin-bottom: 20px; }
                label { display: block; margin-bottom: 8px; font-weight: 600; }
                input, select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
                .btn { background: #667eea; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
                .btn:hover { background: #5a6fd8; }
                .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
                .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
                .db-options { display: none; }
                .db-options.active { display: block; }
                .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            </style>
            <script>
                function toggleDbOptions() {
                    const dbType = document.getElementById('db_type').value;
                    document.querySelectorAll('.db-options').forEach(el => el.classList.remove('active'));
                    if (dbType !== 'sqlite') {
                        document.getElementById(dbType + '-options').classList.add('active');
                    }
                }
            </script>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Database Configuration</h1>
                    <p>Configure your database connection</p>
                </div>
                <div class="content">
                    <?php $this->renderStepIndicator(); ?>
                    
                    <?php if (!empty($this->errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($this->errors as $error): ?>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="form-group">
                            <label for="db_type">Database Type:</label>
                            <select id="db_type" name="db_type" onchange="toggleDbOptions()" required>
                                <option value="sqlite" <?php echo ($this->config['db_type'] ?? 'sqlite') === 'sqlite' ? 'selected' : ''; ?>>SQLite (Recommended)</option>
                                <option value="mysql" <?php echo ($this->config['db_type'] ?? '') === 'mysql' ? 'selected' : ''; ?>>MySQL</option>
                                <option value="postgresql" <?php echo ($this->config['db_type'] ?? '') === 'postgresql' ? 'selected' : ''; ?>>PostgreSQL</option>
                            </select>
                        </div>
                        
                        <div class="info">
                            <strong>SQLite</strong> is recommended for most installations. It requires no additional setup and is perfect for small to medium-sized deployments.
                        </div>
                        
                        <div id="mysql-options" class="db-options">
                            <div class="form-group">
                                <label for="db_host">MySQL Host:</label>
                                <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($this->config['db_host'] ?? 'localhost'); ?>" placeholder="localhost">
                            </div>
                            <div class="form-group">
                                <label for="db_port">MySQL Port:</label>
                                <input type="text" id="db_port" name="db_port" value="<?php echo htmlspecialchars($this->config['db_port'] ?? '3306'); ?>" placeholder="3306">
                            </div>
                            <div class="form-group">
                                <label for="db_name">Database Name:</label>
                                <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($this->config['db_name'] ?? 'fileserver'); ?>" placeholder="fileserver">
                            </div>
                            <div class="form-group">
                                <label for="db_user">Username:</label>
                                <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($this->config['db_user'] ?? ''); ?>" placeholder="username">
                            </div>
                            <div class="form-group">
                                <label for="db_pass">Password:</label>
                                <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($this->config['db_pass'] ?? ''); ?>" placeholder="password">
                            </div>
                        </div>
                        
                        <div id="postgresql-options" class="db-options">
                            <div class="form-group">
                                <label for="db_host">PostgreSQL Host:</label>
                                <input type="text" name="db_host" value="<?php echo htmlspecialchars($this->config['db_host'] ?? 'localhost'); ?>" placeholder="localhost">
                            </div>
                            <div class="form-group">
                                <label for="db_port">PostgreSQL Port:</label>
                                <input type="text" name="db_port" value="<?php echo htmlspecialchars($this->config['db_port'] ?? '5432'); ?>" placeholder="5432">
                            </div>
                            <div class="form-group">
                                <label for="db_name">Database Name:</label>
                                <input type="text" name="db_name" value="<?php echo htmlspecialchars($this->config['db_name'] ?? 'fileserver'); ?>" placeholder="fileserver">
                            </div>
                            <div class="form-group">
                                <label for="db_user">Username:</label>
                                <input type="text" name="db_user" value="<?php echo htmlspecialchars($this->config['db_user'] ?? ''); ?>" placeholder="username">
                            </div>
                            <div class="form-group">
                                <label for="db_pass">Password:</label>
                                <input type="password" name="db_pass" value="<?php echo htmlspecialchars($this->config['db_pass'] ?? ''); ?>" placeholder="password">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">Test Connection & Continue</button>
                    </form>
                </div>
            </div>
            <script>toggleDbOptions();</script>
        </body>
        </html>
        <?php
    }

    private function renderAdmin() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Configuration - Setup</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
                .container { max-width: 800px; margin: 50px auto; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
                .content { padding: 40px; }
                .step-indicator { display: flex; justify-content: center; margin-bottom: 30px; }
                .step { width: 40px; height: 40px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; margin: 0 10px; font-weight: bold; }
                .step.active { background: #667eea; color: white; }
                .step.completed { background: #28a745; color: white; }
                .form-group { margin-bottom: 20px; }
                label { display: block; margin-bottom: 8px; font-weight: 600; }
                input, select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
                .btn { background: #667eea; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
                .btn:hover { background: #5a6fd8; }
                .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
                .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
                .form-row { display: flex; gap: 20px; }
                .form-row .form-group { flex: 1; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Admin Configuration</h1>
                    <p>Create administrator account and site settings</p>
                </div>
                <div class="content">
                    <?php $this->renderStepIndicator(); ?>
                    
                    <?php if (!empty($this->errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($this->errors as $error): ?>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <h3>Administrator Account</h3>
                        
                        <div class="form-group">
                            <label for="admin_name">Administrator Name:</label>
                            <input type="text" id="admin_name" name="admin_name" value="<?php echo htmlspecialchars($this->config['admin_name'] ?? 'Administrator'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_email">Administrator Email:</label>
                            <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($this->config['admin_email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_password">Administrator Password:</label>
                            <input type="password" id="admin_password" name="admin_password" minlength="8" required>
                            <small>Minimum 8 characters required</small>
                        </div>
                        
                        <h3>Site Configuration</h3>
                        
                        <div class="form-group">
                            <label for="site_name">Site Name:</label>
                            <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($this->config['site_name'] ?? 'File Storage Server'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="site_url">Site URL:</label>
                            <input type="url" id="site_url" name="site_url" value="<?php echo htmlspecialchars($this->config['site_url'] ?? 'http://localhost'); ?>" required>
                            <small>Include http:// or https://</small>
                        </div>
                        
                        <button type="submit" class="btn">Continue</button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    private function renderSettings() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>System Settings - Setup</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
                .container { max-width: 800px; margin: 50px auto; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
                .content { padding: 40px; }
                .step-indicator { display: flex; justify-content: center; margin-bottom: 30px; }
                .step { width: 40px; height: 40px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; margin: 0 10px; font-weight: bold; }
                .step.active { background: #667eea; color: white; }
                .step.completed { background: #28a745; color: white; }
                .form-group { margin-bottom: 20px; }
                label { display: block; margin-bottom: 8px; font-weight: 600; }
                input, select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
                .btn { background: #667eea; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
                .btn:hover { background: #5a6fd8; }
                .form-row { display: flex; gap: 20px; }
                .form-row .form-group { flex: 1; }
                .checkbox-group { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
                .checkbox-group input { width: auto; }
                .section { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>System Settings</h1>
                    <p>Configure storage limits and features</p>
                </div>
                <div class="content">
                    <?php $this->renderStepIndicator(); ?>
                    
                    <form method="post">
                        <div class="section">
                            <h3>Storage Configuration</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="upload_max_size">Max Upload Size (MB):</label>
                                    <input type="number" id="upload_max_size" name="upload_max_size" value="<?php echo htmlspecialchars($this->config['upload_max_size'] ?? '100'); ?>" min="1" max="1000">
                                </div>
                                <div class="form-group">
                                    <label for="storage_quota">Storage Quota (MB):</label>
                                    <input type="number" id="storage_quota" name="storage_quota" value="<?php echo htmlspecialchars($this->config['storage_quota'] ?? '1000'); ?>" min="100">
                                </div>
                            </div>
                        </div>
                        
                        <div class="section">
                            <h3>Localization</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="timezone">Timezone:</label>
                                    <select id="timezone" name="timezone">
                                        <option value="UTC" <?php echo ($this->config['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                        <option value="America/New_York" <?php echo ($this->config['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                        <option value="America/Chicago" <?php echo ($this->config['timezone'] ?? '') === 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                        <option value="America/Denver" <?php echo ($this->config['timezone'] ?? '') === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                        <option value="America/Los_Angeles" <?php echo ($this->config['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                        <option value="Europe/London" <?php echo ($this->config['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>London</option>
                                        <option value="Europe/Paris" <?php echo ($this->config['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : ''; ?>>Paris</option>
                                        <option value="Asia/Tokyo" <?php echo ($this->config['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : ''; ?>>Tokyo</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="language">Default Language:</label>
                                    <select id="language" name="language">
                                        <option value="en" <?php echo ($this->config['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                        <option value="es" <?php echo ($this->config['language'] ?? '') === 'es' ? 'selected' : ''; ?>>Español</option>
                                        <option value="fr" <?php echo ($this->config['language'] ?? '') === 'fr' ? 'selected' : ''; ?>>Français</option>
                                        <option value="de" <?php echo ($this->config['language'] ?? '') === 'de' ? 'selected' : ''; ?>>Deutsch</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="section">
                            <h3>Features</h3>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="enable_registration" name="enable_registration" <?php echo !empty($this->config['enable_registration']) ? 'checked' : ''; ?>>
                                <label for="enable_registration">Allow user registration</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="enable_2fa" name="enable_2fa" <?php echo !empty($this->config['enable_2fa']) ? 'checked' : ''; ?>>
                                <label for="enable_2fa">Enable two-factor authentication</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="enable_compression" name="enable_compression" <?php echo !empty($this->config['enable_compression']) ? 'checked' : 'checked'; ?>>
                                <label for="enable_compression">Enable file compression</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="enable_versioning" name="enable_versioning" <?php echo !empty($this->config['enable_versioning']) ? 'checked' : 'checked'; ?>>
                                <label for="enable_versioning">Enable file versioning</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">Continue</button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    private function renderComplete() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Installation Complete - Setup</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
                .container { max-width: 800px; margin: 50px auto; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
                .content { padding: 40px; text-align: center; }
                .success-icon { font-size: 64px; color: #28a745; margin-bottom: 20px; }
                .btn { background: #28a745; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; margin: 10px; }
                .btn:hover { background: #218838; }
                .credentials { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: left; }
                .info-box { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Installation Complete!</h1>
                    <p>PHP File Storage Server is ready to use</p>
                </div>
                <div class="content">
                    <div class="success-icon">✅</div>
                    
                    <h2>Congratulations!</h2>
                    <p>Your PHP File Storage Server has been successfully installed and configured.</p>
                    
                    <div class="credentials">
                        <h3>Administrator Credentials</h3>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($this->config['admin_email']); ?></p>
                        <p><strong>Password:</strong> [The password you entered]</p>
                        <p><strong>Role:</strong> Administrator</p>
                    </div>
                    
                    <div class="info-box">
                        <h3>What's been set up:</h3>
                        <ul style="text-align: left; margin-left: 20px;">
                            <li>Database tables created and configured</li>
                            <li>Administrator account created</li>
                            <li>Security settings configured</li>
                            <li>File storage directories created</li>
                            <li>Default configurations applied</li>
                            <li>Multi-language support enabled</li>
                        </ul>
                    </div>
                    
                    <div class="info-box">
                        <h3>Next Steps:</h3>
                        <ul style="text-align: left; margin-left: 20px;">
                            <li>Delete the setup.php file for security</li>
                            <li>Configure your web server (Apache/Nginx)</li>
                            <li>Set up SSL certificates for HTTPS</li>
                            <li>Configure email settings if needed</li>
                            <li>Review security settings</li>
                        </ul>
                    </div>
                    
                    <form method="post">
                        <button type="submit" class="btn">Launch Application</button>
                    </form>
                    
                    <p style="margin-top: 20px; color: #666;">
                        For documentation and support, visit the project repository.
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    private function renderStepIndicator() {
        echo '<div class="step-indicator">';
        for ($i = 0; $i < count($this->steps); $i++) {
            $class = 'step';
            if ($i < $this->currentStep) {
                $class .= ' completed';
            } elseif ($i === $this->currentStep) {
                $class .= ' active';
            }
            echo "<div class=\"$class\">" . ($i + 1) . "</div>";
        }
        echo '</div>';
    }
}

// Run the setup wizard
$wizard = new SetupWizard();
$wizard->run();
?>