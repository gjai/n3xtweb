<?php
/**
 * N3XT WEB - Modern Installation Interface
 * 
 * Enhanced installation system with email verification, language selection,
 * and modern UI design.
 */

// Enable error reporting for installation debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define security constant before including any files
define('IN_N3XTWEB', true);

// Start session for multi-step installation
session_start();

// Check if system is already installed
if (file_exists('config/config.php')) {
    $config = include 'config/config.php';
    if (defined('DB_HOST') && !(DB_HOST === 'nxtxyzylie618.mysql.db' && DB_NAME === 'nxtxyzylie618_db' && DB_USER === 'nxtxyzylie618_user')) {
        // Find the back office directory
        $adminDir = 'bo'; // Default fallback
        if (isset($_SESSION['bo_directory']) && file_exists($_SESSION['bo_directory'])) {
            $adminDir = $_SESSION['bo_directory'];
        }
        header('Location: ' . $adminDir . '/login.php');
        exit;
    }
}

require_once 'includes/functions.php';

// Define system version if not already defined
if (!defined('SYSTEM_VERSION')) {
    define('SYSTEM_VERSION', '2.0.0');
}

// Get language from session or default to French
$language = $_SESSION['install_language'] ?? 'fr';

// Initialize variables
$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Handle special parameters
if (isset($_GET['reset'])) {
    InstallHelper::resetInstallationState();
    $success = 'Installation state has been reset. You can start fresh.';
    $step = 1;
}

if (isset($_GET['finish']) && isset($_SESSION['installation_ready'])) {
    // User clicked to access admin panel - now we can safely remove install.php
    try {
        if (file_exists(__FILE__)) {
            unlink(__FILE__);
            Logger::log('Install.php removed after user confirmed installation completion', LOG_LEVEL_INFO, 'system');
        }
        // Redirect to admin panel
        $adminDir = $_SESSION['bo_directory'] ?? 'bo';
        header('Location: ' . $adminDir . '/login.php');
        exit;
    } catch (Exception $e) {
        Logger::log('Failed to remove install.php on finish: ' . $e->getMessage(), LOG_LEVEL_WARNING, 'system');
        $adminDir = $_SESSION['bo_directory'] ?? 'bo';
        header('Location: ' . $adminDir . '/login.php');
        exit;
    }
}

