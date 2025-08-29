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

// Load the database-driven configuration system (just the class definition)
require_once dirname(__DIR__) . '/includes/Configuration.php';

// Define dynamic constants based on database configuration for backward compatibility
// These will be loaded from database or use sensible defaults when Database is available

// System paths - these are filesystem dependent
define('ROOT_PATH', dirname(__DIR__));
define('LOG_PATH', ROOT_PATH . '/logs');
define('BACKUP_PATH', ROOT_PATH . '/backups');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// Set admin path fallback (will be updated when configuration loads)
if (!defined('ADMIN_PATH')) {
    define('ADMIN_PATH', ROOT_PATH . '/admin');
}

// Configuration constants will be defined after Database class is loaded
// This prevents circular dependency issues