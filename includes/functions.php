<?php
/**
 * N3XT WEB - Core Functions
 * 
 * This file contains core utility functions used throughout the system.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

// Define security constant
define('N3XT_SECURE', true);

// Load configuration
require_once dirname(__DIR__) . '/config/config.php';

/**
 * Database connection using PDO with prepared statements
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check configuration. Error: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Test database connection
     */
    public static function testConnection($host, $name, $user, $pass) {
        try {
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $pdo = new PDO($dsn, $user, $pass, $options);
            
            // Test basic query
            $pdo->query("SELECT 1");
            
            return ['success' => true, 'message' => 'Connection successful'];
        } catch (PDOException $e) {
            $errorCode = $e->getCode();
            $message = "Connection failed: ";
            
            switch ($errorCode) {
                case 1045:
                    $message .= "Access denied. Please check username and password.";
                    break;
                case 1049:
                    $message .= "Unknown database '{$name}'. Please check database name.";
                    break;
                case 2002:
                    $message .= "Can't connect to MySQL server on '{$host}'. Please check hostname.";
                    break;
                case 2005:
                    $message .= "Unknown MySQL server host '{$host}'. Please check hostname.";
                    break;
                default:
                    $message .= $e->getMessage();
            }
            
            return ['success' => false, 'message' => $message, 'code' => $errorCode];
        }
    }
    
    /**
     * Execute a prepared statement with parameters
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Fetch single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
}

/**
 * Security helper functions
 */
class Security {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_time']) || 
            (time() - $_SESSION['csrf_time']) > CSRF_TOKEN_LIFETIME) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_time'])) {
            return false;
        }
        
        if ((time() - $_SESSION['csrf_time']) > CSRF_TOKEN_LIFETIME) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_time']);
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize input
     */
    public static function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Generate secure password hash
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate random string
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Set security headers
     */
    public static function setSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; connect-src \'self\';');
    }
}

/**
 * Logging system
 */
class Logger {
    
    /**
     * Write log entry
     */
    public static function log($message, $level = LOG_LEVEL_INFO, $logFile = 'system') {
        if ($level > DEFAULT_LOG_LEVEL) {
            return;
        }
        
        $levelNames = [
            LOG_LEVEL_ERROR => 'ERROR',
            LOG_LEVEL_WARNING => 'WARNING',
            LOG_LEVEL_INFO => 'INFO',
            LOG_LEVEL_DEBUG => 'DEBUG'
        ];
        
        $levelName = $levelNames[$level] ?? 'UNKNOWN';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $logEntry = "[{$timestamp}] [{$levelName}] IP: {$ip} | {$message} | User-Agent: {$userAgent}" . PHP_EOL;
        
        $logPath = LOG_PATH . "/{$logFile}.log";
        
        // Create logs directory if it doesn't exist
        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0755, true);
        }
        
        file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get table prefix from configuration
     */
    public static function getTablePrefix() {
        static $prefix = null;
        if ($prefix === null) {
            try {
                // Try to get from environment/session first (during installation)
                if (isset($_SESSION['db_config']['prefix'])) {
                    $prefix = $_SESSION['db_config']['prefix'];
                } elseif (defined('TABLE_PREFIX')) {
                    $prefix = TABLE_PREFIX;
                } else {
                    // Try to get from database - check if we can connect first
                    try {
                        $db = Database::getInstance();
                        // Try with default prefix first
                        $result = $db->fetchOne("SELECT setting_value FROM n3xtweb_system_settings WHERE setting_key = 'table_prefix' LIMIT 1");
                        $prefix = $result ? $result['setting_value'] : 'n3xtweb_';
                    } catch (Exception $e) {
                        // If database connection fails, fall back to default
                        $prefix = 'n3xtweb_';
                    }
                }
            } catch (Exception $e) {
                $prefix = 'n3xtweb_'; // Default fallback
            }
        }
        return $prefix;
    }
    
    /**
     * Log access attempt - now stores in database
     */
    public static function logAccess($username, $success, $notes = '') {
        try {
            $db = Database::getInstance();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $status = $success ? 'SUCCESS' : 'FAILED';
            $prefix = self::getTablePrefix();
            
            // Store in access_logs table
            $db->execute(
                "INSERT INTO {$prefix}access_logs (username, ip_address, user_agent, action, status, notes) VALUES (?, ?, ?, ?, ?, ?)",
                [$username, $ip, $userAgent, 'login', $status, $notes]
            );
            
            // Also store in login_attempts table for tracking
            $db->execute(
                "INSERT INTO {$prefix}login_attempts (ip_address, username, success, failure_reason, user_agent) VALUES (?, ?, ?, ?, ?)",
                [$ip, $username, $success ? 1 : 0, $success ? null : $notes, $userAgent]
            );
            
        } catch (Exception $e) {
            // Fallback to file logging if database fails
            $status = $success ? 'SUCCESS' : 'FAILED';
            $message = "Login attempt - Username: {$username} | Status: {$status}";
            if ($notes) {
                $message .= " | Notes: {$notes}";
            }
            self::log($message, LOG_LEVEL_INFO, 'access');
        }
    }
    
    /**
     * Log update activity
     */
    public static function logUpdate($action, $details = '') {
        $message = "Update action: {$action}";
        if ($details) {
            $message .= " | Details: {$details}";
        }
        
        self::log($message, LOG_LEVEL_INFO, 'update');
    }
}

