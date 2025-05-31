/**
 * File Browser Module
 * Handles file browsing, searching, sorting, and file operations
 */

class FileBrowser {
    constructor() {
        this.currentPath = '/';
        this.currentView = localStorage.getItem('fileView') || 'grid';
        this.sortBy = localStorage.getItem('fileSortBy') || 'name';
        this.sortOrder = localStorage.getItem('fileSortOrder') || 'asc';
        this.selectedFiles = new Set();
        this.files = [];
        
        this.container = document.querySelector('[data-file-browser]');
        this.searchInput = document.querySelector('[data-search-input]');
        this.viewToggle = document.querySelector('[data-view-toggle]');
        this.sortDropdown = document.querySelector('[data-sort-dropdown]');
        this.fileGrid = document.querySelector('[data-file-grid]');
        this.fileList = document.querySelector('[data-file-list]');
        this.breadcrumb = document.querySelector('[data-breadcrumb]');
        this.selectionInfo = document.querySelector('[data-selection-info]');
        this.bulkActions = document.querySelector('[data-bulk-actions]');
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.setView(this.currentView);
        this.loadFiles();
    }
    
    setupEventListeners() {
        // Search functionality
        if (this.searchInput) {
            this.searchInput.addEventListener('input', 
                window.app.debounce(this.handleSearch.bind(this), 300)
            );
        }
        
        // View toggle
        if (this.viewToggle) {
            this.viewToggle.addEventListener('click', this.toggleView.bind(this));
        }
        
        // Sort dropdown
        if (this.sortDropdown) {
            this.sortDropdown.addEventListener('change', this.handleSort.bind(this));
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', this.handleKeyboardShortcuts.bind(this));
        
        // Bulk action buttons
        this.setupBulkActions();
        
        // Context menu
        this.setupContextMenu();
        
        // File drop for moving files
        this.setupFileDrop();
    }
    
    handleKeyboardShortcuts(e) {
        // Only handle if file browser is focused
        if (!this.container.contains(document.activeElement)) return;
        
        switch (e.key) {
            case 'Delete':
                e.preventDefault();
                this.deleteSelected();
                break;
            case 'F2':
                e.preventDefault();
                this.renameSelected();
                break;
            case 'Escape':
                this.clearSelection();
                break;
            case 'a':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    this.selectAll();
                }
                break;
        }
    }
    
    setupBulkActions() {
        const bulkButtons = this.bulkActions?.querySelectorAll('[data-bulk-action]');
        bulkButtons?.forEach(button => {
            button.addEventListener('click', () => {
                const action = button.dataset.bulkAction;
                this.handleBulkAction(action);
            });
        });
    }
    
    setupContextMenu() {
        if (this.fileGrid) {
            this.fileGrid.addEventListener('contextmenu', this.showContextMenu.bind(this));
        }
        if (this.fileList) {
            this.fileList.addEventListener('contextmenu', this.showContextMenu.bind(this));
        }
        
        // Hide context menu on click outside
        document.addEventListener('click', this.hideContextMenu.bind(this));
    }
    
    setupFileDrop() {
        // Allow dropping files on folders for moving
        const fileItems = this.container.querySelectorAll('[data-file-type="folder"]');
        fileItems.forEach(item => {
            item.addEventListener('dragover', this.handleDragOver.bind(this));
            item.addEventListener('drop', this.handleFileDrop.bind(this));
        });
    }
    
    async loadFiles(path = this.currentPath) {
        try {
            this.showLoading();
            
            const response = await window.app.apiRequest(`/api/files/list?path=${encodeURIComponent(path)}`);
            this.files = response.files;
            this.currentPath = path;
            
            this.updateBreadcrumb();
            this.sortFiles();
            this.renderFiles();
            this.updateSelectionInfo();
            
        } catch (error) {
            console.error('Failed to load files:', error);
            window.app.showNotification('Failed to load files', 'error');
        } finally {
            this.hideLoading();
        }
    }
    
