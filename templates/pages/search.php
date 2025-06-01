<?php
/**
 * Search Page Template
 * Advanced file search interface with filters and results
 */

defined('FILESERVER_ACCESS') or die('Direct access denied');

$query = $data['query'] ?? '';
$results = $data['results'] ?? [];
$totalResults = $data['total'] ?? 0;
$currentPage = $data['page'] ?? 1;
$filters = $data['filters'] ?? [];
?>

<div class="container-fluid">
    <div class="row">
        <!-- Search Filters Sidebar -->
        <div class="col-lg-3">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-filter"></i> Search Filters</h6>
                </div>
                <div class="card-body">
                    <form id="searchFilters">
                        <!-- File Type Filter -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">File Type</label>
                            <div class="filter-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="type_all" name="type[]" value="all" checked>
                                    <label class="form-check-label" for="type_all">All Files</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="type_image" name="type[]" value="image">
                                    <label class="form-check-label" for="type_image">
                                        <i class="fas fa-image text-success"></i> Images
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="type_document" name="type[]" value="document">
                                    <label class="form-check-label" for="type_document">
                                        <i class="fas fa-file-alt text-primary"></i> Documents
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="type_video" name="type[]" value="video">
                                    <label class="form-check-label" for="type_video">
                                        <i class="fas fa-video text-danger"></i> Videos
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="type_audio" name="type[]" value="audio">
                                    <label class="form-check-label" for="type_audio">
                                        <i class="fas fa-music text-warning"></i> Audio
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="type_archive" name="type[]" value="archive">
                                    <label class="form-check-label" for="type_archive">
                                        <i class="fas fa-file-archive text-info"></i> Archives
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Size Filter -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">File Size</label>
                            <div class="filter-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="size_all" name="size" value="all" checked>
                                    <label class="form-check-label" for="size_all">Any Size</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="size_small" name="size" value="small">
                                    <label class="form-check-label" for="size_small">Small (&lt; 1MB)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="size_medium" name="size" value="medium">
                                    <label class="form-check-label" for="size_medium">Medium (1MB - 10MB)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="size_large" name="size" value="large">
                                    <label class="form-check-label" for="size_large">Large (&gt; 10MB)</label>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label small">Custom Range</label>
                                <div class="row">
                                    <div class="col-6">
                                        <input type="number" class="form-control form-control-sm" id="sizeMin" name="size_min" placeholder="Min MB">
                                    </div>
                                    <div class="col-6">
                                        <input type="number" class="form-control form-control-sm" id="sizeMax" name="size_max" placeholder="Max MB">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Date Filter -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Upload Date</label>
                            <div class="filter-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="date_all" name="date" value="all" checked>
                                    <label class="form-check-label" for="date_all">Any Time</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="date_today" name="date" value="today">
                                    <label class="form-check-label" for="date_today">Today</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="date_week" name="date" value="week">
                                    <label class="form-check-label" for="date_week">This Week</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="date_month" name="date" value="month">
                                    <label class="form-check-label" for="date_month">This Month</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="date_year" name="date" value="year">
                                    <label class="form-check-label" for="date_year">This Year</label>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label small">Custom Range</label>
                                <input type="date" class="form-control form-control-sm mb-2" id="dateFrom" name="date_from">
                                <input type="date" class="form-control form-control-sm" id="dateTo" name="date_to">
                            </div>
                        </div>

                        <!-- Owner Filter -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Owner</label>
                            <div class="filter-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="owner_all" name="owner" value="all" checked>
                                    <label class="form-check-label" for="owner_all">Anyone</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="owner_me" name="owner" value="me">
                                    <label class="form-check-label" for="owner_me">My Files</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="owner_shared" name="owner" value="shared">
                                    <label class="form-check-label" for="owner_shared">Shared with Me</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary" id="applyFilters">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="clearFilters">
                                <i class="fas fa-times"></i> Clear All
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Saved Searches -->
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bookmark"></i> Saved Searches</h6>
                </div>
                <div class="card-body">
                    <div id="savedSearches">
                        <!-- Saved searches will be loaded here -->
                        <p class="text-muted small">No saved searches yet</p>
                    </div>
                    <?php if (!empty($query)): ?>
                    <button class="btn btn-outline-primary btn-sm w-100 mt-2" id="saveCurrentSearch">
                        <i class="fas fa-bookmark"></i> Save Current Search
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Search Results -->
        <div class="col-lg-9">
            <!-- Search Bar -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="search-container">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" id="searchQuery" 
                                   placeholder="Search files by name, content, or tags..." 
                                   value="<?= htmlspecialchars($query) ?>">
                            <button class="btn btn-primary" type="button" id="searchButton">
                                Search
                            </button>
                        </div>
                        
                        <!-- Search Suggestions -->
                        <div id="searchSuggestions" class="search-suggestions" style="display: none;">
                            <!-- Suggestions will be populated here -->
                        </div>
                        
                        <!-- Quick Filters -->
                        <div class="mt-3">
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-light text-dark filter-tag" data-filter="type:image">
                                    <i class="fas fa-image"></i> Images
                                </span>
                                <span class="badge bg-light text-dark filter-tag" data-filter="type:document">
                                    <i class="fas fa-file-alt"></i> Documents
                                </span>
                                <span class="badge bg-light text-dark filter-tag" data-filter="size:large">
                                    <i class="fas fa-weight-hanging"></i> Large Files
                                </span>
                                <span class="badge bg-light text-dark filter-tag" data-filter="date:week">
                                    <i class="fas fa-calendar-week"></i> This Week
                                </span>
                                <span class="badge bg-light text-dark filter-tag" data-filter="owner:me">
                                    <i class="fas fa-user"></i> My Files
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search Results Header -->
            <?php if (!empty($query) || !empty($filters)): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1">Search Results</h5>
                    <p class="text-muted mb-0">
                        <?php if ($totalResults > 0): ?>
                            Found <?= number_format($totalResults) ?> 
                            result<?= $totalResults !== 1 ? 's' : '' ?>
                            <?php if (!empty($query)): ?>
                                for "<?= htmlspecialchars($query) ?>"
                            <?php endif; ?>
                        <?php else: ?>
                            No results found
                            <?php if (!empty($query)): ?>
                                for "<?= htmlspecialchars($query) ?>"
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <!-- Sort Options -->
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-sort"></i> Sort
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-sort="relevance">Relevance</a></li>
                            <li><a class="dropdown-item" href="#" data-sort="name">Name</a></li>
                            <li><a class="dropdown-item" href="#" data-sort="date">Upload Date</a></li>
                            <li><a class="dropdown-item" href="#" data-sort="size">File Size</a></li>
                            <li><a class="dropdown-item" href="#" data-sort="downloads">Downloads</a></li>
                        </ul>
                    </div>
                    
                    <!-- View Options -->
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary active" id="gridView">
                            <i class="fas fa-th"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="listView">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Search Results Content -->
            <div id="searchResults">
                <?php if (empty($query) && empty($filters)): ?>
                    <!-- Search Landing -->
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Search Your Files</h4>
                        <p class="text-muted">Enter a search term or use filters to find your files</p>
                        
                        <!-- Popular Searches -->
                        <div class="mt-4">
                            <h6 class="text-muted mb-3">Popular Searches</h6>
                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                <span class="badge bg-primary popular-search" data-query="image">Images</span>
                                <span class="badge bg-primary popular-search" data-query="document">Documents</span>
                                <span class="badge bg-primary popular-search" data-query="pdf">PDF Files</span>
                                <span class="badge bg-primary popular-search" data-query="video">Videos</span>
                                <span class="badge bg-primary popular-search" data-query="presentation">Presentations</span>
                            </div>
                        </div>
                    </div>
                
                <?php elseif (empty($results)): ?>
                    <!-- No Results -->
                    <div class="text-center py-5">
                        <i class="fas fa-search-minus fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">No Results Found</h4>
                        <p class="text-muted">Try adjusting your search terms or filters</p>
                        
                        <!-- Search Suggestions -->
                        <div class="mt-4">
                            <h6 class="text-muted mb-3">Search Tips</h6>
                            <ul class="list-unstyled text-muted">
                                <li><i class="fas fa-lightbulb text-warning"></i> Try different keywords</li>
                                <li><i class="fas fa-lightbulb text-warning"></i> Check your spelling</li>
                                <li><i class="fas fa-lightbulb text-warning"></i> Use fewer filters</li>
                                <li><i class="fas fa-lightbulb text-warning"></i> Search for file extensions (e.g., ".pdf")</li>
                            </ul>
                        </div>
                    </div>
                
                <?php else: ?>
                    <!-- Results Grid -->
                    <div id="resultsContainer" class="results-grid">
                        <?php foreach ($results as $file): ?>
                        <div class="file-card card shadow-sm h-100" data-file-id="<?= $file['id'] ?>">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-start">
                                    <!-- File Icon/Thumbnail -->
                                    <div class="file-icon me-3">
                                        <?php if (in_array(strtolower($file['extension'] ?? ''), ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                            <img src="/api/files/<?= $file['id'] ?>/thumbnail" 
                                                 class="img-thumbnail" width="60" height="60" 
                                                 alt="<?= htmlspecialchars($file['name']) ?>">
                                        <?php else: ?>
                                            <div class="file-type-icon">
                                                <i class="fas <?= getFileIcon($file['extension'] ?? '') ?> fa-2x text-primary"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- File Info -->
                                    <div class="file-info flex-grow-1">
                                        <h6 class="file-name mb-1">
                                            <a href="/files/<?= $file['id'] ?>" class="text-decoration-none">
                                                <?= highlightSearchTerm(htmlspecialchars($file['name']), $query) ?>
                                            </a>
                                        </h6>
                                        <div class="file-meta text-muted small">
                                            <div><?= format_bytes($file['size'] ?? 0) ?></div>
                                            <div>Uploaded <?= timeAgo($file['created_at']) ?></div>
                                            <div>By <?= htmlspecialchars($file['owner_name'] ?? 'Unknown') ?></div>
                                            <?php if (!empty($file['download_count'])): ?>
                                            <div><?= number_format($file['download_count']) ?> downloads</div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- File Tags -->
                                        <?php if (!empty($file['tags'])): ?>
                                        <div class="file-tags mt-2">
                                            <?php foreach (explode(',', $file['tags']) as $tag): ?>
                                            <span class="badge bg-light text-dark me-1"><?= htmlspecialchars(trim($tag)) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="file-actions">
                                        <div class="dropdown">
                                            <button class="btn btn-link btn-sm" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="/api/files/<?= $file['id'] ?>/download">
                                                    <i class="fas fa-download"></i> Download
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="previewFile(<?= $file['id'] ?>)">
                                                    <i class="fas fa-eye"></i> Preview
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="shareFile(<?= $file['id'] ?>)">
                                                    <i class="fas fa-share"></i> Share
                                                </a></li>
                                                <?php if ($file['can_edit'] ?? false): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="/files/<?= $file['id'] ?>/edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteFile(<?= $file['id'] ?>)">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalResults > 20): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <!-- Pagination will be generated by JavaScript -->
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let searchTimeout;
    let currentQuery = '<?= addslashes($query) ?>';
    let currentPage = <?= $currentPage ?>;
    let currentSort = 'relevance';
    let currentView = 'grid';

    // Search input with debounced suggestions
    document.getElementById('searchQuery').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => {
                loadSuggestions(query);
            }, 300);
        } else {
            hideSuggestions();
        }
    });

    // Search button and enter key
    document.getElementById('searchButton').addEventListener('click', performSearch);
    document.getElementById('searchQuery').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });

    // Filter application
    document.getElementById('applyFilters').addEventListener('click', performSearch);
    document.getElementById('clearFilters').addEventListener('click', clearAllFilters);

    // Quick filter tags
    document.querySelectorAll('.filter-tag').forEach(tag => {
        tag.addEventListener('click', function() {
            const filter = this.dataset.filter;
            applyQuickFilter(filter);
        });
    });

    // Popular searches
    document.querySelectorAll('.popular-search').forEach(search => {
        search.addEventListener('click', function() {
            const query = this.dataset.query;
            document.getElementById('searchQuery').value = query;
            performSearch();
        });
    });

    // View toggle
    document.getElementById('gridView').addEventListener('click', () => switchView('grid'));
    document.getElementById('listView').addEventListener('click', () => switchView('list'));

    // Sort options
    document.querySelectorAll('[data-sort]').forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            currentSort = this.dataset.sort;
            performSearch();
        });
    });

    // Initialize
    if (currentQuery) {
        loadSearchResults();
    }
});