/**
 * Security settings management
 */
class SecuritySettings {
    
    /**
     * Get security setting from database
     */
    public static function getSetting($key, $default = false) {
        try {
            $db = Database::getInstance();
            $prefix = Logger::getTablePrefix();
            $result = $db->fetchOne(
                "SELECT setting_value FROM {$prefix}system_settings WHERE setting_key = ?",
                [$key]
            );
            return $result ? (bool)$result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    
    /**
     * Set security setting in database
     */
    public static function setSetting($key, $value) {
        try {
            $db = Database::getInstance();
            $prefix = Logger::getTablePrefix();
            $db->execute(
                "INSERT INTO {$prefix}system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
                [$key, $value ? '1' : '0', $value ? '1' : '0']
            );
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if captcha is enabled
     */
    public static function isCaptchaEnabled() {
        return self::getSetting('enable_captcha', ENABLE_CAPTCHA);
    }
    
    /**
     * Check if login attempts limit is enabled
     */
    public static function isLoginAttemptsLimitEnabled() {
        return self::getSetting('enable_login_attempts_limit', ENABLE_LOGIN_ATTEMPTS_LIMIT);
    }
    
    /**
     * Check if IP blocking is enabled
     */
    public static function isIpBlockingEnabled() {
        return self::getSetting('enable_ip_blocking', ENABLE_IP_BLOCKING);
    }
    
    /**
     * Check if IP tracking is enabled
     */
    public static function isIpTrackingEnabled() {
        return self::getSetting('enable_ip_tracking', ENABLE_IP_TRACKING);
    }
    
    /**
     * Get max login attempts from database or config
     */
    public static function getMaxLoginAttempts() {
        try {
            $db = Database::getInstance();
            $prefix = Logger::getTablePrefix();
            $result = $db->fetchOne(
                "SELECT setting_value FROM {$prefix}system_settings WHERE setting_key = 'max_login_attempts'"
            );
            return $result ? (int)$result['setting_value'] : MAX_LOGIN_ATTEMPTS;
        } catch (Exception $e) {
            return MAX_LOGIN_ATTEMPTS;
        }
    }
    
    /**
     * Get login lockout time from database or config  
     */
    public static function getLoginLockoutTime() {
        try {
            $db = Database::getInstance();
            $prefix = Logger::getTablePrefix();
            $result = $db->fetchOne(
                "SELECT setting_value FROM {$prefix}system_settings WHERE setting_key = 'login_lockout_time'"
            );
            return $result ? (int)$result['setting_value'] : LOGIN_LOCKOUT_TIME;
        } catch (Exception $e) {
            return LOGIN_LOCKOUT_TIME;
        }
    }
}

/**
 * Session management
 */
class Session {
    
    /**
     * Start secure session
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure cookie parameters
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $httponly = true;
            $samesite = 'Strict';
            
            // Set session cookie parameters
            session_set_cookie_params([
                'lifetime' => ADMIN_SESSION_TIMEOUT,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ]);
            
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', $secure);
            
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['admin_logged_in']) && 
               $_SESSION['admin_logged_in'] === true &&
               isset($_SESSION['login_time']) &&
               (time() - $_SESSION['login_time']) < ADMIN_SESSION_TIMEOUT;
    }
    
    /**
     * Login user
     */
    public static function login($username) {
        // Clear any previous session data
        session_unset();
        
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['last_regeneration'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
    }
    
    /**
     * Logout user 
     */
    public static function logout() {
        // Clear session data
        session_unset();
        
        // Destroy session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Clear session cookie
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 3600,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
        
        // Start new clean session
        session_start();
        session_regenerate_id(true);
    }
}

/**
 * File operations helper
 */
class FileHelper {
    
    /**
     * Get human readable file size
     */
    public static function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Validate file extension
     */
    public static function validateExtension($filename, $allowedExtensions) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $allowedExtensions);
    }
    
    /**
     * Secure file upload
     */
    public static function uploadFile($file, $uploadDir, $allowedExtensions, $maxSize = null) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No valid file uploaded');
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        if ($maxSize && $file['size'] > $maxSize) {
            throw new Exception('File too large. Maximum size: ' . self::formatFileSize($maxSize));
        }
        
        if (!self::validateExtension($file['name'], $allowedExtensions)) {
            throw new Exception('Invalid file extension. Allowed: ' . implode(', ', $allowedExtensions));
        }
        
        $filename = self::generateSafeFilename($file['name']);
        $uploadPath = $uploadDir . '/' . $filename;
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        return $filename;
    }
    
    /**
     * Generate safe filename
     */
    public static function generateSafeFilename($filename) {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9-_]/', '_', $basename);
        $basename = substr($basename, 0, 100); // Limit length
        return $basename . '_' . date('YmdHis') . '.' . $extension;
    }
}

/**
 * Captcha system
 */
class Captcha {
    
