# FileServer Rules

## Simple Setup Rules

1. **Installation**: Run `./install.sh` or visit `/install.php` in browser
2. **Updates**: Run `./install.sh update` or visit `/update.php`  
3. **Configuration**: Edit `config.php` - that's it
4. **No Composer**: Zero external dependencies, pure PHP
5. **Portable**: Move entire folder anywhere, update `config.php` paths

## File Management Rules

### Upload Rules
- Maximum file size: **100MB** (configurable)
- Allowed types: Images, Documents, Archives, Text files
- Chunked upload for large files (automatic)
- Drag and drop supported everywhere
- Queue system handles multiple uploads

### Storage Rules
- Files stored in `/storage/files/` by default
- Thumbnails auto-generated for images
- Database tracks all file metadata
- Orphaned files cleaned up automatically
- Backup before major operations

### Sharing Rules
- Public links expire after set time
- Password protection available
- Download limits can be set
- Share permissions: View, Download, Upload
- Revoke access anytime

## User Management Rules

### Access Levels
- **Admin**: Full system access
- **User**: Upload, manage own files
- **Guest**: View shared files only

### Authentication Rules
- Session-based login (secure)
- Password requirements: 8+ characters
- Account lockout after failed attempts
- Remember me option available
- Logout from all devices possible

## Security Rules

### File Security
- All uploads scanned for safety
- File type validation (extension + MIME)
- No script execution in upload folders
- Direct file access blocked
- Virus scanning (if ClamAV available)

### System Security
- CSRF protection on all forms
- SQL injection prevention
- XSS protection enabled
- Secure headers configured
- Rate limiting on API endpoints

## Performance Rules

### Optimization
- File thumbnails cached
- Database queries optimized
- Gzip compression enabled
- CDN ready (configurable)
- Browser caching headers set

### Limits
- Concurrent uploads: 3 per user
- API rate limit: 100 requests/minute
- Search results: 50 per page
- Log retention: 30 days default
- Session timeout: 24 hours

## Maintenance Rules

### Automatic Tasks
- Log rotation weekly
- Cache cleanup daily
- Orphaned file cleanup monthly
- Database optimization weekly
- Backup creation (configurable)

### Manual Tasks
- Check disk space regularly
- Review user activity logs
- Update system when available
- Test backup restoration
- Monitor error logs

## API Rules

### Endpoints
- `/api/files/*` - File operations
- `/api/users/*` - User management  
- `/api/admin/*` - Administration
- `/api/shares/*` - Sharing features
- All return JSON format

### Authentication
- Header: `X-CSRF-Token` required
- Session authentication
- API key support (optional)
- Rate limiting applied
- CORS configurable

## Deployment Rules

### Requirements
- PHP 7.4+ (8.1+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx with mod_rewrite
- GD/ImageMagick for thumbnails
- 100MB+ disk space

### Production Setup
1. Set `ENVIRONMENT=production` in config
2. Disable debug mode
3. Use HTTPS only
4. Set proper file permissions
5. Configure backup strategy

### Development Setup
1. Set `ENVIRONMENT=development` in config
2. Enable debug mode
3. Use detailed error reporting
4. Test mode for email
5. Local database recommended

## Troubleshooting Rules

### Common Issues
- **500 Error**: Check file permissions
- **Upload fails**: Check file size limits
- **Login issues**: Clear sessions/cookies
- **Slow performance**: Check database
- **Missing files**: Run orphan cleanup

### Debug Mode
- Enable in `config.php`
- Shows detailed errors
- Logs all database queries
- Displays execution time
- Security warnings visible

### Support
- Check error logs first
- Use debug mode for details
- Test with minimal config
- Backup before changes
- Document steps to reproduce

---

**Remember**: This is a file server, keep it simple. Edit `config.php`, run install script, you're done.
- `storage/private/` - Protected, auth required
- `storage/temp/` - Auto-cleanup enabled
- `storage/shared/` - Time-limited access

## 🔄 Update System

### Automatic Updates
```bash
cd FileServer
./install.sh update
```

### Manual Updates
1. Backup current installation
2. Download latest version
3. Run `./install.sh migrate`

## 🚨 Troubleshooting

### Common Issues
1. **Permission denied**: Check file permissions
2. **Database error**: Verify credentials in `config.php`
3. **File upload fails**: Check PHP upload limits
4. **404 errors**: Ensure `.htaccess` or nginx config

### Debug Mode
Set `DEBUG = true` in `config.php` for detailed error logs.

## 📞 Support

- Check logs in `logs/` directory
- Enable debug mode for detailed errors
- Verify web server configuration
- Ensure PHP extensions: `json`, `mysqli`, `gd`
