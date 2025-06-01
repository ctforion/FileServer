/**
 * Admin Dashboard JavaScript
 * Handles admin interface functionality, user management, system monitoring, and analytics
 */

class AdminDashboard {
    constructor() {
        this.charts = {};
        this.currentUserPage = 1;
        this.usersPerPage = 20;
        this.refreshInterval = null;
        this.realTimeUpdates = true;
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadDashboardData();
        this.initializeCharts();
        this.loadUsers();
        this.loadSystemLogs();
        this.startRealTimeUpdates();
    }

    bindEvents() {
        // Tab switching
        document.querySelectorAll('.nav-tabs .nav-link').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const target = e.target.getAttribute('href');
                this.switchTab(target);
            });
        });

        // User management
        const addUserBtn = document.getElementById('addUserBtn');
        if (addUserBtn) {
            addUserBtn.addEventListener('click', () => {
                this.showAddUserModal();
            });
        }

        // Bulk user actions
        const bulkActionBtn = document.getElementById('bulkActionBtn');
        if (bulkActionBtn) {
            bulkActionBtn.addEventListener('click', () => {
                this.performBulkAction();
            });
        }

        // User search
        const userSearchInput = document.getElementById('userSearch');
        if (userSearchInput) {
            userSearchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.searchUsers(e.target.value);
                }, 300);
            });
        }

        // System settings
        const saveSettingsBtn = document.getElementById('saveSettings');
        if (saveSettingsBtn) {
            saveSettingsBtn.addEventListener('click', () => {
                this.saveSystemSettings();
            });
        }

        // File cleanup
        const cleanupBtn = document.getElementById('cleanupBtn');
        if (cleanupBtn) {
            cleanupBtn.addEventListener('click', () => {
                this.showCleanupModal();
            });
        }

        // Database maintenance
        const dbMaintenanceBtn = document.getElementById('dbMaintenanceBtn');
        if (dbMaintenanceBtn) {
            dbMaintenanceBtn.addEventListener('click', () => {
                this.performDatabaseMaintenance();
            });
        }

        // Export data
        const exportDataBtn = document.getElementById('exportDataBtn');
        if (exportDataBtn) {
            exportDataBtn.addEventListener('click', () => {
                this.showExportModal();
            });
        }

        // Real-time toggle
        const realTimeToggle = document.getElementById('realTimeToggle');
        if (realTimeToggle) {
            realTimeToggle.addEventListener('change', (e) => {
                this.toggleRealTimeUpdates(e.target.checked);
            });
        }

        // Chart refresh
        const refreshChartsBtn = document.getElementById('refreshCharts');
        if (refreshChartsBtn) {
            refreshChartsBtn.addEventListener('click', () => {
                this.refreshCharts();
            });
        }

        // Pagination
        document.addEventListener('click', (e) => {
            if (e.target.matches('.user-pagination-btn')) {
                const page = parseInt(e.target.dataset.page);
                if (page && page !== this.currentUserPage) {
                    this.currentUserPage = page;
                    this.loadUsers();
                }
            }
        });

        // User actions
        document.addEventListener('click', (e) => {
            if (e.target.matches('.edit-user-btn')) {
                const userId = e.target.closest('[data-user-id]').dataset.userId;
                this.editUser(userId);
            } else if (e.target.matches('.delete-user-btn')) {
                const userId = e.target.closest('[data-user-id]').dataset.userId;
                this.deleteUser(userId);
            } else if (e.target.matches('.toggle-user-status-btn')) {
                const userId = e.target.closest('[data-user-id]').dataset.userId;
                this.toggleUserStatus(userId);
            }
        });
    }

    async loadDashboardData() {
        try {
            const response = await window.app.api.request('/admin/dashboard', {
                method: 'GET'
            });

            if (response.success) {
                this.updateDashboardStats(response.data.stats);
                this.updateSystemHealth(response.data.health);
                this.updateActivityFeed(response.data.recent_activity);
            } else {
                throw new Error(response.message || 'Failed to load dashboard data');
            }
        } catch (error) {
            console.error('Dashboard data error:', error);
            window.app.showNotification('Failed to load dashboard data', 'error');
        }
    }

    updateDashboardStats(stats) {
        // Update stat cards
        const statElements = {
            totalUsers: document.getElementById('totalUsers'),
            totalFiles: document.getElementById('totalFiles'),
            totalStorage: document.getElementById('totalStorage'),
            activeUsers: document.getElementById('activeUsers'),
            uploadsToday: document.getElementById('uploadsToday'),
            downloadsToday: document.getElementById('downloadsToday')
        };

        Object.keys(statElements).forEach(key => {
            const element = statElements[key];
            if (element && stats[key] !== undefined) {
                if (key === 'totalStorage') {
                    element.textContent = this.formatFileSize(stats[key]);
                } else {
                    element.textContent = stats[key].toLocaleString();
                }
            }
        });

        // Update growth indicators
        this.updateGrowthIndicators(stats.growth || {});
    }

    updateGrowthIndicators(growth) {
        Object.keys(growth).forEach(key => {
            const indicator = document.getElementById(`${key}Growth`);
            if (indicator) {
                const value = growth[key];
                const isPositive = value >= 0;
                indicator.className = `growth-indicator ${isPositive ? 'positive' : 'negative'}`;
                indicator.innerHTML = `
                    <i class="fas fa-arrow-${isPositive ? 'up' : 'down'}"></i>
                    ${Math.abs(value)}%
                `;
            }
        });
    }

    updateSystemHealth(health) {
        const healthStatus = document.getElementById('systemHealthStatus');
        if (healthStatus) {
            const statusClass = health.overall === 'healthy' ? 'success' : 
                               health.overall === 'warning' ? 'warning' : 'danger';
            healthStatus.className = `badge bg-${statusClass}`;
            healthStatus.textContent = health.overall.toUpperCase();
        }

        // Update individual health metrics
        const metrics = ['cpu', 'memory', 'disk', 'database'];
        metrics.forEach(metric => {
            const element = document.getElementById(`${metric}Health`);
            if (element && health[metric]) {
                const data = health[metric];
                element.innerHTML = `
                    <div class="d-flex justify-content-between">
                        <span>${metric.toUpperCase()}</span>
                        <span>${data.usage}%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-${this.getHealthColor(data.usage)}" 
                             style="width: ${data.usage}%"></div>
                    </div>
                `;
            }
        });
    }

    getHealthColor(usage) {
        if (usage < 60) return 'success';
        if (usage < 80) return 'warning';
        return 'danger';
    }

    updateActivityFeed(activities) {
        const activityFeed = document.getElementById('activityFeed');
        if (!activityFeed || !activities) return;

        activityFeed.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-${this.getActivityIcon(activity.type)}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-text">${activity.description}</div>
                    <div class="activity-time">${this.formatRelativeTime(activity.created_at)}</div>
                </div>
            </div>
        `).join('');
    }

    getActivityIcon(type) {
        const iconMap = {
            upload: 'upload',
            download: 'download',
            user_login: 'sign-in-alt',
            user_register: 'user-plus',
            file_delete: 'trash',
            admin_action: 'cog'
        };
        return iconMap[type] || 'info-circle';
    }

    async loadUsers() {
        try {
            const response = await window.app.api.request(`/admin/users?page=${this.currentUserPage}&limit=${this.usersPerPage}`, {
                method: 'GET'
            });

            if (response.success) {
                this.displayUsers(response.data.users);
                this.updateUserPagination(response.data.pagination);
            } else {
                throw new Error(response.message || 'Failed to load users');
            }
        } catch (error) {
            console.error('Load users error:', error);
            window.app.showNotification('Failed to load users', 'error');
        }
    }

    displayUsers(users) {
        const usersTableBody = document.getElementById('usersTableBody');
        if (!usersTableBody) return;

        usersTableBody.innerHTML = users.map(user => `
            <tr data-user-id="${user.id}">
                <td>
                    <input type="checkbox" class="form-check-input user-checkbox" value="${user.id}">
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <img src="${user.avatar || '/assets/img/default-avatar.png'}" 
                             class="rounded-circle me-2" width="32" height="32" alt="Avatar">
                        <div>
                            <div class="fw-bold">${user.name}</div>
                            <small class="text-muted">${user.email}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge bg-${this.getRoleBadgeColor(user.role)}">${user.role}</span>
                </td>
                <td>
                    <span class="badge bg-${user.status === 'active' ? 'success' : 'secondary'}">${user.status}</span>
                </td>
                <td>${this.formatDate(user.created_at)}</td>
                <td>${this.formatDate(user.last_login)}</td>
                <td>${user.files_count || 0}</td>
                <td>${this.formatFileSize(user.storage_used || 0)}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary edit-user-btn" title="Edit User">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-${user.status === 'active' ? 'warning' : 'success'} toggle-user-status-btn" 
                                title="${user.status === 'active' ? 'Deactivate' : 'Activate'} User">
                            <i class="fas fa-${user.status === 'active' ? 'ban' : 'check'}"></i>
                        </button>
                        <button class="btn btn-outline-danger delete-user-btn" title="Delete User">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        // Update select all checkbox
        const selectAllCheckbox = document.getElementById('selectAllUsers');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.addEventListener('change', (e) => {
                document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                    checkbox.checked = e.target.checked;
                });
            });
        }
    }

    getRoleBadgeColor(role) {
        const colorMap = {
            admin: 'danger',
            moderator: 'warning',
            user: 'primary'
        };
        return colorMap[role] || 'secondary';
    }

    updateUserPagination(pagination) {
        const paginationContainer = document.getElementById('userPagination');
        if (!paginationContainer || !pagination) return;

        const { current_page, total_pages, total_items } = pagination;
        
        if (total_pages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        let paginationHTML = '<nav><ul class="pagination pagination-sm">';
        
        if (current_page > 1) {
            paginationHTML += `<li class="page-item">
                <button class="page-link user-pagination-btn" data-page="${current_page - 1}">Previous</button>
            </li>`;
        }

        const startPage = Math.max(1, current_page - 2);
        const endPage = Math.min(total_pages, current_page + 2);

        for (let i = startPage; i <= endPage; i++) {
            paginationHTML += `<li class="page-item ${i === current_page ? 'active' : ''}">
                <button class="page-link user-pagination-btn" data-page="${i}">${i}</button>
            </li>`;
        }

        if (current_page < total_pages) {
            paginationHTML += `<li class="page-item">
                <button class="page-link user-pagination-btn" data-page="${current_page + 1}">Next</button>
            </li>`;
        }

        paginationHTML += '</ul></nav>';
        paginationContainer.innerHTML = paginationHTML;

        // Update users info
        const usersInfo = document.getElementById('usersInfo');
        if (usersInfo) {
            const start = (current_page - 1) * this.usersPerPage + 1;
            const end = Math.min(current_page * this.usersPerPage, total_items);
            usersInfo.textContent = `Showing ${start}-${end} of ${total_items} users`;
        }
    }

    async searchUsers(query) {
        if (!query.trim()) {
            this.loadUsers();
            return;
        }

        try {
            const response = await window.app.api.request(`/admin/users/search?q=${encodeURIComponent(query)}`, {
                method: 'GET'
            });

            if (response.success) {
                this.displayUsers(response.data.users);
                document.getElementById('userPagination').innerHTML = '';
            } else {
                throw new Error(response.message || 'Search failed');
            }
        } catch (error) {
            console.error('User search error:', error);
            window.app.showNotification('User search failed', 'error');
        }
    }

    showAddUserModal() {
        const modal = document.getElementById('addUserModal') || this.createAddUserModal();
        const form = modal.querySelector('#addUserForm');
        form.reset();
        new bootstrap.Modal(modal).show();
    }

    createAddUserModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'addUserModal';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addUserForm">
                            <div class="mb-3">
                                <label for="addUserName" class="form-label">Name</label>
                                <input type="text" class="form-control" id="addUserName" required>
                            </div>
                            <div class="mb-3">
                                <label for="addUserEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="addUserEmail" required>
                            </div>
                            <div class="mb-3">
                                <label for="addUserPassword" class="form-label">Password</label>
                                <input type="password" class="form-control" id="addUserPassword" required>
                            </div>
                            <div class="mb-3">
                                <label for="addUserRole" class="form-label">Role</label>
                                <select class="form-select" id="addUserRole" required>
                                    <option value="user">User</option>
                                    <option value="moderator">Moderator</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sendWelcomeEmail" checked>
                                    <label class="form-check-label" for="sendWelcomeEmail">
                                        Send welcome email
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmAddUser">Add User</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Bind add user action
        modal.querySelector('#confirmAddUser').addEventListener('click', () => {
            this.addUser();
        });

        return modal;
    }

    async addUser() {
        const form = document.getElementById('addUserForm');
        const formData = new FormData(form);
        
        const userData = {
            name: formData.get('addUserName') || document.getElementById('addUserName').value,
            email: formData.get('addUserEmail') || document.getElementById('addUserEmail').value,
            password: formData.get('addUserPassword') || document.getElementById('addUserPassword').value,
            role: formData.get('addUserRole') || document.getElementById('addUserRole').value,
            send_welcome_email: document.getElementById('sendWelcomeEmail').checked
        };

        if (!userData.name || !userData.email || !userData.password) {
            window.app.showNotification('Please fill in all required fields', 'warning');
            return;
        }

        try {
            const response = await window.app.api.request('/admin/users', {
                method: 'POST',
                body: JSON.stringify(userData)
            });

            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
                window.app.showNotification('User added successfully', 'success');
                this.loadUsers();
            } else {
                throw new Error(response.message || 'Failed to add user');
            }
        } catch (error) {
            console.error('Add user error:', error);
            window.app.showNotification('Failed to add user: ' + error.message, 'error');
        }
    }

    async editUser(userId) {
        try {
            const response = await window.app.api.request(`/admin/users/${userId}`, {
                method: 'GET'
            });

            if (response.success) {
                this.showEditUserModal(response.data);
            } else {
                throw new Error(response.message || 'Failed to load user');
            }
        } catch (error) {
            console.error('Edit user error:', error);
            window.app.showNotification('Failed to load user data', 'error');
        }
    }

    showEditUserModal(user) {
        const modal = document.getElementById('editUserModal') || this.createEditUserModal();
        
        // Populate form
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUserName').value = user.name;
        document.getElementById('editUserEmail').value = user.email;
        document.getElementById('editUserRole').value = user.role;
        document.getElementById('editUserStatus').value = user.status;
        
        new bootstrap.Modal(modal).show();
    }

    createEditUserModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'editUserModal';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editUserForm">
                            <input type="hidden" id="editUserId">
                            <div class="mb-3">
                                <label for="editUserName" class="form-label">Name</label>
                                <input type="text" class="form-control" id="editUserName" required>
                            </div>
                            <div class="mb-3">
                                <label for="editUserEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="editUserEmail" required>
                            </div>
                            <div class="mb-3">
                                <label for="editUserRole" class="form-label">Role</label>
                                <select class="form-select" id="editUserRole" required>
                                    <option value="user">User</option>
                                    <option value="moderator">Moderator</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editUserStatus" class="form-label">Status</label>
                                <select class="form-select" id="editUserStatus" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmEditUser">Update User</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Bind edit user action
        modal.querySelector('#confirmEditUser').addEventListener('click', () => {
            this.updateUser();
        });

        return modal;
    }

    async updateUser() {
        const userId = document.getElementById('editUserId').value;
        const userData = {
            name: document.getElementById('editUserName').value,
            email: document.getElementById('editUserEmail').value,
            role: document.getElementById('editUserRole').value,
            status: document.getElementById('editUserStatus').value
        };

        try {
            const response = await window.app.api.request(`/admin/users/${userId}`, {
                method: 'PUT',
                body: JSON.stringify(userData)
            });

            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
                window.app.showNotification('User updated successfully', 'success');
                this.loadUsers();
            } else {
                throw new Error(response.message || 'Failed to update user');
            }
        } catch (error) {
            console.error('Update user error:', error);
            window.app.showNotification('Failed to update user: ' + error.message, 'error');
        }
    }

    async deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await window.app.api.request(`/admin/users/${userId}`, {
                method: 'DELETE'
            });

            if (response.success) {
                window.app.showNotification('User deleted successfully', 'success');
                this.loadUsers();
            } else {
                throw new Error(response.message || 'Failed to delete user');
            }
        } catch (error) {
            console.error('Delete user error:', error);
            window.app.showNotification('Failed to delete user: ' + error.message, 'error');
        }
    }

    async toggleUserStatus(userId) {
        try {
            const response = await window.app.api.request(`/admin/users/${userId}/toggle-status`, {
                method: 'POST'
            });

            if (response.success) {
                window.app.showNotification('User status updated', 'success');
                this.loadUsers();
            } else {
                throw new Error(response.message || 'Failed to update status');
            }
        } catch (error) {
            console.error('Toggle status error:', error);
            window.app.showNotification('Failed to update user status', 'error');
        }
    }

    performBulkAction() {
        const selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
        const action = document.getElementById('bulkActionSelect')?.value;

        if (selectedUsers.length === 0) {
            window.app.showNotification('Please select users first', 'warning');
            return;
        }

        if (!action) {
            window.app.showNotification('Please select an action', 'warning');
            return;
        }

        const confirmMessage = `Are you sure you want to ${action} ${selectedUsers.length} user(s)?`;
        if (!confirm(confirmMessage)) {
            return;
        }

        this.executeBulkAction(action, selectedUsers);
    }

    async executeBulkAction(action, userIds) {
        try {
            const response = await window.app.api.request('/admin/users/bulk', {
                method: 'POST',
                body: JSON.stringify({
                    action: action,
                    user_ids: userIds
                })
            });

            if (response.success) {
                window.app.showNotification(`Bulk ${action} completed successfully`, 'success');
                this.loadUsers();
                
                // Clear selection
                document.getElementById('selectAllUsers').checked = false;
                document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
            } else {
                throw new Error(response.message || 'Bulk action failed');
            }
        } catch (error) {
            console.error('Bulk action error:', error);
            window.app.showNotification('Bulk action failed: ' + error.message, 'error');
        }
    }

    async loadSystemLogs() {
        try {
            const response = await window.app.api.request('/admin/logs', {
                method: 'GET'
            });

            if (response.success) {
                this.displaySystemLogs(response.data);
            } else {
                throw new Error(response.message || 'Failed to load logs');
            }
        } catch (error) {
            console.error('Load logs error:', error);
            window.app.showNotification('Failed to load system logs', 'error');
        }
    }

    displaySystemLogs(logs) {
        const logsContainer = document.getElementById('systemLogs');
        if (!logsContainer || !logs) return;

        logsContainer.innerHTML = logs.map(log => `
            <div class="log-entry log-${log.level}">
                <div class="log-timestamp">${this.formatDate(log.timestamp)}</div>
                <div class="log-level">
                    <span class="badge bg-${this.getLogLevelColor(log.level)}">${log.level.toUpperCase()}</span>
                </div>
                <div class="log-message">${log.message}</div>
                ${log.context ? `<div class="log-context"><pre>${JSON.stringify(log.context, null, 2)}</pre></div>` : ''}
            </div>
        `).join('');
    }

    getLogLevelColor(level) {
        const colorMap = {
            error: 'danger',
            warning: 'warning',
            info: 'info',
            debug: 'secondary'
        };
        return colorMap[level] || 'secondary';
    }

    initializeCharts() {
        // Initialize Chart.js charts for analytics
        this.initStorageChart();
        this.initUsageChart();
        this.initActivityChart();
    }

    initStorageChart() {
        const ctx = document.getElementById('storageChart');
        if (!ctx) return;

        this.charts.storage = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Used', 'Available'],
                datasets: [{
                    data: [0, 100],
                    backgroundColor: ['#007bff', '#e9ecef'],
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

        this.charts.usage = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Uploads',
                    data: [],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Downloads',
                    data: [],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
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

    initActivityChart() {
        const ctx = document.getElementById('activityChart');
        if (!ctx) return;

        this.charts.activity = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Activity',
                    data: [0, 0, 0, 0, 0, 0, 0],
                    backgroundColor: '#007bff'
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

    async refreshCharts() {
        try {
            const response = await window.app.api.request('/admin/analytics', {
                method: 'GET'
            });

            if (response.success) {
                this.updateCharts(response.data);
            } else {
                throw new Error(response.message || 'Failed to load analytics');
            }
        } catch (error) {
            console.error('Analytics error:', error);
            window.app.showNotification('Failed to load analytics', 'error');
        }
    }

    updateCharts(data) {
        // Update storage chart
        if (this.charts.storage && data.storage) {
            const used = data.storage.used;
            const total = data.storage.total;
            const available = total - used;
            
            this.charts.storage.data.datasets[0].data = [used, available];
            this.charts.storage.update();
        }

        // Update usage chart
        if (this.charts.usage && data.usage) {
            this.charts.usage.data.labels = data.usage.labels;
            this.charts.usage.data.datasets[0].data = data.usage.uploads;
            this.charts.usage.data.datasets[1].data = data.usage.downloads;
            this.charts.usage.update();
        }

        // Update activity chart
        if (this.charts.activity && data.activity) {
            this.charts.activity.data.datasets[0].data = data.activity.weekly;
            this.charts.activity.update();
        }
    }

    startRealTimeUpdates() {
        if (this.realTimeUpdates) {
            this.refreshInterval = setInterval(() => {
                this.loadDashboardData();
            }, 30000); // Update every 30 seconds
        }
    }

    toggleRealTimeUpdates(enabled) {
        this.realTimeUpdates = enabled;
        
        if (enabled) {
            this.startRealTimeUpdates();
        } else if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    switchTab(target) {
        // Hide all tab panes
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });

        // Show target pane
        const targetPane = document.querySelector(target);
        if (targetPane) {
            targetPane.classList.add('show', 'active');
        }

        // Load tab-specific data
        switch (target) {
            case '#users-tab':
                this.loadUsers();
                break;
            case '#analytics-tab':
                this.refreshCharts();
                break;
            case '#logs-tab':
                this.loadSystemLogs();
                break;
        }
    }

    async saveSystemSettings() {
        const settings = {};
        
        // Collect all settings from form
        document.querySelectorAll('[data-setting]').forEach(input => {
            const key = input.dataset.setting;
            const value = input.type === 'checkbox' ? input.checked : input.value;
            settings[key] = value;
        });

        try {
            const response = await window.app.api.request('/admin/settings', {
                method: 'POST',
                body: JSON.stringify(settings)
            });

            if (response.success) {
                window.app.showNotification('Settings saved successfully', 'success');
            } else {
                throw new Error(response.message || 'Failed to save settings');
            }
        } catch (error) {
            console.error('Save settings error:', error);
            window.app.showNotification('Failed to save settings: ' + error.message, 'error');
        }
    }

    // Utility methods
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    formatDate(dateString) {
        if (!dateString) return 'Never';
        return new Date(dateString).toLocaleDateString() + ' ' + new Date(dateString).toLocaleTimeString();
    }

    formatRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);
        
        if (days > 0) return `${days} day${days > 1 ? 's' : ''} ago`;
        if (hours > 0) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        if (minutes > 0) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        return 'Just now';
    }
}

// Initialize admin dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('adminDashboard')) {
        window.adminDashboard = new AdminDashboard();
    }
});
