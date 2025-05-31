# 📁 Portable PHP File Storage Server

## 🎯 Overview

A **zero-dependency**, **portable** PHP file storage server that can be deployed anywhere with just PHP. No composer, no complex setup - just copy, configure, and run.

### Key Features
- 🚀 **Instant Setup**: Single `config.php` file configuration
- 📦 **Zero Dependencies**: Pure PHP, no external libraries required
- 🔄 **Auto-Update**: Built-in GitHub sync system
- 🏠 **Portable**: Move between servers without breaking
- 🔒 **Secure**: Built-in authentication and file protection
- 🎨 **Modern UI**: Clean, responsive web interface

### File Type Categories
- **Public**: Openly accessible files
- **Private**: User-specific files
- **Temporary**: Auto-delete after time limit
- **Shared**: Time-limited sharing links

---

## 📂 Project Structure

```
FileServer/
├── install.sh              # Auto-deployment script
├── config.php              # Single configuration file
├── RULES.md                # Simple setup rules
├── source/                 # Core application files
│   ├── api/                # REST API endpoints
│   │   ├── auth/           # Authentication
│   │   ├── files/          # File operations
│   │   └── admin/          # Admin functions
│   ├── core/               # Business logic
│   │   ├── auth/           # Authentication system
│   │   ├── storage/        # File management
│   │   ├── database/       # Database layer
│   │   └── utils/          # Utilities
│   ├── web/                # Web interface
│   │   ├── assets/         # CSS, JS, images
│   │   ├── templates/      # HTML templates
│   │   └── public/         # Entry point
│   ├── storage/            # File storage
│   │   ├── public/         # Public files
│   │   ├── private/        # Private files
│   │   ├── temp/           # Temporary files
│   │   └── shared/         # Shared files
│   └── logs/               # Application logs
└── updates/                # Update management
    ├── sync.php            # GitHub sync
    ├── backup.php          # Backup system
    └── version.txt         # Current version
```

---

## ⚡ Quick Start

### 1. Auto-Deploy with install.sh
```bash
# Download and run installer
curl -o install.sh https://raw.githubusercontent.com/user/repo/main/install.sh
chmod +x install.sh
./install.sh /path/to/your/webroot
```

### 2. Manual Setup
```bash
# Clone repository to temp location
git clone https://github.com/user/FileServer.git temp_fileserver

# Copy to target directory
cp -r temp_fileserver/source/* /your/web/directory/
cp temp_fileserver/config.php /your/web/directory/
chmod 755 /your/web/directory/storage
chmod 755 /your/web/directory/logs

# Clean up
rm -rf temp_fileserver
```

### 3. Configure
Edit `config.php` with your settings:
```php
<?php
return [
    'database' => [
        'host' => 'localhost',
        'name' => 'fileserver',
        'user' => 'your_user',
        'pass' => 'your_password'
    ],
    'admin' => [
        'username' => 'admin',
        'password' => 'your_admin_password'
    ],
    'storage' => [
        'max_file_size' => '100MB',
        'allowed_types' => 'jpg,png,pdf,txt,zip'
    ]
];
```

### 4. Initialize Database
Visit: `http://yoursite.com/install.php`

---

## 🗃️ Database Schema Design

### `users` Table
| Column       | Type            | Constraints                    | Description                      |
|--------------|-----------------|--------------------------------|----------------------------------|
| id           | INT UNSIGNED    | PRIMARY KEY, AUTO_INCREMENT    | Unique user identifier           |
| username     | VARCHAR(50)     | UNIQUE, NOT NULL               | User login name                  |
| email        | VARCHAR(255)    | UNIQUE, NOT NULL               | User email address               |
| password     | VARCHAR(255)    | NOT NULL                       | BCrypt hashed password           |
| role         | ENUM            | 'user', 'admin', DEFAULT 'user'| User permission level            |
| storage_used | BIGINT UNSIGNED | DEFAULT 0                      | Current storage usage in bytes   |
| storage_limit| BIGINT UNSIGNED | DEFAULT 1073741824             | Storage limit (1GB default)      |
| is_active    | BOOLEAN         | DEFAULT TRUE                   | Account status                   |
| created_at   | TIMESTAMP       | DEFAULT CURRENT_TIMESTAMP      | Account creation time            |
| updated_at   | TIMESTAMP       | ON UPDATE CURRENT_TIMESTAMP    | Last account update              |
| last_login   | TIMESTAMP       | NULL                           | Last login timestamp             |

