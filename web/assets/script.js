// File Storage Server JavaScript
class FileStorageManager {
    constructor() {
        this.currentDirectory = 'private';
        this.currentPage = 1;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadFiles();
        this.setupDragAndDrop();
    }

    setupEventListeners() {
        // Tab switching
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchTab(tab.dataset.directory);
            });
        });

        // File upload form
        const uploadForm = document.getElementById('uploadForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.uploadFile();
            });
        }

        // File input change
        const fileInput = document.getElementById('fileInput');
        if (fileInput) {
            fileInput.addEventListener('change', () => {
                this.uploadFile();
            });
        }

        // Upload zone click
        const uploadZone = document.getElementById('uploadZone');
        if (uploadZone) {
            uploadZone.addEventListener('click', () => {
                fileInput.click();
            });
        }
    }

    setupDragAndDrop() {
        const uploadZone = document.getElementById('uploadZone');
        if (!uploadZone) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, this.preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => {
                uploadZone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => {
                uploadZone.classList.remove('dragover');
            }, false);
        });

        uploadZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.handleFileUpload(files[0]);
            }
        }, false);
    }

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    switchTab(directory) {
        // Update active tab
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelector(`[data-directory="${directory}"]`).classList.add('active');

        // Update current directory and reload files
        this.currentDirectory = directory;
        this.currentPage = 1;
        this.loadFiles();
    }

    uploadFile() {
        const fileInput = document.getElementById('fileInput');
        const file = fileInput.files[0];
        
        if (!file) return;

        this.handleFileUpload(file);
    }

    handleFileUpload(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('directory', this.currentDirectory);

        this.showProgress(true);
        this.showMessage('Uploading file...', 'info');

        fetch('api/upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            this.showProgress(false);
            
            if (data.success) {
                this.showMessage('File uploaded successfully!', 'success');
                this.loadFiles(); // Reload file list
                document.getElementById('fileInput').value = ''; // Clear input
            } else {
                this.showMessage(data.error || 'Upload failed', 'error');
            }
        })
        .catch(error => {
            this.showProgress(false);
            this.showMessage('Upload failed: ' + error.message, 'error');
        });
    }

    loadFiles() {
        const url = `api/list.php?dir=${this.currentDirectory}&page=${this.currentPage}`;
        
        fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.renderFileList(data.files);
                this.renderPagination(data.pagination);
            } else {
                this.showMessage(data.error || 'Failed to load files', 'error');
            }
        })
        .catch(error => {
            this.showMessage('Failed to load files: ' + error.message, 'error');
        });
    }

    renderFileList(files) {
        const fileListContainer = document.getElementById('fileList');
        if (!fileListContainer) return;

        if (files.length === 0) {
            fileListContainer.innerHTML = '<div class="file-item"><div class="file-info">No files found</div></div>';
            return;
        }

        const html = files.map(file => `
            <div class="file-item">
                <div class="file-icon">${this.getFileIcon(file.name)}</div>
                <div class="file-info">
                    <div class="file-name">${this.escapeHtml(file.name)}</div>
                    <div class="file-meta">${this.formatFileSize(file.size)} • ${file.modified}</div>
                </div>
                <div class="file-actions">
                    <a href="api/download.php?file=${encodeURIComponent(file.path)}" 
                       class="btn btn-primary btn-small" target="_blank">Download</a>
                    ${this.currentDirectory !== 'public' ? `
                    <button onclick="fileManager.deleteFile('${file.path}')" 
                            class="btn btn-danger btn-small">Delete</button>
                    ` : ''}
                </div>
            </div>
        `).join('');

        fileListContainer.innerHTML = html;
    }

    renderPagination(pagination) {
        const paginationContainer = document.getElementById('pagination');
        if (!paginationContainer) return;

        if (pagination.pages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        let html = '';
        
        // Previous button
        if (pagination.page > 1) {
            html += `<button onclick="fileManager.changePage(${pagination.page - 1})" class="btn btn-secondary">Previous</button>`;
        }

        // Page numbers
        for (let i = 1; i <= pagination.pages; i++) {
            if (i === pagination.page) {
                html += `<button class="btn btn-primary">${i}</button>`;
            } else {
                html += `<button onclick="fileManager.changePage(${i})" class="btn btn-secondary">${i}</button>`;
            }
        }

        // Next button
        if (pagination.page < pagination.pages) {
            html += `<button onclick="fileManager.changePage(${pagination.page + 1})" class="btn btn-secondary">Next</button>`;
        }

        paginationContainer.innerHTML = html;
    }

    changePage(page) {
        this.currentPage = page;
        this.loadFiles();
    }

    deleteFile(filepath) {
        if (!confirm('Are you sure you want to delete this file?')) {
            return;
        }

        fetch(`api/delete.php?file=${encodeURIComponent(filepath)}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showMessage('File deleted successfully!', 'success');
                this.loadFiles(); // Reload file list
            } else {
                this.showMessage(data.error || 'Delete failed', 'error');
            }
        })
        .catch(error => {
            this.showMessage('Delete failed: ' + error.message, 'error');
        });
    }

    getFileIcon(filename) {
        const extension = filename.split('.').pop().toLowerCase();
        
        const icons = {
            'pdf': '📄',
            'doc': '📄',
            'docx': '📄',
            'txt': '📄',
            'jpg': '🖼️',
            'jpeg': '🖼️',
            'png': '🖼️',
            'gif': '🖼️',
            'mp4': '🎥',
            'mp3': '🎵',
            'zip': '📦',
            'rar': '📦',
            'xlsx': '📊',
            'pptx': '📊'
        };

        return icons[extension] || '📎';
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showMessage(message, type) {
        // Remove existing messages
        document.querySelectorAll('.message').forEach(el => el.remove());

        // Create new message
        const messageEl = document.createElement('div');
        messageEl.className = `message ${type}-message`;
        messageEl.textContent = message;

        // Insert at top of container
        const container = document.querySelector('.container');
        if (container) {
            container.insertBefore(messageEl, container.firstChild);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                messageEl.remove();
            }, 5000);
        }
    }

    showProgress(show) {
        const progress = document.getElementById('uploadProgress');
        if (progress) {
            progress.classList.toggle('hidden', !show);
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.fileManager = new FileStorageManager();
});

// Auto-refresh file list every 30 seconds
setInterval(() => {
    if (window.fileManager) {
        window.fileManager.loadFiles();
    }
}, 30000);
