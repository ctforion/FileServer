# PHP File Storage Server - Download Script (PowerShell)
# GitHub Repository: https://github.com/0xAhmadYousuf/FileServer
# This script downloads all files individually and recreates the exact directory structure

param(
    [string]$OutputPath = ".",
    [switch]$Verbose = $false
)

# Configuration
$GitHubUser = "0xAhmadYousuf"
$RepoName = "FileServer"
$BaseUrl = "https://raw.githubusercontent.com/$GitHubUser/$RepoName/main"

# Function to write colored output
function Write-Status {
    param([string]$Message, [string]$Color = "Blue")
    Write-Host "[INFO] $Message" -ForegroundColor $Color
}

function Write-Success {
    param([string]$Message)
    Write-Host "[SUCCESS] $Message" -ForegroundColor Green
}

function Write-Warning {
    param([string]$Message)
    Write-Host "[WARNING] $Message" -ForegroundColor Yellow
}

function Write-Error {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
}

# Function to download a file
function Download-File {
    param(
        [string]$FilePath,
        [string]$BaseUrl
    )
    
    $url = "$BaseUrl/$($FilePath -replace '\\', '/')"
    $localPath = Join-Path $OutputPath $FilePath
    
    # Create directory if it doesn't exist
    $dir = Split-Path $localPath -Parent
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        if ($Verbose) { Write-Status "Created directory: $dir" }
    }
    
    # Download the file
    try {
        if ($Verbose) { Write-Status "Downloading: $FilePath" }
        Invoke-WebRequest -Uri $url -OutFile $localPath -ErrorAction Stop
        Write-Success "Downloaded: $FilePath"
        return $true
    }
    catch {
        Write-Error "Failed to download: $FilePath - $($_.Exception.Message)"
        return $false
    }
}

# Function to create directory structure
function Create-DirectoryStructure {
    Write-Status "Creating directory structure..."
    
    $directories = @(
        "cache\api",
        "cache\data",
        "cache\search",
        "cache\session",
        "cache\templates",
        "cache\thumbnails",
        "plugins\available",
        "plugins\installed",
        "plugins\manager",
        "storage\archive",
        "storage\backup",
        "storage\index",
        "storage\public",
        "storage\quarantine",
        "storage\shared",
        "storage\temp",
        "storage\thumbnails"
    )
    
    foreach ($dir in $directories) {
        $fullPath = Join-Path $OutputPath $dir
        if (-not (Test-Path $fullPath)) {
            New-Item -ItemType Directory -Path $fullPath -Force | Out-Null
            if ($Verbose) { Write-Status "Created directory: $dir" }
        }
    }
    
    Write-Success "Directory structure created successfully"
}

