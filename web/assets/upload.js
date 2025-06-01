/**
 * Upload JavaScript - Drag & Drop, Progress Tracking, File Validation
 * Part of the comprehensive FileServer system
 */

class FileUploader {
    constructor() {
        this.maxFileSize = parseInt(document.getElementById('maxFileSize')?.value) || 10485760; // 10MB default
        this.allowedTypes = (document.getElementById('allowedTypes')?.value || '').split(',').map(t => t.trim()).filter(t => t);
        this.currentUploads = new Map();
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        this.initializeElements();
        this.setupEventListeners();
        this.loadUserQuota();
    }

    initializeElements() {
        this.dropZone = document.getElementById('dropZone');
        this.fileInput = document.getElementById('fileInput');
        this.uploadButton = document.getElementById('uploadButton');
        this.progressContainer = document.getElementById('progressContainer');
        this.uploadHistory = document.getElementById('uploadHistory');
        this.quotaBar = document.getElementById('quotaBar');
        this.quotaText = document.getElementById('quotaText');
        this.fileList = document.getElementById('fileList');
        this.uploadOptions = document.getElementById('uploadOptions');
    }

    setupEventListeners() {
        // Drag and drop events
        if (this.dropZone) {
            this.dropZone.addEventListener('dragover', this.handleDragOver.bind(this));
            this.dropZone.addEventListener('dragleave', this.handleDragLeave.bind(this));
            this.dropZone.addEventListener('drop', this.handleDrop.bind(this));
            this.dropZone.addEventListener('click', () => this.fileInput?.click());
        }

        // File input change
        if (this.fileInput) {
            this.fileInput.addEventListener('change', this.handleFileSelect.bind(this));
        }

        // Upload button
        if (this.uploadButton) {
            this.uploadButton.addEventListener('click', this.startUpload.bind(this));
        }

        // Upload options
        this.setupUploadOptions();

        // Refresh quota periodically
        setInterval(() => this.loadUserQuota(), 30000);
    }

    setupUploadOptions() {
        const overwriteToggle = document.getElementById('overwriteExisting');
        const compressionToggle = document.getElementById('enableCompression');
        const privateDirToggle = document.getElementById('uploadToPrivate');

        // Load saved preferences
        if (overwriteToggle) {
            overwriteToggle.checked = localStorage.getItem('upload_overwrite') === 'true';
        }
        if (compressionToggle) {
            compressionToggle.checked = localStorage.getItem('upload_compression') === 'true';
        }
        if (privateDirToggle) {
            privateDirToggle.checked = localStorage.getItem('upload_private') === 'true';
        }

        // Save preferences on change
        [overwriteToggle, compressionToggle, privateDirToggle].forEach(toggle => {
            if (toggle) {
                toggle.addEventListener('change', this.saveUploadPreferences.bind(this));
            }
        });
    }

    saveUploadPreferences() {
        const overwrite = document.getElementById('overwriteExisting')?.checked || false;
        const compression = document.getElementById('enableCompression')?.checked || false;
        const privateDir = document.getElementById('uploadToPrivate')?.checked || false;

        localStorage.setItem('upload_overwrite', overwrite);
        localStorage.setItem('upload_compression', compression);
        localStorage.setItem('upload_private', privateDir);
    }

    handleDragOver(e) {
        e.preventDefault();
        this.dropZone.classList.add('drag-over');
    }

    handleDragLeave(e) {
        e.preventDefault();
        if (!this.dropZone.contains(e.relatedTarget)) {
            this.dropZone.classList.remove('drag-over');
        }
    }

    handleDrop(e) {
        e.preventDefault();
        this.dropZone.classList.remove('drag-over');
        
        const files = Array.from(e.dataTransfer.files);
        this.addFilesToQueue(files);
    }

    handleFileSelect(e) {
        const files = Array.from(e.target.files);
        this.addFilesToQueue(files);
    }

    addFilesToQueue(files) {
        files.forEach(file => {
            if (this.validateFile(file)) {
                this.addFileToList(file);
            }
        });
    }

    validateFile(file) {
        // Check file size
        if (file.size > this.maxFileSize) {
            this.showNotification(`File "${file.name}" is too large. Maximum size: ${this.formatFileSize(this.maxFileSize)}`, 'error');
            return false;
        }

        // Check file type if restrictions exist
        if (this.allowedTypes.length > 0) {
            const fileExt = file.name.split('.').pop().toLowerCase();
            if (!this.allowedTypes.includes(fileExt)) {
                this.showNotification(`File type ".${fileExt}" is not allowed. Allowed types: ${this.allowedTypes.join(', ')}`, 'error');
                return false;
            }
        }

        // Check for potential security issues
        if (this.isSecurityRisk(file.name)) {
            this.showNotification(`File "${file.name}" may pose a security risk and cannot be uploaded`, 'error');
            return false;
        }

        return true;
    }

