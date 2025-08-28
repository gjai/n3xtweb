<?php
/**
 * N3XT Communication - Core Functions
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
            die("Database connection failed. Please check configuration.");
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
     * Log access attempt
     */
    public static function logAccess($username, $success, $notes = '') {
        $status = $success ? 'SUCCESS' : 'FAILED';
        $message = "Login attempt - Username: {$username} | Status: {$status}";
        if ($notes) {
            $message .= " | Notes: {$notes}";
        }
        
        self::log($message, LOG_LEVEL_INFO, 'access');
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
 * Session management
 */
class Session {
    
    /**
     * Start secure session
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
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
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        session_regenerate_id(true);
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        session_unset();
        session_destroy();
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
        $defaultHeaders .= "From: N3XT Communication <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
        
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
                'subject' => 'N3XT Communication - Code de vérification',
                'title' => 'Vérification de votre adresse email',
                'message' => 'Votre code de vérification est :',
                'instruction' => 'Veuillez saisir ce code pour continuer l\'installation.',
                'footer' => 'Ce code expire dans 15 minutes.'
            ],
            'en' => [
                'subject' => 'N3XT Communication - Verification Code',
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
                'subject' => 'N3XT Communication - Vos identifiants administrateur',
                'title' => 'Installation terminée avec succès',
                'greeting' => 'Félicitations ! N3XT Communication a été installé avec succès.',
                'credentials_title' => 'Vos identifiants administrateur :',
                'username_label' => 'Nom d\'utilisateur',
                'password_label' => 'Mot de passe',
                'admin_panel_label' => 'Panneau d\'administration',
                'security_note' => 'Pour votre sécurité, veuillez changer ce mot de passe lors de votre première connexion.',
                'footer' => 'Conservez ces informations en lieu sûr.'
            ],
            'en' => [
                'subject' => 'N3XT Communication - Your Administrator Credentials',
                'title' => 'Installation completed successfully',
                'greeting' => 'Congratulations! N3XT Communication has been installed successfully.',
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
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; font-family: monospace;'>
                <p><strong>{$template['username_label']}:</strong> {$username}</p>
                <p><strong>{$template['password_label']}:</strong> {$password}</p>
                <p><strong>{$template['admin_panel_label']}:</strong> <a href='{$adminUrl}'>{$adminUrl}</a></p>
            </div>
            <div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                <p style='margin: 0; color: #856404;'><strong>⚠️ {$template['security_note']}</strong></p>
            </div>
        ";
        
        $html = self::getEmailTemplate($template['title'], $template['greeting'], '', $template['credentials_title'] . $credentialsHtml, $template['footer']);
        
        return self::sendMail($email, $template['subject'], $html);
    }
    
    /**
     * Get email HTML template
     */
    private static function getEmailTemplate($title, $message, $code = '', $instruction = '', $footer = '') {
        $codeHtml = $code ? "<div style='background: #3498db; color: white; font-size: 24px; font-weight: bold; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0; letter-spacing: 2px;'>{$code}</div>" : '';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>N3XT Communication</title>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; box-shadow: 0 0 20px rgba(0,0,0,0.1);'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 24px; font-weight: 600;'>N3XT Communication</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>{$title}</p>
                </div>
                <div style='padding: 30px;'>
                    <p style='font-size: 16px; margin-bottom: 20px;'>{$message}</p>
                    {$codeHtml}
                    <p style='margin: 20px 0;'>{$instruction}</p>
                    <hr style='border: none; border-top: 1px solid #ecf0f1; margin: 30px 0;'>
                    <p style='font-size: 14px; color: #7f8c8d; margin: 0;'>{$footer}</p>
                </div>
                <div style='background: #2c3e50; color: white; padding: 20px; text-align: center; font-size: 14px;'>
                    <p style='margin: 0; opacity: 0.8;'>© " . date('Y') . " N3XT Communication. Tous droits réservés.</p>
                </div>
            </div>
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
        $sourcePath = __DIR__ . '/../admin';
        $targetPath = __DIR__ . '/../' . $dirName;
        
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        if (file_exists($targetPath)) {
            return false;
        }
        
        return self::copyDirectory($sourcePath, $targetPath);
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
            'installation_title' => 'Installation de N3XT Communication',
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
            'installation_title' => 'N3XT Communication Installation',
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