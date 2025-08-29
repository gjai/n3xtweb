<?php
/**
 * N3XT WEB - System Uninstall
 * 
 * Complete system uninstallation with backup creation and double confirmation.
 */

// Define security constant before including any files
define('IN_N3XTWEB', true);

require_once dirname(__DIR__) . '/includes/functions.php';

// Check authentication
if (!Session::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$step = $_GET['step'] ?? '1';

// Handle uninstall process
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'confirm_uninstall':
            // First confirmation step
            if (isset($_POST['confirm_checkbox']) && $_POST['confirm_checkbox'] === 'yes') {
                $step = '2';
            } else {
                $error = 'Vous devez cocher la case de confirmation pour continuer.';
            }
            break;
            
        case 'create_backup_and_uninstall':
            // Second confirmation with typed confirmation
            $confirmation_text = $_POST['confirmation_text'] ?? '';
            if (strtoupper($confirmation_text) === 'SUPPRIMER') {
                try {
                    // Create backup before uninstall
                    $backupResult = UninstallManager::createFinalBackup();
                    
                    if ($backupResult['success']) {
                        $_SESSION['backup_file'] = $backupResult['file'];
                        $_SESSION['backup_download_ready'] = true;
                        $step = '3';
                        $success = 'Sauvegarde créée avec succès. Vous pouvez maintenant télécharger la sauvegarde avant la désinstallation finale.';
                    } else {
                        $error = 'Erreur lors de la création de la sauvegarde: ' . $backupResult['error'];
                    }
                } catch (Exception $e) {
                    $error = 'Erreur critique: ' . $e->getMessage();
                    Logger::log("Uninstall backup error: " . $e->getMessage(), LOG_LEVEL_ERROR, 'uninstall');
                }
            } else {
                $error = 'Vous devez taper "SUPPRIMER" pour confirmer la désinstallation.';
            }
            break;
            
        case 'download_backup':
            // Download backup file
            if (isset($_SESSION['backup_file']) && file_exists($_SESSION['backup_file'])) {
                $backupFile = $_SESSION['backup_file'];
                $filename = basename($backupFile);
                
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($backupFile));
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                
                readfile($backupFile);
                exit;
            } else {
                $error = 'Fichier de sauvegarde introuvable.';
            }
            break;
            
        case 'final_uninstall':
            // Final uninstall execution
            try {
                $uninstallResult = UninstallManager::executeUninstall();
                
                if ($uninstallResult['success']) {
                    // Clear session and redirect to install.php
                    session_destroy();
                    header('Location: ../install.php?uninstall=success');
                    exit;
                } else {
                    $error = 'Erreur lors de la désinstallation: ' . $uninstallResult['error'];
                }
            } catch (Exception $e) {
                $error = 'Erreur critique lors de la désinstallation: ' . $e->getMessage();
                Logger::log("Uninstall execution error: " . $e->getMessage(), LOG_LEVEL_ERROR, 'uninstall');
            }
            break;
    }
}

/**
 * Uninstall Manager Class
 */
class UninstallManager {
    
    /**
     * Create final backup before uninstall
     */
    public static function createFinalBackup() {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupDir = BACKUP_PATH . '/final_backup_' . $timestamp;
            $backupFile = $backupDir . '.zip';
            
            // Create backup directory if it doesn't exist
            if (!is_dir(BACKUP_PATH)) {
                mkdir(BACKUP_PATH, 0755, true);
            }
            
            // Create ZIP archive
            $zip = new ZipArchive();
            if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
                throw new Exception('Impossible de créer l\'archive de sauvegarde');
            }
            
            // Add all files except logs and tmp
            $excludeDirs = ['logs', 'tmp'];
            self::addDirectoryToZip($zip, ROOT_PATH, '', $excludeDirs);
            
            // Add database backup
            $sqlFile = self::createDatabaseBackup();
            if ($sqlFile && file_exists($sqlFile)) {
                $zip->addFile($sqlFile, 'database_backup.sql');
            }
            
            $zip->close();
            
            // Clean up temporary SQL file
            if ($sqlFile && file_exists($sqlFile)) {
                unlink($sqlFile);
            }
            
