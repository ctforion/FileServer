/**
 * Files Page JavaScript
 * Handles file management, viewing, and operations
 */

// Extend the main app with files functionality
FileServerApp.prototype.initFilesPage = function() {
    this.currentView = localStorage.getItem('files_view') || 'grid';
    this.currentSort = localStorage.getItem('files_sort') || 'name_asc';
    this.currentFilter = {};
    this.selectedFiles = new Set();
    this.currentPath = this.getCurrentPath();
    
    this.setupViewToggle();
    this.setupSortControls();
    this.setupFilterControls();
    this.setupSearchBox();
    this.setupFileSelection();
    this.setupFileActions();
    this.setupContextMenu();
    this.setupKeyboardShortcuts();
    this.setupInfiniteScroll();
    this.loadFiles();
};

/**
 * Get current path from URL
 */
FileServerApp.prototype.getCurrentPath = function() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('path') || '/';
};

/**
 * Setup view toggle (grid/list)
 */
FileServerApp.prototype.setupViewToggle = function() {
    const viewToggle = document.querySelectorAll('[data-view]');
    viewToggle.forEach(button => {
        button.addEventListener('click', (e) => {
            const view = e.target.closest('[data-view]').dataset.view;
            this.setView(view);
        });
    });
    
    this.updateViewButtons();
};

/**
 * Set current view
 */
FileServerApp.prototype.setView = function(view) {
    this.currentView = view;
    localStorage.setItem('files_view', view);
    
    const filesContainer = document.getElementById('filesContainer');
    if (filesContainer) {
        filesContainer.className = `files-container files-${view}`;
    }
    
    this.updateViewButtons();
    this.renderFiles();
};

/**
 * Update view toggle buttons
 */
FileServerApp.prototype.updateViewButtons = function() {
    const viewButtons = document.querySelectorAll('[data-view]');
    viewButtons.forEach(button => {
        const isActive = button.dataset.view === this.currentView;
        button.classList.toggle('active', isActive);
    });
};

/**
 * Setup sort controls
 */
FileServerApp.prototype.setupSortControls = function() {
    const sortSelect = document.getElementById('sortSelect');
    if (sortSelect) {
        sortSelect.value = this.currentSort;
        sortSelect.addEventListener('change', (e) => {
            this.setSort(e.target.value);
        });
    }
};

/**
 * Set current sort
 */
FileServerApp.prototype.setSort = function(sort) {
    this.currentSort = sort;
    localStorage.setItem('files_sort', sort);
    this.loadFiles();
};

/**
 * Setup filter controls
 */
FileServerApp.prototype.setupFilterControls = function() {
    // File type filter
    const typeFilter = document.getElementById('typeFilter');
    if (typeFilter) {
        typeFilter.addEventListener('change', (e) => {
            this.setFilter('type', e.target.value);
        });
    }

    // Date range filter
    const dateFromFilter = document.getElementById('dateFromFilter');
    const dateToFilter = document.getElementById('dateToFilter');
    
    if (dateFromFilter) {
        dateFromFilter.addEventListener('change', (e) => {
            this.setFilter('date_from', e.target.value);
        });
    }
    
    if (dateToFilter) {
        dateToFilter.addEventListener('change', (e) => {
            this.setFilter('date_to', e.target.value);
        });
    }

    // Size filter
    const sizeFromFilter = document.getElementById('sizeFromFilter');
    const sizeToFilter = document.getElementById('sizeToFilter');
    
    if (sizeFromFilter) {
        sizeFromFilter.addEventListener('change', (e) => {
            this.setFilter('size_from', e.target.value);
        });
    }
    
    if (sizeToFilter) {
        sizeToFilter.addEventListener('change', (e) => {
            this.setFilter('size_to', e.target.value);
        });
    }

    // Owner filter
    const ownerFilter = document.getElementById('ownerFilter');
    if (ownerFilter) {
        ownerFilter.addEventListener('change', (e) => {
            this.setFilter('owner', e.target.value);
        });
    }

    // Clear filters button
    const clearFiltersBtn = document.getElementById('clearFilters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            this.clearFilters();
        });
    }
};

/**
 * Set filter value
 */
FileServerApp.prototype.setFilter = function(key, value) {
    if (value) {
        this.currentFilter[key] = value;
    } else {
        delete this.currentFilter[key];
    }
    this.loadFiles();
};

/**
 * Clear all filters
 */