// Validate installation session state for steps > 1
if ($step > 1 && !InstallHelper::validateInstallationState($step)) {
    $error = 'Installation session expired or invalid. Please restart the installation.';
    $step = 1;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (int) ($_POST['step'] ?? 1);
    
    // Handle back navigation
    if (isset($_POST['back'])) {
        $step = max(1, $step - 1);
        // Clear step-specific session data when going back
        if ($step == 2) {
            unset($_SESSION['verification_code']);
            unset($_SESSION['verification_email']);
            unset($_SESSION['verification_time']);
        } elseif ($step == 3) {
            unset($_SESSION['db_config']);
        }
    } else {
        switch ($step) {
        case 1:
            // Language selection
            $language = $_POST['language'] ?? 'fr';
            $_SESSION['install_language'] = $language;
            $step = 2;
            break;
            
        case 2:
            // Environment check passed, go to step 3
            $step = 3;
            break;
            
        case 3:
            // Admin information and email verification
            if (isset($_POST['send_code'])) {
                $email = Security::sanitizeInput($_POST['email']);
                $login = Security::sanitizeInput($_POST['login']);
                $firstName = Security::sanitizeInput($_POST['first_name']);
                $lastName = Security::sanitizeInput($_POST['last_name']);
                
                // Validate all fields
                $errors = [];
                if (!Security::validateEmail($email)) {
                    $errors[] = 'Invalid email address format.';
                }
                if (empty($login) || strlen($login) < 3) {
                    $errors[] = 'Login must be at least 3 characters long.';
                }
                if (empty($firstName) || strlen($firstName) < 2) {
                    $errors[] = 'First name must be at least 2 characters long.';
                }
                if (empty($lastName) || strlen($lastName) < 2) {
                    $errors[] = 'Last name must be at least 2 characters long.';
                }
                
                if (empty($errors)) {
                    $code = EmailHelper::generateVerificationCode();
                    $_SESSION['verification_code'] = $code;
                    $_SESSION['verification_email'] = $email;
                    $_SESSION['admin_login'] = $login;
                    $_SESSION['admin_first_name'] = $firstName;
                    $_SESSION['admin_last_name'] = $lastName;
                    $_SESSION['verification_time'] = time();
                    
                    // In test mode or if email fails, show the code for testing
                    if (EmailHelper::sendVerificationEmail($email, $code, $language)) {
                        $success = LanguageHelper::get('email_sent', $language);
                    } else {
                        // For testing purposes, show the verification code
                        $success = LanguageHelper::get('email_sent', $language) . " (Test mode - Code: {$code})";
                    }
                } else {
                    $error = implode(' ', $errors);
                }
            } elseif (isset($_POST['verify_code'])) {
                $inputCode = Security::sanitizeInput($_POST['verification_code']);
                $sessionCode = $_SESSION['verification_code'] ?? '';
                $verificationTime = $_SESSION['verification_time'] ?? 0;
                
                // Code expires after 15 minutes
                if (time() - $verificationTime > 900) {
                    unset($_SESSION['verification_code'], $_SESSION['verification_time']);
                    $error = LanguageHelper::get('invalid_code', $language);
                } elseif ($inputCode === $sessionCode) {
                    $step = 4;
                } else {
                    $error = LanguageHelper::get('invalid_code', $language);
                }
            }
            break;
            
        case 4:
            // Database configuration
            $dbHost = Security::sanitizeInput($_POST['db_host']);
            $dbName = Security::sanitizeInput($_POST['db_name']);
            $dbUser = Security::sanitizeInput($_POST['db_user']);
            $dbPass = $_POST['db_pass']; // Don't sanitize password
            $tablePrefix = Security::sanitizeInput($_POST['table_prefix'] ?? '');
            
            // Test database connection
            $testResult = Database::testConnection($dbHost, $dbName, $dbUser, $dbPass);
            
            if ($testResult['success']) {
                // Store database config in session
                $_SESSION['db_config'] = [
                    'host' => $dbHost,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass,
                    'prefix' => $tablePrefix
                ];
                
                $step = 5;
            } else {
                $error = $testResult['message'];
            }
            break;
            
        case 5:
            // Installation completion - use data from session
            $email = $_SESSION['verification_email'];
            $adminUser = $_SESSION['admin_login'];
            $firstName = $_SESSION['admin_first_name'];
            $lastName = $_SESSION['admin_last_name'];
            
            if (!empty($adminUser) && !empty($email) && !empty($firstName) && !empty($lastName)) {
                // Generate admin password
                $adminPassword = InstallHelper::generateAdminPassword();
                
                // Generate random BO directory
                $boDirectory = InstallHelper::generateRandomBoDirectory();
                
                // Create database tables with prefix
                try {
                    $dbConfig = $_SESSION['db_config'];
                    createDatabaseTables($dbConfig, $language);
                    createAdminUser($dbConfig, $adminUser, $adminPassword, $email, $firstName, $lastName, $language);
                    
                    // Create config file
                    $configContent = generateConfigFile($dbConfig, $boDirectory);
                    file_put_contents('config/config.php', $configContent);
                    
                    // Create installation marker
                    file_put_contents('config/.installed', date('Y-m-d H:i:s'));
                    
                    // Create BO directory
                    if (InstallHelper::createBoDirectory($boDirectory)) {
                        // Clean up installation directories
                        $cleanupResult = InstallHelper::cleanupInstallation();
                        if (!$cleanupResult['success']) {
                            Logger::log('Some cleanup operations failed: ' . implode(', ', $cleanupResult['errors']), LOG_LEVEL_WARNING, 'install');
                        }
                        
                        // Send admin credentials email
                        EmailHelper::sendAdminCredentials($email, $adminUser, $adminPassword, $boDirectory, $language, $firstName, $lastName);
                        
                        // Store BO directory in session
                        $_SESSION['bo_directory'] = $boDirectory;
                        $_SESSION['admin_username'] = $adminUser;
                        
                        // Log successful installation
                        Logger::log("Installation completed successfully for admin: {$firstName} {$lastName} ({$adminUser})", LOG_LEVEL_INFO, 'install');
                        
                        // Mark installation as ready for completion
                        $_SESSION['installation_ready'] = true;
                        
                        $step = 6;
                    } else {
                        $error = 'Failed to create admin directory.';
                    }
                } catch (Exception $e) {
                    $error = 'Installation failed: ' . $e->getMessage();
                    Logger::log('Installation failed: ' . $e->getMessage(), LOG_LEVEL_ERROR, 'install');
                }
            } else {
                $error = 'Missing admin information. Please restart installation.';
            }
            break;
    }
    }
}

