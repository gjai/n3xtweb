<?php
/**
 * N3XT WEB - System Update Module
 * 
 * Manual download of latest GitHub release, backup creation, file scanning,
 * and system core replacement with comprehensive logging.
 */

// Define security constant before including any files
define('IN_N3XTWEB', true);

require_once '../includes/functions.php';

// Check authentication
if (!Session::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';
$updateStep = $_GET['step'] ?? 'check';
$csrfToken = Security::generateCSRFToken();

// GitHub API helper class
class GitHubUpdater {
    private $owner;
    private $repo;
    private $apiUrl;
    
    public function __construct() {
        $this->owner = GITHUB_OWNER;
        $this->repo = GITHUB_REPO;
        $this->apiUrl = GITHUB_API_URL;
    }
    
    /**
     * Get latest release information
     */
    public function getLatestRelease() {
        $url = "{$this->apiUrl}/repos/{$this->owner}/{$this->repo}/releases/latest";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: N3XT-Communication-Updater/1.0'
                ],
                'timeout' => 30
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception('Failed to fetch release information from GitHub');
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Download release archive
     */
    public function downloadRelease($downloadUrl, $targetPath) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: N3XT-Communication-Updater/1.0'
                ],
                'timeout' => 300 // 5 minutes
            ]
        ]);
        
        $content = @file_get_contents($downloadUrl, false, $context);
        
        if ($content === false) {
            throw new Exception('Failed to download release archive');
        }
        
        if (file_put_contents($targetPath, $content) === false) {
            throw new Exception('Failed to save release archive');
        }
        
        return filesize($targetPath);
    }
}

// File scanner class
class FileScanner {
    
    /**
     * Scan for unexpected files
     */
    public static function scanDirectory($directory, $expectedFiles = []) {
        $unexpectedFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            $relativePath = str_replace($directory . '/', '', $file->getPathname());
            
            // Skip expected files and directories
            if (!in_array($relativePath, $expectedFiles) && 
                !self::isExpectedPath($relativePath)) {
                $unexpectedFiles[] = [
                    'path' => $relativePath,
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime()
                ];
            }
        }
        
        return $unexpectedFiles;
    }
    
    /**
     * Check if path is expected
     */
    private static function isExpectedPath($path) {
        $expectedPatterns = [
            'admin/',
            'assets/',
            'config/',
            'includes/',
            'backups/',
            'logs/',
            'uploads/',
            '.htaccess',
            'robots.txt',
            'index.php',
            'README.md'
        ];
        
        foreach ($expectedPatterns as $pattern) {
            if (strpos($path, $pattern) === 0) {
                return true;
            }
        }
        
        return false;
    }
}

// Backup creator class
class BackupCreator {
    
    /**
     * Create full system backup
     */
    public static function createBackup() {
        $backupDir = BACKUP_PATH . '/backup_' . date('Y-m-d_H-i-s');
        $backupFile = $backupDir . '.zip';
        
        // Create backup directory
        if (!is_dir(BACKUP_PATH)) {
            mkdir(BACKUP_PATH, 0755, true);
        }
        
        // Create ZIP archive
        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Failed to create backup archive');
        }
        
        // Add files to archive (excluding sensitive directories)
        $excludeDirs = ['backups', 'logs', 'tmp'];
        self::addDirectoryToZip($zip, ROOT_PATH, '', $excludeDirs);
        
        // Add database backup if configured
        try {
            $sqlFile = self::createDatabaseBackup();
            if ($sqlFile) {
                $zip->addFile($sqlFile, 'backup.sql');
            }
        } catch (Exception $e) {
            Logger::log("Database backup failed: " . $e->getMessage(), LOG_LEVEL_WARNING, 'update');
        }
        
        $zip->close();
        
        // Clean up temporary SQL file
        if (isset($sqlFile) && file_exists($sqlFile)) {
            unlink($sqlFile);
        }
        
