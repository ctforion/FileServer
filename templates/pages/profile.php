<?php
/**
 * User Profile Page Template
 * Displays and allows editing of user profile information
 */

defined('FILESERVER_ACCESS') or die('Direct access denied');

$user = $data['user'] ?? [];
$stats = $data['stats'] ?? [];
$settings = $data['settings'] ?? [];
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <div class="profile-avatar mb-3">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="/uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" 
                                 class="rounded-circle" width="120" height="120" alt="Avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder rounded-circle mx-auto bg-primary text-white d-flex align-items-center justify-content-center" 
                                 style="width: 120px; height: 120px; font-size: 2rem;">
                                <?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="mt-3">
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#avatarModal">
                                <i class="fas fa-camera"></i> Change Avatar
                            </button>
                        </div>
                    </div>
                    <h4 class="mb-1"><?= htmlspecialchars($user['display_name'] ?? $user['username'] ?? 'User') ?></h4>
                    <p class="text-muted mb-1">@<?= htmlspecialchars($user['username'] ?? '') ?></p>
                    <p class="text-muted small"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                    <div class="row text-center mt-3">
                        <div class="col-4">
                            <div class="h5 mb-0"><?= number_format($stats['total_files'] ?? 0) ?></div>
                            <small class="text-muted">Files</small>
                        </div>
                        <div class="col-4">
                            <div class="h5 mb-0"><?= format_bytes($stats['total_size'] ?? 0) ?></div>
                            <small class="text-muted">Storage</small>
                        </div>
                        <div class="col-4">
                            <div class="h5 mb-0"><?= number_format($stats['downloads'] ?? 0) ?></div>
                            <small class="text-muted">Downloads</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Security -->
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-shield-alt"></i> Security</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <strong>Two-Factor Authentication</strong>
                            <br><small class="text-muted">Add an extra layer of security</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="toggle2FA" 
                                   <?= ($user['two_factor_enabled'] ?? false) ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <button class="btn btn-outline-warning btn-sm w-100 mb-2" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                    <button class="btn btn-outline-info btn-sm w-100" id="downloadBackupCodes">
                        <i class="fas fa-download"></i> Backup Codes
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- Profile Information -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-user"></i> Profile Information</h6>
                </div>
                <div class="card-body">
                    <form id="profileForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?= htmlspecialchars($user['username'] ?? '') ?>" readonly>
                                    <small class="text-muted">Username cannot be changed</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="display_name" class="form-label">Display Name</label>
                                    <input type="text" class="form-control" id="display_name" name="display_name" 
                                           value="<?= htmlspecialchars($user['display_name'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="timezone" class="form-label">Timezone</label>
                                    <select class="form-select" id="timezone" name="timezone">
                                        <option value="UTC" <?= ($user['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                        <option value="America/New_York" <?= ($user['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>Eastern Time</option>
                                        <option value="America/Chicago" <?= ($user['timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>Central Time</option>
                                        <option value="America/Denver" <?= ($user['timezone'] ?? '') === 'America/Denver' ? 'selected' : '' ?>>Mountain Time</option>
                                        <option value="America/Los_Angeles" <?= ($user['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time</option>
                                        <option value="Europe/London" <?= ($user['timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>London</option>
                                        <option value="Europe/Paris" <?= ($user['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : '' ?>>Paris</option>
                                        <option value="Asia/Tokyo" <?= ($user['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3" 
                                      maxlength="500"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                            <small class="text-muted">Tell us a little about yourself (max 500 characters)</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>

            <!-- Preferences -->
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-cog"></i> Preferences</h6>
                </div>
                <div class="card-body">
                    <form id="preferencesForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="language" class="form-label">Language</label>
                                    <select class="form-select" id="language" name="language">
                                        <option value="en" <?= ($settings['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                                        <option value="es" <?= ($settings['language'] ?? '') === 'es' ? 'selected' : '' ?>>Español</option>
                                        <option value="fr" <?= ($settings['language'] ?? '') === 'fr' ? 'selected' : '' ?>>Français</option>
                                        <option value="de" <?= ($settings['language'] ?? '') === 'de' ? 'selected' : '' ?>>Deutsch</option>
                                        <option value="it" <?= ($settings['language'] ?? '') === 'it' ? 'selected' : '' ?>>Italiano</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="theme" class="form-label">Theme</label>
                                    <select class="form-select" id="theme" name="theme">
                                        <option value="light" <?= ($settings['theme'] ?? 'light') === 'light' ? 'selected' : '' ?>>Light</option>
                                        <option value="dark" <?= ($settings['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option>
                                        <option value="auto" <?= ($settings['theme'] ?? '') === 'auto' ? 'selected' : '' ?>>Auto</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="emailNotifications" name="email_notifications" 
                                           <?= ($settings['email_notifications'] ?? true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="emailNotifications">
                                        Email Notifications
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="publicProfile" name="public_profile" 
                                           <?= ($settings['public_profile'] ?? false) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="publicProfile">
                                        Public Profile
                                    </label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </form>
                </div>
            </div>

            <!-- Activity History -->
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-history"></i> Recent Activity</h6>
                </div>
                <div class="card-body">
                    <div id="activityTimeline">
                        <!-- Activity items will be loaded via JavaScript -->
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Avatar Upload Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Avatar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="avatarForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="avatarFile" class="form-label">Choose Image</label>
                        <input type="file" class="form-control" id="avatarFile" name="avatar" 
                               accept="image/*" required>
                        <small class="text-muted">Max file size: 2MB. Supported formats: JPG, PNG, GIF</small>
                    </div>
                    <div id="avatarPreview" class="text-center mb-3" style="display: none;">
                        <img id="previewImage" src="" class="rounded-circle" width="120" height="120" alt="Preview">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="uploadAvatar">Upload</button>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="passwordForm">
                    <div class="mb-3">
                        <label for="currentPassword" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="newPassword" name="new_password" required>
                        <div class="password-strength mt-2">
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar" id="strengthBar" style="width: 0%;"></div>
                            </div>
                            <small id="strengthText" class="text-muted">Password strength</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="changePassword">Change Password</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Profile form submission
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        showSpinner('Saving profile...');
        
        fetch('/api/users/profile', {
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
                showAlert('Profile updated successfully', 'success');
            } else {
                showAlert('Error updating profile: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            hideSpinner();
            showAlert('Network error: ' + error.message, 'danger');
        });
    });

    // Preferences form submission
    document.getElementById('preferencesForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        showSpinner('Saving preferences...');
        
        fetch('/api/users/preferences', {
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
                showAlert('Preferences updated successfully', 'success');
                
                // Apply theme change immediately
                const theme = formData.get('theme');
                if (theme === 'dark') {
                    document.body.classList.add('dark-theme');
                } else if (theme === 'light') {
                    document.body.classList.remove('dark-theme');
                }
            } else {
                showAlert('Error updating preferences: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            hideSpinner();
            showAlert('Network error: ' + error.message, 'danger');
        });
    });

    // Avatar file preview
    document.getElementById('avatarFile').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewImage').src = e.target.result;
                document.getElementById('avatarPreview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    // Avatar upload
    document.getElementById('uploadAvatar').addEventListener('click', function() {
        const form = document.getElementById('avatarForm');
        const formData = new FormData(form);
        
        showSpinner('Uploading avatar...');
        
        fetch('/api/users/avatar', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('token')
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideSpinner();
            if (data.success) {
                showAlert('Avatar updated successfully', 'success');
                location.reload(); // Reload to show new avatar
            } else {
                showAlert('Error uploading avatar: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            hideSpinner();
            showAlert('Network error: ' + error.message, 'danger');
        });
    });

    // 2FA toggle
    document.getElementById('toggle2FA').addEventListener('change', function(e) {
        const enabled = e.target.checked;
        
        if (enabled) {
            // Enable 2FA
            fetch('/api/auth/2fa/enable', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('token'),
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('2FA enabled successfully. Please save your backup codes.', 'success');
                } else {
                    e.target.checked = false;
                    showAlert('Error enabling 2FA: ' + (data.message || 'Unknown error'), 'danger');
                }
            });
        } else {
            // Disable 2FA
            if (confirm('Are you sure you want to disable Two-Factor Authentication?')) {
                fetch('/api/auth/2fa/disable', {
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('token')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('2FA disabled successfully', 'success');
                    } else {
                        e.target.checked = true;
                        showAlert('Error disabling 2FA: ' + (data.message || 'Unknown error'), 'danger');
                    }
                });
            } else {
                e.target.checked = true;
            }
        }
    });

    // Load activity timeline
    loadActivityTimeline();
});

function loadActivityTimeline() {
    fetch('/api/users/activity', {
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        }
    })
    .then(response => response.json())
    .then(data => {
        const timeline = document.getElementById('activityTimeline');
        
        if (data.success && data.activities && data.activities.length > 0) {
            timeline.innerHTML = data.activities.map(activity => `
                <div class="activity-item d-flex mb-3">
                    <div class="activity-icon me-3">
                        <i class="fas ${getActivityIcon(activity.type)} text-primary"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">${activity.description}</div>
                        <small class="text-muted">${formatDate(activity.created_at)}</small>
                    </div>
                </div>
            `).join('');
        } else {
            timeline.innerHTML = '<p class="text-muted text-center">No recent activity</p>';
        }
    })
    .catch(error => {
        document.getElementById('activityTimeline').innerHTML = 
            '<p class="text-danger text-center">Error loading activity</p>';
    });
}

function getActivityIcon(type) {
    const icons = {
        'file_upload': 'fa-upload',
        'file_download': 'fa-download',
        'file_delete': 'fa-trash',
        'login': 'fa-sign-in-alt',
        'logout': 'fa-sign-out-alt',
        'profile_update': 'fa-user-edit',
        'default': 'fa-circle'
    };
    return icons[type] || icons.default;
}
</script>

<style>
.profile-avatar {
    position: relative;
}

.avatar-placeholder {
    font-weight: bold;
}

.activity-item {
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 30px;
    text-align: center;
}

.password-strength {
    margin-top: 0.5rem;
}

.progress-bar {
    transition: width 0.3s ease, background-color 0.3s ease;
}

.strength-weak .progress-bar {
    background-color: #dc3545;
}

.strength-medium .progress-bar {
    background-color: #ffc107;
}

.strength-strong .progress-bar {
    background-color: #28a745;
}
</style>