    /**
     * Generate captcha
     */
    public static function generate() {
        $code = '';
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        
        for ($i = 0; $i < CAPTCHA_LENGTH; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        $_SESSION['captcha_code'] = $code;
        $_SESSION['captcha_time'] = time();
        
        return $code;
    }
    
    /**
     * Create captcha image
     */
    public static function createImage($code) {
        $image = imagecreate(CAPTCHA_WIDTH, CAPTCHA_HEIGHT);
        
        // Colors
        $background = imagecolorallocate($image, 255, 255, 255);
        $textColor = imagecolorallocate($image, 50, 50, 50);
        $lineColor = imagecolorallocate($image, 200, 200, 200);
        
        // Add noise lines
        for ($i = 0; $i < 5; $i++) {
            imageline($image, 
                random_int(0, CAPTCHA_WIDTH), random_int(0, CAPTCHA_HEIGHT),
                random_int(0, CAPTCHA_WIDTH), random_int(0, CAPTCHA_HEIGHT),
                $lineColor
            );
        }
        
        // Add text
        $fontSize = 5;
        $textWidth = imagefontwidth($fontSize) * strlen($code);
        $textHeight = imagefontheight($fontSize);
        
        $x = (CAPTCHA_WIDTH - $textWidth) / 2;
        $y = (CAPTCHA_HEIGHT - $textHeight) / 2;
        
        imagestring($image, $fontSize, $x, $y, $code, $textColor);
        
        return $image;
    }
    
    /**
     * Validate captcha
     */
    public static function validate($userInput) {
        if (!isset($_SESSION['captcha_code']) || !isset($_SESSION['captcha_time'])) {
            return false;
        }
        
        // Check if captcha has expired (5 minutes)
        if (time() - $_SESSION['captcha_time'] > 300) {
            unset($_SESSION['captcha_code']);
            unset($_SESSION['captcha_time']);
            return false;
        }
        
        $isValid = strtoupper($userInput) === $_SESSION['captcha_code'];
        
        // Clear captcha after validation attempt
        unset($_SESSION['captcha_code']);
        unset($_SESSION['captcha_time']);
        
        return $isValid;
    }
}

/**
 * Email utility class
 */
class EmailHelper {
    