    isSecurityRisk(filename) {
        const dangerousExtensions = ['php', 'exe', 'bat', 'cmd', 'scr', 'vbs', 'js'];
        const ext = filename.split('.').pop().toLowerCase();
        return dangerousExtensions.includes(ext);
    }

    addFileToList(file) {
        if (!this.fileList) return;

        const fileId = 'file_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.dataset.fileId = fileId;
        
        fileItem.innerHTML = `
            <div class="file-info">
                <div class="file-icon">${this.getFileIcon(file.name)}</div>
                <div class="file-details">
                    <div class="file-name">${this.escapeHtml(file.name)}</div>
                    <div class="file-size">${this.formatFileSize(file.size)}</div>
                </div>
            </div>
            <div class="file-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <div class="progress-text">Waiting...</div>
            </div>
            <button class="remove-btn" onclick="fileUploader.removeFile('${fileId}')">
                <i class="fas fa-times"></i>
            </button>
        `;

        this.fileList.appendChild(fileItem);
        this.currentUploads.set(fileId, { file, element: fileItem, status: 'queued' });
        
        this.updateUploadButton();
    }

    removeFile(fileId) {
        const upload = this.currentUploads.get(fileId);
        if (upload) {
            if (upload.xhr) {
                upload.xhr.abort();
            }
            upload.element.remove();
            this.currentUploads.delete(fileId);
            this.updateUploadButton();
        }
    }

    updateUploadButton() {
        if (!this.uploadButton) return;

        const queuedUploads = Array.from(this.currentUploads.values()).filter(u => u.status === 'queued');
        
        if (queuedUploads.length > 0) {
            this.uploadButton.disabled = false;
            this.uploadButton.textContent = `Upload ${queuedUploads.length} File${queuedUploads.length !== 1 ? 's' : ''}`;
        } else {
            this.uploadButton.disabled = true;
            this.uploadButton.textContent = 'Select Files to Upload';
        }
    }

    async startUpload() {
        const queuedUploads = Array.from(this.currentUploads.entries()).filter(([_, upload]) => upload.status === 'queued');
        
        if (queuedUploads.length === 0) {
            this.showNotification('No files selected for upload', 'warning');
            return;
        }

        this.uploadButton.disabled = true;
        this.uploadButton.textContent = 'Uploading...';

        // Upload files sequentially to avoid overwhelming the server
        for (const [fileId, upload] of queuedUploads) {
            await this.uploadFile(fileId, upload);
        }

        this.uploadButton.textContent = 'Upload Complete';
        setTimeout(() => {
            this.updateUploadButton();
            this.loadUserQuota(); // Refresh quota after upload
        }, 2000);
    }

    uploadFile(fileId, upload) {
        return new Promise((resolve) => {
            const formData = new FormData();
            formData.append('file', upload.file);
            formData.append('csrf_token', this.csrfToken);
            
            // Add upload options
            formData.append('overwrite', document.getElementById('overwriteExisting')?.checked || false);
            formData.append('compress', document.getElementById('enableCompression')?.checked || false);
            formData.append('private', document.getElementById('uploadToPrivate')?.checked || false);

            const xhr = new XMLHttpRequest();
            upload.xhr = xhr;
            upload.status = 'uploading';

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    this.updateProgress(fileId, percentComplete);
                }
            });

