# N3XT WEB

A powerful, secure, and responsive web content management system with advanced back office capabilities, automated updates, and comprehensive backup solutions.

**Publisher:** N3XT Communication  
**Authors:** Julien Gauthier & Copilot  
**Version:** 2.0.0

## 🚀 Features

### 🔐 Enhanced Security
- Multi-layer authentication with captcha protection
- Rate limiting and IP blocking after failed attempts
- Comprehensive access logging and audit trails
- PDO prepared statements to prevent SQL injection
- Security headers and CSRF token protection

### 🔄 Automated Updates
- GitHub integration for downloading latest releases
- Automatic backup creation before updates
- File integrity checking and unexpected file scanning
- Safe core replacement excluding critical directories
- Comprehensive update logging

### 💾 Backup & Restore
- Complete system backup including database and files
- ZIP archive upload and extraction
- Selective restoration options
- Database import/export functionality
- Critical file preservation during restore

### 📱 Mobile-First Design
- Responsive interface that works on all devices
- Mobile-optimized navigation and forms
- Touch-friendly controls and interactions
- Progressive enhancement for desktop users

### 📊 Comprehensive Logging
- Access attempt logging with IP tracking
- Update activity monitoring
- System error tracking
- Configurable log levels
- Real-time log viewing in admin panel

## 📋 System Requirements

- **PHP**: 7.4 or higher (8.2+ recommended for OVH)
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **Extensions**: PDO, PDO_MySQL, GD, ZIP, OpenSSL
- **Web Server**: Apache or Nginx with mod_rewrite
- **Disk Space**: Minimum 100MB for installation

### ✅ OVH Shared Hosting Compatibility
- **Fully tested** on OVH shared hosting (mutualisé)
- **Optimized** `.htaccess` without LocationMatch directives
- **Automatic** PHP version control via `.ovhconfig`
- **Enhanced** security for shared environments
- **Performance** optimized for shared hosting resources

## 🛠️ Installation

### Standard Installation

1. **Download and Extract**
   ```bash
   # Download the latest release
   wget https://github.com/gjai/n3xtweb/archive/main.zip
   unzip main.zip
   mv n3xtweb-main/* /path/to/your/webroot/
   ```

2. **Set Permissions**
   ```bash
   chmod 755 -R /path/to/your/webroot/
   chmod 777 config/ logs/ backups/ uploads/
   ```

3. **Run Installation**
   - Navigate to `http://yourdomain.com/install.php`
   - Follow the step-by-step installation wizard
   - Configure database settings
   - Create admin account

4. **Security Setup** (Important!)
   - Remove or restrict access to `install.php`
   - Verify `.htaccess` configuration
   - Enable HTTPS if possible
   - Review security settings

### 🏢 OVH Shared Hosting Installation

N3XT WEB is fully optimized for **OVH shared hosting (mutualisé)** with special configurations for maximum compatibility and security.

#### Prerequisites for OVH
- OVH shared hosting plan with PHP 8.0+ support
- MySQL database (available in OVH control panel)
- FTP/SFTP access to your hosting space
- Access to OVH control panel for database management

#### Step-by-Step OVH Installation

1. **Prepare Your OVH Environment**
   ```
   ✓ Log in to OVH control panel
   ✓ Create a MySQL database and note the credentials
   ✓ Access your hosting via FTP/SFTP
   ```

2. **Download and Upload Files**
   ```bash
   # Download to your computer then upload via FTP to www/ directory
   # Or use command line if SSH is available:
   cd ~/www
   wget https://github.com/gjai/n3xtweb/archive/main.zip
   unzip main.zip
   mv n3xtweb-main/* ./
   rm -rf n3xtweb-main/ main.zip
   ```

3. **Set OVH-Specific Permissions** (via FTP client or SSH)
   ```bash
   # Essential directories need write permissions
   chmod 755 -R ./
   chmod 755 config/ logs/ backups/ uploads/
   # Note: On OVH shared hosting, avoid 777 permissions
   ```

4. **Configure OVH PHP Settings**
   The included `.ovhconfig` file automatically sets:
   - PHP version 8.2 (recommended)
   - Production environment
   - Optimal security settings

