<?php
/**
 * N3XT WEB - Admin Dashboard
 * 
 * Main admin panel interface with navigation to all back office modules.
 */

// Define security constant before including any files
define('IN_N3XTWEB', true);

require_once '../includes/functions.php';

// Check authentication
if (!Session::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    Logger::logAccess($_SESSION['admin_username'], true, 'Logout');
    Session::logout();
    header('Location: login.php');
    exit;
}

$currentPage = $_GET['page'] ?? 'dashboard';
$csrfToken = Security::generateCSRFToken();

// Get system status
$systemInfo = [
    'version' => SYSTEM_VERSION,
    'maintenance_mode' => MAINTENANCE_MODE,
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'disk_free' => FileHelper::formatFileSize(disk_free_space('.')),
    'memory_limit' => ini_get('memory_limit'),
    'upload_max' => ini_get('upload_max_filesize'),
    'session_timeout' => ADMIN_SESSION_TIMEOUT / 60 . ' minutes'
];

// Get recent logs
$recentLogs = [];
$accessLogFile = LOG_PATH . '/access.log';
if (file_exists($accessLogFile)) {
    $lines = file($accessLogFile);
    $recentLogs = array_slice(array_reverse($lines), 0, 10);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>N3XT WEB - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>N3XT WEB Admin Panel</h1>
            <div style="text-align: center; margin-top: 10px;">
                <span style="opacity: 0.9;">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="?logout=1" style="color: #ecf0f1; margin-left: 20px; text-decoration: none;">Logout</a>
            </div>
        </div>
        
        <div class="main-content">
            <nav class="nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="?page=dashboard" class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="update.php" class="nav-link">
                            System Update
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="restore.php" class="nav-link">
                            Backup & Restore
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?page=logs" class="nav-link <?php echo $currentPage === 'logs' ? 'active' : ''; ?>">
                            System Logs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?page=maintenance" class="nav-link <?php echo $currentPage === 'maintenance' ? 'active' : ''; ?>">
                            Maintenance
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="content-area">
                <?php if ($currentPage === 'dashboard'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">System Overview</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <tr>
                                        <td><strong>System Version</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['version']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Maintenance Mode</strong></td>
                                        <td>
                                            <span class="<?php echo $systemInfo['maintenance_mode'] ? 'alert-warning' : 'alert-success'; ?>" style="padding: 2px 8px; border-radius: 3px; font-size: 12px;">
                                                <?php echo $systemInfo['maintenance_mode'] ? 'ENABLED' : 'DISABLED'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>PHP Version</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['php_version']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Web Server</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['server']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Free Disk Space</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['disk_free']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>PHP Memory Limit</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['memory_limit']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Upload Max Size</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['upload_max']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Session Timeout</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['session_timeout']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Quick Actions</h2>
                        </div>
                        <div class="card-body">
                            <div class="btn-group">
                                <a href="update.php" class="btn btn-primary">System Update</a>
                                <a href="restore.php" class="btn btn-success">Backup System</a>
                                <a href="?page=maintenance" class="btn btn-warning">Maintenance Mode</a>
                                <a href="?page=logs" class="btn btn-secondary">View Logs</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Recent Access Log</h2>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentLogs)): ?>
                                <p>No recent log entries found.</p>
                            <?php else: ?>
                                <div style="font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 4px;">
                                    <?php foreach ($recentLogs as $log): ?>
                                        <div style="margin-bottom: 5px; word-break: break-all;">
                                            <?php echo htmlspecialchars(trim($log)); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php elseif ($currentPage === 'logs'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">System Logs</h2>
                        </div>
                        <div class="card-body">
                            <div class="btn-group">
                                <a href="?page=logs&type=access" class="btn btn-secondary">Access Log</a>
                                <a href="?page=logs&type=update" class="btn btn-secondary">Update Log</a>
                                <a href="?page=logs&type=system" class="btn btn-secondary">System Log</a>
                            </div>
                            
                            <?php 
                            $logType = $_GET['type'] ?? 'access';
                            $logFile = LOG_PATH . "/{$logType}.log";
                            ?>
                            
                            <div style="margin-top: 20px;">
                                <h3>Log: <?php echo ucfirst($logType); ?></h3>
                                
                                <?php if (file_exists($logFile)): ?>
                                    <?php 
                                    $logContent = file_get_contents($logFile);
                                    $logLines = array_reverse(explode("\n", trim($logContent)));
                                    $logLines = array_slice($logLines, 0, 100); // Show last 100 lines
                                    ?>
                                    
                                    <div style="font-family: monospace; font-size: 12px; max-height: 500px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 10px;">
                                        <?php foreach ($logLines as $line): ?>
                                            <?php if (trim($line)): ?>
                                                <div style="margin-bottom: 3px; word-break: break-all;">
                                                    <?php echo htmlspecialchars($line); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <p style="margin-top: 10px; font-size: 12px; color: #7f8c8d;">
                                        Showing last 100 entries. Log file size: <?php echo FileHelper::formatFileSize(filesize($logFile)); ?>
                                    </p>
                                <?php else: ?>
                                    <p>Log file not found or empty.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($currentPage === 'maintenance'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Maintenance Mode</h2>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Maintenance Mode</strong><br>
                                When enabled, maintenance mode will display a maintenance page to all visitors except administrators.
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Current Status:</label>
                                <p>
                                    <span class="<?php echo MAINTENANCE_MODE ? 'alert-warning' : 'alert-success'; ?>" style="padding: 5px 10px; border-radius: 4px;">
                                        <?php echo MAINTENANCE_MODE ? 'ENABLED' : 'DISABLED'; ?>
                                    </span>
                                </p>
                            </div>
                            
                            <div class="alert alert-warning">
                                <strong>Note:</strong> Maintenance mode settings are configured in the config.php file. 
                                To change the maintenance mode status, you need to edit the configuration file directly.
                            </div>
                        </div>
                    </div>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh dashboard every 30 seconds
        <?php if ($currentPage === 'dashboard'): ?>
        setInterval(function() {
            if (document.hasFocus()) {
                // Only refresh if page has focus
                window.location.reload();
            }
        }, 30000);
        <?php endif; ?>
        
        // Session timeout warning
        setTimeout(function() {
            if (confirm('Your session will expire soon. Do you want to stay logged in?')) {
                window.location.reload();
            }
        }, <?php echo (ADMIN_SESSION_TIMEOUT - 300) * 1000; ?>); // 5 minutes before timeout
    </script>
</body>
</html>