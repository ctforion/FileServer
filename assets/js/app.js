/**
 * FileServer Client-Side Application
 * Main JavaScript application file with API interactions and UI functionality
 */

class FileServerApp {
    constructor() {
        this.apiBaseUrl = '/api';
        this.token = localStorage.getItem('auth_token') || null;
        this.user = JSON.parse(localStorage.getItem('user')) || null;
        this.currentPage = window.location.pathname;
        this.uploadQueue = [];
        this.currentTheme = localStorage.getItem('theme') || 'light';
        
        this.init();
    }

    /**
     * Initialize the application
     */
    init() {
        this.setupGlobalEventListeners();
        this.initializeTheme();
        this.initializeTooltips();
        this.initializeModals();
        this.loadPageSpecificFunctionality();
        this.setupApiInterceptors();
        this.checkAuthStatus();
    }

    /**
     * Setup global event listeners
     */
    setupGlobalEventListeners() {
        // Theme toggle
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-theme-toggle]')) {
                this.toggleTheme();
            }
        });

        // Global keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'k':
                        e.preventDefault();
                        this.openSearchModal();
                        break;
                    case 'u':
                        e.preventDefault();
                        this.openUploadModal();
                        break;
                }
            }
        });

        // Auto-save forms
        document.addEventListener('input', (e) => {
            if (e.target.closest('[data-auto-save]')) {
                this.debounce(() => this.autoSaveForm(e.target.closest('form')), 1000)();
            }
        });

        // Confirmation dialogs
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-confirm]')) {
                const message = e.target.closest('[data-confirm]').dataset.confirm;
                if (!confirm(message)) {
                    e.preventDefault();
                }
            }
        });
    }

    /**
     * Initialize theme system
     */
    initializeTheme() {
        document.documentElement.setAttribute('data-theme', this.currentTheme);
        const toggleBtn = document.querySelector('[data-theme-toggle]');
        if (toggleBtn) {
            toggleBtn.innerHTML = this.currentTheme === 'dark' ? 
                '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        }
    }

    /**
     * Toggle theme between light and dark
     */
    toggleTheme() {
        this.currentTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', this.currentTheme);
        localStorage.setItem('theme', this.currentTheme);
        this.initializeTheme();
    }

    /**
     * Initialize tooltips
     */
    initializeTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target, e.target.dataset.tooltip);
            });
            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }

    /**
     * Show tooltip
     */
    showTooltip(element, text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;
        document.body.appendChild(tooltip);

        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        tooltip.style.opacity = '1';
    }

    /**
     * Hide tooltip
     */
    hideTooltip() {
        const tooltip = document.querySelector('.tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    }

    /**
     * Initialize modals
     */
    initializeModals() {
        // Modal triggers
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-modal-target]')) {
                const modalId = e.target.closest('[data-modal-target]').dataset.modalTarget;
                this.openModal(modalId);
            }
            
            if (e.target.closest('[data-modal-close]')) {
                this.closeModal();
            }
        });

        // Close modal on backdrop click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-backdrop')) {
                this.closeModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModal();
            }
        });
    }

    /**
     * Open modal
     */
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.classList.add('modal-open');
            setTimeout(() => modal.classList.add('active'), 10);
        }
    }

    /**
     * Close modal
     */
    closeModal() {
        const activeModal = document.querySelector('.modal.active');
        if (activeModal) {
            activeModal.classList.remove('active');
            setTimeout(() => {
                activeModal.style.display = 'none';
                document.body.classList.remove('modal-open');
            }, 200);
        }
    }

    /**
     * Load page-specific functionality
     */
    loadPageSpecificFunctionality() {
        const page = this.getPageName();
        
        switch (page) {
            case 'login':
                this.initLoginPage();
                break;
            case 'dashboard':
                this.initDashboardPage();
                break;
            case 'upload':
                this.initUploadPage();
                break;
            case 'files':
                this.initFilesPage();
                break;
            case 'profile':
                this.initProfilePage();
                break;
            case 'search':
                this.initSearchPage();
                break;
            case 'admin':
                this.initAdminPage();
                break;
            case 'settings':
                this.initSettingsPage();
                break;
            default:
                this.initHomePage();
        }
    }

    /**
     * Get current page name
     */
    getPageName() {
        const path = window.location.pathname;
        if (path === '/' || path === '/index.php') return 'home';
        return path.replace(/^\//, '').replace(/\.php$/, '');
    }

    /**
     * Setup API interceptors
     */
    setupApiInterceptors() {
        // Add token to all API requests
        const originalFetch = window.fetch;
        window.fetch = (...args) => {
            const [url, options = {}] = args;
            
            if (url.startsWith(this.apiBaseUrl) && this.token) {
                options.headers = {
                    ...options.headers,
                    'Authorization': `Bearer ${this.token}`
                };
            }
            
            return originalFetch(url, options)
                .then(response => {
                    if (response.status === 401) {
                        this.handleUnauthorized();
                    }
                    return response;
                });
        };
    }

    /**
     * Check authentication status
     */
    async checkAuthStatus() {
        if (!this.token) return;

        try {
            const response = await this.apiRequest('/auth/profile');
            if (response.success) {
                this.user = response.data;
                localStorage.setItem('user', JSON.stringify(this.user));
                this.updateUserInterface();
            }
        } catch (error) {
            console.log('Auth check failed:', error);
            this.handleUnauthorized();
        }
    }

    /**
     * Handle unauthorized access
     */
    handleUnauthorized() {
        this.token = null;
        this.user = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        
        if (this.getPageName() !== 'login') {
            window.location.href = '/login';
        }
    }

    /**
     * Update user interface with user data
     */
    updateUserInterface() {
        if (!this.user) return;

        // Update user avatar and name
        const userAvatars = document.querySelectorAll('[data-user-avatar]');
        userAvatars.forEach(avatar => {
            avatar.src = this.user.avatar || '/assets/images/default-avatar.png';
        });

        const userNames = document.querySelectorAll('[data-user-name]');
        userNames.forEach(nameEl => {
            nameEl.textContent = this.user.name || this.user.username;
        });

        // Show/hide elements based on user role
        const adminElements = document.querySelectorAll('[data-admin-only]');
        adminElements.forEach(el => {
            el.style.display = this.user.role === 'admin' ? 'block' : 'none';
        });

        const moderatorElements = document.querySelectorAll('[data-moderator-only]');
        moderatorElements.forEach(el => {
            el.style.display = ['admin', 'moderator'].includes(this.user.role) ? 'block' : 'none';
        });
    }

    /**
     * API request helper
     */
    async apiRequest(endpoint, options = {}) {
        const url = `${this.apiBaseUrl}${endpoint}`;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                ...(this.token && { 'Authorization': `Bearer ${this.token}` })
            }
        };

        const response = await fetch(url, { ...defaultOptions, ...options });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'API request failed');
        }

        return data;
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info', duration = 3000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${this.getNotificationIcon(type)}"></i>
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        const container = this.getOrCreateNotificationContainer();
        container.appendChild(notification);

        // Auto remove after duration
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, duration);

        // Animate in
        setTimeout(() => notification.classList.add('show'), 10);
    }

    /**
     * Get notification icon based on type
     */
    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    /**
     * Get or create notification container
     */
    getOrCreateNotificationContainer() {
        let container = document.getElementById('notifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notifications';
            container.className = 'notifications-container';
            document.body.appendChild(container);
        }
        return container;
    }

    /**
     * Debounce function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Auto-save form
     */
    async autoSaveForm(form) {
        if (!form) return;

        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        try {
            await this.apiRequest('/user/auto-save', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            this.showNotification('Changes saved automatically', 'success', 1000);
        } catch (error) {
            console.error('Auto-save failed:', error);
        }
    }

    /**
     * Format file size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Format date
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));

        if (days === 0) {
            return 'Today';
        } else if (days === 1) {
            return 'Yesterday';
        } else if (days < 7) {
            return `${days} days ago`;
        } else {
            return date.toLocaleDateString();
        }
    }

    /**
     * Copy to clipboard
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showNotification('Copied to clipboard', 'success', 1000);
        } catch (error) {
            console.error('Failed to copy:', error);
            this.showNotification('Failed to copy to clipboard', 'error');
        }
    }

    /**
     * Download file
     */
    downloadFile(url, filename) {
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    /**
     * Open search modal
     */
    openSearchModal() {
        this.openModal('searchModal');
        const searchInput = document.querySelector('#searchModal input[type="text"]');
        if (searchInput) {
            searchInput.focus();
        }
    }

    /**
     * Open upload modal
     */
    openUploadModal() {
        this.openModal('uploadModal');
    }

    // Page-specific initialization methods will be implemented in separate files
    initHomePage() {
        // Implement home page functionality
    }

    initLoginPage() {
        // Will be implemented in login.js
    }

    initDashboardPage() {
        // Will be implemented in dashboard.js
    }

    initUploadPage() {
        // Will be implemented in upload.js
    }

    initFilesPage() {
        // Will be implemented in files.js
    }

    initProfilePage() {
        // Will be implemented in profile.js
    }

    initSearchPage() {
        // Will be implemented in search.js
    }

    initAdminPage() {
        // Will be implemented in admin.js
    }

    initSettingsPage() {
        // Will be implemented in settings.js
    }
}

// Initialize the application when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.app = new FileServerApp();
});

// Export for use in other files
window.FileServerApp = FileServerApp;
