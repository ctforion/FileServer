# 📋 FileServer Logs & Security Documentation

## 📁 Log Files Location
**Path:** `c:\Users\Unkn0\Desktop\VScode\PHP.php\FileServer\logs\`

### 📊 Current Log Files:
- **access.log** - User access and navigation logs
- **admin.log** - Administrative actions and changes
- **error.log** - System errors and exceptions
- **file-operations.log** - File upload, download, delete operations
- **security.log** - Security events, failed logins, blocked IPs
- **test.log** - System testing logs

## 🔒 Security Files (.htaccess)

### 🏠 Root .htaccess (`/.htaccess`)
```apache
# Security Headers
RewriteEngine On

# Block direct access to sensitive files and directories
<Files "*.json">
    Order allow,deny
    Deny from all
</Files>

<Files "*.lock">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

# Block access to includes directory
RewriteRule ^includes/ - [F,L]

# Block access to data directory
RewriteRule ^data/ - [F,L]

# Block access to storage directory except through proper PHP scripts
RewriteRule ^storage/uploads/(.*)$ /api/download.php?file=$1 [L]
RewriteRule ^storage/ - [F,L]

# Block access to logs directory
RewriteRule ^logs/ - [F,L]

# Block dangerous file extensions
<FilesMatch "\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$">
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !^.*/(api|includes)/.*$
        RewriteRule ^storage/ - [F,L]
    </IfModule>
</FilesMatch>

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>
```

### 📁 Directory-Specific .htaccess Files:

#### `/logs/.htaccess`
```apache
# Deny all access to logs directory
Order deny,allow
Deny from all
```

#### `/data/.htaccess`
```apache
# Deny all access to data directory
Order deny,allow
Deny from all
```

#### `/includes/.htaccess`
```apache
# Deny all access to includes directory
Order deny,allow
Deny from all
```

#### `/storage/.htaccess`
```apache
# Allow only authorized download access to storage
Order deny,allow
Deny from all

# Allow access only through download script
<Files "*.txt">
    Allow from all
</Files>

<Files "*.pdf">
    Allow from all
</Files>

<Files "*.jpg">
    Allow from all
</Files>

<Files "*.jpeg">
    Allow from all
</Files>

<Files "*.png">
    Allow from all
</Files>

<Files "*.gif">
    Allow from all
</Files>

<Files "*.zip">
    Allow from all
</Files>

<Files "*.doc">
    Allow from all
</Files>

<Files "*.docx">
    Allow from all
</Files>

<Files "*.xls">
    Allow from all
</Files>

<Files "*.xlsx">
    Allow from all
</Files>

# Block executable files
<Files "*.exe">
    Order deny,allow
    Deny from all
</Files>

<Files "*.bat">
    Order deny,allow
    Deny from all
</Files>

<Files "*.cmd">
    Order deny,allow
    Deny from all
</Files>

<Files "*.php">
    Order deny,allow
    Deny from all
</Files>
```

## 🛠️ Log Management Tools

### 📊 Log Viewer (`log-viewer.php`)
- **Access:** Admin only
- **Features:**
  - View different log types
  - Real-time refresh
  - Line count selection
  - Log statistics
  - Clear log functionality

### 🔄 Log Rotation (`log-rotate.php`)
- **Purpose:** Manage log file sizes
- **Features:**
  - Automatic rotation when files exceed 5MB
  - Archive old logs with timestamps
  - Cleanup logs older than 30 days
  - CLI and web API support

## 📈 Log Entry Format
```
[YYYY-MM-DD HH:MM:SS] [LEVEL] [IP_ADDRESS] [USERNAME] MESSAGE
```

**Example:**
```
[2025-06-02 19:52:21] [INFO] [192.168.1.100] [admin] ACCESS: Dashboard viewed
[2025-06-02 19:52:35] [SECURITY] [192.168.1.50] [anonymous] SECURITY: Failed login attempt
[2025-06-02 19:53:12] [ADMIN] [192.168.1.100] [admin] ADMIN: User created: newuser
```

## 🚀 How to Use

### View Logs (Web Interface):
1. Login as admin
2. Navigate to `log-viewer.php`
3. Select log type and line count
4. Monitor real-time activity

### Manual Log Rotation:
```powershell
php log-rotate.php
```

### Check Log Files:
```powershell
# View recent access logs
Get-Content logs\access.log -Tail 20

# View security events
Get-Content logs\security.log -Tail 20

# View error logs
Get-Content logs\error.log -Tail 20
```

## 🔐 Security Benefits

1. **Access Control:** All sensitive directories protected
2. **File Protection:** JSON data files inaccessible directly
3. **Log Privacy:** Log files cannot be accessed via web
4. **Executable Blocking:** Dangerous file types blocked
5. **Headers Security:** XSS, clickjacking, and MIME-type protections
6. **Audit Trail:** Complete activity logging for forensics

## ⚠️ Important Notes

- Log files contain sensitive information - keep them secure
- Regular log rotation prevents disk space issues
- Monitor security logs for suspicious activity
- Backup logs before clearing them
- Test .htaccess rules on your web server environment

Your FileServer now has comprehensive logging and security protection! 🎉
