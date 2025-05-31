/**
 * FileServer JavaScript Application
 * Main application script handling global functionality
 */

class FileServerApp {
    constructor() {
        this.config = {
            maxFileSize: 100 * 1024 * 1024, // 100MB default
            allowedTypes: ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'],
            previewTypes: ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt'],
            chunkSize: 1024 * 1024, // 1MB chunks for upload
        };
        
        this.currentUser = null;
        this.darkMode = localStorage.getItem('darkMode') === 'true';
        this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.initializeTheme();
        this.loadUserInfo();
        this.setupAjaxDefaults();
        this.initializeModules();
    }
    
    setupEventListeners() {
        // Global keyboard shortcuts
        document.addEventListener('keydown', this.handleKeyboardShortcuts.bind(this));
        
        // Theme toggle
        const themeToggle = document.querySelector('[data-theme-toggle]');
        if (themeToggle) {
            themeToggle.addEventListener('click', this.toggleTheme.bind(this));
        }
        
        // Navigation menu toggle
        const menuToggle = document.querySelector('[data-menu-toggle]');
        const mobileMenu = document.querySelector('[data-mobile-menu]');
        if (menuToggle && mobileMenu) {
            menuToggle.addEventListener('click', () => {
                mobileMenu.classList.toggle('active');
            });
        }
        
        // Close mobile menu on outside click
        document.addEventListener('click', (e) => {
            const mobileMenu = document.querySelector('[data-mobile-menu]');
            const menuToggle = document.querySelector('[data-menu-toggle]');
            if (mobileMenu && !mobileMenu.contains(e.target) && !menuToggle.contains(e.target)) {
                mobileMenu.classList.remove('active');
            }
        });
        
        // Form validation
        this.setupFormValidation();
        
        // AJAX forms
        this.setupAjaxForms();
        
        // Auto-save functionality
        this.setupAutoSave();
    }
    
