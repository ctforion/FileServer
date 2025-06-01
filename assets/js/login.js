/**
 * Login Page JavaScript
 * Handles authentication forms and 2FA
 */

// Extend the main app with login functionality
FileServerApp.prototype.initLoginPage = function() {
    this.setupLoginForm();
    this.setupRegisterForm();
    this.setupForgotPasswordForm();
    this.setup2FAForm();
    this.setupFormToggling();
    this.setupPasswordVisibilityToggle();
    this.setupFormValidation();
};

/**
 * Setup login form
 */
FileServerApp.prototype.setupLoginForm = function() {
    const loginForm = document.getElementById('loginForm');
    if (!loginForm) return;

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        await this.handleLogin(loginForm);
    });

    // Enable Enter key submission
    loginForm.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            loginForm.dispatchEvent(new Event('submit'));
        }
    });
};

/**
 * Handle login form submission
 */
FileServerApp.prototype.handleLogin = async function(form) {
    const formData = new FormData(form);
    const data = {
        username: formData.get('username'),
        password: formData.get('password'),
        remember: formData.get('remember') === 'on'
    };

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Signing in...';
    submitBtn.disabled = true;

    try {
        const response = await this.apiRequest('/auth/login', {
            method: 'POST',
            body: JSON.stringify(data)
        });

        if (response.success) {
            if (response.data.requires_2fa) {
                // Show 2FA form
                this.show2FAForm(response.data.temp_token);
            } else {
                // Complete login
                this.completeLogin(response.data);
            }
        }
    } catch (error) {
        this.showNotification(error.message || 'Login failed', 'error');
        this.shakeForm(form);
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
};

/**
 * Setup register form
 */
FileServerApp.prototype.setupRegisterForm = function() {
    const registerForm = document.getElementById('registerForm');
    if (!registerForm) return;

    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        await this.handleRegister(registerForm);
    });

    // Password strength indicator
    const passwordInput = registerForm.querySelector('input[name="password"]');
    if (passwordInput) {
        passwordInput.addEventListener('input', (e) => {
            this.updatePasswordStrength(e.target.value);
        });
    }

    // Password confirmation validation
    const confirmPasswordInput = registerForm.querySelector('input[name="confirm_password"]');
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', (e) => {
            this.validatePasswordConfirmation();
        });
    }
};

/**
 * Handle register form submission
 */
FileServerApp.prototype.handleRegister = async function(form) {
    const formData = new FormData(form);
    const data = {
        username: formData.get('username'),
        email: formData.get('email'),
        password: formData.get('password'),
        confirm_password: formData.get('confirm_password'),
        name: formData.get('name') || formData.get('username')
    };

    // Validate passwords match
    if (data.password !== data.confirm_password) {
        this.showNotification('Passwords do not match', 'error');
        return;
    }

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Creating account...';
    submitBtn.disabled = true;

    try {
        const response = await this.apiRequest('/auth/register', {
            method: 'POST',
            body: JSON.stringify(data)
        });

        if (response.success) {
            this.showNotification('Account created successfully! Please check your email for verification.', 'success');
            this.showLoginForm();
        }
    } catch (error) {
        this.showNotification(error.message || 'Registration failed', 'error');
        this.shakeForm(form);
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
};

/**
 * Setup forgot password form
 */
FileServerApp.prototype.setupForgotPasswordForm = function() {
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    if (!forgotPasswordForm) return;

    forgotPasswordForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        await this.handleForgotPassword(forgotPasswordForm);
    });
};

/**
 * Handle forgot password form submission
 */
FileServerApp.prototype.handleForgotPassword = async function(form) {
    const formData = new FormData(form);
    const data = {
        email: formData.get('email')
    };

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Sending...';
    submitBtn.disabled = true;

    try {
        const response = await this.apiRequest('/auth/forgot-password', {
            method: 'POST',
            body: JSON.stringify(data)
        });

        if (response.success) {
            this.showNotification('Password reset instructions sent to your email', 'success');
            this.showLoginForm();
        }
    } catch (error) {
        this.showNotification(error.message || 'Failed to send reset email', 'error');
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
};

/**
 * Setup 2FA form
 */
FileServerApp.prototype.setup2FAForm = function() {
    const twoFAForm = document.getElementById('twoFAForm');
    if (!twoFAForm) return;

    twoFAForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        await this.handle2FA(twoFAForm);
    });

    // Auto-focus next input after entering digit
    const codeInputs = twoFAForm.querySelectorAll('.code-input');
    codeInputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            if (e.target.value.length === 1 && index < codeInputs.length - 1) {
                codeInputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                codeInputs[index - 1].focus();
            }
        });
    });
};

