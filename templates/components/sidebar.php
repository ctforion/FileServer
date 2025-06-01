<div class="sidebar bg-light">
    <div class="sidebar-content">
        <ul class="nav nav-pills flex-column">
            <li class="nav-item">
                <a class="nav-link" href="{{ $baseUrl }}/dashboard">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ $baseUrl }}/files">
                    <i class="bi bi-folder"></i>
                    My Files
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ $baseUrl }}/upload">
                    <i class="bi bi-cloud-upload"></i>
                    Upload
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ $baseUrl }}/search">
                    <i class="bi bi-search"></i>
                    Search
                </a>
            </li>
        </ul>
        
        <hr>
        
        <h6 class="sidebar-heading">
            <span>Quick Actions</span>
        </h6>
        
        <ul class="nav nav-pills flex-column">
            <li class="nav-item">
                <a class="nav-link" href="{{ $baseUrl }}/files?filter=recent">
                    <i class="bi bi-clock-history"></i>
                    Recent Files
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ $baseUrl }}/files?filter=shared">
                    <i class="bi bi-share"></i>
                    Shared Files
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ $baseUrl }}/files?filter=public">
                    <i class="bi bi-globe"></i>
                    Public Files
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ $baseUrl }}/files?filter=trash">
                    <i class="bi bi-trash"></i>
                    Trash
                </a>
            </li>
        </ul>
        
        <hr>
        
        <h6 class="sidebar-heading">
            <span>File Types</span>
        </h6>
        
        <ul class="nav nav-pills flex-column">
            <li class="nav-item">
                <a class="nav-link" href="{{ $baseUrl }}/files?type=image">
                    <i class="bi bi-image"></i>
                    Images
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ $baseUrl }}/files?type=document">
                    <i class="bi bi-file-text"></i>
                    Documents
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ $baseUrl }}/files?type=video">
                    <i class="bi bi-camera-video"></i>
                    Videos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ $baseUrl }}/files?type=audio">
                    <i class="bi bi-music-note"></i>
                    Audio
                </a>
            </li>
        </ul>
        
        @if($currentUser['role'] === 'admin')
            <hr>
            
            <h6 class="sidebar-heading">
                <span>Administration</span>
            </h6>
            
            <ul class="nav nav-pills flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="{{ $baseUrl }}/admin">
                        <i class="bi bi-speedometer2"></i>
                        Admin Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ $baseUrl }}/admin/users">
                        <i class="bi bi-people"></i>
                        Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ $baseUrl }}/admin/settings">
                        <i class="bi bi-gear"></i>
                        Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ $baseUrl }}/admin/logs">
                        <i class="bi bi-list-ul"></i>
                        Audit Logs
                    </a>
                </li>
            </ul>
        @endif
    </div>
</div>
