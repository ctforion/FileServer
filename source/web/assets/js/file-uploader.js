/**
 * File Uploader Module
 * Handles file uploads with drag-and-drop, progress tracking, and queue management
 */

class FileUploader {
    constructor() {
        this.uploadQueue = [];
        this.activeUploads = 0;
        this.maxConcurrentUploads = 3;
        this.chunkSize = 1024 * 1024; // 1MB chunks
        
        this.dropZone = document.querySelector('[data-upload-dropzone]');
        this.fileInput = document.querySelector('[data-upload-input]');
        this.uploadButton = document.querySelector('[data-upload-trigger]');
        this.uploadModal = document.querySelector('[data-upload-modal]');
        this.progressContainer = document.querySelector('[data-upload-progress]');
        this.queueContainer = document.querySelector('[data-upload-queue]');
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.setupDropZone();
    }
    
    setupEventListeners() {
        // Upload button click
        if (this.uploadButton) {
            this.uploadButton.addEventListener('click', () => {
                this.fileInput.click();
            });
        }
        
        // File input change
        if (this.fileInput) {
            this.fileInput.addEventListener('change', (e) => {
                this.handleFiles(e.target.files);
            });
        }
        
        // Modal controls
        const modalClose = this.uploadModal?.querySelector('[data-modal-close]');
        if (modalClose) {
            modalClose.addEventListener('click', () => {
                this.closeUploadModal();
            });
        }
        
        const clearQueue = this.uploadModal?.querySelector('[data-clear-queue]');
        if (clearQueue) {
            clearQueue.addEventListener('click', () => {
                this.clearQueue();
            });
        }
        
        const startUploads = this.uploadModal?.querySelector('[data-start-uploads]');
        if (startUploads) {
            startUploads.addEventListener('click', () => {
                this.startUploads();
            });
        }
        
        const pauseUploads = this.uploadModal?.querySelector('[data-pause-uploads]');
        if (pauseUploads) {
            pauseUploads.addEventListener('click', () => {
                this.pauseUploads();
            });
        }
    }
    
