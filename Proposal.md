# Advanced File Storage Server Proposal

## Overview
A comprehensive PHP file storage server with advanced user management, admin controls, and comprehensive logging. Features a JSON-based database system for zero-configuration deployment.

## Core Features

### GUI Features
- **Simple Authentication**
  - Basic login form for admin and user access
  - Plain text password storage (no hashing)
  - Session handling with basic PHP sessions
  - Simple role checking (admin/user)

- **User Management**
  - Add/remove user accounts with simple forms
  - Set user permissions (read/write/admin)
  - View user activity in simple lists

- **File Management Interface**
  - Browse files and folders in simple tables
  - Upload files with basic HTML forms
  - Download and delete files with simple links
  - View file details (size, date, owner)

- **Search & Filtering**
  - Search files by name using simple text input
  - Filter by basic criteria
  - Sort with simple dropdown menus

### Backend Features
- **File Operations**
  - Upload files with basic validation functions
  - Download files with simple access checks
  - Delete files with basic permission functions
  - Move and rename files using built-in PHP functions

- **User System**
  - Simple user registration with basic forms
  - Plain text password storage in JSON
  - Role-based file access using simple functions
  - Basic session management with PHP sessions

- **Data Storage**
  - JSON files for user data and file metadata
  - Simple file organization by folders
  - Basic logging with simple file writes
  - Configuration through simple PHP arrays

- **Admin Tools**
  - View all users with simple HTML tables
  - Basic system statistics using file functions
  - Clean up files with simple PHP scripts
  - Basic backup using file copy functions

- **Additional Features**
  - File sharing with simple temporary tokens
  - Basic file copying for versions
  - Simple email sending for notifications
  - Basic API using simple PHP functions
  - Mobile-friendly with simple CSS
  - Theme toggle with basic JavaScript

## Coding Rules
- no oop if function based coding possible
- all html will be inside `.html` files
- all php visitable file will contain no api
- all api will be in /api
- all css will be inside `.css` files in assets folder
- all javascript will be inside `.js` files in assets folder
- use simple php include() for html files
- keep functions small and focused on one task
- use simple if/else instead of complex logic
- store all data in json files, no database needed
- use simple arrays for data manipulation
- avoid complex loops, use simple foreach when needed
- keep variable names simple and descriptive
- use basic error handling with simple checks
- all forms use POST method for security
- file uploads go directly to storage folders
- use simple `file_get_contents()` and `file_put_contents()` for json
- keep all paths relative to project root
- use simple `header()` redirects for navigation
- use `include_once` to avoied errors
- use file extension to get file type
- use `.htaccess` to avoied file based virus
- give compression / no compression choices on gui and api both
- give metadata removing / no removing choices on gui and api both

## Security Features
- Simple file type validation using extension checks
- Basic file size limits with simple PHP checks
- Directory traversal protection with simple path validation
- Simple CSRF protection using basic tokens
- File quarantine system with simple move operations
- Basic IP blocking with simple file-based storage
- Admin file locking system to prevent database corruption during operations
- JSON database backup before critical operations with simple file copy
- Admin activity logging with rollback capability using JSON snapshots
- File operation locking using simple flag files to prevent concurrent access
- Transaction-like operations with temporary JSON files for safe updates
- Admin rollback interface with simple restore from backup functionality

## Advanced File Operations
- Bulk file operations with simple checkbox forms
- File compression/decompression with basic ZIP functions
- Image thumbnail generation using simple GD functions
- Text file preview with basic file reading
- File versioning with simple copy and rename
- Duplicate file detection using simple hash comparison

## System Monitoring
- Disk usage tracking with simple `disk_free_space()` calls
- Upload/download statistics in JSON files
- User activity logs with simple timestamp entries
- System health checks with basic file system tests
- Error logging with simple file append operations to dedicated log files
- Performance metrics using basic time measurements
- Individual file access monitoring with request tracking per file
- Request logging system storing each file interaction with timestamps

## Project File Structure

```
FileServer/
├── .htaccess
├── index.php
├── login.php
├── register.php
├── dashboard.php
├── admin.php
├── logout.php
├── file-browser.php
├── upload.php
├── search.php
├── user-management.php
├── settings.php
├── backup.php
├── system-monitor.php
├── api/
│   ├── auth.php
│   ├── files.php
│   ├── users.php
│   ├── upload.php
│   ├── download.php
│   ├── delete.php
│   ├── compress.php
│   ├── share.php
│   ├── backup.php
│   ├── admin.php
│   └── monitor.php
├── includes/
│   ├── config.php
│   ├── functions.php
│   ├── auth-functions.php
│   ├── file-functions.php
│   ├── user-functions.php
│   ├── security-functions.php
│   ├── json-functions.php
│   ├── log-functions.php
│   └── validation-functions.php
├── templates/
│   ├── header.html
│   ├── footer.html
│   ├── navigation.html
│   ├── login-form.html
│   ├── register-form.html
│   ├── upload-form.html
│   ├── file-list.html
│   ├── user-list.html
│   ├── search-form.html
│   ├── admin-panel.html
│   ├── settings-form.html
│   ├── backup-interface.html
│   └── monitor-dashboard.html
├── assets/
│   ├── css/
│   │   ├── main.css
│   │   ├── admin.css
│   │   ├── forms.css
│   │   ├── file-browser.css
│   │   ├── mobile.css
│   │   └── themes.css
│   └── js/
│       ├── main.js
│       ├── file-browser.js
│       ├── upload.js
│       ├── admin.js
│       ├── search.js
│       ├── theme-toggle.js
│       └── bulk-operations.js
├── data/
│   ├── users.json
│   ├── files.json
│   ├── logs.json
│   ├── config.json
│   ├── blocked-ips.json
│   ├── shares.json
│   ├── backups/
│   │   ├── users-backup.json
│   │   ├── files-backup.json
│   │   └── logs-backup.json
│   └── locks/
│       ├── user-operations.lock
│       ├── file-operations.lock
│       └── backup-operations.lock
├── storage/
│   ├── uploads/
│   ├── quarantine/
│   ├── compressed/
│   ├── thumbnails/
│   └── versions/
└── logs/
    ├── access.log
    ├── error.log
    ├── admin.log
    ├── file-operations.log
    └── security.log
```