        return [
            'file' => $backupFile,
            'size' => filesize($backupFile)
        ];
    }
    
    /**
     * Add directory to ZIP archive
     */
    private static function addDirectoryToZip($zip, $rootPath, $relativePath, $excludeDirs) {
        $fullPath = $rootPath . '/' . $relativePath;
        
        if (is_dir($fullPath)) {
            $files = scandir($fullPath);
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                $filePath = $relativePath ? $relativePath . '/' . $file : $file;
                $fullFilePath = $rootPath . '/' . $filePath;
                
                // Skip excluded directories
                if (is_dir($fullFilePath) && in_array($file, $excludeDirs)) {
                    continue;
                }
                
                if (is_dir($fullFilePath)) {
                    $zip->addEmptyDir($filePath);
                    self::addDirectoryToZip($zip, $rootPath, $filePath, $excludeDirs);
                } else {
                    $zip->addFile($fullFilePath, $filePath);
                }
            }
        }
    }
    
    /**
     * Create database backup
     */
    private static function createDatabaseBackup() {
        try {
            $db = Database::getInstance();
            $tempFile = sys_get_temp_dir() . '/n3xt_backup_' . date('Y-m-d_H-i-s') . '.sql';
            
            // Get all tables
            $tables = $db->fetchAll("SHOW TABLES");
            
            $sql = "-- N3XT WEB Database Backup\n";
            $sql .= "-- Generated on " . date('Y-m-d H:i:s') . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                
                // Get table structure
                $createTable = $db->fetchOne("SHOW CREATE TABLE `{$tableName}`");
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= $createTable['Create Table'] . ";\n\n";
                
                // Get table data
                $rows = $db->fetchAll("SELECT * FROM `{$tableName}`");
                
                if (!empty($rows)) {
                    $sql .= "INSERT INTO `{$tableName}` VALUES\n";
                    $values = [];
                    
                    foreach ($rows as $row) {
                        $rowValues = array_map(function($value) {
                            return "'" . addslashes($value) . "'";
                        }, array_values($row));
                        $values[] = '(' . implode(',', $rowValues) . ')';
                    }
                    
                    $sql .= implode(",\n", $values) . ";\n\n";
                }
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            file_put_contents($tempFile, $sql);
            return $tempFile;
            
        } catch (Exception $e) {
            Logger::log("Database backup failed: " . $e->getMessage(), LOG_LEVEL_ERROR, 'update');
            return false;
        }
    }
}

