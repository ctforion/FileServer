@echo off
REM PHP File Storage Server - Download Script (Windows)
REM GitHub Repository: https://github.com/0xAhmadYousuf/FileServer
REM This script downloads all files individually and recreates the exact directory structure

setlocal enabledelayedexpansion

REM Configuration
set GITHUB_USER=0xAhmadYousuf
set REPO_NAME=FileServer
set BASE_URL=https://raw.githubusercontent.com/%GITHUB_USER%/%REPO_NAME%/main

echo [INFO] Starting PHP File Storage Server download...
echo [INFO] Repository: https://github.com/%GITHUB_USER%/%REPO_NAME%
echo ==========================================

REM Create directory structure
echo [INFO] Creating directory structure...

mkdir cache\api 2>nul
mkdir cache\data 2>nul
mkdir cache\search 2>nul
mkdir cache\session 2>nul
mkdir cache\templates 2>nul
mkdir cache\thumbnails 2>nul
mkdir plugins\available 2>nul
mkdir plugins\installed 2>nul
mkdir plugins\manager 2>nul
mkdir storage\archive 2>nul
mkdir storage\backup 2>nul
mkdir storage\index 2>nul
mkdir storage\public 2>nul
mkdir storage\quarantine 2>nul
mkdir storage\shared 2>nul
mkdir storage\temp 2>nul
mkdir storage\thumbnails 2>nul

echo [SUCCESS] Directory structure created

echo [INFO] Downloading files...
echo ==========================================

REM Root files
call :download_file ".htaccess"
call :download_file "config.php"
call :download_file "example.env"
call :download_file "generate_migrations.php"
call :download_file "index.php"
call :download_file "Proposal.md"
call :download_file "README.md"
call :download_file "setup.php"

REM API files
mkdir api 2>nul
call :download_file "api\.htaccess"
call :download_file "api\index.php"

REM API - Admin
mkdir api\admin 2>nul
call :download_file "api\admin\dashboard.php"
call :download_file "api\admin\settings.php"
call :download_file "api\admin\users.php"

REM API - Auth
mkdir api\auth 2>nul
call :download_file "api\auth\2fa.php"
call :download_file "api\auth\login.php"
call :download_file "api\auth\logout.php"
call :download_file "api\auth\register.php"
call :download_file "api\auth\reset.php"

REM API - Controllers
mkdir api\controllers 2>nul
call :download_file "api\controllers\AdminController.php"
call :download_file "api\controllers\AuthController.php"
call :download_file "api\controllers\BaseController.php"
call :download_file "api\controllers\FileController.php"
call :download_file "api\controllers\PluginController.php"
call :download_file "api\controllers\SearchController.php"
call :download_file "api\controllers\SystemController.php"
call :download_file "api\controllers\UserController.php"
call :download_file "api\controllers\WebhookController.php"

REM API - Files
mkdir api\files 2>nul
call :download_file "api\files\delete.php"
call :download_file "api\files\download.php"
call :download_file "api\files\list.php"
call :download_file "api\files\metadata.php"
call :download_file "api\files\upload.php"

REM API - Quota
mkdir api\quota 2>nul
call :download_file "api\quota\manage.php"
call :download_file "api\quota\stats.php"

REM API - Search
mkdir api\search 2>nul
call :download_file "api\search\advanced.php"
call :download_file "api\search\index.php"
call :download_file "api\search\suggestions.php"

REM API - Sync
mkdir api\sync 2>nul
call :download_file "api\sync\checksum.php"
call :download_file "api\sync\status.php"

REM API - Webhook
mkdir api\webhook 2>nul
call :download_file "api\webhook\manage.php"
call :download_file "api\webhook\test.php"

REM Assets
mkdir assets\css 2>nul
mkdir assets\js 2>nul
call :download_file "assets\css\app.css"
call :download_file "assets\js\admin.js"
call :download_file "assets\js\app.js"
call :download_file "assets\js\dashboard.js"
call :download_file "assets\js\files.js"
call :download_file "assets\js\login.js"
call :download_file "assets\js\profile.js"
call :download_file "assets\js\search.js"
call :download_file "assets\js\settings.js"
call :download_file "assets\js\upload.js"

