@extends('layouts/main')

@block('content')
<div class="container py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">Dashboard</h1>
                    <p class="text-muted">Welcome back, {{ $currentUser['first_name'] }}!</p>
                </div>
                <div>
                    <a href="{{ $baseUrl }}/upload" class="btn btn-primary">
                        <i class="bi bi-cloud-upload"></i>
                        Upload Files
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Files</h6>
                            <h3 class="mb-0">{{ number_format($fileStats['total_files']) }}</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-files display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Storage Used</h6>
                            <h3 class="mb-0">{{ formatBytes($fileStats['storage_used']) }}</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-hdd display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Public Files</h6>
                            <h3 class="mb-0">{{ number_format($fileStats['public_files']) }}</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-globe display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Storage Quota</h6>
                            <h3 class="mb-0">{{ number_format(($fileStats['storage_used'] / $storageQuota) * 100, 1) }}%</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-pie-chart display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Storage Usage Progress -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-bar-chart"></i>
                        Storage Usage
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ formatBytes($fileStats['storage_used']) }} used</span>
                        <span>{{ formatBytes($storageQuota) }} total</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar {{ ($fileStats['storage_used'] / $storageQuota) > 0.8 ? 'bg-danger' : 'bg-primary' }}" 
                             role="progressbar" 
                             style="width: {{ min(($fileStats['storage_used'] / $storageQuota) * 100, 100) }}%">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Files -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history"></i>
                            Recent Files
                        </h5>
                        <a href="{{ $baseUrl }}/files" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(empty($recentFiles))
                        <div class="text-center py-4">
                            <i class="bi bi-inbox display-4 text-muted"></i>
                            <p class="text-muted mt-2">No files uploaded yet.</p>
                            <a href="{{ $baseUrl }}/upload" class="btn btn-primary">
                                Upload Your First File
                            </a>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Size</th>
                                        <th>Type</th>
                                        <th>Modified</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentFiles as $file)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi {{ getFileIcon($file['mime_type']) }} me-2"></i>
                                                <div>
                                                    <div class="fw-medium">{{ $file['original_name'] }}</div>
                                                    @if($file['description'])
                                                        <small class="text-muted">{{ substr($file['description'], 0, 50) }}...</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ formatBytes($file['file_size']) }}</td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                {{ strtoupper(pathinfo($file['original_name'], PATHINFO_EXTENSION)) }}
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                {{ date('M j, Y g:i A', strtotime($file['updated_at'] ?: $file['created_at'])) }}
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ $baseUrl }}/files/view/{{ $file['id'] }}" 
                                                   class="btn btn-outline-primary" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="{{ $baseUrl }}/api/files/{{ $file['id'] }}/download" 
                                                   class="btn btn-outline-success" title="Download">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning"></i>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ $baseUrl }}/upload" class="btn btn-primary">
                            <i class="bi bi-cloud-upload"></i>
                            Upload Files
                        </a>
                        <a href="{{ $baseUrl }}/files?filter=shared" class="btn btn-outline-primary">
                            <i class="bi bi-share"></i>
                            Shared Files
                        </a>
                        <a href="{{ $baseUrl }}/search" class="btn btn-outline-primary">
                            <i class="bi bi-search"></i>
                            Search Files
                        </a>
                        <a href="{{ $baseUrl }}/profile" class="btn btn-outline-secondary">
                            <i class="bi bi-person"></i>
                            Edit Profile
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Account Info -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-circle"></i>
                        Account Info
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <strong>Name:</strong> {{ $currentUser['first_name'] }} {{ $currentUser['last_name'] }}
                    </div>
                    <div class="mb-2">
                        <strong>Email:</strong> {{ $currentUser['email'] }}
                    </div>
                    <div class="mb-2">
                        <strong>Role:</strong> 
                        <span class="badge bg-primary">{{ ucfirst($currentUser['role']) }}</span>
                    </div>
                    <div class="mb-2">
                        <strong>2FA:</strong> 
                        @if($currentUser['two_factor_enabled'])
                            <span class="badge bg-success">Enabled</span>
                        @else
                            <span class="badge bg-warning">Disabled</span>
                        @endif
                    </div>
                    <div class="mb-2">
                        <strong>Member Since:</strong><br>
                        <small class="text-muted">{{ date('F j, Y', strtotime($currentUser['created_at'])) }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endblock

@block('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add any dashboard-specific JavaScript here
    console.log('Dashboard loaded');
});
</script>
@endblock