    /**
     * Send email using PHP mail function
     */
    public static function sendMail($to, $subject, $message, $headers = '') {
        $defaultHeaders = "MIME-Version: 1.0\r\n";
        $defaultHeaders .= "Content-type: text/html; charset=UTF-8\r\n";
        $defaultHeaders .= "From: N3XT WEB <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
        
        $allHeaders = $defaultHeaders . $headers;
        
        return mail($to, $subject, $message, $allHeaders);
    }
    
    /**
     * Generate email verification code
     */
    public static function generateVerificationCode() {
        return sprintf('%06d', random_int(100000, 999999));
    }
    
    /**
     * Send verification email
     */
    public static function sendVerificationEmail($email, $code, $language = 'fr') {
        $templates = [
            'fr' => [
                'subject' => 'N3XT WEB - Code de vérification',
                'title' => 'Vérification de votre adresse email',
                'message' => 'Votre code de vérification est :',
                'instruction' => 'Veuillez saisir ce code pour continuer l\'installation.',
                'footer' => 'Ce code expire dans 15 minutes.'
            ],
            'en' => [
                'subject' => 'N3XT WEB - Verification Code',
                'title' => 'Email Address Verification',
                'message' => 'Your verification code is:',
                'instruction' => 'Please enter this code to continue the installation.',
                'footer' => 'This code expires in 15 minutes.'
            ]
        ];
        
        $template = $templates[$language] ?? $templates['fr'];
        
        $html = self::getEmailTemplate($template['title'], $template['message'], $code, $template['instruction'], $template['footer']);
        
        return self::sendMail($email, $template['subject'], $html);
    }
    
    /**
     * Send admin credentials email
     */
    public static function sendAdminCredentials($email, $username, $password, $boDirectory, $language = 'fr') {
        $adminUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $boDirectory . '/login.php';
        
        $templates = [
            'fr' => [
                'subject' => 'N3XT WEB - Vos identifiants administrateur',
                'title' => 'Installation terminée avec succès',
                'greeting' => 'Félicitations ! N3XT WEB a été installé avec succès.',
                'credentials_title' => 'Vos identifiants administrateur :',
                'username_label' => 'Nom d\'utilisateur',
                'password_label' => 'Mot de passe',
                'admin_panel_label' => 'Panneau d\'administration',
                'security_note' => 'Pour votre sécurité, veuillez changer ce mot de passe lors de votre première connexion.',
                'footer' => 'Conservez ces informations en lieu sûr.'
            ],
            'en' => [
                'subject' => 'N3XT WEB - Your Administrator Credentials',
                'title' => 'Installation completed successfully',
                'greeting' => 'Congratulations! N3XT WEB has been installed successfully.',
                'credentials_title' => 'Your administrator credentials:',
                'username_label' => 'Username',
                'password_label' => 'Password',
                'admin_panel_label' => 'Admin Panel',
                'security_note' => 'For security, please change this password on your first login.',
                'footer' => 'Keep this information secure.'
            ]
        ];
        
        $template = $templates[$language] ?? $templates['fr'];
        
        $credentialsHtml = "
            <div style='background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 12px; margin: 25px 0; border: 1px solid #dee2e6; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
                <div style='display: flex; align-items: center; margin-bottom: 15px;'>
                    <span style='font-size: 20px; margin-right: 10px;'>🔐</span>
                    <h3 style='margin: 0; color: #2c3e50; font-size: 18px;'>Identifiants d'accès</h3>
                </div>
                <table style='width: 100%; border-collapse: collapse; font-family: \"Courier New\", monospace;'>
                    <tr style='border-bottom: 1px solid #dee2e6;'>
                        <td style='padding: 12px 0; font-weight: bold; color: #495057; width: 40%;'>{$template['username_label']}:</td>
                        <td style='padding: 12px 0; background: #ffffff; padding-left: 15px; border-radius: 4px; color: #2c3e50; font-weight: 600;'>{$username}</td>
                    </tr>
                    <tr style='border-bottom: 1px solid #dee2e6;'>
                        <td style='padding: 12px 0; font-weight: bold; color: #495057;'>{$template['password_label']}:</td>
                        <td style='padding: 12px 0; background: #ffffff; padding-left: 15px; border-radius: 4px; color: #2c3e50; font-weight: 600;'>{$password}</td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 0; font-weight: bold; color: #495057;'>{$template['admin_panel_label']}:</td>
                        <td style='padding: 12px 0;'>
                            <a href='{$adminUrl}' style='background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px; font-weight: 500; display: inline-block;'>
                                🚀 Accéder au panneau
                            </a>
                        </td>
                    </tr>
                </table>
            </div>
            <div style='background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f39c12;'>
                <div style='display: flex; align-items: center;'>
                    <span style='font-size: 24px; margin-right: 10px;'>⚠️</span>
                    <div>
                        <p style='margin: 0; color: #856404; font-weight: bold; font-size: 16px;'>Recommandation de sécurité</p>
                        <p style='margin: 5px 0 0 0; color: #856404; font-size: 14px;'>{$template['security_note']}</p>
                    </div>
                </div>
            </div>
        ";
        
        $html = self::getEmailTemplate($template['title'], $template['greeting'], '', $template['credentials_title'] . $credentialsHtml, $template['footer']);
        
        return self::sendMail($email, $template['subject'], $html);
    }
    