    handleKeyboardShortcuts(e) {
        // Ctrl/Cmd + K for search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('[data-search-input]');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Ctrl/Cmd + U for upload
        if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
            e.preventDefault();
            const uploadBtn = document.querySelector('[data-upload-trigger]');
            if (uploadBtn) {
                uploadBtn.click();
            }
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            this.closeAllModals();
        }
    }
    
    initializeTheme() {
        document.documentElement.setAttribute('data-theme', this.darkMode ? 'dark' : 'light');
        
        // Update theme toggle button
        const themeToggle = document.querySelector('[data-theme-toggle]');
        if (themeToggle) {
            const icon = themeToggle.querySelector('i');
            if (icon) {
                icon.className = this.darkMode ? 'fas fa-sun' : 'fas fa-moon';
            }
        }
    }
    
    toggleTheme() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('darkMode', this.darkMode);
        this.initializeTheme();
        
        // Animate transition if not reduced motion
        if (!this.reducedMotion) {
            document.documentElement.style.transition = 'color-scheme 0.3s ease';
            setTimeout(() => {
                document.documentElement.style.transition = '';
            }, 300);
        }
    }
    
    setupAjaxDefaults() {
        // Set default headers for AJAX requests
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            this.csrfToken = token.getAttribute('content');
        }
    }
    
    setupFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            form.addEventListener('submit', this.validateForm.bind(this));
            
            // Real-time validation
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('blur', () => this.validateField(input));
                input.addEventListener('input', () => this.clearFieldError(input));
            });
        });
    }
    
    validateForm(e) {
        const form = e.target;
        let isValid = true;
        
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            const firstError = form.querySelector('.field-error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }
    
    validateField(field) {
        const value = field.value.trim();
        const type = field.type;
        let isValid = true;
        let message = '';
        
        // Required validation
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            message = 'This field is required';
        }
        
        // Email validation
        else if (type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                message = 'Please enter a valid email address';
            }
        }
        
        // Password validation
        else if (field.name === 'password' && value) {
            if (value.length < 8) {
                isValid = false;
                message = 'Password must be at least 8 characters long';
            }
        }
        
        // Confirm password validation
        else if (field.name === 'confirm_password' && value) {
            const password = document.querySelector('input[name="password"]');
            if (password && value !== password.value) {
                isValid = false;
                message = 'Passwords do not match';
            }
        }
        
        this.displayFieldValidation(field, isValid, message);
        return isValid;
    }
    
    displayFieldValidation(field, isValid, message) {
        const fieldGroup = field.closest('.field-group') || field.parentNode;
        let errorElement = fieldGroup.querySelector('.field-error');
        
        if (!isValid) {
            field.classList.add('error');
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'field-error';
                fieldGroup.appendChild(errorElement);
            }
            errorElement.textContent = message;
        } else {
            field.classList.remove('error');
            if (errorElement) {
                errorElement.remove();
            }
        }
    }
    
    clearFieldError(field) {
        field.classList.remove('error');
        const fieldGroup = field.closest('.field-group') || field.parentNode;
        const errorElement = fieldGroup.querySelector('.field-error');
        if (errorElement) {
            errorElement.remove();
        }
    }
    
    setupAjaxForms() {
        const ajaxForms = document.querySelectorAll('form[data-ajax]');
        ajaxForms.forEach(form => {
            form.addEventListener('submit', this.handleAjaxForm.bind(this));
        });
    }
    
    async handleAjaxForm(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        try {
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            
            const formData = new FormData(form);
            if (this.csrfToken) {
                formData.append('csrf_token', this.csrfToken);
            }
            
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification(result.message || 'Operation completed successfully', 'success');
                
                // Handle redirect
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1000);
                }
                
                // Reset form if specified
                if (result.reset) {
                    form.reset();
                }
                
                // Custom callback
                if (form.dataset.callback && window[form.dataset.callback]) {
                    window[form.dataset.callback](result);
                }
            } else {
                this.showNotification(result.message || 'An error occurred', 'error');
                
                // Display field errors
                if (result.errors) {
                    Object.keys(result.errors).forEach(fieldName => {
                        const field = form.querySelector(`[name="${fieldName}"]`);
                        if (field) {
                            this.displayFieldValidation(field, false, result.errors[fieldName]);
                        }
                    });
                }
            }
        } catch (error) {
            console.error('Ajax form error:', error);
            this.showNotification('Network error occurred', 'error');
        } finally {
            // Restore button state
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
    
    setupAutoSave() {
        const autoSaveElements = document.querySelectorAll('[data-autosave]');
        autoSaveElements.forEach(element => {
            let timeout;
            element.addEventListener('input', () => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    this.autoSave(element);
                }, 2000); // Save after 2 seconds of inactivity
            });
        });
    }
    
    async autoSave(element) {
        const url = element.dataset.autosave;
        const data = {
            field: element.name,
            value: element.value,
            id: element.dataset.id
        };
        
        try {
            await this.apiRequest(url, 'POST', data);
            this.showNotification('Auto-saved', 'info', 2000);
        } catch (error) {
            console.error('Auto-save failed:', error);
        }
    }
    
    initializeModules() {
        // Initialize page-specific modules
        if (window.FileUploader && document.querySelector('[data-uploader]')) {
            new FileUploader();
        }
        
        if (window.FileBrowser && document.querySelector('[data-file-browser]')) {
            new FileBrowser();
        }
        
        if (window.AdminPanel && document.querySelector('[data-admin-panel]')) {
            new AdminPanel();
        }
    }
    
    async loadUserInfo() {
        try {
            const response = await this.apiRequest('/api/user/info');
            this.currentUser = response.user;
        } catch (error) {
            console.error('Failed to load user info:', error);
        }
    }
    
    async apiRequest(url, method = 'GET', data = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        if (this.csrfToken) {
            options.headers['X-CSRF-Token'] = this.csrfToken;
        }
        
        if (data) {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(url, options);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    }
    
    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
                <button class="notification-close" aria-label="Close notification">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        const container = this.getNotificationContainer();
        container.appendChild(notification);
        
        // Auto-remove
        setTimeout(() => {
            this.removeNotification(notification);
        }, duration);
        
        // Manual close
        notification.querySelector('.notification-close').addEventListener('click', () => {
            this.removeNotification(notification);
        });
        
        // Animate in
        if (!this.reducedMotion) {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 10);
        }
    }
    
    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    getNotificationContainer() {
        let container = document.querySelector('.notification-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'notification-container';
            document.body.appendChild(container);
        }
        return container;
    }
    
    removeNotification(notification) {
        if (!this.reducedMotion) {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                notification.remove();
            }, 300);
        } else {
            notification.remove();
        }
    }
    
    closeAllModals() {
        const modals = document.querySelectorAll('.modal.active');
        modals.forEach(modal => {
            modal.classList.remove('active');
        });
    }
    
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            const firstInput = modal.querySelector('input, textarea, select, button');
            if (firstInput) {
                firstInput.focus();
            }
        }
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    formatDate(date) {
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
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
    
    copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                this.showNotification('Copied to clipboard', 'success', 2000);
            });
        } else {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            this.showNotification('Copied to clipboard', 'success', 2000);
        }
    }
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new FileServerApp();
});

// Export for modules
window.FileServerApp = FileServerApp;
