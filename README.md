# FileServer - Comprehensive PHP File Management System

A modern, secure, and feature-rich file management system built with PHP. FileServer provides a web-based interface for file uploads, downloads, sharing, and administration with robust security features and user management.

## Features

### Core Features
- **File Management**: Upload, download, rename, delete, and organize files
- **User Authentication**: Secure login system with role-based access control
- **File Sharing**: Share files with customizable permissions and expiration dates
- **Search & Filter**: Advanced search functionality with multiple filter options
- **Bulk Operations**: Perform operations on multiple files simultaneously
- **File Compression**: Create and extract ZIP archives
- **File Versioning**: Maintain multiple versions of files
- **Thumbnail Generation**: Automatic thumbnail generation for images

### Security Features
- **Access Control**: Role-based permissions (Admin, User, Guest)
- **IP Blocking**: Automatic blocking of suspicious IP addresses
- **File Quarantine**: Suspicious files are quarantined for manual review
- **Session Management**: Secure session handling with timeout
- **CSRF Protection**: Cross-site request forgery protection
- **Input Validation**: Comprehensive input sanitization and validation
- **Secure File Storage**: Files stored outside web root with access controls

### Administrative Features
- **User Management**: Create, edit, and manage user accounts
- **System Monitoring**: Real-time system status and performance metrics
- **Activity Logging**: Comprehensive logging of all system activities
- **Backup & Restore**: Automated backup system with restoration capabilities
- **Configuration Management**: Web-based system configuration
- **Maintenance Mode**: Enable maintenance mode for system updates

### Modern UI/UX Features
- **Responsive Design**: Mobile-friendly interface that works on all devices
- **Theme System**: Light, dark, and auto themes with system preference detection
- **Drag & Drop**: Intuitive drag-and-drop file upload
- **Progress Tracking**: Real-time upload and operation progress
- **Notifications**: Toast notifications for user feedback
- **Keyboard Shortcuts**: Productivity-enhancing keyboard shortcuts
- **Context Menus**: Right-click context menus for quick actions

## System Requirements

### Server Requirements
- **PHP**: 7.4 or higher (8.0+ recommended)
- **Web Server**: Apache with mod_rewrite or Nginx
- **Extensions**: json, mbstring, fileinfo, zip, gd, curl

### PHP Extensions
- `json` - JSON data handling
- `mbstring` - Multi-byte string support
- `fileinfo` - File type detection
- `zip` - Archive creation and extraction
- `gd` - Image processing and thumbnail generation
- `curl` - HTTP requests (optional)

### File System
- Writable directories for data storage, logs, and file uploads
- Sufficient disk space for file storage and backups

## Installation

### 1. Download and Extract
```bash
# Download the FileServer package
# Extract to your web server directory
```

### 2. Set Permissions
```bash
# Set appropriate permissions for data directories
chmod 755 data/ logs/ storage/
chmod 644 data/*.json
```

### 3. Initialize System
```bash
# Navigate to the FileServer directory in your web browser
# Run the initialization script
http://your-domain.com/path-to-fileserver/init.php
```

### 4. Configure Web Server

#### Apache Configuration
Ensure mod_rewrite is enabled and .htaccess files are processed:
```apache
<VirtualHost *:80>
    DocumentRoot /path/to/fileserver
    ServerName your-domain.com
    
    <Directory /path/to/fileserver>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/fileserver;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \\.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~* \\.(json|log|ini)$ {
        deny all;
    }
}
```

## Configuration

### Initial Setup
1. Access the web interface at your configured URL
2. Log in with default credentials: `admin` / `admin123`
3. Change the default admin password immediately
4. Configure system settings via the admin panel

### System Configuration
The system can be configured through:
- **Web Interface**: Admin panel → Settings
- **Configuration File**: `data/config.json`
- **PHP Configuration**: `includes/config.php`

### Key Configuration Options
- **File Upload Limits**: Maximum file size and allowed extensions
- **User Registration**: Enable/disable user registration
- **File Sharing**: Configure sharing permissions and expiration
- **Security Settings**: Failed login attempts, lockout duration
- **Backup Settings**: Automatic backup scheduling
- **Theme Settings**: Default theme and customization options

## Usage

### For Users
1. **Login**: Access the system with your credentials
2. **Upload Files**: Drag and drop or use the upload button
3. **Browse Files**: Navigate through your files and folders
4. **Share Files**: Create shareable links with permissions
5. **Search**: Use the advanced search to find files quickly
6. **Download**: Download individual files or bulk selections

### For Administrators
1. **User Management**: Create and manage user accounts
2. **System Monitoring**: Monitor system health and performance
3. **File Management**: Manage all files across the system
4. **Security**: Review logs and manage security settings
5. **Backups**: Schedule and manage system backups
6. **Configuration**: Adjust system settings and preferences

## File Structure

