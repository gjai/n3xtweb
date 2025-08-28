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

// Initialize security headers
Security::setSecurityHeaders();

// Start session
Session::start();
?>