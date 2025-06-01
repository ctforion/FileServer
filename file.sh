#!/bin/bash

# PHP File Storage Server - Download Script
# GitHub Repository: https://github.com/0xAhmadYousuf/FileServer
# This script downloads all files individually and recreates the exact directory structure

set -e  # Exit on any error

# Configuration
GITHUB_USER="0xAhmadYousuf"
REPO_NAME="FileServer"
BASE_URL="https://raw.githubusercontent.com/${GITHUB_USER}/${REPO_NAME}/main"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to download a file
download_file() {
    local file_path="$1"
    local url="${BASE_URL}/${file_path}"
    
    # Create directory if it doesn't exist
    local dir_path=$(dirname "$file_path")
    if [ ! -d "$dir_path" ]; then
        mkdir -p "$dir_path"
        print_status "Created directory: $dir_path"
    fi
    
    # Download the file
    print_status "Downloading: $file_path"
    if wget -q "$url" -O "$file_path"; then
        print_success "Downloaded: $file_path"
    else
        print_error "Failed to download: $file_path"
        return 1
    fi
}

# Function to create empty directories with .htaccess files
create_directory_structure() {
    print_status "Creating directory structure..."
    
    # Create all necessary directories
    directories=(
        "cache/api"
        "cache/data"
        "cache/search"
        "cache/session"
        "cache/templates"
        "cache/thumbnails"
        "plugins/available"
        "plugins/installed"
        "plugins/manager"
        "storage/archive"
        "storage/backup"
        "storage/index"
        "storage/public"
        "storage/quarantine"
        "storage/shared"
        "storage/temp"
        "storage/thumbnails"
    )
    
    for dir in "${directories[@]}"; do
        mkdir -p "$dir"
        print_status "Created directory: $dir"
    done
    
    print_success "Directory structure created successfully"
}

