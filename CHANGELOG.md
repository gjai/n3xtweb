# Changelog

All notable changes to N3XT WEB will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

# Changelog

All notable changes to N3XT WEB will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.0] - 2024-12-19

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