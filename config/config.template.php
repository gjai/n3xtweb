<?php
/**
 * N3XT WEB - Configuration Template
 * 
 * This template is used during installation to generate the actual config.php file.
 * DO NOT MODIFY THIS FILE - it will be overwritten during updates.
 * The actual configuration is generated during installation.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

// Essential Database Configuration - Only these settings are stored in files
// These values will be replaced during installation
define('DB_HOST', '{{DB_HOST}}');
define('DB_NAME', '{{DB_NAME}}');
define('DB_USER', '{{DB_USER}}');
define('DB_PASS', '{{DB_PASS}}');
define('DB_CHARSET', 'utf8mb4');

// Table Prefix - Essential for database operations
define('TABLE_PREFIX', '{{TABLE_PREFIX}}');

// Load the database-driven configuration system (just the class definition)
require_once dirname(__DIR__) . '/includes/Configuration.php';

// Define dynamic constants based on database configuration for backward compatibility
// These will be loaded from database or use sensible defaults when Database is available

// System paths - these are filesystem dependent
define('ROOT_PATH', dirname(__DIR__));
define('LOG_PATH', ROOT_PATH . '/logs');
define('BACKUP_PATH', ROOT_PATH . '/backups');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// Set admin path - will be set during installation
define('ADMIN_PATH', ROOT_PATH . '/{{ADMIN_DIRECTORY}}');

// Configuration constants will be defined after Database class is loaded
// This prevents circular dependency issues