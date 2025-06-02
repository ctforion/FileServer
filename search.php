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

// Search parameters
$search_term = $_GET['q'] ?? '';
$search_type = $_GET['search_type'] ?? 'filename';
$file_type_filter = $_GET['file_type'] ?? '';
$size_min = $_GET['size_min'] ?? '';
$size_max = $_GET['size_max'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$search_results = [];
$search_performed = false;

// Perform search if search term is provided
if (!empty($search_term)) {
    $search_performed = true;
    
    // Get user's files or all files (for admin)
    if ($user_role === 'admin' && isset($_GET['search_all'])) {
        $all_files = read_json_file(STORAGE_DIR . '/data/files.json');
        $files_to_search = $all_files;
    } else {
        $files_to_search = get_user_files($current_user['id']);
    }
    
    // Perform search based on search type
    foreach ($files_to_search as $file) {
        $match = false;
        
        switch ($search_type) {
            case 'filename':
                $match = stripos($file['name'], $search_term) !== false;
                break;
            case 'content':
                // Simple content search for text files
                if (strpos($file['type'], 'text/') === 0) {
                    $file_path = STORAGE_DIR . '/storage/uploads/' . $file['filename'];
                    if (file_exists($file_path)) {
                        $content = file_get_contents($file_path);
                        $match = stripos($content, $search_term) !== false;
                    }
                }
                break;
            case 'both':
                $match = stripos($file['name'], $search_term) !== false;
                if (!$match && strpos($file['type'], 'text/') === 0) {
                    $file_path = STORAGE_DIR . '/storage/uploads/' . $file['filename'];
                    if (file_exists($file_path)) {
                        $content = file_get_contents($file_path);
                        $match = stripos($content, $search_term) !== false;
                    }
                }
                break;
        }
        
        // Apply additional filters
        if ($match) {
            // File type filter
            if (!empty($file_type_filter) && stripos($file['type'], $file_type_filter) === false) {
                continue;
            }
            
            // Size filters
            if (!empty($size_min) && $file['size'] < (int)$size_min * 1024) {
                continue;
            }
            if (!empty($size_max) && $file['size'] > (int)$size_max * 1024) {
                continue;
            }
            
            // Date filters
            if (!empty($date_from) && strtotime($file['uploaded_at']) < strtotime($date_from)) {
                continue;
            }
            if (!empty($date_to) && strtotime($file['uploaded_at']) > strtotime($date_to . ' 23:59:59')) {
                continue;
            }
            
            $search_results[] = $file;
        }
    }
    
    // Log search activity
    log_access("Search performed by user: " . $current_user['username'] . " - Term: " . $search_term);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Files - FileServer</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/forms.css">
    <link rel="stylesheet" href="assets/css/file-browser.css">
</head>
<body>
    <?php include 'templates/header.html'; ?>
    <?php include 'templates/navigation.html'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Search Files</h1>
            <p>Find your files quickly using various search criteria</p>
        </div>

        <!-- Search Form -->
        <div class="search-section">
            <?php include 'templates/search-form.html'; ?>
        </div>

        <!-- Search Results -->
        <?php if ($search_performed): ?>
        <div class="search-results">
            <div class="results-header">
                <h2>Search Results</h2>
                <p>Found <?php echo count($search_results); ?> file(s) matching "<?php echo htmlspecialchars($search_term); ?>"</p>
            </div>

            <?php if (empty($search_results)): ?>
            <div class="empty-state">
                <h3>No files found</h3>
                <p>No files match your search criteria. Try adjusting your search terms or filters.</p>
            </div>
            <?php else: ?>
            <div class="file-list">
                <?php 
                $files = $search_results; // Set for template inclusion
                include 'templates/file-list.html'; 
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Search Tips -->
        <div class="search-tips">
            <h2>Search Tips</h2>
            <div class="tips-grid">
                <div class="tip-card">
                    <h3>Filename Search</h3>
                    <p>Search by filename is the fastest method. Use partial names for better results.</p>
                </div>
                
                <div class="tip-card">
                    <h3>Content Search</h3>
                    <p>Content search works only with text files and may be slower for large files.</p>
                </div>
                
                <div class="tip-card">
                    <h3>Advanced Filters</h3>
                    <p>Use file type, size, and date filters to narrow down your search results.</p>
                </div>
                
                <div class="tip-card">
                    <h3>Search Operators</h3>
                    <p>Use quotation marks for exact phrases and wildcards for pattern matching.</p>
                </div>
            </div>
        </div>

        <!-- Quick Search Links -->
        <div class="quick-search">
            <h3>Quick Searches</h3>
            <div class="quick-links">                <a href="?q=&file_type=image/" class="quick-link">All Images</a>
                <a href="?q=&file_type=video/" class="quick-link">All Videos</a>
                <a href="?q=&file_type=audio/" class="quick-link">All Audio</a>
                <a href="?q=&file_type=application/pdf" class="quick-link">PDF Files</a>
                <a href="?q=&date_from=<?php echo date('Y-m-d', strtotime('-7 days')); ?>" class="quick-link">Recent Files</a>
            </div>
        </div>
    </div>

    <?php include 'templates/footer.html'; ?>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/search.js"></script>
</body>
</html>
