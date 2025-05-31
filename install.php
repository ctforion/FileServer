<?php
/**
 * Portable PHP File Storage Server - Installation Wizard
 * 
 * This file handles the initial installation and setup
 * Access: https://0xAhmadYousuf.com/FileServer/install.php
 */

// Load configuration
require_once __DIR__ . '/config.php';

// Check if already installed
if (file_exists(__DIR__ . '/.installed')) {
    die('Application is already installed. Delete .installed file to reinstall.');
}

$step = $_GET['step'] ?? 1;
$errors = [];
$success = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            $result = testDatabaseConnection();
            if ($result['success']) {
                $success[] = 'Database connection successful!';
                $step = 3;
            } else {
                $errors[] = $result['message'];
            }
            break;
            
        case 3:
            $result = createDatabase();
            if ($result['success']) {
                $success[] = 'Database created successfully!';
                $step = 4;
            } else {
                $errors[] = $result['message'];
            }
            break;
            
        case 4:
            $result = createAdminUser();
            if ($result['success']) {
                $success[] = 'Admin user created successfully!';
                $step = 5;
            } else {
                $errors[] = $result['message'];
            }
            break;
            
        case 5:
            $result = finishInstallation();
            if ($result['success']) {
                header('Location: ' . url('dashboard'));
                exit;
            } else {
                $errors[] = $result['message'];
            }
            break;
    }
}

function testDatabaseConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
    }
}

