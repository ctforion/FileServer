/**
 * Upload Page JavaScript
 * Handles file uploads with drag-drop, progress tracking, and advanced features
 */

// Extend the main app with upload functionality
FileServerApp.prototype.initUploadPage = function() {
    this.setupDropZone();
    this.setupFileInput();
    this.setupUploadQueue();
    this.setupUploadSettings();
    this.setupPasteUpload();
    this.initializeUploadState();
};

/**
 * Initialize upload state
 */
FileServerApp.prototype.initializeUploadState = function() {
    this.uploadQueue = [];
    this.maxFileSize = parseInt(document.querySelector('[data-max-file-size]')?.dataset.maxFileSize) || 100 * 1024 * 1024; // 100MB default
    this.allowedTypes = document.querySelector('[data-allowed-types]')?.dataset.allowedTypes?.split(',') || [];
    this.maxFiles = parseInt(document.querySelector('[data-max-files]')?.dataset.maxFiles) || 10;
    this.compressionEnabled = document.querySelector('#compressionEnabled')?.checked || false;
    this.generateThumbnails = document.querySelector('#generateThumbnails')?.checked || true;
    this.makePublic = document.querySelector('#makePublic')?.checked || false;
};

/**
 * Setup drag and drop zone
 */
FileServerApp.prototype.setupDropZone = function() {
    const dropZone = document.getElementById('dropZone');
    if (!dropZone) return;

    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, this.preventDefaults, false);
        document.body.addEventListener(eventName, this.preventDefaults, false);
    });

    // Highlight drop zone when item is dragged over it
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('drag-over');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('drag-over');
        }, false);
    });

    // Handle dropped files
    dropZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        this.handleFiles([...files]);
    }, false);

    // Click to upload
    dropZone.addEventListener('click', () => {
        document.getElementById('fileInput')?.click();
    });
};

/**
 * Prevent default drag behaviors
 */
FileServerApp.prototype.preventDefaults = function(e) {
    e.preventDefault();
    e.stopPropagation();
};

/**
 * Setup file input
 */
FileServerApp.prototype.setupFileInput = function() {
    const fileInput = document.getElementById('fileInput');
    if (!fileInput) return;

    fileInput.addEventListener('change', (e) => {
        const files = [...e.target.files];
        this.handleFiles(files);
        // Clear input so same file can be selected again
        e.target.value = '';
    });
};

/**
 * Setup paste upload functionality
 */
FileServerApp.prototype.setupPasteUpload = function() {
    document.addEventListener('paste', (e) => {
        if (e.target.closest('input, textarea')) return; // Don't interfere with text inputs

        const items = e.clipboardData.items;
        const files = [];

        for (let item of items) {
            if (item.kind === 'file') {
                const file = item.getAsFile();
                if (file) {
                    files.push(file);
                }
            }
        }

        if (files.length > 0) {
            e.preventDefault();
            this.handleFiles(files);
            this.showNotification(`${files.length} file(s) pasted from clipboard`, 'info');
        }
    });
};

/**
 * Handle selected files
 */
FileServerApp.prototype.handleFiles = function(files) {
    if (!files || files.length === 0) return;

    // Check file count limit
    if (this.uploadQueue.length + files.length > this.maxFiles) {
        this.showNotification(`Maximum ${this.maxFiles} files allowed`, 'error');
        return;
    }

    const validFiles = [];
    const errors = [];

    files.forEach(file => {
        const validation = this.validateFile(file);
        if (validation.valid) {
            validFiles.push(file);
        } else {
            errors.push(`${file.name}: ${validation.error}`);
        }
    });

    // Show validation errors
    if (errors.length > 0) {
        errors.forEach(error => this.showNotification(error, 'error'));
    }

    // Add valid files to queue
    validFiles.forEach(file => {
        const uploadItem = this.createUploadItem(file);
        this.uploadQueue.push(uploadItem);
        this.addFileToUI(uploadItem);
    });

    if (validFiles.length > 0) {
        this.updateUploadUI();
        this.showNotification(`${validFiles.length} file(s) added to upload queue`, 'success');
    }
};

/**
 * Validate file
 */