/**
 * Handle 2FA form submission
 */
FileServerApp.prototype.handle2FA = async function(form) {
    const codeInputs = form.querySelectorAll('.code-input');
    const code = Array.from(codeInputs).map(input => input.value).join('');
    const tempToken = form.dataset.tempToken;

    if (code.length !== 6) {
        this.showNotification('Please enter a complete 6-digit code', 'error');
        return;
    }

    try {
        const response = await this.apiRequest('/auth/verify-2fa', {
            method: 'POST',
            body: JSON.stringify({
                code: code,
                temp_token: tempToken
            })
        });

        if (response.success) {
            this.completeLogin(response.data);
        }
    } catch (error) {
        this.showNotification(error.message || '2FA verification failed', 'error');
        // Clear code inputs
        codeInputs.forEach(input => input.value = '');
        codeInputs[0].focus();
    }
};

/**
 * Show 2FA form
 */
FileServerApp.prototype.show2FAForm = function(tempToken) {
    const loginContainer = document.querySelector('.login-container');
    const twoFAContainer = document.querySelector('.two-fa-container');
    
    if (loginContainer) loginContainer.style.display = 'none';
    if (twoFAContainer) {
        twoFAContainer.style.display = 'block';
        twoFAContainer.dataset.tempToken = tempToken;
        
        // Focus first input
        const firstInput = twoFAContainer.querySelector('.code-input');
        if (firstInput) firstInput.focus();
    }
};

/**
 * Complete login process
 */
FileServerApp.prototype.completeLogin = function(data) {
    this.token = data.token;
    this.user = data.user;
    
    localStorage.setItem('auth_token', this.token);
    localStorage.setItem('user', JSON.stringify(this.user));
    
    this.showNotification(`Welcome back, ${this.user.name || this.user.username}!`, 'success');
    
    // Redirect to intended page or dashboard
    const redirectUrl = new URLSearchParams(window.location.search).get('redirect') || '/dashboard';
    window.location.href = redirectUrl;
};

/**
 * Setup form toggling
 */
FileServerApp.prototype.setupFormToggling = function() {
    // Toggle between login and register
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-show-register]')) {
            e.preventDefault();
            this.showRegisterForm();
        }
        
        if (e.target.closest('[data-show-login]')) {
            e.preventDefault();
            this.showLoginForm();
        }
        
        if (e.target.closest('[data-show-forgot-password]')) {
            e.preventDefault();
            this.showForgotPasswordForm();
        }
    });
};

/**
 * Show login form
 */
FileServerApp.prototype.showLoginForm = function() {
    this.hideAllForms();
    const loginContainer = document.querySelector('.login-container');
    if (loginContainer) {
        loginContainer.style.display = 'block';
        loginContainer.querySelector('input[name="username"]')?.focus();
    }
};

/**
 * Show register form
 */
FileServerApp.prototype.showRegisterForm = function() {
    this.hideAllForms();
    const registerContainer = document.querySelector('.register-container');
    if (registerContainer) {
        registerContainer.style.display = 'block';
        registerContainer.querySelector('input[name="username"]')?.focus();
    }
};

/**
 * Show forgot password form
 */
FileServerApp.prototype.showForgotPasswordForm = function() {
    this.hideAllForms();
    const forgotPasswordContainer = document.querySelector('.forgot-password-container');
    if (forgotPasswordContainer) {
        forgotPasswordContainer.style.display = 'block';
        forgotPasswordContainer.querySelector('input[name="email"]')?.focus();
    }
};

/**
 * Hide all forms
 */
FileServerApp.prototype.hideAllForms = function() {
    const forms = [
        '.login-container',
        '.register-container', 
        '.forgot-password-container',
        '.two-fa-container'
    ];
    
    forms.forEach(selector => {
        const element = document.querySelector(selector);
        if (element) element.style.display = 'none';
    });
};

/**
 * Setup password visibility toggle
 */
FileServerApp.prototype.setupPasswordVisibilityToggle = function() {
    document.addEventListener('click', (e) => {
        if (e.target.closest('.password-toggle')) {
            const toggle = e.target.closest('.password-toggle');
            const input = toggle.parentElement.querySelector('input');
            
            if (input.type === 'password') {
                input.type = 'text';
                toggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                toggle.innerHTML = '<i class="fas fa-eye"></i>';
            }
        }
    });
};

