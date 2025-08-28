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
            
        default:
            $result = ['error' => 'Invalid action'];
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    Logger::log("Cleanup script error: " . $e->getMessage(), LOG_LEVEL_ERROR);
    echo json_encode(['error' => 'An error occurred during cleanup']);
}
?>