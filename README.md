# N3XT WEB

A powerful, secure, and modular web content management system with advanced back office capabilities, automated updates, and comprehensive backup solutions.

**Publisher:** N3XT Communication  
**Authors:** Julien Gauthier & Copilot  
**Version:** 2.5.0

## 🏗️ Modular Architecture

N3XT WEB features a modular architecture designed for clean installation, scalability, and maintainability:

### Core Modules
- **[EventManager](modules/EventManager/README.md)** - System event logging and monitoring
- **[SecurityManager](modules/SecurityManager/README.md)** - Security policies and threat detection  
- **[BackupManager](modules/BackupManager/README.md)** - Backup and restore operations
- **[UpdateManager](modules/UpdateManager/README.md)** - System updates and version management
- **[NotificationManager](modules/NotificationManager/README.md)** - Email notifications and messaging
- **[MaintenanceManager](modules/MaintenanceManager/README.md)** - Automated system maintenance

### Module Features
- **Independent Configuration** - Each module has its own configuration table
- **Database-Driven Settings** - No hardcoded configuration files
- **Migration Support** - Tracked module migrations for seamless updates
- **Admin Interfaces** - Dedicated management interfaces for each module
- **Event Integration** - Cross-module communication via EventManager
- **Security Integration** - All modules integrate with SecurityManager

## 🚀 Key Features

### 🔐 Enhanced Security
- Multi-layer authentication with configurable captcha protection
- Comprehensive database-based access logging and audit trails
- PDO prepared statements to prevent SQL injection
- Security headers and CSRF token protection
- Dynamic security settings configurable from Back Office
- Automatic installation cleanup and security hardening

### 🔄 Automated Updates
- GitHub integration for downloading latest releases
- ZIP upload system for manual updates (max 50MB)
- Automatic backup creation before updates
- File integrity checking and unexpected file scanning
- Safe core replacement excluding critical directories
- Comprehensive update logging and rollback capability

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

## 📋 System Requirements

- **PHP**: 7.4 or higher (8.2+ recommended for OVH)
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **Extensions**: PDO, PDO_MySQL, GD, ZIP, OpenSSL
- **Web Server**: Apache or Nginx with mod_rewrite
- **Disk Space**: Minimum 100MB for installation

### ✅ OVH Shared Hosting Compatibility
- Fully tested on OVH shared hosting (mutualisé)
- Optimized `.htaccess` without LocationMatch directives
- Automatic PHP version control via `.ovhconfig`
- Enhanced security for shared environments
- Performance optimized for shared hosting resources

## 🛠️ Clean Server Installation

### Prerequisites
- Fresh server environment (VPS or shared hosting)
- PHP 7.4+ with required extensions
- MySQL/MariaDB database
- Web server (Apache/Nginx) with URL rewriting

### Installation Steps

#### 1. Download and Prepare
```bash
# Download latest release
wget https://github.com/gjai/n3xtweb/archive/main.zip
unzip main.zip
mv n3xtweb-main/* /path/to/webroot/
cd /path/to/webroot/
```

#### 2. Set Directory Permissions
```bash
chmod 755 -R .
chmod 777 config/ logs/ backups/ uploads/
```

#### 3. Run Installation Wizard
Navigate to `http://yourdomain.com/install.php` and follow these steps:

1. **Language Selection** - Choose French (default) or English
2. **System Requirements Check** - Verify server compatibility
3. **Administrator Configuration** - Set up admin account with email verification
4. **Database Configuration** - Configure MySQL connection
5. **Finalization** - Automatic cleanup and system initialization

#### 4. Post-Installation Security
- Installation files are automatically removed
- Admin directory is renamed for security
- Maintenance mode is enabled by default
- SSL/TLS configuration recommended

### Modular Configuration

The installation automatically creates module-specific configuration tables:

| Module | Configuration Table | Purpose |
|--------|-------------------|---------|
| Backup | `{prefix}backup_config` | Backup settings and retention policies |
| Update | `{prefix}update_config` | Update sources and procedures |
| Notification | `{prefix}notification_config` | Email and messaging settings |
| Maintenance | `{prefix}maintenance_config` | Automated maintenance schedules |
| Security | `{prefix}security_config` | Security policies and thresholds |
| Events | `{prefix}event_config` | Event logging and monitoring |

### Migration System

The system includes a comprehensive migration tracking system:

- **Module Migrations** - Tracked in `{prefix}module_migrations` table
- **Version Control** - Each module tracks its schema version
- **Automatic Execution** - Migrations run automatically during installation/updates
- **Rollback Support** - Migration rollback capabilities for development

### Compliance Rules

#### Security Compliance
- All forms include CSRF protection
- SQL queries use prepared statements
- Input validation and sanitization
- Session security with configurable timeouts
- IP blocking and rate limiting
- Security audit logging

#### Modular Compliance
- Each module is self-contained
- Configuration stored in dedicated tables
- No cross-module dependencies (except EventManager)
- Consistent naming conventions
- Standardized error handling

#### Update Compliance
- Personal configuration files are preserved
- Custom files listed in `update.excludes` are protected
- Automatic backup before updates
- Rollback capability on failure
- Migration tracking prevents conflicts

## 🏢 OVH Shared Hosting Installation

N3XT WEB is fully optimized for **OVH shared hosting (mutualisé)** with special configurations.

### OVH-Specific Installation Steps

1. **Prepare OVH Environment**
   - Log in to OVH control panel
   - Create MySQL database and note credentials
   - Access hosting via FTP/SFTP

2. **Upload Files**
   - Download N3XT WEB from GitHub
   - Extract and upload to domain root via FTP
   - Ensure `.htaccess` and `.ovhconfig` are uploaded