    sortFiles() {
        this.files.sort((a, b) => {
            let aValue, bValue;
            
            // Folders first
            if (a.type === 'folder' && b.type !== 'folder') return -1;
            if (b.type === 'folder' && a.type !== 'folder') return 1;
            
            switch (this.sortBy) {
                case 'name':
                    aValue = a.name.toLowerCase();
                    bValue = b.name.toLowerCase();
                    break;
                case 'size':
                    aValue = a.size || 0;
                    bValue = b.size || 0;
                    break;
                case 'modified':
                    aValue = new Date(a.modified);
                    bValue = new Date(b.modified);
                    break;
                case 'type':
                    aValue = a.extension || '';
                    bValue = b.extension || '';
                    break;
                default:
                    aValue = a.name.toLowerCase();
                    bValue = b.name.toLowerCase();
            }
            
            if (this.sortOrder === 'desc') {
                [aValue, bValue] = [bValue, aValue];
            }
            
            if (aValue < bValue) return -1;
            if (aValue > bValue) return 1;
            return 0;
        });
    }
    
    renderFiles() {
        const container = this.currentView === 'grid' ? this.fileGrid : this.fileList;
        if (!container) return;
        
        container.innerHTML = '';
        
        this.files.forEach(file => {
            const fileElement = this.createFileElement(file);
            container.appendChild(fileElement);
        });
        
        // Update view visibility
        if (this.fileGrid && this.fileList) {
            this.fileGrid.style.display = this.currentView === 'grid' ? 'grid' : 'none';
            this.fileList.style.display = this.currentView === 'list' ? 'block' : 'none';
        }
    }
    
    createFileElement(file) {
        const element = document.createElement('div');
        element.className = `file-item file-item-${this.currentView}`;
        element.dataset.fileId = file.id;
        element.dataset.fileName = file.name;
        element.dataset.fileType = file.type;
        
        if (this.currentView === 'grid') {
            element.innerHTML = this.createGridItemHTML(file);
        } else {
            element.innerHTML = this.createListItemHTML(file);
        }
        
        this.setupFileItemEvents(element, file);
        
        return element;
    }
    
    createGridItemHTML(file) {
        const icon = this.getFileIcon(file);
        const isImage = this.isImageFile(file);
        const thumbnail = isImage ? `/api/files/thumbnail/${file.id}` : null;
        
        return `
            <div class="file-item-content">
                <div class="file-item-thumbnail">
                    ${thumbnail ? 
                        `<img src="${thumbnail}" alt="${file.name}" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                         <i class="${icon}" style="display: none;"></i>` :
                        `<i class="${icon}"></i>`
                    }
                    <div class="file-item-overlay">
                        <button class="btn-icon" data-action="preview" title="Preview">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-icon" data-action="download" title="Download">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="btn-icon" data-action="share" title="Share">
                            <i class="fas fa-share-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="file-item-info">
                    <div class="file-item-name" title="${file.name}">${file.name}</div>
                    <div class="file-item-meta">
                        ${file.type === 'folder' ? 
                            `${file.items || 0} items` : 
                            `${window.app.formatFileSize(file.size)} • ${window.app.formatDate(file.modified)}`
                        }
                    </div>
                </div>
                <div class="file-item-checkbox">
                    <input type="checkbox" data-file-select>
                </div>
            </div>
        `;
    }
    
    createListItemHTML(file) {
        const icon = this.getFileIcon(file);
        
        return `
            <div class="file-item-content">
                <div class="file-item-checkbox">
                    <input type="checkbox" data-file-select>
                </div>
                <div class="file-item-icon">
                    <i class="${icon}"></i>
                </div>
                <div class="file-item-info">
                    <div class="file-item-name">${file.name}</div>
                </div>
                <div class="file-item-size">
                    ${file.type === 'folder' ? `${file.items || 0} items` : window.app.formatFileSize(file.size)}
                </div>
                <div class="file-item-modified">
                    ${window.app.formatDate(file.modified)}
                </div>
                <div class="file-item-actions">
                    <button class="btn-icon" data-action="preview" title="Preview">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-icon" data-action="download" title="Download">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="btn-icon" data-action="share" title="Share">
                        <i class="fas fa-share-alt"></i>
                    </button>
                    <button class="btn-icon" data-action="more" title="More">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>
        `;
    }
    