function performSearch() {
    currentQuery = document.getElementById('searchQuery').value.trim();
    currentPage = 1;
    loadSearchResults();
}

function loadSearchResults() {
    const filters = collectFilters();
    const params = new URLSearchParams({
        q: currentQuery,
        page: currentPage,
        sort: currentSort,
        ...filters
    });

    showSpinner('Searching...');

    fetch('/api/search?' + params.toString(), {
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        }
    })
    .then(response => response.json())
    .then(data => {
        hideSpinner();
        if (data.success) {
            displaySearchResults(data.results, data.total);
            updateURL(params);
        } else {
            showAlert('Search error: ' + (data.message || 'Unknown error'), 'danger');
        }
    })
    .catch(error => {
        hideSpinner();
        showAlert('Network error: ' + error.message, 'danger');
    });
}

function loadSuggestions(query) {
    fetch('/api/search/suggestions?q=' + encodeURIComponent(query), {
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.suggestions.length > 0) {
            displaySuggestions(data.suggestions);
        } else {
            hideSuggestions();
        }
    })
    .catch(error => {
        hideSuggestions();
    });
}

function displaySuggestions(suggestions) {
    const container = document.getElementById('searchSuggestions');
    container.innerHTML = suggestions.map(suggestion => `
        <div class="suggestion-item" onclick="selectSuggestion('${suggestion}')">
            <i class="fas fa-search text-muted me-2"></i>
            ${suggestion}
        </div>
    `).join('');
    container.style.display = 'block';
}