            Logger::log("Final backup created: " . basename($backupFile), LOG_LEVEL_INFO, 'uninstall');
            
            return [
                'success' => true,
                'file' => $backupFile,
                'size' => filesize($backupFile)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Add directory to ZIP archive recursively
     */
    private static function addDirectoryToZip($zip, $dir, $zipPath = '', $excludeDirs = []) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            $filePath = $file->getPathname();
            $relativePath = str_replace($dir . '/', '', $filePath);
            
            // Skip excluded directories
            $skip = false;
            foreach ($excludeDirs as $excludeDir) {
                if (strpos($relativePath, $excludeDir . '/') === 0) {
                    $skip = true;
                    break;
                }
            }
            
            if (!$skip && $file->isFile()) {
                $zipPath = $relativePath;
                $zip->addFile($filePath, $zipPath);
            }
        }
    }
    
    /**
     * Create database backup
     */
    private static function createDatabaseBackup() {
        try {
            $db = Database::getInstance();
            $tempFile = sys_get_temp_dir() . '/n3xt_final_backup_' . date('Y-m-d_H-i-s') . '.sql';
            
            // Get all tables
            $tables = $db->fetchAll("SHOW TABLES");
            
            $sql = "-- N3XT WEB Final Backup\n";
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
                            return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                        }, array_values($row));
                        $values[] = '(' . implode(', ', $rowValues) . ')';
                    }
                    $sql .= implode(",\n", $values) . ";\n\n";
                }
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            file_put_contents($tempFile, $sql);
            return $tempFile;
            
        } catch (Exception $e) {
            Logger::log("Database backup failed: " . $e->getMessage(), LOG_LEVEL_ERROR, 'uninstall');
            return false;
        }
    }
    
    /**
     * Execute complete system uninstall
     */
    public static function executeUninstall() {
        try {
            Logger::log("Starting system uninstall", LOG_LEVEL_INFO, 'uninstall');
            
            // 1. Drop all database tables
            self::dropAllTables();
            
            // 2. Reset configuration
            self::resetConfiguration();
            
            // 3. Restore install.php
            self::restoreInstallFile();
            
            // 4. Clean up session and admin files
            self::cleanupSystem();
            
            Logger::log("System uninstall completed successfully", LOG_LEVEL_INFO, 'uninstall');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            Logger::log("Uninstall failed: " . $e->getMessage(), LOG_LEVEL_ERROR, 'uninstall');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Drop all database tables
     */
    private static function dropAllTables() {
        try {
            $db = Database::getInstance();
            
            // Disable foreign key checks
            $db->execute("SET FOREIGN_KEY_CHECKS = 0");
            
            // Get all tables
            $tables = $db->fetchAll("SHOW TABLES");
            
            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                $db->execute("DROP TABLE IF EXISTS `{$tableName}`");
                Logger::log("Dropped table: {$tableName}", LOG_LEVEL_INFO, 'uninstall');
            }
            
            // Re-enable foreign key checks
            $db->execute("SET FOREIGN_KEY_CHECKS = 1");
            
        } catch (Exception $e) {
            throw new Exception("Failed to drop database tables: " . $e->getMessage());
        }
    }
    
    /**
     * Reset configuration to default state
     */
    private static function resetConfiguration() {
        $configFile = ROOT_PATH . '/config/config.php';
        
        $defaultConfig = '<?php
/**
 * N3XT WEB - Configuration File
 * 
 * This file contains core configuration settings for the N3XT WEB system.
 */

// Prevent direct access
if (!defined(\'IN_N3XTWEB\')) {
    die(\'Direct access not allowed\');
}

// Database configuration (to be configured during installation)
define(\'DB_HOST\', \'nxtxyzylie618.mysql.db\');
define(\'DB_NAME\', \'nxtxyzylie618_db\');
define(\'DB_USER\', \'nxtxyzylie618_user\');
define(\'DB_PASS\', \'secure_password\');
define(\'DB_CHARSET\', \'utf8mb4\');

// Security settings
define(\'ADMIN_SESSION_TIMEOUT\', 3600);
define(\'MAX_LOGIN_ATTEMPTS\', 3);
define(\'LOGIN_LOCKOUT_TIME\', 900);
define(\'CSRF_TOKEN_LIFETIME\', 3600);

// File paths
define(\'ROOT_PATH\', dirname(__FILE__));
define(\'ADMIN_PATH\', ROOT_PATH . \'/admin\');
define(\'BACKUP_PATH\', ROOT_PATH . \'/backups\');
define(\'LOG_PATH\', ROOT_PATH . \'/logs\');
define(\'UPLOAD_PATH\', ROOT_PATH . \'/uploads\');

// System settings
define(\'SYSTEM_VERSION\', \'2.1.0\');
define(\'MAINTENANCE_MODE\', false);
define(\'DEBUG_MODE\', false);

// Logging levels
define(\'LOG_LEVEL_ERROR\', 1);
define(\'LOG_LEVEL_WARNING\', 2);
define(\'LOG_LEVEL_INFO\', 3);
define(\'LOG_LEVEL_DEBUG\', 4);
define(\'DEFAULT_LOG_LEVEL\', LOG_LEVEL_INFO);
?>';
        
        file_put_contents($configFile, $defaultConfig);
        Logger::log("Configuration reset to default state", LOG_LEVEL_INFO, 'uninstall');
    }
    
    /**
     * Restore install.php file
     */
    private static function restoreInstallFile() {
        $installFile = ROOT_PATH . '/install.php';
        
        // Install.php should already exist, but ensure it's accessible
        if (file_exists($installFile)) {
            chmod($installFile, 0644);
            Logger::log("Install.php restored and accessible", LOG_LEVEL_INFO, 'uninstall');
        }
    }
    
    /**
     * Clean up system files and sessions
     */
    private static function cleanupSystem() {
        // Clear session files
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Clean up temporary files
        $tempFiles = glob(sys_get_temp_dir() . '/n3xt_*');
        foreach ($tempFiles as $tempFile) {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }
        
        Logger::log("System cleanup completed", LOG_LEVEL_INFO, 'uninstall');
    }
}

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <link rel="icon" type="image/png" href="../fav.png">
    <title>N3XT WEB - Désinstallation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .warning-box {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        
        .confirmation-input {
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            color: white;
            width: 200px;
            margin: 10px auto;
        }
        
        .confirmation-input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .danger-btn {
            background: linear-gradient(135deg, #ff4757, #ff3838);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .danger-btn:hover {
            background: linear-gradient(135deg, #ff3838, #ff2d2d);
            transform: translateY(-2px);
        }
        
        .backup-info {
            background: rgba(45, 123, 75, 0.1);
            border: 1px solid rgba(45, 123, 75, 0.3);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            color: white;
        }
        
        .step.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .step.completed {
            background: linear-gradient(135deg, #2d7b4b, #4caf50);
        }
        
        .step.pending {
            background: #ccc;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <?php 
                $logoPath = '../fav.png';
                if (file_exists($logoPath)): ?>
                    <img src="<?php echo $logoPath; ?>?v=<?php echo time(); ?>" 
                         alt="N3XT WEB" 
                         style="max-width: 40px; max-height: 30px; margin-right: 10px; vertical-align: middle;">
                <?php endif; ?>
                N3XT WEB - Désinstallation
            </h1>
        </div>

        <div class="admin-content">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <strong>Erreur:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <strong>Succès:</strong> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?php echo $step == '1' ? 'active' : ($step > '1' ? 'completed' : 'pending'); ?>">1</div>
                <div class="step <?php echo $step == '2' ? 'active' : ($step > '2' ? 'completed' : 'pending'); ?>">2</div>
                <div class="step <?php echo $step == '3' ? 'active' : ($step > '3' ? 'completed' : 'pending'); ?>">3</div>
            </div>

            <?php if ($step == '1'): ?>
                <!-- Step 1: First confirmation -->
                <div class="warning-box">
                    <h2>⚠️ ATTENTION - DÉSINSTALLATION COMPLÈTE ⚠️</h2>
                    <p>Vous êtes sur le point de <strong>DÉSINSTALLER COMPLÈTEMENT</strong> N3XT WEB.</p>
                    <p>Cette action va :</p>
                    <ul style="text-align: left; max-width: 600px; margin: 20px auto;">
                        <li>Supprimer <strong>TOUTES</strong> les données de la base de données</li>
                        <li>Remettre la configuration à zéro</li>
                        <li>Restaurer le fichier install.php</li>
                        <li>Créer une sauvegarde complète avant suppression</li>
                    </ul>
                    <p><strong>Cette action est IRRÉVERSIBLE !</strong></p>
                </div>

                <form method="POST" onsubmit="return confirmUninstall()">
                    <input type="hidden" name="action" value="confirm_uninstall">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <label style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                            <input type="checkbox" name="confirm_checkbox" value="yes" required>
                            <span>Je comprends les risques et souhaite continuer la désinstallation</span>
                        </label>
                    </div>

                    <div style="text-align: center; margin: 30px 0;">
                        <a href="index.php" class="btn btn-secondary" style="margin-right: 20px;">Annuler</a>
                        <button type="submit" class="danger-btn">Continuer la désinstallation</button>
                    </div>
                </form>

            <?php elseif ($step == '2'): ?>
                <!-- Step 2: Type confirmation -->
                <div class="warning-box">
                    <h2>🔐 CONFIRMATION FINALE</h2>
                    <p>Pour confirmer définitivement la désinstallation, tapez le mot :</p>
                    <p style="font-size: 24px; font-weight: bold; letter-spacing: 2px;">SUPPRIMER</p>
                    <p>Une sauvegarde complète sera créée avant la suppression.</p>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="create_backup_and_uninstall">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <input type="text" 
                               name="confirmation_text" 
                               class="confirmation-input" 
                               placeholder="SUPPRIMER"
                               autocomplete="off"
                               required>
                    </div>

                    <div style="text-align: center; margin: 30px 0;">
                        <a href="?step=1" class="btn btn-secondary" style="margin-right: 20px;">Retour</a>
                        <button type="submit" class="danger-btn">Créer la sauvegarde et continuer</button>
                    </div>
                </form>

            <?php elseif ($step == '3'): ?>
                <!-- Step 3: Download backup and final uninstall -->
                <div class="backup-info">
                    <h3>✅ Sauvegarde créée avec succès</h3>
                    <p>Une sauvegarde complète de votre système a été créée.</p>
                    <?php if (isset($_SESSION['backup_file'])): ?>
                        <p><strong>Fichier:</strong> <?php echo basename($_SESSION['backup_file']); ?></p>
                        <p><strong>Taille:</strong> <?php echo round(filesize($_SESSION['backup_file']) / 1024 / 1024, 2); ?> MB</p>
                    <?php endif; ?>
                    <p><strong>Recommandation:</strong> Téléchargez cette sauvegarde avant de procéder à la désinstallation finale.</p>
                </div>

                <div style="text-align: center; margin: 30px 0;">
                    <form method="POST" style="display: inline-block; margin-right: 20px;">
                        <input type="hidden" name="action" value="download_backup">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <button type="submit" class="btn btn-primary">📥 Télécharger la sauvegarde</button>
                    </form>

                    <form method="POST" style="display: inline-block;" onsubmit="return confirmFinalUninstall()">
                        <input type="hidden" name="action" value="final_uninstall">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <button type="submit" class="danger-btn">🗑️ DÉSINSTALLER DÉFINITIVEMENT</button>
                    </form>
                </div>

                <div style="text-align: center; margin: 20px 0;">
                    <a href="index.php" class="btn btn-secondary">Annuler et retourner au tableau de bord</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmUninstall() {
            return confirm('Êtes-vous absolument certain de vouloir désinstaller N3XT WEB ? Cette action est irréversible !');
        }

        function confirmFinalUninstall() {
            return confirm('DERNIÈRE CHANCE ! Voulez-vous vraiment désinstaller définitivement N3XT WEB ? Toutes les données seront perdues !');
        }
    </script>
</body>
</html>