# Main download function
main() {
    print_status "Starting PHP File Storage Server download..."
    print_status "Repository: https://github.com/${GITHUB_USER}/${REPO_NAME}"
    print_status "=========================================="
    
    # Create directory structure first
    create_directory_structure
    
    print_status "Downloading files..."
    print_status "=========================================="
    
    # Root files
    download_file ".htaccess"
    download_file "config.php"
    download_file "example.env"
    download_file "generate_migrations.php"
    download_file "index.php"
    download_file "Proposal.md"
    download_file "README.md"
    download_file "setup.php"
    
    # API files
    download_file "api/.htaccess"
    download_file "api/index.php"
    
    # API - Admin
    download_file "api/admin/dashboard.php"
    download_file "api/admin/settings.php"
    download_file "api/admin/users.php"
    
    # API - Auth
    download_file "api/auth/2fa.php"
    download_file "api/auth/login.php"
    download_file "api/auth/logout.php"
    download_file "api/auth/register.php"
    download_file "api/auth/reset.php"
    
    # API - Controllers
    download_file "api/controllers/AdminController.php"
    download_file "api/controllers/AuthController.php"
    download_file "api/controllers/BaseController.php"
    download_file "api/controllers/FileController.php"
    download_file "api/controllers/PluginController.php"
    download_file "api/controllers/SearchController.php"
    download_file "api/controllers/SystemController.php"
    download_file "api/controllers/UserController.php"
    download_file "api/controllers/WebhookController.php"
    
    # API - Files
    download_file "api/files/delete.php"
    download_file "api/files/download.php"
    download_file "api/files/list.php"
    download_file "api/files/metadata.php"
    download_file "api/files/upload.php"
    
    # API - Quota
    download_file "api/quota/manage.php"
    download_file "api/quota/stats.php"
    
    # API - Search
    download_file "api/search/advanced.php"
    download_file "api/search/index.php"
    download_file "api/search/suggestions.php"
    
    # API - Sync
    download_file "api/sync/checksum.php"
    download_file "api/sync/status.php"
    
    # API - Webhook
    download_file "api/webhook/manage.php"
    download_file "api/webhook/test.php"
    
    # Assets - CSS
    download_file "assets/css/app.css"
    
    # Assets - JS
    download_file "assets/js/admin.js"
    download_file "assets/js/app.js"
    download_file "assets/js/dashboard.js"
    download_file "assets/js/files.js"
    download_file "assets/js/login.js"
    download_file "assets/js/profile.js"
    download_file "assets/js/search.js"
    download_file "assets/js/settings.js"
    download_file "assets/js/upload.js"
    
    # Core files
    download_file "core/.htaccess"
    
    # Core - Auth
    download_file "core/auth/Auth.php"
    
    # Core - Database
    download_file "core/database/Database.php"
    download_file "core/database/Migration.php"
    
    # Core - Database Migrations
    download_file "core/database/migrations/2024_01_01_000800_create_email_queue_table.php"
    download_file "core/database/migrations/2024_01_01_000801_create_webhook_deliveries_table.php"
    download_file "core/database/migrations/2024_01_01_000802_create_backups_table.php"
    download_file "core/database/migrations/2024_01_01_000803_create_update_log_table.php"
    
    # Core - Email
    download_file "core/email/EmailManager.php"
    
    # Core - Monitoring
    download_file "core/monitoring/Monitor.php"
    
    # Core - Plugin
    download_file "core/plugin/PluginManager.php"
    
    # Core - Storage
    download_file "core/storage/FileManager.php"
    
    # Core - Template
    download_file "core/template/TemplateEngine.php"
    
    # Core - Update
    download_file "core/update/UpdateManager.php"
    
    # Core - Utils
    download_file "core/utils/EnvLoader.php"
    
    # Core - Webhook
    download_file "core/webhook/WebhookManager.php"
    
    # Language files (14 languages)
    download_file "languages/ar.json"
    download_file "languages/bn.json"
    download_file "languages/config.json"
    download_file "languages/de.json"
    download_file "languages/en.json"
    download_file "languages/es.json"
    download_file "languages/fr.json"
    download_file "languages/id.json"
    download_file "languages/it.json"
    download_file "languages/ja.json"
    download_file "languages/ko.json"
    download_file "languages/pt.json"
    download_file "languages/ru.json"
    download_file "languages/ur.json"
    download_file "languages/zh.json"
    
    # Logs
    download_file "logs/.htaccess"
    
    # Storage .htaccess files
    download_file "storage/.htaccess"
    download_file "storage/private/.htaccess"
    download_file "storage/system/.htaccess"
    
    # Templates - Components
    download_file "templates/components/footer.php"
    download_file "templates/components/navbar.php"
    download_file "templates/components/sidebar.php"
    
    # Templates - Layouts
    download_file "templates/layouts/main.php"
    
    # Templates - Pages
    download_file "templates/pages/admin.php"
    download_file "templates/pages/dashboard.php"
    download_file "templates/pages/files.php"
    download_file "templates/pages/home.php"
    download_file "templates/pages/login.php"
    download_file "templates/pages/profile.php"
    download_file "templates/pages/search.php"
    download_file "templates/pages/settings.php"
    download_file "templates/pages/upload.php"
    
    print_status "=========================================="
    print_success "Download completed successfully!"
    print_status "=========================================="
    
    # Set proper permissions
    print_status "Setting proper permissions..."
    chmod -R 755 .
    chmod -R 777 cache/
    chmod -R 777 logs/
    chmod -R 777 storage/
    
    print_success "Permissions set successfully!"
    
    # Display next steps
    echo
    print_status "Next Steps:"
    echo "1. Copy example.env to .env and configure your settings"
    echo "2. Create a database and update database credentials in .env"
    echo "3. Run 'php setup.php' to initialize the database"
    echo "4. Configure your web server to point to this directory"
    echo "5. Access the application through your web browser"
    echo
    print_success "PHP File Storage Server is ready to use!"
    print_status "Repository: https://github.com/${GITHUB_USER}/${REPO_NAME}"
    print_status "Documentation: See README.md for detailed setup instructions"
}

# Check if wget is available
if ! command -v wget &> /dev/null; then
    print_error "wget is required but not installed. Please install wget first."
    exit 1
fi

# Run main function
main

print_success "Script execution completed!"
