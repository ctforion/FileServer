<?php
/**
 * Admin Dashboard Template
 * System administration interface with statistics and management tools
 */

defined('FILESERVER_ACCESS') or die('Direct access denied');

$stats = $data['stats'] ?? [];
$systemInfo = $data['system_info'] ?? [];
$recentActivity = $data['recent_activity'] ?? [];
$alerts = $data['alerts'] ?? [];
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Admin Dashboard</h1>
            <p class="text-muted mb-0">System overview and management</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#systemInfoModal">
                <i class="fas fa-info-circle"></i> System Info
            </button>
            <button class="btn btn-outline-warning" onclick="runMaintenance()">
                <i class="fas fa-tools"></i> Maintenance
            </button>
            <button class="btn btn-outline-success" onclick="createBackup()">
                <i class="fas fa-download"></i> Backup
            </button>
        </div>
    </div>

    <!-- System Alerts -->
    <?php if (!empty($alerts)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <?php foreach ($alerts as $alert): ?>
            <div class="alert alert-<?= $alert['type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?= $alert['icon'] ?? 'info-circle' ?> me-2"></i>
                <strong><?= htmlspecialchars($alert['title'] ?? '') ?></strong>
                <?= htmlspecialchars($alert['message'] ?? '') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Total Users</div>
                            <div class="h5 mb-0 font-weight-bold"><?= number_format($stats['total_users'] ?? 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-2 small">
                        <span class="text-success">
                            <i class="fas fa-arrow-up"></i> 
                            <?= $stats['new_users_today'] ?? 0 ?> today
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Total Files</div>
                            <div class="h5 mb-0 font-weight-bold"><?= number_format($stats['total_files'] ?? 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-2 small">
                        <span class="text-light">
                            <i class="fas fa-upload"></i> 
                            <?= $stats['files_uploaded_today'] ?? 0 ?> uploaded today
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Storage Used</div>
                            <div class="h5 mb-0 font-weight-bold"><?= format_bytes($stats['storage_used'] ?? 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hdd fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-2 small">
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-white" 
                                 style="width: <?= min(100, ($stats['storage_used'] ?? 0) / ($stats['storage_limit'] ?? 1) * 100) ?>%"></div>
                        </div>
                        <span class="text-light">
                            <?= number_format(($stats['storage_used'] ?? 0) / ($stats['storage_limit'] ?? 1) * 100, 1) ?>% of 
                            <?= format_bytes($stats['storage_limit'] ?? 0) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Downloads</div>
                            <div class="h5 mb-0 font-weight-bold"><?= number_format($stats['total_downloads'] ?? 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-download fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-2 small">
                        <span class="text-light">
                            <i class="fas fa-arrow-down"></i> 
                            <?= $stats['downloads_today'] ?? 0 ?> today
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Charts Column -->
        <div class="col-xl-8 col-lg-7">
            <!-- Usage Chart -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Usage Overview</h6>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Last 30 Days
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-period="7">Last 7 Days</a></li>
                            <li><a class="dropdown-item" href="#" data-period="30">Last 30 Days</a></li>
                            <li><a class="dropdown-item" href="#" data-period="90">Last 90 Days</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="usageChart" width="100%" height="40"></canvas>
                    </div>
                </div>
            </div>

            <!-- File Types Chart -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">File Types Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="fileTypesChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <span class="mr-2">
                            <i class="fas fa-circle text-primary"></i> Images
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-success"></i> Documents
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-info"></i> Videos
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-warning"></i> Other
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-xl-4 col-lg-5">
            <!-- System Health -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">System Health</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small">CPU Usage</span>
                            <span class="small text-muted"><?= number_format($systemInfo['cpu_usage'] ?? 0, 1) ?>%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" 
                                 style="width: <?= $systemInfo['cpu_usage'] ?? 0 ?>%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small">Memory Usage</span>
                            <span class="small text-muted"><?= number_format($systemInfo['memory_usage'] ?? 0, 1) ?>%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" 
                                 style="width: <?= $systemInfo['memory_usage'] ?? 0 ?>%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small">Disk Usage</span>
                            <span class="small text-muted"><?= number_format($systemInfo['disk_usage'] ?? 0, 1) ?>%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning" 
                                 style="width: <?= $systemInfo['disk_usage'] ?? 0 ?>%"></div>
                        </div>
                    </div>

                    <div class="row text-center">
                        <div class="col-6">
                            <div class="small text-muted">Uptime</div>
                            <div class="font-weight-bold"><?= $systemInfo['uptime'] ?? 'Unknown' ?></div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Load Avg</div>
                            <div class="font-weight-bold"><?= $systemInfo['load_avg'] ?? 'Unknown' ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                </div>
                <div class="card-body">
                    <div class="activity-list">
                        <?php if (!empty($recentActivity)): ?>
                            <?php foreach (array_slice($recentActivity, 0, 10) as $activity): ?>
                            <div class="activity-item d-flex align-items-center mb-3">
                                <div class="activity-icon me-3">
                                    <i class="fas <?= getActivityIcon($activity['type']) ?> 
                                       text-<?= getActivityColor($activity['type']) ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text small">
                                        <?= htmlspecialchars($activity['description']) ?>
                                    </div>
                                    <div class="activity-time text-muted small">
                                        <?= timeAgo($activity['created_at']) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No recent activity</p>
                        <?php endif; ?>
                    </div>
                    <a href="/admin/activity" class="btn btn-outline-primary btn-sm w-100 mt-2">
                        View All Activity
                    </a>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="location.href='/admin/users'">
                            <i class="fas fa-users"></i> Manage Users
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="location.href='/admin/files'">
                            <i class="fas fa-file"></i> Manage Files
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="location.href='/admin/settings'">
                            <i class="fas fa-cog"></i> System Settings
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="location.href='/admin/logs'">
                            <i class="fas fa-file-alt"></i> View Logs
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="clearCache()">
                            <i class="fas fa-broom"></i> Clear Cache
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Users -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Users</h6>
                    <a href="/admin/users" class="btn btn-primary btn-sm">
                        <i class="fas fa-users"></i> Manage All Users
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                    <th>Last Active</th>
                                    <th>Files</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="recentUsersTable">
                                <!-- Users will be loaded via JavaScript -->
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Info Modal -->
<div class="modal fade" id="systemInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">System Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Server Information</h6>
                        <table class="table table-sm">
                            <tr><td>PHP Version</td><td><?= PHP_VERSION ?></td></tr>
                            <tr><td>Server Software</td><td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></td></tr>
                            <tr><td>Operating System</td><td><?= PHP_OS ?></td></tr>
                            <tr><td>Architecture</td><td><?= php_uname('m') ?></td></tr>
                            <tr><td>Memory Limit</td><td><?= ini_get('memory_limit') ?></td></tr>
                            <tr><td>Upload Limit</td><td><?= ini_get('upload_max_filesize') ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Application Information</h6>
                        <table class="table table-sm">
                            <tr><td>App Version</td><td><?= APP_VERSION ?></td></tr>
                            <tr><td>Database Type</td><td><?= DB_TYPE ?></td></tr>
                            <tr><td>Debug Mode</td><td><?= DEBUG ? 'Enabled' : 'Disabled' ?></td></tr>
                            <tr><td>Timezone</td><td><?= TIMEZONE ?></td></tr>
                            <tr><td>Session Lifetime</td><td><?= SESSION_LIFETIME ?> seconds</td></tr>
                            <tr><td>Cache Enabled</td><td><?= ENABLE_CACHE ? 'Yes' : 'No' ?></td></tr>
                        </table>
                    </div>
                </div>
                
                <h6 class="mt-3">Loaded Extensions</h6>
                <div class="row">
                    <?php
                    $extensions = get_loaded_extensions();
                    sort($extensions);
                    foreach (array_chunk($extensions, ceil(count($extensions) / 3)) as $chunk):
                    ?>
                    <div class="col-md-4">
                        <ul class="list-unstyled small">
                            <?php foreach ($chunk as $ext): ?>
                            <li><i class="fas fa-check text-success"></i> <?= $ext ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="downloadSystemInfo()">
                    <i class="fas fa-download"></i> Download Report
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    initializeUsageChart();
    initializeFileTypesChart();
    
    // Load recent users
    loadRecentUsers();
    
    // Set up auto-refresh
    setInterval(refreshDashboard, 30000); // Refresh every 30 seconds
});

function initializeUsageChart() {
    const ctx = document.getElementById('usageChart').getContext('2d');
    
    fetch('/api/admin/stats/usage-chart', {
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Uploads',
                        data: data.uploads,
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        fill: true
                    }, {
                        label: 'Downloads',
                        data: data.downloads,
                        borderColor: '#1cc88a',
                        backgroundColor: 'rgba(28, 200, 138, 0.1)',
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    });
}

function initializeFileTypesChart() {
    const ctx = document.getElementById('fileTypesChart').getContext('2d');
    
    fetch('/api/admin/stats/file-types', {
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'],
                        hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#f4b619'],
                        hoverBorderColor: "rgba(234, 236, 244, 1)",
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    });
}

function loadRecentUsers() {
    fetch('/api/admin/users/recent', {
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        }
    })
    .then(response => response.json())
    .then(data => {
        const tbody = document.getElementById('recentUsersTable');
        
        if (data.success && data.users.length > 0) {
            tbody.innerHTML = data.users.map(user => `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm me-2">
                                ${user.avatar ? 
                                    `<img src="/uploads/avatars/${user.avatar}" class="rounded-circle" width="32" height="32">` :
                                    `<div class="avatar-placeholder rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:0.8rem;">${user.username.charAt(0).toUpperCase()}</div>`
                                }
                            </div>
                            <div>
                                <div class="fw-bold">${user.display_name || user.username}</div>
                                <div class="small text-muted">@${user.username}</div>
                            </div>
                        </div>
                    </td>
                    <td>${user.email}</td>
                    <td><span class="badge bg-${getRoleBadgeColor(user.role)} text-white">${user.role}</span></td>
                    <td>${formatDate(user.created_at)}</td>
                    <td>${user.last_login ? timeAgo(user.last_login) : 'Never'}</td>
                    <td>${user.file_count || 0}</td>
                    <td>
                        <span class="badge bg-${user.active ? 'success' : 'secondary'}">
                            ${user.active ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-link btn-sm" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/admin/users/${user.id}">View Details</a></li>
                                <li><a class="dropdown-item" href="#" onclick="editUser(${user.id})">Edit</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="suspendUser(${user.id})">
                                    ${user.active ? 'Suspend' : 'Activate'}
                                </a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No users found</td></tr>';
        }
    })
    .catch(error => {
        document.getElementById('recentUsersTable').innerHTML = 
            '<tr><td colspan="8" class="text-center text-danger">Error loading users</td></tr>';
    });
}

function refreshDashboard() {
    // Refresh stats and activity
    fetch('/api/admin/stats/realtime', {
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update stats cards
            updateStatsCards(data.stats);
            // Update system health
            updateSystemHealth(data.system);
        }
    })
    .catch(error => {
        console.error('Error refreshing dashboard:', error);
    });
}

function updateStatsCards(stats) {
    // Update the stats cards with new data
    // Implementation would update the displayed numbers
}

function updateSystemHealth(system) {
    // Update system health indicators
    // Implementation would update the progress bars
}

function runMaintenance() {
    if (confirm('This will run system maintenance tasks. Continue?')) {
        showSpinner('Running maintenance...');
        
        fetch('/api/admin/maintenance', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('token'),
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ tasks: ['cleanup', 'optimize', 'backup'] })
        })
        .then(response => response.json())
        .then(data => {
            hideSpinner();
            if (data.success) {
                showAlert('Maintenance completed successfully', 'success');
            } else {
                showAlert('Maintenance failed: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            hideSpinner();
            showAlert('Error: ' + error.message, 'danger');
        });
    }
}

function createBackup() {
    showSpinner('Creating backup...');
    
    fetch('/api/admin/backup', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        }
    })
    .then(response => response.json())
    .then(data => {
        hideSpinner();
        if (data.success) {
            showAlert('Backup created successfully', 'success');
            if (data.download_url) {
                window.open(data.download_url, '_blank');
            }
        } else {
            showAlert('Backup failed: ' + (data.message || 'Unknown error'), 'danger');
        }
    })
    .catch(error => {
        hideSpinner();
        showAlert('Error: ' + error.message, 'danger');
    });
}

function clearCache() {
    if (confirm('This will clear all cached data. Continue?')) {
        showSpinner('Clearing cache...');
        
        fetch('/api/admin/cache/clear', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('token')
            }
        })
        .then(response => response.json())
        .then(data => {
            hideSpinner();
            if (data.success) {
                showAlert('Cache cleared successfully', 'success');
            } else {
                showAlert('Failed to clear cache: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            hideSpinner();
            showAlert('Error: ' + error.message, 'danger');
        });
    }
}

function downloadSystemInfo() {
    window.open('/api/admin/system/report', '_blank');
}

function getRoleBadgeColor(role) {
    const colors = {
        'admin': 'danger',
        'moderator': 'warning',
        'user': 'primary'
    };
    return colors[role] || 'secondary';
}

function getActivityIcon(type) {
    const icons = {
        'user_login': 'fa-sign-in-alt',
        'user_register': 'fa-user-plus',
        'file_upload': 'fa-upload',
        'file_download': 'fa-download',
        'file_delete': 'fa-trash',
        'admin_action': 'fa-cog'
    };
    return icons[type] || 'fa-circle';
}

function getActivityColor(type) {
    const colors = {
        'user_login': 'success',
        'user_register': 'primary',
        'file_upload': 'info',
        'file_download': 'warning',
        'file_delete': 'danger',
        'admin_action': 'secondary'
    };
    return colors[type] || 'muted';
}

function timeAgo(date) {
    const time = Math.floor((new Date() - new Date(date)) / 1000);
    
    if (time < 60) return 'just now';
    if (time < 3600) return Math.floor(time / 60) + ' minutes ago';
    if (time < 86400) return Math.floor(time / 3600) + ' hours ago';
    if (time < 2592000) return Math.floor(time / 86400) + ' days ago';
    if (time < 31536000) return Math.floor(time / 2592000) + ' months ago';
    return Math.floor(time / 31536000) + ' years ago';
}

function formatDate(date) {
    return new Date(date).toLocaleDateString();
}
</script>

<style>
.activity-item {
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #eee;
}

.activity-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.activity-icon {
    width: 30px;
    text-align: center;
}

.avatar-placeholder {
    font-weight: bold;
}

.chart-area {
    position: relative;
    height: 320px;
}

.chart-pie {
    position: relative;
    height: 250px;
}

.stats-card {
    transition: transform 0.2s;
}

.stats-card:hover {
    transform: translateY(-2px);
}

.progress {
    border-radius: 10px;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #5a5c69;
}

.badge {
    font-size: 0.75em;
}

@media (max-width: 768px) {
    .chart-area {
        height: 250px;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
}
</style>

<?php
// Helper functions
function format_bytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
?>
