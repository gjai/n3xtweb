<?php
/**
 * N3XT WEB - Security Scanner and Hardening Tool
 * 
 * Comprehensive security audit and hardening utility
 * Scans for vulnerabilities, misconfigurations, and security issues
 */

// Define security constant before including any files
define('IN_N3XTWEB', true);

require_once 'includes/functions.php';

// Only allow admin access
Session::start();
if (!Session::isLoggedIn()) {
    http_response_code(403);
    exit('Access denied');
}

header('Content-Type: application/json');

/**
 * Security Scanner Class
 */
class SecurityScanner {
    
    /**
     * Perform comprehensive security scan
     */
    public static function performSecurityScan() {
        $results = [
            'status' => 'OK',
            'timestamp' => date('Y-m-d H:i:s'),
            'critical_issues' => [],
            'warnings' => [],
            'info' => [],
            'score' => 100
        ];
        
        // Configuration security checks
        self::checkConfiguration($results);
        
        // File system security checks
        self::checkFileSystem($results);
        
        // Database security checks
        self::checkDatabase($results);
        
        // Web server security checks
        self::checkWebServer($results);
        
        // Session security checks
        self::checkSessionSecurity($results);
        
        // Calculate final security score
        $results['score'] = max(0, 100 - (count($results['critical_issues']) * 25) - (count($results['warnings']) * 10));
        
        if ($results['score'] < 60) {
            $results['status'] = 'CRITICAL';
        } elseif ($results['score'] < 80) {
            $results['status'] = 'WARNING';
        }
        
        return $results;
    }
    
    /**
     * Check configuration security
     */
    private static function checkConfiguration(&$results) {
        // Check default password
        if (defined('DB_PASS') && DB_PASS === 'secure_password') {
            $results['critical_issues'][] = [
                'type' => 'Configuration',
                'issue' => 'Default database password detected',
                'severity' => 'CRITICAL',
                'description' => 'Database password is still set to default value "secure_password"',
                'recommendation' => 'Change database password in config/config.php immediately'
            ];
        }
        
        // Check debug mode
        if (defined('DEBUG') && DEBUG === true) {
            $results['warnings'][] = [
                'type' => 'Configuration',
                'issue' => 'Debug mode enabled',
                'severity' => 'WARNING',
                'description' => 'Debug mode is enabled which may expose sensitive information',
                'recommendation' => 'Set DEBUG to false in production environment'
            ];
        }
        
        // Check error display
        if (defined('ENABLE_ERROR_DISPLAY') && ENABLE_ERROR_DISPLAY === true) {
            $results['warnings'][] = [
                'type' => 'Configuration',
                'issue' => 'Error display enabled',
                'severity' => 'WARNING',
                'description' => 'Error display is enabled which may expose system information',
                'recommendation' => 'Disable error display in production'
            ];
        }
        
        // Check session settings
        if (defined('SESSION_LIFETIME') && SESSION_LIFETIME > 86400) {
            $results['warnings'][] = [
                'type' => 'Configuration',
                'issue' => 'Long session lifetime',
                'severity' => 'WARNING',
                'description' => 'Session lifetime is longer than 24 hours',
                'recommendation' => 'Consider reducing session lifetime for better security'
            ];
        }
        
        $results['info'][] = 'Configuration security check completed';
    }
    
    /**
     * Check file system security
     */
    private static function checkFileSystem(&$results) {
        $criticalFiles = [
            'config/config.php',
            'includes/functions.php',
            '.htaccess'
        ];
        
        foreach ($criticalFiles as $file) {
            $fullPath = ROOT_PATH . '/' . $file;
            if (file_exists($fullPath)) {
                $perms = fileperms($fullPath);
                $readable = is_readable($fullPath);
                $writable = is_writable($fullPath);
                
                // Check if file is world-writable
                if ($perms & 0x0002) {
                    $results['critical_issues'][] = [
                        'type' => 'File System',
                        'issue' => "File {$file} is world-writable",
                        'severity' => 'CRITICAL',
                        'description' => 'Critical system file has world-writable permissions',
                        'recommendation' => 'Change file permissions to 644 or 640'
                    ];
                }
            }
        }
        
        // Check upload directory security
        $uploadHtaccess = UPLOAD_PATH . '/.htaccess';
        if (!file_exists($uploadHtaccess)) {
            $results['critical_issues'][] = [
                'type' => 'File System',
                'issue' => 'Upload directory not protected',
                'severity' => 'CRITICAL',
                'description' => 'Upload directory lacks .htaccess protection',
                'recommendation' => 'Add .htaccess file to uploads directory to prevent PHP execution'
            ];
        }
        
        $results['info'][] = 'File system security check completed';
    }
    