FileServerApp.prototype.clearFilters = function() {
    this.currentFilter = {};
    
    // Reset filter inputs
    document.getElementById('typeFilter').value = '';
    document.getElementById('dateFromFilter').value = '';
    document.getElementById('dateToFilter').value = '';
    document.getElementById('sizeFromFilter').value = '';
    document.getElementById('sizeToFilter').value = '';
    document.getElementById('ownerFilter').value = '';
    
    this.loadFiles();
};

/**
 * Setup search box
 */
FileServerApp.prototype.setupSearchBox = function() {
    const searchBox = document.getElementById('fileSearchBox');
    if (!searchBox) return;

    let searchTimeout;
    searchBox.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            this.setFilter('search', e.target.value);
        }, 300);
    });

    // Clear search button
    const clearSearchBtn = document.getElementById('clearSearch');
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', () => {
            searchBox.value = '';
            this.setFilter('search', '');
        });
    }
};

/**
 * Setup file selection
 */
FileServerApp.prototype.setupFileSelection = function() {
    // Select all checkbox
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', (e) => {
            this.selectAllFiles(e.target.checked);
        });
    }

    // File selection events will be set up when files are rendered
};

/**
 * Select all files
 */
FileServerApp.prototype.selectAllFiles = function(selected) {
    const fileCheckboxes = document.querySelectorAll('.file-checkbox');
    fileCheckboxes.forEach(checkbox => {
        checkbox.checked = selected;
        const fileId = checkbox.dataset.fileId;
        if (selected) {
            this.selectedFiles.add(fileId);
        } else {
            this.selectedFiles.delete(fileId);
        }
    });
    
    this.updateFileSelection();
};

/**
 * Toggle file selection
 */
FileServerApp.prototype.toggleFileSelection = function(fileId) {
    if (this.selectedFiles.has(fileId)) {
        this.selectedFiles.delete(fileId);
    } else {
        this.selectedFiles.add(fileId);
    }
    
    this.updateFileSelection();
};

/**
 * Update file selection UI
 */
FileServerApp.prototype.updateFileSelection = function() {
    const selectedCount = this.selectedFiles.size;
    const totalCount = document.querySelectorAll('.file-checkbox').length;
    
    // Update select all checkbox
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = selectedCount > 0 && selectedCount === totalCount;
        selectAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < totalCount;
    }

    // Update selection counter
    const selectionCounter = document.getElementById('selectionCounter');
    if (selectionCounter) {
        selectionCounter.textContent = selectedCount > 0 ? `${selectedCount} selected` : '';
    }

    // Show/hide bulk actions
    const bulkActions = document.getElementById('bulkActions');
    if (bulkActions) {
        bulkActions.style.display = selectedCount > 0 ? 'flex' : 'none';
    }

    // Update selected files display
    this.updateSelectedFilesDisplay();
};

/**
 * Update selected files display
 */
FileServerApp.prototype.updateSelectedFilesDisplay = function() {
    const fileItems = document.querySelectorAll('.file-item');
    fileItems.forEach(item => {
        const fileId = item.dataset.fileId;
        const isSelected = this.selectedFiles.has(fileId);
        item.classList.toggle('selected', isSelected);
        
        const checkbox = item.querySelector('.file-checkbox');
        if (checkbox) {
            checkbox.checked = isSelected;
        }
    });
};

/**
 * Setup file actions
 */
FileServerApp.prototype.setupFileActions = function() {
    // Bulk actions
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-bulk-action]')) {
            const action = e.target.closest('[data-bulk-action]').dataset.bulkAction;
            this.handleBulkAction(action);
        }
    });

    // Individual file actions will be set up when files are rendered
};

/**
 * Handle bulk actions
 */
FileServerApp.prototype.handleBulkAction = function(action) {
    if (this.selectedFiles.size === 0) {
        this.showNotification('No files selected', 'warning');
        return;
    }

    const fileIds = Array.from(this.selectedFiles);

    switch (action) {
        case 'download':
            this.downloadFiles(fileIds);
            break;
        case 'delete':
            this.deleteFiles(fileIds);
            break;
        case 'move':
            this.moveFiles(fileIds);
            break;
        case 'copy':
            this.copyFiles(fileIds);
            break;
        case 'share':
            this.shareFiles(fileIds);
            break;
        case 'archive':
            this.archiveFiles(fileIds);
            break;
    }
};

/**
 * Load files from API
 */