5. **Database Configuration for OVH**
   When running `install.php`, use your OVH database credentials:
   ```
   DB Host: mysql51-66.perso (example - check your OVH panel)
   DB Name: your_database_name
   DB User: your_database_user  
   DB Pass: your_database_password
   ```

6. **Security Verification**
   ✓ Verify `.htaccess` is working (sensitive directories should be blocked)
   ✓ Check `robots.txt` is accessible
   ✓ Confirm `uploads/.htaccess` prevents PHP execution
   ✓ Test admin login at `/admin/login.php`

#### OVH-Specific File Structure
```
www/                          # Your OVH web root
├── .ovhconfig               # PHP version and environment settings
├── .htaccess               # OVH-compatible security rules
├── uploads/.htaccess       # Blocks PHP execution in uploads
├── config/config.php       # Database config with PDO examples
└── [rest of N3XT files]
```

#### OVH Security Best Practices

**File Permissions**
- Use 755 for directories and 644 for files
- Avoid 777 permissions on shared hosting
- The included `.htaccess` files provide additional protection

**Database Security**
- Use the PDO connection example in `config/config.php`
- Always use prepared statements (built into N3XT)
- Keep database credentials secure

**Performance Optimization**
- The `.ovhconfig` forces PHP 8.2 for better performance
- Caching headers are optimized for shared hosting
- File compression is enabled in `.htaccess`

**Backup Strategy**
```bash
# Regular backups (automate if possible)
# Download via admin panel or create scheduled task
# Store backups outside web root when possible
```

#### OVH-Specific Troubleshooting

**Common OVH Issues and Solutions**

**PHP Version Problems**
```
Problem: Old PHP version being used
Solution: Verify .ovhconfig file is present and readable
Check: OVH control panel > PHP configuration
```

**Database Connection Issues**
```
Problem: Cannot connect to database
Solution: Verify database credentials in OVH control panel
Check: Use full hostname from OVH (e.g., mysql51-66.perso)
Note: Some OVH plans use different MySQL servers
```

**File Permission Errors**
```
Problem: Cannot write to directories
Solution: Set correct permissions via FTP client
OVH Safe: chmod 755 for directories, 644 for files
Avoid: Using 777 permissions on shared hosting
```

**Htaccess Not Working**
```
Problem: Sensitive files accessible directly  
Solution: Verify .htaccess upload and OVH plan supports mod_rewrite
Check: Contact OVH support if rewrite rules don't work
```

**Upload Directory Issues**
```
Problem: PHP files can be executed in uploads/
Solution: Ensure uploads/.htaccess is present and working
Verify: Test by trying to access a .php file in uploads/
```

**Performance Issues**
```
Problem: Site runs slowly
Solution: Check .ovhconfig forces PHP 8.2
Enable: Compression and caching via .htaccess
Monitor: Resource usage in OVH control panel
```

#### OVH Maintenance Tips

- **Regular Updates**: Use the built-in update system monthly
- **Monitor Logs**: Check logs regularly via admin panel  
- **Backup Schedule**: Create weekly backups minimum
- **Security Scan**: Review access logs for suspicious activity
- **Resource Monitoring**: Monitor disk space and bandwidth in OVH panel

## 🔧 Configuration

### Database Configuration
The system uses MySQL/MariaDB for data storage. Configuration is done during installation in `config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'n3xtweb_database');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

### OVH Database Configuration with PDO
For OVH shared hosting, use the included PDO example in `config/config.php`:

```php
// Example for OVH shared hosting
define('DB_HOST', 'mysql51-66.perso'); // Your OVH MySQL server
define('DB_NAME', 'your_ovh_database'); // Database name from OVH panel
define('DB_USER', 'your_ovh_user');     // Username from OVH panel  
define('DB_PASS', 'your_ovh_password'); // Password from OVH panel