3. **Configure Database** (Pre-filled for OVH)
   - Host: `mysql51-XX.perso` (automatically detected)
   - Database name: Your OVH database name
   - Username: Your OVH database username
   - Password: Your OVH database password

4. **Run Installation**
   - Navigate to `https://yourdomain.com/install.php`
   - Installation wizard includes OVH-specific optimizations
   - Automatic cleanup and security hardening

## 📚 Administration

### Back Office Access
The back office is accessible through a renamed directory for security:
- Default URL: `https://yourdomain.com/bo/` (renamed during installation)
- Login with the administrator account created during installation
- Enable maintenance mode for secure updates and maintenance

### Module Management
Each module provides dedicated administration interfaces:
- **EventManager**: Event logs and monitoring (planned: `/bo/events.php`)
- **SecurityManager**: Security policies (planned: `/bo/security.php`)  
- **BackupManager**: Backup operations at `/bo/restore.php`
- **UpdateManager**: System updates at `/bo/update.php`
- **MaintenanceManager**: Maintenance tasks at `/bo/maintenance.php`

## 🛡️ Security Features

### Core Security
- **CSRF Protection** - All forms protected against cross-site request forgery
- **SQL Injection Prevention** - PDO prepared statements throughout
- **XSS Protection** - Input sanitization and output encoding
- **Session Security** - Secure session handling with configurable timeouts
- **Password Security** - Configurable complexity requirements and hashing

### Access Control
- **IP Management** - Whitelist/blacklist functionality
- **Rate Limiting** - Configurable login attempt limits
- **Brute Force Protection** - Automatic IP blocking
- **Two-Factor Authentication** - Optional 2FA support (SecurityManager)
- **Session Management** - Secure token generation and validation

### Monitoring & Auditing
- **Security Events** - Comprehensive security event logging
- **Threat Detection** - Real-time threat level assessment
- **Security Scanning** - Automated security health checks
- **Audit Trails** - Complete access and action logging
- **Alert System** - Critical security event notifications

## 🧩 Modularity & Architecture

### Design Principles
- **Separation of Concerns** - Each module handles specific functionality
- **Loose Coupling** - Minimal dependencies between modules
- **High Cohesion** - Related functionality grouped together
- **Extensibility** - Easy to add new modules or extend existing ones
- **Maintainability** - Clear structure and consistent coding standards

### Module Structure
```
modules/
├── EventManager/         # Event logging and monitoring
│   ├── EventManager.php  # Core module class
│   ├── views/            # Admin interface views
│   └── README.md         # Module documentation
├── SecurityManager/      # Security policies and protection
├── BackupManager/        # Backup and restore operations
├── UpdateManager/        # System updates and versioning
├── NotificationManager/  # Email and messaging services
└── MaintenanceManager/   # Automated maintenance tasks
```

### Configuration Management
- **Database-Driven** - All configuration stored in database tables
- **Module-Specific** - Each module has its own configuration table
- **Runtime Configuration** - No configuration file editing required
- **Version Control** - Configuration changes tracked and versioned
- **Environment Agnostic** - Same codebase works across environments

### Inter-Module Communication
- **Event System** - Modules communicate via EventManager
- **Service Layer** - Shared services for common functionality
- **Dependency Injection** - Loose coupling through DI patterns
- **API Contracts** - Well-defined interfaces between modules
- **Error Handling** - Consistent error handling across modules

## 🗺️ Roadmap

### Version 3.0 (Q1 2024)
- **Multi-Language Frontend** - Full frontend internationalization
- **API System** - RESTful API for external integrations
- **Plugin Architecture** - Third-party plugin support
- **Advanced Analytics** - Comprehensive system analytics dashboard
- **Mobile App** - Companion mobile application for administration

### Version 2.6 (Next Release)
- **Enhanced SecurityManager** - Advanced threat detection algorithms
- **Performance Monitoring** - Real-time performance metrics
- **Automated Testing** - Comprehensive test suite implementation
- **Documentation Portal** - Interactive documentation system
- **Theme System** - Customizable admin interface themes

### Ongoing Development
- **Security Hardening** - Continuous security improvements
- **Performance Optimization** - Ongoing performance enhancements
- **OVH Integration** - Deeper OVH hosting integration
- **Community Features** - User feedback and contribution systems
- **Enterprise Features** - Advanced features for enterprise deployments

### Future Considerations
- **Cloud Integration** - AWS, Azure, GCP deployment options
- **Container Support** - Docker containerization
- **Microservices** - Potential microservices architecture
- **Machine Learning** - AI-powered security and analytics
- **Blockchain Integration** - Blockchain-based audit trails

## 🚨 Troubleshooting

### Installation Problems
- Check file permissions (755 for files, 777 for writable directories)
- Verify PHP extensions are installed
- Ensure database credentials are correct

### Login Issues
- Use the **"🔧 Tester la connexion BDD"** button on the login form
- Check database credentials in `config/config.php`
- Verify database host and database name are correct
- Database diagnostic messages provide specific error codes and suggestions
- Check if IP is blocked (logs/blocked_ips.json)
- Clear browser cache and cookies

### Update Problems
- Ensure GitHub API is accessible
- Check file permissions for backup directory
- Review update logs for specific errors

### Backup/Restore Issues
- Verify ZIP extension is installed
- Check available disk space
- Ensure proper file permissions

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License.

## 🆘 Support

For support and questions:
- Email: support@n3xtcommunication.com
- GitHub Issues: [Create an issue](https://github.com/gjai/n3xtweb/issues)

---

**N3XT WEB** - Secure • Modular • Powerful