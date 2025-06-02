<?php
require_once 'includes/config.php';
require_once 'includes/auth-functions.php';
require_once 'includes/file-functions.php';
require_once 'includes/log-functions.php';
require_once 'includes/json-functions.php';
require_once 'includes/validation-functions.php';

// Start session and check authentication
session_start();
require_authentication();

$current_user = get_current_user();
$upload_errors = [];
$upload_success = [];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $upload_errors[] = "Invalid security token. Please try again.";
    } else {
        $files = $_FILES['files'];
        $upload_dir = $_POST['upload_dir'] ?? '';
        
        // Handle multiple files
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_data = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                    
                    $result = upload_file($file_data, $current_user['id'], $upload_dir);
                    if ($result['success']) {
                        $upload_success[] = "File '{$files['name'][$i]}' uploaded successfully!";
                        log_file_operation("File uploaded: " . $files['name'][$i] . " by user: " . $current_user['username']);
                    } else {
                        $upload_errors[] = "Error uploading '{$files['name'][$i]}': " . $result['message'];
                        log_error("Upload failed for " . $files['name'][$i] . ": " . $result['message']);
                    }
                }
            }
        } else {
            // Single file upload
            if ($files['error'] === UPLOAD_ERR_OK) {
                $result = upload_file($files, $current_user['id'], $upload_dir);
                if ($result['success']) {
                    $upload_success[] = "File '{$files['name']}' uploaded successfully!";
                    log_file_operation("File uploaded: " . $files['name'] . " by user: " . $current_user['username']);
                } else {
                    $upload_errors[] = "Error uploading '{$files['name']}': " . $result['message'];
                    log_error("Upload failed for " . $files['name'] . ": " . $result['message']);
                }
            }
        }
    }
}

// Get user's directories for dropdown
$user_files = get_user_files($current_user['id']);
$user_dirs = get_user_directories($current_user['id']);

// Generate CSRF token
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Files - FileServer</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/forms.css">
</head>
<body>
    <?php include 'templates/header.html'; ?>
    <?php include 'templates/navigation.html'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Upload Files</h1>
            <p>Select files to upload to your storage space</p>
        </div>

        <?php if (!empty($upload_errors)): ?>
        <div class="alert alert-error">
            <h3>Upload Errors:</h3>
            <ul>
                <?php foreach ($upload_errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($upload_success)): ?>
        <div class="alert alert-success">
            <h3>Upload Successful:</h3>
            <ul>
                <?php foreach ($upload_success as $success): ?>
                <li><?php echo htmlspecialchars($success); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="upload-section">
            <?php include 'templates/upload-form.html'; ?>
        </div>

        <div class="upload-info">
            <h2>Upload Guidelines</h2>
            <div class="info-grid">
                <div class="info-card">
                    <h3>File Size Limit</h3>
                    <p><?php echo format_file_size(get_max_upload_size()); ?> per file</p>
                </div>
                
                <div class="info-card">
                    <h3>Allowed Types</h3>
                    <p>Most file types are supported</p>
                </div>
                
                <div class="info-card">
                    <h3>Storage Space</h3>
                    <p>Used: <?php echo format_file_size(calculate_user_storage($current_user['id'])); ?></p>
                </div>
            </div>
        </div>

        <div class="upload-tips">
            <h3>Tips for Better Uploads</h3>
            <ul>
                <li>You can select multiple files at once</li>
                <li>Large files may take longer to upload</li>
                <li>Use descriptive filenames for better organization</li>
                <li>Create folders to organize your files</li>
            </ul>
        </div>
    </div>

    <?php include 'templates/footer.html'; ?>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/upload.js"></script>
</body>
</html>
