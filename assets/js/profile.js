/**
 * Profile Page JavaScript
 * Handles user profile management, avatar upload, 2FA, and preferences
 */

// Extend the main app with profile functionality
FileServerApp.prototype.initProfilePage = function() {
    this.setup2FAManagement();
    this.setupAvatarUpload();
    this.setupPasswordChange();
    this.setupProfileSettings();
    this.setupSecuritySettings();
    this.setupPreferences();
    this.setupActivityTimeline();
    this.setupNotificationSettings();
    this.loadProfileData();
};

/**
 * Setup 2FA management
 */
FileServerApp.prototype.setup2FAManagement = function() {
    const enable2FABtn = document.getElementById('enable2FA');
    const disable2FABtn = document.getElementById('disable2FA');
    const regenerateCodesBtn = document.getElementById('regenerateBackupCodes');

    if (enable2FABtn) {
        enable2FABtn.addEventListener('click', () => {
            this.enable2FA();
        });
    }

    if (disable2FABtn) {
        disable2FABtn.addEventListener('click', () => {
            this.disable2FA();
        });
    }

    if (regenerateCodesBtn) {
        regenerateCodesBtn.addEventListener('click', () => {
            this.regenerateBackupCodes();
        });
    }

    // Verify 2FA setup form
    const verify2FAForm = document.getElementById('verify2FAForm');
    if (verify2FAForm) {
        verify2FAForm.addEventListener('submit', (e) => {
            e.preventDefault();
            this.verify2FASetup(verify2FAForm);
        });
    }
};

/**
 * Enable 2FA
 */
FileServerApp.prototype.enable2FA = async function() {
    try {
        const response = await this.apiRequest('/auth/2fa/enable', {
            method: 'POST'
        });

        if (response.success) {
            this.show2FASetupModal(response.data);
        }
    } catch (error) {
        this.showNotification('Failed to enable 2FA: ' + error.message, 'error');
    }
};

/**
 * Show 2FA setup modal
 */
FileServerApp.prototype.show2FASetupModal = function(data) {
    const modal = document.getElementById('setup2FAModal');
    if (!modal) return;

    // Update QR code
    const qrCode = modal.querySelector('#qrCode');
    if (qrCode) {
        qrCode.src = data.qr_code;
    }

    // Update secret key
    const secretKey = modal.querySelector('#secretKey');
    if (secretKey) {
        secretKey.textContent = data.secret;
    }

    // Setup copy secret button
    const copySecretBtn = modal.querySelector('#copySecret');
    if (copySecretBtn) {
        copySecretBtn.addEventListener('click', () => {
            this.copyToClipboard(data.secret);
        });
    }

    this.openModal('setup2FAModal');
};

/**
 * Verify 2FA setup
 */
FileServerApp.prototype.verify2FASetup = async function(form) {
    const formData = new FormData(form);
    const code = formData.get('verification_code');

    if (!code || code.length !== 6) {
        this.showNotification('Please enter a valid 6-digit code', 'error');
        return;
    }

    try {
        const response = await this.apiRequest('/auth/2fa/verify-setup', {
            method: 'POST',
            body: JSON.stringify({ code: code })
        });

        if (response.success) {
            this.showNotification('2FA enabled successfully!', 'success');
            this.closeModal();
            this.update2FAStatus(true);
            this.showBackupCodes(response.data.backup_codes);
        }
    } catch (error) {
        this.showNotification('Invalid verification code', 'error');
    }
};

/**
 * Disable 2FA
 */
FileServerApp.prototype.disable2FA = async function() {
    if (!confirm('Are you sure you want to disable two-factor authentication? This will make your account less secure.')) {
        return;
    }

    const password = prompt('Please enter your password to confirm:');
    if (!password) return;

    try {
        const response = await this.apiRequest('/auth/2fa/disable', {
            method: 'POST',
            body: JSON.stringify({ password: password })
        });

        if (response.success) {
            this.showNotification('2FA disabled successfully', 'success');
            this.update2FAStatus(false);
        }
    } catch (error) {
        this.showNotification('Failed to disable 2FA: ' + error.message, 'error');
    }
};

/**
 * Update 2FA status in UI
 */
FileServerApp.prototype.update2FAStatus = function(enabled) {
    const enable2FABtn = document.getElementById('enable2FA');
    const disable2FABtn = document.getElementById('disable2FA');
    const status2FA = document.getElementById('status2FA');

    if (enabled) {
        if (enable2FABtn) enable2FABtn.style.display = 'none';
        if (disable2FABtn) disable2FABtn.style.display = 'inline-block';
        if (status2FA) {
            status2FA.innerHTML = '<i class="fas fa-check-circle text-success"></i> Enabled';
        }
    } else {
        if (enable2FABtn) enable2FABtn.style.display = 'inline-block';
        if (disable2FABtn) disable2FABtn.style.display = 'none';
        if (status2FA) {
            status2FA.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Disabled';
        }
    }
};

