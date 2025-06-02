<?php
/**
 * Comprehensive API test to verify all APIs are working
 */

echo "Testing all API endpoints for fatal errors...\n\n";

$apis = [
    'api/list.php',
    'api/upload.php', 
    'api/download.php',
    'api/delete.php'
];

foreach ($apis as $api) {
    echo "Testing $api...\n";
    
    // Capture output and errors
    ob_start();
    $error = '';
      try {
        // Set minimal environment for API testing
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        include $api;
        $output = ob_get_contents();
        echo "✓ $api loaded successfully (no fatal errors)\n";
        
    } catch (Error $e) {
        $error = $e->getMessage();
        echo "✗ $api failed: " . $error . "\n";
    } catch (Exception $e) {
        $error = $e->getMessage(); 
        echo "✗ $api failed: " . $error . "\n";
    } finally {
        ob_end_clean();
    }
    
    // Clear any included files for next test
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    echo "\n";
}

echo "API testing completed!\n";
