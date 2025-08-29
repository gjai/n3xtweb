<?php
/**
 * N3XT WEB - Event Manager Module
 * 
 * Handles system event logging, monitoring, and notifications
 * 
 * @package N3xtWeb
 * @subpackage EventManager
 * @version 1.0.0
 * @author N3XT Communication
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

class EventManager {
    private static $instance = null;
    private $db;
    private $config;
    
    /**
     * Event types
     */
    const EVENT_TYPE_LOGIN = 'LOGIN';
    const EVENT_TYPE_LOGOUT = 'LOGOUT';
    const EVENT_TYPE_UPDATE = 'UPDATE';
    const EVENT_TYPE_BACKUP = 'BACKUP';
    const EVENT_TYPE_MAINTENANCE = 'MAINTENANCE';
    const EVENT_TYPE_SECURITY = 'SECURITY';
    const EVENT_TYPE_ERROR = 'ERROR';
    const EVENT_TYPE_SYSTEM = 'SYSTEM';
    
    /**
     * Event categories
     */
    const CATEGORY_AUTHENTICATION = 'authentication';
    const CATEGORY_SYSTEM = 'system';
    const CATEGORY_SECURITY = 'security';
    const CATEGORY_MAINTENANCE = 'maintenance';
    const CATEGORY_USER_ACTION = 'user_action';
    
    /**
     * Severity levels
     */
    const SEVERITY_DEBUG = 'DEBUG';
    const SEVERITY_INFO = 'INFO';
    const SEVERITY_WARNING = 'WARNING';
    const SEVERITY_ERROR = 'ERROR';
    const SEVERITY_CRITICAL = 'CRITICAL';
    
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
        } catch (Exception $e) {
            error_log("EventManager initialization failed: " . $e->getMessage());
        }
    }
    
    /**
     * Load module configuration
     */
    private function loadConfig() {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            $configData = $this->db->fetchAll("SELECT config_key, config_value FROM {$prefix}event_config");
            
            $this->config = [];
            foreach ($configData as $row) {
                $this->config[$row['config_key']] = $row['config_value'];
            }
        } catch (Exception $e) {
            // Use defaults if config not available
            $this->config = [
                'event_logging_enabled' => '1',
                'event_retention_days' => '90',
                'event_critical_notification' => '1',
                'event_debug_mode' => '0',
                'event_max_log_size_mb' => '50',
                'event_auto_archive' => '1',
                'event_webhook_enabled' => '0'
            ];
        }
    }
    
    /**
     * Log an event
     */
    public function logEvent($type, $category, $message, $data = null, $severity = self::SEVERITY_INFO, $userId = null) {
        if (!$this->isLoggingEnabled()) {
            return false;
        }
        
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "INSERT INTO {$prefix}events (event_type, event_category, event_message, event_data, severity, user_id, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $type,
                $category,
                $message,
                $data ? json_encode($data) : null,
                $severity,
                $userId,
                $this->getClientIP(),
                $this->getUserAgent()
            ];
            
            $this->db->execute($sql, $params);
            
            // Send notification for critical events
            if ($severity === self::SEVERITY_CRITICAL && $this->config['event_critical_notification'] === '1') {
                $this->sendCriticalNotification($type, $message, $data);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("EventManager: Failed to log event - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get events with filters
     */
    public function getEvents($filters = [], $limit = 100, $offset = 0) {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            $where = [];
            $params = [];
            
            if (!empty($filters['type'])) {
                $where[] = "event_type = ?";
                $params[] = $filters['type'];
            }
            
            if (!empty($filters['category'])) {
                $where[] = "event_category = ?";
                $params[] = $filters['category'];
            }
            
            if (!empty($filters['severity'])) {
                $where[] = "severity = ?";
                $params[] = $filters['severity'];
            }
            
            if (!empty($filters['user_id'])) {
                $where[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = "created_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "created_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT * FROM {$prefix}events {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("EventManager: Failed to get events - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get event statistics
     */
    public function getEventStats($days = 7) {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "SELECT 
                        event_type,
                        severity,
                        COUNT(*) as count
                    FROM {$prefix}events 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY event_type, severity
                    ORDER BY count DESC";
            
            return $this->db->fetchAll($sql, [$days]);
            
        } catch (Exception $e) {
            error_log("EventManager: Failed to get event stats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clean old events
     */
    public function cleanOldEvents() {
        if (!$this->isLoggingEnabled()) {
            return false;
        }
        
        try {
            $retentionDays = (int)$this->config['event_retention_days'];
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "DELETE FROM {$prefix}events WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $result = $this->db->execute($sql, [$retentionDays]);
            
            $this->logEvent(
                self::EVENT_TYPE_SYSTEM,
                self::CATEGORY_MAINTENANCE,
                "Cleaned old events older than {$retentionDays} days",
                ['retention_days' => $retentionDays],
                self::SEVERITY_INFO
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("EventManager: Failed to clean old events - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if logging is enabled
     */
    private function isLoggingEnabled() {
        return isset($this->config['event_logging_enabled']) && $this->config['event_logging_enabled'] === '1';
    }
    
    /**
     * Send critical event notification
     */
    private function sendCriticalNotification($type, $message, $data) {
        // TODO: Integrate with NotificationManager when available
        try {
            $subject = "N3XT WEB - Critical Event: {$type}";
            $body = "A critical event has occurred:\n\n";
            $body .= "Type: {$type}\n";
            $body .= "Message: {$message}\n";
            $body .= "Time: " . date('Y-m-d H:i:s') . "\n";
            $body .= "IP: " . $this->getClientIP() . "\n";
            
            if ($data) {
                $body .= "Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
            }
            
            // For now, just log it
            error_log("CRITICAL EVENT: {$subject} - {$message}");
            
        } catch (Exception $e) {
            error_log("EventManager: Failed to send critical notification - " . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Get user agent
     */
    private function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    /**
     * Update module configuration
     */
    public function updateConfig($key, $value) {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "UPDATE {$prefix}event_config SET config_value = ?, updated_at = NOW() WHERE config_key = ?";
            $this->db->execute($sql, [$value, $key]);
            
            // Update local config cache
            $this->config[$key] = $value;
            
            return true;
            
        } catch (Exception $e) {
            error_log("EventManager: Failed to update config - " . $e->getMessage());
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