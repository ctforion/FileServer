<?php
/**
 * Settings Page Template
 * System and user configuration settings
 */

defined('FILESERVER_ACCESS') or die('Direct access denied');

$settings = $data['settings'] ?? [];
$user = $data['user'] ?? [];
$isAdmin = ($user['role'] ?? '') === 'admin';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Settings Navigation -->
        <div class="col-lg-3">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-cog"></i> Settings</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="#general" class="list-group-item list-group-item-action active" data-bs-toggle="tab">
                        <i class="fas fa-sliders-h"></i> General
                    </a>
                    <a href="#appearance" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-palette"></i> Appearance
                    </a>
                    <a href="#security" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-shield-alt"></i> Security
                    </a>
                    <a href="#storage" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-hdd"></i> Storage
                    </a>
                    <a href="#notifications" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-bell"></i> Notifications
                    </a>
                    <?php if ($isAdmin): ?>
                    <a href="#system" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-server"></i> System
                    </a>
                    <a href="#email" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-envelope"></i> Email
                    </a>
                    <a href="#api" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-code"></i> API & Webhooks
                    </a>
                    <a href="#advanced" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-cogs"></i> Advanced
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="col-lg-9">
            <div class="tab-content">
                <!-- General Settings -->
                <div class="tab-pane fade show active" id="general">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">General Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="generalSettingsForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="siteName" class="form-label">Site Name</label>
                                            <input type="text" class="form-control" id="siteName" name="site_name" 
                                                   value="<?= htmlspecialchars($settings['site_name'] ?? 'FileServer') ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="siteDescription" class="form-label">Site Description</label>
                                            <input type="text" class="form-control" id="siteDescription" name="site_description" 
                                                   value="<?= htmlspecialchars($settings['site_description'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="defaultLanguage" class="form-label">Default Language</label>
                                            <select class="form-select" id="defaultLanguage" name="default_language">
                                                <option value="en" <?= ($settings['default_language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                                                <option value="es" <?= ($settings['default_language'] ?? '') === 'es' ? 'selected' : '' ?>>Español</option>
                                                <option value="fr" <?= ($settings['default_language'] ?? '') === 'fr' ? 'selected' : '' ?>>Français</option>
                                                <option value="de" <?= ($settings['default_language'] ?? '') === 'de' ? 'selected' : '' ?>>Deutsch</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="timezone" class="form-label">Default Timezone</label>
                                            <select class="form-select" id="timezone" name="timezone">
                                                <option value="UTC" <?= ($settings['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                                <option value="America/New_York" <?= ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>Eastern Time</option>
                                                <option value="America/Chicago" <?= ($settings['timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>Central Time</option>
                                                <option value="America/Denver" <?= ($settings['timezone'] ?? '') === 'America/Denver' ? 'selected' : '' ?>>Mountain Time</option>
                                                <option value="America/Los_Angeles" <?= ($settings['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="publicRegistration" name="public_registration" 
                                                   <?= ($settings['public_registration'] ?? true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="publicRegistration">
                                                Allow Public Registration
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="emailVerification" name="email_verification" 
                                                   <?= ($settings['email_verification'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="emailVerification">
                                                Require Email Verification
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Appearance Settings -->
                <div class="tab-pane fade" id="appearance">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">Appearance Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="appearanceSettingsForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="defaultTheme" class="form-label">Default Theme</label>
                                            <select class="form-select" id="defaultTheme" name="default_theme">
                                                <option value="light" <?= ($settings['default_theme'] ?? 'light') === 'light' ? 'selected' : '' ?>>Light</option>
                                                <option value="dark" <?= ($settings['default_theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option>
                                                <option value="auto" <?= ($settings['default_theme'] ?? '') === 'auto' ? 'selected' : '' ?>>Auto</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="accentColor" class="form-label">Accent Color</label>
                                            <div class="input-group">
                                                <input type="color" class="form-control form-control-color" id="accentColor" name="accent_color" 
                                                       value="<?= htmlspecialchars($settings['accent_color'] ?? '#007bff') ?>">
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($settings['accent_color'] ?? '#007bff') ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="siteLogo" class="form-label">Site Logo</label>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($settings['site_logo'])): ?>
                                            <img src="<?= htmlspecialchars($settings['site_logo']) ?>" alt="Site Logo" class="me-3" style="max-height: 50px;">
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="siteLogo" name="site_logo" accept="image/*">
                                    </div>
                                    <small class="text-muted">Upload a logo for your site (PNG, JPG, SVG supported)</small>
                                </div>

                                <div class="mb-3">
                                    <label for="customCSS" class="form-label">Custom CSS</label>
                                    <textarea class="form-control font-monospace" id="customCSS" name="custom_css" rows="6" 
                                              placeholder="/* Add your custom CSS here */"><?= htmlspecialchars($settings['custom_css'] ?? '') ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="showBranding" name="show_branding" 
                                                   <?= ($settings['show_branding'] ?? true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="showBranding">
                                                Show FileServer Branding
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="compactMode" name="compact_mode" 
                                                   <?= ($settings['compact_mode'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="compactMode">
                                                Compact Interface Mode
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="tab-pane fade" id="security">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="securitySettingsForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="sessionTimeout" class="form-label">Session Timeout (minutes)</label>
                                            <input type="number" class="form-control" id="sessionTimeout" name="session_timeout" 
                                                   value="<?= $settings['session_timeout'] ?? 1440 ?>" min="5" max="43200">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="maxLoginAttempts" class="form-label">Max Login Attempts</label>
                                            <input type="number" class="form-control" id="maxLoginAttempts" name="max_login_attempts" 
                                                   value="<?= $settings['max_login_attempts'] ?? 5 ?>" min="1" max="50">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="force2FA" name="force_2fa" 
                                                   <?= ($settings['force_2fa'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="force2FA">
                                                Force Two-Factor Authentication
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="secureHeaders" name="secure_headers" 
                                                   <?= ($settings['secure_headers'] ?? true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="secureHeaders">
                                                Enable Security Headers
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="allowedDomains" class="form-label">Allowed Domains (CORS)</label>
                                    <textarea class="form-control" id="allowedDomains" name="allowed_domains" rows="3" 
                                              placeholder="https://example.com&#10;https://subdomain.example.com"><?= htmlspecialchars($settings['allowed_domains'] ?? '') ?></textarea>
                                    <small class="text-muted">One domain per line. Leave empty to allow all domains.</small>
                                </div>

                                <div class="mb-3">
                                    <label for="blockedIPs" class="form-label">Blocked IP Addresses</label>
                                    <textarea class="form-control" id="blockedIPs" name="blocked_ips" rows="3" 
                                              placeholder="192.168.1.100&#10;10.0.0.0/8"><?= htmlspecialchars($settings['blocked_ips'] ?? '') ?></textarea>
                                    <small class="text-muted">IP addresses or CIDR ranges, one per line</small>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Storage Settings -->
                <div class="tab-pane fade" id="storage">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">Storage Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="storageSettingsForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="maxFileSize" class="form-label">Max File Size (MB)</label>
                                            <input type="number" class="form-control" id="maxFileSize" name="max_file_size" 
                                                   value="<?= ($settings['max_file_size'] ?? 100) ?>" min="1" max="5120">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="maxTotalSize" class="form-label">Max Total Size per User (GB)</label>
                                            <input type="number" class="form-control" id="maxTotalSize" name="max_total_size" 
                                                   value="<?= ($settings['max_total_size'] ?? 5) ?>" min="0.1" step="0.1">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="allowedExtensions" class="form-label">Allowed File Extensions</label>
                                    <textarea class="form-control" id="allowedExtensions" name="allowed_extensions" rows="3" 
                                              placeholder="jpg,jpeg,png,gif,pdf,doc,docx,txt"><?= htmlspecialchars($settings['allowed_extensions'] ?? '') ?></textarea>
                                    <small class="text-muted">Comma-separated list of allowed file extensions</small>
                                </div>

                                <div class="mb-3">
                                    <label for="bannedExtensions" class="form-label">Banned File Extensions</label>
                                    <textarea class="form-control" id="bannedExtensions" name="banned_extensions" rows="2" 
                                              placeholder="exe,bat,sh,php"><?= htmlspecialchars($settings['banned_extensions'] ?? '') ?></textarea>
                                    <small class="text-muted">Comma-separated list of banned file extensions</small>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="enableCompression" name="enable_compression" 
                                                   <?= ($settings['enable_compression'] ?? true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="enableCompression">
                                                Enable File Compression
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="enableVersioning" name="enable_versioning" 
                                                   <?= ($settings['enable_versioning'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="enableVersioning">
                                                Enable File Versioning
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="enableThumbnails" name="enable_thumbnails" 
                                                   <?= ($settings['enable_thumbnails'] ?? true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="enableThumbnails">
                                                Generate Thumbnails
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="enableVirusScanning" name="enable_virus_scanning" 
                                                   <?= ($settings['enable_virus_scanning'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="enableVirusScanning">
                                                Enable Virus Scanning
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Notifications Settings -->
                <div class="tab-pane fade" id="notifications">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">Notification Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="notificationSettingsForm">
                                <h6 class="fw-bold mb-3">Email Notifications</h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="notifyNewUser" name="notify_new_user" 
                                                   <?= ($settings['notify_new_user'] ?? true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="notifyNewUser">
                                                New User Registration
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="notifyFileUpload" name="notify_file_upload" 
                                                   <?= ($settings['notify_file_upload'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="notifyFileUpload">
                                                File Upload Activity
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="notifySharedFile" name="notify_shared_file" 
                                                   <?= ($settings['notify_shared_file'] ?? true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="notifySharedFile">
                                                File Shared with User
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="notifyStorageLimit" name="notify_storage_limit" 
                                                   <?= ($settings['notify_storage_limit'] ?? true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="notifyStorageLimit">
                                                Storage Limit Warnings
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="fw-bold mb-3 mt-4">Push Notifications</h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="enablePushNotifications" name="enable_push_notifications" 
                                                   <?= ($settings['enable_push_notifications'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="enablePushNotifications">
                                                Enable Push Notifications
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="pushFileShared" name="push_file_shared" 
                                                   <?= ($settings['push_file_shared'] ?? true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="pushFileShared">
                                                File Sharing Notifications
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($isAdmin): ?>
                <!-- System Settings (Admin Only) -->
                <div class="tab-pane fade" id="system">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">System Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="systemSettingsForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="maintenanceMode" name="maintenance_mode" 
                                                   <?= ($settings['maintenance_mode'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="maintenanceMode">
                                                Maintenance Mode
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="debugMode" name="debug_mode" 
                                                   <?= ($settings['debug_mode'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="debugMode">
                                                Debug Mode
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="logLevel" class="form-label">Log Level</label>
                                            <select class="form-select" id="logLevel" name="log_level">
                                                <option value="error" <?= ($settings['log_level'] ?? 'info') === 'error' ? 'selected' : '' ?>>Error</option>
                                                <option value="warning" <?= ($settings['log_level'] ?? 'info') === 'warning' ? 'selected' : '' ?>>Warning</option>
                                                <option value="info" <?= ($settings['log_level'] ?? 'info') === 'info' ? 'selected' : '' ?>>Info</option>
                                                <option value="debug" <?= ($settings['log_level'] ?? 'info') === 'debug' ? 'selected' : '' ?>>Debug</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="backupRetention" class="form-label">Backup Retention (days)</label>
                                            <input type="number" class="form-control" id="backupRetention" name="backup_retention" 
                                                   value="<?= $settings['backup_retention'] ?? 30 ?>" min="1" max="365">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="autoBackup" name="auto_backup" 
                                                   <?= ($settings['auto_backup'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="autoBackup">
                                                Automatic Backups
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="autoUpdate" name="auto_update" 
                                                   <?= ($settings['auto_update'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="autoUpdate">
                                                Automatic Updates
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Email Settings (Admin Only) -->
                <div class="tab-pane fade" id="email">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">Email Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="emailSettingsForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="mailDriver" class="form-label">Mail Driver</label>
                                            <select class="form-select" id="mailDriver" name="mail_driver">
                                                <option value="smtp" <?= ($settings['mail_driver'] ?? 'smtp') === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                                                <option value="sendmail" <?= ($settings['mail_driver'] ?? '') === 'sendmail' ? 'selected' : '' ?>>Sendmail</option>
                                                <option value="mail" <?= ($settings['mail_driver'] ?? '') === 'mail' ? 'selected' : '' ?>>PHP Mail</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="mailFromAddress" class="form-label">From Address</label>
                                            <input type="email" class="form-control" id="mailFromAddress" name="mail_from_address" 
                                                   value="<?= htmlspecialchars($settings['mail_from_address'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="smtp-settings" style="display: <?= ($settings['mail_driver'] ?? 'smtp') === 'smtp' ? 'block' : 'none' ?>">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="smtpHost" class="form-label">SMTP Host</label>
                                                <input type="text" class="form-control" id="smtpHost" name="smtp_host" 
                                                       value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="smtpPort" class="form-label">SMTP Port</label>
                                                <input type="number" class="form-control" id="smtpPort" name="smtp_port" 
                                                       value="<?= $settings['smtp_port'] ?? 587 ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="smtpUsername" class="form-label">SMTP Username</label>
                                                <input type="text" class="form-control" id="smtpUsername" name="smtp_username" 
                                                       value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="smtpPassword" class="form-label">SMTP Password</label>
                                                <input type="password" class="form-control" id="smtpPassword" name="smtp_password" 
                                                       value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="smtpEncryption" class="form-label">Encryption</label>
                                                <select class="form-select" id="smtpEncryption" name="smtp_encryption">
                                                    <option value="tls" <?= ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                                    <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                                    <option value="none" <?= ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="testEmailSettings()">
                                        Test Email
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- API & Webhooks Settings (Admin Only) -->
                <div class="tab-pane fade" id="api">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">API & Webhooks Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="apiSettingsForm">
                                <h6 class="fw-bold mb-3">API Configuration</h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="enableAPI" name="enable_api" 
                                                   <?= ($settings['enable_api'] ?? true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="enableAPI">
                                                Enable API Access
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="rateLimitRequests" class="form-label">Rate Limit (requests/hour)</label>
                                            <input type="number" class="form-control" id="rateLimitRequests" name="rate_limit_requests" 
                                                   value="<?= $settings['rate_limit_requests'] ?? 1000 ?>" min="1">
                                        </div>
                                    </div>
                                </div>

                                <h6 class="fw-bold mb-3 mt-4">Webhooks</h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="enableWebhooks" name="enable_webhooks" 
                                                   <?= ($settings['enable_webhooks'] ?? false) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="enableWebhooks">
                                                Enable Webhooks
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="webhookTimeout" class="form-label">Webhook Timeout (seconds)</label>
                                            <input type="number" class="form-control" id="webhookTimeout" name="webhook_timeout" 
                                                   value="<?= $settings['webhook_timeout'] ?? 30 ?>" min="5" max="300">
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Advanced Settings (Admin Only) -->
                <div class="tab-pane fade" id="advanced">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">Advanced Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning" role="alert">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Warning:</strong> These settings are for advanced users only. Incorrect values may break your installation.
                            </div>

                            <form id="advancedSettingsForm">
                                <div class="mb-3">
                                    <label for="customConfig" class="form-label">Custom PHP Configuration</label>
                                    <textarea class="form-control font-monospace" id="customConfig" name="custom_config" rows="8" 
                                              placeholder="// Add custom PHP configuration here"><?= htmlspecialchars($settings['custom_config'] ?? '') ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="cacheDriver" class="form-label">Cache Driver</label>
                                            <select class="form-select" id="cacheDriver" name="cache_driver">
                                                <option value="file" <?= ($settings['cache_driver'] ?? 'file') === 'file' ? 'selected' : '' ?>>File</option>
                                                <option value="redis" <?= ($settings['cache_driver'] ?? '') === 'redis' ? 'selected' : '' ?>>Redis</option>
                                                <option value="memcached" <?= ($settings['cache_driver'] ?? '') === 'memcached' ? 'selected' : '' ?>>Memcached</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="sessionDriver" class="form-label">Session Driver</label>
                                            <select class="form-select" id="sessionDriver" name="session_driver">
                                                <option value="file" <?= ($settings['session_driver'] ?? 'file') === 'file' ? 'selected' : '' ?>>File</option>
                                                <option value="database" <?= ($settings['session_driver'] ?? '') === 'database' ? 'selected' : '' ?>>Database</option>
                                                <option value="redis" <?= ($settings['session_driver'] ?? '') === 'redis' ? 'selected' : '' ?>>Redis</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                    <button type="button" class="btn btn-outline-danger" onclick="resetToDefaults()">
                                        Reset to Defaults
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle form submissions
    document.querySelectorAll('form[id$="SettingsForm"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            saveSettings(this);
        });
    });

    // Handle mail driver change
    document.getElementById('mailDriver')?.addEventListener('change', function() {
        const smtpSettings = document.querySelector('.smtp-settings');
        if (smtpSettings) {
            smtpSettings.style.display = this.value === 'smtp' ? 'block' : 'none';
        }
    });

    // Handle accent color change
    document.getElementById('accentColor')?.addEventListener('input', function() {
        const textInput = this.parentNode.querySelector('input[type="text"]');
        if (textInput) {
            textInput.value = this.value;
        }
        // Apply color preview
        document.documentElement.style.setProperty('--bs-primary', this.value);
    });

    // Handle theme change preview
    document.getElementById('defaultTheme')?.addEventListener('change', function() {
        if (this.value === 'dark') {
            document.body.classList.add('dark-theme');
        } else if (this.value === 'light') {
            document.body.classList.remove('dark-theme');
        }
    });
});

function saveSettings(form) {
    const formData = new FormData(form);
    const settingsType = form.id.replace('SettingsForm', '');
    
    showSpinner('Saving settings...');
    
    fetch('/api/admin/settings/' + settingsType, {
        method: 'PUT',
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideSpinner();
        if (data.success) {
            showAlert('Settings saved successfully', 'success');
        } else {
            showAlert('Error saving settings: ' + (data.message || 'Unknown error'), 'danger');
        }
    })
    .catch(error => {
        hideSpinner();
        showAlert('Network error: ' + error.message, 'danger');
    });
}

function testEmailSettings() {
    showSpinner('Testing email configuration...');
    
    fetch('/api/admin/email/test', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token'),
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        hideSpinner();
        if (data.success) {
            showAlert('Test email sent successfully', 'success');
        } else {
            showAlert('Email test failed: ' + (data.message || 'Unknown error'), 'danger');
        }
    })
    .catch(error => {
        hideSpinner();
        showAlert('Network error: ' + error.message, 'danger');
    });
}

function resetToDefaults() {
    if (confirm('Are you sure you want to reset all settings to their default values? This action cannot be undone.')) {
        showSpinner('Resetting settings...');
        
        fetch('/api/admin/settings/reset', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('token')
            }
        })
        .then(response => response.json())
        .then(data => {
            hideSpinner();
            if (data.success) {
                showAlert('Settings reset to defaults', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('Reset failed: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            hideSpinner();
            showAlert('Network error: ' + error.message, 'danger');
        });
    }
}
</script>

<style>
.nav-tabs .nav-link {
    color: #6c757d;
    border: none;
    border-bottom: 2px solid transparent;
}

.nav-tabs .nav-link.active {
    color: #007bff;
    border-bottom-color: #007bff;
    background-color: transparent;
}

.list-group-item-action {
    border: none;
    border-radius: 0;
}

.list-group-item-action.active {
    background-color: #007bff;
    border-color: #007bff;
    color: white;
}

.form-control:focus,
.form-select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.form-check-input:checked {
    background-color: #007bff;
    border-color: #007bff;
}

.font-monospace {
    font-family: 'Courier New', Courier, monospace;
}

.alert {
    border: none;
    border-radius: 0.5rem;
}

.card {
    border: none;
    border-radius: 0.75rem;
}

.form-control-color {
    width: 50px;
    padding: 0.375rem 0.25rem;
}

@media (max-width: 768px) {
    .col-lg-3 {
        margin-bottom: 1rem;
    }
    
    .list-group {
        flex-direction: row;
        overflow-x: auto;
    }
    
    .list-group-item {
        flex: 0 0 auto;
        white-space: nowrap;
    }
}
</style>
