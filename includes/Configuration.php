<?php
/**
 * N3XT WEB - Configuration Management System
 * 
 * This class handles all configuration management, storing settings in database
 * instead of hardcoded constants, with fallback to defaults.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

class Configuration {
    private static $instance = null;
    private static $cache = [];
    private static $loaded = false;
    
    /**
     * Configuration categories and their default values
     */
    private static $defaultConfig = [
        // System Configuration
        'maintenance_mode' => false,
        'system_version' => '2.0.0',
        'root_path' => '',
        'log_path' => '',
        'backup_path' => '',
        'upload_path' => '',
        'admin_path' => '',
        
        // Security Settings
        'csrf_token_lifetime' => 3600,
        'session_lifetime' => 86400,
        'admin_session_timeout' => 14400,
        'max_login_attempts' => 5,
        'login_lockout_time' => 900,
        'password_min_length' => 8,
        'enable_captcha' => false,
        'enable_login_attempts_limit' => true,
        'enable_ip_blocking' => true,
        'enable_ip_tracking' => true,
        'enable_database_logging' => true,
        'enable_security_headers' => true,
        
        // Performance Settings
        'enable_caching' => true,
        'cache_ttl_default' => 3600,
        'cache_ttl_queries' => 300,
        'enable_gzip' => true,
        'enable_asset_optimization' => true,
        
        // Debug Settings
        'debug' => false,
        'enable_error_display' => false,
        'log_queries' => false,
        
        // GitHub Integration
        'github_owner' => 'gjai',
        'github_repo' => 'n3xtweb',
        'github_api_url' => 'https://api.github.com',
        
        // Email Configuration
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_from' => '',
        'smtp_from_name' => 'N3XT WEB',
        
        // Theme/CSS Configuration
        'theme_primary_color' => '#667eea',
        'theme_secondary_color' => '#764ba2',
        'theme_success_color' => '#27ae60',
        'theme_danger_color' => '#e74c3c',
        'theme_warning_color' => '#f39c12',
        'theme_info_color' => '#3498db',
        'theme_font_family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif',
        'theme_font_size' => '14px',
        'theme_border_radius' => '8px',
        
        // Site Configuration
        'site_name' => 'N3XT WEB',
        'site_description' => 'Professional web management system',
        'site_logo' => '',
        'site_favicon' => '/fav.png',
        'site_language' => 'fr',
        'site_timezone' => 'Europe/Paris'
    ];
    
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
     * Load all configuration from database
     */
    public static function loadConfig() {
        if (self::$loaded) {
            return;
        }
        
        try {
            // Check if Database class is available before trying to use it
            if (!class_exists('Database')) {
                throw new Exception('Database class not yet available');
            }
            
            $db = Database::getInstance();
            $prefix = self::getTablePrefix();
            
            $settings = $db->fetchAll("SELECT setting_key, setting_value FROM {$prefix}system_settings");
            
            foreach ($settings as $setting) {
                self::$cache[$setting['setting_key']] = self::parseValue($setting['setting_value']);
            }
            
            self::$loaded = true;
            
        } catch (Exception $e) {
            // Database not available, use defaults
            error_log("Configuration: Failed to load from database, using defaults: " . $e->getMessage());
            self::$cache = self::$defaultConfig;
            self::$loaded = true;
        }
    }
    
    /**
     * Get configuration value
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::loadConfig();
        }
        
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        // Return default from our config array or provided default
        if (isset(self::$defaultConfig[$key])) {
            return self::$defaultConfig[$key];
        }
        
        return $default;
    }
    
    /**
     * Set configuration value (saves to database)
     */
    public static function set($key, $value) {
        try {
            $db = Database::getInstance();
            $prefix = self::getTablePrefix();
            
            $serializedValue = self::serializeValue($value);
            
            $db->execute(
                "INSERT INTO {$prefix}system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
                [$key, $serializedValue, $serializedValue]
            );
            
            // Update cache
            self::$cache[$key] = $value;
            
            return true;
            
        } catch (Exception $e) {
            error_log("Configuration: Failed to save setting {$key}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get multiple configuration values by category
     */
    public static function getCategory($category) {
        $result = [];
        $prefix = $category . '_';
        
        foreach (self::$defaultConfig as $key => $defaultValue) {
            if (strpos($key, $prefix) === 0) {
                $result[$key] = self::get($key);
            }
        }
        
        return $result;
    }
    
    /**
     * Set multiple configuration values
     */
    public static function setMultiple($settings) {
        $success = true;
        
        foreach ($settings as $key => $value) {
            if (!self::set($key, $value)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get all configuration as array
     */
    public static function getAll() {
        if (!self::$loaded) {
            self::loadConfig();
        }
        
        $result = self::$defaultConfig;
        
        foreach (self::$cache as $key => $value) {
            $result[$key] = $value;
        }
        
        return $result;
    }
    
    /**
     * Generate CSS from theme configuration
     */
    public static function generateCSS() {
        $config = self::getCategory('theme');
        
        $css = ":root {\n";
        $css .= "    --primary-color: " . self::get('theme_primary_color') . ";\n";
        $css .= "    --secondary-color: " . self::get('theme_secondary_color') . ";\n";
        $css .= "    --success-color: " . self::get('theme_success_color') . ";\n";
        $css .= "    --danger-color: " . self::get('theme_danger_color') . ";\n";
        $css .= "    --warning-color: " . self::get('theme_warning_color') . ";\n";
        $css .= "    --info-color: " . self::get('theme_info_color') . ";\n";
        $css .= "    --font-family: " . self::get('theme_font_family') . ";\n";
        $css .= "    --font-size: " . self::get('theme_font_size') . ";\n";
        $css .= "    --border-radius: " . self::get('theme_border_radius') . ";\n";
        $css .= "}\n\n";
        
        // Add responsive styles
        $css .= "body { font-family: var(--font-family); font-size: var(--font-size); }\n";
        $css .= ".btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }\n";
        $css .= ".btn-secondary { background-color: var(--secondary-color); border-color: var(--secondary-color); }\n";
        $css .= ".btn-success { background-color: var(--success-color); border-color: var(--success-color); }\n";
        $css .= ".btn-danger { background-color: var(--danger-color); border-color: var(--danger-color); }\n";
        $css .= ".btn-warning { background-color: var(--warning-color); border-color: var(--warning-color); }\n";
        $css .= ".btn-info { background-color: var(--info-color); border-color: var(--info-color); }\n";
        $css .= ".alert-success { border-color: var(--success-color); background-color: var(--success-color)22; color: var(--success-color); }\n";
        $css .= ".alert-danger { border-color: var(--danger-color); background-color: var(--danger-color)22; color: var(--danger-color); }\n";
        $css .= ".alert-warning { border-color: var(--warning-color); background-color: var(--warning-color)22; color: var(--warning-color); }\n";
        $css .= ".alert-info { border-color: var(--info-color); background-color: var(--info-color)22; color: var(--info-color); }\n";
        $css .= ".card, .btn, .form-control { border-radius: var(--border-radius); }\n";
        
        return $css;
    }
    
    /**
     * Get table prefix (special handling since it might not be in database yet)
     */
    private static function getTablePrefix() {
        // First try to get from database
        if (isset(self::$cache['table_prefix'])) {
            return self::$cache['table_prefix'];
        }
        
        // Try defined constant
        if (defined('TABLE_PREFIX')) {
            return TABLE_PREFIX;
        }
        
        // Default fallback
        return 'n3xtweb_';
    }
    
    /**
     * Parse value from database (handle serialization)
     */
    private static function parseValue($value) {
        // Handle boolean values
        if ($value === '1' || $value === 'true') {
            return true;
        }
        if ($value === '0' || $value === 'false') {
            return false;
        }
        
        // Handle numbers
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        
        // Handle JSON
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        
        // Return as string
        return $value;
    }
    
    /**
     * Serialize value for database storage
     */
    private static function serializeValue($value) {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        
        return (string)$value;
    }
    
    /**
     * Reset configuration cache (useful for testing)
     */
    public static function clearCache() {
        self::$cache = [];
        self::$loaded = false;
    }
    
    /**
     * Initialize default configuration in database
     */
    public static function initializeDefaults() {
        try {
            $db = Database::getInstance();
            $prefix = self::getTablePrefix();
            
            foreach (self::$defaultConfig as $key => $value) {
                $serializedValue = self::serializeValue($value);
                $db->execute(
                    "INSERT IGNORE INTO {$prefix}system_settings (setting_key, setting_value) VALUES (?, ?)",
                    [$key, $serializedValue]
                );
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Configuration: Failed to initialize defaults: " . $e->getMessage());
            return false;
        }
    }
}

// Helper functions for backward compatibility with existing constants
if (!function_exists('get_config')) {
    function get_config($key, $default = null) {
        return Configuration::get($key, $default);
    }
}

// Configuration will be loaded explicitly after Database class is available
// Removed auto-load to prevent circular dependency issues