FileServerApp.prototype.validateFile = function(file) {
    // Check file size
    if (file.size > this.maxFileSize) {
        return {
            valid: false,
            error: `File too large (max ${this.formatFileSize(this.maxFileSize)})`
        };
    }

    // Check file type if restrictions exist
    if (this.allowedTypes.length > 0) {
        const fileExtension = file.name.split('.').pop().toLowerCase();
        const mimeType = file.type.toLowerCase();
        
        const isAllowed = this.allowedTypes.some(type => {
            type = type.trim().toLowerCase();
            return type === fileExtension || 
                   type === mimeType || 
                   (type.startsWith('.') && type.substring(1) === fileExtension);
        });

        if (!isAllowed) {
            return {
                valid: false,
                error: `File type not allowed (allowed: ${this.allowedTypes.join(', ')})`
            };
        }
    }

    return { valid: true };
};

/**
 * Create upload item object
 */
FileServerApp.prototype.createUploadItem = function(file) {
    const id = 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    return {
        id: id,
        file: file,
        status: 'pending', // pending, uploading, completed, error
        progress: 0,
        uploadedBytes: 0,
        speed: 0,
        eta: 0,
        error: null,
        response: null,
        startTime: null,
        xhr: null
    };
};

/**
 * Add file to UI
 */
FileServerApp.prototype.addFileToUI = function(uploadItem) {
    const uploadList = document.getElementById('uploadList');
    if (!uploadList) return;

    const fileElement = document.createElement('div');
    fileElement.className = 'upload-item';
    fileElement.dataset.uploadId = uploadItem.id;
    fileElement.innerHTML = this.getUploadItemHTML(uploadItem);

    uploadList.appendChild(fileElement);

    // Add remove button functionality
    const removeBtn = fileElement.querySelector('.remove-file');
    if (removeBtn) {
        removeBtn.addEventListener('click', () => {
            this.removeFileFromQueue(uploadItem.id);
        });
    }

    // Generate preview for images
    if (uploadItem.file.type.startsWith('image/')) {
        this.generateImagePreview(uploadItem.file, fileElement.querySelector('.file-preview'));
    }
};

/**
 * Get upload item HTML
 */
FileServerApp.prototype.getUploadItemHTML = function(uploadItem) {
    const file = uploadItem.file;
    const fileIcon = this.getFileIcon(file.type);
    
    return `
        <div class="upload-item-header">
            <div class="file-info">
                <div class="file-preview">
                    <i class="${fileIcon}"></i>
                </div>
                <div class="file-details">
                    <div class="file-name" title="${file.name}">${file.name}</div>
                    <div class="file-meta">
                        ${this.formatFileSize(file.size)} • ${file.type || 'Unknown type'}
                    </div>
                </div>
            </div>
            <div class="upload-actions">
                <button type="button" class="btn btn-sm btn-outline-danger remove-file" title="Remove file">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="upload-progress">
            <div class="progress">
                <div class="progress-bar" style="width: 0%"></div>
            </div>
            <div class="upload-status">
                <span class="status-text">Pending</span>
                <span class="upload-speed"></span>
            </div>
        </div>
    `;
};

/**
 * Get file icon based on type
 */
FileServerApp.prototype.getFileIcon = function(mimeType) {
    if (mimeType.startsWith('image/')) return 'fas fa-image';
    if (mimeType.startsWith('video/')) return 'fas fa-video';
    if (mimeType.startsWith('audio/')) return 'fas fa-music';
    if (mimeType.includes('pdf')) return 'fas fa-file-pdf';
    if (mimeType.includes('word')) return 'fas fa-file-word';
    if (mimeType.includes('excel') || mimeType.includes('sheet')) return 'fas fa-file-excel';
    if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'fas fa-file-powerpoint';
    if (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('archive')) return 'fas fa-file-archive';
    if (mimeType.includes('text')) return 'fas fa-file-alt';
    return 'fas fa-file';
};

/**
 * Generate image preview
 */
FileServerApp.prototype.generateImagePreview = function(file, previewElement) {
    const reader = new FileReader();
    reader.onload = (e) => {
        previewElement.innerHTML = `<img src="${e.target.result}" alt="Preview" class="img-preview">`;
    };
    reader.readAsDataURL(file);
};

/**
 * Remove file from queue
 */
