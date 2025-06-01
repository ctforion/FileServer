# PHP File Storage Server

A portable, secure, and feature-rich file storage server built in pure PHP with no external dependencies. Designed for easy deployment and management with a modern web interface.

## Features

- **Portable Architecture**: Single config.php file controls all settings
- **No Dependencies**: Pure PHP implementation, no Composer required
- **Modern Interface**: Responsive design with drag-and-drop uploads
- **File Sharing**: Secure link sharing with password protection and expiration
- **User Management**: Role-based access control with admin panel
- **Storage Management**: Per-user storage limits and usage tracking
- **Security**: CSRF protection, file validation, session management
- **Admin Panel**: Comprehensive system monitoring and user management
- **API Support**: RESTful API for programmatic access
- **Easy Updates**: Automated GitHub deployment and update system

## Quick Start

### 1. Download and Extract

```bash
# Clone or download the repository
git clone https://github.com/0xAhmadYousuf/FileServer.git
cd FileServer
```

### 2. Configure Settings

Edit `config.php` with your server details:

```php
<?php
// Basic Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'fileserver');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Site Configuration
define('SITE_NAME', 'My File Server');
define('BASE_URL', 'https://yourdomain.com');
define('BASE_PATH', '/FileServer');

// Storage Configuration
define('UPLOAD_PATH', __DIR__ . '/storage');
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB
define('DEFAULT_STORAGE_LIMIT', 1 * 1024 * 1024 * 1024); // 1GB
?>
```

### 3. Install

#### Option A: Web Installation (Recommended)
1. Upload files to your web server
2. Visit `https://yourdomain.com/FileServer/install.php`
3. Follow the 5-step installation wizard

#### Option B: Command Line Installation
```bash
# Make the script executable
chmod +x install.sh

# Run the installation script
./install.sh
```

### 4. Access Your Server

- **Main Interface**: `https://yourdomain.com/FileServer/`
- **Admin Panel**: Login as admin and access via dashboard
- **API Base**: `https://yourdomain.com/FileServer/api/`

## Directory Structure

```
FileServer/
├── config.php              # Main configuration file
├── index.php               # Entry point
├── install.php             # Web installation wizard
├── install.sh              # Automated deployment script
├── update.php              # Web-based updater
├── .htaccess               # Apache configuration
├── RULES.md                # Usage rules and guidelines
├── README.md               # This file
├── database/
│   └── schema.sql          # Database structure
├── source/
│   ├── core/               # Core PHP classes
│   │   ├── App.php         # Main application
│   │   ├── FileManager.php # File operations
│   │   ├── UserManager.php # User management
│   │   ├── ShareManager.php# Sharing functionality
│   │   ├── AdminManager.php# Admin operations
│   │   └── APIHandler.php  # API endpoints
│   └── web/                # Frontend assets
│       ├── templates/      # HTML templates
│       ├── assets/css/     # Stylesheets
│       └── assets/js/      # JavaScript files
└── storage/                # File storage (created during install)
    ├── files/              # User files
    ├── thumbnails/         # Generated thumbnails
    └── temp/               # Temporary files
```

## System Requirements

### Minimum Requirements
- **PHP**: 7.4 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **Web Server**: Apache 2.4+ or Nginx
- **Storage**: 1GB+ available disk space
- **Memory**: 128MB+ PHP memory limit

### Recommended Requirements
- **PHP**: 8.0 or higher
- **Database**: MySQL 8.0+ or MariaDB 10.6+
- **Storage**: 10GB+ available disk space
- **Memory**: 256MB+ PHP memory limit

### Required PHP Extensions
- `pdo_mysql` - Database connectivity
- `gd` or `imagick` - Image processing
- `fileinfo` - File type detection
- `json` - JSON processing
- `session` - Session management
- `curl` - For updates (optional)

## Configuration Options

### Basic Settings
```php
// Site Information
define('SITE_NAME', 'File Server');
define('SITE_DESCRIPTION', 'Secure file storage and sharing');
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// URLs and Paths
define('BASE_URL', 'https://yourdomain.com');
define('BASE_PATH', '/FileServer');

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'fileserver');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_PREFIX', 'fs_');
```

### Storage Settings
```php
// File Storage
define('UPLOAD_PATH', __DIR__ . '/storage');
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip');

// User Limits
define('DEFAULT_STORAGE_LIMIT', 1 * 1024 * 1024 * 1024); // 1GB
define('MAX_FILES_PER_USER', 1000);

// Thumbnails
define('GENERATE_THUMBNAILS', true);
define('THUMBNAIL_MAX_WIDTH', 300);
define('THUMBNAIL_MAX_HEIGHT', 300);
```

### Security Settings
```php
// Security
define('ENABLE_REGISTRATION', true);
define('REQUIRE_EMAIL_VERIFICATION', false);
define('SESSION_TIMEOUT', 3600); // 1 hour
define('ENABLE_2FA', false);

// File Security
define('SCAN_UPLOADS', true);
define('QUARANTINE_SUSPICIOUS', true);
```

