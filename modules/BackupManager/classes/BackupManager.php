<?php
/**
 * N3XT WEB - BackupManager Module
 * 
 * Gestionnaire de sauvegardes automatiques et manuelles.
 * Gère les sauvegardes de base de données et de fichiers.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

class BackupManager extends BaseModule {
    
    private $backupPath;
    private $notificationManager;
    
    public function __construct() {
        parent::__construct('backupmanager');
        $this->backupPath = BACKUP_PATH;
        $this->initialize();
    }
    
    /**
     * Configuration par défaut du module
     */
    protected function getDefaultConfiguration() {
        return [
            'enabled' => true,
            'auto_backup' => true,
            'retention_days' => 30,
            'compression' => true,
            'include_files' => true,
            'include_uploads' => false,
            'max_backup_size' => 1073741824, // 1GB
            'backup_schedule' => 'daily', // daily, weekly, monthly
            'exclude_extensions' => 'tmp,log,cache',
            'version' => '1.0.0',
            'description' => 'Gestionnaire de sauvegardes automatiques'
        ];
    }
    
    /**
     * Initialise le module
     */
    public function initialize() {
        // Créer le répertoire de sauvegarde si nécessaire
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
        
        // Charger le gestionnaire de notifications
        if (class_exists('NotificationManager')) {
            $this->notificationManager = new NotificationManager();
        }
    }
    
    /**
     * Crée une sauvegarde complète
     */
    public function createBackup($type = 'manual', $notes = '') {
        try {
            $this->checkPermissions();
            
            $this->logAction('Starting backup creation', "Type: {$type}");
            
            // Créer l'enregistrement de sauvegarde
            $backupId = $this->createBackupRecord($type, $notes);
            
            // Générer le nom de fichier
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "n3xtweb_backup_{$timestamp}_{$type}";
            $filepath = $this->backupPath . '/' . $filename;
            
            // Créer la sauvegarde
            $backupData = $this->performBackup($filepath, $backupId);
            
            // Finaliser l'enregistrement
            $this->finalizeBackupRecord($backupId, $backupData);
            
            $this->logAction('Backup created successfully', "File: {$backupData['filename']}, Size: " . FileHelper::formatFileSize($backupData['size']));
            
            // Créer une notification de succès
            if ($this->notificationManager) {
                $this->notificationManager->createNotification(
                    'backup',
                    'Sauvegarde créée',
                    "Sauvegarde {$type} créée avec succès. Taille: " . FileHelper::formatFileSize($backupData['size']),
                    'medium',
                    [
                        'backup_id' => $backupId,
                        'filename' => $backupData['filename'],
                        'size' => $backupData['size'],
                        'type' => $type
                    ]
                );
            }
            
            return [
                'success' => true,
                'id' => $backupId,
                'filename' => $backupData['filename'],
                'filepath' => $backupData['filepath'],
                'size' => $backupData['size']
            ];
            
        } catch (Exception $e) {
            $this->logAction('Backup creation failed', $e->getMessage(), LOG_LEVEL_ERROR);
            
            if (isset($backupId)) {
                $this->updateBackupRecord($backupId, ['status' => 'failed']);
            }
            
            throw $e;
        }
    }
    
    /**
     * Effectue la sauvegarde
     */
    private function performBackup($basePath, $backupId) {
        $compression = $this->getConfig('compression', true);
        $includeFiles = $this->getConfig('include_files', true);
        
        if ($compression) {
            $filename = basename($basePath) . '.zip';
            $filepath = dirname($basePath) . '/' . $filename;
            return $this->createZipBackup($filepath, $includeFiles, $backupId);
        } else {
            $filename = basename($basePath) . '.tar';
            $filepath = dirname($basePath) . '/' . $filename;
            return $this->createTarBackup($filepath, $includeFiles, $backupId);
        }
    }
    
    /**
     * Crée une sauvegarde ZIP
     */
    private function createZipBackup($filepath, $includeFiles, $backupId) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive class not available');
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        if ($result !== TRUE) {
            throw new Exception("Failed to create ZIP archive: {$result}");
        }
        
        // Sauvegarder la base de données
        $this->updateBackupRecord($backupId, ['status' => 'creating']);
        
        $sqlFile = $this->createDatabaseBackup();
        if ($sqlFile) {
            $zip->addFile($sqlFile, 'database.sql');
        }
        
        // Sauvegarder les fichiers si activé
        if ($includeFiles) {
            $this->addFilesToZip($zip, ROOT_PATH, '', $backupId);
        }
        
        $zip->close();
        
        // Nettoyer le fichier SQL temporaire
        if (isset($sqlFile) && file_exists($sqlFile)) {
            unlink($sqlFile);
        }
        
        $size = filesize($filepath);
        
        return [
            'filename' => basename($filepath),
            'filepath' => $filepath,
            'size' => $size
        ];
    }
    
    /**
     * Ajoute les fichiers au ZIP
     */
    private function addFilesToZip($zip, $sourcePath, $relativePath, $backupId) {
        $excludeDirs = ['backups', 'tmp', 'logs'];
        $excludeExtensions = explode(',', $this->getConfig('exclude_extensions', 'tmp,log,cache'));
        $includeUploads = $this->getConfig('include_uploads', false);
        
        if (!$includeUploads) {
            $excludeDirs[] = 'uploads';
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        $filesAdded = 0;
        
        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativeFilePath = $relativePath . substr($filePath, strlen($sourcePath) + 1);
            
            // Vérifier les exclusions
            $pathParts = explode('/', $relativeFilePath);
            if (!empty(array_intersect($pathParts, $excludeDirs))) {
                continue;
            }
            
            if ($file->isFile()) {
                $extension = strtolower($file->getExtension());
                if (in_array($extension, $excludeExtensions)) {
                    continue;
                }
                
                // Vérifier la taille du fichier
                if ($file->getSize() > 50 * 1024 * 1024) { // 50MB max par fichier
                    $this->logAction('Large file skipped in backup', "File: {$relativeFilePath}, Size: " . FileHelper::formatFileSize($file->getSize()), LOG_LEVEL_WARNING);
                    continue;
                }
                
                $zip->addFile($filePath, $relativeFilePath);
                $filesAdded++;
                
                // Mettre à jour le progrès tous les 100 fichiers
                if ($filesAdded % 100 === 0) {
                    $this->logAction('Backup progress', "Files added: {$filesAdded}");
                }
            }
        }
        
        return $filesAdded;
    }
    
    /**
     * Crée une sauvegarde de la base de données
     */
    private function createDatabaseBackup() {
        try {
            $db = Database::getInstance();
            $prefix = Logger::getTablePrefix();
            
            // Générer le fichier SQL temporaire
            $sqlFile = sys_get_temp_dir() . '/n3xtweb_db_backup_' . uniqid() . '.sql';
            
            // Obtenir la liste des tables
            $tables = $db->fetchAll("SHOW TABLES");
            
            $sql = "-- N3XT WEB Database Backup\n";
            $sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Database: " . (defined('DB_NAME') ? DB_NAME : 'unknown') . "\n\n";
            
            $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
            $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
            $sql .= "SET time_zone = \"+00:00\";\n\n";
            
            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                
                // Exporter la structure de la table
                $createTable = $db->fetchOne("SHOW CREATE TABLE `{$tableName}`");
                $sql .= "-- Structure for table `{$tableName}`\n";
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= $createTable['Create Table'] . ";\n\n";
                
                // Exporter les données
                $rows = $db->fetchAll("SELECT * FROM `{$tableName}`");
                
                if (!empty($rows)) {
                    $sql .= "-- Data for table `{$tableName}`\n";
                    
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($row as $value) {
                            if ($value === null) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = "'" . $db->quote($value) . "'";
                            }
                        }
                        
                        $sql .= "INSERT INTO `{$tableName}` VALUES (" . implode(', ', $values) . ");\n";
                    }
                    
                    $sql .= "\n";
                }
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            
            // Écrire le fichier SQL
            if (file_put_contents($sqlFile, $sql) === false) {
                throw new Exception('Failed to write SQL backup file');
            }
            
            return $sqlFile;
            
        } catch (Exception $e) {
            $this->logAction('Database backup failed', $e->getMessage(), LOG_LEVEL_ERROR);
            return null;
        }
    }
    
    /**
     * Restaure une sauvegarde
     */
    public function restoreBackup($backupId) {
        try {
            $this->checkPermissions();
            
            // Récupérer les informations de la sauvegarde
            $backup = $this->getBackupRecord($backupId);
            if (!$backup) {
                throw new Exception('Backup record not found');
            }
            
            if (!file_exists($backup['filepath'])) {
                throw new Exception('Backup file not found');
            }
            
            $this->logAction('Starting backup restoration', "ID: {$backupId}, File: {$backup['filename']}");
            
            // Créer une sauvegarde de sécurité avant restauration
            $securityBackup = $this->createBackup('pre_restore', 'Sauvegarde automatique avant restauration');
            
            // Extraire la sauvegarde
            $extractPath = sys_get_temp_dir() . '/n3xtweb_restore_' . uniqid();
            $this->extractBackup($backup['filepath'], $extractPath);
            
            // Restaurer la base de données
            $dbRestored = $this->restoreDatabase($extractPath . '/database.sql');
            
            // Restaurer les fichiers si présents
            $filesRestored = 0;
            if (is_dir($extractPath) && $backup['backup_type'] === 'full') {
                $filesRestored = $this->restoreFiles($extractPath);
            }
            
            // Nettoyer les fichiers temporaires
            $this->removeDirectory($extractPath);
            
            $this->logAction('Backup restored successfully', "Database: {$dbRestored}, Files: {$filesRestored}");
            
            // Créer une notification de succès
            if ($this->notificationManager) {
                $this->notificationManager->createNotification(
                    'backup',
                    'Sauvegarde restaurée',
                    "Sauvegarde {$backup['filename']} restaurée avec succès.",
                    'high',
                    [
                        'backup_id' => $backupId,
                        'security_backup_id' => $securityBackup['id'],
                        'files_restored' => $filesRestored
                    ]
                );
            }
            
            return [
                'success' => true,
                'database_restored' => $dbRestored,
                'files_restored' => $filesRestored,
                'security_backup_id' => $securityBackup['id']
            ];
            
        } catch (Exception $e) {
            $this->logAction('Backup restoration failed', $e->getMessage(), LOG_LEVEL_ERROR);
            
            // Nettoyer les fichiers temporaires en cas d'erreur
            if (isset($extractPath) && is_dir($extractPath)) {
                $this->removeDirectory($extractPath);
            }
            
            throw $e;
        }
    }
    
    /**
     * Extrait une sauvegarde
     */
    private function extractBackup($backupPath, $extractPath) {
        if (!is_dir($extractPath)) {
            mkdir($extractPath, 0755, true);
        }
        
        $extension = strtolower(pathinfo($backupPath, PATHINFO_EXTENSION));
        
        if ($extension === 'zip') {
            return $this->extractZipBackup($backupPath, $extractPath);
        } elseif ($extension === 'tar') {
            return $this->extractTarBackup($backupPath, $extractPath);
        } else {
            throw new Exception('Unsupported backup format');
        }
    }
    
    /**
     * Extrait une sauvegarde ZIP
     */
    private function extractZipBackup($zipPath, $extractPath) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive class not available');
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($zipPath);
        
        if ($result !== TRUE) {
            throw new Exception("Failed to open backup archive: {$result}");
        }
        
        $zip->extractTo($extractPath);
        $zip->close();
        
        return true;
    }
    
    /**
     * Restaure la base de données depuis un fichier SQL
     */
    private function restoreDatabase($sqlFile) {
        if (!file_exists($sqlFile)) {
            return false;
        }
        
        try {
            $db = Database::getInstance();
            $sql = file_get_contents($sqlFile);
            
            // Diviser le SQL en requêtes individuelles
            $queries = array_filter(array_map('trim', explode(';', $sql)));
            
            $executed = 0;
            foreach ($queries as $query) {
                if (!empty($query) && !preg_match('/^--/', $query)) {
                    $db->execute($query);
                    $executed++;
                }
            }
            
            return $executed;
            
        } catch (Exception $e) {
            $this->logAction('Database restore failed', $e->getMessage(), LOG_LEVEL_ERROR);
            throw $e;
        }
    }
    
    /**
     * Restaure les fichiers depuis l'extraction
     */
    private function restoreFiles($extractPath) {
        $filesRestored = 0;
        $excludeDirs = ['backups', 'tmp'];
        
        // Parcourir récursivement l'extraction
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($extractPath) + 1);
            
            // Ignorer les fichiers système de la sauvegarde
            if ($relativePath === 'database.sql') {
                continue;
            }
            
            // Vérifier les exclusions
            $pathParts = explode('/', $relativePath);
            if (!empty(array_intersect($pathParts, $excludeDirs))) {
                continue;
            }
            
            $destPath = ROOT_PATH . '/' . $relativePath;
            
            if ($file->isFile()) {
                // Créer le répertoire de destination si nécessaire
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                
                // Copier le fichier
                if (copy($filePath, $destPath)) {
                    $filesRestored++;
                }
            }
        }
        
        return $filesRestored;
    }
    
    /**
     * Supprime un répertoire récursivement
     */
    private function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
    
    /**
     * Supprime une sauvegarde
     */
    public function deleteBackup($backupId) {
        try {
            $this->checkPermissions();
            
            $backup = $this->getBackupRecord($backupId);
            if (!$backup) {
                throw new Exception('Backup record not found');
            }
            
            // Supprimer le fichier
            if (file_exists($backup['filepath'])) {
                unlink($backup['filepath']);
            }
            
            // Mettre à jour l'enregistrement
            $this->updateBackupRecord($backupId, ['status' => 'deleted']);
            
            $this->logAction('Backup deleted', "ID: {$backupId}, File: {$backup['filename']}");
            
            return true;
            
        } catch (Exception $e) {
            $this->logAction('Failed to delete backup', $e->getMessage(), LOG_LEVEL_ERROR);
            throw $e;
        }
    }
    
    /**
     * Nettoie les anciennes sauvegardes
     */
    public function cleanupOldBackups() {
        try {
            $retentionDays = (int) $this->getConfig('retention_days', 30);
            
            // Récupérer les anciennes sauvegardes
            $sql = "SELECT * FROM " . Logger::getTablePrefix() . "backups 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) 
                    AND status = 'completed'";
            
            $oldBackups = $this->db->fetchAll($sql, [$retentionDays]);
            
            $deleted = 0;
            foreach ($oldBackups as $backup) {
                try {
                    $this->deleteBackup($backup['id']);
                    $deleted++;
                } catch (Exception $e) {
                    $this->logAction('Failed to delete old backup', "ID: {$backup['id']}, Error: " . $e->getMessage(), LOG_LEVEL_WARNING);
                }
            }
            
            $this->logAction('Old backups cleaned up', "Deleted: {$deleted}, Retention: {$retentionDays} days");
            
            return $deleted;
            
        } catch (Exception $e) {
            $this->logAction('Failed to cleanup old backups', $e->getMessage(), LOG_LEVEL_ERROR);
            return 0;
        }
    }
    
    /**
     * Crée un enregistrement de sauvegarde
     */
    private function createBackupRecord($type, $notes = '') {
        $data = [
            'filename' => '', // Sera mis à jour plus tard
            'filepath' => '', // Sera mis à jour plus tard
            'type' => $type,
            'backup_type' => 'full',
            'size_bytes' => 0, // Sera mis à jour plus tard
            'compressed' => $this->getConfig('compression', true),
            'status' => 'creating',
            'created_by' => $_SESSION['admin_username'] ?? 'system',
            'notes' => $notes
        ];
        
        $sql = "INSERT INTO " . Logger::getTablePrefix() . "backups 
                (filename, filepath, type, backup_type, size_bytes, compressed, status, created_by, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, array_values($data));
        return $this->db->getLastInsertId();
    }
    
    /**
     * Finalise un enregistrement de sauvegarde
     */
    private function finalizeBackupRecord($backupId, $backupData) {
        $this->updateBackupRecord($backupId, [
            'filename' => $backupData['filename'],
            'filepath' => $backupData['filepath'],
            'size_bytes' => $backupData['size'],
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Met à jour un enregistrement de sauvegarde
     */
    private function updateBackupRecord($backupId, $data) {
        $setParts = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $setParts[] = "{$key} = ?";
            $values[] = $value;
        }
        
        $values[] = $backupId;
        
        $sql = "UPDATE " . Logger::getTablePrefix() . "backups SET " . implode(', ', $setParts) . " WHERE id = ?";
        return $this->db->execute($sql, $values);
    }
    
    /**
     * Récupère un enregistrement de sauvegarde
     */
    private function getBackupRecord($backupId) {
        $sql = "SELECT * FROM " . Logger::getTablePrefix() . "backups WHERE id = ?";
        return $this->db->fetchOne($sql, [$backupId]);
    }
    
    /**
     * Retourne la liste des sauvegardes
     */
    public function getBackups($limit = 50) {
        $sql = "SELECT * FROM " . Logger::getTablePrefix() . "backups ORDER BY created_at DESC LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Retourne les statistiques des sauvegardes
     */
    public function getStatistics() {
        try {
            $stats = [];
            
            // Nombre total de sauvegardes
            $total = $this->db->fetchOne("SELECT COUNT(*) as count FROM " . Logger::getTablePrefix() . "backups");
            $stats['total_backups'] = (int) $total['count'];
            
            // Taille totale des sauvegardes
            $size = $this->db->fetchOne("SELECT SUM(size_bytes) as total_size FROM " . Logger::getTablePrefix() . "backups WHERE status = 'completed'");
            $stats['total_size'] = (int) $size['total_size'];
            
            // Dernière sauvegarde
            $lastBackup = $this->db->fetchOne("SELECT * FROM " . Logger::getTablePrefix() . "backups WHERE status = 'completed' ORDER BY created_at DESC LIMIT 1");
            $stats['last_backup'] = $lastBackup;
            
            // Statistiques par type
            $typeStats = $this->db->fetchAll("SELECT type, COUNT(*) as count FROM " . Logger::getTablePrefix() . "backups GROUP BY type");
            foreach ($typeStats as $stat) {
                $stats['by_type'][$stat['type']] = (int) $stat['count'];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            $this->logAction('Failed to get statistics', $e->getMessage(), LOG_LEVEL_ERROR);
            return [];
        }
    }
}