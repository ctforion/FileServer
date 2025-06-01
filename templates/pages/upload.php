<?php
/**
 * File Upload Page Template
 */
?>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">Upload Files</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/">Home</a></li>
                        <li class="breadcrumb-item active">Upload</li>
                    </ol>
                </nav>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
            </div>
        </div>
    </div>

    <!-- Upload Area -->
    <div class="row">
        <div class="col-12">
            <div class="card file-upload-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cloud-upload-alt text-primary"></i>
                        Upload Files
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Upload Zone -->
                    <div id="upload-zone" class="upload-zone">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <h4>Drag and drop files here</h4>
                        <p class="text-muted">or click to browse files</p>
                        <button type="button" class="btn btn-primary" id="browse-files">
                            <i class="fas fa-file-plus"></i> Browse Files
                        </button>
                        <input type="file" id="file-input" multiple style="display: none;">
                    </div>

                    <!-- Upload Options -->
                    <div class="upload-options mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="compress-files" checked>
                                    <label class="form-check-label" for="compress-files">
                                        Compress files automatically
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="extract-metadata" checked>
                                    <label class="form-check-label" for="extract-metadata">
                                        Extract metadata
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="generate-thumbnails" checked>
                                    <label class="form-check-label" for="generate-thumbnails">
                                        Generate thumbnails for images
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="upload-folder">Upload to folder</label>
                                    <select class="form-control" id="upload-folder">
                                        <option value="">Root folder</option>
                                        <option value="documents">Documents</option>
                                        <option value="images">Images</option>
                                        <option value="videos">Videos</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="visibility">Visibility</label>
                                    <select class="form-control" id="visibility">
                                        <option value="private">Private</option>
                                        <option value="public">Public</option>
                                        <option value="shared">Shared with specific users</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Queue -->
                    <div id="upload-queue" class="upload-queue mt-4" style="display: none;">
                        <h6>Upload Queue</h6>
                        <div id="upload-list" class="upload-list"></div>
                        
                        <div class="upload-controls mt-3">
                            <button type="button" class="btn btn-success" id="start-upload">
                                <i class="fas fa-play"></i> Start Upload
                            </button>
                            <button type="button" class="btn btn-warning" id="pause-upload" style="display: none;">
                                <i class="fas fa-pause"></i> Pause
                            </button>
                            <button type="button" class="btn btn-danger" id="clear-queue">
                                <i class="fas fa-trash"></i> Clear Queue
                            </button>
                        </div>
                        
                        <!-- Overall Progress -->
                        <div class="overall-progress mt-3">
                            <div class="d-flex justify-content-between">
                                <span>Overall Progress</span>
                                <span id="overall-progress-text">0%</span>
                            </div>
                            <div class="progress">
                                <div id="overall-progress-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Limits Info -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-info-circle text-info"></i>
                        Upload Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="upload-info-item">
                                <div class="info-value" id="max-file-size">Loading...</div>
                                <div class="info-label">Max File Size</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="upload-info-item">
                                <div class="info-value" id="allowed-types">Loading...</div>
                                <div class="info-label">Allowed Types</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="upload-info-item">
                                <div class="info-value" id="storage-used">Loading...</div>
                                <div class="info-label">Storage Used</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="upload-info-item">
                                <div class="info-value" id="storage-available">Loading...</div>
                                <div class="info-label">Storage Available</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Uploads -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-clock text-success"></i>
                        Recent Uploads
                    </h6>
                </div>
                <div class="card-body">
                    <div id="recent-uploads" class="recent-uploads">
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-upload fa-2x mb-2"></i>
                            <p>No recent uploads. Start uploading files!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadZone = document.getElementById('upload-zone');
    const fileInput = document.getElementById('file-input');
    const browseBtn = document.getElementById('browse-files');
    const uploadQueue = document.getElementById('upload-queue');
    const uploadList = document.getElementById('upload-list');
    const startUploadBtn = document.getElementById('start-upload');
    const pauseUploadBtn = document.getElementById('pause-upload');
    const clearQueueBtn = document.getElementById('clear-queue');
    
    let selectedFiles = [];
    let uploadInProgress = false;
    
    // Load upload limits and info
    loadUploadInfo();
    loadRecentUploads();
    
    // File input handling
    browseBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', handleFiles);
    
    // Drag and drop handling
    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('drag-over');
    });
    
    uploadZone.addEventListener('dragleave', () => {
        uploadZone.classList.remove('drag-over');
    });
    
    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('drag-over');
        handleFiles({ target: { files: e.dataTransfer.files } });
    });
    
    // Upload controls
    startUploadBtn.addEventListener('click', startUpload);
    pauseUploadBtn.addEventListener('click', pauseUpload);
    clearQueueBtn.addEventListener('click', clearQueue);
    
    function handleFiles(event) {
        const files = Array.from(event.target.files);
        
        files.forEach(file => {
            if (!selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                selectedFiles.push(file);
                addFileToQueue(file);
            }
        });
        
        if (selectedFiles.length > 0) {
            uploadQueue.style.display = 'block';
        }
    }
    
    function addFileToQueue(file) {
        const fileItem = document.createElement('div');
        fileItem.className = 'upload-item';
        fileItem.innerHTML = `
            <div class="upload-item-info">
                <div class="upload-item-name">${file.name}</div>
                <div class="upload-item-size">${formatFileSize(file.size)}</div>
            </div>
            <div class="upload-item-progress">
                <div class="progress">
                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                </div>
            </div>
            <div class="upload-item-actions">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile('${file.name}')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        uploadList.appendChild(fileItem);
    }
    
    function removeFile(fileName) {
        selectedFiles = selectedFiles.filter(f => f.name !== fileName);
        const items = uploadList.children;
        
        for (let item of items) {
            if (item.querySelector('.upload-item-name').textContent === fileName) {
                uploadList.removeChild(item);
                break;
            }
        }
        
        if (selectedFiles.length === 0) {
            uploadQueue.style.display = 'none';
        }
    }
    
    function clearQueue() {
        selectedFiles = [];
        uploadList.innerHTML = '';
        uploadQueue.style.display = 'none';
    }
    
    async function startUpload() {
        if (uploadInProgress || selectedFiles.length === 0) return;
        
        uploadInProgress = true;
        startUploadBtn.style.display = 'none';
        pauseUploadBtn.style.display = 'inline-block';
        
        const options = {
            compress: document.getElementById('compress-files').checked,
            extractMetadata: document.getElementById('extract-metadata').checked,
            generateThumbnails: document.getElementById('generate-thumbnails').checked,
            folder: document.getElementById('upload-folder').value,
            visibility: document.getElementById('visibility').value
        };
        
        let completed = 0;
        const total = selectedFiles.length;
        
        for (let i = 0; i < selectedFiles.length; i++) {
            if (!uploadInProgress) break; // Paused
            
            const file = selectedFiles[i];
            const progressBar = uploadList.children[i].querySelector('.progress-bar');
            
            try {
                await uploadFile(file, progressBar, options);
                completed++;
                
                // Update overall progress
                const overallProgress = (completed / total) * 100;
                document.getElementById('overall-progress-bar').style.width = overallProgress + '%';
                document.getElementById('overall-progress-text').textContent = Math.round(overallProgress) + '%';
                
            } catch (error) {
                console.error('Upload failed for file:', file.name, error);
                progressBar.classList.add('bg-danger');
            }
        }
        
        if (uploadInProgress) {
            // All uploads completed
            uploadInProgress = false;
            startUploadBtn.style.display = 'inline-block';
            pauseUploadBtn.style.display = 'none';
            
            // Refresh recent uploads
            loadRecentUploads();
            
            // Show success message
            showAlert('Upload completed successfully!', 'success');
        }
    }
    
    function pauseUpload() {
        uploadInProgress = false;
        startUploadBtn.style.display = 'inline-block';
        pauseUploadBtn.style.display = 'none';
    }
    
    async function uploadFile(file, progressBar, options) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('options', JSON.stringify(options));
        
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const progress = (e.loaded / e.total) * 100;
                    progressBar.style.width = progress + '%';
                }
            });
            
            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    progressBar.classList.add('bg-success');
                    resolve(JSON.parse(xhr.responseText));
                } else {
                    reject(new Error('Upload failed'));
                }
            });
            
            xhr.addEventListener('error', () => {
                reject(new Error('Network error'));
            });
            
            xhr.open('POST', '/api/files/upload');
            xhr.setRequestHeader('Authorization', 'Bearer ' + getAuthToken());
            xhr.send(formData);
        });
    }
    
    async function loadUploadInfo() {
        try {
            const response = await fetch('/api/system/info', {
                headers: {
                    'Authorization': 'Bearer ' + getAuthToken()
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                document.getElementById('max-file-size').textContent = data.data.max_upload_size || 'Unknown';
                document.getElementById('allowed-types').textContent = 'All types';
            }
        } catch (error) {
            console.error('Failed to load upload info:', error);
        }
        
        // Load user storage info
        try {
            const response = await fetch('/api/users/me', {
                headers: {
                    'Authorization': 'Bearer ' + getAuthToken()
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                const used = data.data.storage_used || 0;
                const quota = data.data.storage_quota || 0;
                
                document.getElementById('storage-used').textContent = formatFileSize(used);
                document.getElementById('storage-available').textContent = quota > 0 ? formatFileSize(quota - used) : 'Unlimited';
            }
        } catch (error) {
            console.error('Failed to load storage info:', error);
        }
    }
    
    async function loadRecentUploads() {
        try {
            const response = await fetch('/api/files?limit=5&sort=created_at&order=desc', {
                headers: {
                    'Authorization': 'Bearer ' + getAuthToken()
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                const container = document.getElementById('recent-uploads');
                
                if (data.data && data.data.length > 0) {
                    container.innerHTML = data.data.map(file => `
                        <div class="recent-upload-item">
                            <div class="file-icon">
                                <i class="fas fa-file"></i>
                            </div>
                            <div class="file-info">
                                <div class="file-name">${file.filename}</div>
                                <div class="file-meta">${formatFileSize(file.size)} • ${formatDate(file.created_at)}</div>
                            </div>
                            <div class="file-actions">
                                <a href="/api/files/${file.id}/download" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-upload fa-2x mb-2"></i>
                            <p>No recent uploads. Start uploading files!</p>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Failed to load recent uploads:', error);
        }
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
    }
    
    function getAuthToken() {
        return localStorage.getItem('auth_token') || '';
    }
    
    function showAlert(message, type = 'info') {
        // Create and show alert
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.querySelector('.content-wrapper').insertBefore(alert, document.querySelector('.page-header'));
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
    
    // Make removeFile global
    window.removeFile = removeFile;
});
</script>
