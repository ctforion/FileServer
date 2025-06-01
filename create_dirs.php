<?php
/**
 * Directory Creation Script
 * Pre-creates all required directories to avoid open_basedir issues
 */

// Define base paths
$basePath = __DIR__;
$storagePath = $basePath . DIRECTORY_SEPARATOR . 'storage';
$cachePath = $basePath . DIRECTORY_SEPARATOR . 'cache';
$logsPath = $basePath . DIRECTORY_SEPARATOR . 'logs';

// List of all required directories
$directories = [
    // Main storage directories
    $storagePath,
    $storagePath . DIRECTORY_SEPARATOR . 'public',
    $storagePath . DIRECTORY_SEPARATOR . 'private',
    $storagePath . DIRECTORY_SEPARATOR . 'temp',
    $storagePath . DIRECTORY_SEPARATOR . 'shared',
    $storagePath . DIRECTORY_SEPARATOR . 'backup',
    $storagePath . DIRECTORY_SEPARATOR . 'archive',
    $storagePath . DIRECTORY_SEPARATOR . 'system',
    $storagePath . DIRECTORY_SEPARATOR . 'thumbnails',
    $storagePath . DIRECTORY_SEPARATOR . 'quarantine',
    $storagePath . DIRECTORY_SEPARATOR . 'index',
    
    // Cache directories
    $cachePath,
    $cachePath . DIRECTORY_SEPARATOR . 'templates',
    $cachePath . DIRECTORY_SEPARATOR . 'data',
    $cachePath . DIRECTORY_SEPARATOR . 'thumbnails',
    $cachePath . DIRECTORY_SEPARATOR . 'search',
    $cachePath . DIRECTORY_SEPARATOR . 'api',
    $cachePath . DIRECTORY_SEPARATOR . 'session',
    
    // Logs directories
    $logsPath,
    $logsPath . DIRECTORY_SEPARATOR . 'archive'
];

echo "Creating required directories...\n";

$created = 0;
$existing = 0;

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✓ Created: $dir\n";
            $created++;
        } else {
            echo "✗ Failed to create: $dir\n";
        }
    } else {
        echo "- Already exists: $dir\n";
        $existing++;
    }
}

echo "\nSummary:\n";
echo "Created: $created directories\n";
echo "Already existed: $existing directories\n";
echo "Total: " . ($created + $existing) . " directories\n";

// Create .htaccess files for security
echo "\nCreating .htaccess protection files...\n";

$htaccessFiles = [
    $storagePath . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . '.htaccess' => "# Private files - no direct access\nOrder deny,allow\nDeny from all\n",
    $storagePath . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . '.htaccess' => "# Temporary files - no direct access\nOrder deny,allow\nDeny from all\n",
    $storagePath . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . '.htaccess' => "# Backup files - no direct access\nOrder deny,allow\nDeny from all\n",
    $storagePath . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . '.htaccess' => "# System files - no direct access\nOrder deny,allow\nDeny from all\n",
    $storagePath . DIRECTORY_SEPARATOR . 'quarantine' . DIRECTORY_SEPARATOR . '.htaccess' => "# Quarantine files - no direct access\nOrder deny,allow\nDeny from all\n",
    $cachePath . DIRECTORY_SEPARATOR . '.htaccess' => "# Cache files - no direct access\nOrder deny,allow\nDeny from all\n",
    $logsPath . DIRECTORY_SEPARATOR . '.htaccess' => "# Log files - no direct access\nOrder deny,allow\nDeny from all\n"
];

$htaccessCreated = 0;
$htaccessExisting = 0;

foreach ($htaccessFiles as $file => $content) {
    if (!file_exists($file)) {
        if (file_put_contents($file, $content)) {
            echo "✓ Created: $file\n";
            $htaccessCreated++;
        } else {
            echo "✗ Failed to create: $file\n";
        }
    } else {
        echo "- Already exists: $file\n";
        $htaccessExisting++;
    }
}

// Special .htaccess for public directory
$publicHtaccess = $storagePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess';
if (!file_exists($publicHtaccess)) {
    $content = "# Public files - allow access but prevent script execution\n";
    $content .= "php_flag engine off\n";
    $content .= "AddHandler cgi-script .php .phtml .php3 .pl .py .jsp .asp .sh .cgi\n";
    $content .= "Options -ExecCGI\n";
    $content .= "\n# Force download for potentially dangerous files\n";
    $content .= "<FilesMatch \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\">\n";
    $content .= "    ForceType application/octet-stream\n";
    $content .= "    Header set Content-Disposition attachment\n";
    $content .= "</FilesMatch>\n";
    
    if (file_put_contents($publicHtaccess, $content)) {
        echo "✓ Created: $publicHtaccess\n";
        $htaccessCreated++;
    } else {
        echo "✗ Failed to create: $publicHtaccess\n";
    }
} else {
    echo "- Already exists: $publicHtaccess\n";
    $htaccessExisting++;
}

echo "\n.htaccess Summary:\n";
echo "Created: $htaccessCreated files\n";
echo "Already existed: $htaccessExisting files\n";
echo "Total: " . ($htaccessCreated + $htaccessExisting) . " .htaccess files\n";

echo "\nDirectory structure is ready!\n";