// Handle update process
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }
        
        $action = $_POST['action'];
        
        switch ($action) {
            case 'check_update':
                $updater = new GitHubUpdater();
                $release = $updater->getLatestRelease();
                
                $currentVersion = SYSTEM_VERSION;
                $latestVersion = ltrim($release['tag_name'], 'v');
                
                Logger::logUpdate("Checked for updates - Current: {$currentVersion}, Latest: {$latestVersion}");
                
                if (version_compare($currentVersion, $latestVersion, '<')) {
                    $message = "Update available! Current version: {$currentVersion}, Latest version: {$latestVersion}";
                    $messageType = 'info';
                    $_SESSION['update_info'] = $release;
                } else {
                    $message = "System is up to date. Current version: {$currentVersion}";
                    $messageType = 'success';
                }
                break;
                
            case 'create_backup':
                Logger::logUpdate("Creating system backup before update");
                $backup = BackupCreator::createBackup();
                
                $_SESSION['backup_file'] = $backup['file'];
                $message = "Backup created successfully: " . basename($backup['file']) . " (" . FileHelper::formatFileSize($backup['size']) . ")";
                $messageType = 'success';
                
                Logger::logUpdate("Backup created: " . basename($backup['file']) . " (" . FileHelper::formatFileSize($backup['size']) . ")");
                break;
                
            case 'scan_files':
                Logger::logUpdate("Scanning for unexpected files");
                $unexpectedFiles = FileScanner::scanDirectory(ROOT_PATH);
                
                $_SESSION['scan_results'] = $unexpectedFiles;
                
                if (empty($unexpectedFiles)) {
                    $message = "No unexpected files found. System is clean.";
                    $messageType = 'success';
                } else {
                    $message = count($unexpectedFiles) . " unexpected files found. Please review before proceeding.";
                    $messageType = 'warning';
                }
                
                Logger::logUpdate("File scan completed - " . count($unexpectedFiles) . " unexpected files found");
                break;
                
            case 'download_update':
                if (!isset($_SESSION['update_info'])) {
                    throw new Exception('No update information available');
                }
                
                $release = $_SESSION['update_info'];
                $downloadUrl = $release['zipball_url'];
                
                $updateFile = BACKUP_PATH . '/update_' . date('Y-m-d_H-i-s') . '.zip';
                
                Logger::logUpdate("Downloading update from GitHub: " . $downloadUrl);
                
                $updater = new GitHubUpdater();
                $size = $updater->downloadRelease($downloadUrl, $updateFile);
                
                $_SESSION['update_file'] = $updateFile;
                $message = "Update downloaded successfully: " . basename($updateFile) . " (" . FileHelper::formatFileSize($size) . ")";
                $messageType = 'success';
                
                Logger::logUpdate("Update downloaded: " . basename($updateFile) . " (" . FileHelper::formatFileSize($size) . ")");
                break;
                
            case 'apply_update':
                if (!isset($_SESSION['update_file']) || !file_exists($_SESSION['update_file'])) {
                    throw new Exception('Update file not found');
                }
                
                $updateFile = $_SESSION['update_file'];
                Logger::logUpdate("Applying update from: " . basename($updateFile));
                
                // Extract update
                $zip = new ZipArchive();
                if ($zip->open($updateFile) !== TRUE) {
                    throw new Exception('Failed to open update archive');
                }
                
                $tempDir = sys_get_temp_dir() . '/n3xt_update_' . uniqid();
                mkdir($tempDir, 0755, true);
                
                $zip->extractTo($tempDir);
                $zip->close();
                
                // Find extracted directory (GitHub creates a subdirectory)
                $extractedDirs = glob($tempDir . '/*', GLOB_ONLYDIR);
                if (empty($extractedDirs)) {
                    throw new Exception('No extracted directory found');
                }
                
                $sourceDir = $extractedDirs[0];
                
                // Copy files (excluding critical directories)
                global $CRITICAL_DIRECTORIES, $UPDATE_EXCLUDE_FILES;
                
                $updatedFiles = [];
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                
                foreach ($iterator as $file) {
                    $relativePath = str_replace($sourceDir . '/', '', $file->getPathname());
                    $targetPath = ROOT_PATH . '/' . $relativePath;
                    
                    // Skip critical directories and excluded files
                    $skip = false;
                    foreach ($CRITICAL_DIRECTORIES as $criticalDir) {
                        if (strpos($relativePath, $criticalDir . '/') === 0) {
                            $skip = true;
                            break;
                        }
                    }
                    
                    if (in_array(basename($relativePath), $UPDATE_EXCLUDE_FILES)) {
                        $skip = true;
                    }
                    
                    if (!$skip && $file->isFile()) {
                        $targetDir = dirname($targetPath);
                        if (!is_dir($targetDir)) {
                            mkdir($targetDir, 0755, true);
                        }
                        
                        if (copy($file->getPathname(), $targetPath)) {
                            $updatedFiles[] = $relativePath;
                        }
                    }
                }
                
                // Clean up
                self::deleteDirectory($tempDir);
                unlink($updateFile);
                
                $message = "Update applied successfully! " . count($updatedFiles) . " files updated.";
                $messageType = 'success';
                
                Logger::logUpdate("Update applied successfully - " . count($updatedFiles) . " files updated");
                
                // Clear session data
                unset($_SESSION['update_info']);
                unset($_SESSION['update_file']);
                break;
        }
        
    }
} catch (Exception $e) {
    $message = 'Error: ' . $e->getMessage();
    $messageType = 'error';
    Logger::log("Update error: " . $e->getMessage(), LOG_LEVEL_ERROR, 'update');
}

