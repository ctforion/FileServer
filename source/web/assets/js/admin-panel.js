/**
 * Admin Panel Module
 * Handles administration interface functionality
 */

class AdminPanel {
    constructor() {
        this.refreshInterval = null;
        this.currentTab = 'dashboard';
        
        this.container = document.querySelector('[data-admin-panel]');
        this.tabButtons = document.querySelectorAll('[data-admin-tab]');
        this.tabContents = document.querySelectorAll('[data-tab-content]');
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.setupCharts();
        this.startAutoRefresh();
        this.loadDashboardData();
    }
    
    setupEventListeners() {
        // Tab navigation
        this.tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                this.switchTab(button.dataset.adminTab);
            });
        });
        
        // User management actions
        this.setupUserManagement();
        
        // File management actions
        this.setupFileManagement();
        
        // Settings management
        this.setupSettingsManagement();
        
        // System maintenance actions
        this.setupMaintenanceActions();
        
        // Log viewer
        this.setupLogViewer();
        
        // Real-time updates toggle
        const realtimeToggle = document.querySelector('[data-realtime-toggle]');
        if (realtimeToggle) {
            realtimeToggle.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.startAutoRefresh();
                } else {
                    this.stopAutoRefresh();
                }
            });
        }
    }
    
    setupUserManagement() {
        // User action buttons
        const userActions = document.querySelectorAll('[data-user-action]');
        userActions.forEach(button => {
            button.addEventListener('click', () => {
                const action = button.dataset.userAction;
                const userId = button.dataset.userId;
                this.handleUserAction(action, userId);
            });
        });
        
        // Bulk user actions
        const bulkUserActions = document.querySelectorAll('[data-bulk-user-action]');
        bulkUserActions.forEach(button => {
            button.addEventListener('click', () => {
                const action = button.dataset.bulkUserAction;
                this.handleBulkUserAction(action);
            });
        });
        
        // User search
        const userSearch = document.querySelector('[data-user-search]');
        if (userSearch) {
            userSearch.addEventListener('input', 
                window.app.debounce(this.searchUsers.bind(this), 300)
            );
        }
        
        // User filter
        const userFilter = document.querySelector('[data-user-filter]');
        if (userFilter) {
            userFilter.addEventListener('change', this.filterUsers.bind(this));
        }
        
        // Add user button
        const addUserBtn = document.querySelector('[data-add-user]');
        if (addUserBtn) {
            addUserBtn.addEventListener('click', () => {
                this.showAddUserModal();
            });
        }
    }
    
    setupFileManagement() {
        // File action buttons
        const fileActions = document.querySelectorAll('[data-file-action]');
        fileActions.forEach(button => {
            button.addEventListener('click', () => {
                const action = button.dataset.fileAction;
                const fileId = button.dataset.fileId;
                this.handleFileAction(action, fileId);
            });
        });
        
        // File search
        const fileSearch = document.querySelector('[data-file-search]');
        if (fileSearch) {
            fileSearch.addEventListener('input', 
                window.app.debounce(this.searchFiles.bind(this), 300)
            );
        }
        
        // File filter
        const fileFilter = document.querySelector('[data-file-filter]');
        if (fileFilter) {
            fileFilter.addEventListener('change', this.filterFiles.bind(this));
        }
        
        // Storage cleanup
        const cleanupBtn = document.querySelector('[data-cleanup-storage]');
        if (cleanupBtn) {
            cleanupBtn.addEventListener('click', this.cleanupStorage.bind(this));
        }
    }
    
    setupSettingsManagement() {
        // Settings form
        const settingsForm = document.querySelector('[data-settings-form]');
        if (settingsForm) {
            settingsForm.addEventListener('submit', this.saveSettings.bind(this));
        }
        
        // Individual setting inputs
        const settingInputs = document.querySelectorAll('[data-setting]');
        settingInputs.forEach(input => {
            input.addEventListener('change', () => {
                this.saveIndividualSetting(input.dataset.setting, input.value);
            });
        });
        
        // Reset settings button
        const resetBtn = document.querySelector('[data-reset-settings]');
        if (resetBtn) {
            resetBtn.addEventListener('click', this.resetSettings.bind(this));
        }
        
        // Test email button
        const testEmailBtn = document.querySelector('[data-test-email]');
        if (testEmailBtn) {
            testEmailBtn.addEventListener('click', this.testEmailSettings.bind(this));
        }
    }
    
    setupMaintenanceActions() {
        // Cache clear
        const clearCacheBtn = document.querySelector('[data-clear-cache]');
        if (clearCacheBtn) {
            clearCacheBtn.addEventListener('click', this.clearCache.bind(this));
        }
        
        // Orphaned files cleanup
        const cleanupOrphanedBtn = document.querySelector('[data-cleanup-orphaned]');
        if (cleanupOrphanedBtn) {
            cleanupOrphanedBtn.addEventListener('click', this.cleanupOrphanedFiles.bind(this));
        }
        
        // Database optimization
        const optimizeDbBtn = document.querySelector('[data-optimize-db]');
        if (optimizeDbBtn) {
            optimizeDbBtn.addEventListener('click', this.optimizeDatabase.bind(this));
        }
        
        // Backup creation
        const createBackupBtn = document.querySelector('[data-create-backup]');
        if (createBackupBtn) {
            createBackupBtn.addEventListener('click', this.createBackup.bind(this));
        }
        
        // System info refresh
        const refreshSystemBtn = document.querySelector('[data-refresh-system]');
        if (refreshSystemBtn) {
            refreshSystemBtn.addEventListener('click', this.refreshSystemInfo.bind(this));
        }
    }
    
    setupLogViewer() {
        // Log level filter
        const logLevelFilter = document.querySelector('[data-log-level]');
        if (logLevelFilter) {
            logLevelFilter.addEventListener('change', this.filterLogs.bind(this));
        }
        
        // Log search
        const logSearch = document.querySelector('[data-log-search]');
        if (logSearch) {
            logSearch.addEventListener('input', 
                window.app.debounce(this.searchLogs.bind(this), 300)
            );
        }
        
        // Log refresh
        const refreshLogsBtn = document.querySelector('[data-refresh-logs]');
        if (refreshLogsBtn) {
            refreshLogsBtn.addEventListener('click', this.refreshLogs.bind(this));
        }
        
        // Clear logs
        const clearLogsBtn = document.querySelector('[data-clear-logs]');
        if (clearLogsBtn) {
            clearLogsBtn.addEventListener('click', this.clearLogs.bind(this));
        }
        
        // Download logs
        const downloadLogsBtn = document.querySelector('[data-download-logs]');
        if (downloadLogsBtn) {
            downloadLogsBtn.addEventListener('click', this.downloadLogs.bind(this));
        }
    }
    
    setupCharts() {
        // Initialize Chart.js charts if available
        if (typeof Chart !== 'undefined') {
            this.initStorageChart();
            this.initUsageChart();
            this.initUserActivityChart();
        }
    }
    
    initStorageChart() {
        const ctx = document.getElementById('storageChart');
        if (!ctx) return;
        
        this.storageChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Used Space', 'Free Space'],
                datasets: [{
                    data: [0, 100],
                    backgroundColor: ['#3498db', '#ecf0f1'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    initUsageChart() {
        const ctx = document.getElementById('usageChart');
        if (!ctx) return;
        
        this.usageChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Uploads',
                    data: [],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Downloads',
                    data: [],
                    borderColor: '#2ecc71',
                    backgroundColor: 'rgba(46, 204, 113, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    initUserActivityChart() {
        const ctx = document.getElementById('userActivityChart');
        if (!ctx) return;
        
        this.userActivityChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Active Users',
                    data: [],
                    backgroundColor: '#9b59b6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    switchTab(tabName) {
        this.currentTab = tabName;
        
        // Update tab buttons
        this.tabButtons.forEach(button => {
            button.classList.toggle('active', button.dataset.adminTab === tabName);
        });
        
        // Update tab contents
        this.tabContents.forEach(content => {
            content.classList.toggle('active', content.dataset.tabContent === tabName);
        });
        
        // Load tab-specific data
        this.loadTabData(tabName);
    }
    
    async loadTabData(tabName) {
        switch (tabName) {
            case 'dashboard':
                await this.loadDashboardData();
                break;
            case 'users':
                await this.loadUsersData();
                break;
            case 'files':
                await this.loadFilesData();
                break;
            case 'logs':
                await this.loadLogsData();
                break;
            case 'settings':
                await this.loadSettingsData();
                break;
            case 'maintenance':
                await this.loadMaintenanceData();
                break;
        }
    }
    
    async loadDashboardData() {
        try {
            const response = await window.app.apiRequest('/api/admin/dashboard');
            this.updateDashboardStats(response.stats);
            this.updateCharts(response.charts);
            this.updateRecentActivity(response.recent_activity);
        } catch (error) {
            console.error('Failed to load dashboard data:', error);
            window.app.showNotification('Failed to load dashboard data', 'error');
        }
    }
    
    updateDashboardStats(stats) {
        // Update stat cards
        const statElements = {
            totalUsers: document.querySelector('[data-stat="total-users"]'),
            totalFiles: document.querySelector('[data-stat="total-files"]'),
            totalStorage: document.querySelector('[data-stat="total-storage"]'),
            activeUsers: document.querySelector('[data-stat="active-users"]')
        };
        
        Object.keys(statElements).forEach(key => {
            const element = statElements[key];
            if (element && stats[key] !== undefined) {
                if (key === 'totalStorage') {
                    element.textContent = window.app.formatFileSize(stats[key]);
                } else {
                    element.textContent = stats[key].toLocaleString();
                }
            }
        });
    }
    
    updateCharts(chartData) {
        // Update storage chart
        if (this.storageChart && chartData.storage) {
            const usedPercent = (chartData.storage.used / chartData.storage.total) * 100;
            this.storageChart.data.datasets[0].data = [usedPercent, 100 - usedPercent];
            this.storageChart.update();
        }
        
        // Update usage chart
        if (this.usageChart && chartData.usage) {
            this.usageChart.data.labels = chartData.usage.labels;
            this.usageChart.data.datasets[0].data = chartData.usage.uploads;
            this.usageChart.data.datasets[1].data = chartData.usage.downloads;
            this.usageChart.update();
        }
        
        // Update user activity chart
        if (this.userActivityChart && chartData.user_activity) {
            this.userActivityChart.data.labels = chartData.user_activity.labels;
            this.userActivityChart.data.datasets[0].data = chartData.user_activity.data;
            this.userActivityChart.update();
        }
    }
    
    updateRecentActivity(activities) {
        const container = document.querySelector('[data-recent-activity]');
        if (!container) return;
        
        container.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-${this.getActivityIcon(activity.type)}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-message">${activity.message}</div>
                    <div class="activity-time">${window.app.formatDate(activity.created_at)}</div>
                </div>
            </div>
        `).join('');
    }
    
    getActivityIcon(type) {
        const icons = {
            user_register: 'user-plus',
            user_login: 'sign-in-alt',
            file_upload: 'upload',
            file_download: 'download',
            file_delete: 'trash',
            share_create: 'share-alt',
            admin_action: 'cog'
        };
        return icons[type] || 'info-circle';
    }
    
    async loadUsersData() {
        try {
            const response = await window.app.apiRequest('/api/admin/users');
            this.renderUsersTable(response.users);
        } catch (error) {
            console.error('Failed to load users data:', error);
            window.app.showNotification('Failed to load users data', 'error');
        }
    }
    
    renderUsersTable(users) {
        const tbody = document.querySelector('[data-users-table] tbody');
        if (!tbody) return;
        
        tbody.innerHTML = users.map(user => `
            <tr>
                <td>
                    <input type="checkbox" data-user-select="${user.id}">
                </td>
                <td>
                    <div class="user-info">
                        <div class="user-avatar">
                            <img src="${user.avatar || '/assets/images/default-avatar.png'}" alt="${user.username}">
                        </div>
                        <div>
                            <div class="user-name">${user.username}</div>
                            <div class="user-email">${user.email}</div>
                        </div>
                    </div>
                </td>
                <td><span class="badge badge-${user.role}">${user.role}</span></td>
                <td>${user.file_count}</td>
                <td>${window.app.formatFileSize(user.storage_used)}</td>
                <td><span class="status-badge status-${user.status}">${user.status}</span></td>
                <td>${window.app.formatDate(user.last_login)}</td>
                <td>
                    <div class="btn-group">
                        <button class="btn-icon" data-user-action="edit" data-user-id="${user.id}" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon" data-user-action="toggle-status" data-user-id="${user.id}" title="Toggle Status">
                            <i class="fas fa-${user.status === 'active' ? 'ban' : 'check'}"></i>
                        </button>
                        <button class="btn-icon" data-user-action="delete" data-user-id="${user.id}" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
        
        // Re-setup event listeners for new buttons
        this.setupUserManagement();
    }
    
    async handleUserAction(action, userId) {
        try {
            switch (action) {
                case 'edit':
                    this.showEditUserModal(userId);
                    break;
                case 'toggle-status':
                    await this.toggleUserStatus(userId);
                    break;
                case 'delete':
                    await this.deleteUser(userId);
                    break;
            }
        } catch (error) {
            console.error('User action error:', error);
            window.app.showNotification('Action failed', 'error');
        }
    }
    
    async toggleUserStatus(userId) {
        if (!confirm('Are you sure you want to change this user\'s status?')) {
            return;
        }
        
        await window.app.apiRequest(`/api/admin/users/${userId}/toggle-status`, 'POST');
        window.app.showNotification('User status updated', 'success');
        this.loadUsersData();
    }
    
    async deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            return;
        }
        
        await window.app.apiRequest(`/api/admin/users/${userId}`, 'DELETE');
        window.app.showNotification('User deleted successfully', 'success');
        this.loadUsersData();
    }
    
    startAutoRefresh() {
        this.stopAutoRefresh();
        this.refreshInterval = setInterval(() => {
            if (this.currentTab === 'dashboard') {
                this.loadDashboardData();
            }
        }, 30000); // Refresh every 30 seconds
    }
    
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
    
    async clearCache() {
        try {
            await window.app.apiRequest('/api/admin/maintenance/clear-cache', 'POST');
            window.app.showNotification('Cache cleared successfully', 'success');
        } catch (error) {
            console.error('Cache clear error:', error);
            window.app.showNotification('Failed to clear cache', 'error');
        }
    }
    
    async cleanupOrphanedFiles() {
        if (!confirm('This will remove files that are no longer referenced in the database. Continue?')) {
            return;
        }
        
        try {
            const response = await window.app.apiRequest('/api/admin/maintenance/cleanup-orphaned', 'POST');
            window.app.showNotification(`Cleanup completed. Removed ${response.removed_count} orphaned files.`, 'success');
        } catch (error) {
            console.error('Cleanup error:', error);
            window.app.showNotification('Cleanup failed', 'error');
        }
    }
    
    async optimizeDatabase() {
        if (!confirm('This will optimize the database tables. Continue?')) {
            return;
        }
        
        try {
            await window.app.apiRequest('/api/admin/maintenance/optimize-db', 'POST');
            window.app.showNotification('Database optimized successfully', 'success');
        } catch (error) {
            console.error('Database optimization error:', error);
            window.app.showNotification('Database optimization failed', 'error');
        }
    }
    
    async createBackup() {
        try {
            const response = await window.app.apiRequest('/api/admin/maintenance/create-backup', 'POST');
            window.app.showNotification(`Backup created: ${response.backup_file}`, 'success');
        } catch (error) {
            console.error('Backup creation error:', error);
            window.app.showNotification('Backup creation failed', 'error');
        }
    }
    
    async saveSettings(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const settings = {};
        
        for (let [key, value] of formData.entries()) {
            settings[key] = value;
        }
        
        try {
            await window.app.apiRequest('/api/admin/settings', 'POST', settings);
            window.app.showNotification('Settings saved successfully', 'success');
        } catch (error) {
            console.error('Settings save error:', error);
            window.app.showNotification('Failed to save settings', 'error');
        }
    }
    
    async testEmailSettings() {
        try {        await window.app.apiRequest('/api/admin/settings/test-email', 'POST');
            window.app.showNotification('Test email sent successfully', 'success');
        } catch (error) {
            console.error('Email test error:', error);
            window.app.showNotification('Failed to send test email', 'error');
        }
    }

    // System status and update functions
    async refreshSystemStatus() {
        try {
            const response = await window.app.apiRequest('/api/system/status', 'GET');
            if (response.success) {
                this.updateSystemStatusDisplay(response.data);
            }
        } catch (error) {
            console.error('Failed to refresh system status:', error);
            window.app.showNotification('Failed to refresh system status', 'error');
        }
    }

    updateSystemStatusDisplay(status) {
        const elements = {
            currentVersion: document.getElementById('currentVersion'),
            lastUpdate: document.getElementById('lastUpdate'),
            gitAvailable: document.getElementById('gitAvailable'),
            updateScript: document.getElementById('updateScript'),
            freeSpace: document.getElementById('freeSpace'),
            phpVersion: document.getElementById('phpVersion')
        };

        if (elements.currentVersion) {
            elements.currentVersion.textContent = status.current_version || 'Unknown';
        }

        if (elements.lastUpdate) {
            const lastUpdate = status.last_update ? 
                new Date(status.last_update * 1000).toLocaleString() : 
                'Never';
            elements.lastUpdate.textContent = lastUpdate;
        }

        if (elements.gitAvailable) {
            elements.gitAvailable.textContent = status.git_available ? 'Yes' : 'No';
            elements.gitAvailable.style.color = status.git_available ? '#28a745' : '#dc3545';
        }

        if (elements.updateScript) {
            elements.updateScript.textContent = status.update_script_exists ? 'Available' : 'Missing';
            elements.updateScript.style.color = status.update_script_exists ? '#28a745' : '#dc3545';
        }

        if (elements.freeSpace && status.disk_space) {
            const freeGB = (status.disk_space.free / (1024 * 1024 * 1024)).toFixed(2);
            const totalGB = (status.disk_space.total / (1024 * 1024 * 1024)).toFixed(2);
            elements.freeSpace.textContent = `${freeGB} GB / ${totalGB} GB`;
        }

        if (elements.phpVersion) {
            elements.phpVersion.textContent = status.php_version || 'Unknown';
        }
    }

    async performAutoUpdate() {
        const updateBtn = document.getElementById('autoUpdateBtn');
        const progressDiv = document.getElementById('updateProgress');
        
        if (!updateBtn || !progressDiv) {
            window.app.showNotification('Update interface not found', 'error');
            return;
        }

        // Confirm update
        if (!confirm('Are you sure you want to update the system? This will create a backup and may take a few minutes.')) {
            return;
        }

        try {
            // Show progress
            updateBtn.style.display = 'none';
            progressDiv.style.display = 'block';

            // Start progress animation
            const progressFill = progressDiv.querySelector('.progress-fill');
            if (progressFill) {
                progressFill.style.width = '20%';
            }

            window.app.showNotification('Starting system update...', 'info');

            // Perform update
            const response = await window.app.apiRequest('/api/system/update', 'POST');

            if (progressFill) {
                progressFill.style.width = '100%';
            }

            if (response.success) {
                window.app.showNotification('System updated successfully! Please refresh the page.', 'success');
                
                // Update maintenance log if it exists
                this.addMaintenanceLogEntry({
                    action: 'Auto Update',
                    timestamp: new Date().toLocaleString(),
                    status: 'success',
                    status_text: 'Completed',
                    icon: 'fa-cloud-download-alt'
                });

                // Refresh system status after update
                setTimeout(() => {
                    this.refreshSystemStatus();
                }, 2000);

            } else {
                throw new Error(response.error || 'Update failed');
            }

        } catch (error) {
            console.error('Auto update error:', error);
            window.app.showNotification(`Update failed: ${error.message}`, 'error');
            
            // Update maintenance log
            this.addMaintenanceLogEntry({
                action: 'Auto Update',
                timestamp: new Date().toLocaleString(),
                status: 'error',
                status_text: 'Failed',
                icon: 'fa-exclamation-triangle'
            });

        } finally {
            // Hide progress and show button
            updateBtn.style.display = 'block';
            progressDiv.style.display = 'none';
            
            const progressFill = progressDiv.querySelector('.progress-fill');
            if (progressFill) {
                progressFill.style.width = '0%';
            }
        }
    }

    async createManualBackup() {
        try {
            window.app.showNotification('Creating backup...', 'info');
            
            const response = await window.app.apiRequest('/api/system/backup', 'POST');
            
            if (response.success) {
                window.app.showNotification('Manual backup created successfully', 'success');
                
                // Update maintenance log
                this.addMaintenanceLogEntry({
                    action: 'Manual Backup',
                    timestamp: new Date().toLocaleString(),
                    status: 'success',
                    status_text: 'Completed',
                    icon: 'fa-archive'
                });

            } else {
                throw new Error(response.error || 'Backup failed');
            }

        } catch (error) {
            console.error('Manual backup error:', error);
            window.app.showNotification(`Backup failed: ${error.message}`, 'error');
        }
    }

    addMaintenanceLogEntry(entry) {
        const logList = document.querySelector('.log-list');
        if (!logList) return;

        const logItem = document.createElement('div');
        logItem.className = 'log-item';
        logItem.innerHTML = `
            <div class="log-icon">
                <i class="fas ${entry.icon}"></i>
            </div>
            <div class="log-details">
                <div class="log-action">${entry.action}</div>
                <div class="log-timestamp">${entry.timestamp}</div>
            </div>
            <div class="log-status ${entry.status}">${entry.status_text}</div>
        `;

        // Insert at the top of the log list
        logList.insertBefore(logItem, logList.firstChild);

        // Limit log entries to prevent overflow
        const maxEntries = 20;
        const entries = logList.querySelectorAll('.log-item');
        if (entries.length > maxEntries) {
            for (let i = maxEntries; i < entries.length; i++) {
                entries[i].remove();
            }
        }
    }
    
    // Cleanup when component is destroyed
    destroy() {
        this.stopAutoRefresh();
    }
}

// Export for global use
window.AdminPanel = AdminPanel;

// Global functions for HTML onclick handlers
window.refreshSystemStatus = function() {
    if (window.adminPanelInstance) {
        window.adminPanelInstance.refreshSystemStatus();
    }
};

window.performAutoUpdate = function() {
    if (window.adminPanelInstance) {
        window.adminPanelInstance.performAutoUpdate();
    }
};

window.createManualBackup = function() {
    if (window.adminPanelInstance) {
        window.adminPanelInstance.createManualBackup();
    }
};

// Existing global functions for backward compatibility
window.showSection = function(sectionId) {
    // Hide all sections
    const sections = document.querySelectorAll('.admin-section');
    sections.forEach(section => section.classList.remove('active'));
    
    // Show selected section
    const targetSection = document.getElementById(sectionId);
    if (targetSection) {
        targetSection.classList.add('active');
    }
    
    // Update navigation
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => item.classList.remove('active'));
    
    const activeNavItem = document.querySelector(`[onclick="showSection('${sectionId}')"]`);
    if (activeNavItem) {
        activeNavItem.classList.add('active');
    }
    
    // Load section-specific data
    if (window.adminPanelInstance) {
        switch(sectionId) {
            case 'maintenance':
                window.adminPanelInstance.refreshSystemStatus();
                break;
        }
    }
};

window.optimizeDatabase = function() {
    if (window.adminPanelInstance) {
        window.adminPanelInstance.optimizeDatabase();
    }
};

window.runCleanup = function() {
    if (window.adminPanelInstance) {
        window.adminPanelInstance.cleanupOrphanedFiles();
    }
};

window.checkUpdates = function() {
    // Legacy function - now uses performAutoUpdate
    window.performAutoUpdate();
};

window.createBackup = function() {
    // Legacy function - now uses createManualBackup
    window.createManualBackup();
};

// Initialize admin panel when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.admin-container')) {
        window.adminPanelInstance = new AdminPanel();
        
        // Load system status on maintenance section
        if (document.getElementById('maintenance')) {
            window.adminPanelInstance.refreshSystemStatus();
        }
    }
});
