# PHP File Storage Server - Download Scripts

This repository contains automated download scripts to download and set up the complete PHP File Storage Server from the GitHub repository: https://github.com/0xAhmadYousuf/FileServer

## Available Scripts

### 1. file.sh (Linux/macOS - Bash Script)
For Unix-based systems (Linux, macOS, WSL)

**Requirements:**
- `wget` command-line tool
- Bash shell

**Usage:**
```bash
# Make the script executable
chmod +x file.sh

# Run the script
./file.sh
```

### 2. file.bat (Windows - Batch Script)
For Windows Command Prompt

**Requirements:**
- Windows Command Prompt
- PowerShell (for download functionality)

**Usage:**
```cmd
# Run the batch file
file.bat
```

### 3. file.ps1 (Windows - PowerShell Script)
For Windows PowerShell (Recommended for Windows)

**Requirements:**
- Windows PowerShell 5.0 or later

**Usage:**
```powershell
# Set execution policy if needed (run as Administrator)
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser

# Run the PowerShell script
.\file.ps1

# Or with verbose output
.\file.ps1 -Verbose

# Or specify custom output directory
.\file.ps1 -OutputPath "C:\MyProjects\FileServer" -Verbose
```

## What These Scripts Do

1. **Create Directory Structure**: Automatically creates all necessary directories including:
   - `cache/` (api, data, search, session, templates, thumbnails)
   - `plugins/` (available, installed, manager)
   - `storage/` (archive, backup, index, public, quarantine, shared, temp, thumbnails)
   - All other project directories

2. **Download All Files**: Downloads 99+ files from the GitHub repository including:
   - Root configuration files (.htaccess, config.php, index.php, etc.)
   - API endpoints and controllers
   - Core system files (auth, database, email, storage, etc.)
   - Frontend assets (CSS, JavaScript)
   - 14 language translation files (en, es, fr, de, it, pt, ru, ja, ko, zh, ar, bn, ur, id)
   - Templates and components
   - Database migrations
   - Security files (.htaccess for protected directories)

3. **Set Proper Permissions** (Linux/macOS only):
   - Sets executable permissions for directories
   - Sets write permissions for cache, logs, and storage directories

## Project Features

The downloaded PHP File Storage Server includes:

- **Multi-language Support**: 14 languages (English, Spanish, French, German, Italian, Portuguese, Russian, Japanese, Korean, Chinese, Arabic, Bengali, Urdu, Indonesian)
- **Role-based Permissions**: Admin, user, and guest roles with customizable permissions
- **RESTful API**: Complete API for file operations, user management, and system administration
- **Modern Responsive UI**: Beautiful and intuitive web interface
- **File Management**: Upload, download, delete, share, and organize files
- **Search Functionality**: Advanced search with filters and suggestions
- **Auto-updates**: Built-in update system for easy maintenance
- **Security Features**: Authentication, 2FA, file quarantine, and access control
- **Plugin System**: Extensible architecture with plugin support
- **Monitoring & Logging**: System monitoring and comprehensive logging
- **Email Integration**: Email notifications and user management
- **Webhook Support**: Integration with external services
- **Backup System**: Automated backup and recovery

## After Download

Once the download is complete, follow these steps:

### 1. Configure Environment
```bash
# Copy the example environment file
cp example.env .env

# Edit the .env file with your settings
nano .env  # or use your preferred editor
```

### 2. Database Setup
- Create a MySQL/MariaDB database
- Update database credentials in `.env`
- Run the setup script:
```bash
php setup.php
```

### 3. Web Server Configuration

**Apache:**
- Point your virtual host to the project directory
- Ensure mod_rewrite is enabled
- The included .htaccess files will handle URL rewriting

**Nginx:**
- Configure your server block to point to the project directory
- Set up URL rewriting for the API endpoints

### 4. File Permissions (Linux/macOS)
```bash
# Set proper permissions
chmod -R 755 .
chmod -R 777 cache/
chmod -R 777 logs/
chmod -R 777 storage/
```

### 5. Access the Application
- Open your web browser
- Navigate to your configured domain/localhost
- Complete the initial setup through the web interface

## Troubleshooting

### Common Issues

1. **Download Failures:**
   - Check internet connection
   - Verify GitHub is accessible
   - Try running the script again (it will skip existing files)

2. **Permission Denied (Linux/macOS):**
   ```bash
   chmod +x file.sh
   ```

3. **PowerShell Execution Policy (Windows):**
   ```powershell
   Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
   ```

4. **Missing wget (Linux):**
   ```bash
   # Ubuntu/Debian
   sudo apt-get install wget
   
   # CentOS/RHEL
   sudo yum install wget
   
   # macOS
   brew install wget
   ```

## File Structure

After successful download, you'll have this structure:

```
FileServer/
├── .htaccess
├── config.php
├── example.env
├── index.php
├── setup.php
├── README.md
├── api/
│   ├── admin/
│   ├── auth/
│   ├── controllers/
│   ├── files/
│   ├── quota/
│   ├── search/
│   ├── sync/
│   └── webhook/
├── assets/
│   ├── css/
│   └── js/
├── cache/
├── core/
│   ├── auth/
│   ├── database/
│   ├── email/
│   ├── monitoring/
│   ├── plugin/
│   ├── storage/
│   ├── template/
│   ├── update/
│   ├── utils/
│   └── webhook/
├── languages/
├── logs/
├── plugins/
├── storage/
└── templates/
    ├── components/
    ├── layouts/
    └── pages/
```

## Support

For issues related to the PHP File Storage Server itself, please visit:
- GitHub Repository: https://github.com/0xAhmadYousuf/FileServer
- Documentation: See README.md in the downloaded files

For issues with these download scripts, please check:
1. Internet connectivity
2. Required tools installation (wget, PowerShell)
3. File permissions
4. GitHub accessibility

## License

These download scripts are provided as-is for convenience. The actual PHP File Storage Server has its own license as specified in the GitHub repository.

---

**Note:** These scripts download the latest version from the main branch of the GitHub repository. The actual project may have been updated since these scripts were created.
