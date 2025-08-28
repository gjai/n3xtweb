# Changelog

All notable changes to N3XT WEB will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.3.1] - 2024-12-19

### Added
- **🧹 Installation Cleanup**: Automatic removal of unnecessary directories after installation
  - Fake `/admin` directory (security decoy) is now automatically removed after installation
  - Original `/bo` directory is removed after random BO directory creation
  - Only essential directories and the real BO remain after installation
- **🔧 Enhanced Database Diagnostics**: Improved database connection error handling in Back Office
  - Detailed database connection error messages with specific error codes
  - Database connection test button in BO login form
  - Clear error diagnostics for common database issues (access denied, unknown database, host issues)
  - Configuration suggestions for quick resolution of database problems

### Fixed
- Installation process now properly cleans up temporary and security decoy directories
- Database connection errors in BO login now provide actionable error messages
- Better error handling for database configuration issues

## [2.3.0] - 2024-12-19

### Added
- **🗄️ Database-Based Logging**: Complete migration from file-based to database logging
  - New `access_logs` table for all login attempts and access events
  - New `login_attempts` table for detailed login tracking with IP tracking
  - Enhanced security monitoring with database storage
  - File-based logging maintained as fallback for system issues
- **⚙️ Configurable Security Settings**: Dynamic security configuration system
  - Security features (captcha, login limits, IP blocking, IP tracking) disabled by default
  - Configurable through Back Office settings stored in database
  - Real-time security setting updates without code changes
  - Backward compatibility with existing file-based fallbacks
- **🔌 Enhanced Database Connection**: Improved connection handling and diagnostics
  - Advanced connection testing with specific error code handling
  - Database connection test functionality in Back Office
  - Clear error messages for common connection issues (access denied, unknown database, host issues)
  - Better diagnostic information for troubleshooting
- **🎯 Default MySQL Configuration**: Pre-configured for nxtxyzylie618 hosting
  - Default host: `nxtxyzylie618.mysql.db`
  - Default database: `nxtxyzylie618_db`
  - Default username: `nxtxyzylie618_user`
  - Auto-populated installation form with hosting-specific defaults

### Changed
- **Database Configuration**: Updated default MySQL settings for optimized hosting
- **Security System**: All security features now disabled by default and configurable
- **Logging Architecture**: Primary logging moved to database with file fallback
- **Installation Process**: Enhanced BO directory creation with proper .htaccess
- **Session Management**: Improved session handling with better cookie security
- **Back Office Structure**: Fixed BO directory copying from correct source (`bo/` instead of `admin/`)

### Fixed
- **Installation Process**: Fixed BO directory creation and placement issues
- **Database Connection**: Enhanced error handling and connection diagnostics
- **Session Management**: Improved session purging and cookie management
- **Login Process**: Fixed post-installation login issues with better session handling
- **Table Prefix Handling**: Dynamic table prefix resolution for better installation compatibility

### Security
- **Enhanced Session Security**: Improved cookie parameters and session handling
- **Dynamic Security Configuration**: Security features configurable without code changes
- **Database Security**: Prepared statements and proper error handling
- **Access Logging**: Comprehensive database-based access tracking

### Technical Details
- New `SecuritySettings` class for dynamic configuration management
- Enhanced `Database` class with connection testing and error diagnostics
- Improved `Session` class with secure cookie handling
- Database tables: `access_logs`, `login_attempts` with proper indexing
- Enhanced `Logger` class with database storage and file fallback
- Better error handling throughout the authentication system

---


### Added
- **🗑️ Complete Uninstall System**: Full system uninstallation with double confirmation
  - Automated backup creation before uninstall (SQL database, config files, custom files)
  - Downloadable backup before system deletion
  - Complete database cleanup and config reset
  - Automatic install.php restoration for fresh installation
- **🔒 Enhanced Security System**: Fake admin directory for intrusion detection
  - Fake `/admin` directory with deceptive login form
  - Real back office moved to `/bo` directory for security through obscurity
  - Automatic intrusion logging with detailed IP tracking
  - Email notifications to admin on unauthorized access attempts
- **🔐 Advanced Authentication**: Comprehensive login improvements
  - Forgot password functionality with secure token-based reset
  - Password strength validation and matching verification
  - Enhanced rate limiting with 1-minute IP blocking
  - Improved French translations throughout authentication interface
  - Auto-removal of install.php after successful installation
- **👥 Espace Pro Configuration**: Enhanced client space management
  - Renamed "Espace Client" to "Espace Pro" throughout the system
  - Configurable redirect URL (default: client.n3xt.xyz)
  - Professional client management interface
  - Advanced pro space configuration options
- **📧 Enhanced Email System**: Professional email templates
  - Modern HTML email templates with responsive design
  - Logo integration in all email communications
  - Improved styling with gradient backgrounds and professional layout
  - Enhanced security notifications and password reset emails
  - Better French translations for all email content

### Changed
- **System Version**: Updated to 2.2.0 to reflect major security and functionality improvements
- **Directory Structure**: Moved real admin panel to `/bo` for enhanced security
- **Admin Navigation**: Updated menu structure with new uninstall option
- **Email Templates**: Complete redesign with modern styling and mobile responsiveness
- **Intrusion Detection**: Improved file scanning to better handle legitimate system files
- **Database Schema**: Added reset token fields to admin_users table

