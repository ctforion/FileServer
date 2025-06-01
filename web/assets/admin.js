// Admin Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeTabs();
    loadUsers();
    loadFiles();
    loadLogs();
    initializeEventListeners();
});

// Tab System
function initializeTabs() {
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
    
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href.startsWith('#')) {
                e.preventDefault();
                showTab(href.substring(1));
                
                // Update active state
                sidebarLinks.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
            }
        });
    });
}

function showTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab
    const targetTab = document.getElementById(tabId);
    if (targetTab) {
        targetTab.classList.add('active');
        
        // Load data for specific tabs
        switch(tabId) {
            case 'users':
                loadUsers();
                break;
            case 'files':
                loadFiles();
                break;
            case 'logs':
                loadLogs();
                break;
        }
    }
}

// User Management
function loadUsers() {
    fetch('../api/admin.php?action=get_users', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayUsers(data.data);
        } else {
            console.error('Error loading users:', data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function displayUsers(users) {
    const tbody = document.querySelector('#usersTable tbody');
    tbody.innerHTML = '';
    
    users.forEach(user => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(user.username)}</td>
            <td>${escapeHtml(user.email)}</td>
            <td><span class="badge badge-${user.role}">${user.role}</span></td>
            <td><span class="badge badge-${user.status}">${user.status}</span></td>
            <td>${user.last_login ? formatDate(user.last_login) : 'Never'}</td>
            <td>
                <button class="btn btn-small btn-primary" onclick="editUser('${user.id}')">Edit</button>
                <button class="btn btn-small btn-danger" onclick="deleteUser('${user.id}')">Delete</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function showCreateUserModal() {
    document.getElementById('createUserModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function createUser(formData) {
    fetch('../api/admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'create_user',
            ...formData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal('createUserModal');
            loadUsers();
            showNotification('User created successfully', 'success');
        } else {
            showNotification(data.error || 'Error creating user', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error creating user', 'error');
    });
}

function editUser(userId) {
    // Implementation for editing user
    console.log('Edit user:', userId);
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user?')) {
        fetch('../api/admin.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'delete_user',
                user_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadUsers();
                showNotification('User deleted successfully', 'success');
            } else {
                showNotification(data.error || 'Error deleting user', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error deleting user', 'error');
        });
    }
}

// File Management
function loadFiles() {
    const directory = document.getElementById('fileDirectoryFilter').value;
    const search = document.getElementById('fileSearchFilter').value;
    
    let url = '../api/list.php?csrf_token=' + getCsrfToken();
    if (directory) url += '&dir=' + encodeURIComponent(directory);
    if (search) url += '&search=' + encodeURIComponent(search);
    
    fetch(url, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayFiles(data.data.files);
        } else {
            console.error('Error loading files:', data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function displayFiles(files) {
    const tbody = document.querySelector('#filesTable tbody');
    tbody.innerHTML = '';
    
    files.forEach(file => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(file.name || file.original_name)}</td>
            <td>${escapeHtml(file.path)}</td>
            <td>${file.readable_size || formatFileSize(file.size || 0)}</td>
            <td>${escapeHtml(file.uploaded_by || 'System')}</td>
            <td>${formatDate(file.created || file.upload_date)}</td>
            <td>${file.download_count || 0}</td>
            <td>
                <button class="btn btn-small btn-secondary" onclick="downloadFile('${file.id}')">Download</button>
                <button class="btn btn-small btn-danger" onclick="deleteFile('${file.id}')">Delete</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function downloadFile(fileId) {
    window.open(`../api/download.php?file_id=${fileId}&csrf_token=${getCsrfToken()}`, '_blank');
}

function deleteFile(fileId) {
    if (confirm('Are you sure you want to delete this file?')) {
        fetch('../api/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `file_id=${fileId}&csrf_token=${getCsrfToken()}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadFiles();
                showNotification('File deleted successfully', 'success');
            } else {
                showNotification(data.error || 'Error deleting file', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error deleting file', 'error');
        });
    }
}

// Log Management
function loadLogs() {
    const level = document.getElementById('logLevelFilter').value;
    
    let url = '../api/admin.php?action=get_logs';
    if (level) url += '&level=' + encodeURIComponent(level);
    
    fetch(url, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayLogs(data.data);
        } else {
            console.error('Error loading logs:', data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function displayLogs(logs) {
    const logsContent = document.getElementById('logsContent');
    logsContent.textContent = logs.join('\n');
    logsContent.scrollTop = logsContent.scrollHeight;
}

function refreshLogs() {
    loadLogs();
}

function clearLogs() {
    if (confirm('Are you sure you want to clear all logs?')) {
        fetch('../api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'clear_logs',
                csrf_token: getCsrfToken()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadLogs();
                showNotification('Logs cleared successfully', 'success');
            } else {
                showNotification(data.error || 'Error clearing logs', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error clearing logs', 'error');
        });
    }
}

// Maintenance Functions
function runDatabaseCleanup() {
    if (confirm('Are you sure you want to run database cleanup?')) {
        fetch('../api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'cleanup_database',
                csrf_token: getCsrfToken()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Database cleanup completed successfully', 'success');
            } else {
                showNotification(data.error || 'Error during cleanup', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error during cleanup', 'error');
        });
    }
}

function createBackup() {
    fetch('../api/admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'create_backup',
            csrf_token: getCsrfToken()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Backup created successfully', 'success');
            if (data.backup_path) {
                console.log('Backup saved to:', data.backup_path);
            }
        } else {
            showNotification(data.error || 'Error creating backup', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error creating backup', 'error');
    });
}

function showSystemInfo() {
    fetch('../api/admin.php?action=system_info', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('System Info:\n' + JSON.stringify(data.data, null, 2));
        } else {
            showNotification(data.error || 'Error getting system info', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error getting system info', 'error');
    });
}

// Event Listeners
function initializeEventListeners() {
    // Create User Form
    const createUserForm = document.getElementById('createUserForm');
    if (createUserForm) {
        createUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            createUser(data);
        });
    }
    
    // Settings Form
    const settingsForm = document.getElementById('settingsForm');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            saveSettings(data);
        });
    }
    
    // File Filters
    const fileDirectoryFilter = document.getElementById('fileDirectoryFilter');
    const fileSearchFilter = document.getElementById('fileSearchFilter');
    
    if (fileDirectoryFilter) {
        fileDirectoryFilter.addEventListener('change', loadFiles);
    }
    
    if (fileSearchFilter) {
        let searchTimeout;
        fileSearchFilter.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(loadFiles, 500);
        });
    }
    
    // Log Filter
    const logLevelFilter = document.getElementById('logLevelFilter');
    if (logLevelFilter) {
        logLevelFilter.addEventListener('change', loadLogs);
    }
    
    // Modal close on outside click
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
}

function saveSettings(data) {
    fetch('../api/admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'save_settings',
            ...data
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Settings saved successfully', 'success');
        } else {
            showNotification(data.error || 'Error saving settings', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error saving settings', 'error');
    });
}

// Utility Functions
function getCsrfToken() {
    const tokenInput = document.querySelector('input[name="csrf_token"]');
    return tokenInput ? tokenInput.value : '';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const k = 1024;
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + units[i];
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Style the notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 4px;
        color: white;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s;
    `;
    
    // Set background color based on type
    switch(type) {
        case 'success':
            notification.style.backgroundColor = '#27ae60';
            break;
        case 'error':
            notification.style.backgroundColor = '#e74c3c';
            break;
        case 'warning':
            notification.style.backgroundColor = '#f39c12';
            break;
        default:
            notification.style.backgroundColor = '#3498db';
    }
    
    // Add to DOM and show
    document.body.appendChild(notification);
    setTimeout(() => notification.style.opacity = '1', 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => document.body.removeChild(notification), 300);
    }, 3000);
}
