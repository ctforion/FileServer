/**
 * Dashboard Page JavaScript
 * Handles dashboard functionality, widgets, and data visualization
 */

// Extend the main app with dashboard functionality
FileServerApp.prototype.initDashboardPage = function() {
    this.setupDashboardWidgets();
    this.setupQuickActions();
    this.setupRecentFiles();
    this.setupStorageUsage();
    this.setupActivityFeed();
    this.setupDashboardRefresh();
    this.loadDashboardData();
};

/**
 * Setup dashboard widgets
 */
FileServerApp.prototype.setupDashboardWidgets = function() {
    // Widget refresh buttons
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-widget-refresh]')) {
            const widget = e.target.closest('[data-widget-refresh]').dataset.widgetRefresh;
            this.refreshWidget(widget);
        }
    });

    // Widget collapse/expand
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-widget-toggle]')) {
            const widget = e.target.closest('.dashboard-widget');
            const content = widget.querySelector('.widget-content');
            const icon = e.target.closest('[data-widget-toggle]').querySelector('i');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.className = 'fas fa-chevron-up';
            } else {
                content.style.display = 'none';
                icon.className = 'fas fa-chevron-down';
            }
        }
    });
};

/**
 * Setup quick actions
 */
FileServerApp.prototype.setupQuickActions = function() {
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-quick-action]')) {
            const action = e.target.closest('[data-quick-action]').dataset.quickAction;
            this.handleQuickAction(action);
        }
    });
};

/**
 * Handle quick actions
 */
FileServerApp.prototype.handleQuickAction = function(action) {
    switch (action) {
        case 'upload':
            window.location.href = '/upload';
            break;
        case 'create-folder':
            this.showCreateFolderModal();
            break;
        case 'search':
            this.openSearchModal();
            break;
        case 'share-files':
            window.location.href = '/files';
            break;
        case 'view-analytics':
            if (this.user && ['admin', 'moderator'].includes(this.user.role)) {
                window.location.href = '/admin';
            }
            break;
        case 'manage-users':
            if (this.user && this.user.role === 'admin') {
                window.location.href = '/admin#users';
            }
            break;
    }
};

/**
 * Setup recent files section
 */
FileServerApp.prototype.setupRecentFiles = function() {
    // View all recent files button
    const viewAllBtn = document.getElementById('viewAllRecentFiles');
    if (viewAllBtn) {
        viewAllBtn.addEventListener('click', () => {
            window.location.href = '/files?sort=modified_desc';
        });
    }

    // Recent file actions will be set up when files are loaded
};

/**
 * Setup storage usage widget
 */
FileServerApp.prototype.setupStorageUsage = function() {
    // Storage breakdown toggle
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-storage-toggle]')) {
            const breakdown = document.getElementById('storageBreakdown');
            if (breakdown) {
                breakdown.style.display = breakdown.style.display === 'none' ? 'block' : 'none';
            }
        }
    });
};

/**
 * Setup activity feed
 */
FileServerApp.prototype.setupActivityFeed = function() {
    // Auto-refresh activity feed
    setInterval(() => {
        this.refreshWidget('activity');
    }, 30000); // Refresh every 30 seconds

    // Activity item actions
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-activity-action]')) {
            const action = e.target.closest('[data-activity-action]').dataset.activityAction;
            const activityId = e.target.closest('[data-activity-id]')?.dataset.activityId;
            this.handleActivityAction(action, activityId);
        }
    });
};

/**
 * Handle activity actions
 */
FileServerApp.prototype.handleActivityAction = function(action, activityId) {
    switch (action) {
        case 'view-file':
            // Extract file ID from activity and navigate to file
            this.viewFileFromActivity(activityId);
            break;
        case 'mark-read':
            this.markActivityAsRead(activityId);
            break;
    }
};

/**
 * Setup dashboard refresh
 */
FileServerApp.prototype.setupDashboardRefresh = function() {
    // Global refresh button
    const refreshBtn = document.getElementById('refreshDashboard');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            this.loadDashboardData();
        });
    }

    // Auto-refresh dashboard data
    setInterval(() => {
        this.loadDashboardData();
    }, 60000); // Refresh every minute
};

/**
 * Load dashboard data
 */
FileServerApp.prototype.loadDashboardData = async function() {
    try {
        const response = await this.apiRequest('/user/dashboard');
        
        if (response.success) {
            this.updateDashboardStats(response.data.stats);
            this.updateRecentFiles(response.data.recent_files);
            this.updateStorageUsage(response.data.storage);
            this.updateActivityFeed(response.data.activity);
            this.updateQuickStats(response.data.quick_stats);
        }
    } catch (error) {
        console.error('Failed to load dashboard data:', error);
    }
};

/**
 * Update dashboard statistics
 */
FileServerApp.prototype.updateDashboardStats = function(stats) {
    if (!stats) return;

    // Update stat cards
    this.updateStatCard('totalFiles', stats.total_files);
    this.updateStatCard('totalSize', this.formatFileSize(stats.total_size));
    this.updateStatCard('recentUploads', stats.recent_uploads);
    this.updateStatCard('sharedFiles', stats.shared_files);

    // Update charts if they exist
    this.updateUploadChart(stats.upload_trend);
    this.updateStorageChart(stats.storage_breakdown);
};

