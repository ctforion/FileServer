<?php
/**
 * Portable PHP File Storage Server - Main Entry Point
 * 
 * This file handles all requests and routes them appropriately.
 */

// Load configuration
require_once __DIR__ . '/config.php';

// Initialize the application
require_once __DIR__ . '/source/core/App.php';

// Start the application
$app = new App();
$app->run();
?>
