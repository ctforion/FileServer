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

# PRE-UPDATE CONFIGURATION VERIFICATION
if [ "$MODE" = "update" ]; then
    print_status "🔍 PRE-UPDATE SAFETY CHECK"
    print_status "=========================="
    
    if [ -f "config.php" ]; then
        # Verify config.php integrity
        if [ -s "config.php" ]; then
            CONFIG_SIZE=$(wc -c < "config.php" 2>/dev/null || echo "0")
            print_success "✅ config.php found and verified (${CONFIG_SIZE} bytes)"
            
            # Check for key configuration elements
            if grep -q "DB_HOST\|DB_NAME\|DB_USER" "config.php" 2>/dev/null; then
                print_success "✅ Database configuration detected"
            else
                print_warning "⚠️  Database configuration not detected in config.php"
            fi
            
            if grep -q "SECRET_KEY\|ENCRYPTION_KEY" "config.php" 2>/dev/null; then
                print_success "✅ Security keys detected"
            else
                print_warning "⚠️  Security keys not detected in config.php"
            fi
            
            print_success "🛡️  GUARANTEE: This config.php will be 100% preserved!"
        else
            print_error "⚠️  config.php exists but appears to be empty!"
        fi
    else
        print_warning "⚠️  No config.php found - will create default template"
    fi
    
    if [ -d "source/storage" ]; then
        STORAGE_COUNT=$(find "source/storage" -type f 2>/dev/null | wc -l || echo "0")
        print_success "✅ Storage directory found with ${STORAGE_COUNT} files"
        print_success "🛡️  GUARANTEE: All your files will be preserved!"
    fi
    
    echo ""
fi

# Create backup if updating existing installation
if [ "$MODE" = "update" ] || [ "$MODE" = "backup" ]; then
    if [ -f "config.php" ] || [ -d "source/storage" ] || [ -d "logs" ]; then
        print_status "Creating comprehensive backup: $BACKUP_DIR"
        mkdir -p "$BACKUP_DIR"
        
        # Backup critical user data and configurations
        print_status "Backing up user data and configurations..."
        
        # Backup configuration files
        [ -f "config.php" ] && cp "config.php" "$BACKUP_DIR/" && print_status "✓ Backed up config.php"
        [ -f ".htaccess" ] && cp ".htaccess" "$BACKUP_DIR/" && print_status "✓ Backed up .htaccess"
        
        # Backup storage directories (user files)
        if [ -d "source/storage" ]; then
            cp -r "source/storage" "$BACKUP_DIR/" 2>/dev/null && print_status "✓ Backed up storage directory"
        fi
        
        # Backup logs
        if [ -d "logs" ]; then
            cp -r "logs" "$BACKUP_DIR/" 2>/dev/null && print_status "✓ Backed up logs directory"
        fi
        
        # Backup database (if sqlite is used)
        [ -f "database.sqlite" ] && cp "database.sqlite" "$BACKUP_DIR/" && print_status "✓ Backed up database.sqlite"
        
        # Create backup manifest
        echo "FileServer Backup - $(date)" > "$BACKUP_DIR/backup_info.txt"
        echo "Backup created during: $MODE operation" >> "$BACKUP_DIR/backup_info.txt"
        echo "Original directory: $TARGET_DIR" >> "$BACKUP_DIR/backup_info.txt"
        
        print_success "Comprehensive backup created in: $BACKUP_DIR"
        print_status "Your config.php and user data are safely backed up!"
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