/**
 * Setup form validation
 */
FileServerApp.prototype.setupFormValidation = function() {
    // Real-time validation for all forms
    document.addEventListener('blur', (e) => {
        if (e.target.matches('input[required]')) {
            this.validateField(e.target);
        }
    }, true);
    
    document.addEventListener('input', (e) => {
        if (e.target.matches('input[required]') && e.target.classList.contains('invalid')) {
            this.validateField(e.target);
        }
    });
};

/**
 * Validate individual field
 */
FileServerApp.prototype.validateField = function(field) {
    const value = field.value.trim();
    let isValid = true;
    let message = '';

    // Check if required field is empty
    if (field.hasAttribute('required') && !value) {
        isValid = false;
        message = 'This field is required';
    }

    // Email validation
    if (field.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            message = 'Please enter a valid email address';
        }
    }

    // Username validation
    if (field.name === 'username' && value) {
        if (value.length < 3) {
            isValid = false;
            message = 'Username must be at least 3 characters';
        }
        if (!/^[a-zA-Z0-9_-]+$/.test(value)) {
            isValid = false;
            message = 'Username can only contain letters, numbers, hyphens, and underscores';
        }
    }

    // Password validation
    if (field.name === 'password' && value) {
        if (value.length < 8) {
            isValid = false;
            message = 'Password must be at least 8 characters';
        }
    }

    this.setFieldValidation(field, isValid, message);
    return isValid;
};

/**
 * Set field validation state
 */
FileServerApp.prototype.setFieldValidation = function(field, isValid, message) {
    const container = field.parentElement;
    let errorElement = container.querySelector('.field-error');

    if (isValid) {
        field.classList.remove('invalid');
        field.classList.add('valid');
        if (errorElement) errorElement.remove();
    } else {
        field.classList.remove('valid');
        field.classList.add('invalid');
        
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'field-error';
            container.appendChild(errorElement);
        }
        errorElement.textContent = message;
    }
};

/**
 * Update password strength indicator
 */
FileServerApp.prototype.updatePasswordStrength = function(password) {
    const strengthMeter = document.querySelector('.password-strength');
    if (!strengthMeter) return;

    const strength = this.calculatePasswordStrength(password);
    const strengthBar = strengthMeter.querySelector('.strength-bar');
    const strengthText = strengthMeter.querySelector('.strength-text');

    if (strengthBar) {
        strengthBar.style.width = `${strength.percentage}%`;
        strengthBar.className = `strength-bar strength-${strength.level}`;
    }

    if (strengthText) {
        strengthText.textContent = strength.text;
        strengthText.className = `strength-text strength-${strength.level}`;
    }
};

/**
 * Calculate password strength
 */
FileServerApp.prototype.calculatePasswordStrength = function(password) {
    let score = 0;
    
    if (password.length >= 8) score += 1;
    if (password.length >= 12) score += 1;
    if (/[a-z]/.test(password)) score += 1;
    if (/[A-Z]/.test(password)) score += 1;
    if (/[0-9]/.test(password)) score += 1;
    if (/[^a-zA-Z0-9]/.test(password)) score += 1;

    const levels = {
        0: { level: 'weak', text: 'Very Weak', percentage: 0 },
        1: { level: 'weak', text: 'Weak', percentage: 20 },
        2: { level: 'weak', text: 'Weak', percentage: 30 },
        3: { level: 'medium', text: 'Medium', percentage: 50 },
        4: { level: 'strong', text: 'Strong', percentage: 75 },
        5: { level: 'strong', text: 'Very Strong', percentage: 90 },
        6: { level: 'strong', text: 'Excellent', percentage: 100 }
    };

    return levels[score] || levels[0];
};

/**
 * Validate password confirmation
 */
FileServerApp.prototype.validatePasswordConfirmation = function() {
    const passwordInput = document.querySelector('input[name="password"]');
    const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
    
    if (!passwordInput || !confirmPasswordInput) return;

    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;

    if (confirmPassword && password !== confirmPassword) {
        this.setFieldValidation(confirmPasswordInput, false, 'Passwords do not match');
    } else if (confirmPassword) {
        this.setFieldValidation(confirmPasswordInput, true, '');
    }
};

/**
 * Shake form animation for errors
 */
FileServerApp.prototype.shakeForm = function(form) {
    form.classList.add('shake');
    setTimeout(() => form.classList.remove('shake'), 500);
};
