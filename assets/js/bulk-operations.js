// Bulk Operations JavaScript functionality
class BulkOperations {
    constructor() {
        this.selectedFiles = new Set();
        this.operationQueue = [];
        this.isProcessing = false;
        this.maxConcurrent = 3;
        this.currentOperations = 0;
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupProgressTracking();
        this.initKeyboardShortcuts();
    }

    bindEvents() {
        // Bulk action selector
        const bulkSelector = document.getElementById('bulkActionSelector');
        if (bulkSelector) {
            bulkSelector.addEventListener('change', (e) => {
                if (e.target.value) {
                    this.executeBulkAction(e.target.value);
                    e.target.value = ''; // Reset selector
                }
            });
        }

        // Individual file checkboxes
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('file-checkbox')) {
                this.handleFileSelection(e.target);
            }
        });

        // Select all checkbox
        const selectAllCheckbox = document.getElementById('selectAllFiles');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                this.selectAllFiles(e.target.checked);
            });
        }

        // Bulk operation buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('bulk-operation-btn')) {
                e.preventDefault();
                const operation = e.target.dataset.operation;
                this.executeBulkAction(operation);
            }
        });

        // Cancel bulk operation
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('cancel-bulk-btn')) {
                this.cancelBulkOperation();
            }
        });

        // Confirm bulk operation dialog
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('confirm-bulk-btn')) {
                this.confirmBulkOperation();
            }
            if (e.target.classList.contains('cancel-bulk-dialog-btn')) {
                this.closeBulkDialog();
            }
        });
    }

    setupProgressTracking() {
        this.progressContainer = document.getElementById('bulkOperationProgress');
        this.progressBar = document.getElementById('bulkProgressBar');
        this.progressText = document.getElementById('bulkProgressText');
        this.progressDetails = document.getElementById('bulkProgressDetails');
    }

    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+A - Select all files
            if (e.ctrlKey && e.key === 'a' && !e.target.matches('input, textarea')) {
                e.preventDefault();
                this.selectAllFiles(true);
            }
            
            // Escape - Clear selection
            if (e.key === 'Escape') {
                this.clearSelection();
            }
            
            // Delete - Delete selected files
            if (e.key === 'Delete' && this.selectedFiles.size > 0) {
                e.preventDefault();
                this.executeBulkAction('delete');
            }
            
            // Ctrl+D - Download selected files
            if (e.ctrlKey && e.key === 'd' && this.selectedFiles.size > 0) {
                e.preventDefault();
                this.executeBulkAction('download');
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
        this.updateBulkActionsVisibility();
    }

    selectAllFiles(selectAll) {
        const checkboxes = document.querySelectorAll('.file-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll;
            this.handleFileSelection(checkbox);
        });
    }

    clearSelection() {
        this.selectedFiles.clear();
        document.querySelectorAll('.file-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        this.updateSelectionUI();
        this.updateBulkActionsVisibility();
    }

    updateSelectionUI() {
        const count = this.selectedFiles.size;
        const selectionInfo = document.getElementById('selectionInfo');
        const selectAllCheckbox = document.getElementById('selectAllFiles');

        if (selectionInfo) {
            if (count > 0) {
                selectionInfo.textContent = `${count} file(s) selected`;
                selectionInfo.style.display = 'block';
            } else {
                selectionInfo.style.display = 'none';
            }
        }

        // Update select all checkbox state
        if (selectAllCheckbox) {
            const totalCheckboxes = document.querySelectorAll('.file-checkbox').length;
            selectAllCheckbox.checked = count === totalCheckboxes && count > 0;
            selectAllCheckbox.indeterminate = count > 0 && count < totalCheckboxes;
        }
    }

    updateBulkActionsVisibility() {
        const bulkActions = document.getElementById('bulkActionsContainer');
        if (bulkActions) {
            bulkActions.style.display = this.selectedFiles.size > 0 ? 'block' : 'none';
        }
    }

    executeBulkAction(operation) {
        if (this.selectedFiles.size === 0) {
            showNotification('No files selected', 'warning');
            return;
        }

        if (this.isProcessing) {
            showNotification('Another bulk operation is in progress', 'warning');
            return;
        }

        // Show confirmation dialog for destructive operations
        if (['delete', 'move', 'compress'].includes(operation)) {
            this.showBulkConfirmationDialog(operation);
        } else {
            this.performBulkOperation(operation);
        }
    }

    showBulkConfirmationDialog(operation) {
        const count = this.selectedFiles.size;
        const operationLabels = {
            delete: 'delete',
            move: 'move',
            compress: 'compress'
        };

        const modal = document.getElementById('bulkConfirmationModal');
        if (modal) {
            const message = modal.querySelector('#bulkConfirmationMessage');
            if (message) {
                message.textContent = `Are you sure you want to ${operationLabels[operation]} ${count} file(s)?`;
            }

            const confirmBtn = modal.querySelector('.confirm-bulk-btn');
            if (confirmBtn) {
                confirmBtn.dataset.operation = operation;
            }

            showModal('bulkConfirmationModal');
        } else {
            // Fallback to confirm dialog
            if (confirm(`Are you sure you want to ${operationLabels[operation]} ${count} file(s)?`)) {
                this.performBulkOperation(operation);
            }
        }
    }

    confirmBulkOperation() {
        const confirmBtn = document.querySelector('.confirm-bulk-btn');
        const operation = confirmBtn ? confirmBtn.dataset.operation : '';
        
        this.closeBulkDialog();
        
        if (operation) {
            this.performBulkOperation(operation);
        }
    }

    closeBulkDialog() {
        closeModal('bulkConfirmationModal');
    }

    async performBulkOperation(operation) {
        this.isProcessing = true;
        this.showBulkProgress();
        
        const fileIds = Array.from(this.selectedFiles);
        let results = {
            success: 0,
            failed: 0,
            errors: []
        };

        try {
            switch (operation) {
                case 'download':
                    await this.bulkDownload(fileIds, results);
                    break;
                case 'delete':
                    await this.bulkDelete(fileIds, results);
                    break;
                case 'move':
                    await this.bulkMove(fileIds, results);
                    break;
                case 'compress':
                    await this.bulkCompress(fileIds, results);
                    break;
                case 'share':
                    await this.bulkShare(fileIds, results);
                    break;
                case 'copy':
                    await this.bulkCopy(fileIds, results);
                    break;
                default:
                    throw new Error(`Unknown operation: ${operation}`);
            }
        } catch (error) {
            results.errors.push(`Operation failed: ${error.message}`);
        }

        this.showBulkResults(operation, results);
        this.isProcessing = false;
        this.hideBulkProgress();
        
        // Clear selection after successful operations
        if (results.success > 0) {
            this.clearSelection();
        }
    }

    async bulkDownload(fileIds, results) {
        if (fileIds.length === 1) {
            // Single file download
            window.location.href = `api/files.php?action=download&id=${fileIds[0]}`;
            results.success = 1;
        } else {
            // Create and download zip
            try {
                const response = await fetch('api/compress.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'compress',
                        file_ids: fileIds,
                        download: true,
                        csrf_token: this.getCSRFToken()
                    })
                });

                const data = await response.json();
                if (data.success) {
                    window.location.href = data.download_url;
                    results.success = fileIds.length;
                } else {
                    results.errors.push(data.message || 'Compression failed');
                    results.failed = fileIds.length;
                }
            } catch (error) {
                results.errors.push('Download failed');
                results.failed = fileIds.length;
            }
        }
    }

    async bulkDelete(fileIds, results) {
        this.updateBulkProgress(0, fileIds.length, 'Deleting files...');

        for (let i = 0; i < fileIds.length; i++) {
            const fileId = fileIds[i];
            
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
                    results.success++;
                    this.removeFileFromUI(fileId);
                } else {
                    results.failed++;
                    results.errors.push(`Failed to delete file ${fileId}: ${data.message}`);
                }
            } catch (error) {
                results.failed++;
                results.errors.push(`Failed to delete file ${fileId}: ${error.message}`);
            }

            this.updateBulkProgress(i + 1, fileIds.length, `Deleting files... (${i + 1}/${fileIds.length})`);
        }
    }

    async bulkMove(fileIds, results) {
        // Show directory selection dialog
        const targetDirectory = await this.showDirectorySelector();
        if (!targetDirectory) {
            results.errors.push('No target directory selected');
            return;
        }

        this.updateBulkProgress(0, fileIds.length, 'Moving files...');

        for (let i = 0; i < fileIds.length; i++) {
            const fileId = fileIds[i];
            
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
                    results.success++;
                    this.removeFileFromUI(fileId);
                } else {
                    results.failed++;
                    results.errors.push(`Failed to move file ${fileId}: ${data.message}`);
                }
            } catch (error) {
                results.failed++;
                results.errors.push(`Failed to move file ${fileId}: ${error.message}`);
            }

            this.updateBulkProgress(i + 1, fileIds.length, `Moving files... (${i + 1}/${fileIds.length})`);
        }
    }

    async bulkCompress(fileIds, results) {
        this.updateBulkProgress(0, 1, 'Compressing files...');

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
                results.success = fileIds.length;
                // Optionally download the compressed file
                if (data.download_url) {
                    window.location.href = data.download_url;
                }
            } else {
                results.failed = fileIds.length;
                results.errors.push(data.message || 'Compression failed');
            }
        } catch (error) {
            results.failed = fileIds.length;
            results.errors.push('Compression failed');
        }

        this.updateBulkProgress(1, 1, 'Compression complete');
    }

    async bulkShare(fileIds, results) {
        this.updateBulkProgress(0, fileIds.length, 'Creating share links...');

        const shareLinks = [];

        for (let i = 0; i < fileIds.length; i++) {
            const fileId = fileIds[i];
            
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
                    results.success++;
                    shareLinks.push({
                        fileId: fileId,
                        link: data.share_link,
                        token: data.share_token
                    });
                } else {
                    results.failed++;
                    results.errors.push(`Failed to share file ${fileId}: ${data.message}`);
                }
            } catch (error) {
                results.failed++;
                results.errors.push(`Failed to share file ${fileId}: ${error.message}`);
            }

            this.updateBulkProgress(i + 1, fileIds.length, `Creating share links... (${i + 1}/${fileIds.length})`);
        }

        // Show share links if any were created
        if (shareLinks.length > 0) {
            this.showBulkShareResults(shareLinks);
        }
    }

    async bulkCopy(fileIds, results) {
        // Show directory selection dialog
        const targetDirectory = await this.showDirectorySelector();
        if (!targetDirectory) {
            results.errors.push('No target directory selected');
            return;
        }

        this.updateBulkProgress(0, fileIds.length, 'Copying files...');

        for (let i = 0; i < fileIds.length; i++) {
            const fileId = fileIds[i];
            
            try {
                const response = await fetch('api/files.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'copy',
                        id: fileId,
                        target_directory: targetDirectory,
                        csrf_token: this.getCSRFToken()
                    })
                });

                const data = await response.json();
                if (data.success) {
                    results.success++;
                } else {
                    results.failed++;
                    results.errors.push(`Failed to copy file ${fileId}: ${data.message}`);
                }
            } catch (error) {
                results.failed++;
                results.errors.push(`Failed to copy file ${fileId}: ${error.message}`);
            }

            this.updateBulkProgress(i + 1, fileIds.length, `Copying files... (${i + 1}/${fileIds.length})`);
        }
    }

    showDirectorySelector() {
        return new Promise((resolve) => {
            const modal = document.getElementById('directorySelector');
            if (modal) {
                showModal('directorySelector');
                
                // Set up event listeners for directory selection
                const confirmBtn = modal.querySelector('.confirm-directory-btn');
                const cancelBtn = modal.querySelector('.cancel-directory-btn');
                const directorySelect = modal.querySelector('#targetDirectory');
                
                const handleConfirm = () => {
                    const selectedDirectory = directorySelect ? directorySelect.value : '';
                    closeModal('directorySelector');
                    cleanup();
                    resolve(selectedDirectory);
                };
                
                const handleCancel = () => {
                    closeModal('directorySelector');
                    cleanup();
                    resolve(null);
                };
                
                const cleanup = () => {
                    if (confirmBtn) confirmBtn.removeEventListener('click', handleConfirm);
                    if (cancelBtn) cancelBtn.removeEventListener('click', handleCancel);
                };
                
                if (confirmBtn) confirmBtn.addEventListener('click', handleConfirm);
                if (cancelBtn) cancelBtn.addEventListener('click', handleCancel);
            } else {
                // Fallback to prompt
                const directory = prompt('Enter target directory path:');
                resolve(directory);
            }
        });
    }

    showBulkProgress() {
        if (this.progressContainer) {
            this.progressContainer.style.display = 'block';
        }
    }

    hideBulkProgress() {
        if (this.progressContainer) {
            this.progressContainer.style.display = 'none';
        }
    }

    updateBulkProgress(current, total, message) {
        const progress = total > 0 ? Math.round((current / total) * 100) : 0;
        
        if (this.progressBar) {
            this.progressBar.style.width = `${progress}%`;
        }
        
        if (this.progressText) {
            this.progressText.textContent = `${message} (${progress}%)`;
        }
        
        if (this.progressDetails) {
            this.progressDetails.textContent = `${current} of ${total} completed`;
        }
    }

    showBulkResults(operation, results) {
        const operationLabels = {
            download: 'downloaded',
            delete: 'deleted',
            move: 'moved',
            compress: 'compressed',
            share: 'shared',
            copy: 'copied'
        };

        let message = '';
        let type = 'info';

        if (results.success > 0 && results.failed === 0) {
            message = `Successfully ${operationLabels[operation]} ${results.success} file(s)`;
            type = 'success';
        } else if (results.success === 0 && results.failed > 0) {
            message = `Failed to ${operation} ${results.failed} file(s)`;
            type = 'error';
        } else if (results.success > 0 && results.failed > 0) {
            message = `${operation} completed: ${results.success} successful, ${results.failed} failed`;
            type = 'warning';
        }

        showNotification(message, type);

        // Show detailed errors if any
        if (results.errors.length > 0) {
            console.error('Bulk operation errors:', results.errors);
        }
    }

    showBulkShareResults(shareLinks) {
        const modal = document.getElementById('bulkShareResults');
        if (modal) {
            const content = modal.querySelector('#shareResultsContent');
            if (content) {
                let html = '<div class="share-links-list">';
                shareLinks.forEach(item => {
                    html += `
                        <div class="share-link-item">
                            <div class="file-info">File ID: ${item.fileId}</div>
                            <div class="share-link">
                                <input type="text" value="${item.link}" readonly>
                                <button onclick="copyToClipboard('${item.link}')" class="btn btn-small">Copy</button>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                content.innerHTML = html;
            }
            showModal('bulkShareResults');
        }
    }

    removeFileFromUI(fileId) {
        const fileRow = document.querySelector(`[data-file-id="${fileId}"]`);
        if (fileRow) {
            fileRow.remove();
        }
        this.selectedFiles.delete(fileId);
    }

    cancelBulkOperation() {
        if (this.isProcessing) {
            this.isProcessing = false;
            this.hideBulkProgress();
            showNotification('Bulk operation cancelled', 'info');
        }
    }

    getCSRFToken() {
        const token = document.querySelector('input[name="csrf_token"]') || 
                     document.querySelector('meta[name="csrf-token"]');
        return token ? token.value || token.content : '';
    }

    // Utility methods
    getSelectedCount() {
        return this.selectedFiles.size;
    }

    getSelectedFiles() {
        return Array.from(this.selectedFiles);
    }

    isFileSelected(fileId) {
        return this.selectedFiles.has(fileId);
    }

    selectFile(fileId) {
        this.selectedFiles.add(fileId);
        this.updateSelectionUI();
        this.updateBulkActionsVisibility();
    }

    deselectFile(fileId) {
        this.selectedFiles.delete(fileId);
        this.updateSelectionUI();
        this.updateBulkActionsVisibility();
    }
}

// Initialize bulk operations
document.addEventListener('DOMContentLoaded', () => {
    window.bulkOperations = new BulkOperations();
});

// Global functions for template access
window.executeBulkAction = (operation) => {
    if (window.bulkOperations) {
        window.bulkOperations.executeBulkAction(operation);
    }
};

window.selectAllFiles = (selectAll) => {
    if (window.bulkOperations) {
        window.bulkOperations.selectAllFiles(selectAll);
    }
};

window.clearFileSelection = () => {
    if (window.bulkOperations) {
        window.bulkOperations.clearSelection();
    }
};

window.getSelectedFilesCount = () => {
    return window.bulkOperations ? window.bulkOperations.getSelectedCount() : 0;
};
