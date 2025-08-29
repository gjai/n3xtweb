<?php
/**
 * N3XT WEB - Security Manager Module
 * 
 * Handles security policies, threat detection, and protection mechanisms
 * 
 * @package N3xtWeb
 * @subpackage SecurityManager
 * @version 1.0.0
 * @author N3XT Communication
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

class SecurityManager {
    private static $instance = null;
    private $db;
    private $config;
    private $eventManager;
    
    /**
     * Security threat levels
     */
    const THREAT_LOW = 'LOW';
    const THREAT_MEDIUM = 'MEDIUM';
    const THREAT_HIGH = 'HIGH';
    const THREAT_CRITICAL = 'CRITICAL';
    
    /**
     * Security events
     */
    const EVENT_LOGIN_SUCCESS = 'login_success';
    const EVENT_LOGIN_FAILED = 'login_failed';
    const EVENT_LOGIN_BLOCKED = 'login_blocked';
    const EVENT_IP_BLOCKED = 'ip_blocked';
    const EVENT_SUSPICIOUS_ACTIVITY = 'suspicious_activity';
    const EVENT_BRUTEFORCE_ATTEMPT = 'bruteforce_attempt';
    const EVENT_SECURITY_SCAN = 'security_scan';
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        try {
            $this->db = Database::getInstance();
            $this->loadConfig();
            
            // Initialize EventManager if available
            if (class_exists('EventManager')) {
                $this->eventManager = EventManager::getInstance();
            }
        } catch (Exception $e) {
            error_log("SecurityManager initialization failed: " . $e->getMessage());
        }
    }
    
    /**
     * Load module configuration
     */
    private function loadConfig() {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            $configData = $this->db->fetchAll("SELECT config_key, config_value FROM {$prefix}security_config");
            
            $this->config = [];
            foreach ($configData as $row) {
                $this->config[$row['config_key']] = $row['config_value'];
            }
        } catch (Exception $e) {
            // Use defaults if config not available
            $this->config = [
                'security_login_attempts_max' => '5',
                'security_lockout_duration' => '900',
                'security_session_timeout' => '3600',
                'security_password_min_length' => '8',
                'security_password_complexity' => '1',
                'security_captcha_enabled' => '0',
                'security_two_factor_enabled' => '0',
                'security_audit_logging' => '1'
            ];
        }
    }
    
    /**
     * Check if IP address is blocked
     */
    public function isIPBlocked($ip) {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            // Check temporary blocks from login attempts
            $sql = "SELECT COUNT(*) as blocked FROM {$prefix}login_attempts 
                    WHERE ip_address = ? AND blocked_until > NOW()";
            $result = $this->db->fetchOne($sql, [$ip]);
            
            if ($result && $result['blocked'] > 0) {
                return true;
            }
            
            // Check blacklist
            $blacklist = $this->getConfig('security_ip_blacklist');
            if ($blacklist) {
                $blacklistedIPs = array_map('trim', explode(',', $blacklist));
                if (in_array($ip, $blacklistedIPs)) {
                    return true;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("SecurityManager: Failed to check IP block status - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if IP address is whitelisted
     */
    public function isIPWhitelisted($ip) {
        try {
            $whitelist = $this->getConfig('security_ip_whitelist');
            if ($whitelist) {
                $whitelistedIPs = array_map('trim', explode(',', $whitelist));
                return in_array($ip, $whitelistedIPs);
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("SecurityManager: Failed to check IP whitelist status - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record login attempt
     */
    public function recordLoginAttempt($ip, $username, $success, $failureReason = null) {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $sql = "INSERT INTO {$prefix}login_attempts (ip_address, username, success, failure_reason, user_agent, attempt_time) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            $this->db->execute($sql, [$ip, $username, $success ? 1 : 0, $failureReason, $userAgent]);
            
            // Check for brute force attempts
            if (!$success) {
                $this->checkBruteForceAttempts($ip);
            }
            
            // Log event
            if ($this->eventManager) {
                $eventType = $success ? self::EVENT_LOGIN_SUCCESS : self::EVENT_LOGIN_FAILED;
                $severity = $success ? EventManager::SEVERITY_INFO : EventManager::SEVERITY_WARNING;
                
                $this->eventManager->logEvent(
                    EventManager::EVENT_TYPE_LOGIN,
                    EventManager::CATEGORY_AUTHENTICATION,
                    $success ? "Successful login for user: {$username}" : "Failed login attempt for user: {$username}",
                    [
                        'username' => $username,
                        'ip' => $ip,
                        'success' => $success,
                        'failure_reason' => $failureReason
                    ],
                    $severity
                );
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("SecurityManager: Failed to record login attempt - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for brute force attempts and block IP if necessary
     */
    private function checkBruteForceAttempts($ip) {
        try {
            $maxAttempts = (int)$this->getConfig('security_login_attempts_max');
            $lockoutDuration = (int)$this->getConfig('security_lockout_duration');
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            // Count failed attempts in the last hour
            $sql = "SELECT COUNT(*) as attempts FROM {$prefix}login_attempts 
                    WHERE ip_address = ? AND success = 0 AND attempt_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            $result = $this->db->fetchOne($sql, [$ip]);
            
            if ($result && $result['attempts'] >= $maxAttempts) {
                // Block the IP
                $blockedUntil = date('Y-m-d H:i:s', time() + $lockoutDuration);
                $sql = "UPDATE {$prefix}login_attempts 
                        SET blocked_until = ? 
                        WHERE ip_address = ? AND attempt_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
                $this->db->execute($sql, [$blockedUntil, $ip]);
                
                // Log security event
                if ($this->eventManager) {
                    $this->eventManager->logEvent(
                        EventManager::EVENT_TYPE_SECURITY,
                        EventManager::CATEGORY_SECURITY,
                        "IP address blocked due to brute force attempts: {$ip}",
                        [
                            'ip' => $ip,
                            'attempts' => $result['attempts'],
                            'blocked_until' => $blockedUntil
                        ],
                        EventManager::SEVERITY_WARNING
                    );
                }
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("SecurityManager: Failed to check brute force attempts - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate password strength
     */
    public function validatePasswordStrength($password) {
        $minLength = (int)$this->getConfig('security_password_min_length');
        $requireComplexity = $this->getConfig('security_password_complexity') === '1';
        
        $errors = [];
        
        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long";
        }
        
        if ($requireComplexity) {
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
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Generate secure session token
     */
    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Check session validity
     */
    public function isSessionValid($sessionStart) {
        $timeout = (int)$this->getConfig('security_session_timeout');
        return (time() - $sessionStart) < $timeout;
    }
    
    /**
     * Sanitize input to prevent XSS
     */
    public function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Perform security scan
     */
    public function performSecurityScan() {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'threat_level' => self::THREAT_LOW,
            'issues' => [],
            'recommendations' => []
        ];
        
        try {
            // Check for suspicious login patterns
            $suspiciousLogins = $this->checkSuspiciousLoginPatterns();
            if (!empty($suspiciousLogins)) {
                $results['issues'][] = 'Suspicious login patterns detected';
                $results['threat_level'] = self::THREAT_MEDIUM;
            }
            
            // Check for blocked IPs
            $blockedIPs = $this->getBlockedIPs();
            if (count($blockedIPs) > 10) {
                $results['issues'][] = 'High number of blocked IPs detected';
                $results['threat_level'] = self::THREAT_MEDIUM;
            }
            
            // Check configuration security
            $configIssues = $this->checkConfigurationSecurity();
            if (!empty($configIssues)) {
                $results['issues'] = array_merge($results['issues'], $configIssues);
                $results['threat_level'] = self::THREAT_HIGH;
            }
            
            // Generate recommendations
            $results['recommendations'] = $this->generateSecurityRecommendations($results);
            
            // Log security scan
            if ($this->eventManager) {
                $this->eventManager->logEvent(
                    EventManager::EVENT_TYPE_SECURITY,
                    EventManager::CATEGORY_SECURITY,
                    "Security scan completed",
                    $results,
                    EventManager::SEVERITY_INFO
                );
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("SecurityManager: Security scan failed - " . $e->getMessage());
            $results['issues'][] = 'Security scan failed to complete';
            $results['threat_level'] = self::THREAT_CRITICAL;
            return $results;
        }
    }
    
    /**
     * Check for suspicious login patterns
     */
    private function checkSuspiciousLoginPatterns() {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            // Check for multiple failed logins from different IPs for same user in short time
            $sql = "SELECT username, COUNT(DISTINCT ip_address) as unique_ips, COUNT(*) as attempts
                    FROM {$prefix}login_attempts 
                    WHERE success = 0 AND attempt_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    GROUP BY username
                    HAVING unique_ips > 3 AND attempts > 10";
            
            return $this->db->fetchAll($sql);
            
        } catch (Exception $e) {
            error_log("SecurityManager: Failed to check suspicious login patterns - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get currently blocked IPs
     */
    private function getBlockedIPs() {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "SELECT DISTINCT ip_address FROM {$prefix}login_attempts 
                    WHERE blocked_until > NOW()";
            
            return $this->db->fetchAll($sql);
            
        } catch (Exception $e) {
            error_log("SecurityManager: Failed to get blocked IPs - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check configuration security
     */
    private function checkConfigurationSecurity() {
        $issues = [];
        
        // Check if default passwords are being used
        if ($this->getConfig('security_password_min_length') < 8) {
            $issues[] = 'Minimum password length is too short';
        }
        
        // Check if two-factor authentication is disabled
        if ($this->getConfig('security_two_factor_enabled') === '0') {
            $issues[] = 'Two-factor authentication is disabled';
        }
        
        // Check if CAPTCHA is disabled
        if ($this->getConfig('security_captcha_enabled') === '0') {
            $issues[] = 'CAPTCHA protection is disabled';
        }
        
        return $issues;
    }
    
    /**
     * Generate security recommendations
     */
    private function generateSecurityRecommendations($scanResults) {
        $recommendations = [];
        
        if ($scanResults['threat_level'] === self::THREAT_HIGH) {
            $recommendations[] = 'Enable two-factor authentication';
            $recommendations[] = 'Review and update security policies';
            $recommendations[] = 'Consider implementing additional monitoring';
        }
        
        if ($scanResults['threat_level'] === self::THREAT_CRITICAL) {
            $recommendations[] = 'Immediate security review required';
            $recommendations[] = 'Consider enabling maintenance mode';
            $recommendations[] = 'Contact security team';
        }
        
        $recommendations[] = 'Regularly update passwords';
        $recommendations[] = 'Monitor login attempts';
        $recommendations[] = 'Keep system updated';
        
        return $recommendations;
    }
    
    /**
     * Update module configuration
     */
    public function updateConfig($key, $value) {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "UPDATE {$prefix}security_config SET config_value = ?, updated_at = NOW() WHERE config_key = ?";
            $this->db->execute($sql, [$value, $key]);
            
            // Update local config cache
            $this->config[$key] = $value;
            
            return true;
            
        } catch (Exception $e) {
            error_log("SecurityManager: Failed to update config - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get module configuration
     */
    public function getConfig($key = null) {
        if ($key === null) {
            return $this->config;
        }
        
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }
}