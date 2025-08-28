# Changelog

All notable changes to N3XT WEB will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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