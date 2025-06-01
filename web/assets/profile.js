// Profile Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initializeTabs();
    initializeFileSearch();
    initializePasswordValidation();
});

// Tab System
function initializeTabs() {
    const tabs = document.querySelectorAll('.nav-tabs a');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href').substring(1);
            
            // Remove active class from all tabs and contents
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            const targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.classList.add('active');
            }
            
            // Update URL hash
            window.location.hash = targetId;
        });
    });
    
    // Handle initial hash
    if (window.location.hash) {
        const hashTab = document.querySelector(`a[href="${window.location.hash}"]`);
        if (hashTab) {
            hashTab.click();
        }
    }
}

// File Search
function initializeFileSearch() {
    const searchInput = document.getElementById('file-search');
    if (!searchInput) return;
    
    const tableRows = document.querySelectorAll('.files-table tbody tr');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        tableRows.forEach(row => {
            const fileName = row.querySelector('.file-name')?.textContent.toLowerCase() || '';
            const fileType = row.cells[2]?.textContent.toLowerCase() || '';
            
            if (fileName.includes(searchTerm) || fileType.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide "no files" message
        const visibleRows = Array.from(tableRows).filter(row => row.style.display !== 'none');
        const noFilesRow = document.querySelector('.no-files');
        if (noFilesRow) {
            if (visibleRows.length === 0 && searchTerm.length > 0) {
                noFilesRow.style.display = 'table-cell';
                noFilesRow.textContent = 'No files match your search';
            } else if (visibleRows.length === 0) {
                noFilesRow.style.display = 'table-cell';
                noFilesRow.textContent = 'No files found';
            } else {
                noFilesRow.style.display = 'none';
            }
        }
    });
}

// Password Validation
function initializePasswordValidation() {
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    if (!newPasswordInput || !confirmPasswordInput) return;
    
    function validatePassword() {
        const password = newPasswordInput.value;
        const confirm = confirmPasswordInput.value;
        
        // Remove existing validation messages
        removeValidationMessage(newPasswordInput);
        removeValidationMessage(confirmPasswordInput);
        
        // Password strength validation
        if (password.length > 0) {
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            const isLongEnough = password.length >= 8;
            
            if (!isLongEnough || !hasUpper || !hasLower || !hasNumber || !hasSpecial) {
                showValidationMessage(newPasswordInput, 'Password must be at least 8 characters with uppercase, lowercase, number, and special character', 'error');
            } else {
                showValidationMessage(newPasswordInput, 'Password is strong', 'success');
            }
        }
        
        // Password confirmation validation
        if (confirm.length > 0 && password !== confirm) {
            showValidationMessage(confirmPasswordInput, 'Passwords do not match', 'error');
        } else if (confirm.length > 0 && password === confirm) {
            showValidationMessage(confirmPasswordInput, 'Passwords match', 'success');
        }
    }
    
    newPasswordInput.addEventListener('input', validatePassword);
    confirmPasswordInput.addEventListener('input', validatePassword);
}

function showValidationMessage(input, message, type) {
    removeValidationMessage(input);
    
    const messageElement = document.createElement('div');
    messageElement.className = `validation-message validation-${type}`;
    messageElement.textContent = message;
    
    input.parentNode.appendChild(messageElement);
}

function removeValidationMessage(input) {
    const existingMessage = input.parentNode.querySelector('.validation-message');
    if (existingMessage) {
        existingMessage.remove();
    }
}

// File Management
function deleteFile(filename) {
    if (!confirm(`Are you sure you want to delete "${filename}"?`)) {
        return;
    }
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    fetch('../api/delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            filename: filename,
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('File deleted successfully', 'success');
            // Reload the files tab
            location.reload();
        } else {
            showNotification(data.error || 'Error deleting file', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error deleting file', 'error');
    });
}

// Notifications
function showNotification(message, type) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
    
    // Manual close
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.remove();
    });
    
    // Animate in
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
}

// Form Auto-save (optional enhancement)
function initializeAutoSave() {
    const forms = document.querySelectorAll('.profile-form');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('change', function() {
                // Save to localStorage for form recovery
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                localStorage.setItem(`profile_form_${form.id}`, JSON.stringify(data));
            });
        });
        
        // Restore form data on page load
        const savedData = localStorage.getItem(`profile_form_${form.id}`);
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                Object.keys(data).forEach(key => {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input && input.type !== 'hidden' && input.name !== 'csrf_token') {
                        if (input.type === 'checkbox') {
                            input.checked = data[key] === 'on';
                        } else {
                            input.value = data[key];
                        }
                    }
                });
            } catch (e) {
                console.warn('Could not restore form data:', e);
            }
        }
        
        // Clear saved data on successful submit
        form.addEventListener('submit', function() {
            localStorage.removeItem(`profile_form_${form.id}`);
        });
    });
}

// Initialize auto-save if needed
// initializeAutoSave();

// CSS for notifications
const notificationStyles = `
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    z-index: 1000;
    display: flex;
    align-items: center;
    gap: 1rem;
    transform: translateX(100%);
    transition: transform 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.notification.show {
    transform: translateX(0);
}

.notification-success {
    background-color: #28a745;
}

.notification-error {
    background-color: #dc3545;
}

.notification-close {
    background: none;
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.validation-message {
    font-size: 0.875rem;
    margin-top: 0.25rem;
    padding: 0.5rem;
    border-radius: 4px;
}

.validation-success {
    color: #155724;
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
}

.validation-error {
    color: #721c24;
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
}
`;

// Add notification styles to page
const styleSheet = document.createElement('style');
styleSheet.textContent = notificationStyles;
document.head.appendChild(styleSheet);
