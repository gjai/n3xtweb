# N3XT Communication

A powerful, secure, and responsive content management system with advanced back office capabilities, automated updates, and comprehensive backup solutions.

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

- **PHP**: 7.4 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **Extensions**: PDO, PDO_MySQL, GD, ZIP, OpenSSL
- **Web Server**: Apache or Nginx with mod_rewrite
- **Disk Space**: Minimum 100MB for installation

## 🛠️ Installation

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

## 🔧 Configuration

### Database Configuration
The system uses MySQL/MariaDB for data storage. Configuration is done during installation in `config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'n3xt_communication');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
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
│   └── config.php     # Main configuration
├── includes/          # Core functionality
│   └── functions.php  # Core functions and classes
├── backups/           # System backups
├── logs/              # System logs
├── uploads/           # User uploads
├── .htaccess         # Apache configuration
├── robots.txt        # Search engine directives
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

**N3XT Communication** - Secure • Responsive • Powerful
