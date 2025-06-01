# FileServer Deployment Checklist

## Pre-Deployment Setup ✅

### 1. System Requirements
- [ ] PHP 7.4 or higher installed
- [ ] Web server with mod_rewrite enabled
- [ ] Write permissions for directories
- [ ] At least 1GB available disk space

### 2. File Structure Verification
- [✅] All core system files present
- [✅] Database files initialized in `/data/`
- [✅] Storage directories created in `/storage/`
- [✅] Web interface files in `/web/`
- [✅] API endpoints in `/api/`
- [✅] Security `.htaccess` files in place

### 3. Database Initialization
- [✅] `users.json` - Default admin user created
- [✅] `files.json` - File metadata storage ready
- [✅] `sessions.json` - Session management ready
- [✅] `settings.json` - System settings configured
- [✅] Log directory structure created

## Deployment Steps

### 1. Choose Deployment Method

**Option A: Development/Testing (PHP Built-in Server)**
```bash
cd /path/to/FileServer
php -S localhost:8000
```
- Access: http://localhost:8000
- Best for: Development, testing, small teams

**Option B: Production (Apache/Nginx)**
```bash
# Copy files to web server directory
cp -r FileServer/ /var/www/html/fileserver/
# Set permissions
chmod 755 /var/www/html/fileserver/data/
chmod 755 /var/www/html/fileserver/storage/
chmod -R 644 /var/www/html/fileserver/data/*.json
```
- Access: http://yourdomain.com/fileserver/
- Best for: Production environments

### 2. Directory Permissions
```bash
# Set directory permissions
find FileServer/ -type d -exec chmod 755 {} \;
find FileServer/ -type f -exec chmod 644 {} \;

# Ensure write access for data directories
chmod 755 FileServer/data/
chmod 755 FileServer/storage/
chmod 755 FileServer/storage/*/
chmod 755 FileServer/data/logs/
```

### 3. Security Configuration
- [ ] Change default admin password (admin/admin123)
- [ ] Review allowed file extensions in config.php
- [ ] Set appropriate file size limits
- [ ] Configure user quotas
- [ ] Review security settings

### 4. Test Basic Functionality
- [ ] Access login page
- [ ] Login with admin credentials
- [ ] Upload a test file
- [ ] Download the test file
- [ ] Delete the test file
- [ ] Access admin dashboard
- [ ] Create a new user account
- [ ] Test user profile management

## Post-Deployment Tasks

### 1. Security Hardening
```bash
# Review configuration
cat config.php

# Check .htaccess protection
curl http://yourdomain.com/fileserver/data/users.json
# Should return 403 Forbidden

# Test file upload security
# Try uploading .php files (should be blocked)
```

### 2. Monitoring Setup
- [ ] Set up log rotation for `/data/logs/`
- [ ] Monitor disk space usage
- [ ] Set up regular database backups
- [ ] Configure error notifications

### 3. User Management
- [ ] Create initial user accounts
- [ ] Set user quotas
- [ ] Configure user roles
- [ ] Test user permissions

### 4. Performance Optimization
- [ ] Enable PHP OPcache
- [ ] Configure appropriate PHP memory limits
- [ ] Set up log cleanup schedules
- [ ] Monitor system performance

## Production Checklist

### Apache Configuration
```apache
<VirtualHost *:80>
    ServerName fileserver.yourdomain.com
    DocumentRoot /var/www/html/fileserver
    
    <Directory /var/www/html/fileserver>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Increase upload limits
    php_admin_value upload_max_filesize 50M
    php_admin_value post_max_size 50M
    php_admin_value max_execution_time 300
    
    ErrorLog ${APACHE_LOG_DIR}/fileserver_error.log
    CustomLog ${APACHE_LOG_DIR}/fileserver_access.log combined
</VirtualHost>
```

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name fileserver.yourdomain.com;
    root /var/www/html/fileserver;
    index index.php;
    
    # Increase upload limits
    client_max_body_size 50M;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Protect data directory
    location /data {
        deny all;
        return 403;
    }
    
    access_log /var/log/nginx/fileserver_access.log;
    error_log /var/log/nginx/fileserver_error.log;
}
```

### SSL/HTTPS Setup (Recommended)
```bash
# Install Let's Encrypt certificate
certbot --apache -d fileserver.yourdomain.com
# or for Nginx
certbot --nginx -d fileserver.yourdomain.com
```

## Maintenance Tasks

### Daily
- [ ] Check error logs for issues
- [ ] Monitor disk space usage
- [ ] Review security logs

### Weekly
- [ ] Clean up temporary files
- [ ] Review user activity logs
- [ ] Check system performance

### Monthly
- [ ] Create database backup
- [ ] Update user quotas if needed
- [ ] Review and archive old logs
- [ ] Check for security updates

## Troubleshooting

### Common Issues
1. **Permission Denied Errors**
   - Check directory permissions
   - Verify web server user has write access

2. **Upload Failures**
   - Check PHP upload_max_filesize
   - Verify available disk space
   - Check file extension restrictions

3. **Login Issues**
   - Verify session directory permissions
   - Check database file accessibility
   - Review security logs

4. **Performance Issues**
   - Enable PHP OPcache
   - Check available memory
   - Review log file sizes

### Quick Tests
```bash
# Test system integration
php integration_test.php

# Check PHP configuration
php -m | grep -E "(json|session|fileinfo)"

# Verify file permissions
ls -la data/ storage/

# Check log files
tail -f data/logs/error.log
```

## Success Criteria

✅ **Deployment Successful When:**
- [ ] Login page loads without errors
- [ ] Admin can login with default credentials
- [ ] File upload works correctly
- [ ] File download works correctly
- [ ] Admin dashboard is accessible
- [ ] User registration works
- [ ] Logs are being created
- [ ] Security measures are active

---

## Next Steps After Deployment

1. **Change default admin password immediately**
2. **Create your first regular user account**
3. **Upload and test file operations**
4. **Configure system settings via admin panel**
5. **Set up monitoring and backup procedures**

**🔒 Security Reminder: Always change the default admin password (admin/admin123) before production use!**
