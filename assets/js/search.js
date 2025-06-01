/**
 * Search Page JavaScript
 * Handles search functionality, filters, results display, and advanced search features
 */

class SearchPage {
    constructor() {
        this.currentQuery = '';
        this.currentFilters = {};
        this.currentSort = 'relevance';
        this.currentPage = 1;
        this.itemsPerPage = 20;
        this.searchTimeout = null;
        this.searchHistory = this.loadSearchHistory();
        this.savedSearches = this.loadSavedSearches();
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeFilters();
        this.loadSearchHistory();
        this.loadSavedSearches();
        
        // Check for URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const query = urlParams.get('q');
        if (query) {
            document.getElementById('searchInput').value = query;
            this.performSearch(query);
        }
    }

    bindEvents() {
        // Search input
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    if (e.target.value.trim()) {
                        this.performSearch(e.target.value.trim());
                    } else {
                        this.clearResults();
                    }
                }, 300);
            });

            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(this.searchTimeout);
                    this.performSearch(e.target.value.trim());
                }
            });
        }

        // Advanced search toggle
        const advancedToggle = document.getElementById('advancedSearchToggle');
        if (advancedToggle) {
            advancedToggle.addEventListener('click', () => {
                this.toggleAdvancedSearch();
            });
        }

        // Filter change events
        document.querySelectorAll('.filter-input').forEach(input => {
            input.addEventListener('change', () => {
                this.updateFilters();
            });
        });

        // Sort change
        const sortSelect = document.getElementById('sortSelect');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                this.currentSort = e.target.value;
                if (this.currentQuery) {
                    this.performSearch(this.currentQuery);
                }
            });
        }

        // Clear filters
        const clearFiltersBtn = document.getElementById('clearFilters');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => {
                this.clearFilters();
            });
        }

        // Save search
        const saveSearchBtn = document.getElementById('saveSearch');
        if (saveSearchBtn) {
            saveSearchBtn.addEventListener('click', () => {
                this.showSaveSearchModal();
            });
        }

        // Pagination
        document.addEventListener('click', (e) => {
            if (e.target.matches('.pagination-btn')) {
                const page = parseInt(e.target.dataset.page);
                if (page && page !== this.currentPage) {
                    this.currentPage = page;
                    this.performSearch(this.currentQuery);
                }
            }
        });

        // View toggle
        document.querySelectorAll('.view-toggle').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const view = e.target.dataset.view;
                this.toggleView(view);
            });
        });

        // Search suggestions
        searchInput?.addEventListener('focus', () => {
            this.showSearchSuggestions();
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-container')) {
                this.hideSearchSuggestions();
            }
        });
    }

    initializeFilters() {
        // Initialize date pickers
        const dateInputs = document.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
            if (!input.value) {
                // Set default date ranges
                if (input.name === 'dateFrom') {
                    const date = new Date();
                    date.setMonth(date.getMonth() - 1);
                    input.value = date.toISOString().split('T')[0];
                } else if (input.name === 'dateTo') {
                    input.value = new Date().toISOString().split('T')[0];
                }
            }
        });

        // Initialize size range
        this.initializeSizeRange();
    }

    initializeSizeRange() {
        const sizeRange = document.getElementById('sizeRange');
        if (sizeRange) {
            sizeRange.addEventListener('input', (e) => {
                const value = e.target.value;
                const sizeDisplay = document.getElementById('sizeDisplay');
                if (sizeDisplay) {
                    sizeDisplay.textContent = this.formatFileSize(value * 1024 * 1024);
                }
            });
        }
    }

    async performSearch(query) {
        if (!query.trim()) {
            this.clearResults();
            return;
        }

        this.currentQuery = query;
        this.showSearchLoading();
        this.addToSearchHistory(query);

        try {
            const params = new URLSearchParams({
                q: query,
                page: this.currentPage,
                limit: this.itemsPerPage,
                sort: this.currentSort,
                ...this.currentFilters
            });

            const response = await window.app.api.request(`/search?${params}`, {
                method: 'GET'
            });

            if (response.success) {
                this.displayResults(response.data);
                this.updatePagination(response.pagination);
                this.updateURL(query);
            } else {
                throw new Error(response.message || 'Search failed');
            }
        } catch (error) {
            console.error('Search error:', error);
            window.app.showNotification('Search failed: ' + error.message, 'error');
            this.showSearchError(error.message);
        } finally {
            this.hideSearchLoading();
        }
    }

    displayResults(results) {
        const resultsContainer = document.getElementById('searchResults');
        if (!resultsContainer) return;

        if (!results || results.length === 0) {
            this.showNoResults();
            return;
        }

        const isGridView = document.querySelector('.view-toggle.active')?.dataset.view === 'grid';
        
        resultsContainer.innerHTML = results.map(file => {
            return isGridView ? this.renderGridItem(file) : this.renderListItem(file);
        }).join('');

        // Update results count
        const resultsCount = document.getElementById('resultsCount');
        if (resultsCount) {
            resultsCount.textContent = `Found ${results.length} files`;
        }

        // Bind result actions
        this.bindResultActions();
    }

    renderGridItem(file) {
        const thumbnail = file.thumbnail || this.getFileIcon(file.extension);
        const size = this.formatFileSize(file.size);
        const date = new Date(file.created_at).toLocaleDateString();

        return `
            <div class="search-result-item grid-item" data-id="${file.id}">
                <div class="file-thumbnail">
                    <img src="${thumbnail}" alt="${file.name}" onerror="this.src='${this.getFileIcon(file.extension)}'">
                    <div class="file-overlay">
                        <div class="file-actions">
                            <button class="btn btn-sm btn-primary download-btn" data-id="${file.id}">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="btn btn-sm btn-secondary preview-btn" data-id="${file.id}">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="file-info">
                    <h6 class="file-name" title="${file.name}">${this.truncateText(file.name, 30)}</h6>
                    <p class="file-meta">
                        <span class="file-size">${size}</span>
                        <span class="file-date">${date}</span>
                    </p>
                    ${file.description ? `<p class="file-description">${this.truncateText(file.description, 50)}</p>` : ''}
                </div>
            </div>
        `;
    }

    renderListItem(file) {
        const size = this.formatFileSize(file.size);
        const date = new Date(file.created_at).toLocaleDateString();
        const icon = this.getFileIcon(file.extension);

        return `
            <div class="search-result-item list-item" data-id="${file.id}">
                <div class="file-icon">
                    <img src="${icon}" alt="${file.extension}">
                </div>
                <div class="file-details">
                    <h6 class="file-name">${file.name}</h6>
                    ${file.description ? `<p class="file-description">${file.description}</p>` : ''}
                    <div class="file-meta">
                        <span class="file-size">${size}</span>
                        <span class="file-date">${date}</span>
                        <span class="file-owner">${file.owner || 'Unknown'}</span>
                        ${file.tags ? `<span class="file-tags">${file.tags.join(', ')}</span>` : ''}
                    </div>
                </div>
                <div class="file-actions">
                    <button class="btn btn-sm btn-primary download-btn" data-id="${file.id}">
                        <i class="fas fa-download"></i> Download
                    </button>
                    <button class="btn btn-sm btn-secondary preview-btn" data-id="${file.id}">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item info-btn" href="#" data-id="${file.id}">
                                <i class="fas fa-info-circle"></i> Details
                            </a></li>
                            <li><a class="dropdown-item share-btn" href="#" data-id="${file.id}">
                                <i class="fas fa-share"></i> Share
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        `;
    }

    bindResultActions() {
        // Download buttons
        document.querySelectorAll('.download-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const fileId = e.target.closest('[data-id]').dataset.id;
                this.downloadFile(fileId);
            });
        });

        // Preview buttons
        document.querySelectorAll('.preview-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const fileId = e.target.closest('[data-id]').dataset.id;
                this.previewFile(fileId);
            });
        });

        // Info buttons
        document.querySelectorAll('.info-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const fileId = e.target.closest('[data-id]').dataset.id;
                this.showFileInfo(fileId);
            });
        });

        // Share buttons
        document.querySelectorAll('.share-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const fileId = e.target.closest('[data-id]').dataset.id;
                this.shareFile(fileId);
            });
        });
    }

    async downloadFile(fileId) {
        try {
            const response = await window.app.api.request(`/files/${fileId}/download`, {
                method: 'GET'
            });

            if (response.success) {
                // Create download link
                const link = document.createElement('a');
                link.href = response.data.download_url;
                link.download = response.data.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                throw new Error(response.message || 'Download failed');
            }
        } catch (error) {
            console.error('Download error:', error);
            window.app.showNotification('Download failed: ' + error.message, 'error');
        }
    }

    async previewFile(fileId) {
        try {
            const response = await window.app.api.request(`/files/${fileId}`, {
                method: 'GET'
            });

            if (response.success) {
                this.showPreviewModal(response.data);
            } else {
                throw new Error(response.message || 'Preview failed');
            }
        } catch (error) {
            console.error('Preview error:', error);
            window.app.showNotification('Preview failed: ' + error.message, 'error');
        }
    }

    showPreviewModal(file) {
        const modal = document.getElementById('previewModal') || this.createPreviewModal();
        const modalBody = modal.querySelector('.modal-body');
        
        if (this.isImageFile(file.extension)) {
            modalBody.innerHTML = `<img src="/api/files/${file.id}/preview" class="img-fluid" alt="${file.name}">`;
        } else if (this.isVideoFile(file.extension)) {
            modalBody.innerHTML = `
                <video controls class="w-100">
                    <source src="/api/files/${file.id}/preview" type="video/${file.extension}">
                    Your browser does not support the video tag.
                </video>
            `;
        } else if (this.isTextFile(file.extension)) {
            modalBody.innerHTML = '<div class="text-preview loading">Loading...</div>';
            this.loadTextPreview(file.id);
        } else {
            modalBody.innerHTML = `
                <div class="text-center">
                    <i class="fas fa-file fa-5x text-muted mb-3"></i>
                    <p>Preview not available for this file type.</p>
                    <button class="btn btn-primary download-btn" data-id="${file.id}">
                        <i class="fas fa-download"></i> Download File
                    </button>
                </div>
            `;
        }

        modal.querySelector('.modal-title').textContent = file.name;
        new bootstrap.Modal(modal).show();
    }

    createPreviewModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'previewModal';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">File Preview</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body"></div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        return modal;
    }

    async loadTextPreview(fileId) {
        try {
            const response = await window.app.api.request(`/files/${fileId}/preview`, {
                method: 'GET'
            });

            if (response.success) {
                const textPreview = document.querySelector('.text-preview');
                if (textPreview) {
                    textPreview.className = 'text-preview';
                    textPreview.innerHTML = `<pre><code>${this.escapeHtml(response.data.content)}</code></pre>`;
                }
            }
        } catch (error) {
            console.error('Text preview error:', error);
            const textPreview = document.querySelector('.text-preview');
            if (textPreview) {
                textPreview.innerHTML = '<p class="text-danger">Failed to load preview</p>';
            }
        }
    }

    updateFilters() {
        const filters = {};
        
        // File type filter
        const fileType = document.getElementById('fileTypeFilter')?.value;
        if (fileType) filters.type = fileType;

        // Size filter
        const maxSize = document.getElementById('sizeRange')?.value;
        if (maxSize) filters.max_size = maxSize * 1024 * 1024;

        // Date filters
        const dateFrom = document.getElementById('dateFrom')?.value;
        if (dateFrom) filters.date_from = dateFrom;

        const dateTo = document.getElementById('dateTo')?.value;
        if (dateTo) filters.date_to = dateTo;

        // Owner filter
        const owner = document.getElementById('ownerFilter')?.value;
        if (owner) filters.owner = owner;

        // Tags filter
        const tags = document.getElementById('tagsFilter')?.value;
        if (tags) filters.tags = tags;

        this.currentFilters = filters;
        
        if (this.currentQuery) {
            this.currentPage = 1;
            this.performSearch(this.currentQuery);
        }
    }

    clearFilters() {
        document.querySelectorAll('.filter-input').forEach(input => {
            if (input.type === 'select-one') {
                input.selectedIndex = 0;
            } else {
                input.value = '';
            }
        });

        this.currentFilters = {};
        
        if (this.currentQuery) {
            this.currentPage = 1;
            this.performSearch(this.currentQuery);
        }
    }

    toggleAdvancedSearch() {
        const advancedPanel = document.getElementById('advancedSearchPanel');
        const toggleBtn = document.getElementById('advancedSearchToggle');
        
        if (advancedPanel) {
            const isVisible = advancedPanel.style.display !== 'none';
            advancedPanel.style.display = isVisible ? 'none' : 'block';
            
            if (toggleBtn) {
                toggleBtn.innerHTML = isVisible ? 
                    '<i class="fas fa-chevron-down"></i> Advanced Search' :
                    '<i class="fas fa-chevron-up"></i> Hide Advanced';
            }
        }
    }

    toggleView(view) {
        document.querySelectorAll('.view-toggle').forEach(btn => {
            btn.classList.remove('active');
        });
        
        document.querySelector(`[data-view="${view}"]`).classList.add('active');
        
        // Re-render current results
        if (this.currentQuery) {
            this.performSearch(this.currentQuery);
        }
    }

    showSearchSuggestions() {
        const suggestionsContainer = document.getElementById('searchSuggestions');
        if (!suggestionsContainer) return;

        const suggestions = [
            ...this.searchHistory.slice(0, 5),
            ...this.savedSearches.map(s => s.query).slice(0, 3)
        ];

        if (suggestions.length > 0) {
            suggestionsContainer.innerHTML = suggestions.map(suggestion => 
                `<div class="search-suggestion" data-query="${suggestion}">${suggestion}</div>`
            ).join('');
            
            suggestionsContainer.style.display = 'block';
            
            // Bind suggestion clicks
            suggestionsContainer.querySelectorAll('.search-suggestion').forEach(item => {
                item.addEventListener('click', (e) => {
                    const query = e.target.dataset.query;
                    document.getElementById('searchInput').value = query;
                    this.performSearch(query);
                    this.hideSearchSuggestions();
                });
            });
        }
    }

    hideSearchSuggestions() {
        const suggestionsContainer = document.getElementById('searchSuggestions');
        if (suggestionsContainer) {
            suggestionsContainer.style.display = 'none';
        }
    }

    addToSearchHistory(query) {
        if (!this.searchHistory.includes(query)) {
            this.searchHistory.unshift(query);
            this.searchHistory = this.searchHistory.slice(0, 10); // Keep only 10 recent searches
            this.saveSearchHistory();
        }
    }

    saveSearchHistory() {
        localStorage.setItem('searchHistory', JSON.stringify(this.searchHistory));
    }

    loadSearchHistory() {
        try {
            return JSON.parse(localStorage.getItem('searchHistory') || '[]');
        } catch (error) {
            return [];
        }
    }

    loadSavedSearches() {
        try {
            return JSON.parse(localStorage.getItem('savedSearches') || '[]');
        } catch (error) {
            return [];
        }
    }

    showSaveSearchModal() {
        if (!this.currentQuery) {
            window.app.showNotification('No search query to save', 'warning');
            return;
        }

        const modal = document.getElementById('saveSearchModal') || this.createSaveSearchModal();
        document.getElementById('saveSearchQuery').value = this.currentQuery;
        new bootstrap.Modal(modal).show();
    }

    createSaveSearchModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'saveSearchModal';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Save Search</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="saveSearchForm">
                            <div class="mb-3">
                                <label for="saveSearchName" class="form-label">Search Name</label>
                                <input type="text" class="form-control" id="saveSearchName" required>
                            </div>
                            <div class="mb-3">
                                <label for="saveSearchQuery" class="form-label">Query</label>
                                <input type="text" class="form-control" id="saveSearchQuery" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="saveSearchDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="saveSearchDescription" rows="3"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmSaveSearch">Save Search</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Bind save action
        modal.querySelector('#confirmSaveSearch').addEventListener('click', () => {
            this.saveSearch();
        });

        return modal;
    }

    saveSearch() {
        const name = document.getElementById('saveSearchName').value.trim();
        const query = document.getElementById('saveSearchQuery').value;
        const description = document.getElementById('saveSearchDescription').value.trim();

        if (!name) {
            window.app.showNotification('Please enter a name for the search', 'warning');
            return;
        }

        const savedSearch = {
            id: Date.now(),
            name,
            query,
            description,
            filters: { ...this.currentFilters },
            created_at: new Date().toISOString()
        };

        this.savedSearches.push(savedSearch);
        localStorage.setItem('savedSearches', JSON.stringify(this.savedSearches));

        bootstrap.Modal.getInstance(document.getElementById('saveSearchModal')).hide();
        window.app.showNotification('Search saved successfully', 'success');
    }

    updatePagination(pagination) {
        const paginationContainer = document.getElementById('searchPagination');
        if (!paginationContainer || !pagination) return;

        const { current_page, total_pages, total_items } = pagination;
        
        if (total_pages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        let paginationHTML = '<nav><ul class="pagination">';
        
        // Previous button
        if (current_page > 1) {
            paginationHTML += `<li class="page-item">
                <button class="page-link pagination-btn" data-page="${current_page - 1}">Previous</button>
            </li>`;
        }

        // Page numbers
        const startPage = Math.max(1, current_page - 2);
        const endPage = Math.min(total_pages, current_page + 2);

        if (startPage > 1) {
            paginationHTML += `<li class="page-item">
                <button class="page-link pagination-btn" data-page="1">1</button>
            </li>`;
            if (startPage > 2) {
                paginationHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationHTML += `<li class="page-item ${i === current_page ? 'active' : ''}">
                <button class="page-link pagination-btn" data-page="${i}">${i}</button>
            </li>`;
        }

        if (endPage < total_pages) {
            if (endPage < total_pages - 1) {
                paginationHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            paginationHTML += `<li class="page-item">
                <button class="page-link pagination-btn" data-page="${total_pages}">${total_pages}</button>
            </li>`;
        }

        // Next button
        if (current_page < total_pages) {
            paginationHTML += `<li class="page-item">
                <button class="page-link pagination-btn" data-page="${current_page + 1}">Next</button>
            </li>`;
        }

        paginationHTML += '</ul></nav>';
        paginationContainer.innerHTML = paginationHTML;

        // Update results info
        const resultsInfo = document.getElementById('resultsInfo');
        if (resultsInfo) {
            const start = (current_page - 1) * this.itemsPerPage + 1;
            const end = Math.min(current_page * this.itemsPerPage, total_items);
            resultsInfo.textContent = `Showing ${start}-${end} of ${total_items} results`;
        }
    }

    updateURL(query) {
        const url = new URL(window.location);
        url.searchParams.set('q', query);
        window.history.replaceState({}, '', url);
    }

    clearResults() {
        const resultsContainer = document.getElementById('searchResults');
        if (resultsContainer) {
            resultsContainer.innerHTML = '';
        }

        const resultsCount = document.getElementById('resultsCount');
        if (resultsCount) {
            resultsCount.textContent = '';
        }

        const paginationContainer = document.getElementById('searchPagination');
        if (paginationContainer) {
            paginationContainer.innerHTML = '';
        }
    }

    showSearchLoading() {
        const resultsContainer = document.getElementById('searchResults');
        if (resultsContainer) {
            resultsContainer.innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Searching...</span>
                    </div>
                    <p class="mt-2">Searching files...</p>
                </div>
            `;
        }
    }

    hideSearchLoading() {
        // Loading will be replaced by results or error message
    }

    showNoResults() {
        const resultsContainer = document.getElementById('searchResults');
        if (resultsContainer) {
            resultsContainer.innerHTML = `
                <div class="text-center p-4">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5>No results found</h5>
                    <p>Try adjusting your search terms or filters.</p>
                </div>
            `;
        }
    }

    showSearchError(message) {
        const resultsContainer = document.getElementById('searchResults');
        if (resultsContainer) {
            resultsContainer.innerHTML = `
                <div class="text-center p-4">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h5>Search Error</h5>
                    <p>${message}</p>
                    <button class="btn btn-primary" onclick="location.reload()">Retry</button>
                </div>
            `;
        }
    }

    // Utility methods
    getFileIcon(extension) {
        const iconMap = {
            pdf: '/assets/icons/pdf.svg',
            doc: '/assets/icons/doc.svg',
            docx: '/assets/icons/doc.svg',
            xls: '/assets/icons/excel.svg',
            xlsx: '/assets/icons/excel.svg',
            ppt: '/assets/icons/ppt.svg',
            pptx: '/assets/icons/ppt.svg',
            jpg: '/assets/icons/image.svg',
            jpeg: '/assets/icons/image.svg',
            png: '/assets/icons/image.svg',
            gif: '/assets/icons/image.svg',
            mp4: '/assets/icons/video.svg',
            avi: '/assets/icons/video.svg',
            mp3: '/assets/icons/audio.svg',
            wav: '/assets/icons/audio.svg',
            zip: '/assets/icons/archive.svg',
            rar: '/assets/icons/archive.svg',
            txt: '/assets/icons/text.svg',
            md: '/assets/icons/text.svg'
        };
        return iconMap[extension.toLowerCase()] || '/assets/icons/file.svg';
    }

    isImageFile(extension) {
        return ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(extension.toLowerCase());
    }

    isVideoFile(extension) {
        return ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'].includes(extension.toLowerCase());
    }

    isTextFile(extension) {
        return ['txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'php', 'py'].includes(extension.toLowerCase());
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    truncateText(text, maxLength) {
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize search page when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('searchPage')) {
        window.searchPage = new SearchPage();
    }
});