    /**
     * Get email HTML template with enhanced styling and logo
     */
    private static function getEmailTemplate($title, $message, $code = '', $instruction = '', $footer = '') {
        $codeHtml = $code ? "<div style='background: #3498db; color: white; font-size: 24px; font-weight: bold; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0; letter-spacing: 2px;'>{$code}</div>" : '';
        
        // Get logo URL
        $logoUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/fav.png';
        $logoHtml = "<img src='{$logoUrl}' alt='N3XT WEB' style='width: 40px; height: 40px; margin-bottom: 10px;'>";
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>N3XT WEB - {$title}</title>
            <style>
                @media only screen and (max-width: 600px) {
                    .container { width: 100% !important; margin: 0 !important; }
                    .header { padding: 20px !important; }
                    .content { padding: 20px !important; }
                }
            </style>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5;'>
            <table cellpadding='0' cellspacing='0' border='0' width='100%' style='background: #f5f5f5; min-height: 100vh;'>
                <tr>
                    <td align='center' style='padding: 20px;'>
                        <table class='container' cellpadding='0' cellspacing='0' border='0' style='max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;'>
                            <!-- Header -->
                            <tr>
                                <td class='header' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 30px; text-align: center;'>
                                    {$logoHtml}
                                    <h1 style='margin: 0; font-size: 28px; font-weight: 600;'>N3XT WEB</h1>
                                    <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>{$title}</p>
                                </td>
                            </tr>
                            <!-- Content -->
                            <tr>
                                <td class='content' style='padding: 40px 30px;'>
                                    <p style='font-size: 16px; margin-bottom: 20px; color: #2c3e50;'>{$message}</p>
                                    {$codeHtml}
                                    <p style='margin: 20px 0; color: #34495e; font-size: 15px;'>{$instruction}</p>
                                    
                                    <!-- Call to Action -->
                                    <div style='text-align: center; margin: 30px 0;'>
                                        <div style='background: #ecf0f1; padding: 15px; border-radius: 8px; border-left: 4px solid #3498db;'>
                                            <p style='margin: 0; font-size: 14px; color: #2c3e50;'>
                                                <strong>🔒 Sécurité:</strong> Ce message contient des informations sensibles. Ne le transférez pas.
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <hr style='border: none; border-top: 1px solid #ecf0f1; margin: 30px 0;'>
                                    <p style='font-size: 14px; color: #7f8c8d; margin: 0;'>{$footer}</p>
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style='background: #2c3e50; color: white; padding: 25px 30px; text-align: center;'>
                                    <p style='margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;'>
                                        <strong>N3XT WEB</strong> - Solution web professionnelle
                                    </p>
                                    <p style='margin: 0; font-size: 12px; opacity: 0.7;'>
                                        © " . date('Y') . " N3XT Communication. Tous droits réservés.
                                    </p>
                                    <div style='margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);'>
                                        <p style='margin: 0; font-size: 11px; opacity: 0.6;'>
                                            Cet email a été envoyé automatiquement, veuillez ne pas y répondre.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>";
    }
}

/**
 * Installation helper class
 */
class InstallHelper {
    