// Use the PDO connection function (uncomment in config.php)
$pdo = getPDOConnection();
```

### Security Settings
```php
define('ADMIN_SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
```

### GitHub Integration
For automatic updates, configure GitHub repository:
```php
define('GITHUB_OWNER', 'gjai');
define('GITHUB_REPO', 'n3xtweb');
```

## 🎯 Usage

### Admin Panel Access
1. Navigate to `/admin/login.php`
2. Enter your admin credentials
3. Complete captcha if required (after failed attempts)

### System Updates
1. Go to **System Update** in admin panel
2. Check for available updates
3. Create backup before updating
4. Scan for unexpected files
5. Download and apply updates

### Backup Management
1. Access **Backup & Restore** section
2. Create new backups or upload existing ones
3. Download backups for external storage
4. Restore system from backup when needed

### Maintenance Mode
- Enable/disable through configuration
- Displays maintenance page to visitors
- Admins can still access the system
- Preview maintenance page: `/maintenance.php?preview=1`

## 🗂️ Directory Structure

```
n3xtweb/
├── admin/              # Admin panel files
│   ├── index.php       # Main dashboard
│   ├── login.php       # Authentication
│   ├── update.php      # Update management
│   ├── restore.php     # Backup & restore
│   └── captcha.php     # Security captcha
├── assets/             # Static assets
│   ├── css/           # Stylesheets
│   └── js/            # JavaScript files
├── config/            # Configuration files
│   └── config.php     # Main configuration (with PDO examples)
├── includes/          # Core functionality
│   └── functions.php  # Core functions and classes
├── backups/           # System backups
├── logs/              # System logs
├── uploads/           # User uploads
│   └── .htaccess      # Security rules for uploads (blocks PHP)
├── .htaccess         # Apache configuration (OVH compatible)
├── .ovhconfig        # OVH PHP version and environment settings
├── robots.txt        # Search engine directives (enhanced)
├── index.php         # Main landing page
├── install.php       # Installation wizard
└── maintenance.php   # Maintenance mode page
```

## 🔒 Security Features

### Authentication
- Secure password hashing with Argon2ID
- Session management with regeneration
- CSRF token protection on all forms
- IP-based rate limiting and blocking

### File Security
- `.htaccess` rules block access to sensitive files
- Robots.txt prevents search engine indexing
- File upload validation and size limits
- Safe filename generation

### Database Security
- All queries use PDO prepared statements
- Input sanitization and validation
- SQL injection prevention
- Database connection encryption

## 📝 Logging

### Access Logs (`logs/access.log`)
- Login attempts (successful and failed)
- IP addresses and user agents
- Timestamp and result status

### Update Logs (`logs/update.log`)
- System update activities
- Backup creation and restoration
- File operations and results

### System Logs (`logs/system.log`)
- General system events
- Error messages and warnings
- Performance monitoring

## 🔄 Update Process

1. **Check for Updates**: Connects to GitHub API to check for newer releases
2. **Create Backup**: Automatically creates full system backup
3. **File Scanning**: Identifies unexpected files that might conflict
4. **Download Release**: Downloads latest release from GitHub
5. **Apply Update**: Safely replaces core files while preserving critical data

## 🛡️ Backup & Restore

### Backup Contents
- Complete file system (excluding logs and temporary files)
- Full database dump with structure and data
- Configuration files and uploads

### Restore Options
- **Database Only**: Restore database from backup.sql
- **Files Only**: Restore system files
- **Selective Restore**: Choose specific components
- **Configuration Preservation**: Keep current config during restore

## 🚨 Troubleshooting

### Common Issues

**Installation Problems**
- Check file permissions (755 for files, 777 for writable directories)
- Verify PHP extensions are installed
- Ensure database credentials are correct

**Login Issues**
- Check if IP is blocked (logs/blocked_ips.json)
- Verify database connection
- Clear browser cache and cookies

**Update Problems**
- Ensure GitHub API is accessible
- Check file permissions for backup directory
- Review update logs for specific errors

**Backup/Restore Issues**
- Verify ZIP extension is installed
- Check available disk space
- Ensure proper file permissions

### Log Analysis
- Access logs through admin panel
- Check system logs for detailed error messages
- Monitor update logs for process status

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For support and questions:
- Email: support@n3xtcommunication.com
- GitHub Issues: [Create an issue](https://github.com/gjai/n3xtweb/issues)

---

**N3XT WEB** - Secure • Responsive • Powerful
