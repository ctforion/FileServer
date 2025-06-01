# Simple File Storage Server

# PHP FileServer

A comprehensive PHP file storage server with JSON-based database, advanced logging, user management, and admin dashboard. No external database required - everything runs on JSON files for easy deployment.

## Features

### 🚀 Core Features
- **File Management**: Upload, download, delete, and organize files
- **JSON Database**: No MySQL/PostgreSQL required - uses JSON files
- **User Management**: Complete user registration, profiles, and role-based access
- **Admin Dashboard**: Comprehensive admin panel for system management
- **Logging System**: Multi-level logging with analysis and monitoring
- **Security**: CSRF protection, rate limiting, input validation, and secure sessions

### 💾 Storage Areas
- **Public Storage**: Files accessible to all authenticated users
- **Private Storage**: User-specific private file storage
- **Temporary Storage**: Auto-cleanup temporary file area

### 🔐 Authentication & Authorization
- **Session-based Authentication**: Secure login system
- **Role-based Access Control**: Admin, user, and guest roles
- **User Profiles**: Profile management with settings and preferences
- **Password Security**: Bcrypt hashing with password complexity requirements

### 📊 Admin Features
- **User Management**: Create, edit, delete, and manage user accounts
- **System Monitoring**: View logs, system status, and performance metrics
- **File Management**: Admin-level file operations across all users
- **Settings Configuration**: System-wide settings management
- **Backup & Maintenance**: Database backup and system maintenance tools

### 🌐 Web Interface
- **Modern UI**: Responsive design with Bootstrap styling
- **Drag & Drop Upload**: Advanced file upload with progress tracking
- **File Browser**: Intuitive file management interface
- **Search & Filter**: Advanced file search and filtering capabilities
- **Real-time Updates**: Dynamic updates without page refresh

## Quick Start

### Prerequisites
- PHP 7.4 or higher
- Web server (Apache/Nginx) with mod_rewrite enabled
- Write permissions for data and storage directories

### Installation

1. **Download/Clone the Project**
   ```bash
   git clone <repository-url> FileServer
   cd FileServer
   ```

2. **Set Directory Permissions**
   ```bash
   chmod 755 data/ storage/ storage/*/ data/logs/
   chmod 644 data/*.json
   ```

3. **Start Web Server**
   
   **Option A: PHP Built-in Server (Development)**
   ```bash
   php -S localhost:8000
   ```
   
   **Option B: Apache/Nginx (Production)**
   - Copy files to web server directory
   - Ensure mod_rewrite is enabled
   - Configure virtual host if needed

4. **Access the Application**
   - Open `http://localhost:8000` (or your domain)
   - You'll be redirected to the login page

### Default Admin Account
- **Username**: `admin`
- **Password**: `admin123`
- **⚠️ Change the default password immediately after first login!**

## Directory Structure

```
FileServer/
├── config.php                     # Main configuration
├── index.php                      # Main web interface
├── integration_test.php           # System integration test
├── .htaccess                      # Security and URL rewriting
│
├── core/                          # Core system classes
│   ├── auth/
│   │   ├── UserManager.php        # User authentication & management
│   │   └── AdminManager.php       # Admin operations
│   ├── database/
│   │   ├── JsonDatabase.php       # JSON database engine
│   │   └── DatabaseManager.php    # Database abstraction layer
│   ├── logging/
│   │   ├── Logger.php             # Multi-level logging system
│   │   └── LogAnalyzer.php        # Log analysis and reporting
│   ├── storage/
│   │   ├── FileManager.php        # File operations & metadata
│   │   └── MetadataManager.php    # File metadata management
│   └── utils/
│       └── SecurityManager.php    # Security utilities & validation
│
├── api/                           # REST API endpoints
│   ├── users.php                  # User management API
│   ├── admin.php                  # Admin operations API
│   ├── upload.php                 # File upload API
│   ├── download.php               # File download API
│   ├── delete.php                 # File deletion API
│   └── list.php                   # File listing API
│
├── web/                           # Web interface files
│   ├── login.php                  # Login page
│   ├── admin.php                  # Admin dashboard
│   ├── profile.php                # User profile management
│   ├── upload.php                 # File upload interface
│   └── assets/                    # CSS, JS, and other assets
│       ├── admin.css              # Admin dashboard styling
│       ├── admin.js               # Admin dashboard functionality
│       ├── profile.css            # Profile page styling
│       ├── profile.js             # Profile page functionality
│       ├── upload.css             # Upload interface styling
│       └── upload.js              # Upload interface functionality
│
├── data/                          # JSON database files
│   ├── users.json                 # User accounts data
│   ├── files.json                 # File metadata
│   ├── sessions.json              # User sessions
│   ├── settings.json              # System settings
│   ├── .htaccess                  # Security protection
│   └── logs/                      # Log files directory
│       ├── access.log             # Access logs
│       ├── error.log              # Error logs
│       ├── security.log           # Security events
│       └── admin.log              # Admin operations
│
└── storage/                       # File storage areas
    ├── public/                    # Public files
    ├── private/                   # Private user files
    └── temp/                      # Temporary files
```

