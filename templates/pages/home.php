@extends('layouts/main')

@block('content')
<div class="hero-section bg-primary text-white">
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">
                    Secure File Storage Made Simple
                </h1>
                <p class="lead mb-4">
                    Store, share, and manage your files with enterprise-grade security. 
                    Access your documents from anywhere, collaborate with your team, 
                    and keep your data safe in the cloud.
                </p>
                <div class="d-grid gap-2 d-md-flex">
                    <a href="{{ $baseUrl }}/register" class="btn btn-light btn-lg me-md-2">
                        <i class="bi bi-person-plus"></i> Get Started
                    </a>
                    <a href="{{ $baseUrl }}/login" class="btn btn-outline-light btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="text-center">
                    <i class="bi bi-cloud-upload display-1 opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="features-section py-5">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-lg-8 mx-auto">
                <h2 class="display-5 fw-bold">Why Choose Our Platform?</h2>
                <p class="lead text-muted">
                    Built with security, performance, and user experience in mind
                </p>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-shield-check text-primary display-4 mb-3"></i>
                        <h5 class="card-title">Enterprise Security</h5>
                        <p class="card-text">
                            Advanced encryption, two-factor authentication, and comprehensive audit trails 
                            keep your files secure.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-lightning text-primary display-4 mb-3"></i>
                        <h5 class="card-title">Lightning Fast</h5>
                        <p class="card-text">
                            Optimized for speed with intelligent caching, compression, 
                            and efficient file handling.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-people text-primary display-4 mb-3"></i>
                        <h5 class="card-title">Team Collaboration</h5>
                        <p class="card-text">
                            Share files securely, manage permissions, and collaborate 
                            with your team in real-time.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-phone text-primary display-4 mb-3"></i>
                        <h5 class="card-title">Mobile Ready</h5>
                        <p class="card-text">
                            Access your files from any device with our responsive 
                            interface and mobile-optimized experience.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-search text-primary display-4 mb-3"></i>
                        <h5 class="card-title">Powerful Search</h5>
                        <p class="card-text">
                            Find your files instantly with advanced search capabilities 
                            including full-text content search.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-gear text-primary display-4 mb-3"></i>
                        <h5 class="card-title">Fully Customizable</h5>
                        <p class="card-text">
                            Extensible plugin system and comprehensive API allow 
                            for unlimited customization possibilities.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cta-section bg-light py-5">
    <div class="container">
        <div class="row text-center">
            <div class="col-lg-8 mx-auto">
                <h2 class="display-6 fw-bold mb-4">Ready to Get Started?</h2>
                <p class="lead mb-4">
                    Join thousands of users who trust our platform with their important files.
                </p>
                <a href="{{ $baseUrl }}/register" class="btn btn-primary btn-lg">
                    <i class="bi bi-rocket"></i> Start Free Today
                </a>
            </div>
        </div>
    </div>
</section>
@endblock

@block('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});
</script>
@endblock
