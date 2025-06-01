<?php
/**
 * Files Management Page Template
 */
?>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">My Files</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/">Home</a></li>
                        <li class="breadcrumb-item active">Files</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="/upload" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Upload Files
                </a>
                <button type="button" class="btn btn-outline-secondary" id="view-toggle">
                    <i class="fas fa-th-large"></i> Grid View
                </button>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="search-files">Search Files</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="search-files" placeholder="Search by filename, content, or tags...">
                                    <button class="btn btn-outline-secondary" type="button" id="search-btn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="filter-type">File Type</label>
                                <select class="form-control" id="filter-type">
                                    <option value="">All Types</option>
                                    <option value="image">Images</option>
                                    <option value="document">Documents</option>
                                    <option value="video">Videos</option>
                                    <option value="audio">Audio</option>
                                    <option value="archive">Archives</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="filter-folder">Folder</label>
                                <select class="form-control" id="filter-folder">
                                    <option value="">All Folders</option>
                                    <option value="documents">Documents</option>
                                    <option value="images">Images</option>
                                    <option value="videos">Videos</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="sort-by">Sort By</label>
                                <select class="form-control" id="sort-by">
                                    <option value="created_at">Date Uploaded</option>
                                    <option value="filename">Name</option>
                                    <option value="size">Size</option>
                                    <option value="downloads">Downloads</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="sort-order">Order</label>
                                <select class="form-control" id="sort-order">
                                    <option value="desc">Newest First</option>
                                    <option value="asc">Oldest First</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- File Actions Bar -->
    <div class="files-toolbar mb-3" id="files-toolbar" style="display: none;">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span id="selected-count">0</span> file(s) selected
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary" id="download-selected">
                    <i class="fas fa-download"></i> Download
                </button>
                <button type="button" class="btn btn-outline-success" id="share-selected">
                    <i class="fas fa-share"></i> Share
                </button>
                <button type="button" class="btn btn-outline-warning" id="move-selected">
                    <i class="fas fa-folder-open"></i> Move
                </button>
                <button type="button" class="btn btn-outline-danger" id="delete-selected">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Files Grid/List -->
    <div class="files-container">
        <div id="files-loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading files...</p>
        </div>
        
        <div id="files-grid" class="files-grid" style="display: none;">
            <!-- Files will be populated here -->
        </div>
        
        <div id="files-list" class="files-list" style="display: none;">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="40">
                                <input type="checkbox" id="select-all" class="form-check-input">
                            </th>
                            <th>Name</th>
                            <th>Size</th>
                            <th>Type</th>
                            <th>Modified</th>
                            <th>Downloads</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="files-table-body">
                        <!-- Files will be populated here -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="files-empty" class="text-center py-5" style="display: none;">
            <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
            <h4>No files found</h4>
            <p class="text-muted">Upload some files to get started!</p>
            <a href="/upload" class="btn btn-primary">
                <i class="fas fa-plus"></i> Upload Files
            </a>
        </div>
    </div>

    <!-- Pagination -->
    <div id="pagination-container" class="d-flex justify-content-center mt-4">
        <!-- Pagination will be populated here -->
    </div>
</div>

<!-- File Preview Modal -->
<div class="modal fade" id="file-preview-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="preview-filename">File Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="preview-content" class="text-center">
                    <!-- Preview content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="download-preview">
                    <i class="fas fa-download"></i> Download
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div class="modal fade" id="share-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="share-with">Share with users (email addresses)</label>
                    <input type="text" class="form-control" id="share-with" placeholder="user1@example.com, user2@example.com">
                    <small class="form-text text-muted">Separate multiple emails with commas</small>
                </div>
                <div class="form-group">
                    <label for="share-permissions">Permissions</label>
                    <select class="form-control" id="share-permissions">
                        <option value="read">View only</option>
                        <option value="download">View and download</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="share-expiry">Expires</label>
                    <select class="form-control" id="share-expiry">
                        <option value="">Never</option>
                        <option value="1">1 day</option>
                        <option value="7">1 week</option>
                        <option value="30">1 month</option>
                        <option value="90">3 months</option>
                    </select>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="share-public">
                    <label class="form-check-label" for="share-public">
                        Make publicly accessible (generate share link)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="create-share">Share</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Move Files Modal -->
