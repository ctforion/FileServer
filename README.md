# Simple File Storage Server

A minimal PHP file storage server with basic file upload, download, and management capabilities. Designed for quick deployment with minimal setup.

## Features

- ✅ File upload/download
- ✅ Basic session-based authentication
- ✅ File listing and management
- ✅ Simple web interface with drag-and-drop
- ✅ REST API endpoints
- ✅ Three storage areas: public, private, temp
- ✅ Security protection with .htaccess
- ✅ Modern responsive UI

## Quick Start

1. **Deploy to Web Server**
   - Copy all files to your web server directory
   - Ensure Apache/Nginx has mod_rewrite enabled
   - Set directory permissions (755 for directories, 644 for files)

2. **Access the Application**
   - Open `http://your-domain/FileServer/` in your browser
   - You'll be redirected to the login page

3. **Default Login**
   - Username: `admin`
   - Password: `admin123`

## Directory Structure

```
FileServer/
├── config.php              # Configuration settings
├── index.php               # Main web interface
├── api.php                 # API endpoint router
├── .htaccess               # Security and URL rewriting
│
├── core/                   # Core functionality
│   ├── auth/Authenticator.php      # User authentication
│   ├── storage/FileManager.php     # File operations
│   └── utils/                      # Utility classes
│
├── api/                    # API endpoints
│   ├── upload.php          # File upload
│   ├── download.php        # File download  
│   ├── delete.php          # File deletion
│   └── list.php            # File listing
│
├── web/                    # Web interface
│   ├── login.php           # Login page
│   └── assets/             # CSS and JS files
│
└── storage/                # File storage (auto-created)
    ├── public/             # Public files (direct access)
    ├── private/            # Private files (auth required)
    └── temp/               # Temporary files (auto-cleanup)
```

## Storage Areas

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