## Usage Guide

### For Users

#### 1. Login and Dashboard
- Access the main interface at `http://localhost:8000`
- Login with your credentials
- View your file dashboard with upload options and file listing

#### 2. File Upload
- **Drag & Drop**: Drag files directly to the upload area
- **Browse Upload**: Click "Choose files" to select files
- **Clipboard Paste**: Paste images directly from clipboard
- **Progress Tracking**: Monitor upload progress in real-time
- **Quota Display**: View your storage quota and usage

#### 3. File Management
- **View Files**: Browse your uploaded files with search and filter
- **Download**: Click any file to download
- **Delete**: Remove files you own
- **Share**: Get public links for files in public storage

#### 4. Profile Management
- **Profile Settings**: Update your profile information
- **Password Change**: Change your password securely
- **Storage Quota**: View your storage usage and limits
- **Upload History**: View your recent uploads

### For Administrators

#### 1. Admin Dashboard
- Access admin panel at `http://localhost:8000/web/admin.php`
- Comprehensive system overview and management tools

#### 2. User Management
- **Create Users**: Add new user accounts
- **Edit Users**: Modify user information and permissions
- **Delete Users**: Remove user accounts (with data cleanup)
- **View Statistics**: User activity and storage usage

#### 3. File Management
- **Global File View**: See all files across all users
- **File Operations**: Move, copy, delete files system-wide
- **Storage Analytics**: Monitor storage usage and trends

#### 4. System Monitoring
- **Live Logs**: View real-time system logs
- **Security Events**: Monitor security-related activities
- **Performance Metrics**: System performance statistics
- **Error Tracking**: View and analyze system errors

#### 5. Maintenance
- **Database Backup**: Create JSON database backups
- **Log Cleanup**: Manage and archive log files
- **System Settings**: Configure system-wide settings
- **User Cleanup**: Remove inactive users and orphaned files

## API Documentation

### Authentication
All API endpoints require authentication via session cookies or API tokens.

```bash
# Login to get session
curl -X POST http://localhost:8000/api/users.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","username":"admin","password":"admin123"}'
```

### User API (`/api/users.php`)

#### Register User
```bash
curl -X POST http://localhost:8000/api/users.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "register",
    "username": "newuser",
    "email": "user@example.com",
    "password": "securepassword"
  }'
```

#### Get Profile
```bash
curl -X POST http://localhost:8000/api/users.php \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=your_session_id" \
  -d '{"action":"profile"}'
```

#### Update Profile
```bash
curl -X POST http://localhost:8000/api/users.php \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=your_session_id" \
  -d '{
    "action": "update_profile",
    "email": "newemail@example.com",
    "display_name": "New Name"
  }'
```

### File API

#### Upload File (`/api/upload.php`)
```bash
curl -X POST http://localhost:8000/api/upload.php \
  -b "PHPSESSID=your_session_id" \
  -F "file=@/path/to/file.jpg" \
  -F "storage_area=public"
```

#### List Files (`/api/list.php`)
```bash
curl -X GET "http://localhost:8000/api/list.php?area=public&search=test" \
  -b "PHPSESSID=your_session_id"
```

#### Download File (`/api/download.php`)
```bash
curl -X GET "http://localhost:8000/api/download.php?file=filename.jpg&area=public" \
  -b "PHPSESSID=your_session_id" \
  -o downloaded_file.jpg
```

#### Delete File (`/api/delete.php`)
```bash
curl -X POST http://localhost:8000/api/delete.php \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=your_session_id" \
  -d '{
    "filename": "file_to_delete.jpg",
    "storage_area": "public"
  }'
```

### Admin API (`/api/admin.php`)

#### Get System Status
```bash
curl -X POST http://localhost:8000/api/admin.php \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=admin_session_id" \
  -d '{"action":"system_status"}'
```

#### Create User (Admin)
```bash
curl -X POST http://localhost:8000/api/admin.php \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=admin_session_id" \
  -d '{
    "action": "create_user",
    "username": "newuser",
    "email": "user@example.com",
    "password": "temppassword",
    "role": "user"
  }'
```

## Configuration

### config.php Settings

```php
<?php
return [
    'database' => [
        'path' => __DIR__ . '/data',
        'backup_path' => __DIR__ . '/data/backups'
    ],
    'paths' => [
        'storage' => __DIR__ . '/storage',
        'logs' => __DIR__ . '/data/logs',
        'temp' => __DIR__ . '/storage/temp'
    ],
    'upload' => [
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'],
        'default_quota' => 100 * 1024 * 1024 // 100MB per user
    ],
    'security' => [
        'session_timeout' => 3600, // 1 hour
        'csrf_protection' => true,
        'rate_limiting' => true,
        'max_login_attempts' => 5
    ],
    'logging' => [
        'level' => 'info', // debug, info, warning, error
        'max_log_size' => 10 * 1024 * 1024, // 10MB
        'log_rotation' => true
    ]
];
```

