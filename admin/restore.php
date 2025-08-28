<?php
/**
 * N3XT Communication - Backup & Restore Module
 * 
 * Upload ZIP archive with backup.sql, import SQL database, and restore critical files
 * with comprehensive notifications and logging.
 */

require_once '../includes/functions.php';

// Check authentication
if (!Session::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';
$csrfToken = Security::generateCSRFToken();

// Handle backup download
if (isset($_GET['download'])) {
    $filename = Security::sanitizeInput($_GET['download']);
    $filepath = BACKUP_PATH . '/' . $filename;
    
    if (file_exists($filepath) && strpos($filepath, BACKUP_PATH) === 0) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        $message = 'Backup file not found.';
        $messageType = 'error';
    }
}

// Restore helper class
class RestoreManager {
    
    /**
     * List available backups
     */
    public static function listBackups() {
        $backups = [];
        
        if (!is_dir(BACKUP_PATH)) {
            return $backups;
        }
        
        $files = glob(BACKUP_PATH . '/*.zip');
        
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'filepath' => $file,
                'size' => filesize($file),
                'created' => filemtime($file)
            ];
        }
        
        // Sort by creation date (newest first)
        usort($backups, function($a, $b) {
            return $b['created'] - $a['created'];
        });
        
        return $backups;
    }
    
    /**
     * Extract and validate backup archive
     */
    public static function extractBackup($backupFile) {
        $extractDir = sys_get_temp_dir() . '/n3xt_restore_' . uniqid();
        
        $zip = new ZipArchive();
        if ($zip->open($backupFile) !== TRUE) {
            throw new Exception('Failed to open backup archive');
        }
        
        // Create extraction directory
        if (!mkdir($extractDir, 0755, true)) {
            throw new Exception('Failed to create extraction directory');
        }
        
        // Extract archive
        if (!$zip->extractTo($extractDir)) {
            throw new Exception('Failed to extract backup archive');
        }
        
        $zip->close();
        
        // Validate backup contents
        $sqlFile = $extractDir . '/backup.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception('Backup archive does not contain backup.sql file');
        }
        
        return [
            'extract_dir' => $extractDir,
            'sql_file' => $sqlFile
        ];
    }
    
    /**
     * Restore database from SQL file
     */
    public static function restoreDatabase($sqlFile) {
        try {
            $db = Database::getInstance();
            $connection = $db->getConnection();
            
            // Read SQL file
            $sql = file_get_contents($sqlFile);
            if ($sql === false) {
                throw new Exception('Failed to read SQL backup file');
            }
            
            // Split SQL into individual queries
            $queries = self::splitSqlQueries($sql);
            $executedQueries = 0;
            
            // Execute queries
            $connection->beginTransaction();
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (empty($query) || strpos($query, '--') === 0) {
                    continue;
                }
                
                try {
                    $connection->exec($query);
                    $executedQueries++;
                } catch (PDOException $e) {
                    Logger::log("SQL query failed during restore: " . $e->getMessage(), LOG_LEVEL_WARNING, 'update');
                    // Continue with other queries - some failures might be expected
                }
            }
            
            $connection->commit();
            
            return $executedQueries;
            
        } catch (Exception $e) {
            if (isset($connection)) {
                $connection->rollback();
            }
            throw $e;
        }
    }
    
    /**
     * Split SQL file into individual queries
     */
    private static function splitSqlQueries($sql) {
        // Remove comments and normalize line endings
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        
        // Split by semicolon at end of line
        $queries = preg_split('/;\s*\n/', $sql);
        
        return array_filter($queries, function($query) {
            return !empty(trim($query));
        });
    }
    
    /**
     * Restore files from backup
     */
    public static function restoreFiles($extractDir, $restoreOptions = []) {
        $restoredFiles = [];
        $skipDirs = $restoreOptions['skip_dirs'] ?? ['logs', 'backups', 'uploads'];
        $skipFiles = $restoreOptions['skip_files'] ?? ['.htaccess', 'config.php'];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            $relativePath = str_replace($extractDir . '/', '', $file->getPathname());
            
            // Skip backup.sql as it's handled separately
            if ($relativePath === 'backup.sql') {
                continue;
            }
            
            // Check if we should skip this file/directory
            $skip = false;
            
            foreach ($skipDirs as $skipDir) {
                if (strpos($relativePath, $skipDir . '/') === 0) {
                    $skip = true;
                    break;
                }
            }
            
            if (in_array(basename($relativePath), $skipFiles)) {
                $skip = true;
            }
            
            if (!$skip && $file->isFile()) {
                $targetPath = ROOT_PATH . '/' . $relativePath;
                $targetDir = dirname($targetPath);
                
                // Create target directory if it doesn't exist
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                // Backup existing file if it exists
                if (file_exists($targetPath)) {
                    $backupPath = $targetPath . '.backup.' . date('YmdHis');
                    copy($targetPath, $backupPath);
                }
                
                // Copy file
                if (copy($file->getPathname(), $targetPath)) {
                    $restoredFiles[] = $relativePath;
                }
            }
        }
        
        return $restoredFiles;
    }
    
    /**
     * Clean up temporary files
     */
    public static function cleanup($extractDir) {
        if (is_dir($extractDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($extractDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            
            rmdir($extractDir);
        }
    }
}

// Handle form submissions
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'upload_backup':
                if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Please select a valid backup file');
                }
                
                $file = $_FILES['backup_file'];
                
                // Validate file
                if (!FileHelper::validateExtension($file['name'], ALLOWED_BACKUP_EXTENSIONS)) {
                    throw new Exception('Invalid file type. Only ZIP files are allowed.');
                }
                
                if ($file['size'] > MAX_UPLOAD_SIZE) {
                    throw new Exception('File too large. Maximum size: ' . FileHelper::formatFileSize(MAX_UPLOAD_SIZE));
                }
                
                // Upload file
                $filename = FileHelper::uploadFile($file, BACKUP_PATH, ALLOWED_BACKUP_EXTENSIONS, MAX_UPLOAD_SIZE);
                
                $message = "Backup uploaded successfully: {$filename}";
                $messageType = 'success';
                
                Logger::logUpdate("Backup uploaded: {$filename}");
                break;
                
            case 'restore_backup':
                $backupFilename = Security::sanitizeInput($_POST['backup_filename'] ?? '');
                $backupFile = BACKUP_PATH . '/' . $backupFilename;
                
                if (!file_exists($backupFile) || strpos($backupFile, BACKUP_PATH) !== 0) {
                    throw new Exception('Backup file not found');
                }
                
                $restoreDatabase = isset($_POST['restore_database']);
                $restoreFiles = isset($_POST['restore_files']);
                
                if (!$restoreDatabase && !$restoreFiles) {
                    throw new Exception('Please select what to restore');
                }
                
                Logger::logUpdate("Starting restore from: {$backupFilename}");
                
                // Extract backup
                $extractResult = RestoreManager::extractBackup($backupFile);
                $extractDir = $extractResult['extract_dir'];
                
                $results = [];
                
                try {
                    // Restore database if requested
                    if ($restoreDatabase) {
                        $queryCount = RestoreManager::restoreDatabase($extractResult['sql_file']);
                        $results[] = "Database restored successfully ({$queryCount} queries executed)";
                        Logger::logUpdate("Database restored - {$queryCount} queries executed");
                    }
                    
                    // Restore files if requested
                    if ($restoreFiles) {
                        $skipDirs = [];
                        $skipFiles = [];
                        
                        // Check which items to skip
                        if (!isset($_POST['restore_config'])) {
                            $skipFiles[] = 'config.php';
                        }
                        if (!isset($_POST['restore_htaccess'])) {
                            $skipFiles[] = '.htaccess';
                        }
                        if (!isset($_POST['restore_logs'])) {
                            $skipDirs[] = 'logs';
                        }
                        if (!isset($_POST['restore_uploads'])) {
                            $skipDirs[] = 'uploads';
                        }
                        
                        $restoredFiles = RestoreManager::restoreFiles($extractDir, [
                            'skip_dirs' => $skipDirs,
                            'skip_files' => $skipFiles
                        ]);
                        
                        $results[] = "Files restored successfully (" . count($restoredFiles) . " files)";
                        Logger::logUpdate("Files restored - " . count($restoredFiles) . " files processed");
                    }
                    
                    $message = implode("<br>", $results);
                    $messageType = 'success';
                    
                } finally {
                    // Clean up temporary files
                    RestoreManager::cleanup($extractDir);
                }
                
                break;
                
            case 'delete_backup':
                $backupFilename = Security::sanitizeInput($_POST['backup_filename'] ?? '');
                $backupFile = BACKUP_PATH . '/' . $backupFilename;
                
                if (!file_exists($backupFile) || strpos($backupFile, BACKUP_PATH) !== 0) {
                    throw new Exception('Backup file not found');
                }
                
                if (unlink($backupFile)) {
                    $message = "Backup deleted successfully: {$backupFilename}";
                    $messageType = 'success';
                    Logger::logUpdate("Backup deleted: {$backupFilename}");
                } else {
                    throw new Exception('Failed to delete backup file');
                }
                break;
                
            case 'create_backup':
                Logger::logUpdate("Creating new backup");
                
                // Use the backup creator from update module
                require_once 'update.php';
                $backup = BackupCreator::createBackup();
                
                $message = "Backup created successfully: " . basename($backup['file']) . " (" . FileHelper::formatFileSize($backup['size']) . ")";
                $messageType = 'success';
                
                Logger::logUpdate("Backup created: " . basename($backup['file']) . " (" . FileHelper::formatFileSize($backup['size']) . ")");
                break;
        }
    }
} catch (Exception $e) {
    $message = 'Error: ' . $e->getMessage();
    $messageType = 'error';
    Logger::log("Restore error: " . $e->getMessage(), LOG_LEVEL_ERROR, 'update');
}

