# Simple File Storage Server Proposal

## Overview
A minimal PHP file storage server with basic file upload, download, and management capabilities. Designed for quick deployment with minimal setup.

## Core Features
- File upload/download
- Basic authentication
- File listing and management
- Simple web interface
- REST API endpoints

## File Structure
```
FileServer/
├── config.php              # Configuration settings
├── index.php               # Main entry point and web interface
├── api.php                 # API endpoint handler
├── .htaccess               # URL rewriting and security
│
├── core/                   # Core functionality
│   ├── auth/
│   │   └── Authenticator.php    # Simple user authentication
│   ├── storage/
│   │   └── FileManager.php      # File operations (upload/download/delete)
│   └── utils/
│       ├── EnvLoader.php        # Environment and config loader
│       ├── PathResolver.php     # Path handling utilities
│       └── Validator.php        # Input validation
│
├── api/                    # API endpoints
│   ├── upload.php          # File upload endpoint
│   ├── download.php        # File download endpoint
│   ├── delete.php          # File deletion endpoint
│   └── list.php            # File listing endpoint
│
├── web/                    # Web interface
│   ├── index.php           # Main web interface
│   ├── login.php           # Login page
│   └── assets/
│       ├── style.css       # Basic styling
│       └── script.js       # Basic JavaScript functionality
│
└── storage/                # File storage directories
    ├── .htaccess           # Deny direct access
    ├── public/             # Public files (direct access allowed)
    │   └── .htaccess       # Allow public access
    ├── private/            # Private files (auth required)
    │   └── .htaccess       # Deny direct access
    └── temp/               # Temporary files (auto-cleanup)
        └── .htaccess       # Deny direct access
```

## Key Components

### 1. config.php
Central configuration file containing:
- Database settings (SQLite for simplicity)
- Storage paths
- Upload limits
- Security settings

### 2. Core Classes

#### Authenticator.php
- Simple session-based authentication
- Basic user management
- Login/logout functionality

#### FileManager.php
- File upload handling
- File download streaming
- File deletion with safety checks
- Directory management

#### EnvLoader.php
- Configuration loading
- Environment variable handling
- Path resolution

### 3. API Endpoints

#### upload.php
- Handles file uploads
- Validates file types and sizes
- Returns JSON response with file info

#### download.php
- Streams files for download
- Checks permissions
- Handles large files efficiently

#### list.php
- Lists files in directories
- Supports pagination
- Returns JSON file metadata

#### delete.php
- Safely deletes files
- Checks permissions
- Logs deletion activities

### 4. Web Interface

#### index.php
- File browser interface
- Drag-and-drop upload
- Basic file management UI

#### login.php
- Simple login form
- Session management
- Redirect after authentication

### 5. Storage Structure

#### public/
- Files accessible without authentication
- Direct URL access allowed
- Suitable for shared content

#### private/
- User-specific files
- Authentication required
- Protected from direct access

#### temp/
- Temporary uploads
- Auto-cleanup after 24 hours
- Processing workspace

## Security Features
- File type validation
- Size limit enforcement
- Path traversal protection
- Session-based authentication
- .htaccess protection for sensitive directories

## Deployment Steps
1. Copy files to web server
2. Set directory permissions (755 for dirs, 644 for files)
3. Configure database in config.php
4. Create storage directories
5. Access via web browser

## API Usage Examples

### Upload File
```bash
curl -X POST -F "file=@document.pdf" http://localhost/FileServer/api/upload.php
```

### List Files
```bash
curl http://localhost/FileServer/api/list.php?dir=public
```

### Download File
```bash
curl http://localhost/FileServer/api/download.php?file=public/document.pdf
```

### Delete File
```bash
curl -X DELETE http://localhost/FileServer/api/delete.php?file=public/document.pdf
```

## Technical Requirements
- PHP 7.4+
- SQLite or MySQL
- Apache/Nginx with mod_rewrite
- 128MB+ memory limit
- 1GB+ storage space

## Benefits
- **Simplicity**: Minimal files and dependencies
- **Portability**: Easy to move between servers
- **Security**: Basic but effective protection
- **Extensibility**: Easy to add features
- **Performance**: Lightweight and fast

This simplified approach provides essential file storage functionality while maintaining ease of use and deployment.