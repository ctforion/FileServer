// File Browser JavaScript functionality
class FileBrowser {
    constructor() {
        this.selectedFiles = new Set();
        this.sortOptions = {
            column: 'name',
            direction: 'asc'
        };
        this.init();
    }

    init() {
        this.bindEvents();
        this.initFileSelection();
        this.initFileActions();
        this.initContextMenu();
    }

    bindEvents() {
        // File selection events
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('file-checkbox')) {
                this.handleFileSelection(e.target);
            }
        });

        // Bulk actions
        const bulkActions = document.getElementById('bulkActions');
        if (bulkActions) {
            bulkActions.addEventListener('change', (e) => {
                this.handleBulkAction(e.target.value);
            });
        }

        // Select all checkbox
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.addEventListener('change', (e) => {
                this.selectAllFiles(e.target.checked);
            });
        }

        // Table header clicks for sorting
        document.querySelectorAll('.sortable').forEach(header => {
            header.addEventListener('click', (e) => {
                this.handleSort(e.target.dataset.column);
            });
        });

        // File action buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('download-btn')) {
                this.downloadFile(e.target.dataset.fileId);
            } else if (e.target.classList.contains('share-btn')) {
                this.shareFile(e.target.dataset.fileId);
            } else if (e.target.classList.contains('delete-btn')) {
                this.deleteFile(e.target.dataset.fileId);
            } else if (e.target.classList.contains('rename-btn')) {
                this.renameFile(e.target.dataset.fileId);
            } else if (e.target.classList.contains('move-btn')) {
                this.moveFile(e.target.dataset.fileId);
            } else if (e.target.classList.contains('details-btn')) {
                this.showFileDetails(e.target.dataset.fileId);
            }
        });
    }

    initFileSelection() {
        const fileRows = document.querySelectorAll('.file-row');
        fileRows.forEach(row => {
            row.addEventListener('click', (e) => {
                if (!e.target.classList.contains('file-checkbox') && 
                    !e.target.closest('.action-buttons')) {
                    const checkbox = row.querySelector('.file-checkbox');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        this.handleFileSelection(checkbox);
                    }
                }
            });

            // Double click to view/download
            row.addEventListener('dblclick', (e) => {
                const fileId = row.dataset.fileId;
                if (fileId) {
                    this.viewOrDownloadFile(fileId);
                }
            });
        });
    }

    initFileActions() {
        // Initialize drag and drop for moving files
        this.initDragAndDrop();
        
        // Initialize keyboard shortcuts
        this.initKeyboardShortcuts();
    }

    initContextMenu() {
        document.addEventListener('contextmenu', (e) => {
            const fileRow = e.target.closest('.file-row');
            if (fileRow) {
                e.preventDefault();
                this.showContextMenu(e, fileRow.dataset.fileId);
            }
        });

        // Hide context menu on click outside
        document.addEventListener('click', () => {
            this.hideContextMenu();
        });
    }

    initDragAndDrop() {
        const fileRows = document.querySelectorAll('.file-row');
        fileRows.forEach(row => {
            row.draggable = true;
            
            row.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', row.dataset.fileId);
                row.classList.add('dragging');
            });

            row.addEventListener('dragend', () => {
                row.classList.remove('dragging');
            });

            row.addEventListener('dragover', (e) => {
                e.preventDefault();
                row.classList.add('drag-over');
            });

            row.addEventListener('dragleave', () => {
                row.classList.remove('drag-over');
            });

            row.addEventListener('drop', (e) => {
                e.preventDefault();
                const draggedFileId = e.dataTransfer.getData('text/plain');
                const targetFileId = row.dataset.fileId;
                
                if (draggedFileId !== targetFileId) {
                    this.moveFileToDirectory(draggedFileId, targetFileId);
                }
                
                row.classList.remove('drag-over');
            });
        });
    }

    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'a') {
                e.preventDefault();
                this.selectAllFiles(true);
            } else if (e.key === 'Delete') {
                this.deleteSelectedFiles();
            } else if (e.ctrlKey && e.key === 'c') {
                this.copySelectedFiles();
            } else if (e.ctrlKey && e.key === 'v') {
                this.pasteFiles();
            }
        });
    }

    handleFileSelection(checkbox) {
        const fileId = checkbox.value;
        const isChecked = checkbox.checked;

        if (isChecked) {
            this.selectedFiles.add(fileId);
        } else {
            this.selectedFiles.delete(fileId);
        }

        this.updateSelectionUI();
    }

    selectAllFiles(selectAll) {
        const checkboxes = document.querySelectorAll('.file-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll;
            this.handleFileSelection(checkbox);
        });
    }

    updateSelectionUI() {
        const selectedCount = this.selectedFiles.size;
        const selectionInfo = document.getElementById('selectionInfo');
        const bulkActions = document.getElementById('bulkActions');

        if (selectionInfo) {
            if (selectedCount > 0) {
                selectionInfo.textContent = `${selectedCount} file(s) selected`;
                selectionInfo.style.display = 'block';
            } else {
                selectionInfo.style.display = 'none';
            }
        }

        if (bulkActions) {
            bulkActions.style.display = selectedCount > 0 ? 'block' : 'none';
        }
    }

    handleBulkAction(action) {
        if (this.selectedFiles.size === 0) {
            showNotification('No files selected', 'warning');
            return;
        }

        switch (action) {
            case 'download':
                this.downloadSelectedFiles();
                break;
            case 'delete':
                this.deleteSelectedFiles();
                break;
            case 'move':
                this.moveSelectedFiles();
                break;
            case 'compress':
                this.compressSelectedFiles();
                break;
            case 'share':
                this.shareSelectedFiles();
                break;
        }
    }

    handleSort(column) {
        if (this.sortOptions.column === column) {
            this.sortOptions.direction = this.sortOptions.direction === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortOptions.column = column;
            this.sortOptions.direction = 'asc';
        }

        this.sortFiles();
        this.updateSortUI();
    }

    sortFiles() {
        const fileTable = document.getElementById('fileTable');
        const tbody = fileTable.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('.file-row'));

        rows.sort((a, b) => {
            const aValue = a.querySelector(`[data-sort="${this.sortOptions.column}"]`)?.textContent || '';
            const bValue = b.querySelector(`[data-sort="${this.sortOptions.column}"]`)?.textContent || '';

            let comparison = 0;
            if (this.sortOptions.column === 'size') {
                comparison = parseInt(a.dataset.size) - parseInt(b.dataset.size);
            } else if (this.sortOptions.column === 'date') {
                comparison = new Date(a.dataset.date) - new Date(b.dataset.date);
            } else {
                comparison = aValue.localeCompare(bValue);
            }

            return this.sortOptions.direction === 'desc' ? -comparison : comparison;
        });

        // Reorder rows in DOM
        rows.forEach(row => tbody.appendChild(row));
    }

    updateSortUI() {
        // Update sort indicators
        document.querySelectorAll('.sort-indicator').forEach(indicator => {
            indicator.remove();
        });

        const currentHeader = document.querySelector(`[data-column="${this.sortOptions.column}"]`);
        if (currentHeader) {
            const indicator = document.createElement('span');
            indicator.className = 'sort-indicator';
            indicator.textContent = this.sortOptions.direction === 'asc' ? ' ↑' : ' ↓';
            currentHeader.appendChild(indicator);
        }
    }

    // File action methods
    async downloadFile(fileId) {
        try {
            window.location.href = `api/files.php?action=download&id=${fileId}`;
            showNotification('Download started', 'success');
        } catch (error) {
            showNotification('Download failed', 'error');
        }
    }

    async shareFile(fileId) {
        try {
            const response = await fetch('api/share.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'create',
                    file_id: fileId,
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            if (data.success) {
                this.showShareModal(data.share_link, data.share_token);
            } else {
                showNotification(data.message || 'Share failed', 'error');
            }
        } catch (error) {
            showNotification('Share failed', 'error');
        }
    }

    async deleteFile(fileId) {
        if (!confirm('Are you sure you want to delete this file?')) {
            return;
        }

        try {
            const response = await fetch('api/files.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: fileId,
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            if (data.success) {
                this.removeFileFromUI(fileId);
                showNotification('File deleted successfully', 'success');
            } else {
                showNotification(data.message || 'Delete failed', 'error');
            }
        } catch (error) {
            showNotification('Delete failed', 'error');
        }
    }

    async renameFile(fileId) {
        const currentName = document.querySelector(`[data-file-id="${fileId}"]`)?.querySelector('.filename')?.textContent;
        const newName = prompt('Enter new filename:', currentName);
        
        if (!newName || newName === currentName) {
            return;
        }

        try {
            const response = await fetch('api/files.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'rename',
                    id: fileId,
                    new_name: newName,
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            if (data.success) {
                this.updateFileNameInUI(fileId, newName);
                showNotification('File renamed successfully', 'success');
            } else {
                showNotification(data.message || 'Rename failed', 'error');
            }
        } catch (error) {
            showNotification('Rename failed', 'error');
        }
    }

    async moveFile(fileId) {
        // Show directory selection modal
        this.showMoveModal(fileId);
    }

    async showFileDetails(fileId) {
        try {
            const response = await fetch(`api/files.php?action=details&id=${fileId}`);
            const data = await response.json();
            
            if (data.success) {
                this.displayFileDetails(data.file);
            } else {
                showNotification(data.message || 'Failed to load file details', 'error');
            }
        } catch (error) {
            showNotification('Failed to load file details', 'error');
        }
    }

    // Bulk operations
    async downloadSelectedFiles() {
        if (this.selectedFiles.size === 1) {
            this.downloadFile(Array.from(this.selectedFiles)[0]);
        } else {
            // Create zip and download
            this.downloadMultipleFiles();
        }
    }

    async deleteSelectedFiles() {
        if (!confirm(`Are you sure you want to delete ${this.selectedFiles.size} file(s)?`)) {
            return;
        }

        const fileIds = Array.from(this.selectedFiles);
        let successCount = 0;

        for (const fileId of fileIds) {
            try {
                const response = await fetch('api/files.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: fileId,
                        csrf_token: this.getCSRFToken()
                    })
                });

                const data = await response.json();
                if (data.success) {
                    this.removeFileFromUI(fileId);
                    successCount++;
                }
            } catch (error) {
                console.error('Delete failed for file:', fileId);
            }
        }

        this.clearSelection();
        showNotification(`${successCount} file(s) deleted successfully`, 'success');
    }

    async compressSelectedFiles() {
        const fileIds = Array.from(this.selectedFiles);
        
        try {
            const response = await fetch('api/compress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'compress',
                    file_ids: fileIds,
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            if (data.success) {
                showNotification('Files compressed successfully', 'success');
                // Optionally refresh the file list or download the compressed file
                if (data.download_url) {
                    window.location.href = data.download_url;
                }
            } else {
                showNotification(data.message || 'Compression failed', 'error');
            }
        } catch (error) {
            showNotification('Compression failed', 'error');
        }
    }

    // UI helper methods
    removeFileFromUI(fileId) {
        const fileRow = document.querySelector(`[data-file-id="${fileId}"]`);
        if (fileRow) {
            fileRow.remove();
        }
        this.selectedFiles.delete(fileId);
        this.updateSelectionUI();
    }

    updateFileNameInUI(fileId, newName) {
        const fileRow = document.querySelector(`[data-file-id="${fileId}"]`);
        if (fileRow) {
            const filenameElement = fileRow.querySelector('.filename');
            if (filenameElement) {
                filenameElement.textContent = newName;
            }
        }
    }

    clearSelection() {
        this.selectedFiles.clear();
        document.querySelectorAll('.file-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        this.updateSelectionUI();
    }

    showShareModal(shareLink, shareToken) {
        const modal = document.getElementById('shareFileModal');
        if (modal) {
            const content = modal.querySelector('#shareFileContent');
            content.innerHTML = `
                <div class="share-details">
                    <div class="form-group">
                        <label>Share Link:</label>
                        <div class="input-with-button">
                            <input type="text" value="${shareLink}" readonly id="shareLinkInput">
                            <button onclick="copyToClipboard('${shareLink}')" class="btn btn-secondary">Copy</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Share Token:</label>
                        <div class="input-with-button">
                            <input type="text" value="${shareToken}" readonly id="shareTokenInput">
                            <button onclick="copyToClipboard('${shareToken}')" class="btn btn-secondary">Copy</button>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button onclick="closeModal('shareFileModal')" class="btn btn-secondary">Close</button>
                    </div>
                </div>
            `;
            showModal('shareFileModal');
        }
    }

    showMoveModal(fileId) {
        // This would show a modal with directory selection
        // For now, just prompt for directory
        const directory = prompt('Enter target directory (leave empty for root):');
        if (directory !== null) {
            this.moveFileToDirectory(fileId, directory);
        }
    }

    async moveFileToDirectory(fileId, targetDirectory) {
        try {
            const response = await fetch('api/files.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'move',
                    id: fileId,
                    target_directory: targetDirectory,
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            if (data.success) {
                showNotification('File moved successfully', 'success');
                // Optionally refresh the file list
                location.reload();
            } else {
                showNotification(data.message || 'Move failed', 'error');
            }
        } catch (error) {
            showNotification('Move failed', 'error');
        }
    }

    displayFileDetails(file) {
        const modal = document.getElementById('fileDetailsModal');
        if (modal) {
            const content = modal.querySelector('#fileDetailsContent');
            content.innerHTML = `
                <div class="file-details">
                    <div class="detail-row">
                        <strong>Name:</strong> ${file.original_name}
                    </div>
                    <div class="detail-row">
                        <strong>Size:</strong> ${formatFileSize(file.size)}
                    </div>
                    <div class="detail-row">
                        <strong>Type:</strong> ${file.type}
                    </div>
                    <div class="detail-row">
                        <strong>Uploaded:</strong> ${formatDate(file.uploaded_at)}
                    </div>
                    <div class="detail-row">
                        <strong>Path:</strong> ${file.path}
                    </div>
                    ${file.description ? `<div class="detail-row"><strong>Description:</strong> ${file.description}</div>` : ''}
                    <div class="modal-actions">
                        <button onclick="closeModal('fileDetailsModal')" class="btn btn-secondary">Close</button>
                    </div>
                </div>
            `;
            showModal('fileDetailsModal');
        }
    }

    showContextMenu(event, fileId) {
        const contextMenu = document.getElementById('contextMenu') || this.createContextMenu();
        
        // Update context menu for this file
        this.updateContextMenu(contextMenu, fileId);
        
        // Position and show
        contextMenu.style.left = event.pageX + 'px';
        contextMenu.style.top = event.pageY + 'px';
        contextMenu.style.display = 'block';
    }

    createContextMenu() {
        const menu = document.createElement('div');
        menu.id = 'contextMenu';
        menu.className = 'context-menu';
        document.body.appendChild(menu);
        return menu;
    }

    updateContextMenu(menu, fileId) {
        menu.innerHTML = `
            <div class="context-item" onclick="fileBrowser.downloadFile('${fileId}')">
                📥 Download
            </div>
            <div class="context-item" onclick="fileBrowser.shareFile('${fileId}')">
                🔗 Share
            </div>
            <div class="context-item" onclick="fileBrowser.renameFile('${fileId}')">
                ✏️ Rename
            </div>
            <div class="context-item" onclick="fileBrowser.moveFile('${fileId}')">
                📁 Move
            </div>
            <div class="context-item" onclick="fileBrowser.showFileDetails('${fileId}')">
                ℹ️ Details
            </div>
            <div class="context-divider"></div>
            <div class="context-item danger" onclick="fileBrowser.deleteFile('${fileId}')">
                🗑️ Delete
            </div>
        `;
    }

    hideContextMenu() {
        const contextMenu = document.getElementById('contextMenu');
        if (contextMenu) {
            contextMenu.style.display = 'none';
        }
    }

    viewOrDownloadFile(fileId) {
        // For now, just download
        this.downloadFile(fileId);
    }

    downloadMultipleFiles() {
        // Create a zip with selected files
        this.compressSelectedFiles();
    }

    getCSRFToken() {
        const token = document.querySelector('input[name="csrf_token"]') || 
                     document.querySelector('meta[name="csrf-token"]');
        return token ? token.value || token.content : '';
    }
}

// Initialize file browser
const fileBrowser = new FileBrowser();

// Global functions for template access
window.shareFile = (fileId) => fileBrowser.shareFile(fileId);
window.deleteFile = (fileId) => fileBrowser.deleteFile(fileId);
window.downloadFile = (fileId) => fileBrowser.downloadFile(fileId);
window.renameFile = (fileId) => fileBrowser.renameFile(fileId);
window.moveFile = (fileId) => fileBrowser.moveFile(fileId);
window.showFileDetails = (fileId) => fileBrowser.showFileDetails(fileId);