## Security Features

### Built-in Protections
- **CSRF Protection**: All forms protected with CSRF tokens
- **Rate Limiting**: Prevents brute force attacks
- **Input Validation**: All inputs sanitized and validated
- **File Type Validation**: Only allowed file types accepted
- **Path Traversal Protection**: Prevents directory traversal attacks
- **Session Security**: Secure session handling with timeouts

### Directory Protection
- **Data Directory**: Protected with .htaccess (no direct access)
- **Private Storage**: Only accessible through application
- **Log Files**: Protected from direct web access

### Password Security
- **Bcrypt Hashing**: Strong password hashing
- **Complexity Requirements**: Enforced password complexity
- **Secure Reset**: Secure password reset functionality

## Troubleshooting

### Common Issues

#### 1. Permission Errors
```bash
# Fix directory permissions
chmod 755 data/ storage/ storage/*/
chmod 644 data/*.json
```

#### 2. Upload Failures
- Check PHP upload_max_filesize setting
- Verify file permissions in storage directories
- Check available disk space

#### 3. Database Errors
- Ensure data directory is writable
- Check JSON file syntax if manually edited
- Restore from backup if corrupted

#### 4. Session Issues
- Clear browser cookies and restart
- Check PHP session configuration
- Verify session directory permissions

### Log Analysis
```bash
# View recent access logs
tail -f data/logs/access.log

# Check error logs
tail -f data/logs/error.log

# Monitor security events
tail -f data/logs/security.log
```

### Performance Optimization
- Enable PHP OPcache for better performance
- Use SSD storage for database and file storage
- Configure proper PHP memory limits
- Regular log cleanup and database optimization

## Development

### Running Tests
```bash
# Run integration test
php integration_test.php

# Check syntax of all PHP files
find . -name "*.php" -exec php -l {} \;
```

### Adding Features
1. Create new classes in appropriate core/ subdirectories
2. Add API endpoints in api/ directory
3. Create web interfaces in web/ directory
4. Update this documentation

## Support

For issues, feature requests, or contributions:
1. Check the troubleshooting section above
2. Review log files for error details
3. Create detailed bug reports with:
   - PHP version
   - Web server information
   - Error messages from logs
   - Steps to reproduce

## License

This project is open source. See LICENSE file for details.

---

**Security Note**: Always change the default admin password and review security settings before deploying to production!

### Public Files
- Accessible without authentication
- Direct URL access allowed
- Good for shared content

### Private Files  
- Requires authentication to access
- Protected from direct access
- User-specific files

### Temporary Files
- Auto-cleanup after 24 hours
- Good for temporary uploads
- Processing workspace

## API Usage

### Upload File
```bash
curl -X POST -F "file=@document.pdf" -F "directory=private" http://localhost/FileServer/api/upload.php
```

### List Files
```bash
curl "http://localhost/FileServer/api/list.php?dir=public&page=1&limit=20"
```

### Download File
```bash
curl "http://localhost/FileServer/api/download.php?file=public/document.pdf" -o document.pdf
```

### Delete File (requires authentication)
```bash
curl -X DELETE "http://localhost/FileServer/api/delete.php?file=private/document.pdf"
```

## Configuration

Edit `config.php` to customize:

- **Database Path**: SQLite database location
- **Storage Path**: File storage directory
- **Upload Limits**: Maximum file size and allowed extensions
- **Security Settings**: Session timeout, etc.

## Security Features

- File type validation
- Size limit enforcement  
- Path traversal protection
- Session-based authentication
- .htaccess protection for sensitive directories
- SQL injection prevention
- XSS protection

## Requirements

- PHP 7.4 or higher
- SQLite support (usually included)
- Apache/Nginx with mod_rewrite
- 128MB+ memory limit
- 1GB+ storage space

## Troubleshooting

### Login Issues
- Check if SQLite database is writable
- Verify storage directory permissions
- Default credentials: admin/admin123

### Upload Issues  
- Check file permissions on storage directory
- Verify upload_max_filesize in php.ini
- Check allowed file extensions in config.php

### API Access Issues
- Ensure mod_rewrite is enabled
- Check .htaccess files are being read
- Verify JSON responses in browser developer tools

## Development

To modify or extend the application:

1. **Add new file types**: Update `config.php` allowed extensions
2. **Change styling**: Edit `web/assets/style.css`
3. **Add features**: Extend core classes in `core/` directory
4. **New API endpoints**: Add to `api/` directory and update `api.php`

## License

This project is open source and available under the MIT License.