            xhr.addEventListener('load', () => {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        this.markUploadComplete(fileId, 'success');
                        this.addToHistory(upload.file.name, 'success', response.message);
                    } else {
                        this.markUploadComplete(fileId, 'error');
                        this.addToHistory(upload.file.name, 'error', response.message);
                        this.showNotification(response.message, 'error');
                    }
                } catch (e) {
                    this.markUploadComplete(fileId, 'error');
                    this.addToHistory(upload.file.name, 'error', 'Invalid server response');
                    this.showNotification('Upload failed: Invalid server response', 'error');
                }
                resolve();
            });

            xhr.addEventListener('error', () => {
                this.markUploadComplete(fileId, 'error');
                this.addToHistory(upload.file.name, 'error', 'Network error');
                this.showNotification('Upload failed: Network error', 'error');
                resolve();
            });

            xhr.open('POST', '../api/upload.php');
            xhr.send(formData);
        });
    }

    updateProgress(fileId, percent) {
        const upload = this.currentUploads.get(fileId);
        if (!upload) return;

        const progressFill = upload.element.querySelector('.progress-fill');
        const progressText = upload.element.querySelector('.progress-text');

        if (progressFill) {
            progressFill.style.width = percent + '%';
        }
        if (progressText) {
            progressText.textContent = Math.round(percent) + '%';
        }
    }

    markUploadComplete(fileId, status) {
        const upload = this.currentUploads.get(fileId);
        if (!upload) return;

        upload.status = status;
        upload.element.classList.add(`upload-${status}`);

        const progressText = upload.element.querySelector('.progress-text');
        const removeBtn = upload.element.querySelector('.remove-btn');

        if (progressText) {
            progressText.textContent = status === 'success' ? 'Complete' : 'Failed';
        }

        // Auto-remove successful uploads after delay
        if (status === 'success') {
            setTimeout(() => {
                if (this.currentUploads.has(fileId)) {
                    this.removeFile(fileId);
                }
            }, 3000);
        }
    }

    addToHistory(filename, status, message) {
        if (!this.uploadHistory) return;

        const historyItem = document.createElement('div');
        historyItem.className = `history-item history-${status}`;
        
        const timestamp = new Date().toLocaleString();
        historyItem.innerHTML = `
            <div class="history-icon">${status === 'success' ? '✓' : '✗'}</div>
            <div class="history-details">
                <div class="history-filename">${this.escapeHtml(filename)}</div>
                <div class="history-message">${this.escapeHtml(message)}</div>
                <div class="history-time">${timestamp}</div>
            </div>
        `;

        this.uploadHistory.insertBefore(historyItem, this.uploadHistory.firstChild);

        // Keep only last 10 items
        while (this.uploadHistory.children.length > 10) {
            this.uploadHistory.removeChild(this.uploadHistory.lastChild);
        }
    }

    async loadUserQuota() {
        try {
            const response = await fetch('../api/users.php?action=quota', {
                headers: {
                    'X-CSRF-Token': this.csrfToken
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.updateQuotaDisplay(data.quota);
                }
            }
        } catch (e) {
            console.error('Failed to load quota:', e);
        }
    }

    updateQuotaDisplay(quota) {
        if (!this.quotaBar || !this.quotaText) return;

        const usedPercent = quota.limit > 0 ? (quota.used / quota.limit) * 100 : 0;
        
        this.quotaBar.style.width = usedPercent + '%';
        this.quotaBar.className = 'quota-bar';
        
        if (usedPercent > 90) {
            this.quotaBar.classList.add('quota-critical');
        } else if (usedPercent > 75) {
            this.quotaBar.classList.add('quota-warning');
        }

        const usedFormatted = this.formatFileSize(quota.used);
        const limitFormatted = quota.limit > 0 ? this.formatFileSize(quota.limit) : 'Unlimited';
        
        this.quotaText.textContent = `${usedFormatted} / ${limitFormatted} (${Math.round(usedPercent)}%)`;
    }

    getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const iconMap = {
            // Images
            'jpg': '🖼️', 'jpeg': '🖼️', 'png': '🖼️', 'gif': '🖼️', 'bmp': '🖼️', 'svg': '🖼️',
            // Documents
            'pdf': '📄', 'doc': '📄', 'docx': '📄', 'txt': '📄', 'rtf': '📄',
            // Spreadsheets
            'xls': '📊', 'xlsx': '📊', 'csv': '📊',
            // Presentations
            'ppt': '📊', 'pptx': '📊',
            // Archives
            'zip': '🗜️', 'rar': '🗜️', '7z': '🗜️', 'tar': '🗜️', 'gz': '🗜️',
            // Audio
            'mp3': '🎵', 'wav': '🎵', 'flac': '🎵', 'aac': '🎵',
            // Video
            'mp4': '🎬', 'avi': '🎬', 'mkv': '🎬', 'mov': '🎬', 'wmv': '🎬',
            // Code
            'js': '💻', 'html': '💻', 'css': '💻', 'php': '💻', 'py': '💻', 'java': '💻'
        };
        
        return iconMap[ext] || '📁';
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${this.escapeHtml(message)}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

        // Add to page
        let container = document.getElementById('notificationContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notificationContainer';
            container.className = 'notification-container';
            document.body.appendChild(container);
        }

        container.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.fileUploader = new FileUploader();
});

// Handle paste events for file uploads
document.addEventListener('paste', (e) => {
    const items = e.clipboardData?.items;
    if (!items) return;

    const files = [];
    for (let i = 0; i < items.length; i++) {
        if (items[i].kind === 'file') {
            files.push(items[i].getAsFile());
        }
    }

    if (files.length > 0 && window.fileUploader) {
        e.preventDefault();
        window.fileUploader.addFilesToQueue(files);
        window.fileUploader.showNotification(`${files.length} file(s) added from clipboard`, 'success');
    }
});