/**
 * Generate configuration file content
 */
function generateConfigFile($dbConfig, $boDirectory) {
    $template = file_get_contents('config/config.template.php');
    
    $replacements = [
        '{{DB_HOST}}' => $dbConfig['host'],
        '{{DB_NAME}}' => $dbConfig['name'],
        '{{DB_USER}}' => $dbConfig['user'],
        '{{DB_PASS}}' => $dbConfig['pass'],
        '{{TABLE_PREFIX}}' => $dbConfig['prefix'],
        '{{ADMIN_DIRECTORY}}' => $boDirectory
    ];
    
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

/**
 * Create database tables with prefix support
 */
function createDatabaseTables($dbConfig, $language = 'fr') {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $prefix = $dbConfig['prefix'];
    
    // Create admin users table
    $sql = "
        CREATE TABLE IF NOT EXISTS {$prefix}admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            language VARCHAR(2) DEFAULT 'fr',
            reset_token VARCHAR(64) NULL,
            reset_token_expiry TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            active BOOLEAN DEFAULT TRUE
        )
    ";
    $pdo->exec($sql);
    
    // Create system settings table
    $sql = "
        CREATE TABLE IF NOT EXISTS {$prefix}system_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($sql);
    
    // Create access logs table for database logging
    $sql = "
        CREATE TABLE IF NOT EXISTS {$prefix}access_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50),
            ip_address VARCHAR(45),
            user_agent TEXT,
            action VARCHAR(100),
            status ENUM('SUCCESS', 'FAILED') NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_ip (ip_address),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        )
    ";
    $pdo->exec($sql);
    
    // Create login attempts table for tracking attempts
    $sql = "
        CREATE TABLE IF NOT EXISTS {$prefix}login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(50),
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success BOOLEAN DEFAULT FALSE,
            failure_reason VARCHAR(255),
            user_agent TEXT,
            blocked_until TIMESTAMP NULL,
            INDEX idx_ip_time (ip_address, attempt_time),
            INDEX idx_username (username),
            INDEX idx_blocked (blocked_until)
        )
    ";
    $pdo->exec($sql);
    
    // Insert default settings - comprehensive configuration system
    $settings = [
        // System Configuration
        ['maintenance_mode', '1'], // Enable maintenance mode by default
        ['system_version', SYSTEM_VERSION],
        ['install_date', date('Y-m-d H:i:s')],
        ['table_prefix', $prefix],
        ['system_language', $language], // Store chosen language as system default
        ['root_path', dirname(__DIR__)],
        ['log_path', dirname(__DIR__) . '/logs'],
        ['backup_path', dirname(__DIR__) . '/backups'],
        ['upload_path', dirname(__DIR__) . '/uploads'],
        ['admin_path', dirname(__DIR__) . '/bo'], // Will be updated after BO directory creation
        
        // Security Settings
        ['csrf_token_lifetime', '3600'],
        ['session_lifetime', '86400'],
        ['admin_session_timeout', '14400'],
        ['max_login_attempts', '5'],
        ['login_lockout_time', '900'],
        ['password_min_length', '8'],
        ['enable_captcha', '0'],
        ['enable_login_attempts_limit', '1'], 
        ['enable_ip_blocking', '1'],
        ['enable_ip_tracking', '1'],
        ['enable_database_logging', '1'],
        ['enable_security_headers', '1'],
        
        // Performance Settings
        ['enable_caching', '1'],
        ['cache_ttl_default', '3600'],
        ['cache_ttl_queries', '300'],
        ['enable_gzip', '1'],
        ['enable_asset_optimization', '1'],
        
        // Debug Settings (disabled by default in production)
        ['debug', '0'],
        ['enable_error_display', '0'],
        ['log_queries', '0'],
        
        // GitHub Integration
        ['github_owner', 'gjai'],
        ['github_repo', 'n3xtweb'],
        ['github_api_url', 'https://api.github.com'],
        
        // Email Configuration
        ['smtp_host', ''],
        ['smtp_port', '587'],
        ['smtp_user', ''],
        ['smtp_pass', ''],
        ['smtp_from', ''],
        ['smtp_from_name', 'N3XT WEB'],
        
        // Theme/CSS Configuration
        ['theme_primary_color', '#667eea'],
        ['theme_secondary_color', '#764ba2'],
        ['theme_success_color', '#27ae60'],
        ['theme_danger_color', '#e74c3c'],
        ['theme_warning_color', '#f39c12'],
        ['theme_info_color', '#3498db'],
        ['theme_font_family', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif'],
        ['theme_font_size', '14px'],
        ['theme_border_radius', '8px'],
        
        // Site Configuration
        ['site_name', 'N3XT WEB'],
        ['site_description', 'Professional web management system'],
        ['site_logo', ''],
        ['site_favicon', '/fav.png'],
        ['site_language', 'fr'],
        ['site_timezone', 'Europe/Paris']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
}

/**
 * Create admin user
 */
function createAdminUser($dbConfig, $username, $password, $email, $firstName, $lastName, $language) {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $prefix = $dbConfig['prefix'];
    $passwordHash = Security::hashPassword($password);
    
    $stmt = $pdo->prepare("INSERT INTO {$prefix}admin_users (username, password_hash, email, first_name, last_name, language, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE password_hash = ?, email = ?, first_name = ?, last_name = ?, language = ?");
    $stmt->execute([$username, $passwordHash, $email, $firstName, $lastName, $language, $passwordHash, $email, $firstName, $lastName, $language]);
}

/**
 * Check system requirements
 */
function checkSystemRequirements() {
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'GD Extension' => extension_loaded('gd'),
        'ZIP Extension' => extension_loaded('zip'),
        'OpenSSL Extension' => extension_loaded('openssl'),
        'Mail Function' => function_exists('mail'),
        'Config Directory Writable' => is_writable('config'),
        'Logs Directory Writable' => is_writable('logs') || !file_exists('logs'),
        'Backups Directory Writable' => is_writable('backups') || !file_exists('backups')
    ];
    
    return $requirements;
}