/**
 * Update individual stat card
 */
FileServerApp.prototype.updateStatCard = function(cardId, value) {
    const card = document.getElementById(cardId);
    if (card) {
        const valueElement = card.querySelector('.stat-value');
        const changeElement = card.querySelector('.stat-change');
        
        if (valueElement) {
            valueElement.textContent = value;
        }
        
        // Add animation for value change
        if (valueElement && valueElement.textContent !== value.toString()) {
            valueElement.classList.add('stat-updating');
            setTimeout(() => {
                valueElement.classList.remove('stat-updating');
            }, 500);
        }
    }
};

/**
 * Update recent files list
 */
FileServerApp.prototype.updateRecentFiles = function(recentFiles) {
    const container = document.getElementById('recentFilesList');
    if (!container || !recentFiles) return;

    container.innerHTML = '';

    if (recentFiles.length === 0) {
        container.innerHTML = `
            <div class="empty-state-small">
                <i class="fas fa-clock"></i>
                <p>No recent files</p>
            </div>
        `;
        return;
    }

    recentFiles.forEach(file => {
        const fileElement = this.createRecentFileElement(file);
        container.appendChild(fileElement);
    });
};

/**
 * Create recent file element
 */
FileServerApp.prototype.createRecentFileElement = function(file) {
    const element = document.createElement('div');
    element.className = 'recent-file-item';
    element.innerHTML = `
        <div class="file-icon">
            ${file.thumbnail_url ? 
                `<img src="${file.thumbnail_url}" alt="Thumbnail" class="file-thumbnail">` :
                `<i class="${this.getFileIcon(file.mime_type)}"></i>`
            }
        </div>
        <div class="file-info">
            <div class="file-name" title="${file.name}">${file.name}</div>
            <div class="file-meta">
                ${this.formatFileSize(file.size)} • ${this.formatDate(file.modified_at)}
            </div>
        </div>
        <div class="file-actions">
            <button type="button" class="btn btn-sm btn-outline-primary" 
                    onclick="window.app.previewFile('${file.id}')" title="Preview">
                <i class="fas fa-eye"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-success" 
                    onclick="window.app.downloadFiles(['${file.id}'])" title="Download">
                <i class="fas fa-download"></i>
            </button>
        </div>
    `;
    return element;
};

/**
 * Update storage usage
 */
FileServerApp.prototype.updateStorageUsage = function(storage) {
    if (!storage) return;

    // Update storage progress bar
    const progressBar = document.getElementById('storageProgress');
    if (progressBar) {
        const percentage = (storage.used / storage.total) * 100;
        progressBar.style.width = `${percentage}%`;
        
        // Update progress bar color based on usage
        if (percentage > 90) {
            progressBar.className = 'progress-bar bg-danger';
        } else if (percentage > 75) {
            progressBar.className = 'progress-bar bg-warning';
        } else {
            progressBar.className = 'progress-bar bg-success';
        }
    }

    // Update storage text
    const storageText = document.getElementById('storageText');
    if (storageText) {
        storageText.textContent = `${this.formatFileSize(storage.used)} of ${this.formatFileSize(storage.total)} used`;
    }

    // Update storage breakdown
    this.updateStorageBreakdown(storage.breakdown);
};

/**
 * Update storage breakdown
 */
FileServerApp.prototype.updateStorageBreakdown = function(breakdown) {
    const container = document.getElementById('storageBreakdown');
    if (!container || !breakdown) return;

    container.innerHTML = '';

    Object.entries(breakdown).forEach(([type, data]) => {
        const item = document.createElement('div');
        item.className = 'storage-breakdown-item';
        item.innerHTML = `
            <div class="storage-type">
                <span class="storage-color" style="background-color: ${data.color}"></span>
                <span class="storage-label">${type}</span>
            </div>
            <div class="storage-size">${this.formatFileSize(data.size)}</div>
            <div class="storage-percentage">${data.percentage}%</div>
        `;
        container.appendChild(item);
    });
};

/**
 * Update activity feed
 */
FileServerApp.prototype.updateActivityFeed = function(activities) {
    const container = document.getElementById('activityFeed');
    if (!container || !activities) return;

    container.innerHTML = '';

    if (activities.length === 0) {
        container.innerHTML = `
            <div class="empty-state-small">
                <i class="fas fa-history"></i>
                <p>No recent activity</p>
            </div>
        `;
        return;
    }

    activities.forEach(activity => {
        const activityElement = this.createActivityElement(activity);
        container.appendChild(activityElement);
    });
};

/**
 * Create activity element
 */
FileServerApp.prototype.createActivityElement = function(activity) {
    const element = document.createElement('div');
    element.className = 'activity-item';
    element.dataset.activityId = activity.id;

    const icon = this.getActivityIcon(activity.type);
    const timeAgo = this.formatDate(activity.created_at);

    element.innerHTML = `
        <div class="activity-icon">
            <i class="${icon}"></i>
        </div>
        <div class="activity-content">
            <div class="activity-description">${activity.description}</div>
            <div class="activity-time">${timeAgo}</div>
        </div>
        <div class="activity-actions">
            ${activity.file_id ? `
                <button type="button" class="btn btn-sm btn-outline-primary" 
                        data-activity-action="view-file" title="View file">
                    <i class="fas fa-external-link-alt"></i>
                </button>
            ` : ''}
        </div>
    `;

    return element;
};