// Get list of available backups
$backups = RestoreManager::listBackups();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>N3XT Communication - Backup & Restore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>N3XT Communication</h1>
            <p style="margin-top: 10px; opacity: 0.9;">Backup & Restore Manager</p>
        </div>
        
        <div class="main-content">
            <nav class="nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a href="update.php" class="nav-link">System Update</a>
                    </li>
                    <li class="nav-item">
                        <a href="restore.php" class="nav-link active">Backup & Restore</a>
                    </li>
                </ul>
            </nav>
            
            <div class="content-area">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Create Backup Section -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Create New Backup</h2>
                    </div>
                    <div class="card-body">
                        <p>Create a complete backup of your system including database and files.</p>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="create_backup">
                            <button type="submit" class="btn btn-success">Create Backup</button>
                        </form>
                    </div>
                </div>
                
                <!-- Upload Backup Section -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Upload Backup</h2>
                    </div>
                    <div class="card-body">
                        <p>Upload a backup ZIP file containing backup.sql and system files.</p>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="upload_backup">
                            
                            <div class="form-group">
                                <label for="backup_file" class="form-label">Backup File (ZIP):</label>
                                <div class="file-upload">
                                    <input type="file" 
                                           id="backup_file" 
                                           name="backup_file" 
                                           accept=".zip"
                                           required>
                                    <label for="backup_file" class="file-upload-label">
                                        Click to select backup file (ZIP format only)
                                        <br><small>Maximum size: <?php echo FileHelper::formatFileSize(MAX_UPLOAD_SIZE); ?></small>
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Upload Backup</button>
                        </form>
                    </div>
                </div>
                
                <!-- Available Backups Section -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Available Backups</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($backups)): ?>
                            <p>No backups found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Filename</th>
                                            <th>Size</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backups as $backup): ?>
                                            <tr>
                                                <td style="font-family: monospace; font-size: 12px;">
                                                    <?php echo htmlspecialchars($backup['filename']); ?>
                                                </td>
                                                <td><?php echo FileHelper::formatFileSize($backup['size']); ?></td>
                                                <td><?php echo date('Y-m-d H:i:s', $backup['created']); ?></td>
                                                <td>
                                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                        <a href="?download=<?php echo urlencode($backup['filename']); ?>" 
                                                           class="btn btn-secondary" 
                                                           style="font-size: 12px; padding: 5px 10px;">Download</a>
                                                        
                                                        <button type="button" 
                                                                class="btn btn-primary" 
                                                                style="font-size: 12px; padding: 5px 10px;"
                                                                onclick="showRestoreDialog('<?php echo htmlspecialchars($backup['filename']); ?>')">
                                                            Restore
                                                        </button>
                                                        
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to delete this backup?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                            <input type="hidden" name="action" value="delete_backup">
                                                            <input type="hidden" name="backup_filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                                            <button type="submit" 
                                                                    class="btn btn-danger" 
                                                                    style="font-size: 12px; padding: 5px 10px;">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Restore Dialog -->
    <div id="restoreDialog" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 8px; padding: 30px; max-width: 500px; width: 90%;">
            <h3 style="margin-top: 0;">Restore Backup</h3>
            
            <form method="POST" id="restoreForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="restore_backup">
                <input type="hidden" name="backup_filename" id="restoreFilename">
                
                <div class="alert alert-warning">
                    <strong>Warning:</strong> This will replace existing data. Make sure you have a recent backup!
                </div>
                
                <div class="form-group">
                    <label class="form-label">What to restore:</label>
                    
                    <div style="margin: 10px 0;">
                        <label style="display: flex; align-items: center; margin-bottom: 10px;">
                            <input type="checkbox" name="restore_database" checked style="margin-right: 10px;">
                            Database (backup.sql)
                        </label>
                        
                        <label style="display: flex; align-items: center; margin-bottom: 10px;">
                            <input type="checkbox" name="restore_files" checked style="margin-right: 10px;">
                            System Files
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">File restore options:</label>
                    
                    <div style="margin: 10px 0;">
                        <label style="display: flex; align-items: center; margin-bottom: 5px;">
                            <input type="checkbox" name="restore_config" style="margin-right: 10px;">
                            Restore configuration files (config.php)
                        </label>
                        
                        <label style="display: flex; align-items: center; margin-bottom: 5px;">
                            <input type="checkbox" name="restore_htaccess" style="margin-right: 10px;">
                            Restore .htaccess file
                        </label>
                        
                        <label style="display: flex; align-items: center; margin-bottom: 5px;">
                            <input type="checkbox" name="restore_logs" style="margin-right: 10px;">
                            Restore log files
                        </label>
                        
                        <label style="display: flex; align-items: center; margin-bottom: 5px;">
                            <input type="checkbox" name="restore_uploads" style="margin-right: 10px;">
                            Restore uploaded files
                        </label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="hideRestoreDialog()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Restore</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showRestoreDialog(filename) {
            document.getElementById('restoreFilename').value = filename;
            document.getElementById('restoreDialog').style.display = 'block';
        }
        
        function hideRestoreDialog() {
            document.getElementById('restoreDialog').style.display = 'none';
        }
        
        // Close dialog when clicking outside
        document.getElementById('restoreDialog').addEventListener('click', function(e) {
            if (e.target === this) {
                hideRestoreDialog();
            }
        });
        
        // File upload preview
        document.getElementById('backup_file').addEventListener('change', function() {
            const label = document.querySelector('.file-upload-label');
            if (this.files.length > 0) {
                const file = this.files[0];
                label.innerHTML = `Selected: ${file.name}<br><small>Size: ${(file.size / 1024 / 1024).toFixed(2)} MB</small>`;
            }
        });
        
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