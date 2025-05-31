#!/bin/bash

# Portable PHP File Storage Server - Auto Deploy/Update Script
# This script downloads the latest version from GitHub and deploys it

set -e  # Exit on any error

# Configuration
REPO_URL="https://github.com/ctforion/FileServer.git"
REPO_BRANCH="main"
TEMP_DIR="/tmp/fileserver_update_$(date +%s)"
BACKUP_DIR="backup_$(date +%Y%m%d_%H%M%S)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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

# Function to show usage
show_usage() {
    echo "Usage: $0 [target_directory] [mode]"
    echo ""
    echo "Modes:"
    echo "  install   - Fresh installation (default)"
    echo "  update    - Update existing installation"
    echo "  backup    - Create backup before update"
    echo ""
    echo "Examples:"
    echo "  $0 /var/www/html/FileServer install"
    echo "  $0 /var/www/html/FileServer update"
    echo "  $0 . update  # Update current directory"
}

# Parse arguments
TARGET_DIR="${1:-$(pwd)}"
MODE="${2:-install}"

if [ "$1" = "--help" ] || [ "$1" = "-h" ]; then
    show_usage
    exit 0
fi

# Validate target directory
if [ ! -d "$(dirname "$TARGET_DIR")" ]; then
    print_error "Parent directory $(dirname "$TARGET_DIR") does not exist!"
    exit 1
fi

print_status "Starting FileServer deployment..."
print_status "Target Directory: $TARGET_DIR"
print_status "Mode: $MODE"

# Create target directory if it doesn't exist
if [ ! -d "$TARGET_DIR" ]; then
    print_status "Creating target directory: $TARGET_DIR"
    mkdir -p "$TARGET_DIR"
fi

# Change to target directory
cd "$TARGET_DIR"

# Create backup if updating existing installation
if [ "$MODE" = "update" ] || [ "$MODE" = "backup" ]; then
    if [ -f "config.php" ]; then
        print_status "Creating backup: $BACKUP_DIR"
        mkdir -p "$BACKUP_DIR"
        
        # Backup important files
        [ -f "config.php" ] && cp "config.php" "$BACKUP_DIR/"
        [ -d "source/storage" ] && cp -r "source/storage" "$BACKUP_DIR/" 2>/dev/null || true
        [ -d "logs" ] && cp -r "logs" "$BACKUP_DIR/" 2>/dev/null || true
        
        print_success "Backup created in: $BACKUP_DIR"
    else
        print_warning "No existing installation found, proceeding with fresh install"
        MODE="install"
    fi
fi

# Clone repository to temporary directory
print_status "Downloading latest version from GitHub..."
if ! git clone -b "$REPO_BRANCH" "$REPO_URL" "$TEMP_DIR"; then
    print_error "Failed to clone repository!"
    exit 1
fi

# Copy source files
print_status "Copying source files..."

# Copy main files
cp "$TEMP_DIR/index.php" . 2>/dev/null || true
cp "$TEMP_DIR/install.php" . 2>/dev/null || true
cp "$TEMP_DIR/update.php" . 2>/dev/null || true
cp "$TEMP_DIR/.htaccess" . 2>/dev/null || true

# Copy source directory
if [ -d "$TEMP_DIR/source" ]; then
    print_status "Copying source directory..."
    if [ -d "source" ]; then
        # Preserve storage directory during update
        if [ -d "source/storage" ] && [ "$MODE" = "update" ]; then
            mv "source/storage" "temp_storage_backup" 2>/dev/null || true
        fi
        rm -rf "source"
    fi
    cp -r "$TEMP_DIR/source" .
    
    # Restore storage directory
    if [ -d "temp_storage_backup" ] && [ "$MODE" = "update" ]; then
        rm -rf "source/storage" 2>/dev/null || true
        mv "temp_storage_backup" "source/storage"
        print_status "Preserved existing storage directory"
    fi
fi

# Handle configuration
if [ "$MODE" = "install" ] || [ ! -f "config.php" ]; then
    print_status "Creating configuration file..."
    if [ -f "$TEMP_DIR/config.php" ]; then
        cp "$TEMP_DIR/config.php" "config.example.php"
        if [ ! -f "config.php" ]; then
            cp "$TEMP_DIR/config.php" "config.php"
            print_warning "Default config.php created. Please edit it with your settings!"
        fi
    fi
else
    print_status "Preserving existing configuration"
    if [ -f "$TEMP_DIR/config.php" ]; then
        cp "$TEMP_DIR/config.php" "config.example.php"
        print_status "Updated config.example.php with latest template"
    fi
fi

# Copy documentation
cp "$TEMP_DIR/README.md" . 2>/dev/null || true
cp "$TEMP_DIR/RULES.md" . 2>/dev/null || true

# Set proper permissions
print_status "Setting file permissions..."
chmod 755 . 2>/dev/null || true
chmod 644 *.php 2>/dev/null || true
chmod 644 *.md 2>/dev/null || true

if [ -d "source" ]; then
    find source -type f -name "*.php" -exec chmod 644 {} \; 2>/dev/null || true
    find source -type d -exec chmod 755 {} \; 2>/dev/null || true
fi

# Create required directories
print_status "Creating required directories..."
mkdir -p source/storage/{public,private,temp,shared,thumbnails} 2>/dev/null || true
mkdir -p logs 2>/dev/null || true

# Set storage permissions
chmod 755 source/storage 2>/dev/null || true
chmod 755 source/storage/* 2>/dev/null || true
chmod 755 logs 2>/dev/null || true

# Create .htaccess for protected directories
print_status "Creating security files..."
echo "Order deny,allow" > source/storage/private/.htaccess
echo "Deny from all" >> source/storage/private/.htaccess

echo "Order deny,allow" > source/storage/temp/.htaccess
echo "Deny from all" >> source/storage/temp/.htaccess

echo "Order deny,allow" > logs/.htaccess
echo "Deny from all" >> logs/.htaccess

# Clean up temporary directory
rm -rf "$TEMP_DIR"

# Create update timestamp
echo "$(date '+%Y-%m-%d %H:%M:%S')" > last_update.txt

print_success "FileServer deployment completed!"
echo ""
print_status "Next steps:"
if [ "$MODE" = "install" ]; then
    echo "1. Edit config.php with your database and settings"
    echo "2. Visit: https://0xAhmadYousuf.com/FileServer/install.php"
    echo "3. Follow the installation wizard"
else
    echo "1. Check if any config.php updates are needed (see config.example.php)"
    echo "2. Visit your site to verify everything is working"
fi
echo ""
print_status "Your FileServer is ready at: https://0xAhmadYousuf.com/FileServer"

# Check if webserver user needs ownership
if command -v www-data >/dev/null 2>&1; then
    print_warning "Consider running: sudo chown -R www-data:www-data $TARGET_DIR"
elif command -v apache >/dev/null 2>&1; then
    print_warning "Consider running: sudo chown -R apache:apache $TARGET_DIR"
fi
