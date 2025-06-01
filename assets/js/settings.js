/**
 * Settings Page JavaScript
 * Handles system settings, user preferences, security configurations, and integrations
 */

class SettingsPage {
    constructor() {
        this.currentSection = 'general';
        this.unsavedChanges = false;
        this.validators = {};
        this.originalSettings = {};
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadSettings();
        this.initializeValidators();
        this.setupAutoSave();
        this.loadIntegrations();
    }

    bindEvents() {
        // Section navigation
        document.querySelectorAll('.settings-nav .nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = e.target.getAttribute('href').substring(1);
                this.switchSection(section);
            });
        });

        // Save buttons
        const saveBtn = document.getElementById('saveSettings');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                this.saveSettings();
            });
        }

        const resetBtn = document.getElementById('resetSettings');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this.resetSettings();
            });
        }

        // Form change detection
        document.addEventListener('change', (e) => {
            if (e.target.matches('.setting-input')) {
                this.markAsChanged();
                this.validateField(e.target);
            }
        });

        document.addEventListener('input', (e) => {
            if (e.target.matches('.setting-input')) {
                this.markAsChanged();
                clearTimeout(this.validateTimeout);
                this.validateTimeout = setTimeout(() => {
                    this.validateField(e.target);
                }, 500);
            }
        });

        // Test connections
        document.querySelectorAll('.test-connection-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const service = e.target.dataset.service;
                this.testConnection(service);
            });
        });

        // Import/Export
        const importBtn = document.getElementById('importSettings');
        if (importBtn) {
            importBtn.addEventListener('click', () => {
                this.showImportModal();
            });
        }

        const exportBtn = document.getElementById('exportSettings');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.exportSettings();
            });
        }

        // Backup settings
        const backupBtn = document.getElementById('createBackup');
        if (backupBtn) {
            backupBtn.addEventListener('click', () => {
                this.createBackup();
            });
        }

        const restoreBtn = document.getElementById('restoreBackup');
        if (restoreBtn) {
            restoreBtn.addEventListener('click', () => {
                this.showRestoreModal();
            });
        }

        // Clear cache
        const clearCacheBtn = document.getElementById('clearCache');
        if (clearCacheBtn) {
            clearCacheBtn.addEventListener('click', () => {
                this.clearCache();
            });
        }

        // Regenerate keys
        const regenerateKeysBtn = document.getElementById('regenerateKeys');
        if (regenerateKeysBtn) {
            regenerateKeysBtn.addEventListener('click', () => {
                this.regenerateSecurityKeys();
            });
        }

        // Plugin management
        document.addEventListener('click', (e) => {
            if (e.target.matches('.enable-plugin-btn')) {
                const pluginId = e.target.dataset.pluginId;
                this.togglePlugin(pluginId, true);
            } else if (e.target.matches('.disable-plugin-btn')) {
                const pluginId = e.target.dataset.pluginId;
                this.togglePlugin(pluginId, false);
            } else if (e.target.matches('.configure-plugin-btn')) {
                const pluginId = e.target.dataset.pluginId;
                this.configurePlugin(pluginId);
            }
        });

        // Before unload warning
        window.addEventListener('beforeunload', (e) => {
            if (this.unsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });

        // Theme preview
        document.querySelectorAll('.theme-preview').forEach(preview => {
            preview.addEventListener('click', (e) => {
                const theme = e.target.dataset.theme;
                this.previewTheme(theme);
            });
        });

        // Advanced toggles
        document.querySelectorAll('.advanced-toggle').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                const target = e.target.dataset.target;
                this.toggleAdvancedSection(target);
            });
        });
    }

    async loadSettings() {
        try {
            const response = await window.app.api.request('/settings', {
                method: 'GET'
            });

            if (response.success) {
                this.originalSettings = { ...response.data };
                this.populateSettings(response.data);
            } else {
                throw new Error(response.message || 'Failed to load settings');
            }
        } catch (error) {
            console.error('Load settings error:', error);
            window.app.showNotification('Failed to load settings', 'error');
        }
    }

    populateSettings(settings) {
        Object.keys(settings).forEach(key => {
            const input = document.querySelector(`[data-setting="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = settings[key];
                } else if (input.type === 'radio') {
                    const radio = document.querySelector(`[data-setting="${key}"][value="${settings[key]}"]`);
                    if (radio) radio.checked = true;
                } else {
                    input.value = settings[key];
                }

                // Trigger change event for dependent fields
                input.dispatchEvent(new Event('change'));
            }
        });

        this.unsavedChanges = false;
        this.updateSaveButton();
    }

    initializeValidators() {
        this.validators = {
            email: (value) => {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(value) || 'Invalid email format';
            },
            url: (value) => {
                try {
                    new URL(value);
                    return true;
                } catch {
                    return 'Invalid URL format';
                }
            },
            number: (value, min, max) => {
                const num = parseFloat(value);
                if (isNaN(num)) return 'Must be a number';
                if (min !== undefined && num < min) return `Must be at least ${min}`;
                if (max !== undefined && num > max) return `Must be at most ${max}`;
                return true;
            },
            required: (value) => {
                return value.trim() !== '' || 'This field is required';
            },
            password: (value) => {
                if (value.length < 8) return 'Password must be at least 8 characters';
                if (!/(?=.*[a-z])/.test(value)) return 'Password must contain lowercase letters';
                if (!/(?=.*[A-Z])/.test(value)) return 'Password must contain uppercase letters';
                if (!/(?=.*\d)/.test(value)) return 'Password must contain numbers';
                return true;
            }
        };
    }

    validateField(field) {
        const value = field.value;
        const validators = field.dataset.validators?.split(',') || [];
        const feedbackElement = field.parentNode.querySelector('.invalid-feedback');
        
        let isValid = true;
        let errorMessage = '';

        for (const validator of validators) {
            const [name, ...params] = validator.split(':');
            const validatorFn = this.validators[name];
            
            if (validatorFn) {
                const result = validatorFn(value, ...params);
                if (result !== true) {
                    isValid = false;
                    errorMessage = result;
                    break;
                }
            }
        }

        // Update field appearance
        field.classList.toggle('is-invalid', !isValid);
        field.classList.toggle('is-valid', isValid && value !== '');
        
        if (feedbackElement) {
            feedbackElement.textContent = errorMessage;
        }

        return isValid;
    }

    validateAllFields() {
        const fields = document.querySelectorAll('.setting-input[data-validators]');
        let allValid = true;

        fields.forEach(field => {
            if (!this.validateField(field)) {
                allValid = false;
            }
        });

        return allValid;
    }

    async saveSettings() {
        if (!this.validateAllFields()) {
            window.app.showNotification('Please fix validation errors before saving', 'warning');
            return;
        }

        const settings = {};
        
        // Collect all settings
        document.querySelectorAll('.setting-input').forEach(input => {
            const key = input.dataset.setting;
            if (key) {
                let value = input.value;
                
                if (input.type === 'checkbox') {
                    value = input.checked;
                } else if (input.type === 'number') {
                    value = parseFloat(value);
                } else if (input.type === 'range') {
                    value = parseInt(value);
                }
                
                settings[key] = value;
            }
        });

        try {
            const response = await window.app.api.request('/settings', {
                method: 'POST',
                body: JSON.stringify(settings)
            });

            if (response.success) {
                this.originalSettings = { ...settings };
                this.unsavedChanges = false;
                this.updateSaveButton();
                window.app.showNotification('Settings saved successfully', 'success');
                
                // Apply theme changes immediately if changed
                if (settings.theme && settings.theme !== document.documentElement.getAttribute('data-theme')) {
                    window.app.setTheme(settings.theme);
                }
            } else {
                throw new Error(response.message || 'Failed to save settings');
            }
        } catch (error) {
            console.error('Save settings error:', error);
            window.app.showNotification('Failed to save settings: ' + error.message, 'error');
        }
    }

    resetSettings() {
        if (!confirm('Are you sure you want to reset all settings to their original values?')) {
            return;
        }

        this.populateSettings(this.originalSettings);
        window.app.showNotification('Settings reset to original values', 'info');
    }

    markAsChanged() {
        this.unsavedChanges = true;
        this.updateSaveButton();
    }

    updateSaveButton() {
        const saveBtn = document.getElementById('saveSettings');
        if (saveBtn) {
            saveBtn.disabled = !this.unsavedChanges;
            saveBtn.textContent = this.unsavedChanges ? 'Save Changes' : 'Saved';
        }
    }

    setupAutoSave() {
        // Auto-save every 5 minutes if there are changes
        setInterval(() => {
            if (this.unsavedChanges) {
                this.saveSettings();
            }
        }, 5 * 60 * 1000);
    }

    switchSection(section) {
        // Update navigation
        document.querySelectorAll('.settings-nav .nav-link').forEach(link => {
            link.classList.remove('active');
        });
        
        document.querySelector(`.settings-nav .nav-link[href="#${section}"]`)?.classList.add('active');

        // Update content
        document.querySelectorAll('.settings-section').forEach(sec => {
            sec.classList.remove('active');
        });
        
        const targetSection = document.getElementById(section);
        if (targetSection) {
            targetSection.classList.add('active');
        }

        this.currentSection = section;

        // Load section-specific data
        switch (section) {
            case 'integrations':
                this.loadIntegrations();
                break;
            case 'plugins':
                this.loadPlugins();
                break;
            case 'backup':
                this.loadBackups();
                break;
        }
    }

    async loadIntegrations() {
        try {
            const response = await window.app.api.request('/settings/integrations', {
                method: 'GET'
            });

            if (response.success) {
                this.displayIntegrations(response.data);
            } else {
                throw new Error(response.message || 'Failed to load integrations');
            }
        } catch (error) {
            console.error('Load integrations error:', error);
            // Don't show error notification for optional features
        }
    }

    displayIntegrations(integrations) {
        const container = document.getElementById('integrationsContainer');
        if (!container) return;

        container.innerHTML = Object.keys(integrations).map(key => {
            const integration = integrations[key];
            return `
                <div class="integration-card">
                    <div class="integration-header">
                        <div class="integration-info">
                            <h5>${integration.name}</h5>
                            <p class="text-muted">${integration.description}</p>
                        </div>
                        <div class="integration-status">
                            <span class="badge bg-${integration.enabled ? 'success' : 'secondary'}">
                                ${integration.enabled ? 'Enabled' : 'Disabled'}
                            </span>
                        </div>
                    </div>
                    <div class="integration-body">
                        ${this.renderIntegrationSettings(key, integration)}
                    </div>
                    <div class="integration-footer">
                        <button class="btn btn-sm btn-outline-primary test-connection-btn" 
                                data-service="${key}">
                            Test Connection
                        </button>
                        <button class="btn btn-sm btn-${integration.enabled ? 'warning' : 'success'} toggle-integration-btn" 
                                data-service="${key}">
                            ${integration.enabled ? 'Disable' : 'Enable'}
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    renderIntegrationSettings(service, integration) {
        const settings = integration.settings || {};
        
        return Object.keys(settings).map(key => {
            const setting = settings[key];
            const inputType = setting.type || 'text';
            const value = setting.value || '';
            
            return `
                <div class="mb-3">
                    <label class="form-label">${setting.label}</label>
                    <input type="${inputType}" 
                           class="form-control setting-input" 
                           data-setting="integration_${service}_${key}"
                           value="${value}"
                           placeholder="${setting.placeholder || ''}"
                           ${setting.required ? 'required' : ''}>
                    ${setting.help ? `<div class="form-text">${setting.help}</div>` : ''}
                </div>
            `;
        }).join('');
    }

    async testConnection(service) {
        const btn = document.querySelector(`[data-service="${service}"]`);
        const originalText = btn.textContent;
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';

        try {
            const response = await window.app.api.request(`/settings/integrations/${service}/test`, {
                method: 'POST'
            });

            if (response.success) {
                window.app.showNotification(`${service} connection successful`, 'success');
            } else {
                throw new Error(response.message || 'Connection test failed');
            }
        } catch (error) {
            console.error('Connection test error:', error);
            window.app.showNotification(`${service} connection failed: ${error.message}`, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async loadPlugins() {
        try {
            const response = await window.app.api.request('/plugins', {
                method: 'GET'
            });

            if (response.success) {
                this.displayPlugins(response.data);
            } else {
                throw new Error(response.message || 'Failed to load plugins');
            }
        } catch (error) {
            console.error('Load plugins error:', error);
            window.app.showNotification('Failed to load plugins', 'error');
        }
    }

    displayPlugins(plugins) {
        const container = document.getElementById('pluginsContainer');
        if (!container) return;

        container.innerHTML = plugins.map(plugin => `
            <div class="plugin-card">
                <div class="plugin-header">
                    <div class="plugin-info">
                        <h5>${plugin.name}</h5>
                        <p class="text-muted">${plugin.description}</p>
                        <small class="text-muted">Version ${plugin.version} by ${plugin.author}</small>
                    </div>
                    <div class="plugin-status">
                        <span class="badge bg-${plugin.enabled ? 'success' : 'secondary'}">
                            ${plugin.enabled ? 'Enabled' : 'Disabled'}
                        </span>
                    </div>
                </div>
                <div class="plugin-actions">
                    <button class="btn btn-sm btn-outline-primary configure-plugin-btn" 
                            data-plugin-id="${plugin.id}">
                        Configure
                    </button>
                    <button class="btn btn-sm btn-${plugin.enabled ? 'warning' : 'success'} ${plugin.enabled ? 'disable' : 'enable'}-plugin-btn" 
                            data-plugin-id="${plugin.id}">
                        ${plugin.enabled ? 'Disable' : 'Enable'}
                    </button>
                </div>
            </div>
        `).join('');
    }

    async togglePlugin(pluginId, enable) {
        try {
            const response = await window.app.api.request(`/plugins/${pluginId}/${enable ? 'enable' : 'disable'}`, {
                method: 'POST'
            });

            if (response.success) {
                window.app.showNotification(`Plugin ${enable ? 'enabled' : 'disabled'} successfully`, 'success');
                this.loadPlugins();
            } else {
                throw new Error(response.message || 'Failed to toggle plugin');
            }
        } catch (error) {
            console.error('Toggle plugin error:', error);
            window.app.showNotification('Failed to toggle plugin: ' + error.message, 'error');
        }
    }

    configurePlugin(pluginId) {
        // Implementation for plugin configuration modal
        window.app.showNotification('Plugin configuration coming soon', 'info');
    }

    showImportModal() {
        const modal = document.getElementById('importModal') || this.createImportModal();
        new bootstrap.Modal(modal).show();
    }

    createImportModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'importModal';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Import Settings</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="settingsFile" class="form-label">Settings File</label>
                            <input type="file" class="form-control" id="settingsFile" accept=".json">
                            <div class="form-text">Select a JSON file exported from this system</div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="overwriteExisting" checked>
                            <label class="form-check-label" for="overwriteExisting">
                                Overwrite existing settings
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmImport">Import</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Bind import action
        modal.querySelector('#confirmImport').addEventListener('click', () => {
            this.importSettings();
        });

        return modal;
    }

    async importSettings() {
        const fileInput = document.getElementById('settingsFile');
        const file = fileInput.files[0];
        
        if (!file) {
            window.app.showNotification('Please select a file', 'warning');
            return;
        }

        try {
            const text = await file.text();
            const settings = JSON.parse(text);
            
            this.populateSettings(settings);
            bootstrap.Modal.getInstance(document.getElementById('importModal')).hide();
            window.app.showNotification('Settings imported successfully', 'success');
        } catch (error) {
            console.error('Import error:', error);
            window.app.showNotification('Failed to import settings: Invalid file format', 'error');
        }
    }

    exportSettings() {
        const settings = {};
        
        // Collect current settings
        document.querySelectorAll('.setting-input').forEach(input => {
            const key = input.dataset.setting;
            if (key) {
                settings[key] = input.type === 'checkbox' ? input.checked : input.value;
            }
        });

        // Create and download file
        const blob = new Blob([JSON.stringify(settings, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `fileserver-settings-${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        window.app.showNotification('Settings exported successfully', 'success');
    }

    async createBackup() {
        try {
            const response = await window.app.api.request('/admin/backup', {
                method: 'POST'
            });

            if (response.success) {
                window.app.showNotification('Backup created successfully', 'success');
                this.loadBackups();
            } else {
                throw new Error(response.message || 'Failed to create backup');
            }
        } catch (error) {
            console.error('Backup error:', error);
            window.app.showNotification('Failed to create backup: ' + error.message, 'error');
        }
    }

    async loadBackups() {
        try {
            const response = await window.app.api.request('/admin/backups', {
                method: 'GET'
            });

            if (response.success) {
                this.displayBackups(response.data);
            } else {
                throw new Error(response.message || 'Failed to load backups');
            }
        } catch (error) {
            console.error('Load backups error:', error);
            // Don't show error for optional feature
        }
    }

    displayBackups(backups) {
        const container = document.getElementById('backupsContainer');
        if (!container) return;

        if (backups.length === 0) {
            container.innerHTML = '<p class="text-muted">No backups available</p>';
            return;
        }

        container.innerHTML = backups.map(backup => `
            <div class="backup-item">
                <div class="backup-info">
                    <h6>${backup.name}</h6>
                    <p class="text-muted mb-1">Created: ${this.formatDate(backup.created_at)}</p>
                    <small class="text-muted">Size: ${this.formatFileSize(backup.size)}</small>
                </div>
                <div class="backup-actions">
                    <button class="btn btn-sm btn-outline-primary download-backup-btn" 
                            data-backup-id="${backup.id}">
                        Download
                    </button>
                    <button class="btn btn-sm btn-warning restore-backup-btn" 
                            data-backup-id="${backup.id}">
                        Restore
                    </button>
                    <button class="btn btn-sm btn-outline-danger delete-backup-btn" 
                            data-backup-id="${backup.id}">
                        Delete
                    </button>
                </div>
            </div>
        `).join('');
    }

    async clearCache() {
        if (!confirm('Are you sure you want to clear all caches?')) {
            return;
        }

        try {
            const response = await window.app.api.request('/admin/cache/clear', {
                method: 'POST'
            });

            if (response.success) {
                window.app.showNotification('Cache cleared successfully', 'success');
            } else {
                throw new Error(response.message || 'Failed to clear cache');
            }
        } catch (error) {
            console.error('Clear cache error:', error);
            window.app.showNotification('Failed to clear cache: ' + error.message, 'error');
        }
    }

    async regenerateSecurityKeys() {
        if (!confirm('Are you sure you want to regenerate security keys? This will log out all users.')) {
            return;
        }

        try {
            const response = await window.app.api.request('/admin/security/regenerate-keys', {
                method: 'POST'
            });

            if (response.success) {
                window.app.showNotification('Security keys regenerated successfully', 'success');
                // Redirect to login as current session will be invalid
                setTimeout(() => {
                    window.location.href = '/login';
                }, 2000);
            } else {
                throw new Error(response.message || 'Failed to regenerate keys');
            }
        } catch (error) {
            console.error('Regenerate keys error:', error);
            window.app.showNotification('Failed to regenerate keys: ' + error.message, 'error');
        }
    }

    previewTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        
        // Update theme setting
        const themeInput = document.querySelector('[data-setting="theme"]');
        if (themeInput) {
            themeInput.value = theme;
            this.markAsChanged();
        }
    }

    toggleAdvancedSection(target) {
        const section = document.getElementById(target);
        const toggle = document.querySelector(`[data-target="${target}"]`);
        
        if (section && toggle) {
            const isVisible = section.style.display !== 'none';
            section.style.display = isVisible ? 'none' : 'block';
            
            const icon = toggle.querySelector('i');
            if (icon) {
                icon.className = isVisible ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
            }
        }
    }

    // Utility methods
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString() + ' ' + new Date(dateString).toLocaleTimeString();
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

// Initialize settings page when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('settingsPage')) {
        window.settingsPage = new SettingsPage();
    }
});
