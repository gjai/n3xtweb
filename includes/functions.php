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

// Define logging level constants
if (!defined('LOG_LEVEL_ERROR')) define('LOG_LEVEL_ERROR', 1);
if (!defined('LOG_LEVEL_WARNING')) define('LOG_LEVEL_WARNING', 2);
if (!defined('LOG_LEVEL_INFO')) define('LOG_LEVEL_INFO', 3);
if (!defined('LOG_LEVEL_DEBUG')) define('LOG_LEVEL_DEBUG', 4);
if (!defined('DEFAULT_LOG_LEVEL')) define('DEFAULT_LOG_LEVEL', LOG_LEVEL_WARNING);

// Load configuration
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Configuration.php';

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
                PDO::ATTR_PERSISTENT => false,
                // Enhanced security options
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_TIMEOUT => 30
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Set SQL mode for stricter operation (NO_AUTO_CREATE_USER removed for MySQL 8.0+ compatibility)
            $this->pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            
        } catch (PDOException $e) {
            // Don't expose database details in production
            $message = defined('DEBUG') && DEBUG ? $e->getMessage() : 'Database connection failed';
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check configuration.");
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
     * Execute a prepared statement with parameters and enhanced security
     */
    public function execute($sql, $params = []) {
        try {
            // Log potentially dangerous queries in development
            if (defined('DEBUG') && DEBUG) {
                $dangerousPatterns = ['/DELETE\s+FROM/i', '/DROP\s+TABLE/i', '/TRUNCATE/i', '/ALTER\s+TABLE/i'];
                foreach ($dangerousPatterns as $pattern) {
                    if (preg_match($pattern, $sql)) {
                        error_log("Potentially dangerous SQL query executed: " . $sql);
                        break;
                    }
                }
            }
            
            $stmt = $this->pdo->prepare($sql);
            
            // Type checking for parameters
            foreach ($params as $key => $value) {
                if (is_int($value)) {
                    $stmt->bindValue($key + 1, $value, PDO::PARAM_INT);
                } elseif (is_bool($value)) {
                    $stmt->bindValue($key + 1, $value, PDO::PARAM_BOOL);
                } elseif (is_null($value)) {
                    $stmt->bindValue($key + 1, null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue($key + 1, $value, PDO::PARAM_STR);
                }
            }
            
            $stmt->execute();
            return $stmt;
            
        } catch (PDOException $e) {
            // Enhanced error logging without exposing sensitive data
            $errorId = uniqid('db_error_');
            error_log("Database query failed [{$errorId}]: " . $e->getMessage() . " | SQL: " . substr($sql, 0, 200));
            
            // In production, don't expose SQL details
            if (defined('DEBUG') && DEBUG) {
                throw new Exception("Database error [{$errorId}]: " . $e->getMessage());
            } else {
                throw new Exception("Database operation failed. Error ID: {$errorId}");
            }
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
     * Sanitize input with enhanced validation
     */
    public static function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        $input = trim($input);
        
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'filename':
                // Enhanced filename sanitization
                $input = preg_replace('/[^a-zA-Z0-9._-]/', '', $input);
                return substr($input, 0, 255); // Limit filename length
            case 'sql':
                // For SQL LIKE queries - escape wildcards
                return str_replace(['%', '_'], ['\\%', '\\_'], $input);
            default:
                return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Validate password strength
     */
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Generate secure password hash
     */
    public static function hashPassword($password) {
        // Validate password before hashing
        $validation = self::validatePasswordStrength($password);
        if ($validation !== true) {
            throw new InvalidArgumentException('Password does not meet security requirements: ' . implode(', ', $validation));
        }
        
        // Use stronger options for Argon2ID
        $options = [
            'memory_cost' => 65536, // 64 MB
            'time_cost'   => 4,     // 4 iterations
            'threads'     => 3,     // 3 threads
        ];
        
        return password_hash($password, PASSWORD_ARGON2ID, $options);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Check if password hash needs rehashing
     */
    public static function needsRehash($hash) {
        $options = [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 3,
        ];
        
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $options);
    }
    
    /**
     * Generate random string
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Set comprehensive security headers
     */
    public static function setSecurityHeaders() {
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy (enhanced)
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: blob:; " .
               "font-src 'self'; " .
               "connect-src 'self'; " .
               "media-src 'self'; " .
               "object-src 'none'; " .
               "frame-src 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self'";
        header("Content-Security-Policy: {$csp}");
        
        // HSTS for HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Permissions Policy (formerly Feature Policy)
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        
        // Prevent caching of sensitive pages
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/bo/') !== false || 
            strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
    
    /**
     * Validate and sanitize file uploads
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = null) {
        $errors = [];
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'No valid file uploaded';
            return $errors;
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds PHP upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $errors[] = $uploadErrors[$file['error']] ?? 'Unknown upload error';
            return $errors;
        }
        
        // Check file size
        if ($maxSize && $file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size';
        }
        
        // Check MIME type
        if (!empty($allowedTypes)) {
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $file['tmp_name']);
            finfo_close($fileInfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                $errors[] = 'File type not allowed';
            }
        }
        
        // Check for dangerous file extensions
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'pl', 'py', 'jsp', 'asp', 'aspx', 'sh', 'cgi'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($extension, $dangerousExtensions)) {
            $errors[] = 'File extension not allowed for security reasons';
        }
        
        return $errors;
    }
}

/**
 * Logging system
 */
class Logger {
    
    /**
     * Write log entry with improved formatting and rotation
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
        $ip = self::getClientIP();
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 100); // Limit user agent length
        
        // Sanitize message to prevent log injection
        $message = preg_replace('/[\r\n]/', ' ', $message);
        $message = substr($message, 0, 500); // Limit message length
        
        $logEntry = sprintf(
            "[%s] [%s] [IP:%s] %s | UA:%s%s",
            $timestamp,
            $levelName,
            $ip,
            $message,
            $userAgent,
            PHP_EOL
        );
        
        $logPath = LOG_PATH . "/{$logFile}.log";
        
        // Create logs directory if it doesn't exist
        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0755, true);
        }
        
        // Rotate log file if it's too large (> 10MB)
        if (file_exists($logPath) && filesize($logPath) > 10 * 1024 * 1024) {
            self::rotateLogFile($logPath);
        }
        
        file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get client IP address with proxy support
     */
    private static function getClientIP() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated list (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Rotate log file when it gets too large
     */
    private static function rotateLogFile($logPath) {
        $rotatedPath = $logPath . '.' . date('Y-m-d-H-i-s');
        rename($logPath, $rotatedPath);
        
        // Compress old log file if gzip is available
        if (function_exists('gzopen')) {
            $gz = gzopen($rotatedPath . '.gz', 'wb9');
            if ($gz) {
                gzwrite($gz, file_get_contents($rotatedPath));
                gzclose($gz);
                unlink($rotatedPath);
            }
        }
        
        // Clean up old rotated logs (keep only last 30 days)
        self::cleanupOldLogs();
    }
    
    /**
     * Clean up old log files
     */
    public static function cleanupOldLogs($maxAge = 30) {
        if (!is_dir(LOG_PATH)) {
            return 0;
        }
        
        $deleted = 0;
        $cutoffTime = time() - ($maxAge * 24 * 60 * 60);
        
        $files = glob(LOG_PATH . '/*.log.*');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Get log statistics
     */
    public static function getLogStats() {
        $stats = [
            'total_size' => 0,
            'file_count' => 0,
            'log_files' => []
        ];
        
        if (!is_dir(LOG_PATH)) {
            return $stats;
        }
        
        $files = glob(LOG_PATH . '/*.log*');
        foreach ($files as $file) {
            $size = filesize($file);
            $stats['total_size'] += $size;
            $stats['file_count']++;
            
            $stats['log_files'][] = [
                'name' => basename($file),
                'size' => $size,
                'modified' => filemtime($file),
                'lines' => self::countFileLines($file)
            ];
        }
        
        return $stats;
    }
    
    /**
     * Count lines in a file efficiently
     */
    private static function countFileLines($file) {
        $lineCount = 0;
        $handle = fopen($file, 'r');
        
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $lineCount++;
            }
            fclose($handle);
        }
        
        return $lineCount;
    }
    
    /**
     * Analyze log patterns for security issues
     */
    public static function analyzeSecurityLogs($logFile = 'access') {
        $logPath = LOG_PATH . "/{$logFile}.log";
        
        if (!file_exists($logPath)) {
            return ['status' => 'error', 'message' => 'Log file not found'];
        }
        
        $analysis = [
            'failed_logins' => 0,
            'suspicious_ips' => [],
            'user_agents' => [],
            'attack_patterns' => [],
            'time_analysis' => []
        ];
        
        $handle = fopen($logPath, 'r');
        if (!$handle) {
            return ['status' => 'error', 'message' => 'Cannot read log file'];
        }
        
        $ipFailures = [];
        $userAgents = [];
        
        while (($line = fgets($handle)) !== false) {
            // Parse log entry
            if (preg_match('/\[([^\]]+)\].*\[IP:([^\]]+)\].*\| UA:(.*)/', $line, $matches)) {
                $timestamp = $matches[1];
                $ip = $matches[2];
                $userAgent = trim($matches[3]);
                
                // Check for failed login attempts
                if (strpos($line, 'FAILED') !== false) {
                    $analysis['failed_logins']++;
                    $ipFailures[$ip] = ($ipFailures[$ip] ?? 0) + 1;
                }
                
                // Collect user agents
                if (!empty($userAgent) && $userAgent !== 'unknown') {
                    $userAgents[$userAgent] = ($userAgents[$userAgent] ?? 0) + 1;
                }
                
                // Check for attack patterns
                $attackKeywords = ['sql', 'script', 'union', 'select', 'drop', 'delete', 'admin\'', 'or 1=1'];
                foreach ($attackKeywords as $keyword) {
                    if (stripos($line, $keyword) !== false) {
                        $analysis['attack_patterns'][] = [
                            'timestamp' => $timestamp,
                            'ip' => $ip,
                            'pattern' => $keyword,
                            'line' => substr($line, 0, 200)
                        ];
                    }
                }
            }
        }
        fclose($handle);
        
        // Identify suspicious IPs (more than 10 failed attempts)
        foreach ($ipFailures as $ip => $count) {
            if ($count >= 10) {
                $analysis['suspicious_ips'][] = [
                    'ip' => $ip,
                    'failed_attempts' => $count
                ];
            }
        }
        
        // Sort user agents by frequency
        arsort($userAgents);
        $analysis['user_agents'] = array_slice($userAgents, 0, 10, true);
        
        return $analysis;
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
                    // Try to get from database - use default prefix to avoid chicken-and-egg problem
                    try {
                        $db = Database::getInstance();
                        $defaultPrefix = 'n3xtweb_';
                        $result = $db->fetchOne("SELECT setting_value FROM {$defaultPrefix}system_settings WHERE setting_key = 'table_prefix' LIMIT 1");
                        $prefix = $result ? $result['setting_value'] : $defaultPrefix;
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
     * Start secure session with enhanced security
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            // Enhanced session security settings
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.use_trans_sid', 0);
            ini_set('session.cookie_httponly', 1);
            ini_set('session.entropy_length', 32);
            ini_set('session.hash_function', 'sha256');
            ini_set('session.hash_bits_per_character', 6);
            ini_set('session.sid_length', 48);
            
            // Set secure cookie parameters
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $httponly = true;
            $samesite = 'Strict';
            
            // Set session cookie parameters
            session_set_cookie_params([
                'lifetime' => defined('ADMIN_SESSION_TIMEOUT') ? ADMIN_SESSION_TIMEOUT : SESSION_LIFETIME,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ]);
            
            // Set session name to something non-standard
            session_name('N3XT_SESSID');
            
            session_start();
            
            // Enhanced session validation
            self::validateSession();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
                $_SESSION['session_fingerprint'] = self::generateFingerprint();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }
    
    /**
     * Generate session fingerprint for additional security
     */
    private static function generateFingerprint() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        return hash('sha256', $userAgent . $acceptLanguage . $acceptEncoding);
    }
    
    /**
     * Validate session integrity
     */
    private static function validateSession() {
        // Check session fingerprint
        if (isset($_SESSION['session_fingerprint'])) {
            $currentFingerprint = self::generateFingerprint();
            if ($_SESSION['session_fingerprint'] !== $currentFingerprint) {
                self::destroy();
                return false;
            }
        }
        
        // Check for session hijacking attempts
        if (isset($_SESSION['remote_addr'])) {
            if ($_SESSION['remote_addr'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
                self::destroy();
                return false;
            }
        } else {
            $_SESSION['remote_addr'] = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        return true;
    }
    
    /**
     * Destroy session completely
     */
    public static function destroy() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
            
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
        }
    }
    
    /**
     * Check if user is logged in with enhanced validation
     */
    public static function isLoggedIn() {
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            return false;
        }
        
        if (!isset($_SESSION['login_time'])) {
            return false;
        }
        
        $sessionTimeout = defined('ADMIN_SESSION_TIMEOUT') ? ADMIN_SESSION_TIMEOUT : SESSION_LIFETIME;
        if ((time() - $_SESSION['login_time']) >= $sessionTimeout) {
            self::logout();
            return false;
        }
        
        // Validate session integrity
        if (!self::validateSession()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Login user with enhanced security
     */
    public static function login($username) {
        // Sanitize username
        $username = Security::sanitizeInput($username);
        
        // Clear any previous session data but preserve security tokens
        $csrfToken = $_SESSION['csrf_token'] ?? null;
        $csrfTime = $_SESSION['csrf_time'] ?? null;
        session_unset();
        
        // Restore CSRF token if it was valid
        if ($csrfToken && $csrfTime && (time() - $csrfTime) < CSRF_TOKEN_LIFETIME) {
            $_SESSION['csrf_token'] = $csrfToken;
            $_SESSION['csrf_time'] = $csrfTime;
        }
        
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['last_regeneration'] = time();
        $_SESSION['session_fingerprint'] = self::generateFingerprint();
        $_SESSION['remote_addr'] = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Log successful login
        Logger::logAccess($username, true, 'User logged in successfully');
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
        
        // Remove documentation markdown files for security and cleanliness
        $rootPath = __DIR__ . '/..';
        $mdFiles = [
            'README.md',
            'OPTIMIZATION_REPORT.md', 
            'INSTALL_IMPROVEMENTS.md',
            'SECURITY_MONITORING_GUIDE.md',
            'CHANGELOG.md'
        ];
        
        foreach ($mdFiles as $mdFile) {
            $filePath = $rootPath . '/' . $mdFile;
            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    $errors[] = "Failed to remove $mdFile";
                } else {
                    Logger::log("Documentation file $mdFile removed successfully", LOG_LEVEL_INFO, 'install');
                }
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
        
        // Add prefix to common table names using word boundaries to avoid partial replacements
        $tables = ['admin_users', 'system_settings', 'access_logs', 'login_attempts', 'logs', 'backups'];
        
        foreach ($tables as $table) {
            // Use word boundaries to ensure we only replace complete table names
            $sql = preg_replace('/\b' . preg_quote($table, '/') . '\b/', $prefix . $table, $sql);
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

/**
 * Simple file-based caching system for performance optimization
 */
class Cache {
    private static $cacheDir = null;
    
    /**
     * Initialize cache directory
     */
    private static function initCacheDir() {
        if (self::$cacheDir === null) {
            self::$cacheDir = ROOT_PATH . '/cache';
            if (!is_dir(self::$cacheDir)) {
                mkdir(self::$cacheDir, 0755, true);
            }
        }
    }
    
    /**
     * Generate cache key
     */
    private static function generateKey($key) {
        return hash('sha256', $key);
    }
    
    /**
     * Store data in cache
     */
    public static function set($key, $data, $ttl = 3600) {
        self::initCacheDir();
        
        $cacheKey = self::generateKey($key);
        $cacheFile = self::$cacheDir . '/' . $cacheKey . '.cache';
        
        $cacheData = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        $result = file_put_contents($cacheFile, serialize($cacheData), LOCK_EX);
        return $result !== false;
    }
    
    /**
     * Retrieve data from cache
     */
    public static function get($key, $default = null) {
        self::initCacheDir();
        
        $cacheKey = self::generateKey($key);
        $cacheFile = self::$cacheDir . '/' . $cacheKey . '.cache';
        
        if (!file_exists($cacheFile)) {
            return $default;
        }
        
        $cacheData = unserialize(file_get_contents($cacheFile));
        
        if (!$cacheData || !isset($cacheData['expires'])) {
            unlink($cacheFile);
            return $default;
        }
        
        if (time() > $cacheData['expires']) {
            unlink($cacheFile);
            return $default;
        }
        
        return $cacheData['data'];
    }
    
    /**
     * Cache with callback for automatic caching
     */
    public static function remember($key, $ttl, callable $callback) {
        $data = self::get($key);
        
        if ($data === null) {
            $data = $callback();
            self::set($key, $data, $ttl);
        }
        
        return $data;
    }
    
    /**
     * Clear all cache
     */
    public static function clear() {
        self::initCacheDir();
        
        $files = glob(self::$cacheDir . '/*.cache');
        $cleared = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $cleared++;
            }
        }
        
        return $cleared;
    }
}

/**
 * Performance monitoring and optimization utilities
 */
class Performance {
    private static $timers = [];
    
    /**
     * Start performance timer
     */
    public static function startTimer($name) {
        self::$timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
    }
    
    /**
     * End performance timer
     */
    public static function endTimer($name) {
        if (!isset(self::$timers[$name])) {
            return null;
        }
        
        $timer = self::$timers[$name];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        return [
            'execution_time' => $endTime - $timer['start'],
            'memory_used' => $endMemory - $timer['memory_start'],
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
    
    /**
     * Optimize database queries with caching
     */
    public static function cachedQuery($db, $sql, $params = [], $ttl = 300) {
        $cacheKey = 'query_' . hash('sha256', $sql . serialize($params));
        
        return Cache::remember($cacheKey, $ttl, function() use ($db, $sql, $params) {
            return $db->fetchAll($sql, $params);
        });
    }
}

/**
 * Asset optimization utilities
 */
class AssetOptimizer {
    
    /**
     * Minify CSS content
     */
    public static function minifyCSS($css) {
        // Remove comments
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        
        // Remove unnecessary whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove whitespace around specific characters
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        
        // Remove leading/trailing whitespace
        $css = trim($css);
        
        // Remove empty rules
        $css = preg_replace('/[^{}]*{\s*}/', '', $css);
        
        return $css;
    }
    
    /**
     * Minify JavaScript content
     */
    public static function minifyJS($js) {
        // Remove single line comments
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Remove multi-line comments
        $js = preg_replace('/\/\*.*?\*\//s', '', $js);
        
        // Remove unnecessary whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Remove whitespace around operators
        $js = preg_replace('/\s*([=+\-*\/{}();,:])\s*/', '$1', $js);
        
        return trim($js);
    }
    
    /**
     * Combine and cache CSS files
     */
    public static function combineCSS($files, $outputFile = null) {
        $combinedCSS = '';
        $lastModified = 0;
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $combinedCSS .= $content . "\n";
                $lastModified = max($lastModified, filemtime($file));
            }
        }
        
        // Minify combined CSS
        $combinedCSS = self::minifyCSS($combinedCSS);
        
        if ($outputFile) {
            file_put_contents($outputFile, $combinedCSS);
            touch($outputFile, $lastModified);
        }
        
        return $combinedCSS;
    }
    
    /**
     * Generate integrity hash for assets
     */
    public static function generateIntegrity($content) {
        return 'sha384-' . base64_encode(hash('sha384', $content, true));
    }
    
    /**
     * Get asset with cache busting
     */
    public static function getAssetUrl($file) {
        if (file_exists($file)) {
            $mtime = filemtime($file);
            return $file . '?v=' . $mtime;
        }
        return $file;
    }
}

/**
 * System health and optimization utilities
 */
class SystemHealth {
    
    /**
     * Check overall system health
     */
    public static function checkHealth() {
        $checks = [];
        
        // Database connectivity
        try {
            $db = Database::getInstance();
            $checks['database'] = ['status' => 'OK', 'message' => 'Database connection successful'];
        } catch (Exception $e) {
            $checks['database'] = ['status' => 'ERROR', 'message' => 'Database connection failed'];
        }
        
        // File permissions
        $criticalDirs = [LOG_PATH, BACKUP_PATH, UPLOAD_PATH];
        $permissionErrors = [];
        
        foreach ($criticalDirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (!is_writable($dir)) {
                $permissionErrors[] = $dir;
            }
        }
        
        $checks['permissions'] = empty($permissionErrors) 
            ? ['status' => 'OK', 'message' => 'All directories writable']
            : ['status' => 'WARNING', 'message' => 'Some directories not writable: ' . implode(', ', $permissionErrors)];
        
        // Security settings
        $securityIssues = [];
        
        if (DB_PASS === 'secure_password') {
            $securityIssues[] = 'Default database password detected';
        }
        
        if (!defined('DEBUG') || DEBUG === true) {
            $securityIssues[] = 'Debug mode may be enabled';
        }
        
        $checks['security'] = empty($securityIssues)
            ? ['status' => 'OK', 'message' => 'Security settings appear correct']
            : ['status' => 'WARNING', 'message' => implode(', ', $securityIssues)];
        
        // Performance metrics
        $metrics = Performance::getSystemMetrics();
        $memoryUsage = $metrics['memory_usage'] / 1024 / 1024; // MB
        
        $checks['performance'] = $memoryUsage < 128 
            ? ['status' => 'OK', 'message' => sprintf('Memory usage: %.2f MB', $memoryUsage)]
            : ['status' => 'WARNING', 'message' => sprintf('High memory usage: %.2f MB', $memoryUsage)];
        
        return $checks;
    }
    
    /**
     * Clean up system files
     */
    public static function cleanup() {
        $cleaned = [];
        
        // Clean expired cache
        if (defined('ENABLE_CACHING') && ENABLE_CACHING) {
            $cacheStats = Cache::getStats();
            if ($cacheStats['expired_entries'] > 0) {
                Cache::clear();
                $cleaned[] = 'Cleared ' . $cacheStats['expired_entries'] . ' expired cache entries';
            }
        }
        
        // Clean old log files (older than 30 days)
        if (is_dir(LOG_PATH)) {
            $oldLogs = glob(LOG_PATH . '/*.log.*');
            $threshold = time() - (30 * 24 * 60 * 60); // 30 days
            
            foreach ($oldLogs as $logFile) {
                if (filemtime($logFile) < $threshold) {
                    unlink($logFile);
                    $cleaned[] = 'Removed old log file: ' . basename($logFile);
                }
            }
        }
        
        // Clean temporary files
        $tempFiles = glob(sys_get_temp_dir() . '/n3xtweb_*');
        foreach ($tempFiles as $tempFile) {
            if (is_file($tempFile) && time() - filemtime($tempFile) > 3600) { // 1 hour old
                unlink($tempFile);
                $cleaned[] = 'Removed temporary file: ' . basename($tempFile);
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Optimize database tables
     */
    public static function optimizeDatabase() {
        try {
            $db = Database::getInstance();
            $prefix = Logger::getTablePrefix();
            
            // Get all tables with the prefix
            $tables = $db->fetchAll("SHOW TABLES LIKE '{$prefix}%'");
            $optimized = [];
            
            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                $db->execute("OPTIMIZE TABLE `{$tableName}`");
                $optimized[] = $tableName;
            }
            
            return $optimized;
            
        } catch (Exception $e) {
            Logger::log("Database optimization failed: " . $e->getMessage(), LOG_LEVEL_ERROR);
            return false;
        }
    }
}

// Initialize security headers if enabled
if (defined('ENABLE_SECURITY_HEADERS') && ENABLE_SECURITY_HEADERS) {
    Security::setSecurityHeaders();
}

// Start session
Session::start();
?>