/**
 * Show backup codes
 */
FileServerApp.prototype.showBackupCodes = function(codes) {
    const modal = document.getElementById('backupCodesModal');
    if (!modal) return;

    const codesList = modal.querySelector('#backupCodesList');
    if (codesList) {
        codesList.innerHTML = codes.map(code => `
            <div class="backup-code">
                <code>${code}</code>
                <button type="button" class="btn btn-sm btn-outline-secondary" 
                        onclick="window.app.copyToClipboard('${code}')">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
        `).join('');
    }

    this.openModal('backupCodesModal');
};

/**
 * Regenerate backup codes
 */
FileServerApp.prototype.regenerateBackupCodes = async function() {
    if (!confirm('This will invalidate your existing backup codes. Continue?')) {
        return;
    }

    try {
        const response = await this.apiRequest('/auth/2fa/regenerate-codes', {
            method: 'POST'
        });

        if (response.success) {
            this.showNotification('Backup codes regenerated', 'success');
            this.showBackupCodes(response.data.backup_codes);
        }
    } catch (error) {
        this.showNotification('Failed to regenerate backup codes: ' + error.message, 'error');
    }
};

/**
 * Setup avatar upload
 */
FileServerApp.prototype.setupAvatarUpload = function() {
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatarPreview');
    const uploadAvatarBtn = document.getElementById('uploadAvatar');
    const removeAvatarBtn = document.getElementById('removeAvatar');

    if (avatarInput) {
        avatarInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                this.previewAvatar(file);
            }
        });
    }

    if (uploadAvatarBtn) {
        uploadAvatarBtn.addEventListener('click', () => {
            avatarInput?.click();
        });
    }

    if (removeAvatarBtn) {
        removeAvatarBtn.addEventListener('click', () => {
            this.removeAvatar();
        });
    }

    // Drag and drop for avatar
    if (avatarPreview) {
        avatarPreview.addEventListener('dragover', (e) => {
            e.preventDefault();
            avatarPreview.classList.add('drag-over');
        });

        avatarPreview.addEventListener('dragleave', () => {
            avatarPreview.classList.remove('drag-over');
        });

        avatarPreview.addEventListener('drop', (e) => {
            e.preventDefault();
            avatarPreview.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.previewAvatar(files[0]);
            }
        });
    }
};

/**
 * Preview avatar before upload
 */
FileServerApp.prototype.previewAvatar = function(file) {
    // Validate file
    if (!file.type.startsWith('image/')) {
        this.showNotification('Please select an image file', 'error');
        return;
    }

    if (file.size > 5 * 1024 * 1024) { // 5MB limit
        this.showNotification('Image must be smaller than 5MB', 'error');
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        const avatarPreview = document.getElementById('avatarPreview');
        if (avatarPreview) {
            avatarPreview.innerHTML = `<img src="${e.target.result}" alt="Avatar preview" class="avatar-image">`;
        }
        
        // Upload the avatar
        this.uploadAvatar(file);
    };
    reader.readAsDataURL(file);
};

/**
 * Upload avatar
 */
FileServerApp.prototype.uploadAvatar = async function(file) {
    const formData = new FormData();
    formData.append('avatar', file);

    try {
        const response = await this.apiRequest('/user/avatar', {
            method: 'POST',
            body: formData
        });

        if (response.success) {
            this.showNotification('Avatar updated successfully', 'success');
            
            // Update avatar in navigation
            const navAvatars = document.querySelectorAll('[data-user-avatar]');
            navAvatars.forEach(avatar => {
                avatar.src = response.data.avatar_url;
            });
        }
    } catch (error) {
        this.showNotification('Failed to upload avatar: ' + error.message, 'error');
    }
};

/**
 * Remove avatar
 */
FileServerApp.prototype.removeAvatar = async function() {
    if (!confirm('Are you sure you want to remove your avatar?')) {
        return;
    }

    try {
        const response = await this.apiRequest('/user/avatar', {
            method: 'DELETE'
        });

        if (response.success) {
            this.showNotification('Avatar removed successfully', 'success');
            
            // Reset avatar preview
            const avatarPreview = document.getElementById('avatarPreview');
            if (avatarPreview) {
                avatarPreview.innerHTML = '<i class="fas fa-user"></i>';
            }
            
            // Update avatar in navigation
            const navAvatars = document.querySelectorAll('[data-user-avatar]');
            navAvatars.forEach(avatar => {
                avatar.src = '/assets/images/default-avatar.png';
            });
        }
    } catch (error) {
        this.showNotification('Failed to remove avatar: ' + error.message, 'error');
    }
};

