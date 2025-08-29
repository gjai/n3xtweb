<?php
/**
 * N3XT WEB - System Cleanup and Optimization Script
 * 
 * This script helps maintain system performance by cleaning up
 * unnecessary files and optimizing database tables.
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

try {
    $action = $_GET['action'] ?? 'health';
    
    switch ($action) {
        case 'health':
            $result = SystemHealth::checkHealth();
            break;
            
        case 'cleanup':
            $result = SystemHealth::cleanup();
            break;
            
        case 'optimize':
            $result = SystemHealth::optimizeDatabase();
            break;
            
        case 'cache_clear':
            $cleared = Cache::clear();
            $result = ['status' => 'OK', 'message' => "Cleared {$cleared} cache entries"];
            break;
            
        case 'performance':
            $result = Performance::getSystemMetrics();
            break;
            
        case 'logs_cleanup':
            $deleted = Logger::cleanupOldLogs(30);
            $result = ['status' => 'OK', 'message' => "Cleaned up {$deleted} old log files"];
            break;
            
        case 'logs_analyze':
            $analysis = Logger::analyzeSecurityLogs('access');
            $result = ['status' => 'OK', 'analysis' => $analysis];
            break;
            
        case 'logs_stats':
            $stats = Logger::getLogStats();
            $result = ['status' => 'OK', 'stats' => $stats];
            break;
            
        case 'security_scan':
            if (file_exists(ROOT_PATH . '/security_scanner.php')) {
                include_once ROOT_PATH . '/security_scanner.php';
                if (class_exists('SecurityScanner')) {
                    $result = SecurityScanner::performSecurityScan();
                } else {
                    $result = ['error' => 'Security scanner class not found'];
                }
            } else {
                $result = ['error' => 'Security scanner not available'];
            }
            break;
            
        case 'maintenance_full':
            if (file_exists(ROOT_PATH . '/auto_maintenance.php')) {
                include_once ROOT_PATH . '/auto_maintenance.php';
                if (class_exists('MaintenanceManager')) {
                    $result = MaintenanceManager::runFullMaintenance();
                } else {
                    $result = ['error' => 'Maintenance manager class not found'];
                }
            } else {
                $result = ['error' => 'Auto maintenance not available'];
            }
            break;
            
        default:
            $result = ['error' => 'Invalid action'];
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    Logger::log("Cleanup script error: " . $e->getMessage(), LOG_LEVEL_ERROR);
    echo json_encode(['error' => 'An error occurred during cleanup']);
}
?>