function hideSuggestions() {
    document.getElementById('searchSuggestions').style.display = 'none';
}

function selectSuggestion(suggestion) {
    document.getElementById('searchQuery').value = suggestion;
    hideSuggestions();
    performSearch();
}

function collectFilters() {
    const form = document.getElementById('searchFilters');
    const formData = new FormData(form);
    const filters = {};

    for (let [key, value] of formData.entries()) {
        if (key.endsWith('[]')) {
            const cleanKey = key.slice(0, -2);
            if (!filters[cleanKey]) filters[cleanKey] = [];
            filters[cleanKey].push(value);
        } else {
            filters[key] = value;
        }
    }

    return filters;
}

function clearAllFilters() {
    document.getElementById('searchFilters').reset();
    document.querySelectorAll('input[value="all"]').forEach(input => {
        input.checked = true;
    });
    performSearch();
}

function applyQuickFilter(filter) {
    const [type, value] = filter.split(':');
    
    // Clear existing filters of this type
    document.querySelectorAll(`input[name="${type}"]`).forEach(input => {
        input.checked = false;
    });
    
    // Apply the quick filter
    const targetInput = document.querySelector(`input[value="${value}"]`);
    if (targetInput) {
        targetInput.checked = true;
        performSearch();
    }
}

function switchView(view) {
    currentView = view;
    
    // Update button states
    document.getElementById('gridView').classList.toggle('active', view === 'grid');
    document.getElementById('listView').classList.toggle('active', view === 'list');
    
    // Update results container
    const container = document.getElementById('resultsContainer');
    container.className = view === 'grid' ? 'results-grid' : 'results-list';
}

