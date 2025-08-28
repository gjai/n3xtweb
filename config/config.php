<?php
/**
 * N3XT Communication - Configuration File
 * 
 * This file contains core configuration settings for the N3XT Communication system.
 * It should be placed outside the web root for maximum security.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    die('Direct access not allowed');
}

// Database configuration (to be configured during installation)
// For OVH shared hosting, use the database credentials provided in your OVH control panel
define('DB_HOST', 'localhost'); // Often 'mysql51-66.perso' or similar for OVH
define('DB_NAME', 'n3xtweb_database'); // Your database name from OVH
define('DB_USER', 'n3xtweb_user'); // Your database username from OVH
define('DB_PASS', 'secure_password'); // Your database password from OVH
define('DB_CHARSET', 'utf8mb4');

// PDO Database Connection Example for OVH Shared Hosting
// Uncomment and modify the following function to use PDO instead of mysqli
/*
function getPDOConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false, // Recommended for shared hosting
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false // May be needed for some OVH configurations
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Log error securely (don't expose database credentials)
        error_log('Database connection failed: ' . $e->getMessage());
        throw new Exception('Database connection failed. Please check your configuration.');
    }
}
*/

// Security settings
define('ADMIN_SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('CSRF_TOKEN_LIFETIME', 3600);

// File paths
define('ROOT_PATH', dirname(__FILE__));
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('BACKUP_PATH', ROOT_PATH . '/backups');
define('LOG_PATH', ROOT_PATH . '/logs');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// GitHub repository for updates
define('GITHUB_OWNER', 'gjai');
define('GITHUB_REPO', 'n3xtweb');
define('GITHUB_API_URL', 'https://api.github.com');

// System settings
define('SYSTEM_VERSION', '2.0.0');
define('MAINTENANCE_MODE', false);
define('DEBUG_MODE', false);

// File upload limits
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_BACKUP_EXTENSIONS', ['zip', 'sql']);

// Logging levels
define('LOG_LEVEL_ERROR', 1);
define('LOG_LEVEL_WARNING', 2);
define('LOG_LEVEL_INFO', 3);
define('LOG_LEVEL_DEBUG', 4);
define('DEFAULT_LOG_LEVEL', LOG_LEVEL_INFO);

// Captcha settings
define('CAPTCHA_LENGTH', 5);
define('CAPTCHA_WIDTH', 120);
define('CAPTCHA_HEIGHT', 40);

// Critical directories that should not be modified during updates
$CRITICAL_DIRECTORIES = [
    'backups',
    'logs',
    'config',
    'uploads'
];

// Files to exclude from updates
$UPDATE_EXCLUDE_FILES = [
    'config.php',
    '.htaccess',
    'robots.txt'
];
?>