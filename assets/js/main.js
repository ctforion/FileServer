// Main JavaScript file for FileServer
class FileServer {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.initTooltips();
        this.checkAuth();
    }

    bindEvents() {
        // Global event listeners
        document.addEventListener('DOMContentLoaded', () => {
            this.initNotifications();
            this.initModals();
        });

        // CSRF token refresh
        setInterval(() => {
            this.refreshCSRFToken();
        }, 300000); // 5 minutes
    }

    // Authentication check
    async checkAuth() {
        try {
            const response = await fetch('/api/auth.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            const data = await response.json();
            if (!data.authenticated && !window.location.pathname.includes('login')) {
                window.location.href = '/login.php';
            }
        } catch (error) {
            console.error('Auth check failed:', error);
        }
    }

    // Refresh CSRF token
    async refreshCSRFToken() {
        try {
            const response = await fetch('/api/auth.php?action=csrf', {
                method: 'GET'
            });
            const data = await response.json();
            
            // Update all CSRF tokens on page
            const tokens = document.querySelectorAll('input[name="csrf_token"], meta[name="csrf-token"]');
            tokens.forEach(token => {
                if (token.tagName === 'INPUT') {
                    token.value = data.csrf_token;
                } else {
                    token.setAttribute('content', data.csrf_token);
                }
            });
        } catch (error) {
            console.error('CSRF token refresh failed:', error);
        }
    }

    // Show notification
    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button class="notification-close">&times;</button>
        `;

        document.body.appendChild(notification);

        // Auto remove
        setTimeout(() => {
            notification.remove();
        }, duration);

        // Manual close
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.remove();
        });
    }

    // Initialize notifications container
    initNotifications() {
        if (!document.querySelector('.notifications-container')) {
            const container = document.createElement('div');
            container.className = 'notifications-container';
            document.body.appendChild(container);
        }
    }

    // Initialize modals
    initModals() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            const closeBtn = modal.querySelector('.modal-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.closeModal(modal);
                });
            }

            // Close on backdrop click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeModal(modal);
                }
            });
        });
    }

    // Show modal
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            document.body.classList.add('modal-open');
        }
    }

    // Close modal
    closeModal(modal) {
        if (typeof modal === 'string') {
            modal = document.getElementById(modal);
        }
        if (modal) {
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
    }

    // Initialize tooltips
    initTooltips() {
        const tooltips = document.querySelectorAll('[data-tooltip]');
        tooltips.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target, e.target.dataset.tooltip);
            });
            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }

    // Show tooltip
    showTooltip(element, text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;
        document.body.appendChild(tooltip);

        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
    }

    // Hide tooltip
    hideTooltip() {
        const tooltip = document.querySelector('.tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    }

    // Format file size
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Format date
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    // Copy to clipboard
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showNotification('Copied to clipboard', 'success');
        } catch (error) {
            console.error('Copy failed:', error);
            this.showNotification('Copy failed', 'error');
        }
    }
}

// Initialize FileServer
const fileServer = new FileServer();

// Global utility functions
window.showNotification = (message, type, duration) => fileServer.showNotification(message, type, duration);
window.showModal = (modalId) => fileServer.showModal(modalId);
window.closeModal = (modalId) => fileServer.closeModal(modalId);
window.formatFileSize = (bytes) => fileServer.formatFileSize(bytes);
window.formatDate = (dateString) => fileServer.formatDate(dateString);
window.copyToClipboard = (text) => fileServer.copyToClipboard(text);
