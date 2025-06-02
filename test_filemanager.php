<?php
/**
 * Simple test to verify FileManager class is working
 */

require_once 'config.php';
require_once 'core/storage/FileManager.php';

try {
    $config = require 'config.php';
    
    echo "Testing FileManager class instantiation...\n";
    
    $fileManager = new FileManager(
        $config['storage_path'],
        $config['max_file_size'],
        $config['allowed_extensions']
    );
    
    echo "✓ FileManager class loaded successfully!\n";
    
    // Test storage stats method
    echo "Testing storage statistics...\n";
    $stats = $fileManager->getStorageStats();
    echo "✓ Storage stats retrieved successfully!\n";
    echo "Total files: " . $stats['total_files'] . "\n";
    echo "Total size: " . $stats['total_size_formatted'] . "\n";
    echo "Storage path: " . $stats['storage_path'] . "\n";
    
    echo "\n✓ All FileManager tests passed!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
