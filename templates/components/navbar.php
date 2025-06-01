<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ $baseUrl }}/">
            <i class="bi bi-cloud-upload"></i>
            {{ $appName }}
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="{{ $baseUrl }}/dashboard">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ $baseUrl }}/files">
                        <i class="bi bi-folder"></i> Files
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ $baseUrl }}/upload">
                        <i class="bi bi-cloud-upload"></i> Upload
                    </a>
                </li>
            </ul>
            
            <!-- Search Form -->
            <form class="d-flex me-3" method="GET" action="{{ $baseUrl }}/search">
                <div class="input-group">
                    <input class="form-control" type="search" name="q" placeholder="Search files..." value="{{ $searchQuery ?? '' }}">
                    <button class="btn btn-outline-light" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
            
            <!-- User Menu -->
            <div class="dropdown">
                <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i>
                    {{ $currentUser['first_name'] }} {{ $currentUser['last_name'] }}
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ $baseUrl }}/profile">
                        <i class="bi bi-person"></i> Profile
                    </a></li>
                    <li><a class="dropdown-item" href="{{ $baseUrl }}/settings">
                        <i class="bi bi-gear"></i> Settings
                    </a></li>
                    @if($currentUser['role'] === 'admin')
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ $baseUrl }}/admin">
                            <i class="bi bi-shield-check"></i> Admin Panel
                        </a></li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ $baseUrl }}/logout">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