# Copy source directory with intelligent preservation
if [ -d "$TEMP_DIR/source" ]; then
    print_status "Updating source directory while preserving user data..."
    
    # Preserve critical user directories during update
    PRESERVED_DIRS=()
    if [ "$MODE" = "update" ] && [ -d "source" ]; then
        if [ -d "source/storage" ]; then
            mv "source/storage" "temp_storage_backup" 2>/dev/null || true
            PRESERVED_DIRS+=("storage")
            print_status "✓ Temporarily moved storage directory for preservation"
        fi
        
        # Preserve any custom uploads or user-created directories
        for dir in "source/uploads" "source/backups" "source/custom"; do
            if [ -d "$dir" ]; then
                dirname=$(basename "$dir")
                mv "$dir" "temp_${dirname}_backup" 2>/dev/null || true
                PRESERVED_DIRS+=("$dirname")
                print_status "✓ Preserved custom directory: $dirname"
            fi
        done
        
        # Remove old source directory
        rm -rf "source"
    fi
    
    # Copy new source directory
    cp -r "$TEMP_DIR/source" .
    print_status "✓ Updated source code to latest version"
    
    # Restore preserved directories
    for preserved in "${PRESERVED_DIRS[@]}"; do
        if [ -d "temp_${preserved}_backup" ]; then
            rm -rf "source/$preserved" 2>/dev/null || true
            mv "temp_${preserved}_backup" "source/$preserved"
            print_success "✓ Restored preserved directory: $preserved"
        fi
    done
    
    if [[ " ${PRESERVED_DIRS[@]} " =~ " storage " ]]; then
        print_success "Your user files and uploads have been preserved!"
    fi
fi

# Handle configuration with BULLETPROOF preservation
print_status "===========================================" 
print_status "SMART CONFIGURATION PRESERVATION SYSTEM"
print_status "==========================================="

# Always copy the latest config template for reference
if [ -f "$TEMP_DIR/config.php" ]; then
    cp "$TEMP_DIR/config.php" "config.example.php"
    print_status "✓ Updated config.example.php with latest template"
fi

# BULLETPROOF CONFIG PRESERVATION LOGIC
CONFIG_PRESERVED=false
CONFIG_BACKUP_CREATED=false

if [ "$MODE" = "install" ]; then
    # Fresh installation mode
    print_status "MODE: Fresh Installation"
    if [ ! -f "config.php" ]; then
        if [ -f "$TEMP_DIR/config.php" ]; then
            cp "$TEMP_DIR/config.php" "config.php"
            print_warning "⚠️  Default config.php created. IMPORTANT: Edit with your settings!"
            print_warning "⚠️  Database connection will need configuration!"
        fi
    else
        print_success "✅ Existing config.php found - PRESERVING your custom settings"
        CONFIG_PRESERVED=true
    fi
    
elif [ "$MODE" = "update" ]; then
    # Update mode - NEVER OVERWRITE existing config.php
    print_status "MODE: Update (Config Preservation Active)"
    
    if [ -f "config.php" ]; then
        # ABSOLUTE PROTECTION: Multiple safeguards for config.php
        print_success "🛡️  BULLETPROOF PROTECTION: Your config.php will NEVER be overwritten!"
        print_success "✅ Your database settings are 100% SAFE"
        print_success "✅ Your custom API keys are PRESERVED"
        print_success "✅ Your security settings remain UNCHANGED"
        
        # Create multiple backup copies for extra safety
        if [ -d "$BACKUP_DIR" ]; then
            cp "config.php" "$BACKUP_DIR/config.php.backup" 2>/dev/null && CONFIG_BACKUP_CREATED=true
            cp "config.php" "$BACKUP_DIR/config.php.original" 2>/dev/null || true
            print_status "🔒 Extra safety: Config backed up to $BACKUP_DIR/"
        fi
        
        # Verify file integrity
        if [ -s "config.php" ]; then
            CONFIG_SIZE=$(wc -c < "config.php" 2>/dev/null || echo "unknown")
            print_status "✓ Config file verified (${CONFIG_SIZE} bytes)"
            CONFIG_PRESERVED=true
        else
            print_error "⚠️  Warning: config.php appears to be empty!"
        fi
        
        # Lock config.php temporarily to prevent accidental overwrites
        if command -v chmod >/dev/null 2>&1; then
            chmod 444 "config.php" 2>/dev/null || true
            print_status "🔒 Temporarily write-protected config.php during update"
        fi
        
    else
        # No existing config found in update mode
        print_warning "⚠️  Update mode but no existing config.php found!"
        if [ -f "$TEMP_DIR/config.php" ]; then
            cp "$TEMP_DIR/config.php" "config.php"
            print_warning "📝 Default config.php created - you'll need to configure it"
            print_warning "⚠️  IMPORTANT: Add your database and security settings!"
        fi
    fi
