<?php
// Test script to check if constants are being defined correctly
require_once __DIR__ . '/core/utils/EnvLoader.php';

try {
    EnvLoader::load();
    
    echo "Testing constants:\n";
    echo "STORAGE_PATH: " . (defined('STORAGE_PATH') ? STORAGE_PATH : 'NOT DEFINED') . "\n";
    echo "PUBLIC_PATH: " . (defined('PUBLIC_PATH') ? PUBLIC_PATH : 'NOT DEFINED') . "\n";
    echo "PRIVATE_PATH: " . (defined('PRIVATE_PATH') ? PRIVATE_PATH : 'NOT DEFINED') . "\n";
    echo "TEMP_PATH: " . (defined('TEMP_PATH') ? TEMP_PATH : 'NOT DEFINED') . "\n";
    echo "CACHE_PATH: " . (defined('CACHE_PATH') ? CACHE_PATH : 'NOT DEFINED') . "\n";
    echo "LOGS_PATH: " . (defined('LOGS_PATH') ? LOGS_PATH : 'NOT DEFINED') . "\n";
    
    echo "\nTesting createDirectories:\n";
    EnvLoader::createDirectories();
    echo "Directories created successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
