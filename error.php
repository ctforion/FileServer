<?php
/**
 * Error Page Handler
 * Displays user-friendly error pages
 */

// Start session for user context
session_start();

// Get error code from query parameter
$error_code = $_GET['code'] ?? '404';
$error_code = filter_var($error_code, FILTER_VALIDATE_INT) ?: '404';

// Define error messages
$error_messages = [
    '400' => [
        'title' => 'Bad Request',
        'message' => 'The request could not be understood by the server.',
        'description' => 'Please check your request and try again.'
    ],
    '401' => [
        'title' => 'Unauthorized',
        'message' => 'You need to be logged in to access this resource.',
        'description' => 'Please log in and try again.'
    ],
    '403' => [
        'title' => 'Forbidden',
        'message' => 'You don\'t have permission to access this resource.',
        'description' => 'If you believe this is an error, please contact the administrator.'
    ],
    '404' => [
        'title' => 'Page Not Found',
        'message' => 'The requested page could not be found.',
        'description' => 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.'
    ],
    '500' => [
        'title' => 'Internal Server Error',
        'message' => 'An internal server error occurred.',
        'description' => 'Please try again later. If the problem persists, contact the administrator.'
    ],
    '503' => [
        'title' => 'Service Unavailable',
        'message' => 'The service is temporarily unavailable.',
        'description' => 'The server is currently under maintenance. Please try again later.'
    ]
];

// Get error details
$error = $error_messages[$error_code] ?? $error_messages['404'];

// Set HTTP status code
http_response_code($error_code);

// Set page title
$page_title = $error['title'];
$additional_css = ['assets/css/file-browser.css'];

// Include header
include 'templates/header.html';
?>

<div class="error-container">
    <div class="error-content">
        <div class="error-icon">
            <?php if ($error_code == '404'): ?>
                <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="M21 21l-4.35-4.35"></path>
                    <path d="M11 8v6"></path>
                    <path d="M8 11h6"></path>
                </svg>
            <?php elseif ($error_code == '403'): ?>
                <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <circle cx="12" cy="16" r="1"></circle>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            <?php elseif ($error_code == '500'): ?>
                <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            <?php else: ?>
                <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            <?php endif; ?>
        </div>
        
        <div class="error-details">
            <h1 class="error-code"><?php echo $error_code; ?></h1>
            <h2 class="error-title"><?php echo htmlspecialchars($error['title']); ?></h2>
            <p class="error-message"><?php echo htmlspecialchars($error['message']); ?></p>
            <p class="error-description"><?php echo htmlspecialchars($error['description']); ?></p>
        </div>
        
        <div class="error-actions">
            <a href="javascript:history.back()" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5"></path>
                    <path d="M12 19l-7-7 7-7"></path>
                </svg>
                Go Back
            </a>
            <a href="/" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9,22 9,12 15,12 15,22"></polyline>
                </svg>
                Home
            </a>
            <?php if ($error_code == '401' || $error_code == '403'): ?>
                <a href="login.php" class="btn btn-outline-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10,17 15,12 10,7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    Login
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <div class="error-debug">
            <details>
                <summary>Debug Information (Admin Only)</summary>
                <div class="debug-info">
                    <p><strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                    <p><strong>Request URI:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A'); ?></p>
                    <p><strong>HTTP Method:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'N/A'); ?></p>
                    <p><strong>User Agent:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'); ?></p>
                    <p><strong>IP Address:</strong> <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'N/A'); ?></p>
                    <p><strong>Referer:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'N/A'); ?></p>
                </div>
            </details>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.error-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 200px);
    padding: 2rem;
}

.error-content {
    text-align: center;
    max-width: 600px;
    width: 100%;
}

.error-icon {
    margin-bottom: 2rem;
    color: var(--text-muted);
}

.error-icon svg {
    width: 120px;
    height: 120px;
}

.error-code {
    font-size: 4rem;
    font-weight: bold;
    color: var(--accent-color);
    margin: 0 0 1rem 0;
    line-height: 1;
}

.error-title {
    font-size: 2rem;
    font-weight: 600;
    color: var(--text-color);
    margin: 0 0 1rem 0;
}

.error-message {
    font-size: 1.2rem;
    color: var(--text-secondary);
    margin: 0 0 1rem 0;
}

.error-description {
    font-size: 1rem;
    color: var(--text-muted);
    margin: 0 0 2rem 0;
    line-height: 1.5;
}

.error-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 2rem;
}

.error-actions .btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    text-decoration: none;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.error-actions .btn svg {
    width: 16px;
    height: 16px;
}

.error-debug {
    margin-top: 2rem;
    text-align: left;
    background-color: var(--bg-secondary);
    border-radius: 0.375rem;
    padding: 1rem;
}

.error-debug summary {
    cursor: pointer;
    font-weight: 600;
    color: var(--text-color);
    margin-bottom: 0.5rem;
}

.debug-info {
    font-family: monospace;
    font-size: 0.9rem;
    color: var(--text-secondary);
    line-height: 1.5;
}

.debug-info p {
    margin: 0.25rem 0;
}

.debug-info strong {
    color: var(--text-color);
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .error-container {
        padding: 1rem;
        min-height: calc(100vh - 150px);
    }
    
    .error-icon svg {
        width: 80px;
        height: 80px;
    }
    
    .error-code {
        font-size: 3rem;
    }
    
    .error-title {
        font-size: 1.5rem;
    }
    
    .error-message {
        font-size: 1rem;
    }
    
    .error-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .error-actions .btn {
        width: 100%;
        max-width: 200px;
        justify-content: center;
    }
}
</style>

<?php include 'templates/footer.html'; ?>
