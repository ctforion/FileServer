#!/bin/bash

# Production Permission Fix Script for FileServer
# Run this script if you encounter permission issues after deployment

set -e

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

# Get target directory
TARGET_DIR="${1:-$(pwd)}"

print_status "🔧 FileServer Permission Fix Utility"
print_status "===================================="
print_status "Target Directory: $TARGET_DIR"

cd "$TARGET_DIR"

# Detect web server user
WEB_USER="www-data"
[ -d "/etc/httpd" ] && WEB_USER="apache"  # CentOS/RHEL
[ -d "/etc/nginx" ] && WEB_USER="nginx"   # Nginx

print_status "Detected web server user: $WEB_USER"

# Check if running as root or with sudo
if [ "$EUID" -eq 0 ]; then
    print_status "✅ Running as root"
    SUDO_CMD=""
elif command -v sudo >/dev/null 2>&1; then
    print_status "✅ Using sudo"
    SUDO_CMD="sudo"
else
    print_error "❌ Need root access or sudo to fix permissions"
    exit 1
fi

# Fix ownership
print_status "🔧 Setting ownership to $WEB_USER..."
$SUDO_CMD chown -R "$WEB_USER:$WEB_USER" . || {
    print_error "Failed to set ownership"
    exit 1
}
print_success "✅ Ownership set to $WEB_USER"

# Fix directory permissions
print_status "🔧 Setting directory permissions..."
$SUDO_CMD find . -type d -exec chmod 755 {} \; || {
    print_error "Failed to set directory permissions"
    exit 1
}
print_success "✅ Directory permissions set to 755"

# Fix file permissions
print_status "🔧 Setting file permissions..."
$SUDO_CMD find . -type f -name "*.php" -exec chmod 644 {} \; || {
    print_error "Failed to set PHP file permissions"
    exit 1
}
$SUDO_CMD find . -type f -name "*.html" -exec chmod 644 {} \; || true
$SUDO_CMD find . -type f -name "*.css" -exec chmod 644 {} \; || true
$SUDO_CMD find . -type f -name "*.js" -exec chmod 644 {} \; || true
$SUDO_CMD find . -type f -name "*.md" -exec chmod 644 {} \; || true
print_success "✅ File permissions set to 644"

# Fix storage directory permissions (needs to be writable)
if [ -d "source/storage" ]; then
    print_status "🔧 Setting storage directory permissions..."
    $SUDO_CMD chmod -R 755 source/storage/
    $SUDO_CMD chmod -R g+w source/storage/ 2>/dev/null || true
    print_success "✅ Storage directory permissions fixed"
fi

# Fix logs directory permissions
if [ -d "logs" ]; then
    print_status "🔧 Setting logs directory permissions..."
    $SUDO_CMD chmod -R 755 logs/
    $SUDO_CMD chmod -R g+w logs/ 2>/dev/null || true
    print_success "✅ Logs directory permissions fixed"
fi

# Set config.php permissions (readable by web server)
if [ -f "config.php" ]; then
    print_status "🔧 Setting config.php permissions..."
    $SUDO_CMD chmod 644 config.php
    print_success "✅ config.php permissions set"
fi

# Make sure .htaccess files are readable
print_status "🔧 Setting .htaccess permissions..."
$SUDO_CMD find . -name ".htaccess" -exec chmod 644 {} \; 2>/dev/null || true
print_success "✅ .htaccess permissions set"

# Special handling for writable directories
WRITABLE_DIRS=("source/storage/temp" "source/storage/private" "logs")
for dir in "${WRITABLE_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        print_status "🔧 Making $dir writable..."
        $SUDO_CMD chmod 775 "$dir"
        $SUDO_CMD chmod g+s "$dir" 2>/dev/null || true  # Set group sticky bit
    fi
done

print_success "🎉 ALL PERMISSIONS FIXED!"
print_status "============================================"
print_status "✅ Ownership: $WEB_USER:$WEB_USER"
print_status "✅ Directories: 755 (readable/executable)"
print_status "✅ Files: 644 (readable)"
print_status "✅ Storage: 775 (writable by web server)"
print_status "✅ Logs: 775 (writable by web server)"
print_status "============================================"

# Verify installation
if [ -f "index.php" ] && [ -d "source" ]; then
    print_success "🚀 FileServer installation verified!"
    print_status "Your FileServer should now be accessible via web browser"
else
    print_warning "⚠️  Some files may be missing. Consider re-running the installation."
fi

print_status "Script completed successfully!"