FileServerApp.prototype.removeFileFromQueue = function(uploadId) {
    const index = this.uploadQueue.findIndex(item => item.id === uploadId);
    if (index === -1) return;

    const uploadItem = this.uploadQueue[index];
    
    // Cancel upload if in progress
    if (uploadItem.xhr) {
        uploadItem.xhr.abort();
    }

    // Remove from queue
    this.uploadQueue.splice(index, 1);

    // Remove from UI
    const element = document.querySelector(`[data-upload-id="${uploadId}"]`);
    if (element) {
        element.remove();
    }

    this.updateUploadUI();
};

/**
 * Setup upload queue controls
 */
FileServerApp.prototype.setupUploadQueue = function() {
    // Start upload button
    const startUploadBtn = document.getElementById('startUpload');
    if (startUploadBtn) {
        startUploadBtn.addEventListener('click', () => {
            this.startUploads();
        });
    }

    // Clear queue button
    const clearQueueBtn = document.getElementById('clearQueue');
    if (clearQueueBtn) {
        clearQueueBtn.addEventListener('click', () => {
            this.clearQueue();
        });
    }

    // Pause/Resume uploads
    const pauseUploadBtn = document.getElementById('pauseUpload');
    if (pauseUploadBtn) {
        pauseUploadBtn.addEventListener('click', () => {
            this.toggleUploads();
        });
    }
};

/**
 * Setup upload settings
 */
FileServerApp.prototype.setupUploadSettings = function() {
    // Compression toggle
    const compressionToggle = document.getElementById('compressionEnabled');
    if (compressionToggle) {
        compressionToggle.addEventListener('change', (e) => {
            this.compressionEnabled = e.target.checked;
        });
    }

    // Thumbnail generation toggle
    const thumbnailToggle = document.getElementById('generateThumbnails');
    if (thumbnailToggle) {
        thumbnailToggle.addEventListener('change', (e) => {
            this.generateThumbnails = e.target.checked;
        });
    }

    // Public access toggle
    const publicToggle = document.getElementById('makePublic');
    if (publicToggle) {
        publicToggle.addEventListener('change', (e) => {
            this.makePublic = e.target.checked;
        });
    }

    // Directory selection
    const directorySelect = document.getElementById('uploadDirectory');
    if (directorySelect) {
        directorySelect.addEventListener('change', (e) => {
            this.uploadDirectory = e.target.value;
        });
    }
};

/**
 * Start uploads
 */
FileServerApp.prototype.startUploads = function() {
    const pendingUploads = this.uploadQueue.filter(item => item.status === 'pending');
    
    if (pendingUploads.length === 0) {
        this.showNotification('No files to upload', 'warning');
        return;
    }

    this.uploadsPaused = false;
    this.updateUploadControlsUI();

    // Start uploading files (max 3 concurrent)
    const maxConcurrent = 3;
    const activeUploads = [];

    const processNext = () => {
        if (this.uploadsPaused) return;

        const nextUpload = this.uploadQueue.find(item => 
            item.status === 'pending' && !activeUploads.includes(item.id)
        );

        if (nextUpload && activeUploads.length < maxConcurrent) {
            activeUploads.push(nextUpload.id);
            this.uploadFile(nextUpload).finally(() => {
                const index = activeUploads.indexOf(nextUpload.id);
                if (index > -1) {
                    activeUploads.splice(index, 1);
                }
                processNext();
            });
        }
    };

    // Start initial uploads
    for (let i = 0; i < maxConcurrent; i++) {
        processNext();
    }
};

/**
 * Upload single file
 */
