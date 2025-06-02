// Upload functionality JavaScript
class FileUploader {
    constructor() {
        this.selectedFiles = [];
        this.uploadQueue = [];
        this.isUploading = false;
        this.chunkSize = 1024 * 1024; // 1MB chunks
        this.maxConcurrent = 3;
        this.currentUploads = 0;
        this.init();
    }

    init() {
        this.bindEvents();
        this.initDragAndDrop();
        this.setupProgressTracking();
    }

    bindEvents() {
        // File input change
        const fileInput = document.getElementById('files');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                this.handleFileSelection(e.target.files);
            });
        }

        // Upload form submission
        const uploadForm = document.getElementById('uploadForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.startUpload();
            });
        }

        // Remove file buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-file-btn')) {
                this.removeFile(e.target.dataset.index);
            }
        });

        // Upload directory selection
        const uploadDir = document.getElementById('upload_dir');
        if (uploadDir) {
            uploadDir.addEventListener('change', (e) => {
                this.updateUploadDirectory(e.target.value);
            });
        }

        // Cancel upload
        const cancelBtn = document.getElementById('cancelUpload');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                this.cancelUpload();
            });
        }
    }

    initDragAndDrop() {
        const dropZone = document.querySelector('.upload-form');
        if (!dropZone) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, this.preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.remove('dragover');
            }, false);
        });

        dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            this.handleFileSelection(files);
        }, false);
    }

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    setupProgressTracking() {
        this.progressContainer = document.getElementById('uploadProgress');
        this.progressFill = document.getElementById('progressFill');
        this.progressText = document.getElementById('progressText');
    }

    handleFileSelection(files) {
        const fileArray = Array.from(files);
        
        // Validate files
        const validFiles = [];
        for (const file of fileArray) {
            const validation = this.validateFile(file);
            if (validation.valid) {
                validFiles.push(file);
            } else {
                showNotification(`${file.name}: ${validation.error}`, 'error');
            }
        }

        // Add to selected files
        this.selectedFiles.push(...validFiles);
        this.updateFilePreview();
        this.updateUploadButton();
    }

    validateFile(file) {
        // File size check
        const maxSize = 50 * 1024 * 1024; // 50MB
        if (file.size > maxSize) {
            return { valid: false, error: 'File size exceeds maximum allowed (50MB)' };
        }

        // File type check
        const allowedExtensions = ['txt', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 
                                 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'];
        const extension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedExtensions.includes(extension)) {
            return { valid: false, error: 'File type not allowed' };
        }

        // Dangerous extensions check
        const dangerousExtensions = ['exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'vbs', 'js', 'jar'];
        if (dangerousExtensions.includes(extension)) {
            return { valid: false, error: 'File type is potentially dangerous and not allowed' };
        }

        return { valid: true };
    }

    updateFilePreview() {
        const preview = document.getElementById('filePreview');
        const container = document.getElementById('selectedFiles');
        
        if (!preview || !container) return;

        if (this.selectedFiles.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        preview.innerHTML = '';

        this.selectedFiles.forEach((file, index) => {
            const fileDiv = document.createElement('div');
            fileDiv.className = 'file-preview-item';
            fileDiv.innerHTML = `
                <div class="file-icon">${this.getFileIcon(file.type)}</div>
                <div class="file-info">
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${formatFileSize(file.size)}</div>
                    <div class="upload-progress-individual" id="progress-${index}" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill-${index}"></div>
                        </div>
                        <div class="progress-text" id="progressText-${index}">0%</div>
                    </div>
                </div>
                <div class="file-actions">
                    <button type="button" class="btn btn-small btn-danger remove-file-btn" data-index="${index}">
                        Remove
                    </button>
                </div>
            `;
            preview.appendChild(fileDiv);
        });
    }

    getFileIcon(mimeType) {
        if (mimeType.startsWith('image/')) return '🖼️';
        if (mimeType.startsWith('video/')) return '🎥';
        if (mimeType.startsWith('audio/')) return '🎵';
        if (mimeType.includes('pdf')) return '📄';
        if (mimeType.includes('zip') || mimeType.includes('rar')) return '📦';
        if (mimeType.includes('word') || mimeType.includes('document')) return '📝';
        if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return '📊';
        if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return '📽️';
        return '📁';
    }

    removeFile(index) {
        this.selectedFiles.splice(index, 1);
        this.updateFilePreview();
        this.updateUploadButton();
        
        // Update file input
        const fileInput = document.getElementById('files');
        if (fileInput && this.selectedFiles.length === 0) {
            fileInput.value = '';
        }
    }

    updateUploadButton() {
        const uploadBtn = document.querySelector('.upload-form button[type="submit"]');
        if (uploadBtn) {
            uploadBtn.disabled = this.selectedFiles.length === 0 || this.isUploading;
            uploadBtn.textContent = this.isUploading ? 'Uploading...' : 
                                   this.selectedFiles.length === 0 ? 'Select Files First' : 
                                   `Upload ${this.selectedFiles.length} File(s)`;
        }
    }

    updateUploadDirectory(directory) {
        this.uploadDirectory = directory;
    }

    async startUpload() {
        if (this.selectedFiles.length === 0) {
            showNotification('No files selected', 'warning');
            return;
        }

        this.isUploading = true;
        this.updateUploadButton();
        this.showProgress();

        // Add files to upload queue
        this.uploadQueue = this.selectedFiles.map((file, index) => ({
            file,
            index,
            status: 'pending',
            progress: 0,
            chunks: this.calculateChunks(file),
            currentChunk: 0
        }));

        try {
            await this.processUploadQueue();
            this.handleUploadComplete();
        } catch (error) {
            this.handleUploadError(error);
        }
    }

    calculateChunks(file) {
        return Math.ceil(file.size / this.chunkSize);
    }

    async processUploadQueue() {
        const promises = [];
        
        while (this.uploadQueue.length > 0 && this.currentUploads < this.maxConcurrent) {
            const uploadItem = this.uploadQueue.shift();
            this.currentUploads++;
            
            const promise = this.uploadFile(uploadItem)
                .finally(() => {
                    this.currentUploads--;
                });
            
            promises.push(promise);
        }

        if (promises.length > 0) {
            await Promise.all(promises);
            
            // Process remaining files
            if (this.uploadQueue.length > 0) {
                await this.processUploadQueue();
            }
        }
    }

    async uploadFile(uploadItem) {
        const { file, index } = uploadItem;
        
        try {
            uploadItem.status = 'uploading';
            this.updateIndividualProgress(index, 0, 'Uploading...');

            if (file.size <= this.chunkSize) {
                // Small file - upload directly
                await this.uploadSmallFile(uploadItem);
            } else {
                // Large file - chunked upload
                await this.uploadLargeFile(uploadItem);
            }

            uploadItem.status = 'completed';
            this.updateIndividualProgress(index, 100, 'Complete');
            
        } catch (error) {
            uploadItem.status = 'error';
            this.updateIndividualProgress(index, 0, 'Error');
            throw error;
        }
    }

    async uploadSmallFile(uploadItem) {
        const { file, index } = uploadItem;
        const formData = new FormData();
        
        formData.append('files[]', file);
        formData.append('upload_dir', this.uploadDirectory || '');
        formData.append('csrf_token', this.getCSRFToken());

        const response = await fetch('api/upload.php', {
            method: 'POST',
            body: formData,
            onUploadProgress: (progressEvent) => {
                const progress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                this.updateIndividualProgress(index, progress, `${progress}%`);
                this.updateOverallProgress();
            }
        });

        if (!response.ok) {
            throw new Error(`Upload failed: ${response.statusText}`);
        }

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Upload failed');
        }
    }

    async uploadLargeFile(uploadItem) {
        const { file, index, chunks } = uploadItem;
        const uploadId = this.generateUploadId();
        
        for (let chunkIndex = 0; chunkIndex < chunks; chunkIndex++) {
            await this.uploadChunk(uploadItem, chunkIndex, uploadId);
            
            const progress = Math.round(((chunkIndex + 1) / chunks) * 100);
            this.updateIndividualProgress(index, progress, `${progress}%`);
            this.updateOverallProgress();
        }

        // Finalize upload
        await this.finalizeUpload(uploadId, file.name);
    }

    async uploadChunk(uploadItem, chunkIndex, uploadId) {
        const { file } = uploadItem;
        const start = chunkIndex * this.chunkSize;
        const end = Math.min(start + this.chunkSize, file.size);
        const chunk = file.slice(start, end);

        const formData = new FormData();
        formData.append('chunk', chunk);
        formData.append('chunk_index', chunkIndex);
        formData.append('total_chunks', uploadItem.chunks);
        formData.append('upload_id', uploadId);
        formData.append('filename', file.name);
        formData.append('upload_dir', this.uploadDirectory || '');
        formData.append('csrf_token', this.getCSRFToken());

        const response = await fetch('api/upload.php?chunked=1', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`Chunk upload failed: ${response.statusText}`);
        }

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Chunk upload failed');
        }
    }

    async finalizeUpload(uploadId, filename) {
        const response = await fetch('api/upload.php?action=finalize', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                upload_id: uploadId,
                filename: filename,
                upload_dir: this.uploadDirectory || '',
                csrf_token: this.getCSRFToken()
            })
        });

        if (!response.ok) {
            throw new Error(`Finalization failed: ${response.statusText}`);
        }

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Finalization failed');
        }
    }

    updateIndividualProgress(index, progress, text) {
        const progressContainer = document.getElementById(`progress-${index}`);
        const progressFill = document.getElementById(`progressFill-${index}`);
        const progressText = document.getElementById(`progressText-${index}`);

        if (progressContainer) {
            progressContainer.style.display = 'block';
        }
        
        if (progressFill) {
            progressFill.style.width = `${progress}%`;
        }
        
        if (progressText) {
            progressText.textContent = text;
        }
    }

    updateOverallProgress() {
        if (!this.progressContainer) return;

        const totalFiles = this.selectedFiles.length;
        const completedFiles = this.uploadQueue.filter(item => 
            item.status === 'completed'
        ).length;
        
        const progress = totalFiles > 0 ? Math.round((completedFiles / totalFiles) * 100) : 0;
        
        if (this.progressFill) {
            this.progressFill.style.width = `${progress}%`;
        }
        
        if (this.progressText) {
            this.progressText.textContent = `${completedFiles}/${totalFiles} files uploaded (${progress}%)`;
        }
    }

    showProgress() {
        if (this.progressContainer) {
            this.progressContainer.style.display = 'block';
        }
    }

    hideProgress() {
        if (this.progressContainer) {
            this.progressContainer.style.display = 'none';
        }
    }

    handleUploadComplete() {
        this.isUploading = false;
        this.updateUploadButton();
        
        const successCount = this.uploadQueue.filter(item => 
            item.status === 'completed'
        ).length;
        
        const errorCount = this.uploadQueue.filter(item => 
            item.status === 'error'
        ).length;

        if (successCount > 0) {
            showNotification(
                `${successCount} file(s) uploaded successfully${errorCount > 0 ? `, ${errorCount} failed` : ''}`, 
                errorCount > 0 ? 'warning' : 'success'
            );
        }

        if (errorCount === 0) {
            // Clear form on complete success
            setTimeout(() => {
                this.resetForm();
            }, 2000);
        }
    }

    handleUploadError(error) {
        this.isUploading = false;
        this.updateUploadButton();
        showNotification(`Upload error: ${error.message}`, 'error');
        console.error('Upload error:', error);
    }

    cancelUpload() {
        if (this.isUploading) {
            this.isUploading = false;
            this.uploadQueue.forEach(item => {
                if (item.status === 'uploading') {
                    item.status = 'cancelled';
                }
            });
            this.updateUploadButton();
            showNotification('Upload cancelled', 'info');
        }
    }

    resetForm() {
        this.selectedFiles = [];
        this.uploadQueue = [];
        this.updateFilePreview();
        this.updateUploadButton();
        this.hideProgress();
        
        const fileInput = document.getElementById('files');
        if (fileInput) {
            fileInput.value = '';
        }
    }

    generateUploadId() {
        return 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    getCSRFToken() {
        const token = document.querySelector('input[name="csrf_token"]') || 
                     document.querySelector('meta[name="csrf-token"]');
        return token ? token.value || token.content : '';
    }
}

// Paste upload functionality
class PasteUploader {
    constructor() {
        this.initPasteUpload();
    }

    initPasteUpload() {
        document.addEventListener('paste', (e) => {
            if (e.target.closest('.upload-form')) {
                this.handlePaste(e);
            }
        });
    }

    handlePaste(e) {
        const items = e.clipboardData.items;
        const files = [];

        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            
            if (item.kind === 'file') {
                const file = item.getAsFile();
                if (file) {
                    files.push(file);
                }
            }
        }

        if (files.length > 0) {
            e.preventDefault();
            if (window.fileUploader) {
                window.fileUploader.handleFileSelection(files);
            }
            showNotification(`${files.length} file(s) pasted`, 'info');
        }
    }
}

// Initialize uploaders
document.addEventListener('DOMContentLoaded', () => {
    window.fileUploader = new FileUploader();
    window.pasteUploader = new PasteUploader();
});

// Global functions for template access
window.removeFile = (index) => {
    if (window.fileUploader) {
        window.fileUploader.removeFile(index);
    }
};

window.startUpload = () => {
    if (window.fileUploader) {
        window.fileUploader.startUpload();
    }
};

window.cancelUpload = () => {
    if (window.fileUploader) {
        window.fileUploader.cancelUpload();
    }
};