    setupDropZone() {
        if (!this.dropZone) return;
        
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, this.preventDefaults, false);
            document.body.addEventListener(eventName, this.preventDefaults, false);
        });
        
        // Highlight drop zone when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => {
                this.dropZone.classList.add('drag-over');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => {
                this.dropZone.classList.remove('drag-over');
            }, false);
        });
        
        // Handle dropped files
        this.dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            this.handleFiles(files);
        }, false);
    }
    
    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    handleFiles(files) {
        const fileArray = Array.from(files);
        
        // Validate files
        const validFiles = fileArray.filter(file => this.validateFile(file));
        
        if (validFiles.length === 0) {
            window.app.showNotification('No valid files selected', 'warning');
            return;
        }
        
        // Add files to queue
        validFiles.forEach(file => {
            this.addToQueue(file);
        });
        
        // Show upload modal
        this.showUploadModal();
        
        // Auto-start if enabled
        if (this.isAutoStartEnabled()) {
            this.startUploads();
        }
    }
    
    validateFile(file) {
        const config = window.app.config;
        
        // Check file size
        if (file.size > config.maxFileSize) {
            window.app.showNotification(
                `File "${file.name}" is too large. Maximum size is ${window.app.formatFileSize(config.maxFileSize)}`,
                'error'
            );
            return false;
        }
        
        // Check file type
        const extension = file.name.split('.').pop().toLowerCase();
        if (config.allowedTypes && !config.allowedTypes.includes(extension)) {
            window.app.showNotification(
                `File type "${extension}" is not allowed`,
                'error'
            );
            return false;
        }
        
        return true;
    }
    
    addToQueue(file) {
        const uploadItem = {
            id: this.generateUploadId(),
            file: file,
            status: 'queued', // queued, uploading, completed, error, paused
            progress: 0,
            uploaded: 0,
            speed: 0,
            timeRemaining: 0,
            startTime: null,
            xhr: null,
            retryCount: 0,
            maxRetries: 3
        };
        
        this.uploadQueue.push(uploadItem);
        this.renderQueueItem(uploadItem);
    }
    
    generateUploadId() {
        return 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    renderQueueItem(uploadItem) {
        const queueItem = document.createElement('div');
        queueItem.className = 'upload-queue-item';
        queueItem.dataset.uploadId = uploadItem.id;
        
        queueItem.innerHTML = `
            <div class="upload-item-info">
                <div class="upload-item-name">${uploadItem.file.name}</div>
                <div class="upload-item-details">
                    <span class="upload-item-size">${window.app.formatFileSize(uploadItem.file.size)}</span>
                    <span class="upload-item-status">${uploadItem.status}</span>
                </div>
            </div>
            <div class="upload-item-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${uploadItem.progress}%"></div>
                </div>
                <div class="upload-item-actions">
                    <button class="btn-icon" data-action="retry" title="Retry" style="display: none;">
                        <i class="fas fa-redo"></i>
                    </button>
                    <button class="btn-icon" data-action="pause" title="Pause" style="display: none;">
                        <i class="fas fa-pause"></i>
                    </button>
                    <button class="btn-icon" data-action="resume" title="Resume" style="display: none;">
                        <i class="fas fa-play"></i>
                    </button>
                    <button class="btn-icon" data-action="remove" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        
        // Add event listeners for actions
        this.setupQueueItemActions(queueItem, uploadItem);
        
        if (this.queueContainer) {
            this.queueContainer.appendChild(queueItem);
        }
    }
    
    setupQueueItemActions(queueItem, uploadItem) {
        const actions = queueItem.querySelectorAll('[data-action]');
        actions.forEach(button => {
            button.addEventListener('click', () => {
                const action = button.dataset.action;
                this.handleQueueAction(uploadItem.id, action);
            });
        });
    }
    
    handleQueueAction(uploadId, action) {
        const uploadItem = this.uploadQueue.find(item => item.id === uploadId);
        if (!uploadItem) return;
        
        switch (action) {
            case 'remove':
                this.removeFromQueue(uploadId);
                break;
            case 'retry':
                this.retryUpload(uploadId);
                break;
            case 'pause':
                this.pauseUpload(uploadId);
                break;
            case 'resume':
                this.resumeUpload(uploadId);
                break;
        }
    }
    
    removeFromQueue(uploadId) {
        const index = this.uploadQueue.findIndex(item => item.id === uploadId);
        if (index === -1) return;
        
        const uploadItem = this.uploadQueue[index];
        
        // Cancel active upload
        if (uploadItem.xhr) {
            uploadItem.xhr.abort();
        }
        
        // Remove from queue
        this.uploadQueue.splice(index, 1);
        
        // Remove from DOM
        const queueItem = document.querySelector(`[data-upload-id="${uploadId}"]`);
        if (queueItem) {
            queueItem.remove();
        }
        
        // Update active uploads count
        if (uploadItem.status === 'uploading') {
            this.activeUploads--;
            this.processQueue();
        }
    }
    
    showUploadModal() {
        if (this.uploadModal) {
            this.uploadModal.classList.add('active');
        }
    }
    
    closeUploadModal() {
        if (this.uploadModal) {
            this.uploadModal.classList.remove('active');
        }
    }
    
    startUploads() {
        this.processQueue();
    }
    
    pauseUploads() {
        this.uploadQueue.forEach(item => {
            if (item.status === 'uploading') {
                this.pauseUpload(item.id);
            }
        });
    }
    
    pauseUpload(uploadId) {
        const uploadItem = this.uploadQueue.find(item => item.id === uploadId);
        if (!uploadItem || uploadItem.status !== 'uploading') return;
        
        if (uploadItem.xhr) {
            uploadItem.xhr.abort();
        }
        
        uploadItem.status = 'paused';
        this.activeUploads--;
        this.updateQueueItemUI(uploadItem);
        this.processQueue();
    }
    
    resumeUpload(uploadId) {
        const uploadItem = this.uploadQueue.find(item => item.id === uploadId);
        if (!uploadItem || uploadItem.status !== 'paused') return;
        
        uploadItem.status = 'queued';
        this.updateQueueItemUI(uploadItem);
        this.processQueue();
    }
    
    retryUpload(uploadId) {
        const uploadItem = this.uploadQueue.find(item => item.id === uploadId);
        if (!uploadItem) return;
        
        uploadItem.status = 'queued';
        uploadItem.progress = 0;
        uploadItem.uploaded = 0;
        uploadItem.retryCount++;
        this.updateQueueItemUI(uploadItem);
        this.processQueue();
    }
    
    clearQueue() {
        // Cancel all active uploads
        this.uploadQueue.forEach(item => {
            if (item.xhr) {
                item.xhr.abort();
            }
        });
        
        // Clear queue
        this.uploadQueue = [];
        this.activeUploads = 0;
        
        // Clear UI
        if (this.queueContainer) {
            this.queueContainer.innerHTML = '';
        }
    }
    
    processQueue() {
        // Start uploads up to the concurrent limit
        while (this.activeUploads < this.maxConcurrentUploads) {
            const nextUpload = this.uploadQueue.find(item => item.status === 'queued');
            if (!nextUpload) break;
            
            this.startUpload(nextUpload);
        }
    }
    
    async startUpload(uploadItem) {
        uploadItem.status = 'uploading';
        uploadItem.startTime = Date.now();
        this.activeUploads++;
        this.updateQueueItemUI(uploadItem);
        
        try {
            // Check if file should be uploaded in chunks
            if (uploadItem.file.size > this.chunkSize) {
                await this.uploadFileChunked(uploadItem);
            } else {
                await this.uploadFileSimple(uploadItem);
            }
            
            uploadItem.status = 'completed';
            uploadItem.progress = 100;
            this.updateQueueItemUI(uploadItem);
            
            window.app.showNotification(`"${uploadItem.file.name}" uploaded successfully`, 'success');
            
            // Refresh file list if on files page
            if (window.fileBrowser) {
                window.fileBrowser.refresh();
            }
            
        } catch (error) {
            console.error('Upload error:', error);
            
            if (uploadItem.retryCount < uploadItem.maxRetries) {
                uploadItem.status = 'queued';
                window.app.showNotification(`Upload failed, retrying "${uploadItem.file.name}"`, 'warning');
            } else {
                uploadItem.status = 'error';
                window.app.showNotification(`Upload failed for "${uploadItem.file.name}"`, 'error');
            }
            
            this.updateQueueItemUI(uploadItem);
        } finally {
            this.activeUploads--;
            this.processQueue();
        }
    }
    
    uploadFileSimple(uploadItem) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('file', uploadItem.file);
            formData.append('action', 'upload');
            
            const xhr = new XMLHttpRequest();
            uploadItem.xhr = xhr;
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    uploadItem.progress = (e.loaded / e.total) * 100;
                    uploadItem.uploaded = e.loaded;
                    this.updateUploadProgress(uploadItem, e.loaded, e.total);
                }
            });
            
            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            resolve(response);
                        } else {
                            reject(new Error(response.message || 'Upload failed'));
                        }
                    } catch (e) {
                        reject(new Error('Invalid response format'));
                    }
                } else {
                    reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                }
            });
            
            xhr.addEventListener('error', () => {
                reject(new Error('Network error'));
            });
            
            xhr.addEventListener('abort', () => {
                reject(new Error('Upload cancelled'));
            });
            
            xhr.open('POST', '/api/files/upload');
            
            // Add CSRF token if available
            if (window.app.csrfToken) {
                xhr.setRequestHeader('X-CSRF-Token', window.app.csrfToken);
            }
            
            xhr.send(formData);
        });
    }
    
    async uploadFileChunked(uploadItem) {
        const file = uploadItem.file;
        const totalChunks = Math.ceil(file.size / this.chunkSize);
        let uploadedChunks = 0;
        
        // Initialize chunked upload
        const initResponse = await this.apiRequest('/api/files/upload/init', 'POST', {
            filename: file.name,
            size: file.size,
            type: file.type,
            chunks: totalChunks
        });
        
        const uploadId = initResponse.upload_id;
        
        // Upload chunks
        for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
            if (uploadItem.status !== 'uploading') {
                throw new Error('Upload cancelled');
            }
            
            const start = chunkIndex * this.chunkSize;
            const end = Math.min(start + this.chunkSize, file.size);
            const chunk = file.slice(start, end);
            
            await this.uploadChunk(uploadItem, uploadId, chunkIndex, chunk);
            
            uploadedChunks++;
            uploadItem.progress = (uploadedChunks / totalChunks) * 100;
            uploadItem.uploaded = uploadedChunks * this.chunkSize;
            this.updateUploadProgress(uploadItem, uploadItem.uploaded, file.size);
        }
        
        // Finalize upload
        await this.apiRequest('/api/files/upload/finalize', 'POST', {
            upload_id: uploadId
        });
    }
    
    uploadChunk(uploadItem, uploadId, chunkIndex, chunk) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('upload_id', uploadId);
            formData.append('chunk_index', chunkIndex);
            formData.append('chunk', chunk);
            
            const xhr = new XMLHttpRequest();
            uploadItem.xhr = xhr;
            
            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            resolve(response);
                        } else {
                            reject(new Error(response.message || 'Chunk upload failed'));
                        }
                    } catch (e) {
                        reject(new Error('Invalid response format'));
                    }
                } else {
                    reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                }
            });
            
            xhr.addEventListener('error', () => {
                reject(new Error('Network error'));
            });
            
            xhr.addEventListener('abort', () => {
                reject(new Error('Upload cancelled'));
            });
            
            xhr.open('POST', '/api/files/upload/chunk');
            
            if (window.app.csrfToken) {
                xhr.setRequestHeader('X-CSRF-Token', window.app.csrfToken);
            }
            
            xhr.send(formData);
        });
    }
    
    updateUploadProgress(uploadItem, loaded, total) {
        // Calculate speed and time remaining
        if (uploadItem.startTime) {
            const elapsedTime = (Date.now() - uploadItem.startTime) / 1000;
            uploadItem.speed = loaded / elapsedTime;
            
            if (uploadItem.speed > 0) {
                uploadItem.timeRemaining = (total - loaded) / uploadItem.speed;
            }
        }
        
        this.updateQueueItemUI(uploadItem);
    }
    
    updateQueueItemUI(uploadItem) {
        const queueItem = document.querySelector(`[data-upload-id="${uploadItem.id}"]`);
        if (!queueItem) return;
        
        // Update progress bar
        const progressFill = queueItem.querySelector('.progress-fill');
        if (progressFill) {
            progressFill.style.width = `${uploadItem.progress}%`;
        }
        
        // Update status
        const statusElement = queueItem.querySelector('.upload-item-status');
        if (statusElement) {
            let statusText = uploadItem.status;
            
            if (uploadItem.status === 'uploading' && uploadItem.speed) {
                statusText += ` - ${window.app.formatFileSize(uploadItem.speed)}/s`;
                if (uploadItem.timeRemaining) {
                    statusText += ` - ${this.formatTime(uploadItem.timeRemaining)} left`;
                }
            }
            
            statusElement.textContent = statusText;
        }
        
        // Update action buttons visibility
        const retryBtn = queueItem.querySelector('[data-action="retry"]');
        const pauseBtn = queueItem.querySelector('[data-action="pause"]');
        const resumeBtn = queueItem.querySelector('[data-action="resume"]');
        
        if (retryBtn) retryBtn.style.display = uploadItem.status === 'error' ? 'inline-block' : 'none';
        if (pauseBtn) pauseBtn.style.display = uploadItem.status === 'uploading' ? 'inline-block' : 'none';
        if (resumeBtn) resumeBtn.style.display = uploadItem.status === 'paused' ? 'inline-block' : 'none';
        
        // Update item class
        queueItem.className = `upload-queue-item upload-${uploadItem.status}`;
    }
    
    formatTime(seconds) {
        if (seconds < 60) {
            return `${Math.round(seconds)}s`;
        } else if (seconds < 3600) {
            return `${Math.round(seconds / 60)}m`;
        } else {
            return `${Math.round(seconds / 3600)}h`;
        }
    }
    
    isAutoStartEnabled() {
        return localStorage.getItem('autoStartUploads') === 'true';
    }
    
    async apiRequest(url, method = 'GET', data = null) {
        return await window.app.apiRequest(url, method, data);
    }
}

// Export for global use
window.FileUploader = FileUploader;