<div class="modal fade" id="move-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Move Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="move-folder">Move to folder</label>
                    <select class="form-control" id="move-folder">
                        <option value="">Root folder</option>
                        <option value="documents">Documents</option>
                        <option value="images">Images</option>
                        <option value="videos">Videos</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new-folder">Or create new folder</label>
                    <input type="text" class="form-control" id="new-folder" placeholder="New folder name">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="confirm-move">Move</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filesGrid = document.getElementById('files-grid');
    const filesList = document.getElementById('files-list');
    const filesLoading = document.getElementById('files-loading');
    const filesEmpty = document.getElementById('files-empty');
    const filesToolbar = document.getElementById('files-toolbar');
    const selectedCount = document.getElementById('selected-count');
    const viewToggle = document.getElementById('view-toggle');
    
    let currentView = 'grid';
    let selectedFiles = new Set();
    let allFiles = [];
    let currentPage = 1;
    const itemsPerPage = 20;
    
    // Initialize
    loadFiles();
    
    // View toggle
    viewToggle.addEventListener('click', toggleView);
    
    // Search and filters
    document.getElementById('search-btn').addEventListener('click', () => loadFiles());
    document.getElementById('search-files').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') loadFiles();
    });
    
    document.getElementById('filter-type').addEventListener('change', () => loadFiles());
    document.getElementById('filter-folder').addEventListener('change', () => loadFiles());
    document.getElementById('sort-by').addEventListener('change', () => loadFiles());
    document.getElementById('sort-order').addEventListener('change', () => loadFiles());
    
    // File actions
    document.getElementById('select-all').addEventListener('change', toggleSelectAll);
    document.getElementById('download-selected').addEventListener('click', downloadSelected);
    document.getElementById('share-selected').addEventListener('click', shareSelected);
    document.getElementById('move-selected').addEventListener('click', moveSelected);
    document.getElementById('delete-selected').addEventListener('click', deleteSelected);
    
    // Modal actions
    document.getElementById('create-share').addEventListener('click', createShare);
    document.getElementById('confirm-move').addEventListener('click', confirmMove);
    
    function toggleView() {
        if (currentView === 'grid') {
            currentView = 'list';
            filesGrid.style.display = 'none';
            filesList.style.display = 'block';
            viewToggle.innerHTML = '<i class="fas fa-th-large"></i> Grid View';
        } else {
            currentView = 'grid';
            filesGrid.style.display = 'block';
            filesList.style.display = 'none';
            viewToggle.innerHTML = '<i class="fas fa-list"></i> List View';
        }
    }
    
    async function loadFiles() {
        showLoading();
        
        try {
            const params = new URLSearchParams({
                page: currentPage,
                limit: itemsPerPage,
                search: document.getElementById('search-files').value,
                type: document.getElementById('filter-type').value,
                folder: document.getElementById('filter-folder').value,
                sort: document.getElementById('sort-by').value,
                order: document.getElementById('sort-order').value
            });
            
            const response = await fetch(`/api/files?${params}`, {
                headers: {
                    'Authorization': 'Bearer ' + getAuthToken()
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                allFiles = data.data || [];
                displayFiles(allFiles);
                displayPagination(data.pagination);
            } else {
                showError('Failed to load files');
            }
        } catch (error) {
            console.error('Error loading files:', error);
            showError('Failed to load files');
        }
    }
    
    function displayFiles(files) {
        filesLoading.style.display = 'none';
        
        if (files.length === 0) {
            filesEmpty.style.display = 'block';
            filesGrid.style.display = 'none';
            filesList.style.display = 'none';
            return;
        }
        
        filesEmpty.style.display = 'none';
        
        if (currentView === 'grid') {
            displayFilesGrid(files);
        } else {
            displayFilesList(files);
        }
    }
    
    function displayFilesGrid(files) {
        filesGrid.innerHTML = files.map(file => `
            <div class="file-card" data-file-id="${file.id}">
                <div class="file-select">
                    <input type="checkbox" class="form-check-input file-checkbox" value="${file.id}">
                </div>
                <div class="file-thumbnail" onclick="previewFile(${file.id})">
                    ${getFileIcon(file.mime_type, file.id)}
                </div>
                <div class="file-info">
                    <div class="file-name" title="${file.filename}">${file.filename}</div>
                    <div class="file-meta">
                        ${formatFileSize(file.size)} • ${formatDate(file.created_at)}
                    </div>
                </div>
                <div class="file-actions">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="previewFile(${file.id})">
                                <i class="fas fa-eye"></i> Preview
                            </a></li>
                            <li><a class="dropdown-item" href="/api/files/${file.id}/download">
                                <i class="fas fa-download"></i> Download
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="shareFile(${file.id})">
                                <i class="fas fa-share"></i> Share
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteFile(${file.id})">
                                <i class="fas fa-trash"></i> Delete
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        `).join('');
        
        filesGrid.style.display = 'block';
        filesList.style.display = 'none';
        
        // Add event listeners for checkboxes
        addCheckboxListeners();
    }
    
    function displayFilesList(files) {
        const tbody = document.getElementById('files-table-body');
        tbody.innerHTML = files.map(file => `
            <tr data-file-id="${file.id}">
                <td>
                    <input type="checkbox" class="form-check-input file-checkbox" value="${file.id}">
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="file-icon-small me-2">
                            ${getFileIcon(file.mime_type)}
                        </div>
                        <span class="file-name" onclick="previewFile(${file.id})" style="cursor: pointer;">
                            ${file.filename}
                        </span>
                    </div>
                </td>
                <td>${formatFileSize(file.size)}</td>
                <td>${file.mime_type}</td>
                <td>${formatDate(file.created_at)}</td>
                <td>${file.download_count || 0}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="previewFile(${file.id})" title="Preview">
                            <i class="fas fa-eye"></i>
                        </button>
                        <a class="btn btn-outline-success" href="/api/files/${file.id}/download" title="Download">
                            <i class="fas fa-download"></i>
                        </a>
                        <button class="btn btn-outline-warning" onclick="shareFile(${file.id})" title="Share">
                            <i class="fas fa-share"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteFile(${file.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
        
        filesGrid.style.display = 'none';
        filesList.style.display = 'block';
        
        // Add event listeners for checkboxes
        addCheckboxListeners();
    }
    
    function addCheckboxListeners() {
        document.querySelectorAll('.file-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelection);
        });
    }
    
    function updateSelection() {
        selectedFiles.clear();
        document.querySelectorAll('.file-checkbox:checked').forEach(checkbox => {
            selectedFiles.add(parseInt(checkbox.value));
        });
        
        selectedCount.textContent = selectedFiles.size;
        filesToolbar.style.display = selectedFiles.size > 0 ? 'block' : 'none';
        
        // Update select all checkbox
        const allCheckboxes = document.querySelectorAll('.file-checkbox');
        const checkedCheckboxes = document.querySelectorAll('.file-checkbox:checked');
        const selectAllCheckbox = document.getElementById('select-all');
        
        if (checkedCheckboxes.length === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (checkedCheckboxes.length === allCheckboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
    }
    
    function toggleSelectAll() {
        const selectAll = document.getElementById('select-all').checked;
        document.querySelectorAll('.file-checkbox').forEach(checkbox => {
            checkbox.checked = selectAll;
        });
        updateSelection();
    }
    
    function getFileIcon(mimeType, fileId = null) {
        const type = mimeType.split('/')[0];
        let icon = 'fas fa-file';
        
        switch (type) {
            case 'image':
                if (fileId) {
                    return `<img src="/api/files/${fileId}/thumbnail" alt="Thumbnail" class="file-thumbnail-img">`;
                }
                icon = 'fas fa-file-image';
                break;
            case 'video':
                icon = 'fas fa-file-video';
                break;
            case 'audio':
                icon = 'fas fa-file-audio';
                break;
            case 'application':
                if (mimeType.includes('pdf')) {
                    icon = 'fas fa-file-pdf';
                } else if (mimeType.includes('zip') || mimeType.includes('archive')) {
                    icon = 'fas fa-file-archive';
                } else if (mimeType.includes('word')) {
                    icon = 'fas fa-file-word';
                } else if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) {
                    icon = 'fas fa-file-excel';
                } else if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) {
                    icon = 'fas fa-file-powerpoint';
                }
                break;
            case 'text':
                icon = 'fas fa-file-alt';
                break;
        }
        
        return `<i class="${icon}"></i>`;
    }
    
    function showLoading() {
        filesLoading.style.display = 'block';
        filesGrid.style.display = 'none';
        filesList.style.display = 'none';
        filesEmpty.style.display = 'none';
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
    
    function showError(message) {
        // Show error notification
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.content-wrapper').insertBefore(alert, document.querySelector('.page-header'));
    }
    
    // Global functions for file actions
    window.previewFile = function(fileId) {
        // Implement file preview
        console.log('Preview file:', fileId);
    };
    
    window.shareFile = function(fileId) {
        selectedFiles.clear();
        selectedFiles.add(fileId);
        shareSelected();
    };
    
    window.deleteFile = function(fileId) {
        if (confirm('Are you sure you want to delete this file?')) {
            // Implement file deletion
            console.log('Delete file:', fileId);
        }
    };
    
    function downloadSelected() {
        if (selectedFiles.size === 0) return;
        
        if (selectedFiles.size === 1) {
            const fileId = Array.from(selectedFiles)[0];
            window.open(`/api/files/${fileId}/download`);
        } else {
            // Download multiple files as zip
            console.log('Download multiple files:', Array.from(selectedFiles));
        }
    }
    
    function shareSelected() {
        if (selectedFiles.size === 0) return;
        
        const modal = new bootstrap.Modal(document.getElementById('share-modal'));
        modal.show();
    }
    
    function moveSelected() {
        if (selectedFiles.size === 0) return;
        
        const modal = new bootstrap.Modal(document.getElementById('move-modal'));
        modal.show();
    }
    
    function deleteSelected() {
        if (selectedFiles.size === 0) return;
        
        if (confirm(`Are you sure you want to delete ${selectedFiles.size} file(s)?`)) {
            // Implement bulk deletion
            console.log('Delete files:', Array.from(selectedFiles));
        }
    }
    
    function createShare() {
        // Implement sharing functionality
        console.log('Create share for files:', Array.from(selectedFiles));
    }
    
    function confirmMove() {
        // Implement move functionality
        console.log('Move files:', Array.from(selectedFiles));
    }
    
    function displayPagination(pagination) {
        if (!pagination || pagination.pages <= 1) {
            document.getElementById('pagination-container').innerHTML = '';
            return;
        }
        
        let paginationHtml = '<nav><ul class="pagination">';
        
        // Previous button
        if (pagination.has_prev) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${pagination.page - 1})">Previous</a></li>`;
        }
        
        // Page numbers
        for (let i = 1; i <= pagination.pages; i++) {
            if (i === pagination.page) {
                paginationHtml += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
            } else {
                paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
            }
        }
        
        // Next button
        if (pagination.has_next) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${pagination.page + 1})">Next</a></li>`;
        }
        
        paginationHtml += '</ul></nav>';
        document.getElementById('pagination-container').innerHTML = paginationHtml;
    }
    
    window.changePage = function(page) {
        currentPage = page;
        loadFiles();
    };
});
</script>
