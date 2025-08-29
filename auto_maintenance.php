<?php
/**
 * N3XT WEB - Automated Maintenance Script
 * 
 * Handles log rotation, cleanup, optimization, and maintenance tasks
 * Can be run via cron job or manually
 */

// Define security constant before including any files
define('IN_N3XTWEB', true);

require_once 'includes/functions.php';

/**
 * Maintenance Manager Class
 */
class MaintenanceManager {
    
    private static $logFile = 'maintenance';
    
    /**
     * Run all maintenance tasks
     */
    public static function runFullMaintenance() {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tasks' => [],
            'total_time' => 0,
            'errors' => []
        ];
        
        $startTime = microtime(true);
        
        try {
            // Log rotation
            $results['tasks']['log_rotation'] = self::performLogRotation();
            
            // Cache cleanup
            $results['tasks']['cache_cleanup'] = self::performCacheCleanup();
            
            // Temporary files cleanup
            $results['tasks']['temp_cleanup'] = self::performTempCleanup();
            
            // Database optimization
            $results['tasks']['database_optimization'] = self::performDatabaseOptimization();
            
            // Security scan
            $results['tasks']['security_scan'] = self::performSecurityScan();
            
            // System health check
            $results['tasks']['health_check'] = self::performHealthCheck();
            
            // Backup old files
            $results['tasks']['backup_cleanup'] = self::performBackupCleanup();
            
        } catch (Exception $e) {
            $results['errors'][] = "Maintenance error: " . $e->getMessage();
            Logger::log("Maintenance script error: " . $e->getMessage(), LOG_LEVEL_ERROR, self::$logFile);
        }
        
        $results['total_time'] = microtime(true) - $startTime;
        
        // Log maintenance completion
        Logger::log("Full maintenance completed in " . round($results['total_time'], 2) . " seconds", LOG_LEVEL_INFO, self::$logFile);
        