FileServerApp.prototype.uploadFile = async function(uploadItem) {
    const formData = new FormData();
    formData.append('file', uploadItem.file);
    formData.append('directory', this.uploadDirectory || '');
    formData.append('compression', this.compressionEnabled);
    formData.append('generate_thumbnails', this.generateThumbnails);
    formData.append('public', this.makePublic);

    const xhr = new XMLHttpRequest();
    uploadItem.xhr = xhr;
    uploadItem.startTime = Date.now();

    return new Promise((resolve, reject) => {
        // Progress tracking
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                this.updateUploadProgress(uploadItem, e.loaded, e.total);
            }
        });

        // Upload completion
        xhr.addEventListener('load', () => {
            try {
                const response = JSON.parse(xhr.responseText);
                if (xhr.status === 200 && response.success) {
                    this.handleUploadSuccess(uploadItem, response);
                    resolve(response);
                } else {
                    this.handleUploadError(uploadItem, response.message || 'Upload failed');
                    reject(new Error(response.message || 'Upload failed'));
                }
            } catch (error) {
                this.handleUploadError(uploadItem, 'Invalid response from server');
                reject(error);
            }
        });

        // Upload error
        xhr.addEventListener('error', () => {
            this.handleUploadError(uploadItem, 'Network error occurred');
            reject(new Error('Network error'));
        });

        // Upload abort
        xhr.addEventListener('abort', () => {
            this.handleUploadAbort(uploadItem);
            resolve(); // Don't reject on user abort
        });

        // Start upload
        uploadItem.status = 'uploading';
        this.updateUploadItemUI(uploadItem);

        xhr.open('POST', `${this.apiBaseUrl}/files/upload`);
        if (this.token) {
            xhr.setRequestHeader('Authorization', `Bearer ${this.token}`);
        }
        xhr.send(formData);
    });
};

/**
 * Update upload progress
 */
FileServerApp.prototype.updateUploadProgress = function(uploadItem, loaded, total) {
    const now = Date.now();
    const elapsed = (now - uploadItem.startTime) / 1000; // seconds
    
    uploadItem.uploadedBytes = loaded;
    uploadItem.progress = (loaded / total) * 100;
    uploadItem.speed = loaded / elapsed; // bytes per second
    uploadItem.eta = uploadItem.speed > 0 ? (total - loaded) / uploadItem.speed : 0;

    this.updateUploadItemUI(uploadItem);
    this.updateOverallProgress();
};

/**
 * Handle upload success
 */
FileServerApp.prototype.handleUploadSuccess = function(uploadItem, response) {
    uploadItem.status = 'completed';
    uploadItem.response = response;
    uploadItem.progress = 100;
    
    this.updateUploadItemUI(uploadItem);
    this.updateOverallProgress();
    
    this.showNotification(`${uploadItem.file.name} uploaded successfully`, 'success', 2000);
};

/**
 * Handle upload error
 */
FileServerApp.prototype.handleUploadError = function(uploadItem, error) {
    uploadItem.status = 'error';
    uploadItem.error = error;
    
    this.updateUploadItemUI(uploadItem);
    this.updateOverallProgress();
    
    this.showNotification(`Failed to upload ${uploadItem.file.name}: ${error}`, 'error');
};

/**
 * Handle upload abort
 */
FileServerApp.prototype.handleUploadAbort = function(uploadItem) {
    uploadItem.status = 'pending'; // Reset to pending so it can be retried
    uploadItem.progress = 0;
    uploadItem.uploadedBytes = 0;
    
    this.updateUploadItemUI(uploadItem);
    this.updateOverallProgress();
};

/**
 * Update upload item UI
 */
FileServerApp.prototype.updateUploadItemUI = function(uploadItem) {
    const element = document.querySelector(`[data-upload-id="${uploadItem.id}"]`);
    if (!element) return;

    const progressBar = element.querySelector('.progress-bar');
    const statusText = element.querySelector('.status-text');
    const uploadSpeed = element.querySelector('.upload-speed');

    // Update progress bar
    if (progressBar) {
        progressBar.style.width = `${uploadItem.progress}%`;
        progressBar.className = `progress-bar progress-bar-${uploadItem.status}`;
    }

    // Update status text
    if (statusText) {
        switch (uploadItem.status) {
            case 'pending':
                statusText.textContent = 'Pending';
                break;
            case 'uploading':
                const eta = uploadItem.eta > 0 ? this.formatTime(uploadItem.eta) : '';
                statusText.textContent = `Uploading... ${Math.round(uploadItem.progress)}%${eta ? ` • ${eta} remaining` : ''}`;
                break;
            case 'completed':
                statusText.textContent = 'Completed';
                break;
            case 'error':
                statusText.textContent = `Error: ${uploadItem.error}`;
                break;
        }
    }

    // Update upload speed
    if (uploadSpeed && uploadItem.status === 'uploading' && uploadItem.speed > 0) {
        uploadSpeed.textContent = `${this.formatFileSize(uploadItem.speed)}/s`;
    } else {
        uploadSpeed.textContent = '';
    }

    // Update element class
    element.className = `upload-item upload-item-${uploadItem.status}`;
};