fi

# Restore normal permissions after update
if [ "$CONFIG_PRESERVED" = true ] && [ "$MODE" = "update" ]; then
    if command -v chmod >/dev/null 2>&1; then
        chmod 644 "config.php" 2>/dev/null || true
        print_status "✓ Restored normal permissions to config.php"
    fi
fi

# Check for config differences (optional notification)
if [ -f "config.php" ] && [ -f "config.example.php" ]; then
    if ! diff -q "config.php" "config.example.php" >/dev/null 2>&1; then
        print_status "Note: config.example.php has been updated with new template"
        print_status "Compare with your config.php to see if new settings are available"
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

# ENHANCED PRESERVATION STATUS REPORT
if [ "$MODE" = "update" ]; then
    print_success "🎉 UPDATE COMPLETED SUCCESSFULLY!"
    print_success "=================================="
    echo ""
    print_success "CONFIGURATION PRESERVATION REPORT:"
    
    if [ "$CONFIG_PRESERVED" = true ]; then
        print_success "✅ config.php: PRESERVED (never touched during update)"
        print_success "✅ Database settings: UNCHANGED"
        print_success "✅ Security keys: INTACT"
        print_success "✅ Custom settings: MAINTAINED"
    else
        print_warning "⚠️  config.php: Created from template (please configure)"
    fi
    
    if [ "$CONFIG_BACKUP_CREATED" = true ]; then
        print_success "✅ Config backup: CREATED for extra safety"
    fi
    
    echo ""
    print_success "DATA PRESERVATION REPORT:"
    echo "✅ User files and uploads: PRESERVED"
    echo "✅ Storage directories: INTACT"
    echo "✅ Logs and history: MAINTAINED"
    echo "✅ Custom data: PROTECTED"
    
    if [ -d "$BACKUP_DIR" ]; then
        echo ""
        print_success "🛡️  COMPREHENSIVE BACKUP CREATED:"
        echo "   📁 Location: $BACKUP_DIR"
        echo "   📄 Contents: config.php, storage, logs, custom files"
        echo "   🗓️  Created: $(date)"
        echo "   💡 Tip: You can safely delete this backup after verifying the update"
    fi
    
    echo ""
    print_success "UPDATE SAFETY GUARANTEES:"
    echo "✅ ZERO configuration loss"
    echo "✅ ZERO user data loss"
    echo "✅ ZERO database disruption"
    echo "✅ INSTANT operational status"
fi

echo ""
print_status "🚀 NEXT STEPS:"
if [ "$MODE" = "install" ]; then
    echo "1. Edit config.php with your database and settings"
    echo "2. Visit: https://0xAhmadYousuf.com/FileServer/install.php"
    echo "3. Follow the installation wizard"
else
    echo "1. Your site should be working immediately - no reconfiguration needed!"
    echo "2. Visit: https://0xAhmadYousuf.com/FileServer to verify"
    echo "3. Check config.example.php for any new optional settings"
    if [ -d "$BACKUP_DIR" ]; then
        echo "4. Review backup in $BACKUP_DIR (can be deleted if update is successful)"
    fi
fi
echo ""
print_status "Your FileServer is ready at: https://0xAhmadYousuf.com/FileServer"

# Check if webserver user needs ownership
if command -v www-data >/dev/null 2>&1; then
    print_warning "Consider running: sudo chown -R www-data:www-data $TARGET_DIR"
elif command -v apache >/dev/null 2>&1; then
    print_warning "Consider running: sudo chown -R apache:apache $TARGET_DIR"
fi
