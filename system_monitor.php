<?php
/**
 * N3XT WEB - System Monitor Dashboard
 * 
 * Comprehensive system monitoring, security analysis, and health checking
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

/**
 * System Monitor Class
 */
class SystemMonitor {
    
    /**
     * Get comprehensive system overview
     */
    public static function getSystemOverview() {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'server_info' => self::getServerInfo(),
            'security_status' => self::getSecurityStatus(),
            'performance_metrics' => self::getPerformanceMetrics(),
            'log_analysis' => self::getLogAnalysis(),
            'disk_usage' => self::getDiskUsage(),
            'database_status' => self::getDatabaseStatus(),
            'file_integrity' => self::getFileIntegrityStatus()
        ];
    }
    
    /**
     * Get server information
     */
    private static function getServerInfo() {
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
            'server_admin' => $_SERVER['SERVER_ADMIN'] ?? 'unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'timezone' => date_default_timezone_get(),
            'extensions' => self::getImportantExtensions()
        ];
    }
    
    /**
     * Check important PHP extensions
     */
    private static function getImportantExtensions() {
        $extensions = ['pdo', 'pdo_mysql', 'mysqli', 'gd', 'curl', 'zip', 'mbstring', 'openssl'];
        $status = [];
        
        foreach ($extensions as $ext) {
            $status[$ext] = extension_loaded($ext);
        }
        
        return $status;
    }
    
    /**
     * Get security status
     */
    private static function getSecurityStatus() {
        $status = [
            'overall_score' => 100,
            'issues' => [],
            'warnings' => []
        ];
        
        // Check default password
        if (defined('DB_PASS') && DB_PASS === 'secure_password') {
            $status['issues'][] = 'Default database password detected';
            $status['overall_score'] -= 30;
        }
        
        // Check debug mode
        if (defined('DEBUG') && DEBUG === true) {
            $status['warnings'][] = 'Debug mode is enabled';
            $status['overall_score'] -= 10;
        }
        
        // Check file permissions
        $criticalFiles = ['.htaccess', 'config/config.php', 'includes/functions.php'];
        foreach ($criticalFiles as $file) {
            $fullPath = ROOT_PATH . '/' . $file;
            if (file_exists($fullPath)) {
                $perms = fileperms($fullPath);
                if ($perms & 0x0002) { // World writable
                    $status['issues'][] = "File {$file} is world-writable";
                    $status['overall_score'] -= 20;
                }
            }
        }
        
        // Check .htaccess files
        $protectedDirs = ['logs', 'backups', 'config', 'uploads'];
        foreach ($protectedDirs as $dir) {
            $htaccessPath = ROOT_PATH . '/' . $dir . '/.htaccess';
            if ($dir === 'uploads' && !file_exists($htaccessPath)) {
                $status['warnings'][] = "Directory {$dir} lacks .htaccess protection";
                $status['overall_score'] -= 5;
            }
        }
        
        return $status;
    }
    
    /**
     * Get performance metrics
     */
    private static function getPerformanceMetrics() {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        // Simple database query to test performance
        try {
            $db = Database::getInstance();
            $queryStart = microtime(true);
            $db->getConnection()->query("SELECT 1");
            $queryTime = (microtime(true) - $queryStart) * 1000; // ms
        } catch (Exception $e) {
            $queryTime = null;
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        return [
            'execution_time' => ($endTime - $startTime) * 1000, // ms
            'memory_usage' => $endMemory - $startMemory,
            'peak_memory' => memory_get_peak_usage(true),
            'current_memory' => memory_get_usage(true),
            'db_query_time' => $queryTime,
            'load_average' => self::getLoadAverage()
        ];
    }
    
    /**
     * Get server load average (Unix/Linux only)
     */
    private static function getLoadAverage() {
        if (function_exists('sys_getloadavg')) {
            return sys_getloadavg();
        }
        
        if (is_readable('/proc/loadavg')) {
            $loadavg = file_get_contents('/proc/loadavg');
            $load = explode(' ', $loadavg);
            return [(float)$load[0], (float)$load[1], (float)$load[2]];
        }
        
        return null;
    }
    
    /**
     * Get log analysis summary
     */
    private static function getLogAnalysis() {
        $analysis = [
            'log_stats' => Logger::getLogStats(),
            'security_analysis' => Logger::analyzeSecurityLogs('access'),
            'recent_errors' => self::getRecentLogEntries('system', 'ERROR', 10),
            'recent_warnings' => self::getRecentLogEntries('system', 'WARNING', 5)
        ];
        
        return $analysis;
    }
    
    /**
     * Get recent log entries of specific level
     */
    private static function getRecentLogEntries($logFile, $level, $limit = 10) {
        $logPath = LOG_PATH . "/{$logFile}.log";
        
        if (!file_exists($logPath)) {
            return [];
        }
        
        $entries = [];
        $handle = fopen($logPath, 'r');
        
        if ($handle) {
            // Read file backwards for recent entries
            $lines = [];
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, "[$level]") !== false) {
                    $lines[] = trim($line);
                }
            }
            fclose($handle);
            
            // Return most recent entries
            $entries = array_slice(array_reverse($lines), 0, $limit);
        }
        
        return $entries;
    }
    
    /**
     * Get disk usage information
     */
    private static function getDiskUsage() {
        $usage = [
            'total_space' => disk_total_space('.'),
            'free_space' => disk_free_space('.'),
            'used_space' => disk_total_space('.') - disk_free_space('.'),
            'directories' => []
        ];
        
        // Check specific directories
        $checkDirs = ['logs', 'backups', 'uploads', 'cache'];
        foreach ($checkDirs as $dir) {
            $dirPath = ROOT_PATH . '/' . $dir;
            if (is_dir($dirPath)) {
                $usage['directories'][$dir] = self::getDirectorySize($dirPath);
            }
        }
        
        return $usage;
    }
    
    /**
     * Calculate directory size recursively
     */
    private static function getDirectorySize($directory) {
        $size = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($files as $file) {
            $size += $file->getSize();
        }
        
        return $size;
    }
    
    /**
     * Get database status
     */
    private static function getDatabaseStatus() {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            // Get database version
            $stmt = $pdo->query("SELECT VERSION() as version");
            $version = $stmt->fetch()['version'];
            
            // Get database size
            $stmt = $pdo->prepare("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'size_mb' FROM information_schema.tables WHERE table_schema = ?");
            $stmt->execute([DB_NAME]);
            $sizeResult = $stmt->fetch();
            
            // Get table count
            $stmt = $pdo->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
            $tableCount = $stmt->fetch()['table_count'];
            
            // Get connection count
            $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
            $connections = $stmt->fetch()['Value'] ?? 'unknown';
            
            return [
                'status' => 'connected',
                'version' => $version,
                'size_mb' => $sizeResult['size_mb'] ?? 0,
                'table_count' => $tableCount,
                'connections' => $connections,
                'charset' => DB_CHARSET
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check file integrity of critical files
     */
    private static function getFileIntegrityStatus() {
        $criticalFiles = [
            'index.php',
            'config/config.php',
            'includes/functions.php',
            '.htaccess',
            'bo/index.php',
            'bo/login.php'
        ];
        
        $status = [
            'total_files' => count($criticalFiles),
            'missing_files' => [],
            'modified_files' => [],
            'last_check' => date('Y-m-d H:i:s')
        ];
        
        foreach ($criticalFiles as $file) {
            $fullPath = ROOT_PATH . '/' . $file;
            
            if (!file_exists($fullPath)) {
                $status['missing_files'][] = $file;
            } else {
                // Check if file was modified recently (within last 24 hours)
                $modTime = filemtime($fullPath);
                if ($modTime > (time() - 86400)) {
                    $status['modified_files'][] = [
                        'file' => $file,
                        'modified' => date('Y-m-d H:i:s', $modTime)
                    ];
                }
            }
        }
        
        return $status;
    }
    
    /**
     * Generate health score
     */
    public static function getHealthScore() {
        $overview = self::getSystemOverview();
        $score = 100;
        
        // Security issues
        $securityScore = $overview['security_status']['overall_score'];
        $score = min($score, $securityScore);
        
        // Database connectivity
        if ($overview['database_status']['status'] === 'error') {
            $score -= 30;
        }
        
        // Disk usage (reduce score if > 90% full)
        $diskUsage = $overview['disk_usage'];
        $usagePercent = (($diskUsage['used_space'] / $diskUsage['total_space']) * 100);
        if ($usagePercent > 90) {
            $score -= 20;
        } elseif ($usagePercent > 80) {
            $score -= 10;
        }
        
        // Recent errors
        $errorCount = count($overview['log_analysis']['recent_errors']);
        if ($errorCount > 10) {
            $score -= 15;
        } elseif ($errorCount > 5) {
            $score -= 10;
        }
        
        return max(0, $score);
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? 'overview';
    
    try {
        switch ($action) {
            case 'overview':
                $result = SystemMonitor::getSystemOverview();
                break;
                
            case 'health_score':
                $result = ['health_score' => SystemMonitor::getHealthScore()];
                break;
                
            case 'security_only':
                $result = SystemMonitor::getSystemOverview()['security_status'];
                break;
                
            case 'performance_only':
                $result = SystemMonitor::getSystemOverview()['performance_metrics'];
                break;
                
            default:
                $result = ['error' => 'Invalid action'];
        }
        
        echo json_encode($result, JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        Logger::log("System monitor error: " . $e->getMessage(), LOG_LEVEL_ERROR);
        echo json_encode(['error' => 'System monitoring failed', 'message' => $e->getMessage()]);
    }
    
    exit;
}

// HTML Dashboard (if accessed directly)
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>N3XT WEB - System Monitor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .dashboard { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .widget { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .widget h3 { margin-top: 0; color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .status-good { color: #27ae60; }
        .status-warning { color: #f39c12; }
        .status-error { color: #e74c3c; }
        .metric { margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 4px; }
        .health-score { font-size: 2em; font-weight: bold; text-align: center; padding: 20px; }
        .log-entry { font-family: monospace; font-size: 12px; padding: 5px; margin: 2px 0; background: #f8f9fa; border-left: 3px solid #007cba; }
        .error-entry { border-left-color: #e74c3c; }
        .warning-entry { border-left-color: #f39c12; }
    </style>
</head>
<body>
    <h1>N3XT WEB - System Monitor Dashboard</h1>
    
    <div class="dashboard">
        <div class="widget">
            <h3>System Health Score</h3>
            <div id="health-score" class="health-score">Loading...</div>
        </div>
        
        <div class="widget">
            <h3>Security Status</h3>
            <div id="security-status">Loading...</div>
        </div>
        
        <div class="widget">
            <h3>Performance Metrics</h3>
            <div id="performance-metrics">Loading...</div>
        </div>
        
        <div class="widget">
            <h3>Database Status</h3>
            <div id="database-status">Loading...</div>
        </div>
        
        <div class="widget">
            <h3>Recent Errors</h3>
            <div id="recent-errors">Loading...</div>
        </div>
        
        <div class="widget">
            <h3>Log Analysis</h3>
            <div id="log-analysis">Loading...</div>
        </div>
    </div>
    
    <script>
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function loadSystemData() {
            fetch('?action=overview')
                .then(response => response.json())
                .then(data => {
                    updateHealthScore(data);
                    updateSecurityStatus(data.security_status);
                    updatePerformanceMetrics(data.performance_metrics);
                    updateDatabaseStatus(data.database_status);
                    updateRecentErrors(data.log_analysis.recent_errors);
                    updateLogAnalysis(data.log_analysis);
                })
                .catch(error => console.error('Error loading system data:', error));
        }
        
        function updateHealthScore(data) {
            fetch('?action=health_score')
                .then(response => response.json())
                .then(result => {
                    const score = result.health_score;
                    const element = document.getElementById('health-score');
                    element.textContent = score + '%';
                    
                    if (score >= 90) {
                        element.className = 'health-score status-good';
                    } else if (score >= 70) {
                        element.className = 'health-score status-warning';
                    } else {
                        element.className = 'health-score status-error';
                    }
                });
        }
        
        function updateSecurityStatus(security) {
            const element = document.getElementById('security-status');
            let html = `<div class="metric">Overall Score: <strong>${security.overall_score}%</strong></div>`;
            
            if (security.issues.length > 0) {
                html += '<h4 class="status-error">Critical Issues:</h4>';
                security.issues.forEach(issue => {
                    html += `<div class="metric status-error">${issue}</div>`;
                });
            }
            
            if (security.warnings.length > 0) {
                html += '<h4 class="status-warning">Warnings:</h4>';
                security.warnings.forEach(warning => {
                    html += `<div class="metric status-warning">${warning}</div>`;
                });
            }
            
            if (security.issues.length === 0 && security.warnings.length === 0) {
                html += '<div class="metric status-good">No security issues detected</div>';
            }
            
            element.innerHTML = html;
        }
        
        function updatePerformanceMetrics(performance) {
            const element = document.getElementById('performance-metrics');
            let html = `
                <div class="metric">Execution Time: ${performance.execution_time.toFixed(2)} ms</div>
                <div class="metric">Current Memory: ${formatBytes(performance.current_memory)}</div>
                <div class="metric">Peak Memory: ${formatBytes(performance.peak_memory)}</div>
            `;
            
            if (performance.db_query_time !== null) {
                html += `<div class="metric">DB Query Time: ${performance.db_query_time.toFixed(2)} ms</div>`;
            }
            
            if (performance.load_average) {
                html += `<div class="metric">Load Average: ${performance.load_average.map(l => l.toFixed(2)).join(', ')}</div>`;
            }
            
            element.innerHTML = html;
        }
        
        function updateDatabaseStatus(database) {
            const element = document.getElementById('database-status');
            
            if (database.status === 'connected') {
                element.innerHTML = `
                    <div class="metric status-good">Status: Connected</div>
                    <div class="metric">Version: ${database.version}</div>
                    <div class="metric">Size: ${database.size_mb} MB</div>
                    <div class="metric">Tables: ${database.table_count}</div>
                    <div class="metric">Connections: ${database.connections}</div>
                `;
            } else {
                element.innerHTML = `<div class="metric status-error">Error: ${database.error}</div>`;
            }
        }
        
        function updateRecentErrors(errors) {
            const element = document.getElementById('recent-errors');
            
            if (errors.length === 0) {
                element.innerHTML = '<div class="metric status-good">No recent errors</div>';
            } else {
                let html = '';
                errors.forEach(error => {
                    html += `<div class="log-entry error-entry">${error}</div>`;
                });
                element.innerHTML = html;
            }
        }
        
        function updateLogAnalysis(logAnalysis) {
            const element = document.getElementById('log-analysis');
            const stats = logAnalysis.log_stats;
            const security = logAnalysis.security_analysis;
            
            let html = `
                <div class="metric">Total Log Size: ${formatBytes(stats.total_size)}</div>
                <div class="metric">Log Files: ${stats.file_count}</div>
            `;
            
            if (security.failed_logins > 0) {
                html += `<div class="metric status-warning">Failed Logins: ${security.failed_logins}</div>`;
            }
            
            if (security.suspicious_ips && security.suspicious_ips.length > 0) {
                html += `<div class="metric status-error">Suspicious IPs: ${security.suspicious_ips.length}</div>`;
            }
            
            element.innerHTML = html;
        }
        
        // Load data on page load and refresh every 30 seconds
        loadSystemData();
        setInterval(loadSystemData, 30000);
    </script>
</body>
</html>