**Indexes:**
- `idx_username` ON (username)
- `idx_email` ON (email)
- `idx_role` ON (role)

---

### `files` Table
| Column        | Type            | Constraints                    | Description                      |
|---------------|-----------------|--------------------------------|----------------------------------|
| id            | INT UNSIGNED    | PRIMARY KEY, AUTO_INCREMENT    | Unique file identifier           |
| user_id       | INT UNSIGNED    | FOREIGN KEY REFERENCES users(id)| File owner                      |
| filename      | VARCHAR(255)    | NOT NULL                       | Original filename                |
| stored_name   | VARCHAR(255)    | UNIQUE, NOT NULL               | System-generated filename        |
| file_type     | ENUM            | 'static','dynamic','one-time','temp','persistent'| File category    |
| size          | BIGINT UNSIGNED | NOT NULL                       | File size in bytes               |
| mime_type     | VARCHAR(100)    | NOT NULL                       | MIME content type                |
| file_hash     | VARCHAR(64)     | NOT NULL                       | SHA-256 hash for integrity      |
| storage_path  | VARCHAR(500)    | NOT NULL                       | Relative storage path            |
| description   | TEXT            | NULL                           | Optional file description        |
| download_count| INT UNSIGNED    | DEFAULT 0                      | Number of downloads              |
| max_downloads | INT UNSIGNED    | NULL                           | Download limit (NULL = unlimited)|
| created_at    | TIMESTAMP       | DEFAULT CURRENT_TIMESTAMP      | Upload timestamp                 |
| updated_at    | TIMESTAMP       | ON UPDATE CURRENT_TIMESTAMP    | Last modification time           |
| expires_at    | TIMESTAMP       | NULL                           | Expiration time (NULL = never)   |
| is_public     | BOOLEAN         | DEFAULT FALSE                  | Public access flag               |
| is_deleted    | BOOLEAN         | DEFAULT FALSE                  | Soft delete flag                 |
| deleted_at    | TIMESTAMP       | NULL                           | Deletion timestamp               |

**Indexes:**
- `idx_user_files` ON (user_id, is_deleted)
- `idx_stored_name` ON (stored_name)
- `idx_file_type` ON (file_type)
- `idx_expires_at` ON (expires_at)
- `idx_file_hash` ON (file_hash)

---

### `user_sessions` Table
| Column        | Type            | Constraints                    | Description                      |
|---------------|-----------------|--------------------------------|----------------------------------|
| id            | VARCHAR(128)    | PRIMARY KEY                    | Session identifier               |
| user_id       | INT UNSIGNED    | FOREIGN KEY REFERENCES users(id)| Session owner                   |
| ip_address    | VARCHAR(45)     | NOT NULL                       | Client IP address                |
| user_agent    | TEXT            | NULL                           | Client user agent string        |
| created_at    | TIMESTAMP       | DEFAULT CURRENT_TIMESTAMP      | Session creation time            |
| expires_at    | TIMESTAMP       | NOT NULL                       | Session expiration time          |
| is_active     | BOOLEAN         | DEFAULT TRUE                   | Session status                   |

**Indexes:**
- `idx_user_sessions` ON (user_id, is_active)
- `idx_expires_at` ON (expires_at)

---

