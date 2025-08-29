<?php
/**
 * N3XT WEB - Minimal Configuration
 * 
 * This file contains only essential database connection settings.
 * All other configuration is stored in the database and managed through the back office.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

// Essential Database Configuration - Only these settings are stored in files
// WARNING: Change these default values during installation!
define('DB_HOST', 'nxtxyzylie618.mysql.db');
define('DB_NAME', 'nxtxyzylie618_db');
define('DB_USER', 'nxtxyzylie618_user');
define('DB_PASS', 'secure_password'); // CHANGE THIS PASSWORD!
define('DB_CHARSET', 'utf8mb4');

// Table Prefix - Essential for database operations
define('TABLE_PREFIX', 'n3xtweb_');

// Load the database-driven configuration system
require_once dirname(__DIR__) . '/includes/Configuration.php';

// Define dynamic constants based on database configuration for backward compatibility
// These will be loaded from database or use sensible defaults

// System paths - these are filesystem dependent
define('ROOT_PATH', dirname(__DIR__));
define('LOG_PATH', ROOT_PATH . '/logs');
define('BACKUP_PATH', ROOT_PATH . '/backups');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// Try to set admin path from database or use fallback
$admin_path = Configuration::get('admin_path', ROOT_PATH . '/admin');
if (!defined('ADMIN_PATH')) {
    define('ADMIN_PATH', $admin_path);
}

// Load all other configuration from database with backward compatibility constants
$config = Configuration::getInstance();

// System Settings
if (!defined('MAINTENANCE_MODE')) define('MAINTENANCE_MODE', Configuration::get('maintenance_mode', false));
if (!defined('SYSTEM_VERSION')) define('SYSTEM_VERSION', Configuration::get('system_version', '2.0.0'));

// GitHub Integration
if (!defined('GITHUB_OWNER')) define('GITHUB_OWNER', Configuration::get('github_owner', 'gjai'));
if (!defined('GITHUB_REPO')) define('GITHUB_REPO', Configuration::get('github_repo', 'n3xtweb'));
if (!defined('GITHUB_API_URL')) define('GITHUB_API_URL', Configuration::get('github_api_url', 'https://api.github.com'));

// Security Settings
if (!defined('CSRF_TOKEN_LIFETIME')) define('CSRF_TOKEN_LIFETIME', Configuration::get('csrf_token_lifetime', 3600));
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', Configuration::get('session_lifetime', 86400));
if (!defined('ADMIN_SESSION_TIMEOUT')) define('ADMIN_SESSION_TIMEOUT', Configuration::get('admin_session_timeout', 14400));
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', Configuration::get('max_login_attempts', 5));
if (!defined('LOGIN_LOCKOUT_TIME')) define('LOGIN_LOCKOUT_TIME', Configuration::get('login_lockout_time', 900));
if (!defined('PASSWORD_MIN_LENGTH')) define('PASSWORD_MIN_LENGTH', Configuration::get('password_min_length', 8));

// Performance Settings
if (!defined('ENABLE_CACHING')) define('ENABLE_CACHING', Configuration::get('enable_caching', true));
if (!defined('CACHE_TTL_DEFAULT')) define('CACHE_TTL_DEFAULT', Configuration::get('cache_ttl_default', 3600));
if (!defined('CACHE_TTL_QUERIES')) define('CACHE_TTL_QUERIES', Configuration::get('cache_ttl_queries', 300));
if (!defined('ENABLE_GZIP')) define('ENABLE_GZIP', Configuration::get('enable_gzip', true));
if (!defined('ENABLE_ASSET_OPTIMIZATION')) define('ENABLE_ASSET_OPTIMIZATION', Configuration::get('enable_asset_optimization', true));

// Debug Settings
if (!defined('DEBUG')) define('DEBUG', Configuration::get('debug', false));
if (!defined('ENABLE_ERROR_DISPLAY')) define('ENABLE_ERROR_DISPLAY', Configuration::get('enable_error_display', false));
if (!defined('LOG_QUERIES')) define('LOG_QUERIES', Configuration::get('log_queries', false));

// Security Features
if (!defined('ENABLE_CAPTCHA')) define('ENABLE_CAPTCHA', Configuration::get('enable_captcha', false));
if (!defined('ENABLE_LOGIN_ATTEMPTS_LIMIT')) define('ENABLE_LOGIN_ATTEMPTS_LIMIT', Configuration::get('enable_login_attempts_limit', true));
if (!defined('ENABLE_IP_BLOCKING')) define('ENABLE_IP_BLOCKING', Configuration::get('enable_ip_blocking', true));
if (!defined('ENABLE_IP_TRACKING')) define('ENABLE_IP_TRACKING', Configuration::get('enable_ip_tracking', true));
if (!defined('ENABLE_DATABASE_LOGGING')) define('ENABLE_DATABASE_LOGGING', Configuration::get('enable_database_logging', true));
if (!defined('ENABLE_SECURITY_HEADERS')) define('ENABLE_SECURITY_HEADERS', Configuration::get('enable_security_headers', true));

// Email Configuration
if (!defined('SMTP_HOST')) define('SMTP_HOST', Configuration::get('smtp_host', ''));
if (!defined('SMTP_PORT')) define('SMTP_PORT', Configuration::get('smtp_port', 587));
if (!defined('SMTP_USER')) define('SMTP_USER', Configuration::get('smtp_user', ''));
if (!defined('SMTP_PASS')) define('SMTP_PASS', Configuration::get('smtp_pass', ''));
if (!defined('SMTP_FROM')) define('SMTP_FROM', Configuration::get('smtp_from', ''));
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', Configuration::get('smtp_from_name', 'N3XT WEB'));