/**
 * Get activity icon based on type
 */
FileServerApp.prototype.getActivityIcon = function(type) {
    const icons = {
        upload: 'fas fa-upload text-success',
        download: 'fas fa-download text-primary',
        delete: 'fas fa-trash text-danger',
        share: 'fas fa-share-alt text-info',
        rename: 'fas fa-edit text-warning',
        move: 'fas fa-arrows-alt text-secondary',
        login: 'fas fa-sign-in-alt text-success',
        logout: 'fas fa-sign-out-alt text-muted'
    };
    return icons[type] || 'fas fa-circle text-muted';
};

/**
 * Update quick stats
 */
FileServerApp.prototype.updateQuickStats = function(quickStats) {
    if (!quickStats) return;

    // Update files uploaded today
    const todayUploads = document.getElementById('todayUploads');
    if (todayUploads) {
        todayUploads.textContent = quickStats.uploads_today || 0;
    }

    // Update downloads today
    const todayDownloads = document.getElementById('todayDownloads');
    if (todayDownloads) {
        todayDownloads.textContent = quickStats.downloads_today || 0;
    }

    // Update active shares
    const activeShares = document.getElementById('activeShares');
    if (activeShares) {
        activeShares.textContent = quickStats.active_shares || 0;
    }
};

/**
 * Refresh individual widget
 */
FileServerApp.prototype.refreshWidget = async function(widget) {
    const widgetElement = document.querySelector(`[data-widget="${widget}"]`);
    if (widgetElement) {
        widgetElement.classList.add('widget-loading');
    }

    try {
        const response = await this.apiRequest(`/user/dashboard/${widget}`);
        
        if (response.success) {
            switch (widget) {
                case 'stats':
                    this.updateDashboardStats(response.data);
                    break;
                case 'recent':
                    this.updateRecentFiles(response.data);
                    break;
                case 'storage':
                    this.updateStorageUsage(response.data);
                    break;
                case 'activity':
                    this.updateActivityFeed(response.data);
                    break;
            }
        }
    } catch (error) {
        console.error(`Failed to refresh ${widget} widget:`, error);
    } finally {
        if (widgetElement) {
            widgetElement.classList.remove('widget-loading');
        }
    }
};

/**
 * Update upload chart
 */
FileServerApp.prototype.updateUploadChart = function(data) {
    // This would integrate with Chart.js or similar library
    const chartContainer = document.getElementById('uploadChart');
    if (!chartContainer || !data) return;

    // Simple text-based chart for now
    chartContainer.innerHTML = `
        <div class="simple-chart">
            <div class="chart-title">Uploads This Week</div>
            <div class="chart-bars">
                ${data.map(item => `
                    <div class="chart-bar">
                        <div class="bar" style="height: ${(item.count / Math.max(...data.map(d => d.count))) * 100}%"></div>
                        <div class="bar-label">${item.day}</div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
};

/**
 * Update storage chart
 */
FileServerApp.prototype.updateStorageChart = function(data) {
    // This would integrate with Chart.js for pie chart
    const chartContainer = document.getElementById('storageChart');
    if (!chartContainer || !data) return;

    // Simple breakdown for now
    chartContainer.innerHTML = `
        <div class="storage-chart">
            ${Object.entries(data).map(([type, info]) => `
                <div class="storage-segment" style="flex: ${info.percentage}">
                    <div class="segment-color" style="background-color: ${info.color}"></div>
                    <span class="segment-label">${type}</span>
                </div>
            `).join('')}
        </div>
    `;
};

/**
 * Show create folder modal
 */
FileServerApp.prototype.showCreateFolderModal = function() {
    // Implementation for create folder modal
    this.openModal('createFolderModal');
    
    const modal = document.getElementById('createFolderModal');
    if (modal) {
        const nameInput = modal.querySelector('input[name="folder_name"]');
        if (nameInput) {
            nameInput.focus();
        }
    }
};

/**
 * View file from activity
 */
FileServerApp.prototype.viewFileFromActivity = async function(activityId) {
    try {
        const response = await this.apiRequest(`/user/activity/${activityId}/file`);
        if (response.success && response.data.file_id) {
            window.location.href = `/files?file=${response.data.file_id}`;
        }
    } catch (error) {
        this.showNotification('File not found', 'error');
    }
};

/**
 * Mark activity as read
 */
FileServerApp.prototype.markActivityAsRead = async function(activityId) {
    try {
        await this.apiRequest(`/user/activity/${activityId}/read`, {
            method: 'POST'
        });
        
        // Update UI to show as read
        const activityElement = document.querySelector(`[data-activity-id="${activityId}"]`);
        if (activityElement) {
            activityElement.classList.add('activity-read');
        }
    } catch (error) {
        console.error('Failed to mark activity as read:', error);
    }
};