/**
 * Update overall progress
 */
FileServerApp.prototype.updateOverallProgress = function() {
    const totalFiles = this.uploadQueue.length;
    const completedFiles = this.uploadQueue.filter(item => item.status === 'completed').length;
    const failedFiles = this.uploadQueue.filter(item => item.status === 'error').length;
    
    const overallProgress = totalFiles > 0 ? (completedFiles / totalFiles) * 100 : 0;
    
    // Update overall progress bar
    const overallProgressBar = document.getElementById('overallProgress');
    if (overallProgressBar) {
        overallProgressBar.style.width = `${overallProgress}%`;
    }

    // Update status text
    const overallStatus = document.getElementById('overallStatus');
    if (overallStatus) {
        overallStatus.textContent = `${completedFiles}/${totalFiles} files uploaded`;
        if (failedFiles > 0) {
            overallStatus.textContent += ` (${failedFiles} failed)`;
        }
    }

    this.updateUploadControlsUI();
};

/**
 * Update upload controls UI
 */
FileServerApp.prototype.updateUploadControlsUI = function() {
    const startBtn = document.getElementById('startUpload');
    const pauseBtn = document.getElementById('pauseUpload');
    const clearBtn = document.getElementById('clearQueue');

    const hasFiles = this.uploadQueue.length > 0;
    const hasPending = this.uploadQueue.some(item => item.status === 'pending');
    const hasUploading = this.uploadQueue.some(item => item.status === 'uploading');

    if (startBtn) {
        startBtn.disabled = !hasPending;
        startBtn.textContent = hasUploading ? 'Upload in Progress...' : 'Start Upload';
    }

    if (pauseBtn) {
        pauseBtn.style.display = hasUploading ? 'inline-block' : 'none';
        pauseBtn.textContent = this.uploadsPaused ? 'Resume' : 'Pause';
    }

    if (clearBtn) {
        clearBtn.disabled = hasUploading;
    }
};

/**
 * Update upload UI
 */
FileServerApp.prototype.updateUploadUI = function() {
    const hasFiles = this.uploadQueue.length > 0;
    
    // Show/hide upload controls
    const uploadControls = document.getElementById('uploadControls');
    if (uploadControls) {
        uploadControls.style.display = hasFiles ? 'block' : 'none';
    }

    // Show/hide empty state
    const emptyState = document.getElementById('emptyState');
    if (emptyState) {
        emptyState.style.display = hasFiles ? 'none' : 'block';
    }

    this.updateUploadControlsUI();
    this.updateOverallProgress();
};

/**
 * Toggle uploads (pause/resume)
 */
FileServerApp.prototype.toggleUploads = function() {
    this.uploadsPaused = !this.uploadsPaused;
    
    if (this.uploadsPaused) {
        // Abort current uploads
        this.uploadQueue.forEach(item => {
            if (item.status === 'uploading' && item.xhr) {
                item.xhr.abort();
            }
        });
        this.showNotification('Uploads paused', 'info');
    } else {
        this.showNotification('Uploads resumed', 'info');
        this.startUploads();
    }
    
    this.updateUploadControlsUI();
};

/**
 * Clear upload queue
 */
FileServerApp.prototype.clearQueue = function() {
    if (this.uploadQueue.some(item => item.status === 'uploading')) {
        if (!confirm('There are uploads in progress. Are you sure you want to clear the queue?')) {
            return;
        }
    }

    // Abort all uploads
    this.uploadQueue.forEach(item => {
        if (item.xhr) {
            item.xhr.abort();
        }
    });

    // Clear queue and UI
    this.uploadQueue = [];
    const uploadList = document.getElementById('uploadList');
    if (uploadList) {
        uploadList.innerHTML = '';
    }

    this.updateUploadUI();
    this.showNotification('Upload queue cleared', 'info');
};

/**
 * Format time in seconds to human readable
 */
FileServerApp.prototype.formatTime = function(seconds) {
    if (seconds < 60) {
        return `${Math.round(seconds)}s`;
    } else if (seconds < 3600) {
        return `${Math.round(seconds / 60)}m ${Math.round(seconds % 60)}s`;
    } else {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.round((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    }
};
