# Production Deployment Guide - FileServer

## Quick Fix for Permission Issues

If you're encountering permission errors during deployment (especially when running as `www-data` or web server user), follow these steps:

### Method 1: Use the Enhanced Install Script

The install script now automatically detects and handles permission issues:

```bash
# Download and run the enhanced installer
curl -sSL https://raw.githubusercontent.com/ctforion/FileServer/main/install.sh | bash -s /var/www/html/FileServer update
```

### Method 2: Manual Permission Fix

If you still encounter issues, use the dedicated permission fix script:

```bash
# Make the permission fix script executable
chmod +x fix_permissions.sh

# Run it with sudo (will fix all permissions)
sudo ./fix_permissions.sh

# Or specify target directory
sudo ./fix_permissions.sh /var/www/html/FileServer
```

### Method 3: Manual Commands

If you prefer manual control, run these commands:

```bash
# Navigate to your FileServer directory
cd /var/www/html/FileServer

# Fix ownership (replace www-data with your web server user if different)
sudo chown -R www-data:www-data .

# Fix directory permissions
sudo find . -type d -exec chmod 755 {} \;

# Fix file permissions
sudo find . -type f -name "*.php" -exec chmod 644 {} \;
sudo find . -type f -name "*.html" -exec chmod 644 {} \;
sudo find . -type f -name "*.css" -exec chmod 644 {} \;
sudo find . -type f -name "*.js" -exec chmod 644 {} \;
sudo find . -name ".htaccess" -exec chmod 644 {} \;

# Make storage and logs writable
sudo chmod -R 775 source/storage/
sudo chmod -R 775 logs/

# Set group sticky bit for better permission handling
sudo chmod g+s source/storage/
sudo chmod g+s logs/
```

## Alternative Deployment Method

If permission issues persist, the installer now supports an alternative deployment method:

1. **Backup and Replace**: Instead of overwriting files, it backs up existing files and replaces them
2. **File-by-File Copy**: Copies individual files instead of entire directories
3. **Graceful Degradation**: Falls back to alternative methods when standard methods fail

## Troubleshooting Common Issues

### Issue: "Permission denied" errors
**Solution**: Run the permission fix script or use Method 3 commands above

### Issue: "Cannot write to source directory"
**Solution**: The installer will automatically use alternative deployment method

### Issue: "www-data cannot overwrite files"
**Solution**: Enhanced installer detects this and uses backup-and-replace method

### Issue: Config gets reset during update
**Solution**: The installer has bulletproof config preservation - this should never happen

## Verification Steps

After running the installation/permission fix:

1. **Check file ownership**:
   ```bash
   ls -la /var/www/html/FileServer/
   ```
   Should show `www-data www-data` (or your web server user)

2. **Check permissions**:
   ```bash
   ls -la /var/www/html/FileServer/source/
   ```
   Directories should be `755`, files should be `644`

3. **Test web access**:
   Visit: `https://0xAhmadYousuf.com/FileServer`

4. **Check storage write permissions**:
   ```bash
   sudo -u www-data touch /var/www/html/FileServer/source/storage/test_write.txt
   ```
   Should create file without errors

## Web Server Specific Notes

### Apache (www-data user)
- Most common on Ubuntu/Debian
- Uses `www-data:www-data` ownership
- Standard configuration works

### Nginx (nginx user)
- Common on CentOS/RHEL
- Uses `nginx:nginx` ownership
- Installer auto-detects this

### Custom Web Servers
- Installer attempts to detect web server user
- Manual specification may be needed

## Security Considerations

1. **Never run as root**: The web server should run as `www-data` or similar
2. **Protect config.php**: Should be readable but not writable by web server
3. **Secure storage**: Private storage should have `.htaccess` protection
4. **Regular updates**: Use the auto-update feature to keep secure

## Auto-Update Feature

Once deployed, you can update FileServer using the web interface:

1. Access admin panel: `https://0xAhmadYousuf.com/FileServer/admin`
2. Click "Check for Updates" in System Status card
3. Click "Update Now" to perform automatic update
4. Config and user data are automatically preserved

## Support

If you continue experiencing issues:

1. Check the installation logs for specific error messages
2. Verify your web server configuration
3. Ensure PHP has proper permissions to write to the directory
4. Consider running the permission fix script multiple times if needed

The enhanced installer is designed to handle 99% of permission issues automatically while ensuring your configuration and data are never lost during updates.