REM Core files
mkdir core 2>nul
mkdir core\auth 2>nul
mkdir core\database 2>nul
mkdir core\database\migrations 2>nul
mkdir core\email 2>nul
mkdir core\monitoring 2>nul
mkdir core\plugin 2>nul
mkdir core\storage 2>nul
mkdir core\template 2>nul
mkdir core\update 2>nul
mkdir core\utils 2>nul
mkdir core\webhook 2>nul

call :download_file "core\.htaccess"
call :download_file "core\auth\Auth.php"
call :download_file "core\database\Database.php"
call :download_file "core\database\Migration.php"
call :download_file "core\database\migrations\2024_01_01_000800_create_email_queue_table.php"
call :download_file "core\database\migrations\2024_01_01_000801_create_webhook_deliveries_table.php"
call :download_file "core\database\migrations\2024_01_01_000802_create_backups_table.php"
call :download_file "core\database\migrations\2024_01_01_000803_create_update_log_table.php"
call :download_file "core\email\EmailManager.php"
call :download_file "core\monitoring\Monitor.php"
call :download_file "core\plugin\PluginManager.php"
call :download_file "core\storage\FileManager.php"
call :download_file "core\template\TemplateEngine.php"
call :download_file "core\update\UpdateManager.php"
call :download_file "core\utils\EnvLoader.php"
call :download_file "core\webhook\WebhookManager.php"

REM Language files
mkdir languages 2>nul
call :download_file "languages\ar.json"
call :download_file "languages\bn.json"
call :download_file "languages\config.json"
call :download_file "languages\de.json"
call :download_file "languages\en.json"
call :download_file "languages\es.json"
call :download_file "languages\fr.json"
call :download_file "languages\id.json"
call :download_file "languages\it.json"
call :download_file "languages\ja.json"
call :download_file "languages\ko.json"
call :download_file "languages\pt.json"
call :download_file "languages\ru.json"
call :download_file "languages\ur.json"
call :download_file "languages\zh.json"

REM Logs and Storage
mkdir logs 2>nul
mkdir storage\private 2>nul
mkdir storage\system 2>nul
call :download_file "logs\.htaccess"
call :download_file "storage\.htaccess"
call :download_file "storage\private\.htaccess"
call :download_file "storage\system\.htaccess"

REM Templates
mkdir templates\components 2>nul
mkdir templates\layouts 2>nul
mkdir templates\pages 2>nul
call :download_file "templates\components\footer.php"
call :download_file "templates\components\navbar.php"
call :download_file "templates\components\sidebar.php"
call :download_file "templates\layouts\main.php"
call :download_file "templates\pages\admin.php"
call :download_file "templates\pages\dashboard.php"
call :download_file "templates\pages\files.php"
call :download_file "templates\pages\home.php"
call :download_file "templates\pages\login.php"
call :download_file "templates\pages\profile.php"
call :download_file "templates\pages\search.php"
call :download_file "templates\pages\settings.php"
call :download_file "templates\pages\upload.php"

echo ==========================================
echo [SUCCESS] Download completed successfully!
echo ==========================================
echo.
echo Next Steps:
echo 1. Copy example.env to .env and configure your settings
echo 2. Create a database and update database credentials in .env
echo 3. Run 'php setup.php' to initialize the database
echo 4. Configure your web server to point to this directory
echo 5. Access the application through your web browser
echo.
echo [SUCCESS] PHP File Storage Server is ready to use!
echo [INFO] Repository: https://github.com/%GITHUB_USER%/%REPO_NAME%
echo [INFO] Documentation: See README.md for detailed setup instructions

pause
goto :eof

:download_file
set file_path=%~1
set url_path=%file_path:\=/%
set download_url=%BASE_URL%/%url_path%

echo [INFO] Downloading: %file_path%
powershell -Command "try { Invoke-WebRequest -Uri '%download_url%' -OutFile '%file_path%' -ErrorAction Stop; Write-Host '[SUCCESS] Downloaded: %file_path%' -ForegroundColor Green } catch { Write-Host '[ERROR] Failed to download: %file_path%' -ForegroundColor Red }"
goto :eof
