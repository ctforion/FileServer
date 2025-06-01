<!DOCTYPE html>
<html lang="{{ $lang ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ? $pageTitle . ' - ' . $appName : $appName }}</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="{{ $baseUrl }}/assets/css/app.css" rel="stylesheet">
    
    @block('head')
    @endblock
</head>
<body class="{{ $bodyClass ?? '' }}">
    @if($currentUser)
        @include('components/navbar')
    @endif
    
    <main class="{{ $currentUser ? 'main-content' : 'main-guest' }}">
        @if($currentUser)
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-2 sidebar-col">
                        @include('components/sidebar')
                    </div>
                    <div class="col-md-10 content-col">
                        @block('content')
                        @endblock
                    </div>
                </div>
            </div>
        @else
            @block('content')
            @endblock
        @endif
    </main>
    
    @include('components/footer')
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="{{ $baseUrl }}/assets/js/app.js"></script>
    
    @block('scripts')
    @endblock
    
    <!-- Flash Messages -->
    <div id="flash-messages"></div>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</body>
</html>