$requirements = checkSystemRequirements();
$allRequirementsMet = !in_array(false, $requirements);
?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="fav.png">
    <title><?php echo LanguageHelper::get('installation_title', $language); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
        }
        
        .install-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            position: relative;
        }
        
        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        
        .install-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }
        
        .logo {
            font-size: 48px;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .install-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        
        .install-subtitle {
            font-size: 16px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .install-body {
            padding: 40px 30px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 10px;
        }
        
        .step-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e0e0e0;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .step-dot.active {
            background: #667eea;
            transform: scale(1.2);
        }
        
        .step-dot.completed {
            background: #27ae60;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-help {
            font-size: 13px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-block {
            width: 100%;
            text-align: center;
        }
        
        .btn-row {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-row .btn {
            flex: 1;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffeaa7;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 2px solid #bee5eb;
        }
        
        .summary-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .summary-card h3 {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 18px;
        }
        
        .summary-item {
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .summary-item strong {
            display: inline-block;
            width: 120px;
            color: #495057;
        }
        
        .language-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        
        .language-option {
            padding: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .language-option:hover {
            border-color: #667eea;
            background: white;
        }
        
        .language-option.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        
        .language-flag {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .requirements-list {
            list-style: none;
            margin: 20px 0;
        }
        
        .requirements-list li {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .requirements-list li:last-child {
            border-bottom: none;
        }
        
        .req-icon {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .req-icon.success {
            color: #27ae60;
        }
        
        .req-icon.error {
            color: #e74c3c;
        }
        
        .verification-code-input {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 3px;
            font-family: 'Courier New', monospace;
        }
        
        .installation-complete {
            text-align: center;
            padding: 20px;
        }
        
        .success-icon {
            font-size: 72px;
            color: #27ae60;
            margin-bottom: 20px;
            animation: bounce 1s ease-in-out;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .install-container {
                padding: 10px;
            }
            
            .install-card {
                border-radius: 15px;
            }
            
            .install-header {
                padding: 30px 20px;
            }
            
            .install-title {
                font-size: 24px;
            }
            
            .install-body {
                padding: 30px 20px;
            }
            
            .btn-row {
                flex-direction: column;
            }
            
            .language-selector {
                grid-template-columns: 1fr;
            }
        }
        
        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-card fade-in">
            <div class="install-header">
                <div class="logo">
                    <?php 
                    $logoPath = 'fav.png';
                    if (file_exists($logoPath)): ?>
                        <img src="<?php echo $logoPath; ?>?v=<?php echo time(); ?>" 
                             alt="N3XT WEB" 
                             style="max-width: 80px; max-height: 60px;">
                    <?php else:
                        $logoPath = 'assets/images/logo.png';
                        if (file_exists($logoPath)): ?>
                            <img src="<?php echo $logoPath; ?>?v=<?php echo time(); ?>" 
                                 alt="N3XT WEB" 
                                 style="max-width: 80px; max-height: 60px;">
                        <?php else: ?>
                            🚀
                        <?php endif; 
                    endif; ?>
                </div>
                <h1 class="install-title">N3XT WEB</h1>
                <p class="install-subtitle"><?php echo LanguageHelper::get('installation_title', $language); ?></p>
            </div>
            
            <div class="install-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <div class="step-dot <?php echo $i < $step ? 'completed' : ($i == $step ? 'active' : ''); ?>"></div>
                    <?php endfor; ?>
                </div>
                
                <!-- Error/Success Messages -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <strong>❌ Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <strong>✅ Success:</strong> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Step Content -->
                <?php if ($step == 1): ?>
                    <!-- Step 1: Language Selection -->
                    <h2><?php echo LanguageHelper::get('welcome', $language); ?></h2>
                    <p><?php echo LanguageHelper::get('choose_language', $language); ?></p>
                    
                    <form method="POST" id="languageForm">
                        <input type="hidden" name="step" value="1">
                        <input type="hidden" name="language" id="selectedLanguage" value="<?php echo $language; ?>">
                        
                        <div class="language-selector">
                            <div class="language-option <?php echo $language === 'fr' ? 'selected' : ''; ?>" data-lang="fr">
                                <div class="language-flag">🇫🇷</div>
                                <div><?php echo LanguageHelper::get('french', $language); ?></div>
                            </div>
                            <div class="language-option <?php echo $language === 'en' ? 'selected' : ''; ?>" data-lang="en">
                                <div class="language-flag">🇬🇧</div>
                                <div><?php echo LanguageHelper::get('english', $language); ?></div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <?php echo LanguageHelper::get('continue', $language); ?>
                        </button>
                    </form>
                    
                <?php elseif ($step == 2): ?>
                    <!-- Step 2: System Requirements -->
                    <h2><?php echo LanguageHelper::get('system_requirements', $language); ?></h2>
                    <p>Checking system compatibility...</p>
                    
                    <ul class="requirements-list">
                        <?php foreach ($requirements as $requirement => $met): ?>
                            <li>
                                <span class="req-icon <?php echo $met ? 'success' : 'error'; ?>">
                                    <?php echo $met ? '✅' : '❌'; ?>
                                </span>
                                <?php echo $requirement; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <?php if ($allRequirementsMet): ?>
                        <div class="alert alert-success">
                            <strong>✅ Great!</strong> All system requirements are met.
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="step" value="2">
                            <div class="btn-row">
                                <button type="submit" name="back" class="btn btn-secondary">
                                    <?php echo LanguageHelper::get('previous', $language); ?>
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <?php echo LanguageHelper::get('next', $language); ?>
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <strong>❌ Error:</strong> Please resolve the missing requirements before continuing.
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($step == 3): ?>
                    <!-- Step 3: Admin Information and Email Verification -->
                    <h2><?php echo LanguageHelper::get('admin_info_title', $language); ?></h2>
                    <p><?php echo LanguageHelper::get('admin_info_subtitle', $language); ?></p>
                    
                    <?php if (!isset($_SESSION['verification_code'])): ?>
                        <form method="POST">
                            <input type="hidden" name="step" value="3">
                            
                            <div class="form-group">
                                <label for="email" class="form-label"><?php echo LanguageHelper::get('email_address', $language); ?></label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       required>
                                <div class="form-help"><?php echo LanguageHelper::get('email_help', $language); ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="login" class="form-label"><?php echo LanguageHelper::get('admin_username', $language); ?></label>
                                <input type="text" 
                                       id="login" 
                                       name="login" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>"
                                       minlength="3"
                                       pattern="[a-zA-Z0-9_-]+"
                                       required>
                                <div class="form-help"><?php echo LanguageHelper::get('login_help', $language); ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="first_name" class="form-label"><?php echo LanguageHelper::get('first_name', $language); ?></label>
                                <input type="text" 
                                       id="first_name" 
                                       name="first_name" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                       minlength="2"
                                       required>
                                <div class="form-help"><?php echo LanguageHelper::get('first_name_help', $language); ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name" class="form-label"><?php echo LanguageHelper::get('last_name', $language); ?></label>
                                <input type="text" 
                                       id="last_name" 
                                       name="last_name" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                       minlength="2"
                                       required>
                                <div class="form-help"><?php echo LanguageHelper::get('last_name_help', $language); ?></div>
                            </div>
                            
                            <div class="btn-row">
                                <button type="submit" name="back" class="btn btn-secondary">
                                    <?php echo LanguageHelper::get('previous', $language); ?>
                                </button>
                                <button type="submit" name="send_code" class="btn btn-primary">
                                    <?php echo LanguageHelper::get('verify_and_continue', $language); ?>
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p><?php echo LanguageHelper::get('email_sent', $language); ?></p>
                        
                        <form method="POST">
                            <input type="hidden" name="step" value="3">
                            
                            <div class="form-group">
                                <label for="verification_code" class="form-label"><?php echo LanguageHelper::get('verification_code', $language); ?></label>
                                <input type="text" 
                                       id="verification_code" 
                                       name="verification_code" 
                                       class="form-control verification-code-input"
                                       maxlength="6"
                                       required>
                            </div>
                            
                            <div class="btn-row">
                                <button type="submit" name="back" class="btn btn-secondary">
                                    <?php echo LanguageHelper::get('previous', $language); ?>
                                </button>
                                <button type="submit" name="verify_code" class="btn btn-primary">
                                    <?php echo LanguageHelper::get('verify_code', $language); ?>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                <?php elseif ($step == 4): ?>
                    <!-- Step 4: Database Configuration -->
                    <h2><?php echo LanguageHelper::get('database_configuration', $language); ?></h2>
                    <p>Configure your MySQL database connection.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="step" value="4">
                        
                        <div class="form-group">
                            <label for="db_host" class="form-label">Database Host</label>
                            <input type="text" 
                                   id="db_host" 
                                   name="db_host" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'nxtxyzylie618.mysql.db'); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_name" class="form-label">Database Name</label>
                            <input type="text" 
                                   id="db_name" 
                                   name="db_name" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'nxtxyzylie618'); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_user" class="form-label">Database Username</label>
                            <input type="text" 
                                   id="db_user" 
                                   name="db_user" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'nxtxyzylie618'); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_pass" class="form-label">Database Password</label>
                            <input type="password" 
                                   id="db_pass" 
                                   name="db_pass" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="table_prefix" class="form-label"><?php echo LanguageHelper::get('table_prefix', $language); ?></label>
                            <input type="text" 
                                   id="table_prefix" 
                                   name="table_prefix" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['table_prefix'] ?? 'n3xtweb_'); ?>"
                                   placeholder="n3xtweb_">
                            <div class="form-help"><?php echo LanguageHelper::get('table_prefix_help', $language); ?></div>
                        </div>
                        
                        <div class="btn-row">
                            <button type="submit" name="back" class="btn btn-secondary">
                                <?php echo LanguageHelper::get('previous', $language); ?>
                            </button>
                            <button type="submit" class="btn btn-primary">Test Connection</button>
                        </div>
                    </form>
                    
                <?php elseif ($step == 5): ?>
                    <!-- Step 5: Installation Summary and Completion -->
                    <h2><?php echo LanguageHelper::get('installation_complete', $language); ?></h2>
                    <p>Review your installation details and complete the setup.</p>
                    
                    <div class="summary-card">
                        <h3>Installation Summary</h3>
                        <div class="summary-item">
                            <strong>Admin Email:</strong> <?php echo htmlspecialchars($_SESSION['verification_email']); ?>
                        </div>
                        <div class="summary-item">
                            <strong>Admin Login:</strong> <?php echo htmlspecialchars($_SESSION['admin_login']); ?>
                        </div>
                        <div class="summary-item">
                            <strong>Admin Name:</strong> <?php echo htmlspecialchars($_SESSION['admin_first_name'] . ' ' . $_SESSION['admin_last_name']); ?>
                        </div>
                        <div class="summary-item">
                            <strong>Language:</strong> <?php echo $language === 'fr' ? 'Français' : 'English'; ?>
                        </div>
                        <div class="summary-item">
                            <strong>Database:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['name']); ?>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="step" value="5">
                        
                        <div class="alert alert-info">
                            <strong>📧 Note:</strong> Your admin credentials will be sent to your email address after installation.
                        </div>
                        
                        <div class="btn-row">
                            <button type="submit" name="back" class="btn btn-secondary">
                                <?php echo LanguageHelper::get('previous', $language); ?>
                            </button>
                            <button type="submit" class="btn btn-primary">
                                Complete Installation
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($step == 6): ?>
                    <!-- Step 6: Installation Complete -->
                    <div class="installation-complete">
                        <div class="success-icon">🎉</div>
                        <h2><?php echo LanguageHelper::get('installation_success', $language); ?></h2>
                        
                        <div class="alert alert-success">
                            <p><?php echo LanguageHelper::get('check_email', $language); ?></p>
                            <p><?php echo LanguageHelper::get('maintenance_mode_enabled', $language); ?></p>
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>🔒 Security Information:</strong>
                            <ul style="text-align: left; margin: 10px 0;">
                                <li>Back Office directory: <code><?php echo htmlspecialchars($_SESSION['bo_directory'] ?? 'bo'); ?></code></li>
                                <li>Installation file will be automatically removed when you access the admin panel</li>
                                <li>Enable HTTPS if possible</li>
                            </ul>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <a href="?finish=1" class="btn btn-primary">
                                Access Admin Panel & Complete Installation
                            </a>
                            <br><br>
                            <a href="?reset=1" class="btn btn-secondary" style="background-color: #6c757d;">
                                Reset Installation (if needed)
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Language selection
        document.querySelectorAll('.language-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.language-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('selectedLanguage').value = this.dataset.lang;
            });
        });
        
        // Verification code auto-format
        const codeInput = document.getElementById('verification_code');
        if (codeInput) {
            codeInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').substring(0, 6);
            });
        }
        
        // Auto-submit language form when selection changes
        document.addEventListener('DOMContentLoaded', function() {
            const languageOptions = document.querySelectorAll('.language-option');
            const languageForm = document.getElementById('languageForm');
            
            if (languageOptions.length && languageForm) {
                languageOptions.forEach(option => {
                    option.addEventListener('click', function() {
                        setTimeout(() => {
                            languageForm.submit();
                        }, 300);
                    });
                });
            }
        });
        
        // Add loading animation to buttons on submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML += '<span class="loading"></span>';
                    submitBtn.disabled = true;
                }
            });
        });
    </script>
</body>
</html>