### `file_shares` Table
| Column        | Type            | Constraints                    | Description                      |
|---------------|-----------------|--------------------------------|----------------------------------|
| id            | INT UNSIGNED    | PRIMARY KEY, AUTO_INCREMENT    | Share identifier                 |
| file_id       | INT UNSIGNED    | FOREIGN KEY REFERENCES files(id)| Shared file                     |
| share_token   | VARCHAR(64)     | UNIQUE, NOT NULL               | Public access token              |
| created_by    | INT UNSIGNED    | FOREIGN KEY REFERENCES users(id)| Share creator                   |
| access_count  | INT UNSIGNED    | DEFAULT 0                      | Number of accesses               |
| max_accesses  | INT UNSIGNED    | NULL                           | Access limit (NULL = unlimited)  |
| password      | VARCHAR(255)    | NULL                           | Optional password protection     |
| created_at    | TIMESTAMP       | DEFAULT CURRENT_TIMESTAMP      | Share creation time              |
| expires_at    | TIMESTAMP       | NULL                           | Share expiration time            |
| is_active     | BOOLEAN         | DEFAULT TRUE                   | Share status                     |

**Indexes:**
- `idx_share_token` ON (share_token)
- `idx_file_shares` ON (file_id, is_active)

---

### `activity_logs` Table
| Column        | Type            | Constraints                    | Description                      |
|---------------|-----------------|--------------------------------|----------------------------------|
| id            | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT    | Log entry identifier             |
| user_id       | INT UNSIGNED    | FOREIGN KEY REFERENCES users(id)| User performing action          |
| file_id       | INT UNSIGNED    | NULL, FOREIGN KEY files(id)    | Related file (if applicable)     |
| action        | VARCHAR(50)     | NOT NULL                       | Action performed                 |
| details       | JSON            | NULL                           | Additional action details        |
| ip_address    | VARCHAR(45)     | NOT NULL                       | Client IP address                |
| user_agent    | TEXT            | NULL                           | Client user agent                |
| created_at    | TIMESTAMP       | DEFAULT CURRENT_TIMESTAMP      | Action timestamp                 |

**Indexes:**
- `idx_user_logs` ON (user_id, created_at)
- `idx_action_logs` ON (action, created_at)
- `idx_file_logs` ON (file_id, created_at)

---

### `system_settings` Table
| Column        | Type            | Constraints                    | Description                      |
|---------------|-----------------|--------------------------------|----------------------------------|
| setting_key   | VARCHAR(100)    | PRIMARY KEY                    | Configuration key                |
| setting_value | TEXT            | NOT NULL                       | Configuration value              |
| data_type     | ENUM            | 'string','integer','boolean','json'| Value data type             |
| description   | TEXT            | NULL                           | Setting description              |
| is_editable   | BOOLEAN         | DEFAULT TRUE                   | Can be modified via UI           |
| updated_at    | TIMESTAMP       | ON UPDATE CURRENT_TIMESTAMP    | Last modification time           |

---

## 🔐 Security & Authentication

### Authentication System
- **Password Security**: BCrypt hashing with configurable cost factor
- **Session Management**: Secure session handling with regeneration and timeout
- **CSRF Protection**: Token-based protection for all forms and state-changing operations
- **Rate Limiting**: Configurable limits on login attempts, API calls, and file operations
- **Two-Factor Authentication**: Optional TOTP support for enhanced security

### Access Control
- **Role-Based Permissions**: Admin and user roles with granular permissions
- **File Ownership**: Users can only access their own files unless explicitly shared
- **Public Sharing**: Optional public file sharing with time-limited tokens
- **API Authentication**: Token-based authentication for programmatic access

### File Security
- **Upload Validation**: MIME type checking, file size limits, and extension filtering
- **Virus Scanning**: Integration hooks for antivirus scanning
- **Secure Storage**: Files stored outside web root with access-controlled downloads
- **File Integrity**: SHA-256 checksums for corruption detection

---

## 🏗️ System Architecture

### Request Flow
1. **Client Request** → Web server (Apache/Nginx)
2. **Routing** → PHP application entry point
3. **Middleware** → Authentication, rate limiting, CORS
4. **Controller** → Business logic processing
5. **Service Layer** → File operations, database interactions
6. **Response** → JSON API or HTML template

### Storage Architecture
- **Hierarchical Storage**: Files organized by type and date
- **Deduplication**: Hash-based duplicate file detection
- **Backup Integration**: Hooks for automated backup systems
- **CDN Ready**: Support for CDN integration for static files