## API Documentation

### Authentication
```bash
# Login
POST /api/auth/login
{
    "username": "user@example.com",
    "password": "password",
    "remember_me": false
}

# Register
POST /api/auth/register
{
    "username": "newuser",
    "email": "user@example.com",
    "password": "password",
    "full_name": "John Doe"
}
```

### File Operations
```bash
# Upload file
POST /api/files/upload
# Send as multipart/form-data with 'file' field

# List files
GET /api/files/list?page=1&limit=20&search=query

# Download file
GET /api/files/download/{file_id}

# Delete file
DELETE /api/files/delete/{file_id}
```

### Sharing
```bash
# Create share
POST /api/shares/create
{
    "file_id": "123",
    "expires_at": "2024-12-31",
    "password": "optional",
    "download_limit": 10
}

# Access shared file
POST /api/shares/access
{
    "token": "share_token",
    "password": "optional"
}
```

## Deployment

### Standard Web Hosting
1. Upload all files to your web directory
2. Create a MySQL database
3. Run the web installer at `/install.php`
4. Configure your domain to point to the FileServer directory

### VPS/Dedicated Server
```bash
# Clone repository
git clone https://github.com/0xAhmadYousuf/FileServer.git

# Set permissions
sudo chown -R www-data:www-data /var/www/fileserver
sudo chmod -R 755 /var/www/FileServer

# Run installer
cd /var/www/fileserver
sudo -u www-data ./install.sh
```

### Docker (Optional)
```dockerfile
FROM php:8.1-apache
COPY . /var/www/html/
RUN docker-php-ext-install pdo_mysql gd
EXPOSE 80
```

## Updates

### Automatic Updates
- Visit `/update.php` in your browser
- Or use the admin panel's update feature
- The system will backup current files and update from GitHub

### Manual Updates
```bash
# Backup current installation
cp -r FileServer FileServer_backup

# Download new version
wget https://github.com/0xAhmadYousuf/FileServer/archive/main.zip

# Extract and replace files (preserve config.php and storage/)
```

## Maintenance

### Regular Tasks
- **Weekly**: Check storage usage and clean temporary files
- **Monthly**: Review user accounts and access logs
- **Quarterly**: Update software and review security settings

### Admin Panel Tasks
- Monitor system performance
- Manage user accounts and permissions
- Review file uploads and sharing activity
- Backup database and files
- Update system settings

### Command Line Maintenance
```bash
# Clean up expired shares and sessions
php -r "require 'config.php'; require 'source/core/App.php'; (new AdminManager(...))->runMaintenance(['cleanup_expired_shares', 'cleanup_expired_sessions']);"

# Update storage usage statistics
php -r "require 'config.php'; require 'source/core/App.php'; (new AdminManager(...))->runMaintenance(['update_storage_usage']);"
```

## Troubleshooting

### Common Issues

#### Upload Fails
- Check PHP upload limits: `upload_max_filesize`, `post_max_size`
- Verify storage directory permissions (755 for directories, 644 for files)
- Ensure sufficient disk space

#### Database Connection Errors
- Verify database credentials in `config.php`
- Check MySQL/MariaDB service status
- Ensure database exists and user has proper permissions

#### Permission Denied Errors
```bash
# Fix file permissions
sudo chown -R www-data:www-data /path/to/fileserver
sudo chmod -R 755 /path/to/fileserver
sudo chmod -R 777 /path/to/fileserver/storage
```

#### Session Issues
- Check session save path permissions
- Verify session settings in PHP configuration
- Clear browser cookies and try again

### Log Files
- Application logs: Check admin panel → Logs section
- PHP errors: Check your web server error logs
- Database errors: Enable query logging in MySQL

### Getting Help
1. Check the `RULES.md` file for usage guidelines
2. Review error logs in the admin panel
3. Verify system requirements are met
4. Check file and directory permissions

## Security Considerations

### File Upload Security
- All uploads are validated for type and content
- Executable files are blocked by default
- Files are stored outside the web root when possible
- Virus scanning integration available

### User Security
- Passwords are hashed using PHP's `password_hash()`
- Sessions are secured with httpOnly and secure flags
- CSRF protection on all forms
- Account lockout after failed login attempts

### Server Security
- Configure HTTPS/SSL for all connections
- Use strong database passwords
- Regular security updates
- Monitor access logs for suspicious activity

## License

This project is released under the MIT License. See LICENSE file for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## Support

For support and questions:
- Check the documentation in `RULES.md`
- Review the troubleshooting section above
- Create an issue on GitHub
- Contact the development team

---

**Note**: This file storage server is designed for ease of use and portability. Always ensure you have proper backups and security measures in place for production deployments.