    /**
     * Generate random directory name for Back Office
     */
    public static function generateRandomBoDirectory() {
        $prefix = 'bo-';
        $suffix = bin2hex(random_bytes(8));
        return $prefix . $suffix;
    }
    
    /**
     * Generate secure admin password
     */
    public static function generateAdminPassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $charCount = strlen($chars);
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charCount - 1)];
        }
        
        return $password;
    }
    
    /**
     * Create Back Office directory
     */
    public static function createBoDirectory($dirName) {
        $sourcePath = __DIR__ . '/../bo';
        $targetPath = __DIR__ . '/../' . $dirName;
        
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        if (file_exists($targetPath)) {
            return false;
        }
        
        // Copy the bo directory to the new random directory name
        if (!self::copyDirectory($sourcePath, $targetPath)) {
            return false;
        }
        
        // Create proper .htaccess for the BO directory
        $htaccessContent = "# N3XT WEB - Back Office Access Control\n";
        $htaccessContent .= "Options -Indexes\n";
        $htaccessContent .= "RewriteEngine On\n";
        $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
        $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
        $htaccessContent .= "RewriteRule ^(.*)$ index.php?route=$1 [QSA,L]\n";
        $htaccessContent .= "\n# Security headers\n";
        $htaccessContent .= "<IfModule mod_headers.c>\n";
        $htaccessContent .= "    Header always set X-Content-Type-Options nosniff\n";
        $htaccessContent .= "    Header always set X-Frame-Options DENY\n";
        $htaccessContent .= "    Header always set X-XSS-Protection \"1; mode=block\"\n";
        $htaccessContent .= "</IfModule>\n";
        
        file_put_contents($targetPath . '/.htaccess', $htaccessContent);
        
        return true;
    }
    
    /**
     * Clean up installation directories after successful setup
     */
    public static function cleanupInstallation() {
        $errors = [];
        
        // Remove fake admin directory
        $adminPath = __DIR__ . '/../admin';
        if (file_exists($adminPath)) {
            if (!self::removeDirectory($adminPath)) {
                $errors[] = 'Failed to remove fake admin directory';
            } else {
                Logger::log('Fake admin directory removed successfully', LOG_LEVEL_INFO, 'install');
            }
        }
        
        // Remove original bo directory (after random BO has been created)
        $boPath = __DIR__ . '/../bo';
        if (file_exists($boPath)) {
            if (!self::removeDirectory($boPath)) {
                $errors[] = 'Failed to remove original bo directory';
            } else {
                Logger::log('Original bo directory removed successfully', LOG_LEVEL_INFO, 'install');
            }
        }
        
        return ['success' => empty($errors), 'errors' => $errors];
    }
    
    /**
     * Remove directory recursively
     */
    private static function removeDirectory($path) {
        if (!is_dir($path)) {
            return false;
        }
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    if (!rmdir($item->getRealPath())) {
                        return false;
                    }
                } else {
                    if (!unlink($item->getRealPath())) {
                        return false;
                    }
                }
            }
            
            return rmdir($path);
        } catch (Exception $e) {
            Logger::log("Error removing directory {$path}: " . $e->getMessage(), LOG_LEVEL_ERROR, 'install');
            return false;
        }
    }
    
    /**
     * Copy directory recursively
     */
    private static function copyDirectory($source, $destination) {
        if (!is_dir($source)) {
            return false;
        }
        
        if (!mkdir($destination, 0755, true)) {
            return false;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!mkdir($target, 0755, true)) {
                    return false;
                }
            } else {
                if (!copy($item, $target)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Update database table prefix in queries
     */
    public static function updateTablePrefix($sql, $prefix = '') {
        if (empty($prefix)) {
            return $sql;
        }
        
        // Add prefix to common table names
        $tables = ['admin_users', 'system_settings', 'logs', 'backups'];
        
        foreach ($tables as $table) {
            $sql = str_replace($table, $prefix . $table, $sql);
        }
        
        return $sql;
    }
}

/**
 * Language helper class
 */
class LanguageHelper {
    
    private static $translations = [
        'fr' => [
            'installation_title' => 'Installation de N3XT WEB',
            'welcome' => 'Bienvenue',
            'language_selection' => 'Sélection de la langue',
            'choose_language' => 'Choisissez votre langue',
            'french' => 'Français',
            'english' => 'English',
            'continue' => 'Continuer',
            'next' => 'Suivant',
            'previous' => 'Précédent',
            'finish' => 'Terminer',
            'step' => 'Étape',
            'system_requirements' => 'Vérification des prérequis',
            'database_configuration' => 'Configuration de la base de données',
            'admin_setup' => 'Configuration administrateur',
            'installation_complete' => 'Installation terminée',
            'email_address' => 'Adresse email',
            'verification_code' => 'Code de vérification',
            'send_code' => 'Envoyer le code',
            'verify_code' => 'Vérifier le code',
            'admin_username' => 'Nom d\'utilisateur administrateur',
            'table_prefix' => 'Préfixe des tables',
            'table_prefix_help' => 'Préfixe pour les tables de la base de données (optionnel)',
            'email_sent' => 'Un code de vérification a été envoyé à votre adresse email.',
            'invalid_code' => 'Code de vérification invalide ou expiré.',
            'installation_success' => 'L\'installation s\'est terminée avec succès !',
            'check_email' => 'Vérifiez votre email pour les identifiants administrateur.',
            'maintenance_mode_enabled' => 'Le mode maintenance est activé par défaut.',
        ],
        'en' => [
            'installation_title' => 'N3XT WEB Installation',
            'welcome' => 'Welcome',
            'language_selection' => 'Language Selection',
            'choose_language' => 'Choose your language',
            'french' => 'Français',
            'english' => 'English',
            'continue' => 'Continue',
            'next' => 'Next',
            'previous' => 'Previous',
            'finish' => 'Finish',
            'step' => 'Step',
            'system_requirements' => 'System Requirements Check',
            'database_configuration' => 'Database Configuration',
            'admin_setup' => 'Administrator Setup',
            'installation_complete' => 'Installation Complete',
            'email_address' => 'Email Address',
            'verification_code' => 'Verification Code',
            'send_code' => 'Send Code',
            'verify_code' => 'Verify Code',
            'admin_username' => 'Administrator Username',
            'table_prefix' => 'Table Prefix',
            'table_prefix_help' => 'Prefix for database tables (optional)',
            'email_sent' => 'A verification code has been sent to your email address.',
            'invalid_code' => 'Invalid or expired verification code.',
            'installation_success' => 'Installation completed successfully!',
            'check_email' => 'Check your email for administrator credentials.',
            'maintenance_mode_enabled' => 'Maintenance mode is enabled by default.',
        ]
    ];
    
    /**
     * Get translation
     */
    public static function get($key, $language = 'fr') {
        return self::$translations[$language][$key] ?? self::$translations['fr'][$key] ?? $key;
    }
    
    /**
     * Get all translations for a language
     */
    public static function getAll($language = 'fr') {
        return self::$translations[$language] ?? self::$translations['fr'];
    }
}

// Initialize security headers
Security::setSecurityHeaders();

// Start session
Session::start();
?>