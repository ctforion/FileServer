# Production Deployment Fix for FileServer

## 🚨 PERMISSION ISSUE RESOLUTION

You're encountering permission issues because the existing FileServer directory has restrictive permissions. Here's how to fix it:

## 🔧 IMMEDIATE FIX COMMANDS

Run these commands on your server:

```bash
# 1. Navigate to the FileServer directory
cd /www/wwwroot/0xAhmadYousuf.com/FileServer

# 2. Fix ownership and permissions BEFORE running install script
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 644 *.php *.md

# 3. Make directories writable
sudo chmod -R 755 source/
sudo mkdir -p logs source/storage/{public,private,temp,shared,thumbnails}
sudo chmod -R 755 logs source/storage/

# 4. NOW run the install script as root (not www-data)
sudo ./install.sh . update
```

## 🛡️ ALTERNATIVE: Clean Installation

If the permission fix doesn't work, do a clean installation:

```bash
# 1. Backup existing installation (if needed)
sudo cp -r /www/wwwroot/0xAhmadYousuf.com/FileServer /tmp/fileserver_backup

# 2. Remove existing directory
sudo rm -rf /www/wwwroot/0xAhmadYousuf.com/FileServer

# 3. Create fresh directory
sudo mkdir -p /www/wwwroot/0xAhmadYousuf.com/FileServer
cd /www/wwwroot/0xAhmadYousuf.com/FileServer

# 4. Download and run install script
sudo wget https://raw.githubusercontent.com/ctforion/FileServer/main/install.sh
sudo chmod +x install.sh
sudo ./install.sh . install

# 5. Set proper ownership
sudo chown -R www-data:www-data .
```

## 🔍 WHY THIS HAPPENED

The issue occurred because:
1. Files were cloned with `git clone` which preserves original permissions
2. Running as `sudo -u www-data` limited write permissions
3. Existing directories had restrictive permissions

## ✅ RECOMMENDED PRODUCTION SETUP

After fixing the permissions, follow this sequence:

```bash
# 1. Fix permissions (run the commands above)
# 2. Run the enhanced install script
sudo ./install.sh . update

# 3. Configure your database in config.php
sudo nano config.php

# 4. Set final permissions
sudo chown -R www-data:www-data .
sudo chmod 755 .
sudo chmod 644 *.php
sudo chmod 755 source/ logs/
sudo chmod -R 755 source/storage/

# 5. Visit your installation wizard
# https://0xAhmadYousuf.com/FileServer/install.php
```

## 🚀 ENHANCED SCRIPT BENEFITS

Once deployed, your enhanced install.sh provides:
- 🛡️ Bulletproof config.php preservation
- 📦 Comprehensive backup system
- 🔍 Pre-update verification
- 📊 Professional status reporting
- 🎯 Zero-downtime updates

## 🔐 SECURITY NOTES

After successful deployment:
1. Change default passwords in config.php
2. Configure your database settings
3. Set up SSL/TLS for HTTPS
4. Review file permissions regularly

## 📞 SUPPORT

If you still encounter issues:
1. Check the error logs: `sudo tail -f /var/log/apache2/error.log`
2. Verify PHP modules: `php -m`
3. Test database connection in config.php

Your FileServer will be accessible at: https://0xAhmadYousuf.com/FileServer