FileServerApp.prototype.loadFiles = async function() {
    const loadingIndicator = document.getElementById('loadingIndicator');
    if (loadingIndicator) loadingIndicator.style.display = 'block';

    try {
        const params = new URLSearchParams({
            path: this.currentPath,
            sort: this.currentSort,
            ...this.currentFilter
        });

        const response = await this.apiRequest(`/files/list?${params}`);
        
        if (response.success) {
            this.files = response.data.files;
            this.directories = response.data.directories;
            this.renderFiles();
            this.updateBreadcrumbs();
        }
    } catch (error) {
        this.showNotification('Failed to load files: ' + error.message, 'error');
    } finally {
        if (loadingIndicator) loadingIndicator.style.display = 'none';
    }
};

/**
 * Render files in the current view
 */
FileServerApp.prototype.renderFiles = function() {
    const filesContainer = document.getElementById('filesContainer');
    if (!filesContainer) return;

    // Clear current content
    filesContainer.innerHTML = '';

    // Render directories first
    if (this.directories && this.directories.length > 0) {
        this.directories.forEach(dir => {
            const dirElement = this.createDirectoryElement(dir);
            filesContainer.appendChild(dirElement);
        });
    }

    // Render files
    if (this.files && this.files.length > 0) {
        this.files.forEach(file => {
            const fileElement = this.createFileElement(file);
            filesContainer.appendChild(fileElement);
        });
    } else if ((!this.directories || this.directories.length === 0)) {
        // Show empty state
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state';
        emptyState.innerHTML = `
            <div class="empty-state-content">
                <i class="fas fa-folder-open"></i>
                <h3>No files found</h3>
                <p>This directory is empty or no files match your current filters.</p>
                <button type="button" class="btn btn-primary" onclick="window.location.href='/upload'">
                    <i class="fas fa-upload"></i> Upload Files
                </button>
            </div>
        `;
        filesContainer.appendChild(emptyState);
    }

    // Setup file events
    this.setupFileEvents();
    this.updateSelectedFilesDisplay();
};

/**
 * Create directory element
 */
FileServerApp.prototype.createDirectoryElement = function(directory) {
    const element = document.createElement('div');
    element.className = `file-item directory-item ${this.currentView === 'list' ? 'list-item' : 'grid-item'}`;
    element.dataset.fileId = directory.id;
    element.dataset.type = 'directory';

    if (this.currentView === 'grid') {
        element.innerHTML = `
            <div class="file-item-content">
                <div class="file-selection">
                    <input type="checkbox" class="file-checkbox" data-file-id="${directory.id}">
                </div>
                <div class="file-icon">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="file-info">
                    <div class="file-name" title="${directory.name}">${directory.name}</div>
                    <div class="file-meta">
                        ${directory.file_count} items • ${this.formatDate(directory.modified_at)}
                    </div>
                </div>
                <div class="file-actions">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-action="open">
                        <i class="fas fa-folder-open"></i>
                    </button>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-action="rename"><i class="fas fa-edit"></i> Rename</a></li>
                            <li><a class="dropdown-item" href="#" data-action="move"><i class="fas fa-arrows-alt"></i> Move</a></li>
                            <li><a class="dropdown-item" href="#" data-action="delete"><i class="fas fa-trash"></i> Delete</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        `;
    } else {
        element.innerHTML = `
            <div class="file-item-content">
                <div class="file-selection">
                    <input type="checkbox" class="file-checkbox" data-file-id="${directory.id}">
                </div>
                <div class="file-icon">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="file-details">
                    <div class="file-name">${directory.name}</div>
                    <div class="file-type">Directory</div>
                    <div class="file-size">${directory.file_count} items</div>
                    <div class="file-date">${this.formatDate(directory.modified_at)}</div>
                    <div class="file-owner">${directory.owner}</div>
                </div>
                <div class="file-actions">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-action="open">
                        <i class="fas fa-folder-open"></i>
                    </button>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-action="rename"><i class="fas fa-edit"></i> Rename</a></li>
                            <li><a class="dropdown-item" href="#" data-action="move"><i class="fas fa-arrows-alt"></i> Move</a></li>
                            <li><a class="dropdown-item" href="#" data-action="delete"><i class="fas fa-trash"></i> Delete</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        `;
    }

    return element;
};

/**
 * Create file element
 */