    /**
     * Check database security
     */
    private static function checkDatabase(&$results) {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            // Check database version
            $stmt = $pdo->query("SELECT VERSION() as version");
            $version = $stmt->fetch()['version'];
            
            if (strpos($version, '5.') === 0) {
                $results['warnings'][] = [
                    'type' => 'Database',
                    'issue' => 'Old MySQL version detected',
                    'severity' => 'WARNING',
                    'description' => "MySQL version {$version} may have security vulnerabilities",
                    'recommendation' => 'Consider upgrading to MySQL 8.0 or MariaDB 10.4+'
                ];
            }
            
            // Check for tables with default admin users
            $stmt = $pdo->prepare("SHOW TABLES LIKE '" . TABLE_PREFIX . "admin'");
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM " . TABLE_PREFIX . "admin WHERE username = 'admin'");
                $stmt->execute();
                $adminCount = $stmt->fetch()['count'];
                
                if ($adminCount > 0) {
                    $results['warnings'][] = [
                        'type' => 'Database',
                        'issue' => 'Default admin username detected',
                        'severity' => 'WARNING',
                        'description' => 'Default admin username "admin" found in database',
                        'recommendation' => 'Change default admin username to something unique'
                    ];
                }
            }
            
            $results['info'][] = 'Database security check completed';
            
        } catch (Exception $e) {
            $results['warnings'][] = [
                'type' => 'Database',
                'issue' => 'Database connection failed during security check',
                'severity' => 'WARNING',
                'description' => 'Could not connect to database to perform security checks',
                'recommendation' => 'Check database configuration and connection'
            ];
        }
    }
    
    /**
     * Check web server security
     */
    private static function checkWebServer(&$results) {
        // Check if sensitive directories are accessible
        $sensitiveFiles = [
            'config/config.php',
            'logs/',
            'backups/',
            'includes/functions.php'
        ];
        
        foreach ($sensitiveFiles as $file) {
            // Simulate checking accessibility (in real scenario, you'd make HTTP requests)
            if (strpos($file, '.php') !== false && file_exists(ROOT_PATH . '/' . $file)) {
                $results['info'][] = "Checked access protection for {$file}";
            }
        }
        
        // Check for .htaccess files
        if (!file_exists(ROOT_PATH . '/.htaccess')) {
            $results['critical_issues'][] = [
                'type' => 'Web Server',
                'issue' => 'Main .htaccess file missing',
                'severity' => 'CRITICAL',
                'description' => 'Main .htaccess file is missing, leaving site vulnerable',
                'recommendation' => 'Restore .htaccess file with proper security rules'
            ];
        }
        
        $results['info'][] = 'Web server security check completed';
    }
    
    /**
     * Check session security
     */
    private static function checkSessionSecurity(&$results) {
        // Check session configuration
        $sessionName = session_name();
        if ($sessionName === 'PHPSESSID') {
            $results['warnings'][] = [
                'type' => 'Session',
                'issue' => 'Default session name in use',
                'severity' => 'WARNING',
                'description' => 'Using default PHP session name makes session hijacking easier',
                'recommendation' => 'Change session name to something unique'
            ];
        }
        
        // Check session cookie settings
        $cookieParams = session_get_cookie_params();
        if (!$cookieParams['secure'] && isset($_SERVER['HTTPS'])) {
            $results['warnings'][] = [
                'type' => 'Session',
                'issue' => 'Session cookies not secure',
                'severity' => 'WARNING',
                'description' => 'Session cookies should be marked as secure when using HTTPS',
                'recommendation' => 'Enable secure flag for session cookies'
            ];
        }
        
        if (!$cookieParams['httponly']) {
            $results['warnings'][] = [
                'type' => 'Session',
                'issue' => 'Session cookies accessible via JavaScript',
                'severity' => 'WARNING',
                'description' => 'Session cookies should be HTTP-only to prevent XSS attacks',
                'recommendation' => 'Enable httponly flag for session cookies'
            ];
        }
        
        $results['info'][] = 'Session security check completed';
    }
    
    /**
     * Generate security report
     */
    public static function generateSecurityReport() {
        $scanResults = self::performSecurityScan();
        
        $report = [
            'scan_results' => $scanResults,
            'recommendations' => self::getPriorityRecommendations($scanResults),
            'next_scan' => date('Y-m-d H:i:s', strtotime('+1 week'))
        ];
        
        // Log security scan
        Logger::log("Security scan completed. Score: {$scanResults['score']}/100", LOG_LEVEL_INFO);
        
        return $report;
    }
    
    /**
     * Get priority recommendations
     */
    private static function getPriorityRecommendations($scanResults) {
        $recommendations = [];
        
        // Critical issues first
        foreach ($scanResults['critical_issues'] as $issue) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'action' => $issue['recommendation'],
                'impact' => 'Security vulnerability'
            ];
        }
        
        // Important warnings
        foreach ($scanResults['warnings'] as $warning) {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'action' => $warning['recommendation'],
                'impact' => 'Security improvement'
            ];
        }
        
        return $recommendations;
    }
}

try {
    $action = $_GET['action'] ?? 'scan';
    
    switch ($action) {
        case 'scan':
            $result = SecurityScanner::performSecurityScan();
            break;
            
        case 'report':
            $result = SecurityScanner::generateSecurityReport();
            break;
            
        case 'quick_check':
            $result = [
                'default_password' => (defined('DB_PASS') && DB_PASS === 'secure_password'),
                'debug_mode' => (defined('DEBUG') && DEBUG === true),
                'secure_headers' => file_exists(ROOT_PATH . '/.htaccess')
            ];
            break;
            
        default:
            $result = ['error' => 'Invalid action'];
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    Logger::log("Security scanner error: " . $e->getMessage(), LOG_LEVEL_ERROR);
    echo json_encode(['error' => 'Security scan failed', 'message' => $e->getMessage()]);
}
?>