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
    if (defined('DB_HOST') && DB_HOST !== 'localhost') {
        // Find the admin directory
        $adminDir = 'admin';
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
            // Email verification
            if (isset($_POST['send_code'])) {
                $email = Security::sanitizeInput($_POST['email']);
                if (Security::validateEmail($email)) {
                    $code = EmailHelper::generateVerificationCode();
                    $_SESSION['verification_code'] = $code;
                    $_SESSION['verification_email'] = $email;
                    $_SESSION['verification_time'] = time();
                    
                    // In test mode or if email fails, show the code for testing
                    if (EmailHelper::sendVerificationEmail($email, $code, $language)) {
                        $success = LanguageHelper::get('email_sent', $language);
                    } else {
                        // For testing purposes, show the verification code
                        $success = LanguageHelper::get('email_sent', $language) . " (Test mode - Code: {$code})";
                    }
                } else {
                    $error = 'Invalid email address.';
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
            try {
                $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                
                // Store database config in session
                $_SESSION['db_config'] = [
                    'host' => $dbHost,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass,
                    'prefix' => $tablePrefix
                ];
                
                $step = 5;
            } catch (PDOException $e) {
                $error = 'Database connection failed: ' . $e->getMessage();
            }
            break;
            
        case 5:
            // Admin setup
            $adminUser = Security::sanitizeInput($_POST['admin_user']);
            $email = $_SESSION['verification_email'];
            
            if (!empty($adminUser) && !empty($email)) {
                // Generate admin password
                $adminPassword = InstallHelper::generateAdminPassword();
                
                // Generate random BO directory
                $boDirectory = InstallHelper::generateRandomBoDirectory();
                
                // Create database tables with prefix
                try {
                    $dbConfig = $_SESSION['db_config'];
                    createDatabaseTables($dbConfig);
                    createAdminUser($dbConfig, $adminUser, $adminPassword);
                    
                    // Create config file
                    $configContent = generateConfigFile($dbConfig, $boDirectory);
                    file_put_contents('config/config.php', $configContent);
                    
                    // Create BO directory
                    if (InstallHelper::createBoDirectory($boDirectory)) {
                        // Send admin credentials email
                        EmailHelper::sendAdminCredentials($email, $adminUser, $adminPassword, $boDirectory, $language);
                        
                        // Store BO directory in session
                        $_SESSION['bo_directory'] = $boDirectory;
                        $_SESSION['admin_username'] = $adminUser;
                        
                        // Auto-remove install.php for security
                        try {
                            if (file_exists(__FILE__)) {
                                unlink(__FILE__);
                                Logger::log('Install.php automatically removed after successful installation', LOG_LEVEL_INFO, 'system');
                            }
                        } catch (Exception $e) {
                            Logger::log('Failed to remove install.php: ' . $e->getMessage(), LOG_LEVEL_WARNING, 'system');
                        }
                        
                        $step = 6;
                    } else {
                        $error = 'Failed to create admin directory.';
                    }
                } catch (Exception $e) {
                    $error = 'Installation failed: ' . $e->getMessage();
                }
            } else {
                $error = 'Please provide a valid username.';
            }
            break;
    }
    }
}

/**
 * Generate configuration file content
 */
function generateConfigFile($dbConfig, $boDirectory) {
    $template = file_get_contents('config/config.php');
    
    $replacements = [
        "'localhost'" => "'{$dbConfig['host']}'",
        "'n3xtweb_database'" => "'{$dbConfig['name']}'",
        "'n3xtweb_user'" => "'{$dbConfig['user']}'",
        "'secure_password'" => "'{$dbConfig['pass']}'",
        "define('MAINTENANCE_MODE', false);" => "define('MAINTENANCE_MODE', true);", // Enable maintenance mode by default
        "define('ADMIN_PATH', ROOT_PATH . '/admin');" => "define('ADMIN_PATH', ROOT_PATH . '/{$boDirectory}');"
    ];
    
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

/**
 * Create database tables with prefix support
 */
function createDatabaseTables($dbConfig) {
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
    
    // Insert default settings
    $settings = [
        ['maintenance_mode', '1'], // Enable maintenance mode by default
        ['system_version', SYSTEM_VERSION],
        ['install_date', date('Y-m-d H:i:s')],
        ['table_prefix', $prefix]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
}

/**
 * Create admin user
 */
function createAdminUser($dbConfig, $username, $password) {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $prefix = $dbConfig['prefix'];
    $passwordHash = Security::hashPassword($password);
    $email = $_SESSION['verification_email'];
    
    $stmt = $pdo->prepare("INSERT INTO {$prefix}admin_users (username, password_hash, email) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE password_hash = ?, email = ?");
    $stmt->execute([$username, $passwordHash, $email, $passwordHash, $email]);
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
                    $logoPath = 'assets/images/logo.png';
                    if (file_exists($logoPath)): ?>
                        <img src="<?php echo $logoPath; ?>?v=<?php echo time(); ?>" 
                             alt="N3XT WEB" 
                             style="max-width: 80px; max-height: 60px;">
                    <?php else: ?>
                        🚀
                    <?php endif; ?>
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
                    <!-- Step 3: Email Verification -->
                    <h2><?php echo LanguageHelper::get('admin_setup', $language); ?></h2>
                    <p>Please provide your email address for administrator account setup.</p>
                    
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
                            </div>
                            
                            <div class="btn-row">
                                <button type="submit" name="back" class="btn btn-secondary">
                                    <?php echo LanguageHelper::get('previous', $language); ?>
                                </button>
                                <button type="submit" name="send_code" class="btn btn-primary">
                                    <?php echo LanguageHelper::get('send_code', $language); ?>
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
                                   value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_name" class="form-label">Database Name</label>
                            <input type="text" 
                                   id="db_name" 
                                   name="db_name" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'n3xtweb_database'); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_user" class="form-label">Database Username</label>
                            <input type="text" 
                                   id="db_user" 
                                   name="db_user" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>"
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
                    <!-- Step 5: Admin Account Setup -->
                    <h2><?php echo LanguageHelper::get('admin_setup', $language); ?></h2>
                    <p>Choose your administrator username. A secure password will be generated and sent to your email.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="step" value="5">
                        
                        <div class="form-group">
                            <label for="admin_user" class="form-label"><?php echo LanguageHelper::get('admin_username', $language); ?></label>
                            <input type="text" 
                                   id="admin_user" 
                                   name="admin_user" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['admin_user'] ?? 'admin'); ?>"
                                   required>
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>📧 Note:</strong> Your admin credentials will be sent to: 
                            <strong><?php echo htmlspecialchars($_SESSION['verification_email']); ?></strong>
                        </div>
                        
                        <div class="btn-row">
                            <button type="submit" name="back" class="btn btn-secondary">
                                <?php echo LanguageHelper::get('previous', $language); ?>
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <?php echo LanguageHelper::get('finish', $language); ?>
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
                                <li>Admin directory: <code><?php echo htmlspecialchars($_SESSION['bo_directory'] ?? 'admin'); ?></code></li>
                                <li>Remove or restrict access to this installation file</li>
                                <li>Enable HTTPS if possible</li>
                            </ul>
                        </div>
                        
                        <a href="<?php echo htmlspecialchars($_SESSION['bo_directory'] ?? 'admin'); ?>/login.php" class="btn btn-primary">
                            Access Admin Panel
                        </a>
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