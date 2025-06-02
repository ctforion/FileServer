<?php
require_once 'includes/config.php';
require_once 'includes/auth-functions.php';
require_once 'includes/functions.php';

// Start session and check admin authentication
session_start();
require_authentication();

$current_user = get_current_user();
if ($current_user['role'] !== 'admin') {
    redirect_to('dashboard.php');
}

// Get log files
$log_files = array(
    'access.log' => 'Access Log',
    'error.log' => 'Error Log',
    'security.log' => 'Security Log',
    'admin.log' => 'Admin Actions',
    'file-operations.log' => 'File Operations'
);

$selected_log = isset($_GET['log']) ? $_GET['log'] : 'access.log';
$lines_to_show = isset($_GET['lines']) ? (int)$_GET['lines'] : 50;

// Read log file
$log_content = array();
if (isset($log_files[$selected_log])) {
    $log_path = STORAGE_DIR . '/logs/' . $selected_log;
    if (file_exists($log_path)) {
        $all_lines = file($log_path, FILE_IGNORE_NEW_LINES);
        $log_content = array_slice(array_reverse($all_lines), 0, $lines_to_show);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Viewer - FileServer Admin</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .log-viewer {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 20px;
            border-radius: 5px;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            margin: 20px 0;
        }
        .log-controls {
            margin: 20px 0;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .log-line {
            margin: 2px 0;
            padding: 2px 0;
        }
        .log-line.error { color: #ff6b6b; }
        .log-line.security { color: #ffd93d; }
        .log-line.admin { color: #6bcf7f; }
        .log-line.info { color: #74c0fc; }
        .log-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'templates/header.html'; ?>
    <?php include 'templates/navigation.html'; ?>
    
    <div class="container">
        <div class="admin-header">
            <h1>📋 Log Viewer</h1>
            <p>Monitor system activity and events</p>
        </div>

        <div class="log-controls">
            <label for="log-select">Log File:</label>
            <select id="log-select" onchange="changeLog()">
                <?php foreach ($log_files as $file => $label): ?>
                <option value="<?php echo $file; ?>" <?php echo $selected_log === $file ? 'selected' : ''; ?>>
                    <?php echo $label; ?>
                </option>
                <?php endforeach; ?>
            </select>

            <label for="lines-select">Lines:</label>
            <select id="lines-select" onchange="changeLines()">
                <option value="25" <?php echo $lines_to_show === 25 ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo $lines_to_show === 50 ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo $lines_to_show === 100 ? 'selected' : ''; ?>>100</option>
                <option value="200" <?php echo $lines_to_show === 200 ? 'selected' : ''; ?>>200</option>
            </select>

            <button onclick="refreshLogs()" class="btn btn-primary">🔄 Refresh</button>
            <button onclick="clearLog()" class="btn btn-warning">🗑️ Clear Log</button>
        </div>

        <div class="log-stats">
            <div class="stat-card">
                <h3>📁 Log File</h3>
                <div class="stat-number"><?php echo $log_files[$selected_log]; ?></div>
            </div>
            <div class="stat-card">
                <h3>📄 Total Lines</h3>
                <div class="stat-number"><?php echo count($log_content); ?></div>
            </div>
            <div class="stat-card">
                <h3>📊 File Size</h3>
                <div class="stat-number">
                    <?php 
                    $log_path = STORAGE_DIR . '/logs/' . $selected_log;
                    echo file_exists($log_path) ? format_file_size(filesize($log_path)) : '0 B';
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>🕒 Last Modified</h3>
                <div class="stat-number">
                    <?php 
                    $log_path = STORAGE_DIR . '/logs/' . $selected_log;
                    echo file_exists($log_path) ? date('M j, Y g:i A', filemtime($log_path)) : 'N/A';
                    ?>
                </div>
            </div>
        </div>

        <div class="log-viewer" id="log-content">
            <?php if (empty($log_content)): ?>
                <div style="text-align: center; color: #999; padding: 40px;">
                    📝 No log entries found or log file is empty
                </div>
            <?php else: ?>
                <?php foreach ($log_content as $line): ?>
                    <?php
                    $class = 'info';
                    if (strpos($line, '[ERROR]') !== false) $class = 'error';
                    elseif (strpos($line, '[SECURITY]') !== false) $class = 'security';
                    elseif (strpos($line, '[ADMIN]') !== false) $class = 'admin';
                    ?>
                    <div class="log-line <?php echo $class; ?>"><?php echo htmlspecialchars($line); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'templates/footer.html'; ?>
    
    <script>
        function changeLog() {
            const log = document.getElementById('log-select').value;
            const lines = document.getElementById('lines-select').value;
            window.location.href = `log-viewer.php?log=${log}&lines=${lines}`;
        }

        function changeLines() {
            const log = document.getElementById('log-select').value;
            const lines = document.getElementById('lines-select').value;
            window.location.href = `log-viewer.php?log=${log}&lines=${lines}`;
        }

        function refreshLogs() {
            location.reload();
        }

        function clearLog() {
            const log = document.getElementById('log-select').value;
            if (confirm(`Are you sure you want to clear the ${log} file? This action cannot be undone.`)) {
                fetch('api/admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'clear_log',
                        log_file: log
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error clearing log: ' + data.message);
                    }
                });
            }
        }

        // Auto-refresh every 30 seconds
        setInterval(refreshLogs, 30000);
    </script>
</body>
</html>