        return $results;
    }
    
    /**
     * Perform log rotation
     */
    public static function performLogRotation() {
        $results = [
            'status' => 'success',
            'rotated_files' => 0,
            'compressed_files' => 0,
            'deleted_old_files' => 0
        ];
        
        try {
            if (!is_dir(LOG_PATH)) {
                return array_merge($results, ['status' => 'skipped', 'message' => 'Log directory does not exist']);
            }
            
            $logFiles = glob(LOG_PATH . '/*.log');
            
            foreach ($logFiles as $logFile) {
                $fileSize = filesize($logFile);
                
                // Rotate if file is larger than 10MB
                if ($fileSize > 10 * 1024 * 1024) {
                    $rotatedName = $logFile . '.' . date('Y-m-d-H-i-s');
                    
                    if (rename($logFile, $rotatedName)) {
                        $results['rotated_files']++;
                        
                        // Compress rotated file
                        if (function_exists('gzopen')) {
                            $gz = gzopen($rotatedName . '.gz', 'wb9');
                            if ($gz) {
                                gzwrite($gz, file_get_contents($rotatedName));
                                gzclose($gz);
                                unlink($rotatedName);
                                $results['compressed_files']++;
                            }
                        }
                    }
                }
            }
            
            // Clean up old rotated logs (older than 30 days)
            $oldFiles = glob(LOG_PATH . '/*.log.*');
            $cutoffTime = time() - (30 * 24 * 60 * 60);
            
            foreach ($oldFiles as $oldFile) {
                if (filemtime($oldFile) < $cutoffTime) {
                    unlink($oldFile);
                    $results['deleted_old_files']++;
                }
            }
            
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            Logger::log("Log rotation error: " . $e->getMessage(), LOG_LEVEL_ERROR, self::$logFile);
        }
        
        return $results;
    }
    
    /**
     * Perform cache cleanup
     */
    public static function performCacheCleanup() {
        $results = [
            'status' => 'success',
            'cleared_entries' => 0,
            'freed_space' => 0
        ];
        
        try {
            if (class_exists('Cache')) {
                $cleared = Cache::clear();
                $results['cleared_entries'] = $cleared;
            }
            
            // Clean file-based cache if exists
            $cacheDir = ROOT_PATH . '/cache';
            if (is_dir($cacheDir)) {
                $freedSpace = self::cleanDirectory($cacheDir, 3600); // Clean files older than 1 hour
                $results['freed_space'] = $freedSpace;
            }
            
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            Logger::log("Cache cleanup error: " . $e->getMessage(), LOG_LEVEL_ERROR, self::$logFile);
        }
        
        return $results;
    }
    
    /**
     * Perform temporary files cleanup
     */
    public static function performTempCleanup() {
        $results = [
            'status' => 'success',
            'deleted_files' => 0,
            'freed_space' => 0
        ];
        
        try {
            $tempDirs = [
                sys_get_temp_dir() . '/n3xt*',
                ROOT_PATH . '/tmp',
                ROOT_PATH . '/temp'
            ];
            
            foreach ($tempDirs as $pattern) {
                if (strpos($pattern, '*') !== false) {
                    $dirs = glob($pattern, GLOB_ONLYDIR);
                    foreach ($dirs as $dir) {
                        $freedSpace = self::cleanDirectory($dir, 0, true); // Delete all temp files
                        $results['freed_space'] += $freedSpace;
                        if (is_dir($dir) && count(scandir($dir)) == 2) { // Empty directory
                            rmdir($dir);
                        }
                    }
                } else {
                    if (is_dir($pattern)) {
                        $freedSpace = self::cleanDirectory($pattern, 86400); // Clean files older than 24 hours
                        $results['freed_space'] += $freedSpace;
                    }
                }
            }
            
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            Logger::log("Temp cleanup error: " . $e->getMessage(), LOG_LEVEL_ERROR, self::$logFile);
        }
        
        return $results;
    }
    
    /**
     * Perform database optimization
     */
    public static function performDatabaseOptimization() {
        $results = [
            'status' => 'success',
            'optimized_tables' => 0,
            'space_saved' => 0
        ];
        
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            // Get all tables with the configured prefix
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            $stmt = $pdo->query("SHOW TABLES LIKE '{$prefix}%'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                // Get table size before optimization
                $stmt = $pdo->prepare("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb' FROM information_schema.TABLES WHERE table_name = ?");
                $stmt->execute([$table]);
                $sizeBefore = $stmt->fetch()['size_mb'] ?? 0;
                
                // Optimize table
                $pdo->exec("OPTIMIZE TABLE `{$table}`");
                
                // Get table size after optimization
                $stmt->execute([$table]);
                $sizeAfter = $stmt->fetch()['size_mb'] ?? 0;
                
                $results['optimized_tables']++;
                $results['space_saved'] += max(0, $sizeBefore - $sizeAfter);
            }
            
            // Analyze tables for better query performance
            foreach ($tables as $table) {
                $pdo->exec("ANALYZE TABLE `{$table}`");
            }
            
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            Logger::log("Database optimization error: " . $e->getMessage(), LOG_LEVEL_ERROR, self::$logFile);
        }
        
        return $results;
    }
    
    /**
     * Perform security scan
     */
    public static function performSecurityScan() {
        $results = [
            'status' => 'success',
            'security_score' => 100,
            'issues_found' => 0,
            'warnings_found' => 0
        ];
        
        try {
            // Use the security scanner if available
            if (file_exists(ROOT_PATH . '/security_scanner.php')) {
                // Simulate security scan call
                include_once ROOT_PATH . '/security_scanner.php';
                
                if (class_exists('SecurityScanner')) {
                    $scanResults = SecurityScanner::performSecurityScan();
                    $results['security_score'] = $scanResults['score'];
                    $results['issues_found'] = count($scanResults['critical_issues']);
                    $results['warnings_found'] = count($scanResults['warnings']);
                }
            }
            
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            Logger::log("Security scan error: " . $e->getMessage(), LOG_LEVEL_ERROR, self::$logFile);
        }
        
        return $results;
    }
    
    /**
     * Perform system health check
     */
    public static function performHealthCheck() {
        $results = [
            'status' => 'success',
            'health_score' => 100,
            'checks_passed' => 0,
            'checks_failed' => 0
        ];
        
        try {
            if (class_exists('SystemHealth')) {
                $healthResults = SystemHealth::checkHealth();
                
                foreach ($healthResults as $check => $result) {
                    if ($result['status'] === 'OK') {
                        $results['checks_passed']++;
                    } else {
                        $results['checks_failed']++;
                    }
                }
                
                // Calculate health score
                $totalChecks = $results['checks_passed'] + $results['checks_failed'];
                if ($totalChecks > 0) {
                    $results['health_score'] = round(($results['checks_passed'] / $totalChecks) * 100);
                }
            }
            
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            Logger::log("Health check error: " . $e->getMessage(), LOG_LEVEL_ERROR, self::$logFile);
        }
        
        return $results;
    }
    
    /**
     * Perform backup cleanup
     */
    public static function performBackupCleanup() {
        $results = [
            'status' => 'success',
            'deleted_backups' => 0,
            'freed_space' => 0
        ];
        
        try {
            if (!is_dir(BACKUP_PATH)) {
                return array_merge($results, ['status' => 'skipped', 'message' => 'Backup directory does not exist']);
            }
            
            $backups = glob(BACKUP_PATH . '/*.zip');
            $cutoffTime = time() - (60 * 24 * 60 * 60); // Keep backups for 60 days
            
            // Sort by modification time (oldest first)
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Keep at least 5 most recent backups regardless of age
            $backupsToKeep = array_slice($backups, -5);
            $backupsToDelete = array_diff($backups, $backupsToKeep);
            
            foreach ($backupsToDelete as $backup) {
                if (filemtime($backup) < $cutoffTime) {
                    $size = filesize($backup);
                    if (unlink($backup)) {
                        $results['deleted_backups']++;
                        $results['freed_space'] += $size;
                    }
                }
            }
            
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['error'] = $e->getMessage();
            Logger::log("Backup cleanup error: " . $e->getMessage(), LOG_LEVEL_ERROR, self::$logFile);
        }
        
        return $results;
    }
    
    /**
     * Clean directory of old files
     */
    private static function cleanDirectory($directory, $maxAge = 3600, $deleteAll = false) {
        $freedSpace = 0;
        
        if (!is_dir($directory)) {
            return $freedSpace;
        }
        
        $cutoffTime = time() - $maxAge;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                if ($deleteAll || $file->getMTime() < $cutoffTime) {
                    $size = $file->getSize();
                    if (unlink($file->getPathname())) {
                        $freedSpace += $size;
                    }
                }
            }
        }
        
        return $freedSpace;
    }
    
    /**
     * Get maintenance schedule recommendations
     */
    public static function getMaintenanceSchedule() {
        return [
            'daily' => [
                'log_rotation',
                'temp_cleanup',
                'cache_cleanup'
            ],
            'weekly' => [
                'security_scan',
                'health_check',
                'database_optimization'
            ],
            'monthly' => [
                'backup_cleanup',
                'full_system_audit'
            ],
            'cron_examples' => [
                'daily' => '0 2 * * * php ' . ROOT_PATH . '/auto_maintenance.php daily',
                'weekly' => '0 3 * * 0 php ' . ROOT_PATH . '/auto_maintenance.php weekly',
                'monthly' => '0 4 1 * * php ' . ROOT_PATH . '/auto_maintenance.php monthly'
            ]
        ];
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $task = $argv[1] ?? 'full';
    
    echo "N3XT WEB Maintenance Script\n";
    echo "Starting maintenance task: {$task}\n";
    echo str_repeat('-', 50) . "\n";
    
    $startTime = microtime(true);
    
    switch ($task) {
        case 'full':
            $results = MaintenanceManager::runFullMaintenance();
            break;
        case 'logs':
            $results = ['tasks' => ['log_rotation' => MaintenanceManager::performLogRotation()]];
            break;
        case 'cache':
            $results = ['tasks' => ['cache_cleanup' => MaintenanceManager::performCacheCleanup()]];
            break;
        case 'temp':
            $results = ['tasks' => ['temp_cleanup' => MaintenanceManager::performTempCleanup()]];
            break;
        case 'database':
            $results = ['tasks' => ['database_optimization' => MaintenanceManager::performDatabaseOptimization()]];
            break;
        case 'security':
            $results = ['tasks' => ['security_scan' => MaintenanceManager::performSecurityScan()]];
            break;
        case 'health':
            $results = ['tasks' => ['health_check' => MaintenanceManager::performHealthCheck()]];
            break;
        case 'backups':
            $results = ['tasks' => ['backup_cleanup' => MaintenanceManager::performBackupCleanup()]];
            break;
        case 'schedule':
            $schedule = MaintenanceManager::getMaintenanceSchedule();
            echo "Recommended Maintenance Schedule:\n\n";
            foreach ($schedule as $frequency => $tasks) {
                if ($frequency !== 'cron_examples') {
                    echo strtoupper($frequency) . ":\n";
                    foreach ($tasks as $task) {
                        echo "  - {$task}\n";
                    }
                    echo "\n";
                }
            }
            echo "Cron Job Examples:\n";
            foreach ($schedule['cron_examples'] as $freq => $cron) {
                echo "  {$freq}: {$cron}\n";
            }
            exit(0);
        default:
            echo "Unknown task: {$task}\n";
            echo "Available tasks: full, logs, cache, temp, database, security, health, backups, schedule\n";
            exit(1);
    }
    
    $endTime = microtime(true);
    $totalTime = $endTime - $startTime;
    
    echo "\nMaintenance Results:\n";
    echo str_repeat('-', 50) . "\n";
    
    foreach ($results['tasks'] ?? [] as $taskName => $taskResult) {
        echo "Task: " . ucfirst(str_replace('_', ' ', $taskName)) . "\n";
        echo "Status: " . ucfirst($taskResult['status']) . "\n";
        
        foreach ($taskResult as $key => $value) {
            if ($key !== 'status' && $key !== 'error') {
                echo "  {$key}: {$value}\n";
            }
        }
        
        if (isset($taskResult['error'])) {
            echo "  Error: " . $taskResult['error'] . "\n";
        }
        
        echo "\n";
    }
    
    echo "Total execution time: " . round($totalTime, 2) . " seconds\n";
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Web interface
    Session::start();
    if (!Session::isLoggedIn()) {
        http_response_code(403);
        exit('Access denied');
    }
    
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? 'full';
    
    try {
        switch ($action) {
            case 'full':
                $result = MaintenanceManager::runFullMaintenance();
                break;
            case 'schedule':
                $result = MaintenanceManager::getMaintenanceSchedule();
                break;
            default:
                $result = ['error' => 'Invalid action'];
        }
        
        echo json_encode($result, JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        Logger::log("Maintenance web interface error: " . $e->getMessage(), LOG_LEVEL_ERROR);
        echo json_encode(['error' => 'Maintenance failed', 'message' => $e->getMessage()]);
    }
}
?>