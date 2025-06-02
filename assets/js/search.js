// Search functionality JavaScript
class FileSearch {
    constructor() {
        this.searchForm = null;
        this.searchInput = null;
        this.searchResults = null;
        this.searchFilters = {};
        this.currentQuery = '';
        this.searchHistory = [];
        this.maxHistoryItems = 10;
        this.debounceTimer = null;
        this.init();
    }

    init() {
        this.initElements();
        this.bindEvents();
        this.loadSearchHistory();
        this.setupAdvancedFilters();
        this.initAutoComplete();
    }

    initElements() {
        this.searchForm = document.getElementById('searchForm');
        this.searchInput = document.getElementById('searchQuery');
        this.searchResults = document.getElementById('searchResults');
        this.advancedFilters = document.getElementById('advancedFilters');
        this.searchSuggestions = document.getElementById('searchSuggestions');
    }

    bindEvents() {
        // Search form submission
        if (this.searchForm) {
            this.searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.performSearch();
            });
        }

        // Real-time search as user types
        if (this.searchInput) {
            this.searchInput.addEventListener('input', (e) => {
                this.handleSearchInput(e.target.value);
            });

            // Search input focus/blur for suggestions
            this.searchInput.addEventListener('focus', () => {
                this.showSearchSuggestions();
            });

            this.searchInput.addEventListener('blur', () => {
                // Delay hiding to allow click on suggestions
                setTimeout(() => this.hideSearchSuggestions(), 200);
            });

            // Keyboard navigation for suggestions
            this.searchInput.addEventListener('keydown', (e) => {
                this.handleKeyboardNavigation(e);
            });
        }

        // Advanced filter toggles
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('filter-toggle')) {
                this.toggleAdvancedFilters();
            }
        });

        // Filter form changes
        document.addEventListener('change', (e) => {
            if (e.target.closest('#advancedFilters')) {
                this.updateFilters();
            }
        });

        // Clear search button
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('clear-search-btn')) {
                this.clearSearch();
            }
        });

        // Search history clicks
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('search-history-item')) {
                this.executeHistorySearch(e.target.dataset.query);
            }
        });

        // Quick search buttons (predefined searches)
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('quick-search-btn')) {
                this.executeQuickSearch(e.target.dataset.query, e.target.dataset.filters);
            }
        });
    }

    handleSearchInput(value) {
        // Clear previous debounce timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        // Set new debounce timer for real-time search
        this.debounceTimer = setTimeout(() => {
            if (value.length >= 2) {
                this.performLiveSearch(value);
            } else {
                this.clearSearchResults();
            }
        }, 300);

        // Update suggestions immediately
        this.updateSearchSuggestions(value);
    }

    async performSearch() {
        const query = this.searchInput ? this.searchInput.value.trim() : '';
        
        if (!query) {
            showNotification('Please enter a search query', 'warning');
            return;
        }

        this.currentQuery = query;
        this.showSearchLoading();
        this.addToSearchHistory(query);

        try {
            const searchData = this.buildSearchData(query);
            const response = await fetch('api/search.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ...searchData,
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.displaySearchResults(data.results, data.total, data.query);
                this.updateSearchStats(data.stats);
            } else {
                this.displaySearchError(data.message || 'Search failed');
            }
        } catch (error) {
            this.displaySearchError('Search request failed');
        } finally {
            this.hideSearchLoading();
        }
    }

    async performLiveSearch(query) {
        if (query === this.currentQuery) return;

        try {
            const response = await fetch('api/search.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    query: query,
                    live: true,
                    limit: 5,
                    csrf_token: this.getCSRFToken()
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.updateLiveResults(data.results);
            }
        } catch (error) {
            console.error('Live search failed:', error);
        }
    }

    buildSearchData(query) {
        const filters = this.getActiveFilters();
        
        return {
            query: query,
            filters: filters,
            sort: this.getSelectedSort(),
            limit: this.getSearchLimit(),
            offset: this.getCurrentOffset()
        };
    }

    getActiveFilters() {
        const filters = {};
        
        // File type filter
        const fileType = document.querySelector('[name="file_type"]:checked');
        if (fileType && fileType.value !== 'all') {
            filters.file_type = fileType.value;
        }

        // Size filter
        const minSize = document.querySelector('[name="min_size"]');
        const maxSize = document.querySelector('[name="max_size"]');
        if (minSize && minSize.value) {
            filters.min_size = parseInt(minSize.value);
        }
        if (maxSize && maxSize.value) {
            filters.max_size = parseInt(maxSize.value);
        }

        // Date filter
        const dateFrom = document.querySelector('[name="date_from"]');
        const dateTo = document.querySelector('[name="date_to"]');
        if (dateFrom && dateFrom.value) {
            filters.date_from = dateFrom.value;
        }
        if (dateTo && dateTo.value) {
            filters.date_to = dateTo.value;
        }

        // Owner filter (if admin)
        const owner = document.querySelector('[name="owner"]');
        if (owner && owner.value) {
            filters.owner = owner.value;
        }

        // Extension filter
        const extension = document.querySelector('[name="extension"]');
        if (extension && extension.value) {
            filters.extension = extension.value;
        }

        return filters;
    }

    getSelectedSort() {
        const sortSelect = document.querySelector('[name="sort"]');
        return sortSelect ? sortSelect.value : 'relevance';
    }

    getSearchLimit() {
        const limitSelect = document.querySelector('[name="limit"]');
        return limitSelect ? parseInt(limitSelect.value) : 20;
    }

    getCurrentOffset() {
        const urlParams = new URLSearchParams(window.location.search);
        const page = parseInt(urlParams.get('page')) || 1;
        return (page - 1) * this.getSearchLimit();
    }

    displaySearchResults(results, total, query) {
        if (!this.searchResults) return;

        if (results.length === 0) {
            this.displayNoResults(query);
            return;
        }

        let html = `
            <div class="search-header">
                <h3>Search Results</h3>
                <p>Found ${total} file(s) for "${query}"</p>
            </div>
            <div class="search-results-list">
        `;

        results.forEach(file => {
            html += this.renderFileResult(file);
        });

        html += '</div>';

        // Add pagination if needed
        if (total > this.getSearchLimit()) {
            html += this.renderPagination(total);
        }

        this.searchResults.innerHTML = html;
        this.searchResults.style.display = 'block';
    }

    renderFileResult(file) {
        return `
            <div class="search-result-item" data-file-id="${file.id}">
                <div class="file-icon">${this.getFileIcon(file.type)}</div>
                <div class="file-details">
                    <div class="file-name">
                        <a href="api/files.php?action=download&id=${file.id}" target="_blank">
                            ${this.highlightSearchTerm(file.original_name)}
                        </a>
                    </div>
                    <div class="file-meta">
                        <span class="file-size">${formatFileSize(file.size)}</span>
                        <span class="file-type">${file.type}</span>
                        <span class="file-date">${formatDate(file.uploaded_at)}</span>
                        ${file.uploaded_by_username ? `<span class="file-owner">by ${file.uploaded_by_username}</span>` : ''}
                    </div>
                    ${file.description ? `<div class="file-description">${this.highlightSearchTerm(file.description)}</div>` : ''}
                </div>
                <div class="file-actions">
                    <button class="btn btn-small btn-primary" onclick="downloadFile('${file.id}')">
                        Download
                    </button>
                    <button class="btn btn-small btn-secondary" onclick="shareFile('${file.id}')">
                        Share
                    </button>
                    <button class="btn btn-small btn-info" onclick="showFileDetails('${file.id}')">
                        Details
                    </button>
                </div>
            </div>
        `;
    }

    highlightSearchTerm(text) {
        if (!this.currentQuery || !text) return text;
        
        const regex = new RegExp(`(${this.escapeRegex(this.currentQuery)})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    renderPagination(total) {
        const limit = this.getSearchLimit();
        const totalPages = Math.ceil(total / limit);
        const currentPage = Math.floor(this.getCurrentOffset() / limit) + 1;
        
        let html = '<div class="search-pagination">';
        
        if (currentPage > 1) {
            html += `<a href="?query=${encodeURIComponent(this.currentQuery)}&page=${currentPage - 1}" class="pagination-btn">← Previous</a>`;
        }
        
        for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
            const activeClass = i === currentPage ? 'active' : '';
            html += `<a href="?query=${encodeURIComponent(this.currentQuery)}&page=${i}" class="pagination-btn ${activeClass}">${i}</a>`;
        }
        
        if (currentPage < totalPages) {
            html += `<a href="?query=${encodeURIComponent(this.currentQuery)}&page=${currentPage + 1}" class="pagination-btn">Next →</a>`;
        }
        
        html += '</div>';
        return html;
    }

    displayNoResults(query) {
        if (!this.searchResults) return;

        this.searchResults.innerHTML = `
            <div class="no-results">
                <div class="no-results-icon">🔍</div>
                <h3>No files found</h3>
                <p>No files match your search for "${query}"</p>
                <div class="search-suggestions">
                    <p>Try:</p>
                    <ul>
                        <li>Checking your spelling</li>
                        <li>Using different keywords</li>
                        <li>Removing some filters</li>
                        <li>Searching for file extensions (e.g., .pdf, .jpg)</li>
                    </ul>
                </div>
            </div>
        `;
        this.searchResults.style.display = 'block';
    }

    displaySearchError(message) {
        if (!this.searchResults) return;

        this.searchResults.innerHTML = `
            <div class="search-error">
                <div class="error-icon">⚠️</div>
                <h3>Search Error</h3>
                <p>${message}</p>
                <button class="btn btn-primary" onclick="location.reload()">Try Again</button>
            </div>
        `;
        this.searchResults.style.display = 'block';
    }

    updateSearchSuggestions(query) {
        if (!this.searchSuggestions || !query) return;

        const suggestions = this.generateSuggestions(query);
        
        if (suggestions.length === 0) {
            this.hideSearchSuggestions();
            return;
        }

        let html = '<div class="suggestions-list">';
        suggestions.forEach(suggestion => {
            html += `
                <div class="suggestion-item" data-query="${suggestion.query}">
                    <span class="suggestion-icon">${suggestion.icon}</span>
                    <span class="suggestion-text">${suggestion.text}</span>
                </div>
            `;
        });
        html += '</div>';

        this.searchSuggestions.innerHTML = html;
        this.showSearchSuggestions();
    }

    generateSuggestions(query) {
        const suggestions = [];
        
        // File extension suggestions
        const extensions = ['.pdf', '.doc', '.jpg', '.png', '.txt', '.zip'];
        extensions.forEach(ext => {
            if (ext.includes(query.toLowerCase())) {
                suggestions.push({
                    query: `extension:${ext}`,
                    text: `Files with ${ext} extension`,
                    icon: '📄'
                });
            }
        });

        // Search history suggestions
        this.searchHistory.forEach(historyItem => {
            if (historyItem.toLowerCase().includes(query.toLowerCase()) && 
                historyItem !== query) {
                suggestions.push({
                    query: historyItem,
                    text: historyItem,
                    icon: '🕐'
                });
            }
        });

        return suggestions.slice(0, 5);
    }

    showSearchSuggestions() {
        if (this.searchSuggestions) {
            this.searchSuggestions.style.display = 'block';
        }
    }

    hideSearchSuggestions() {
        if (this.searchSuggestions) {
            this.searchSuggestions.style.display = 'none';
        }
    }

    addToSearchHistory(query) {
        if (!query || this.searchHistory.includes(query)) return;

        this.searchHistory.unshift(query);
        
        // Limit history size
        if (this.searchHistory.length > this.maxHistoryItems) {
            this.searchHistory = this.searchHistory.slice(0, this.maxHistoryItems);
        }

        this.saveSearchHistory();
    }

    saveSearchHistory() {
        localStorage.setItem('fileserver-search-history', JSON.stringify(this.searchHistory));
    }

    loadSearchHistory() {
        const saved = localStorage.getItem('fileserver-search-history');
        if (saved) {
            try {
                this.searchHistory = JSON.parse(saved);
            } catch (error) {
                this.searchHistory = [];
            }
        }
    }

    clearSearch() {
        if (this.searchInput) {
            this.searchInput.value = '';
        }
        this.clearSearchResults();
        this.hideSearchSuggestions();
        this.currentQuery = '';
    }

    clearSearchResults() {
        if (this.searchResults) {
            this.searchResults.style.display = 'none';
            this.searchResults.innerHTML = '';
        }
    }

    toggleAdvancedFilters() {
        if (this.advancedFilters) {
            const isVisible = this.advancedFilters.style.display === 'block';
            this.advancedFilters.style.display = isVisible ? 'none' : 'block';
            
            const toggleBtn = document.querySelector('.filter-toggle');
            if (toggleBtn) {
                toggleBtn.textContent = isVisible ? 'Show Advanced Filters' : 'Hide Advanced Filters';
            }
        }
    }

    showSearchLoading() {
        if (this.searchResults) {
            this.searchResults.innerHTML = `
                <div class="search-loading">
                    <div class="loading-spinner"></div>
                    <p>Searching files...</p>
                </div>
            `;
            this.searchResults.style.display = 'block';
        }
    }

    hideSearchLoading() {
        // Loading will be replaced by results or error
    }

    getFileIcon(mimeType) {
        const iconMap = {
            'image/': '🖼️',
            'video/': '🎥',
            'audio/': '🎵',
            'application/pdf': '📄',
            'application/zip': '📦',
            'text/': '📝',
            'application/msword': '📄',
            'application/vnd.ms-excel': '📊',
            'application/vnd.ms-powerpoint': '📊'
        };

        for (const [type, icon] of Object.entries(iconMap)) {
            if (mimeType.startsWith(type)) {
                return icon;
            }
        }

        return '📁';
    }

    getCSRFToken() {
        const token = document.querySelector('input[name="csrf_token"]') || 
                     document.querySelector('meta[name="csrf-token"]');
        return token ? token.value || token.content : '';
    }
}

// Initialize search functionality
document.addEventListener('DOMContentLoaded', () => {
    window.fileSearch = new FileSearch();
});

// Global functions for template access
window.performSearch = () => {
    if (window.fileSearch) {
        window.fileSearch.performSearch();
    }
};

window.clearSearch = () => {
    if (window.fileSearch) {
        window.fileSearch.clearSearch();
    }
};

window.toggleAdvancedSearch = () => {
    if (window.fileSearch) {
        window.fileSearch.toggleAdvancedFilters();
    }
};
