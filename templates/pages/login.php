@extends('layouts/main')

@block('content')
<div class="container-fluid">
    <div class="row min-vh-100">
        <div class="col-lg-6 d-none d-lg-flex bg-primary text-white align-items-center">
            <div class="container">
                <div class="text-center">
                    <i class="bi bi-cloud-upload display-1 mb-4"></i>
                    <h2 class="display-6 fw-bold mb-3">{{ $appName }}</h2>
                    <p class="lead">
                        Secure file storage and sharing platform with enterprise-grade features.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 d-flex align-items-center">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card shadow">
                            <div class="card-body p-5">
                                <div class="text-center mb-4">
                                    <h1 class="h3 mb-3 fw-bold">Sign In</h1>
                                    <p class="text-muted">Welcome back! Please sign in to your account.</p>
                                </div>
                                
                                @if($error)
                                    <div class="alert alert-danger" role="alert">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        {{ $error }}
                                    </div>
                                @endif
                                
                                <form id="loginForm" method="POST" action="{{ $baseUrl }}/api/auth/login">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="bi bi-envelope"></i>
                                            </span>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   placeholder="Enter your email" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="bi bi-lock"></i>
                                            </span>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="Enter your password" required>
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div id="twoFactorSection" class="mb-3 d-none">
                                        <label for="twoFactorCode" class="form-label">Two-Factor Authentication Code</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="bi bi-shield-check"></i>
                                            </span>
                                            <input type="text" class="form-control" id="twoFactorCode" name="two_factor_code" 
                                                   placeholder="Enter 6-digit code" maxlength="6">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                                        <label class="form-check-label" for="rememberMe">
                                            Remember me
                                        </label>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button class="btn btn-primary btn-lg" type="submit">
                                            <i class="bi bi-box-arrow-in-right"></i>
                                            Sign In
                                        </button>
                                    </div>
                                </form>
                                
                                <hr class="my-4">
                                
                                <div class="text-center">
                                    <p class="mb-2">
                                        <a href="{{ $baseUrl }}/forgot-password" class="text-decoration-none">
                                            Forgot your password?
                                        </a>
                                    </p>
                                    <p class="mb-0">
                                        Don't have an account? 
                                        <a href="{{ $baseUrl }}/register" class="text-decoration-none fw-bold">
                                            Sign up here
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>
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
    const loginForm = document.getElementById('loginForm');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const twoFactorSection = document.getElementById('twoFactorSection');
    
    // Toggle password visibility
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        const icon = this.querySelector('i');
        icon.className = type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
    
    // Handle form submission
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData);
        
        try {
            showLoading();
            
            const response = await fetch('{{ $baseUrl }}/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Store token and redirect
                localStorage.setItem('auth_token', result.token);
                if (result.refresh_token) {
                    localStorage.setItem('refresh_token', result.refresh_token);
                }
                
                showMessage('Login successful! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = '{{ $baseUrl }}/dashboard';
                }, 1000);
                
            } else {
                hideLoading();
                
                if (result.code === '2FA_REQUIRED') {
                    twoFactorSection.classList.remove('d-none');
                    document.getElementById('twoFactorCode').focus();
                    showMessage('Please enter your two-factor authentication code.', 'info');
                } else {
                    showMessage(result.error || 'Login failed. Please try again.', 'danger');
                }
            }
            
        } catch (error) {
            hideLoading();
            showMessage('Network error. Please check your connection and try again.', 'danger');
        }
    });
});
</script>
@endblock