/**
 * Setup password change
 */
FileServerApp.prototype.setupPasswordChange = function() {
    const passwordForm = document.getElementById('changePasswordForm');
    if (!passwordForm) return;

    passwordForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        await this.changePassword(passwordForm);
    });

    // Password strength indicator for new password
    const newPasswordInput = passwordForm.querySelector('input[name="new_password"]');
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', (e) => {
            this.updatePasswordStrength(e.target.value);
        });
    }

    // Password confirmation validation
    const confirmPasswordInput = passwordForm.querySelector('input[name="confirm_password"]');
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', () => {
            this.validatePasswordConfirmation();
        });
    }
};

/**
 * Change password
 */
FileServerApp.prototype.changePassword = async function(form) {
    const formData = new FormData(form);
    const data = {
        current_password: formData.get('current_password'),
        new_password: formData.get('new_password'),
        confirm_password: formData.get('confirm_password')
    };

    // Validate passwords match
    if (data.new_password !== data.confirm_password) {
        this.showNotification('New passwords do not match', 'error');
        return;
    }

    try {
        const response = await this.apiRequest('/user/password', {
            method: 'PUT',
            body: JSON.stringify(data)
        });

        if (response.success) {
            this.showNotification('Password changed successfully', 'success');
            form.reset();
        }
    } catch (error) {
        this.showNotification('Failed to change password: ' + error.message, 'error');
    }
};

/**
 * Setup profile settings
 */
FileServerApp.prototype.setupProfileSettings = function() {
    const profileForm = document.getElementById('profileSettingsForm');
    if (!profileForm) return;

    profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        await this.updateProfile(profileForm);
    });

    // Auto-save profile changes
    const autoSaveFields = profileForm.querySelectorAll('[data-auto-save]');
    autoSaveFields.forEach(field => {
        field.addEventListener('change', () => {
            this.debounce(() => this.autoSaveProfile(), 1000)();
        });
    });
};

/**
 * Update profile
 */
FileServerApp.prototype.updateProfile = async function(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);

    try {
        const response = await this.apiRequest('/user/profile', {
            method: 'PUT',
            body: JSON.stringify(data)
        });

        if (response.success) {
            this.showNotification('Profile updated successfully', 'success');
            
            // Update user object
            this.user = { ...this.user, ...response.data };
            localStorage.setItem('user', JSON.stringify(this.user));
            this.updateUserInterface();
        }
    } catch (error) {
        this.showNotification('Failed to update profile: ' + error.message, 'error');
    }
};

/**
 * Auto-save profile
 */
FileServerApp.prototype.autoSaveProfile = async function() {
    const form = document.getElementById('profileSettingsForm');
    if (!form) return;

    const formData = new FormData(form);
    const data = Object.fromEntries(formData);

    try {
        await this.apiRequest('/user/profile', {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    } catch (error) {
        console.error('Auto-save failed:', error);
    }
};

/**
 * Setup security settings
 */
FileServerApp.prototype.setupSecuritySettings = function() {
    const loginAlertsToggle = document.getElementById('loginAlerts');
    const sessionTimeoutSelect = document.getElementById('sessionTimeout');
    const ipWhitelistInput = document.getElementById('ipWhitelist');

    if (loginAlertsToggle) {
        loginAlertsToggle.addEventListener('change', (e) => {
            this.updateSecuritySetting('login_alerts', e.target.checked);
        });
    }

    if (sessionTimeoutSelect) {
        sessionTimeoutSelect.addEventListener('change', (e) => {
            this.updateSecuritySetting('session_timeout', e.target.value);
        });
    }

    if (ipWhitelistInput) {
        ipWhitelistInput.addEventListener('blur', (e) => {
            this.updateSecuritySetting('ip_whitelist', e.target.value);
        });
    }
};

/**
 * Update security setting
 */
FileServerApp.prototype.updateSecuritySetting = async function(setting, value) {
    try {
        const response = await this.apiRequest('/user/security', {
            method: 'PUT',
            body: JSON.stringify({ [setting]: value })
        });

        if (response.success) {
            this.showNotification('Security setting updated', 'success', 1000);
        }
    } catch (error) {
        this.showNotification('Failed to update security setting: ' + error.message, 'error');
    }
};

/**
 * Setup preferences
 */
FileServerApp.prototype.setupPreferences = function() {
    const languageSelect = document.getElementById('language');
    const timezoneSelect = document.getElementById('timezone');
    const themeSelect = document.getElementById('theme');
    const defaultViewSelect = document.getElementById('defaultView');

    if (languageSelect) {
        languageSelect.addEventListener('change', (e) => {
            this.updatePreference('language', e.target.value);
        });
    }

    if (timezoneSelect) {
        timezoneSelect.addEventListener('change', (e) => {
            this.updatePreference('timezone', e.target.value);
        });
    }

    if (themeSelect) {
        themeSelect.addEventListener('change', (e) => {
            this.updatePreference('theme', e.target.value);
            this.currentTheme = e.target.value;
            this.initializeTheme();
        });
    }

    if (defaultViewSelect) {
        defaultViewSelect.addEventListener('change', (e) => {
            this.updatePreference('default_view', e.target.value);
        });
    }
};

/**
 * Update preference
 */
FileServerApp.prototype.updatePreference = async function(preference, value) {
    try {
        const response = await this.apiRequest('/user/preferences', {
            method: 'PUT',
            body: JSON.stringify({ [preference]: value })
        });

        if (response.success) {
            this.showNotification('Preference updated', 'success', 1000);
        }
    } catch (error) {
        this.showNotification('Failed to update preference: ' + error.message, 'error');
    }
};

/**
 * Setup activity timeline
 */
FileServerApp.prototype.setupActivityTimeline = function() {
    const loadMoreBtn = document.getElementById('loadMoreActivity');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => {
            this.loadMoreActivity();
        });
    }

    // Auto-refresh activity
    setInterval(() => {
        this.refreshActivity();
    }, 30000);
};