### Fixed
- **Install.php Auto-removal**: Now properly removes installation file after completion
- **Login Session Management**: Enhanced session handling and cookie security
- **File Intrusion Detection**: Better recognition of legitimate system files
- **French Translations**: Corrected and improved throughout the system
- **Email Delivery**: Enhanced email system with better error handling

### Security
- **Enhanced Access Control**: Fake admin directory catches unauthorized access attempts
- **Improved Rate Limiting**: More sophisticated login attempt tracking and blocking
- **Token-based Password Reset**: Secure password recovery system with expiring tokens
- **Enhanced Logging**: Comprehensive security event logging and monitoring
- **Email Notifications**: Automatic admin alerts for security events

### Technical Details
- Enhanced file intrusion detection excluding critical system files
- Improved email template system with logo integration and responsive design
- Professional uninstall process with complete system cleanup
- Advanced pro space configuration with redirect capabilities
- Enhanced security through directory obfuscation and intrusion detection

---

## [2.1.0] - 2024-12-19

### Added
- **🇫🇷 Complete French Admin Interface**: Fully translated admin panel with French navigation menu
- **🎨 Enhanced Admin Header**: User avatar, profile information, and French logout functionality
- **🔧 Interactive Maintenance Mode**: Direct control from admin panel with immediate effect
- **🏠 Visual Communication Homepage**: Professional front-office designed for communication agencies
- **🛒 Modular E-commerce**: Completely disableable shop module with conditional navigation
- **🖼️ Unified Logo System**: fav.png priority across all interfaces (admin, front, maintenance)
- **📱 Responsive Front Office**: Mobile-optimized layout with professional service presentation
- **⚙️ Theme Management Structure**: Foundation for customizable front-end themes

### Changed
- **Admin Navigation**: French menu with Général, Site vitrine, Espace client, Boutique e-commerce, Utilisateurs, Logs
- **Maintenance Page**: N3XT WEB branded with "Retour prochain" message and French content
- **Logo Management**: fav.png takes priority over assets/images/logo.png
- **Error Messages**: All admin error messages translated to French
- **System Version**: Updated to 2.1.0 to reflect major improvements

### Fixed
- **Install.php Auto-removal**: Automatically deleted after successful installation for security
- **Config.php Intrusion Detection**: No longer flagged as unexpected file during updates
- **GitHub API Error Handling**: Improved error messages in French with better timeout handling
- **White Page on Logo Upload**: Fixed logo modification process
- **Maintenance Mode Control**: Now toggleable directly from admin interface

### Technical Details
- Enhanced file intrusion detection excluding critical system files
- Improved GitHub API integration with better error reporting
- French language constants throughout admin interface
- Auto-removal security feature for installation files
- Responsive design improvements for mobile admin access

---

## [2.0.0] - 2024-08-28

### Added
- **N3XT WEB Integration**: Complete rebranding from N3XT Communication to N3XT WEB
- **Enhanced Installation Process**: Added back navigation capability throughout installation steps
- **Improved Database Management**: Default database prefix changed to 'n3xtweb_' (configurable during installation)
- **System Email Configuration**: Updated sender to 'N3XT WEB' with admin email as sender address
- **Complete Language Support**: French and English translations with installation-time language selection
- **Admin Details Collection**: Enhanced installation to collect admin login, email, name and first name
- **Fixed Direct Access Error**: Resolved "Direct access not allowed" error on index.php

### Changed
- **Branding Update**: All references changed from "N3XT Communication" to "N3XT WEB"
- **Database Defaults**: Changed default database name to 'n3xtweb_database' and user to 'n3xtweb_user'
- **Table Prefix**: Default table prefix changed from 'n3xt_' to 'n3xtweb_'
- **Installation Flow**: Added back navigation buttons on all installation steps (steps 2-5)
- **Email Templates**: Updated all email templates to use N3XT WEB branding
- **Admin Panel**: Updated all admin interface titles and headers to N3XT WEB

### Fixed
- **Index.php Access**: Fixed "Direct access not allowed" error by properly defining IN_N3XTWEB constant
- **Installation Redirection**: Improved redirection logic from index.php to install.php when system not installed
- **Email Verification**: Enhanced email verification process during installation
- **Back Navigation**: Added ability to go back during installation process with proper session cleanup

### Technical Details
- Enhanced security constant definition in index.php
- Improved session management for installation back navigation
- Updated language translation keys for consistent branding
- Refined database configuration templates
- Strengthened admin panel authentication and branding

---

## [1.0.0] - 2024-08-01

### Added
- Initial release of N3XT Communication system
- Modern installation interface with email verification
- Comprehensive admin panel with dashboard, updates, and backup functionality
- Multi-language support (French/English)
- Database table prefix support
- GitHub integration for automatic updates
- Complete backup and restore system
- Enhanced security features with captcha protection
- Mobile-responsive design
- OVH shared hosting compatibility

### Features
- 🎨 Modern UI with gradient backgrounds and glassmorphism effects
- 🌐 Complete French/English language support
- 📧 Email verification system for admin setup
- 🔐 Auto-generated secure admin credentials
- 🗄️ Database table prefix configuration
- 📁 Random back office directory generation
- 🚧 Maintenance mode by default
- 🔄 Improved redirection logic
- 💾 Comprehensive backup and restore functionality
- 🔄 Automated GitHub-based update system
- 🔒 Enhanced security with rate limiting and IP blocking
- 📱 Mobile-first responsive design
- 📊 Detailed logging and monitoring
- ⚙️ Easy installation wizard

---

*Authors: Julien Gauthier & Copilot*  
*Publisher: N3XT Communication*