FileServerApp.prototype.createFileElement = function(file) {
    const element = document.createElement('div');
    element.className = `file-item ${this.currentView === 'list' ? 'list-item' : 'grid-item'}`;
    element.dataset.fileId = file.id;
    element.dataset.type = 'file';

    const fileIcon = this.getFileIcon(file.mime_type);
    const thumbnail = file.thumbnail_url ? 
        `<img src="${file.thumbnail_url}" alt="Thumbnail" class="file-thumbnail">` : 
        `<i class="${fileIcon}"></i>`;

    if (this.currentView === 'grid') {
        element.innerHTML = `
            <div class="file-item-content">
                <div class="file-selection">
                    <input type="checkbox" class="file-checkbox" data-file-id="${file.id}">
                </div>
                <div class="file-icon">
                    ${thumbnail}
                </div>
                <div class="file-info">
                    <div class="file-name" title="${file.name}">${file.name}</div>
                    <div class="file-meta">
                        ${this.formatFileSize(file.size)} • ${this.formatDate(file.created_at)}
                    </div>
                </div>
                <div class="file-actions">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-action="preview" title="Preview">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" data-action="download" title="Download">
                        <i class="fas fa-download"></i>
                    </button>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-action="info"><i class="fas fa-info-circle"></i> Info</a></li>
                            <li><a class="dropdown-item" href="#" data-action="rename"><i class="fas fa-edit"></i> Rename</a></li>
                            <li><a class="dropdown-item" href="#" data-action="move"><i class="fas fa-arrows-alt"></i> Move</a></li>
                            <li><a class="dropdown-item" href="#" data-action="copy"><i class="fas fa-copy"></i> Copy</a></li>
                            <li><a class="dropdown-item" href="#" data-action="share"><i class="fas fa-share-alt"></i> Share</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" data-action="delete"><i class="fas fa-trash"></i> Delete</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        `;
    } else {
        element.innerHTML = `
            <div class="file-item-content">
                <div class="file-selection">
                    <input type="checkbox" class="file-checkbox" data-file-id="${file.id}">
                </div>
                <div class="file-icon">
                    ${thumbnail}
                </div>
                <div class="file-details">
                    <div class="file-name">${file.name}</div>
                    <div class="file-type">${file.mime_type || 'Unknown'}</div>
                    <div class="file-size">${this.formatFileSize(file.size)}</div>
                    <div class="file-date">${this.formatDate(file.created_at)}</div>
                    <div class="file-owner">${file.owner}</div>
                </div>
                <div class="file-actions">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-action="preview" title="Preview">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" data-action="download" title="Download">
                        <i class="fas fa-download"></i>
                    </button>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-action="info"><i class="fas fa-info-circle"></i> Info</a></li>
                            <li><a class="dropdown-item" href="#" data-action="rename"><i class="fas fa-edit"></i> Rename</a></li>
                            <li><a class="dropdown-item" href="#" data-action="move"><i class="fas fa-arrows-alt"></i> Move</a></li>
                            <li><a class="dropdown-item" href="#" data-action="copy"><i class="fas fa-copy"></i> Copy</a></li>
                            <li><a class="dropdown-item" href="#" data-action="share"><i class="fas fa-share-alt"></i> Share</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" data-action="delete"><i class="fas fa-trash"></i> Delete</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        `;
    }

    return element;
};

/**
 * Setup file events
 */
FileServerApp.prototype.setupFileEvents = function() {
    // File selection
    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('file-checkbox')) {
            const fileId = e.target.dataset.fileId;
            this.toggleFileSelection(fileId);
        }
    });

    // File actions
    document.addEventListener('click', (e) => {
        const actionBtn = e.target.closest('[data-action]');
        if (!actionBtn) return;

        e.preventDefault();
        const action = actionBtn.dataset.action;
        const fileItem = actionBtn.closest('.file-item');
        const fileId = fileItem?.dataset.fileId;
        const fileType = fileItem?.dataset.type;

        if (!fileId) return;

        this.handleFileAction(action, fileId, fileType);
    });

    // Double-click to open
    document.addEventListener('dblclick', (e) => {
        const fileItem = e.target.closest('.file-item');
        if (!fileItem) return;

        const fileId = fileItem.dataset.fileId;
        const fileType = fileItem.dataset.type;

        if (fileType === 'directory') {
            this.openDirectory(fileId);
        } else {
            this.previewFile(fileId);
        }
    });
};

/**
 * Handle file action
 */
