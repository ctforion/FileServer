// Admin Panel JavaScript functionality
class AdminPanel {
    constructor() {
        this.currentSection = 'dashboard';
        this.refreshInterval = null;
        this.charts = {};
        this.init();
    }

    init() {
        this.bindEvents();
        this.initCharts();
        this.startAutoRefresh();
        this.loadCurrentSection();
    }

    bindEvents() {
        // Navigation clicks
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('admin-nav-link')) {
                e.preventDefault();
                const section = e.target.dataset.section;
                this.loadSection(section);
            }
        });

        // Admin actions
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('admin-action-btn')) {
                e.preventDefault();
                this.handleAdminAction(e.target);
            }
        });

        // User management actions
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('user-action-btn')) {
                this.handleUserAction(e.target);
            }
        });

        // System actions
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('system-action-btn')) {
                this.handleSystemAction(e.target);
            }
        });

        // Security actions
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('security-action-btn')) {
                this.handleSecurityAction(e.target);
            }
        });

        // File management actions
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('file-admin-btn')) {
                this.handleFileAction(e.target);
            }
        });

        // Refresh buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('refresh-btn')) {
                this.refreshCurrentSection();
            }
        });

        // Settings form submission
        const settingsForm = document.getElementById('adminSettingsForm');
        if (settingsForm) {
            settingsForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveSettings(settingsForm);
            });
        }
    }

    loadCurrentSection() {
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action') || 'dashboard';
        this.loadSection(action);
    }

    async loadSection(section) {
        this.currentSection = section;
        
        // Update navigation active state
        document.querySelectorAll('.admin-nav a').forEach(link => {
            link.classList.toggle('active', link.href.includes(`action=${section}`));
        });

        // Load section content
        switch (section) {
            case 'dashboard':
                await this.loadDashboard();
                break;
            case 'users':
                await this.loadUsers();
                break;
            case 'files':
                await this.loadFiles();
                break;
            case 'security':
                await this.loadSecurity();
                break;
            case 'system':
                await this.loadSystem();
                break;
            case 'logs':
                await this.loadLogs();
                break;
            case 'settings':
                await this.loadSettings();
                break;
        }
    }

    async loadDashboard() {
        try {
            const response = await fetch('api/admin.php?action=dashboard');
            const data = await response.json();
            
            if (data.success) {
                this.updateDashboardStats(data.stats);
                this.updateSystemHealth(data.health);
                this.updateCharts(data.charts);
            }
        } catch (error) {
            showNotification('Failed to load dashboard data', 'error');
        }
    }

    async loadUsers() {
        try {
            const response = await fetch('api/users.php?action=list&admin=1');
            const data = await response.json();
            
            if (data.success) {
                this.updateUsersTable(data.users);
            }
        } catch (error) {
            showNotification('Failed to load users', 'error');
        }
    }

    async loadFiles() {
        try {
            const response = await fetch('api/files.php?action=admin_list');
            const data = await response.json();
            
            if (data.success) {
                this.updateFilesTable(data.files);
                this.updateFileStats(data.stats);
            }
        } catch (error) {
            showNotification('Failed to load files', 'error');
        }
    }

    async loadSecurity() {
        try {
            const response = await fetch('api/admin.php?action=security');
            const data = await response.json();
            
            if (data.success) {
                this.updateSecurityInfo(data.security);
                this.updateBlockedIPs(data.blocked_ips);
                this.updateSecurityLogs(data.security_logs);
            }
        } catch (error) {
            showNotification('Failed to load security info', 'error');
        }
    }

    async loadSystem() {
        try {
            const response = await fetch('api/admin.php?action=system');
            const data = await response.json();
            
            if (data.success) {
                this.updateSystemInfo(data.system);
                this.updateMaintenanceStatus(data.maintenance);
            }
        } catch (error) {
            showNotification('Failed to load system info', 'error');
        }
    }

    async loadLogs() {
        try {
            const response = await fetch('api/admin.php?action=logs');
            const data = await response.json();
            
            if (data.success) {
                this.updateLogsDisplay(data.logs);
            }
        } catch (error) {
            showNotification('Failed to load logs', 'error');
        }
    }

    async loadSettings() {
        try {
            const response = await fetch('api/admin.php?action=settings');
            const data = await response.json();
            
            if (data.success) {
                this.populateSettingsForm(data.settings);
            }
        } catch (error) {
            showNotification('Failed to load settings', 'error');
        }
    }

    // Dashboard updates
    updateDashboardStats(stats) {
        Object.keys(stats).forEach(key => {
            const element = document.getElementById(`stat-${key}`);
            if (element) {
                element.textContent = stats[key];
            }
        });
    }

    updateSystemHealth(health) {
        const healthContainer = document.getElementById('systemHealth');
        if (!healthContainer) return;

        healthContainer.innerHTML = '';
        
        health.forEach(item => {
            const healthItem = document.createElement('div');
            healthItem.className = 'health-item';
            healthItem.innerHTML = `
                <span class="health-label">${item.name}</span>
                <span class="health-status ${item.status}">${item.value}</span>
            `;
            healthContainer.appendChild(healthItem);
        });
    }

    updateCharts(chartData) {
        // Update upload chart
        if (chartData.uploads) {
            this.updateUploadChart(chartData.uploads);
        }
        
        // Update storage chart
        if (chartData.storage) {
            this.updateStorageChart(chartData.storage);
        }
        
        // Update file types chart
        if (chartData.file_types) {
            this.updateFileTypesChart(chartData.file_types);
        }
    }

    updateUploadChart(data) {
        const chartContainer = document.getElementById('uploadChart');
        if (!chartContainer) return;

        chartContainer.innerHTML = '';
        
        const maxValue = Math.max(...data.map(d => d.count));
        
        data.forEach(item => {
            const bar = document.createElement('div');
            bar.className = 'chart-bar';
            
            const height = maxValue > 0 ? (item.count / maxValue) * 100 : 0;
            
            bar.innerHTML = `
                <div class="bar-fill" style="height: ${height}%"></div>
                <div class="bar-label">${item.label}</div>
                <div class="bar-value">${item.count}</div>
            `;
            
            chartContainer.appendChild(bar);
        });
    }

    updateStorageChart(data) {
        const chartContainer = document.getElementById('storageChart');
        if (!chartContainer) return;

        const usedPercent = (data.used / data.total) * 100;
        
        chartContainer.innerHTML = `
            <div class="disk-stats">
                <span>Used: ${formatFileSize(data.used)}</span>
                <span>Free: ${formatFileSize(data.free)}</span>
            </div>
            <div class="disk-bar">
                <div class="disk-fill" style="width: ${usedPercent}%"></div>
            </div>
            <div class="disk-percent">${Math.round(usedPercent)}%</div>
        `;
    }

    updateFileTypesChart(data) {
        const chartContainer = document.getElementById('fileTypesChart');
        if (!chartContainer) return;

        chartContainer.innerHTML = '';
        
        const total = data.reduce((sum, item) => sum + item.count, 0);
        
        data.forEach(item => {
            const percent = total > 0 ? (item.count / total) * 100 : 0;
            
            const typeItem = document.createElement('div');
            typeItem.className = 'file-type-item';
            typeItem.innerHTML = `
                <div class="type-name">${item.type}</div>
                <div class="type-count">${item.count}</div>
                <div class="type-bar">
                    <div class="type-fill" style="width: ${percent}%"></div>
                </div>
                <div class="type-percent">${Math.round(percent)}%</div>
            `;
            
            chartContainer.appendChild(typeItem);
        });
    }

    // User management
    updateUsersTable(users) {
        const tableBody = document.querySelector('#usersTable tbody');
        if (!tableBody) return;

        tableBody.innerHTML = '';
        
        users.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="user-info">
                        <span class="username">${user.username}</span>
                        <span class="user-role ${user.role}">${user.role}</span>
                    </div>
                </td>
                <td>${user.email || 'N/A'}</td>
                <td>${formatDate(user.created_at)}</td>
                <td>${formatDate(user.last_login || 'Never')}</td>
                <td>
                    <span class="status-badge ${user.status}">${user.status}</span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-small btn-primary user-action-btn" 
                                data-action="edit" data-user-id="${user.id}">
                            Edit
                        </button>
                        <button class="btn btn-small btn-secondary user-action-btn" 
                                data-action="toggle" data-user-id="${user.id}">
                            ${user.status === 'active' ? 'Suspend' : 'Activate'}
                        </button>
                        <button class="btn btn-small btn-danger user-action-btn" 
                                data-action="delete" data-user-id="${user.id}">
                            Delete
                        </button>
                    </div>
                </td>
            `;
            tableBody.appendChild(row);
        });
    }

    updateFilesTable(files) {
        const tableBody = document.querySelector('#filesTable tbody');
        if (!tableBody) return;

        tableBody.innerHTML = '';
        
        files.forEach(file => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="file-info">
                        <span class="file-icon">${this.getFileIcon(file.type)}</span>
                        <span class="filename">${file.original_name}</span>
                    </div>
                </td>
                <td>${formatFileSize(file.size)}</td>
                <td>${file.type}</td>
                <td>${file.uploaded_by_username || 'System'}</td>
                <td>${formatDate(file.uploaded_at)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-small btn-primary file-admin-btn" 
                                data-action="download" data-file-id="${file.id}">
                            Download
                        </button>
                        <button class="btn btn-small btn-secondary file-admin-btn" 
                                data-action="details" data-file-id="${file.id}">
                            Details
                        </button>
                        <button class="btn btn-small btn-danger file-admin-btn" 
                                data-action="delete" data-file-id="${file.id}">
                            Delete
                        </button>
                    </div>
                </td>
            `;
            tableBody.appendChild(row);
        });
    }

    updateFileStats(stats) {
        const statsContainer = document.getElementById('fileStats');
        if (!statsContainer) return;

        statsContainer.innerHTML = `
            <div class="stat-card">
                <h4>Total Files</h4>
                <div class="stat-number">${stats.total_files}</div>
            </div>
            <div class="stat-card">
                <h4>Total Size</h4>
                <div class="stat-number">${formatFileSize(stats.total_size)}</div>
            </div>
            <div class="stat-card">
                <h4>Today's Uploads</h4>
                <div class="stat-number">${stats.uploads_today}</div>
            </div>
        `;
    }

    // Security management
    updateSecurityInfo(security) {
        const container = document.getElementById('securityInfo');
        if (!container) return;

        container.innerHTML = `
            <div class="security-stat">
                <label>Failed Login Attempts (24h):</label>
                <span class="stat-value">${security.failed_logins}</span>
            </div>
            <div class="security-stat">
                <label>Blocked IP Addresses:</label>
                <span class="stat-value">${security.blocked_ips}</span>
            </div>
            <div class="security-stat">
                <label>Quarantined Files:</label>
                <span class="stat-value">${security.quarantined_files}</span>
            </div>
        `;
    }

    updateBlockedIPs(blockedIPs) {
        const container = document.getElementById('blockedIPsList');
        if (!container) return;

        container.innerHTML = '';
        
        if (blockedIPs.length === 0) {
            container.innerHTML = '<p>No IP addresses are currently blocked.</p>';
            return;
        }

        blockedIPs.forEach(ip => {
            const ipItem = document.createElement('div');
            ipItem.className = 'blocked-ip-item';
            ipItem.innerHTML = `
                <span class="ip-address">${ip.address}</span>
                <span class="block-reason">${ip.reason || 'Manual block'}</span>
                <span class="block-date">${formatDate(ip.blocked_at)}</span>
                <button class="btn btn-small btn-secondary security-action-btn" 
                        data-action="unblock" data-ip="${ip.address}">
                    Unblock
                </button>
            `;
            container.appendChild(ipItem);
        });
    }

    updateSecurityLogs(logs) {
        const container = document.getElementById('securityLogsList');
        if (!container) return;

        container.innerHTML = '';
        
        logs.forEach(log => {
            const logItem = document.createElement('div');
            logItem.className = `log-entry ${log.level}`;
            logItem.innerHTML = `
                <span class="log-time">${formatDate(log.timestamp)}</span>
                <span class="log-message">${log.message}</span>
                <span class="log-ip">${log.ip || 'N/A'}</span>
            `;
            container.appendChild(logItem);
        });
    }

    // System management
    updateSystemInfo(system) {
        const container = document.getElementById('systemInfo');
        if (!container) return;

        container.innerHTML = `
            <div class="system-stat">
                <label>PHP Version:</label>
                <span>${system.php_version}</span>
            </div>
            <div class="system-stat">
                <label>Memory Usage:</label>
                <span>${system.memory_usage}</span>
            </div>
            <div class="system-stat">
                <label>Disk Space:</label>
                <span>${system.disk_space}</span>
            </div>
            <div class="system-stat">
                <label>Uptime:</label>
                <span>${system.uptime}</span>
            </div>
        `;
    }

    updateMaintenanceStatus(maintenance) {
        const container = document.getElementById('maintenanceStatus');
        if (!container) return;

        container.innerHTML = `
            <div class="maintenance-info">
                <span>Maintenance Mode: ${maintenance.enabled ? 'Enabled' : 'Disabled'}</span>
                <button class="btn btn-secondary system-action-btn" 
                        data-action="toggle-maintenance">
                    ${maintenance.enabled ? 'Disable' : 'Enable'} Maintenance
                </button>
            </div>
        `;
    }

    updateLogsDisplay(logs) {
        const container = document.getElementById('logsDisplay');
        if (!container) return;

        container.innerHTML = '';
        
        Object.keys(logs).forEach(logType => {
            const logSection = document.createElement('div');
            logSection.className = 'log-section';
            logSection.innerHTML = `
                <h3>${logType.charAt(0).toUpperCase() + logType.slice(1)} Logs</h3>
                <div class="log-list" id="${logType}LogsList"></div>
            `;
            container.appendChild(logSection);

            const logsList = logSection.querySelector('.log-list');
            logs[logType].forEach(log => {
                const logItem = document.createElement('div');
                logItem.className = `log-entry ${log.level || 'info'}`;
                logItem.innerHTML = `
                    <span class="log-time">${formatDate(log.timestamp)}</span>
                    <span class="log-message">${log.message}</span>
                `;
                logsList.appendChild(logItem);
            });
        });
    }

    // Event handlers
    async handleAdminAction(button) {
        const action = button.dataset.action;
        
        switch (action) {
            case 'backup':
                await this.performBackup();
                break;
            case 'restore':
                await this.performRestore();
                break;
            case 'clear-logs':
                await this.clearLogs();
                break;
            case 'optimize':
                await this.optimizeSystem();
                break;
        }
    }

    async handleUserAction(button) {
        const action = button.dataset.action;
        const userId = button.dataset.userId;
        
        switch (action) {
            case 'edit':
                this.editUser(userId);
                break;
            case 'toggle':
                await this.toggleUserStatus(userId);
                break;
            case 'delete':
                await this.deleteUser(userId);
                break;
        }
    }

    async handleSystemAction(button) {
        const action = button.dataset.action;
        
        switch (action) {
            case 'toggle-maintenance':
                await this.toggleMaintenance();
                break;
            case 'clear-cache':
                await this.clearCache();
                break;
            case 'update-check':
                await this.checkForUpdates();
                break;
        }
    }

    async handleSecurityAction(button) {
        const action = button.dataset.action;
        
        switch (action) {
            case 'block-ip':
                await this.blockIP();
                break;
            case 'unblock':
                const ip = button.dataset.ip;
                await this.unblockIP(ip);
                break;
            case 'clear-quarantine':
                await this.clearQuarantine();
                break;
        }
    }

    async handleFileAction(button) {
        const action = button.dataset.action;
        const fileId = button.dataset.fileId;
        
        switch (action) {
            case 'download':
                window.location.href = `api/files.php?action=download&id=${fileId}`;
                break;
            case 'details':
                await this.showFileDetails(fileId);
                break;
            case 'delete':
                await this.deleteFileAsAdmin(fileId);
                break;
        }
    }

    // Specific action implementations
    async performBackup() {
        if (!confirm('This will create a backup of all system data. Continue?')) {
            return;
        }

        try {
            const response = await fetch('api/backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create',
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            if (data.success) {
                showNotification('Backup created successfully', 'success');
            } else {
                showNotification(data.message || 'Backup failed', 'error');
            }
        } catch (error) {
            showNotification('Backup failed', 'error');
        }
    }

    async toggleUserStatus(userId) {
        try {
            const response = await fetch('api/users.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'toggle_status',
                    id: userId,
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            if (data.success) {
                showNotification('User status updated', 'success');
                this.loadUsers(); // Reload users table
            } else {
                showNotification(data.message || 'Update failed', 'error');
            }
        } catch (error) {
            showNotification('Update failed', 'error');
        }
    }

    async deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch('api/users.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: userId,
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            if (data.success) {
                showNotification('User deleted successfully', 'success');
                this.loadUsers(); // Reload users table
            } else {
                showNotification(data.message || 'Delete failed', 'error');
            }
        } catch (error) {
            showNotification('Delete failed', 'error');
        }
    }

    async blockIP() {
        const ip = prompt('Enter IP address to block:');
        if (!ip) return;

        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'block_ip',
                    ip_address: ip,
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            if (data.success) {
                showNotification('IP address blocked', 'success');
                this.loadSecurity(); // Reload security info
            } else {
                showNotification(data.message || 'Block failed', 'error');
            }
        } catch (error) {
            showNotification('Block failed', 'error');
        }
    }

    async unblockIP(ip) {
        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'unblock_ip',
                    ip_address: ip,
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            if (data.success) {
                showNotification('IP address unblocked', 'success');
                this.loadSecurity(); // Reload security info
            } else {
                showNotification(data.message || 'Unblock failed', 'error');
            }
        } catch (error) {
            showNotification('Unblock failed', 'error');
        }
    }

    async toggleMaintenance() {
        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'toggle_maintenance',
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            if (data.success) {
                showNotification(`Maintenance mode ${data.enabled ? 'enabled' : 'disabled'}`, 'success');
                this.loadSystem(); // Reload system info
            } else {
                showNotification(data.message || 'Toggle failed', 'error');
            }
        } catch (error) {
            showNotification('Toggle failed', 'error');
        }
    }

    async deleteFileAsAdmin(fileId) {
        if (!confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch('api/files.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: fileId,
                    admin: true,
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            if (data.success) {
                showNotification('File deleted successfully', 'success');
                this.loadFiles(); // Reload files table
            } else {
                showNotification(data.message || 'Delete failed', 'error');
            }
        } catch (error) {
            showNotification('Delete failed', 'error');
        }
    }

    // Auto-refresh functionality
    startAutoRefresh() {
        this.refreshInterval = setInterval(() => {
            if (this.currentSection === 'dashboard') {
                this.loadDashboard();
            }
        }, 30000); // Refresh every 30 seconds
    }

    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    refreshCurrentSection() {
        this.loadSection(this.currentSection);
    }

    // Utility methods
    initCharts() {
        // Initialize any chart libraries if needed
    }

    getFileIcon(mimeType) {
        if (mimeType.startsWith('image/')) return '🖼️';
        if (mimeType.startsWith('video/')) return '🎥';
        if (mimeType.startsWith('audio/')) return '🎵';
        if (mimeType.includes('pdf')) return '📄';
        if (mimeType.includes('zip') || mimeType.includes('rar')) return '📦';
        return '📁';
    }

    getCSRFToken() {
        const token = document.querySelector('input[name="csrf_token"]') || 
                     document.querySelector('meta[name="csrf-token"]');
        return token ? token.value || token.content : '';
    }

    populateSettingsForm(settings) {
        Object.keys(settings).forEach(key => {
            const input = document.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = settings[key];
                } else {
                    input.value = settings[key];
                }
            }
        });
    }

    async saveSettings(form) {
        const formData = new FormData(form);
        const settings = {};
        
        for (const [key, value] of formData.entries()) {
            settings[key] = value;
        }

        try {
            const response = await fetch('api/admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'save_settings',
                    settings: settings,
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            if (data.success) {
                showNotification('Settings saved successfully', 'success');
            } else {
                showNotification(data.message || 'Save failed', 'error');
            }
        } catch (error) {
            showNotification('Save failed', 'error');
        }
    }
}

// Initialize admin panel
document.addEventListener('DOMContentLoaded', () => {
    window.adminPanel = new AdminPanel();
});

// Global functions for template access
window.blockIP = () => {
    if (window.adminPanel) {
        window.adminPanel.blockIP();
    }
};

window.refreshAdmin = () => {
    if (window.adminPanel) {
        window.adminPanel.refreshCurrentSection();
    }
};