### Scalability Considerations
- **Database Optimization**: Proper indexing and query optimization
- **Caching**: Redis/Memcached support for session and metadata caching
- **Load Balancing**: Session affinity support for multiple servers
- **Microservice Ready**: Modular design for easy service extraction

---

## 💡 System Workflow

### User Registration & Authentication
1. **Registration** → User submits credentials → Password hashed → Account created
2. **Login** → Credentials validated → Session created → Dashboard access
3. **Session Management** → Automatic renewal → Secure logout → Session cleanup

### File Upload Process
1. **Upload Initiation** → User selects file → Client-side validation
2. **Server Processing** → File validation → Virus scan → Storage path determination
3. **Storage** → File saved with UUID name → Database record created → Cleanup scheduled
4. **Response** → Upload confirmation → File metadata returned

### File Download Process
1. **Access Request** → User requests file → Authentication check
2. **Authorization** → Ownership validation → Permission check → Token generation
3. **Download** → Secure file serving → Access logging → Download count increment
4. **Cleanup** → One-time file deletion → Statistics update

### Automated Cleanup
1. **Scheduled Tasks** → CRON triggers cleanup scripts
2. **File Scanning** → Expired files identified → Deletion candidates listed
3. **Cleanup Execution** → Files deleted → Database updated → Storage reclaimed
4. **Reporting** → Cleanup statistics → Error notifications

---

## 🖥️ Web Interface

### User Dashboard
- **File Browser**: Grid and list views with sorting and filtering
- **Upload Interface**: Drag-and-drop with progress tracking and batch uploads
- **File Management**: Preview, download, share, and delete operations
- **Search Functionality**: Full-text search across filenames and metadata
- **Storage Analytics**: Usage statistics and storage quota visualization

### Admin Panel
- **User Management**: User creation, role assignment, and account management
- **System Monitoring**: Storage usage, performance metrics, and error tracking
- **File Administration**: Global file management and cleanup tools
- **Configuration**: System settings and policy management
- **Reports**: Usage reports, audit logs, and security notifications

### Template System
- **Responsive Design**: Mobile-friendly interface with Bootstrap framework
- **Component-Based**: Reusable template components for consistency
- **Theming Support**: Customizable themes and branding
- **Accessibility**: WCAG compliance for inclusive design

---

## 🧹 Maintenance & Automation

### Automated Cleanup System
The system includes comprehensive automated maintenance capabilities:

#### CRON Configuration
```bash
# File cleanup (every hour)
0 * * * * /usr/bin/php /path/to/cron/cleanup.php

# Generate statistics (daily at midnight)
0 0 * * * /usr/bin/php /path/to/cron/statistics.php

# Database backup (daily at 2 AM)
0 2 * * * /usr/bin/php /path/to/cron/backup.php

# Session cleanup (every 30 minutes)
*/30 * * * * /usr/bin/php /path/to/cron/sessions.php
```

#### Cleanup Operations
- **Expired Files**: Automatic deletion of files past their expiration date
- **One-time Files**: Removal after successful download
- **Orphaned Files**: Cleanup of files without database records
- **Temporary Uploads**: Cleanup of incomplete or failed uploads
- **Session Management**: Removal of expired user sessions
- **Log Rotation**: Automated log file rotation and archival

---

## 🛠️ Installation & Setup Guide

### System Requirements
- **PHP**: 8.1 or higher with extensions: mysqli, gd, fileinfo, json, openssl
- **Database**: MySQL 8.0+ or MariaDB 10.5+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Storage**: Minimum 1GB free space for application and initial storage
- **Memory**: 512MB RAM minimum, 2GB recommended

### Installation Steps

#### 1. Server Preparation
```bash
# Clone repository
git clone https://github.com/your-org/php-storage-service.git
cd php-storage-service

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set permissions
chmod -R 755 storage/
chmod -R 755 logs/
chmod 600 config/.env
```

#### 2. Database Setup
```sql
-- Create database
CREATE DATABASE file_storage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'storage_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON file_storage.* TO 'storage_user'@'localhost';
FLUSH PRIVILEGES;

-- Import schema
mysql -u storage_user -p file_storage < database/schema.sql
```