    setupFileItemEvents(element, file) {
        // Double-click to open
        element.addEventListener('dblclick', () => {
            if (file.type === 'folder') {
                this.navigateToFolder(file.path);
            } else {
                this.previewFile(file);
            }
        });
        
        // Single click to select
        element.addEventListener('click', (e) => {
            if (e.target.type === 'checkbox') return;
            
            if (e.ctrlKey || e.metaKey) {
                this.toggleFileSelection(file.id);
            } else if (e.shiftKey) {
                this.selectFileRange(file.id);
            } else {
                this.selectFile(file.id, true);
            }
        });
        
        // Checkbox selection
        const checkbox = element.querySelector('[data-file-select]');
        if (checkbox) {
            checkbox.addEventListener('change', () => {
                this.toggleFileSelection(file.id);
            });
        }
        
        // Action buttons
        const actionButtons = element.querySelectorAll('[data-action]');
        actionButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = button.dataset.action;
                this.handleFileAction(file, action);
            });
        });
        
        // Drag and drop
        if (file.type !== 'folder') {
            element.draggable = true;
            element.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', file.id);
                e.dataTransfer.effectAllowed = 'move';
            });
        }
    }
    
    getFileIcon(file) {
        if (file.type === 'folder') {
            return 'fas fa-folder';
        }
        
        const extension = file.extension?.toLowerCase();
        const iconMap = {
            pdf: 'fas fa-file-pdf',
            doc: 'fas fa-file-word',
            docx: 'fas fa-file-word',
            xls: 'fas fa-file-excel',
            xlsx: 'fas fa-file-excel',
            ppt: 'fas fa-file-powerpoint',
            pptx: 'fas fa-file-powerpoint',
            txt: 'fas fa-file-alt',
            jpg: 'fas fa-file-image',
            jpeg: 'fas fa-file-image',
            png: 'fas fa-file-image',
            gif: 'fas fa-file-image',
            svg: 'fas fa-file-image',
            mp4: 'fas fa-file-video',
            avi: 'fas fa-file-video',
            mov: 'fas fa-file-video',
            mp3: 'fas fa-file-audio',
            wav: 'fas fa-file-audio',
            zip: 'fas fa-file-archive',
            rar: 'fas fa-file-archive',
            tar: 'fas fa-file-archive',
            gz: 'fas fa-file-archive',
            js: 'fas fa-file-code',
            html: 'fas fa-file-code',
            css: 'fas fa-file-code',
            php: 'fas fa-file-code',
            py: 'fas fa-file-code',
            java: 'fas fa-file-code',
        };
        
        return iconMap[extension] || 'fas fa-file';
    }
    
    isImageFile(file) {
        const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        return imageExtensions.includes(file.extension?.toLowerCase());
    }
    
    selectFile(fileId, clearOthers = false) {
        if (clearOthers) {
            this.clearSelection();
        }
        
        this.selectedFiles.add(fileId);
        this.updateFileSelection();
        this.updateSelectionInfo();
    }
    
    toggleFileSelection(fileId) {
        if (this.selectedFiles.has(fileId)) {
            this.selectedFiles.delete(fileId);
        } else {
            this.selectedFiles.add(fileId);
        }
        
        this.updateFileSelection();
        this.updateSelectionInfo();
    }
    
    selectFileRange(endFileId) {
        // Find start and end indices
        const fileIds = this.files.map(f => f.id);
        const lastSelected = Array.from(this.selectedFiles).pop();
        const startIndex = lastSelected ? fileIds.indexOf(lastSelected) : 0;
        const endIndex = fileIds.indexOf(endFileId);
        
        const start = Math.min(startIndex, endIndex);
        const end = Math.max(startIndex, endIndex);
        
        // Select range
        for (let i = start; i <= end; i++) {
            this.selectedFiles.add(fileIds[i]);
        }
        
        this.updateFileSelection();
        this.updateSelectionInfo();
    }
    
    selectAll() {
        this.files.forEach(file => {
            this.selectedFiles.add(file.id);
        });
        
        this.updateFileSelection();
        this.updateSelectionInfo();
    }
    
    clearSelection() {
        this.selectedFiles.clear();
        this.updateFileSelection();
        this.updateSelectionInfo();
    }
    
    updateFileSelection() {
        const fileItems = this.container.querySelectorAll('.file-item');
        fileItems.forEach(item => {
            const fileId = item.dataset.fileId;
            const checkbox = item.querySelector('[data-file-select]');
            const isSelected = this.selectedFiles.has(fileId);
            
            item.classList.toggle('selected', isSelected);
            if (checkbox) {
                checkbox.checked = isSelected;
            }
        });
    }
    
    updateSelectionInfo() {
        if (!this.selectionInfo) return;
        
        const count = this.selectedFiles.size;
        if (count === 0) {
            this.selectionInfo.textContent = '';
            this.bulkActions?.classList.remove('active');
        } else {
            this.selectionInfo.textContent = `${count} item${count > 1 ? 's' : ''} selected`;
            this.bulkActions?.classList.add('active');
        }
    }
    
    handleFileAction(file, action) {
        switch (action) {
            case 'preview':
                this.previewFile(file);
                break;
            case 'download':
                this.downloadFile(file);
                break;
            case 'share':
                this.shareFile(file);
                break;
            case 'rename':
                this.renameFile(file);
                break;
            case 'delete':
                this.deleteFile(file);
                break;
            case 'more':
                this.showFileMenu(file);
                break;
        }
    }
    
    handleBulkAction(action) {
        const selectedFileIds = Array.from(this.selectedFiles);
        const selectedFiles = this.files.filter(f => selectedFileIds.includes(f.id));
        
        switch (action) {
            case 'download':
                this.downloadFiles(selectedFiles);
                break;
            case 'delete':
                this.deleteFiles(selectedFiles);
                break;
            case 'move':
                this.moveFiles(selectedFiles);
                break;
            case 'copy':
                this.copyFiles(selectedFiles);
                break;
        }
    }
    
    previewFile(file) {
        // Open preview modal
        window.app.openModal('file-preview-modal');
        
        // Load file preview
        this.loadFilePreview(file);
    }
    
    async loadFilePreview(file) {
        const previewContainer = document.querySelector('[data-preview-container]');
        if (!previewContainer) return;
        
        previewContainer.innerHTML = '<div class="loading">Loading preview...</div>';
        
        try {
            const previewTypes = window.app.config.previewTypes;
            const extension = file.extension?.toLowerCase();
            
            if (this.isImageFile(file)) {
                previewContainer.innerHTML = `
                    <img src="/api/files/download/${file.id}" alt="${file.name}" style="max-width: 100%; height: auto;">
                `;
            } else if (extension === 'pdf') {
                previewContainer.innerHTML = `
                    <iframe src="/api/files/download/${file.id}" style="width: 100%; height: 600px;" frameborder="0"></iframe>
                `;
            } else if (extension === 'txt') {
                const response = await fetch(`/api/files/download/${file.id}`);
                const text = await response.text();
                previewContainer.innerHTML = `
                    <pre style="white-space: pre-wrap; font-family: monospace; padding: 1rem; background: #f5f5f5; border-radius: 4px;">${this.escapeHtml(text)}</pre>
                `;
            } else {
                previewContainer.innerHTML = `
                    <div class="preview-not-available">
                        <i class="fas fa-file fa-3x"></i>
                        <p>Preview not available for this file type</p>
                        <button class="btn btn-primary" onclick="window.fileBrowser.downloadFile({id: '${file.id}', name: '${file.name}'})">
                            <i class="fas fa-download"></i> Download
                        </button>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Preview error:', error);
            previewContainer.innerHTML = `
                <div class="preview-error">
                    <i class="fas fa-exclamation-triangle fa-3x"></i>
                    <p>Failed to load preview</p>
                </div>
            `;
        }
    }
    
    downloadFile(file) {
        const link = document.createElement('a');
        link.href = `/api/files/download/${file.id}`;
        link.download = file.name;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    downloadFiles(files) {
        if (files.length === 1) {
            this.downloadFile(files[0]);
        } else {
            // Create zip download
            const fileIds = files.map(f => f.id);
            const link = document.createElement('a');
            link.href = `/api/files/download/zip?files=${fileIds.join(',')}`;
            link.download = 'files.zip';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
    
    async deleteFile(file) {
        if (!confirm(`Are you sure you want to delete "${file.name}"?`)) {
            return;
        }
        
        try {
            await window.app.apiRequest(`/api/files/delete/${file.id}`, 'DELETE');
            window.app.showNotification(`"${file.name}" deleted successfully`, 'success');
            this.refresh();
        } catch (error) {
            console.error('Delete error:', error);
            window.app.showNotification(`Failed to delete "${file.name}"`, 'error');
        }
    }
    
    async deleteFiles(files) {
        const fileNames = files.map(f => f.name).join(', ');
        if (!confirm(`Are you sure you want to delete ${files.length} files?\n\n${fileNames}`)) {
            return;
        }
        
        try {
            const fileIds = files.map(f => f.id);
            await window.app.apiRequest('/api/files/delete/bulk', 'POST', { file_ids: fileIds });
            window.app.showNotification(`${files.length} files deleted successfully`, 'success');
            this.clearSelection();
            this.refresh();
        } catch (error) {
            console.error('Bulk delete error:', error);
            window.app.showNotification('Failed to delete files', 'error');
        }
    }
    
    navigateToFolder(path) {
        this.loadFiles(path);
    }
    
    updateBreadcrumb() {
        if (!this.breadcrumb) return;
        
        const pathParts = this.currentPath.split('/').filter(part => part);
        let breadcrumbHTML = '<a href="#" data-path="/">Home</a>';
        
        let currentPath = '';
        pathParts.forEach(part => {
            currentPath += '/' + part;
            breadcrumbHTML += ` <i class="fas fa-chevron-right"></i> <a href="#" data-path="${currentPath}">${part}</a>`;
        });
        
        this.breadcrumb.innerHTML = breadcrumbHTML;
        
        // Add click handlers
        this.breadcrumb.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const path = link.dataset.path;
                this.loadFiles(path);
            });
        });
    }
    
    handleSearch() {
        const query = this.searchInput.value.trim();
        
        if (query === '') {
            this.loadFiles(this.currentPath);
        } else {
            this.searchFiles(query);
        }
    }
    
    async searchFiles(query) {
        try {
            this.showLoading();
            
            const response = await window.app.apiRequest(`/api/files/search?q=${encodeURIComponent(query)}&path=${encodeURIComponent(this.currentPath)}`);
            this.files = response.files;
            
            this.sortFiles();
            this.renderFiles();
            
        } catch (error) {
            console.error('Search error:', error);
            window.app.showNotification('Search failed', 'error');
        } finally {
            this.hideLoading();
        }
    }
    
    toggleView() {
        this.currentView = this.currentView === 'grid' ? 'list' : 'grid';
        localStorage.setItem('fileView', this.currentView);
        
        this.setView(this.currentView);
        this.renderFiles();
    }
    
    setView(view) {
        this.currentView = view;
        
        if (this.viewToggle) {
            const icon = this.viewToggle.querySelector('i');
            if (icon) {
                icon.className = view === 'grid' ? 'fas fa-list' : 'fas fa-th';
            }
        }
        
        this.container?.classList.toggle('view-grid', view === 'grid');
        this.container?.classList.toggle('view-list', view === 'list');
    }
    
    handleSort() {
        const value = this.sortDropdown.value;
        const [sortBy, sortOrder] = value.split('-');
        
        this.sortBy = sortBy;
        this.sortOrder = sortOrder;
        
        localStorage.setItem('fileSortBy', this.sortBy);
        localStorage.setItem('fileSortOrder', this.sortOrder);
        
        this.sortFiles();
        this.renderFiles();
    }
    
    showLoading() {
        const loader = this.container?.querySelector('.loading-overlay');
        if (loader) {
            loader.style.display = 'flex';
        }
    }
    
    hideLoading() {
        const loader = this.container?.querySelector('.loading-overlay');
        if (loader) {
            loader.style.display = 'none';
        }
    }
    
    refresh() {
        this.loadFiles(this.currentPath);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Export for global use
window.FileBrowser = FileBrowser;
