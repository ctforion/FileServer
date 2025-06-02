<?php
/**
 * API Response Handler
 * Standardized response formatting for separated API system
 */

require_once __DIR__ . '/ApiConfig.php';

class ApiResponse {
    private static $requestId;
    
    public static function init() {
        if (self::$requestId === null) {
            self::$requestId = uniqid('api_', true);
        }
    }
    
    /**
     * Send successful response
     */
    public static function success($data = null, $message = null, $statusCode = 200) {
        self::init();
        http_response_code($statusCode);
        
        $response = [
            'success' => true,
            'status_code' => $statusCode
        ];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        self::addMetadata($response);
        self::output($response);
    }
    
    /**
     * Send error response
     */
    public static function error($message, $statusCode = 400, $details = null) {
        self::init();
        http_response_code($statusCode);
        
        $response = [
            'success' => false,
            'status_code' => $statusCode,
            'error' => $message
        ];
        
        if ($details !== null) {
            $response['details'] = $details;
        }
        
        self::addMetadata($response);
        self::output($response);
    }
    
    /**
     * Send validation error response
     */
    public static function validationError($errors, $message = 'Validation failed') {
        self::error($message, 422, ['validation_errors' => $errors]);
    }
    
    /**
     * Send authentication error
     */
    public static function unauthorized($message = 'Authentication required') {
        self::error($message, 401);
    }
    
    /**
     * Send forbidden error
     */
    public static function forbidden($message = 'Access denied') {
        self::error($message, 403);
    }
    
    /**
     * Send not found error
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }
    
    /**
     * Send rate limit error
     */
    public static function rateLimited($message = 'Rate limit exceeded') {
        self::error($message, 429);
    }
    
    /**
     * Send server error
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }
    
    /**
     * Send paginated response
     */
    public static function paginated($data, $total, $page, $perPage, $message = null) {
        $totalPages = ceil($total / $perPage);
        
        $response = [
            'items' => $data,
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$perPage,
                'total_items' => (int)$total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
        
        self::success($response, $message);
    }
    
    /**
     * Add metadata to response
     */
    private static function addMetadata(&$response) {
        $config = ApiConfig::get('response', []);
        
        if ($config['include_timestamp'] ?? true) {
            $response['timestamp'] = date('c');
        }
        
        if ($config['include_request_id'] ?? true) {
            $response['request_id'] = self::$requestId;
        }
        
        $response['api_version'] = ApiConfig::get('version', '1.0.0');
    }
    
    /**
     * Output JSON response and exit
     */
    private static function output($response) {
        $prettyPrint = ApiConfig::get('response.pretty_print', false);
        $flags = JSON_UNESCAPED_SLASHES;
        
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }
        
        echo json_encode($response, $flags);
        exit;
    }
    
    /**
     * Handle exceptions and convert to error responses
     */
    public static function handleException($exception) {
        // Log the exception
        if (class_exists('Logger')) {
            $logger = new Logger();
            $logger->error('API Exception: ' . $exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);
        }
        
        if (ApiConfig::isDebugMode()) {
            self::error(
                'Server error: ' . $exception->getMessage(),
                500,
                [
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTrace()
                ]
            );
        } else {
            self::serverError();
        }
    }
    
    /**
     * Stream file download response
     */
    public static function downloadFile($filePath, $filename = null, $mimeType = null) {
        if (!file_exists($filePath)) {
            self::notFound('File not found');
        }
        
        if ($filename === null) {
            $filename = basename($filePath);
        }
        
        if ($mimeType === null) {
            $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        }
        
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers for file download
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Stream the file
        readfile($filePath);
        exit;
    }
}
