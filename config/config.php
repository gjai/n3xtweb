<?php
/**
 * N3XT WEB - Configuration Template
 * 
 * This file contains database and system configuration settings.
 * Values are replaced during installation.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

// Database Configuration
define('DB_HOST', 'nxtxyzylie618.mysql.db');
define('DB_NAME', 'nxtxyzylie618_db');
define('DB_USER', 'nxtxyzylie618_user');
define('DB_PASS', 'secure_password');
define('DB_CHARSET', 'utf8mb4');

// Table Prefix
define('TABLE_PREFIX', 'n3xtweb_');

// System Configuration
define('ROOT_PATH', dirname(__DIR__));
define('LOG_PATH', ROOT_PATH . '/logs');
define('BACKUP_PATH', ROOT_PATH . '/backups');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// Admin Configuration
define('ADMIN_PATH', ROOT_PATH . '/admin');

// System Settings
define('MAINTENANCE_MODE', false);
define('SYSTEM_VERSION', '2.0.0');

// Security Settings
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour
define('SESSION_LIFETIME', 86400); // 24 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('PASSWORD_MIN_LENGTH', 8);

// Security Features (disabled by default)
define('ENABLE_CAPTCHA', false);
define('ENABLE_LOGIN_ATTEMPTS_LIMIT', true);
define('ENABLE_IP_BLOCKING', true);
define('ENABLE_IP_TRACKING', true);
define('ENABLE_DATABASE_LOGGING', true);

// Email Configuration (optional)
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', '');
define('SMTP_FROM_NAME', 'N3XT WEB');