#### 3. Environment Configuration
```bash
# Copy environment template
cp config/.env.example config/.env

# Edit configuration
nano config/.env
```

#### 4. Web Server Configuration

**Apache (.htaccess)**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>
```

**Nginx Configuration**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/php-storage-service/web/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location /storage/ {
        deny all;
        return 404;
    }
}
```

#### 5. Initial Configuration
```bash
# Run installation script
php cli/install.php

# Create admin user
php cli/create-admin.php

# Set up CRON jobs
crontab -e
# Add the cron configuration from above
```

### Security Hardening
- **File Permissions**: Ensure storage directories are not web-accessible
- **Database Security**: Use strong passwords and limit database user privileges
- **SSL/TLS**: Configure HTTPS for all communications
- **Firewall**: Restrict access to database and admin ports
- **Updates**: Keep PHP, web server, and dependencies updated

---

## 📊 API Documentation

### Authentication Endpoints
```
POST /api/v1/auth/login
POST /api/v1/auth/register
POST /api/v1/auth/logout
POST /api/v1/auth/refresh
```

### File Management Endpoints
```
GET    /api/v1/files              # List files
POST   /api/v1/files              # Upload file
GET    /api/v1/files/{id}         # Get file metadata
PUT    /api/v1/files/{id}         # Update file metadata
DELETE /api/v1/files/{id}         # Delete file
GET    /api/v1/files/{id}/download # Download file
POST   /api/v1/files/{id}/share   # Create share link
```

### Admin Endpoints
```
GET    /api/v1/admin/users        # List users
POST   /api/v1/admin/users        # Create user
PUT    /api/v1/admin/users/{id}   # Update user
DELETE /api/v1/admin/users/{id}   # Delete user
GET    /api/v1/admin/stats        # System statistics
POST   /api/v1/admin/cleanup      # Trigger cleanup
```

### Response Format
```json
{
    "success": true,
    "data": {},
    "message": "Operation successful",
    "timestamp": "2025-05-30T10:30:00Z",
    "request_id": "req_12345"
}
```

---

## 🚀 Performance & Optimization

### Caching Strategy
- **File Metadata Caching**: Redis/Memcached for frequently accessed file information
- **Session Storage**: Database or Redis-based session storage for scalability
- **Static Asset Caching**: Browser caching headers and CDN integration
- **Query Optimization**: Database query caching and connection pooling

### Monitoring & Metrics
- **Performance Monitoring**: Response time tracking and bottleneck identification
- **Storage Analytics**: Usage patterns, popular files, and capacity planning
- **Error Tracking**: Comprehensive error logging and alerting system
- **Health Checks**: Automated system health monitoring and reporting

---

## 🧱 Future Enhancements & Roadmap

### Phase 1: Core Enhancements
- **File Versioning**: Complete version control with diff tracking
- **Bulk Operations**: Multi-file upload, download, and management
- **Advanced Search**: Full-text search with metadata indexing
- **Mobile App**: Native iOS and Android applications

### Phase 2: Enterprise Features
- **Single Sign-On (SSO)**: SAML/OAuth integration
- **Advanced Analytics**: Detailed usage reports and insights
- **Webhook Integration**: Real-time event notifications
- **API Rate Limiting**: Sophisticated throttling and quota management

### Phase 3: Advanced Storage
- **Cloud Storage Integration**: AWS S3, Google Cloud, Azure Blob support
- **Content Delivery Network**: Automatic CDN distribution
- **Image Processing**: Thumbnail generation and format conversion
- **Video Processing**: Transcoding and streaming capabilities

### Phase 4: Collaboration
- **Real-time Collaboration**: Shared workspaces and team management
- **Comment System**: File annotation and discussion threads
- **Approval Workflows**: Document review and approval processes
- **Integration APIs**: Third-party application integrations

---

## 🔧 Technical Specifications

### Development Standards
- **Coding Standards**: PSR-12 compliant PHP code
- **Documentation**: PHPDoc comments and API documentation
- **Testing**: Unit tests with PHPUnit, integration testing
- **Version Control**: Git workflow with feature branches