// Helper function to delete directory recursively
function deleteDirectory($dir) {
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $filePath = $dir . '/' . $file;
                if (is_dir($filePath)) {
                    deleteDirectory($filePath);
                } else {
                    unlink($filePath);
                }
            }
        }
        rmdir($dir);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>N3XT WEB - System Update</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>N3XT WEB</h1>
            <p style="margin-top: 10px; opacity: 0.9;">System Update Manager</p>
        </div>
        
        <div class="main-content">
            <nav class="nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a href="update.php" class="nav-link active">System Update</a>
                    </li>
                    <li class="nav-item">
                        <a href="restore.php" class="nav-link">Backup & Restore</a>
                    </li>
                </ul>
            </nav>
            
            <div class="content-area">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">System Update Process</h2>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <strong>Important:</strong> Always create a backup before applying updates. 
                            This process will download the latest release from GitHub and replace system files.
                        </div>
                        
                        <!-- Step 1: Check for Updates -->
                        <div class="card" style="margin-bottom: 20px;">
                            <div class="card-header">
                                <h3 class="card-title">Step 1: Check for Updates</h3>
                            </div>
                            <div class="card-body">
                                <p>Current version: <strong><?php echo SYSTEM_VERSION; ?></strong></p>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="check_update">
                                    <button type="submit" class="btn btn-primary">Check for Updates</button>
                                </form>
                                
                                <?php if (isset($_SESSION['update_info'])): ?>
                                    <div style="margin-top: 15px; padding: 15px; background: #e8f5e8; border-radius: 4px;">
                                        <h4>Update Available</h4>
                                        <p><strong>Version:</strong> <?php echo htmlspecialchars($_SESSION['update_info']['tag_name']); ?></p>
                                        <p><strong>Released:</strong> <?php echo date('Y-m-d H:i', strtotime($_SESSION['update_info']['published_at'])); ?></p>
                                        <p><strong>Description:</strong></p>
                                        <div style="max-height: 200px; overflow-y: auto; background: white; padding: 10px; border-radius: 4px;">
                                            <?php echo nl2br(htmlspecialchars($_SESSION['update_info']['body'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Step 2: Create Backup -->
                        <div class="card" style="margin-bottom: 20px;">
                            <div class="card-header">
                                <h3 class="card-title">Step 2: Create Backup</h3>
                            </div>
                            <div class="card-body">
                                <p>Create a full system backup before applying updates.</p>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="create_backup">
                                    <button type="submit" class="btn btn-success">Create Backup</button>
                                </form>
                                
                                <?php if (isset($_SESSION['backup_file'])): ?>
                                    <div style="margin-top: 15px; padding: 15px; background: #e8f5e8; border-radius: 4px;">
                                        <p><strong>Backup created:</strong> <?php echo basename($_SESSION['backup_file']); ?></p>
                                        <a href="restore.php?download=<?php echo urlencode(basename($_SESSION['backup_file'])); ?>" 
                                           class="btn btn-secondary">Download Backup</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Step 3: Scan Files -->
                        <div class="card" style="margin-bottom: 20px;">
                            <div class="card-header">
                                <h3 class="card-title">Step 3: Scan for Unexpected Files</h3>
                            </div>
                            <div class="card-body">
                                <p>Scan the system for unexpected files that might interfere with the update.</p>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="scan_files">
                                    <button type="submit" class="btn btn-warning">Scan Files</button>
                                </form>
                                
                                <?php if (isset($_SESSION['scan_results'])): ?>
                                    <div style="margin-top: 15px;">
                                        <?php if (empty($_SESSION['scan_results'])): ?>
                                            <div style="padding: 15px; background: #e8f5e8; border-radius: 4px;">
                                                <p><strong>✓ No unexpected files found</strong></p>
                                            </div>
                                        <?php else: ?>
                                            <div style="padding: 15px; background: #fff3cd; border-radius: 4px;">
                                                <p><strong>⚠ Unexpected files found:</strong></p>
                                                <div style="max-height: 200px; overflow-y: auto; background: white; padding: 10px; border-radius: 4px; margin-top: 10px;">
                                                    <?php foreach ($_SESSION['scan_results'] as $file): ?>
                                                        <div style="margin-bottom: 5px; font-family: monospace; font-size: 12px;">
                                                            <?php echo htmlspecialchars($file['path']); ?>
                                                            <span style="color: #666;">(<?php echo FileHelper::formatFileSize($file['size']); ?>)</span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Step 4: Download Update -->
                        <div class="card" style="margin-bottom: 20px;">
                            <div class="card-header">
                                <h3 class="card-title">Step 4: Download Update</h3>
                            </div>
                            <div class="card-body">
                                <p>Download the latest release from GitHub.</p>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="download_update">
                                    <button type="submit" class="btn btn-primary" 
                                            <?php echo !isset($_SESSION['update_info']) ? 'disabled' : ''; ?>>
                                        Download Update
                                    </button>
                                </form>
                                
                                <?php if (isset($_SESSION['update_file'])): ?>
                                    <div style="margin-top: 15px; padding: 15px; background: #e8f5e8; border-radius: 4px;">
                                        <p><strong>✓ Update downloaded:</strong> <?php echo basename($_SESSION['update_file']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Step 5: Apply Update -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Step 5: Apply Update</h3>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-danger">
                                    <strong>Warning:</strong> This will replace system files. Make sure you have a backup!
                                </div>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="apply_update">
                                    <button type="submit" class="btn btn-danger" 
                                            <?php echo !isset($_SESSION['update_file']) ? 'disabled' : ''; ?>
                                            onclick="return confirm('Are you sure you want to apply the update? This action cannot be undone without a backup.')">
                                        Apply Update
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Show loading indicator for long operations
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function() {
                    const button = form.querySelector('button[type="submit"]');
                    if (button) {
                        button.disabled = true;
                        button.innerHTML = 'Processing...';
                    }
                });
            });
        });
    </script>
</body>
</html>