```
FileServer/
├── index.php                 # Main entry point
├── login.php                 # User login page
├── register.php              # User registration page
├── dashboard.php             # User dashboard
├── file-browser.php          # File browser interface
├── upload.php                # File upload interface
├── search.php                # Search interface
├── admin.php                 # Admin panel
├── settings.php              # User settings
├── error.php                 # Error page handler
├── init.php                  # System initialization
├── .htaccess                 # Apache configuration
├── api/                      # API endpoints
│   ├── auth.php              # Authentication API
│   ├── files.php             # File operations API
│   ├── users.php             # User management API
│   ├── upload.php            # Upload API
│   ├── share.php             # Sharing API
│   ├── compress.php          # Compression API
│   └── backup.php            # Backup API
├── assets/                   # Static assets
│   ├── css/                  # Stylesheets
│   │   ├── main.css          # Main styles
│   │   ├── admin.css         # Admin panel styles
│   │   ├── forms.css         # Form styles
│   │   ├── file-browser.css  # File browser styles
│   │   ├── mobile.css        # Mobile responsive styles
│   │   └── themes.css        # Theme system styles
│   └── js/                   # JavaScript files
│       ├── main.js           # Core JavaScript framework
│       ├── file-browser.js   # File browser functionality
│       ├── upload.js         # Upload functionality
│       ├── admin.js          # Admin panel functionality
│       ├── search.js         # Search functionality
│       ├── theme-toggle.js   # Theme management
│       └── bulk-operations.js # Bulk operations
├── includes/                 # PHP includes
│   ├── config.php            # Configuration
│   ├── functions.php         # Core functions
│   ├── auth-functions.php    # Authentication functions
│   ├── file-functions.php    # File handling functions
│   ├── user-functions.php    # User management functions
│   ├── json-functions.php    # JSON data functions
│   ├── log-functions.php     # Logging functions
│   ├── security-functions.php # Security functions
│   └── validation-functions.php # Input validation
├── templates/                # HTML templates
│   ├── header.html           # Page header
│   ├── footer.html           # Page footer
│   ├── navigation.html       # Navigation menu
│   ├── login-form.html       # Login form
│   ├── register-form.html    # Registration form
│   ├── upload-form.html      # Upload form
│   ├── search-form.html      # Search form
│   ├── file-list.html        # File listing
│   └── user-list.html        # User listing
├── data/                     # Data storage (JSON files)
│   ├── users.json            # User data
│   ├── files.json            # File metadata
│   ├── shares.json           # Share configurations
│   ├── logs.json             # System logs
│   ├── config.json           # System configuration
│   ├── blocked-ips.json      # Blocked IP addresses
│   ├── backups/              # System backups
│   └── locks/                # File locks
├── storage/                  # File storage
│   ├── uploads/              # User uploaded files
│   ├── compressed/           # Compressed archives
│   ├── quarantine/           # Quarantined files
│   ├── thumbnails/           # Generated thumbnails
│   └── versions/             # File versions
└── logs/                     # System logs
```

## Security Considerations

### File Upload Security
- File type validation based on MIME type and extension
- File size limits to prevent resource exhaustion
- Quarantine system for suspicious files
- Virus scanning integration (optional)

### Access Control
- Session-based authentication with secure cookies
- Role-based access control (RBAC)
- IP-based access restrictions
- Failed login attempt tracking and lockout

### Data Protection
- Files stored outside web root when possible
- Encrypted file storage options
- Secure file sharing with expiration dates
- Regular security audits and updates

## API Documentation

### Authentication
All API endpoints require authentication via session cookies or API tokens.

### File Operations
- `GET /api/files.php` - List files
- `POST /api/files.php` - Create file/folder
- `PUT /api/files.php` - Update file metadata
- `DELETE /api/files.php` - Delete files

### Upload
- `POST /api/upload.php` - Upload files (supports chunked upload)

### User Management
- `GET /api/users.php` - List users (admin only)
- `POST /api/users.php` - Create user (admin only)
- `PUT /api/users.php` - Update user
- `DELETE /api/users.php` - Delete user (admin only)

### Sharing
- `POST /api/share.php` - Create share link
- `GET /api/share.php` - Get share information
- `DELETE /api/share.php` - Remove share

## Troubleshooting

### Common Issues

#### Upload Failures
- Check PHP `upload_max_filesize` and `post_max_size` settings
- Verify directory permissions for storage folders
- Ensure sufficient disk space

#### Performance Issues
- Enable PHP opcache for better performance
- Optimize database queries and file operations
- Consider using a CDN for static assets

#### Security Warnings
- Update PHP to the latest version
- Review and update .htaccess rules
- Monitor failed login attempts

#### Theme Issues
- Clear browser cache after theme changes
- Check CSS file permissions
- Verify theme file syntax

### Debug Mode
Enable debug mode in `includes/config.php` for detailed error information:
```php
$config['debug'] = true;
```

### Log Files
Check system logs for detailed error information:
- `logs/system.log` - General system logs
- `logs/error.log` - Error logs
- `logs/security.log` - Security-related logs

## Contributing

### Development Setup
1. Clone the repository
2. Set up a local web server (Apache/Nginx)
3. Configure PHP with required extensions
4. Run initialization script
5. Start developing

### Code Standards
- Follow PSR-12 coding standards for PHP
- Use meaningful variable and function names
- Add comments for complex logic
- Maintain security best practices

### Testing
- Test all functionality across different browsers
- Verify mobile responsiveness
- Test with different file types and sizes
- Perform security testing

## License

This project is licensed under the MIT License. See the LICENSE file for details.

## Support

For support and questions:
- Check the documentation and troubleshooting section
- Review the issue tracker for known problems
- Contact the development team

## Changelog

### Version 1.0.0
- Initial release with core functionality
- User authentication and management
- File upload/download with chunked support
- File sharing and compression
- Admin panel and system monitoring
- Mobile-responsive design
- Theme system with light/dark modes
- Advanced search and bulk operations
- Comprehensive security features
- Automated backup system

---

**FileServer** - A comprehensive PHP file management system for modern web applications.