FileServerApp.prototype.handleFileAction = function(action, fileId, fileType) {
    switch (action) {
        case 'open':
            if (fileType === 'directory') {
                this.openDirectory(fileId);
            } else {
                this.previewFile(fileId);
            }
            break;
        case 'preview':
            this.previewFile(fileId);
            break;
        case 'download':
            this.downloadFiles([fileId]);
            break;
        case 'info':
            this.showFileInfo(fileId);
            break;
        case 'rename':
            this.renameFile(fileId);
            break;
        case 'move':
            this.moveFiles([fileId]);
            break;
        case 'copy':
            this.copyFiles([fileId]);
            break;
        case 'share':
            this.shareFiles([fileId]);
            break;
        case 'delete':
            this.deleteFiles([fileId]);
            break;
    }
};

/**
 * Open directory
 */
FileServerApp.prototype.openDirectory = function(directoryId) {
    // Find directory in current list
    const directory = this.directories?.find(dir => dir.id === directoryId);
    if (!directory) return;

    const newPath = this.currentPath === '/' ? 
        `/${directory.name}` : 
        `${this.currentPath}/${directory.name}`;
    
    window.location.href = `/files?path=${encodeURIComponent(newPath)}`;
};

/**
 * Preview file
 */
FileServerApp.prototype.previewFile = function(fileId) {
    // Implementation for file preview modal
    this.openModal('filePreviewModal');
    // Load file content for preview
    this.loadFilePreview(fileId);
};

/**
 * Download files
 */
FileServerApp.prototype.downloadFiles = function(fileIds) {
    if (fileIds.length === 1) {
        // Single file download
        window.open(`${this.apiBaseUrl}/files/download/${fileIds[0]}`, '_blank');
    } else {
        // Multiple files - create zip
        this.createZipDownload(fileIds);
    }
};

/**
 * Delete files
 */
FileServerApp.prototype.deleteFiles = async function(fileIds) {
    const fileCount = fileIds.length;
    const message = fileCount === 1 ? 
        'Are you sure you want to delete this file?' : 
        `Are you sure you want to delete ${fileCount} files?`;

    if (!confirm(message)) return;

    try {
        const response = await this.apiRequest('/files/delete', {
            method: 'DELETE',
            body: JSON.stringify({ file_ids: fileIds })
        });

        if (response.success) {
            this.showNotification(`${fileCount} file(s) deleted successfully`, 'success');
            this.selectedFiles.clear();
            this.loadFiles(); // Refresh the list
        }
    } catch (error) {
        this.showNotification('Failed to delete files: ' + error.message, 'error');
    }
};

/**
 * Update breadcrumbs
 */
FileServerApp.prototype.updateBreadcrumbs = function() {
    const breadcrumbs = document.getElementById('breadcrumbs');
    if (!breadcrumbs) return;

    const pathParts = this.currentPath.split('/').filter(part => part);
    
    let breadcrumbHTML = '<li class="breadcrumb-item"><a href="/files" class="breadcrumb-link">Home</a></li>';
    
    let currentPath = '';
    pathParts.forEach((part, index) => {
        currentPath += '/' + part;
        const isLast = index === pathParts.length - 1;
        
        if (isLast) {
            breadcrumbHTML += `<li class="breadcrumb-item active">${part}</li>`;
        } else {
            breadcrumbHTML += `<li class="breadcrumb-item"><a href="/files?path=${encodeURIComponent(currentPath)}" class="breadcrumb-link">${part}</a></li>`;
        }
    });
    
    breadcrumbs.innerHTML = breadcrumbHTML;
};

/**
 * Setup context menu
 */
FileServerApp.prototype.setupContextMenu = function() {
    document.addEventListener('contextmenu', (e) => {
        const fileItem = e.target.closest('.file-item');
        if (!fileItem) return;

        e.preventDefault();
        this.showContextMenu(e.pageX, e.pageY, fileItem);
    });

    // Hide context menu on click elsewhere
    document.addEventListener('click', () => {
        this.hideContextMenu();
    });
};

/**
 * Setup keyboard shortcuts
 */
FileServerApp.prototype.setupKeyboardShortcuts = function() {
    document.addEventListener('keydown', (e) => {
        // Don't interfere with input fields
        if (e.target.matches('input, textarea, select')) return;

        switch (e.key) {
            case 'Delete':
                if (this.selectedFiles.size > 0) {
                    this.deleteFiles(Array.from(this.selectedFiles));
                }
                break;
            case 'a':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    this.selectAllFiles(true);
                }
                break;
            case 'Escape':
                this.selectedFiles.clear();
                this.updateFileSelection();
                break;
        }
    });
};

/**
 * Setup infinite scroll
 */
FileServerApp.prototype.setupInfiniteScroll = function() {
    // Implementation for loading more files as user scrolls
    // This would be useful for large directories
};

// Additional file management functions would be implemented here...