# Main function
function Main {
    Write-Status "Starting PHP File Storage Server download..."
    Write-Status "Repository: https://github.com/$GitHubUser/$RepoName"
    Write-Status "Output Path: $OutputPath"
    Write-Host "==========================================" -ForegroundColor Cyan
    
    # Change to output directory
    if (-not (Test-Path $OutputPath)) {
        New-Item -ItemType Directory -Path $OutputPath -Force | Out-Null
    }
    Set-Location $OutputPath
    
    # Create directory structure
    Create-DirectoryStructure
    
    Write-Status "Downloading files..."
    Write-Host "==========================================" -ForegroundColor Cyan
    
    $totalFiles = 0
    $successfulDownloads = 0
    
    # Define all files to download
    $filesToDownload = @(
        # Root files
        ".htaccess",
        "config.php",
        "example.env",
        "generate_migrations.php",
        "index.php",
        "Proposal.md",
        "README.md",
        "setup.php",
        
        # API files
        "api\.htaccess",
        "api\index.php",
        
        # API - Admin
        "api\admin\dashboard.php",
        "api\admin\settings.php",
        "api\admin\users.php",
        
        # API - Auth
        "api\auth\2fa.php",
        "api\auth\login.php",
        "api\auth\logout.php",
        "api\auth\register.php",
        "api\auth\reset.php",
        
        # API - Controllers
        "api\controllers\AdminController.php",
        "api\controllers\AuthController.php",
        "api\controllers\BaseController.php",
        "api\controllers\FileController.php",
        "api\controllers\PluginController.php",
        "api\controllers\SearchController.php",
        "api\controllers\SystemController.php",
        "api\controllers\UserController.php",
        "api\controllers\WebhookController.php",
        
        # API - Files
        "api\files\delete.php",
        "api\files\download.php",
        "api\files\list.php",
        "api\files\metadata.php",
        "api\files\upload.php",
        
        # API - Quota
        "api\quota\manage.php",
        "api\quota\stats.php",
        
        # API - Search
        "api\search\advanced.php",
        "api\search\index.php",
        "api\search\suggestions.php",
        
        # API - Sync
        "api\sync\checksum.php",
        "api\sync\status.php",
        
        # API - Webhook
        "api\webhook\manage.php",
        "api\webhook\test.php",
        
        # Assets
        "assets\css\app.css",
        "assets\js\admin.js",
        "assets\js\app.js",
        "assets\js\dashboard.js",
        "assets\js\files.js",
        "assets\js\login.js",
        "assets\js\profile.js",
        "assets\js\search.js",
        "assets\js\settings.js",
        "assets\js\upload.js",
        
        # Core files
        "core\.htaccess",
        "core\auth\Auth.php",
        "core\database\Database.php",
        "core\database\Migration.php",
        "core\database\migrations\2024_01_01_000800_create_email_queue_table.php",
        "core\database\migrations\2024_01_01_000801_create_webhook_deliveries_table.php",
        "core\database\migrations\2024_01_01_000802_create_backups_table.php",
        "core\database\migrations\2024_01_01_000803_create_update_log_table.php",
        "core\email\EmailManager.php",
        "core\monitoring\Monitor.php",
        "core\plugin\PluginManager.php",
        "core\storage\FileManager.php",
        "core\template\TemplateEngine.php",
        "core\update\UpdateManager.php",
        "core\utils\EnvLoader.php",
        "core\webhook\WebhookManager.php",
        
        # Language files (14 languages)
        "languages\ar.json",
        "languages\bn.json",
        "languages\config.json",
        "languages\de.json",
        "languages\en.json",
        "languages\es.json",
        "languages\fr.json",
        "languages\id.json",
        "languages\it.json",
        "languages\ja.json",
        "languages\ko.json",
        "languages\pt.json",
        "languages\ru.json",
        "languages\ur.json",
        "languages\zh.json",
        
        # Storage and Logs
        "logs\.htaccess",
        "storage\.htaccess",
        "storage\private\.htaccess",
        "storage\system\.htaccess",
        
        # Templates
        "templates\components\footer.php",
        "templates\components\navbar.php",
        "templates\components\sidebar.php",
        "templates\layouts\main.php",
        "templates\pages\admin.php",
        "templates\pages\dashboard.php",
        "templates\pages\files.php",
        "templates\pages\home.php",
        "templates\pages\login.php",
        "templates\pages\profile.php",
        "templates\pages\search.php",
        "templates\pages\settings.php",
        "templates\pages\upload.php"
    )
    
    # Download all files
    foreach ($file in $filesToDownload) {
        $totalFiles++
        if (Download-File -FilePath $file -BaseUrl $BaseUrl) {
            $successfulDownloads++
        }
    }
    
    Write-Host "==========================================" -ForegroundColor Cyan
    Write-Success "Download completed!"
    Write-Status "Total files: $totalFiles"
    Write-Status "Successfully downloaded: $successfulDownloads"
    
    if ($successfulDownloads -lt $totalFiles) {
        Write-Warning "Some files failed to download. Check the error messages above."
    }
    
    Write-Host "==========================================" -ForegroundColor Cyan
    
    # Display next steps
    Write-Host ""
    Write-Status "Next Steps:" "Magenta"
    Write-Host "1. Copy example.env to .env and configure your settings"
    Write-Host "2. Create a database and update database credentials in .env"
    Write-Host "3. Run 'php setup.php' to initialize the database"
    Write-Host "4. Configure your web server to point to this directory"
    Write-Host "5. Access the application through your web browser"
    Write-Host ""
    Write-Success "PHP File Storage Server is ready to use!"
    Write-Status "Repository: https://github.com/$GitHubUser/$RepoName"
    Write-Status "Documentation: See README.md for detailed setup instructions"
}

# Check for internet connectivity
try {
    Test-NetConnection -ComputerName "github.com" -Port 443 -ErrorAction Stop | Out-Null
}
catch {
    Write-Error "No internet connection or unable to reach GitHub. Please check your connection."
    exit 1
}

# Run main function
try {
    Main
    Write-Success "Script execution completed successfully!"
}
catch {
    Write-Error "Script execution failed: $($_.Exception.Message)"
    exit 1
}