### Deployment Options
- **Docker Support**: Containerized deployment with Docker Compose
- **Cloud Deployment**: AWS, Google Cloud, Azure deployment guides
- **Load Balancing**: Multi-server deployment configuration
- **Database Replication**: Master-slave database setup

### Integration Capabilities
- **Webhook Support**: Event-driven integrations
- **REST API**: Complete RESTful API for all operations
- **SDK Development**: Client libraries for popular languages
- **Plugin Architecture**: Extensible plugin system

---

## 📋 Compliance & Legal

### Data Protection
- **GDPR Compliance**: User data management and right to deletion
- **Data Encryption**: At-rest and in-transit encryption
- **Audit Trails**: Comprehensive activity logging
- **Data Retention**: Configurable retention policies

### Security Standards
- **OWASP Compliance**: Following OWASP Top 10 security guidelines
- **Security Auditing**: Regular security assessments and penetration testing
- **Vulnerability Management**: Automated dependency scanning
- **Incident Response**: Security incident handling procedures

---

## 📚 Documentation Structure

### User Documentation
- **User Guide**: Comprehensive end-user manual
- **Admin Guide**: System administration documentation
- **API Reference**: Complete API documentation with examples
- **Troubleshooting**: Common issues and solutions

### Developer Documentation
- **Architecture Guide**: System design and component documentation
- **Development Setup**: Local development environment setup
- **Contributing Guide**: Code contribution guidelines
- **Plugin Development**: Custom plugin creation guide

---

## 🎯 Success Metrics

### Performance Targets
- **Response Time**: < 200ms for API calls, < 2s for file uploads
- **Uptime**: 99.9% availability target
- **Scalability**: Support for 10,000+ concurrent users
- **Storage Efficiency**: 95% storage utilization efficiency

### User Experience Goals
- **User Adoption**: 90% user retention after first month
- **Support Tickets**: < 5% of users requiring support
- **User Satisfaction**: 4.5+ star rating in user feedback
- **Feature Usage**: 80% of users using core features

---

## 📄 License & Support

### License Information
This project is released under the **MIT License**, allowing for:
- Commercial and personal use
- Modification and distribution
- Private use and patent use
- Limited liability and warranty

### Support Options
- **Community Support**: GitHub issues and discussions
- **Documentation**: Comprehensive online documentation
- **Professional Support**: Available for enterprise deployments
- **Custom Development**: Tailored solutions and feature development

### Contributing
We welcome contributions from the community:
- **Bug Reports**: Issue tracking and bug fixes
- **Feature Requests**: New feature suggestions and discussions
- **Code Contributions**: Pull requests with improvements
- **Documentation**: Help improve project documentation

---

**Project Repository**: [GitHub - PHP Storage Service](https://github.com/ctforion/FileServer)  

---

## 🤖 CI/CD Integration: Simple Cron-Based Deployment

### Automated Deployment Script

To enable basic CI/CD for integrating new changes, create a `cronejob.sh` script in your project root:

```bash
#!/bin/bash

# cronejob.sh - Simple CI/CD deployment script

REPO_DIR="/path/to/php-storage-service"
BRANCH="main"
LOG_FILE="$REPO_DIR/logs/deploy.log"

cd "$REPO_DIR" || exit 1

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting deployment..." >> "$LOG_FILE"

# Fetch and reset to latest changes
git fetch origin "$BRANCH" >> "$LOG_FILE" 2>&1
git reset --hard "origin/$BRANCH" >> "$LOG_FILE" 2>&1

# Install/update dependencies
composer install --no-dev --optimize-autoloader >> "$LOG_FILE" 2>&1

# Run database migrations (optional)
php cli/migrate.php >> "$LOG_FILE" 2>&1

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deployment finished." >> "$LOG_FILE"
```

Make it executable:

```bash
chmod +x cronejob.sh
```

### Cron Configuration

Add this to your crontab (e.g., every 10 minutes):

```bash
*/10 * * * * /path/to/php-storage-service/cronejob.sh
```

This will automatically pull new changes, update dependencies, and run migrations on schedule.