function createDatabase() {
    try {
        // Connect without database name
        $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`");
        
        // Switch to the database
        $pdo->exec("USE `" . DB_NAME . "`");
        
        // Load and execute schema
        $schema = file_get_contents(__DIR__ . '/database/schema.sql');
        $statements = explode(';', $schema);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database creation failed: ' . $e->getMessage()];
    }
}

function createAdminUser() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $username = $_POST['admin_username'] ?? ADMIN_USERNAME;
        $email = $_POST['admin_email'] ?? ADMIN_EMAIL;
        $password = $_POST['admin_password'] ?? ADMIN_PASSWORD;
        
        if (empty($username) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role) 
            VALUES (?, ?, ?, 'admin')
            ON DUPLICATE KEY UPDATE 
            email = VALUES(email), 
            password = VALUES(password)
        ");
        
        $stmt->execute([$username, $email, $hashedPassword]);
        
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Admin user creation failed: ' . $e->getMessage()];
    }
}

function finishInstallation() {
    try {
        // Create .installed file
        file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
        
        // Create .htaccess file
        $htaccess = <<<HTACCESS
RewriteEngine On

# Redirect all requests to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"

# Deny access to sensitive files
<Files "config.php">
    Require all denied
</Files>

<Files ".installed">
    Require all denied
</Files>

<FilesMatch "\.(log|sql)$">
    Require all denied
</FilesMatch>
HTACCESS;
        
        file_put_contents(__DIR__ . '/.htaccess', $htaccess);
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Installation completion failed: ' . $e->getMessage()];
    }
}

function checkRequirements() {
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'JSON Extension' => extension_loaded('json'),
        'FileInfo Extension' => extension_loaded('fileinfo'),
        'GD Extension' => extension_loaded('gd'),
        'Storage Directory Writable' => is_writable(dirname(STORAGE_PATH)),
        'Logs Directory Writable' => is_writable(dirname(__DIR__ . '/logs'))
    ];
    
    return $requirements;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Installation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2em;
        }
        
        .content {
            padding: 30px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 0;
            list-style: none;
        }
        
        .step-indicator li {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step-indicator li:not(:last-child):after {
            content: '';
            position: absolute;
            top: 15px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #ddd;
            z-index: -1;
        }
        
        .step-indicator li.active:not(:last-child):after {
            background: #28a745;
        }
        
        .step-indicator .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #ddd;
            color: #666;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .step-indicator li.active .step {
            background: #28a745;
            color: white;
        }
        
        .step-indicator li.completed .step {
            background: #28a745;
            color: white;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        input:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
        }
        
        .btn {
            background: #007cba;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #005a87;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .requirements-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .requirements-table th,
        .requirements-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .requirements-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-ok {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-error {
            color: #dc3545;
            font-weight: bold;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mb-0 {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= APP_NAME ?></h1>
            <p class="mb-0">Installation Wizard</p>
        </div>
        
        <div class="content">
            <ul class="step-indicator">
                <li class="<?= $step >= 1 ? 'active' : '' ?>">
                    <div class="step">1</div>
                    <div>Requirements</div>
                </li>
                <li class="<?= $step >= 2 ? 'active' : '' ?>">
                    <div class="step">2</div>
                    <div>Database</div>
                </li>
                <li class="<?= $step >= 3 ? 'active' : '' ?>">
                    <div class="step">3</div>
                    <div>Setup</div>
                </li>
                <li class="<?= $step >= 4 ? 'active' : '' ?>">
                    <div class="step">4</div>
                    <div>Admin</div>
                </li>
                <li class="<?= $step >= 5 ? 'active' : '' ?>">
                    <div class="step">5</div>
                    <div>Finish</div>
                </li>
            </ul>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div><?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php foreach ($success as $message): ?>
                        <div><?= htmlspecialchars($message) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <h2>System Requirements</h2>
                <p>Please ensure your system meets the following requirements:</p>
                
                <table class="requirements-table">
                    <thead>
                        <tr>
                            <th>Requirement</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $requirements = checkRequirements(); ?>
                        <?php foreach ($requirements as $name => $status): ?>
                            <tr>
                                <td><?= htmlspecialchars($name) ?></td>
                                <td class="<?= $status ? 'status-ok' : 'status-error' ?>">
                                    <?= $status ? '✓ OK' : '✗ Failed' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (array_filter($requirements)): ?>
                    <div class="text-center" style="margin-top: 30px;">
                        <a href="?step=2" class="btn">Continue to Database Setup</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger" style="margin-top: 20px;">
                        Please fix the failed requirements before continuing.
                    </div>
                <?php endif; ?>
                
            <?php elseif ($step == 2): ?>
                <h2>Database Configuration</h2>
                <p>Testing database connection with the settings from config.php:</p>
                
                <table class="requirements-table">
                    <tr>
                        <td><strong>Host:</strong></td>
                        <td><?= htmlspecialchars(DB_HOST) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Database:</strong></td>
                        <td><?= htmlspecialchars(DB_NAME) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Username:</strong></td>
                        <td><?= htmlspecialchars(DB_USER) ?></td>
                    </tr>
                </table>
                
                <form method="post" style="margin-top: 30px;">
                    <div class="text-center">
                        <button type="submit" class="btn">Test Database Connection</button>
                    </div>
                </form>
                
            <?php elseif ($step == 3): ?>
                <h2>Database Setup</h2>
                <p>Now we'll create the database and tables:</p>
                
                <form method="post" style="margin-top: 30px;">
                    <div class="text-center">
                        <button type="submit" class="btn">Create Database & Tables</button>
                    </div>
                </form>
                
            <?php elseif ($step == 4): ?>
                <h2>Admin User Setup</h2>
                <p>Create your administrator account:</p>
                
                <form method="post">
                    <div class="form-group">
                        <label for="admin_username">Username:</label>
                        <input type="text" id="admin_username" name="admin_username" 
                               value="<?= htmlspecialchars(ADMIN_USERNAME) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email">Email:</label>
                        <input type="email" id="admin_email" name="admin_email" 
                               value="<?= htmlspecialchars(ADMIN_EMAIL) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_password">Password:</label>
                        <input type="password" id="admin_password" name="admin_password" 
                               value="<?= htmlspecialchars(ADMIN_PASSWORD) ?>" 
                               minlength="<?= PASSWORD_MIN_LENGTH ?>" required>
                        <small>Minimum <?= PASSWORD_MIN_LENGTH ?> characters</small>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn">Create Admin User</button>
                    </div>
                </form>
                
            <?php elseif ($step == 5): ?>
                <h2>Installation Complete</h2>
                <div class="alert alert-success">
                    <strong>Congratulations!</strong> FileServer has been installed successfully.
                </div>
                
                <p>Your FileServer installation is now ready. Here are your next steps:</p>
                
                <ol>
                    <li><strong>Secure your installation:</strong> Change the default passwords in config.php</li>
                    <li><strong>Configure settings:</strong> Review and adjust settings in config.php</li>
                    <li><strong>Set up backups:</strong> Configure automated backups for your data</li>
                    <li><strong>Test features:</strong> Upload files and test sharing functionality</li>
                </ol>
                
                <form method="post" style="margin-top: 30px;">
                    <div class="text-center">
                        <button type="submit" class="btn btn-success">Launch FileServer</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