function displaySearchResults(results, total) {
    // This would be implemented to update the results display
    console.log('Displaying results:', results, total);
}

function updateURL(params) {
    const newURL = window.location.pathname + '?' + params.toString();
    window.history.pushState({}, '', newURL);
}
</script>

<style>
.search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 0.375rem 0.375rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    z-index: 1000;
}

.suggestion-item {
    padding: 0.75rem 1rem;
    cursor: pointer;
    border-bottom: 1px solid #eee;
}

.suggestion-item:hover {
    background-color: #f8f9fa;
}

.suggestion-item:last-child {
    border-bottom: none;
}

.filter-tag {
    cursor: pointer;
    transition: all 0.2s;
}

.filter-tag:hover {
    background-color: #007bff !important;
    color: white !important;
}

.popular-search {
    cursor: pointer;
    transition: all 0.2s;
}

.popular-search:hover {
    background-color: #0056b3 !important;
}

.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.results-list .file-card {
    margin-bottom: 1rem;
}

.file-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.file-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
}

.file-icon img {
    object-fit: cover;
    border-radius: 0.375rem;
}

.file-type-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fa;
    border-radius: 0.375rem;
}

.file-name a {
    color: #333;
    font-weight: 500;
}

.file-name a:hover {
    color: #007bff;
}

.file-meta > div {
    line-height: 1.3;
}

.file-tags .badge {
    font-size: 0.7rem;
}

.search-container {
    position: relative;
}

.filter-group .form-check {
    margin-bottom: 0.5rem;
}

.filter-group .form-check-label {
    font-size: 0.9rem;
}

mark {
    background-color: #fff3cd;
    padding: 0.1em 0.2em;
    border-radius: 0.2em;
}

@media (max-width: 768px) {
    .results-grid {
        grid-template-columns: 1fr;
    }
    
    .file-card .d-flex {
        flex-direction: column;
    }
    
    .file-icon {
        margin-bottom: 1rem;
        align-self: center;
    }
}
</style>

<?php
// Helper functions for the template
function getFileIcon($extension) {
    $icons = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word', 'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel',
        'ppt' => 'fa-file-powerpoint', 'pptx' => 'fa-file-powerpoint',
        'txt' => 'fa-file-alt',
        'zip' => 'fa-file-archive', 'rar' => 'fa-file-archive', '7z' => 'fa-file-archive',
        'mp4' => 'fa-file-video', 'avi' => 'fa-file-video', 'mov' => 'fa-file-video',
        'mp3' => 'fa-file-audio', 'wav' => 'fa-file-audio', 'flac' => 'fa-file-audio',
        'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image', 'png' => 'fa-file-image', 'gif' => 'fa-file-image'
    ];
    return $icons[strtolower($extension)] ?? 'fa-file';
}

function highlightSearchTerm($text, $term) {
    if (empty($term)) return $text;
    return preg_replace('/(' . preg_quote($term, '/') . ')/i', '<mark>$1</mark>', $text);
}

function timeAgo($date) {
    $time = time() - strtotime($date);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}
?>
