<?php
require_once 'includes/config.php';
require_once 'includes/auth-functions.php';
require_once 'includes/file-functions.php';
require_once 'includes/log-functions.php';
require_once 'includes/json-functions.php';

// Start session and check authentication
session_start();
require_authentication();

$current_user = get_current_user();
$user_role = $current_user['role'] ?? 'user';

// Get filter parameters
$search_term = $_GET['search'] ?? '';
$file_type = $_GET['type'] ?? '';
$sort_by = $_GET['sort'] ?? 'name';
$sort_order = $_GET['order'] ?? 'asc';
$current_dir = $_GET['dir'] ?? '';

// Get user's files or all files (for admin)
if ($user_role === 'admin' && isset($_GET['show_all'])) {
    $all_files = read_json_file(STORAGE_DIR . '/data/files.json');
    $files = $all_files;
} else {
    $files = get_user_files($current_user['id']);
}

// Apply filters
if (!empty($search_term)) {
    $files = array_filter($files, function($file) use ($search_term) {
        return stripos($file['name'], $search_term) !== false;
    });
}

if (!empty($file_type)) {
    $files = array_filter($files, function($file) use ($file_type) {
        return stripos($file['type'], $file_type) !== false;
    });
}

if (!empty($current_dir)) {
    $files = array_filter($files, function($file) use ($current_dir) {
        return ($file['directory'] ?? '') === $current_dir;
    });
}

// Sort files
usort($files, function($a, $b) use ($sort_by, $sort_order) {
    $field_a = $a[$sort_by] ?? '';
    $field_b = $b[$sort_by] ?? '';
    
    if ($sort_by === 'size') {
        $result = $field_a <=> $field_b;
    } else {
        $result = strcasecmp($field_a, $field_b);
    }
    
    return $sort_order === 'desc' ? -$result : $result;
});

// Get user directories
$user_dirs = get_user_directories($current_user['id']);

// Log file browser access
log_access("File browser accessed by user: " . $current_user['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Browser - FileServer</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/file-browser.css">
</head>
<body>
    <?php include 'templates/header.html'; ?>
    <?php include 'templates/navigation.html'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>File Browser</h1>
            <div class="browser-actions">
                <a href="upload.php" class="btn btn-primary">Upload Files</a>
                <?php if ($user_role === 'admin'): ?>
                <a href="?show_all=1" class="btn btn-secondary">Show All Files</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter and Search Controls -->
        <div class="filter-controls">
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <div class="filter-item">
                        <label for="search">Search:</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search files...">
                    </div>
                    
                    <div class="filter-item">
                        <label for="type">File Type:</label>
                        <select id="type" name="type">
                            <option value="">All Types</option>
                            <option value="image/" <?php echo $file_type === 'image/' ? 'selected' : ''; ?>>Images</option>
                            <option value="video/" <?php echo $file_type === 'video/' ? 'selected' : ''; ?>>Videos</option>
                            <option value="audio/" <?php echo $file_type === 'audio/' ? 'selected' : ''; ?>>Audio</option>
                            <option value="text/" <?php echo $file_type === 'text/' ? 'selected' : ''; ?>>Text</option>
                            <option value="application/pdf" <?php echo $file_type === 'application/pdf' ? 'selected' : ''; ?>>PDF</option>
                        </select>
                    </div>
                    
                    <div class="filter-item">
                        <label for="dir">Directory:</label>
                        <select id="dir" name="dir">
                            <option value="">All Directories</option>
                            <?php foreach ($user_dirs as $dir): ?>
                            <option value="<?php echo htmlspecialchars($dir); ?>" <?php echo $current_dir === $dir ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dir ?: 'Root'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-item">
                        <label for="sort">Sort By:</label>
                        <select id="sort" name="sort">
                            <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                            <option value="size" <?php echo $sort_by === 'size' ? 'selected' : ''; ?>>Size</option>
                            <option value="uploaded_at" <?php echo $sort_by === 'uploaded_at' ? 'selected' : ''; ?>>Date</option>
                            <option value="type" <?php echo $sort_by === 'type' ? 'selected' : ''; ?>>Type</option>
                        </select>
                    </div>
                    
                    <div class="filter-item">
                        <label for="order">Order:</label>
                        <select id="order" name="order">
                            <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                            <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>Descending</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="file-browser.php" class="btn btn-secondary">Clear</a>
                </div>
                
                <?php if (isset($_GET['show_all'])): ?>
                <input type="hidden" name="show_all" value="1">
                <?php endif; ?>
            </form>
        </div>

        <!-- File List -->
        <div class="file-browser">
            <?php if (empty($files)): ?>
            <div class="empty-state">
                <h3>No files found</h3>
                <p>No files match your current filters or you haven't uploaded any files yet.</p>
                <a href="upload.php" class="btn btn-primary">Upload Your First File</a>
            </div>
            <?php else: ?>
            <div class="file-stats">
                <p>Showing <?php echo count($files); ?> file(s)</p>
            </div>
            
            <div class="file-list">
                <?php include 'templates/file-list.html'; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'templates/footer.html'; ?>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/file-browser.js"></script>
</body>
</html>