/**
 * Load more activity
 */
FileServerApp.prototype.loadMoreActivity = async function() {
    const timeline = document.getElementById('activityTimeline');
    const currentItems = timeline?.children.length || 0;

    try {
        const response = await this.apiRequest(`/user/activity?offset=${currentItems}&limit=10`);
        
        if (response.success && response.data.length > 0) {
            response.data.forEach(activity => {
                const activityElement = this.createActivityElement(activity);
                timeline?.appendChild(activityElement);
            });
        } else {
            const loadMoreBtn = document.getElementById('loadMoreActivity');
            if (loadMoreBtn) {
                loadMoreBtn.style.display = 'none';
            }
        }
    } catch (error) {
        this.showNotification('Failed to load activity: ' + error.message, 'error');
    }
};

/**
 * Refresh activity timeline
 */
FileServerApp.prototype.refreshActivity = async function() {
    try {
        const response = await this.apiRequest('/user/activity?limit=5');
        
        if (response.success) {
            const timeline = document.getElementById('activityTimeline');
            if (timeline && response.data.length > 0) {
                // Add new activities to the top
                response.data.reverse().forEach(activity => {
                    const activityElement = this.createActivityElement(activity);
                    timeline.insertBefore(activityElement, timeline.firstChild);
                });
            }
        }
    } catch (error) {
        console.error('Failed to refresh activity:', error);
    }
};

/**
 * Setup notification settings
 */
FileServerApp.prototype.setupNotificationSettings = function() {
    const notificationToggles = document.querySelectorAll('[data-notification-setting]');
    
    notificationToggles.forEach(toggle => {
        toggle.addEventListener('change', (e) => {
            const setting = e.target.dataset.notificationSetting;
            this.updateNotificationSetting(setting, e.target.checked);
        });
    });
};

/**
 * Update notification setting
 */
FileServerApp.prototype.updateNotificationSetting = async function(setting, enabled) {
    try {
        const response = await this.apiRequest('/user/notifications', {
            method: 'PUT',
            body: JSON.stringify({ [setting]: enabled })
        });

        if (response.success) {
            this.showNotification('Notification setting updated', 'success', 1000);
        }
    } catch (error) {
        this.showNotification('Failed to update notification setting: ' + error.message, 'error');
    }
};

/**
 * Load profile data
 */
FileServerApp.prototype.loadProfileData = async function() {
    try {
        const response = await this.apiRequest('/user/profile/complete');
        
        if (response.success) {
            this.populateProfileData(response.data);
        }
    } catch (error) {
        console.error('Failed to load profile data:', error);
    }
};

/**
 * Populate profile data in forms
 */
FileServerApp.prototype.populateProfileData = function(data) {
    // Populate profile form
    const profileForm = document.getElementById('profileSettingsForm');
    if (profileForm && data.profile) {
        Object.entries(data.profile).forEach(([key, value]) => {
            const input = profileForm.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = value || '';
            }
        });
    }

    // Update 2FA status
    if (data.security) {
        this.update2FAStatus(data.security.two_factor_enabled);
    }

    // Populate preferences
    if (data.preferences) {
        Object.entries(data.preferences).forEach(([key, value]) => {
            const input = document.getElementById(key);
            if (input) {
                input.value = value || '';
            }
        });
    }

    // Populate notification settings
    if (data.notifications) {
        Object.entries(data.notifications).forEach(([key, value]) => {
            const toggle = document.querySelector(`[data-notification-setting="${key}"]`);
            if (toggle) {
                toggle.checked = value;
            }
        });
    }
};
