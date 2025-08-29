<?php
/**
 * N3XT WEB - MaintenanceManager Module
 * 
 * Gestionnaire de maintenance et nettoyage automatique.
 * Gère le nettoyage des logs, backups et fichiers temporaires.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

class MaintenanceManager extends BaseModule {
    
    private $notificationManager;
    
    public function __construct() {
        parent::__construct('maintenancemanager');
        $this->initialize();
    }
    
    /**
     * Configuration par défaut du module
     */
    protected function getDefaultConfiguration() {
        return [
            'enabled' => true,
            'auto_cleanup' => true,
            'log_retention_days' => 7,
            'backup_retention_days' => 30,
            'temp_cleanup_hours' => 24,
            'archive_before_delete' => true,
            'max_log_size_mb' => 50,
            'cleanup_schedule' => 'daily', // daily, weekly
            'notify_on_cleanup' => true,
            'version' => '1.0.0',
            'description' => 'Gestionnaire de maintenance et nettoyage automatique'
        ];
    }
    
    /**
     * Initialise le module
     */
    public function initialize() {
        // Charger le gestionnaire de notifications
        if (class_exists('NotificationManager')) {
            $this->notificationManager = new NotificationManager();
        }
        
        // Exécuter le nettoyage automatique si activé
        if ($this->getConfig('auto_cleanup', true) && $this->shouldRunCleanup()) {
            $this->runAutomaticCleanup();
        }
    }
    
    /**
     * Vérifie si le nettoyage automatique doit être exécuté
     */
    private function shouldRunCleanup() {
        $lastCleanup = (int) $this->getConfig('last_cleanup', 0);
        $schedule = $this->getConfig('cleanup_schedule', 'daily');
        
        $interval = $schedule === 'weekly' ? 7 * 24 * 3600 : 24 * 3600; // 7 jours ou 1 jour
        
        return (time() - $lastCleanup) >= $interval;
    }
    
    /**
     * Exécute le nettoyage automatique
     */
    public function runAutomaticCleanup() {
        try {
            $this->logAction('Starting automatic cleanup');
            
            $results = [
                'logs' => $this->cleanupLogs(),
                'backups' => $this->cleanupBackups(),
                'temp_files' => $this->cleanupTempFiles(),
                'database' => $this->optimizeDatabase()
            ];
            
            // Mettre à jour le timestamp de dernier nettoyage
            $this->setConfig('last_cleanup', time());
            
            $this->logAction('Automatic cleanup completed', json_encode($results));
            
            // Créer une notification si activé
            if ($this->getConfig('notify_on_cleanup', true) && $this->notificationManager) {
                $this->createCleanupNotification($results);
            }
            
            return $results;
            
        } catch (Exception $e) {
            $this->logAction('Automatic cleanup failed', $e->getMessage(), LOG_LEVEL_ERROR);
            throw $e;
        }
    }
    
    /**
     * Nettoie les anciens logs
     */
    public function cleanupLogs() {
        try {
            $this->checkPermissions();
            
            $retentionDays = (int) $this->getConfig('log_retention_days', 7);
            $archiveBeforeDelete = $this->getConfig('archive_before_delete', true);
            $maxLogSizeMB = (int) $this->getConfig('max_log_size_mb', 50);
            
            $taskId = $this->createMaintenanceTask('cleanup_logs');
            
            $results = [
                'files_processed' => 0,
                'files_deleted' => 0,
                'files_archived' => 0,
                'space_freed' => 0
            ];
            
            if (!is_dir(LOG_PATH)) {
                return $results;
            }
            
            $logFiles = glob(LOG_PATH . '/*.log*');
            $threshold = time() - ($retentionDays * 24 * 60 * 60);
            
            foreach ($logFiles as $logFile) {
                $results['files_processed']++;
                
                $fileSize = filesize($logFile);
                $fileTime = filemtime($logFile);
                $shouldDelete = false;
                
                // Vérifier l'âge du fichier
                if ($fileTime < $threshold) {
                    $shouldDelete = true;
                    $reason = 'age';
                } 
                // Vérifier la taille du fichier
                elseif ($fileSize > ($maxLogSizeMB * 1024 * 1024)) {
                    $shouldDelete = true;
                    $reason = 'size';
                }
                
                if ($shouldDelete) {
                    // Archiver avant suppression si activé
                    if ($archiveBeforeDelete) {
                        $archived = $this->archiveFile($logFile, 'logs');
                        if ($archived) {
                            $results['files_archived']++;
                        }
                    }
                    
                    // Supprimer le fichier
                    if (unlink($logFile)) {
                        $results['files_deleted']++;
                        $results['space_freed'] += $fileSize;
                        
                        $this->logAction('Log file deleted', "File: " . basename($logFile) . ", Reason: {$reason}, Size: " . FileHelper::formatFileSize($fileSize));
                    }
                }
            }
            
            $this->updateMaintenanceTask($taskId, 'completed', $results);
            
            $this->logAction('Log cleanup completed', "Processed: {$results['files_processed']}, Deleted: {$results['files_deleted']}, Archived: {$results['files_archived']}, Space freed: " . FileHelper::formatFileSize($results['space_freed']));
            
            return $results;
            
        } catch (Exception $e) {
            if (isset($taskId)) {
                $this->updateMaintenanceTask($taskId, 'failed', [], $e->getMessage());
            }
            
            $this->logAction('Log cleanup failed', $e->getMessage(), LOG_LEVEL_ERROR);
            throw $e;
        }
    }
    
    /**
     * Nettoie les anciennes sauvegardes
     */
    public function cleanupBackups() {
        try {
            $this->checkPermissions();
            
            $retentionDays = (int) $this->getConfig('backup_retention_days', 30);
            $archiveBeforeDelete = $this->getConfig('archive_before_delete', true);
            
            $taskId = $this->createMaintenanceTask('cleanup_backups');
            
            $results = [
                'files_processed' => 0,
                'files_deleted' => 0,
                'files_archived' => 0,
                'space_freed' => 0
            ];
            
            // Utiliser BackupManager si disponible
            if (class_exists('BackupManager')) {
                $backupManager = new BackupManager();
                $deleted = $backupManager->cleanupOldBackups();
                $results['files_deleted'] = $deleted;
            } else {
                // Nettoyage manuel du répertoire de sauvegarde
                if (is_dir(BACKUP_PATH)) {
                    $backupFiles = glob(BACKUP_PATH . '/*');
                    $threshold = time() - ($retentionDays * 24 * 60 * 60);
                    
                    foreach ($backupFiles as $backupFile) {
                        if (is_file($backupFile)) {
                            $results['files_processed']++;
                            
                            $fileSize = filesize($backupFile);
                            $fileTime = filemtime($backupFile);
                            
                            if ($fileTime < $threshold) {
                                // Archiver avant suppression si activé
                                if ($archiveBeforeDelete) {
                                    $archived = $this->archiveFile($backupFile, 'backups');
                                    if ($archived) {
                                        $results['files_archived']++;
                                    }
                                }
                                
                                // Supprimer le fichier
                                if (unlink($backupFile)) {
                                    $results['files_deleted']++;
                                    $results['space_freed'] += $fileSize;
                                    
                                    $this->logAction('Backup file deleted', "File: " . basename($backupFile) . ", Size: " . FileHelper::formatFileSize($fileSize));
                                }
                            }
                        }
                    }
                }
            }
            
            $this->updateMaintenanceTask($taskId, 'completed', $results);
            
            $this->logAction('Backup cleanup completed', "Processed: {$results['files_processed']}, Deleted: {$results['files_deleted']}, Space freed: " . FileHelper::formatFileSize($results['space_freed']));
            
            return $results;
            
        } catch (Exception $e) {
            if (isset($taskId)) {
                $this->updateMaintenanceTask($taskId, 'failed', [], $e->getMessage());
            }
            
            $this->logAction('Backup cleanup failed', $e->getMessage(), LOG_LEVEL_ERROR);
            throw $e;
        }
    }
    
    /**
     * Nettoie les fichiers temporaires
     */
    public function cleanupTempFiles() {
        try {
            $this->checkPermissions();
            
            $cleanupHours = (int) $this->getConfig('temp_cleanup_hours', 24);
            
            $taskId = $this->createMaintenanceTask('cleanup_temp');
            
            $results = [
                'files_processed' => 0,
                'files_deleted' => 0,
                'files_archived' => 0,
                'space_freed' => 0
            ];
            
            $tempDirs = [
                ROOT_PATH . '/tmp',
                sys_get_temp_dir(),
                ROOT_PATH . '/uploads/tmp'
            ];
            
            $threshold = time() - ($cleanupHours * 60 * 60);
            
            foreach ($tempDirs as $tempDir) {
                if (is_dir($tempDir)) {
                    $this->cleanupDirectory($tempDir, $threshold, $results, ['n3xtweb_', 'upload_', 'backup_', 'update_']);
                }
            }
            
            // Nettoyer les sessions PHP expirées
            $this->cleanupPhpSessions($results);
            
            $this->updateMaintenanceTask($taskId, 'completed', $results);
            
            $this->logAction('Temp files cleanup completed', "Processed: {$results['files_processed']}, Deleted: {$results['files_deleted']}, Space freed: " . FileHelper::formatFileSize($results['space_freed']));
            
            return $results;
            
        } catch (Exception $e) {
            if (isset($taskId)) {
                $this->updateMaintenanceTask($taskId, 'failed', [], $e->getMessage());
            }
            
            $this->logAction('Temp files cleanup failed', $e->getMessage(), LOG_LEVEL_ERROR);
            throw $e;
        }
    }
    
    /**
     * Nettoie un répertoire selon les critères donnés
     */
    private function cleanupDirectory($dir, $threshold, &$results, $prefixes = []) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = $dir . '/' . $file;
            
            if (is_file($filePath)) {
                $results['files_processed']++;
                
                $fileTime = filemtime($filePath);
                $shouldDelete = false;
                
                // Vérifier l'âge du fichier
                if ($fileTime < $threshold) {
                    $shouldDelete = true;
                } 
                // Vérifier les préfixes spécifiques si fournis
                elseif (!empty($prefixes)) {
                    foreach ($prefixes as $prefix) {
                        if (strpos($file, $prefix) === 0) {
                            $shouldDelete = true;
                            break;
                        }
                    }
                }
                
                if ($shouldDelete) {
                    $fileSize = filesize($filePath);
                    
                    if (unlink($filePath)) {
                        $results['files_deleted']++;
                        $results['space_freed'] += $fileSize;
                    }
                }
            } elseif (is_dir($filePath)) {
                // Nettoyer récursivement les sous-répertoires
                $this->cleanupDirectory($filePath, $threshold, $results, $prefixes);
                
                // Supprimer le répertoire s'il est vide
                if ($this->isDirectoryEmpty($filePath)) {
                    rmdir($filePath);
                }
            }
        }
    }
    
    /**
     * Vérifie si un répertoire est vide
     */
    private function isDirectoryEmpty($dir) {
        $files = scandir($dir);
        return count($files) <= 2; // Seulement . et ..
    }
    
    /**
     * Nettoie les sessions PHP expirées
     */
    private function cleanupPhpSessions(&$results) {
        try {
            $sessionPath = session_save_path() ?: sys_get_temp_dir();
            $sessionLifetime = ini_get('session.gc_maxlifetime') ?: 1440; // 24 minutes par défaut
            $threshold = time() - $sessionLifetime;
            
            if (is_dir($sessionPath)) {
                $sessionFiles = glob($sessionPath . '/sess_*');
                
                foreach ($sessionFiles as $sessionFile) {
                    if (is_file($sessionFile) && filemtime($sessionFile) < $threshold) {
                        $fileSize = filesize($sessionFile);
                        
                        if (unlink($sessionFile)) {
                            $results['files_deleted']++;
                            $results['space_freed'] += $fileSize;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->logAction('PHP session cleanup failed', $e->getMessage(), LOG_LEVEL_WARNING);
        }
    }
    
    /**
     * Archive un fichier avant suppression
     */
    private function archiveFile($filePath, $category) {
        try {
            $archiveDir = ROOT_PATH . '/backups/archives/' . $category;
            
            if (!is_dir($archiveDir)) {
                mkdir($archiveDir, 0755, true);
            }
            
            $filename = basename($filePath);
            $timestamp = date('Y-m-d_H-i-s');
            $archiveName = "{$category}_archive_{$timestamp}.zip";
            $archivePath = $archiveDir . '/' . $archiveName;
            
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($archivePath, ZipArchive::CREATE) === TRUE) {
                    $zip->addFile($filePath, $filename);
                    $zip->close();
                    
                    $this->logAction('File archived', "File: {$filename}, Archive: {$archiveName}");
                    return true;
                }
            } else {
                // Fallback: copier le fichier avec un nouveau nom
                $archiveFilePath = $archiveDir . '/' . $timestamp . '_' . $filename;
                if (copy($filePath, $archiveFilePath)) {
                    $this->logAction('File archived (copy)', "File: {$filename}, Archive: " . basename($archiveFilePath));
                    return true;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logAction('File archiving failed', "File: {$filePath}, Error: " . $e->getMessage(), LOG_LEVEL_WARNING);
            return false;
        }
    }
    
    /**
     * Optimise la base de données
     */
    public function optimizeDatabase() {
        try {
            $this->checkPermissions();
            
            $taskId = $this->createMaintenanceTask('optimize_db');
            
            $results = [
                'tables_optimized' => 0,
                'space_freed' => 0
            ];
            
            // Utiliser la méthode existante de SystemManager si disponible
            if (class_exists('SystemManager') && method_exists('SystemManager', 'optimizeDatabase')) {
                $optimized = SystemManager::optimizeDatabase();
                $results['tables_optimized'] = count($optimized);
            } else {
                // Optimisation manuelle
                $db = Database::getInstance();
                $prefix = Logger::getTablePrefix();
                
                $tables = $db->fetchAll("SHOW TABLES LIKE '{$prefix}%'");
                
                foreach ($tables as $table) {
                    $tableName = array_values($table)[0];
                    
                    try {
                        $db->execute("OPTIMIZE TABLE `{$tableName}`");
                        $results['tables_optimized']++;
                        
                    } catch (Exception $e) {
                        $this->logAction('Table optimization failed', "Table: {$tableName}, Error: " . $e->getMessage(), LOG_LEVEL_WARNING);
                    }
                }
            }
            
            $this->updateMaintenanceTask($taskId, 'completed', $results);
            
            $this->logAction('Database optimization completed', "Tables optimized: {$results['tables_optimized']}");
            
            return $results;
            
        } catch (Exception $e) {
            if (isset($taskId)) {
                $this->updateMaintenanceTask($taskId, 'failed', [], $e->getMessage());
            }
            
            $this->logAction('Database optimization failed', $e->getMessage(), LOG_LEVEL_ERROR);
            throw $e;
        }
    }
    
    /**
     * Crée une notification de nettoyage
     */
    private function createCleanupNotification($results) {
        $totalDeleted = $results['logs']['files_deleted'] + $results['backups']['files_deleted'] + $results['temp_files']['files_deleted'];
        $totalSpaceFreed = $results['logs']['space_freed'] + $results['backups']['space_freed'] + $results['temp_files']['space_freed'];
        
        if ($totalDeleted > 0 || $results['database']['tables_optimized'] > 0) {
            $message = "Maintenance automatique terminée :\n";
            $message .= "• {$totalDeleted} fichiers supprimés\n";
            $message .= "• " . FileHelper::formatFileSize($totalSpaceFreed) . " d'espace libéré\n";
            $message .= "• {$results['database']['tables_optimized']} tables optimisées";
            
            $this->notificationManager->createNotification(
                'maintenance',
                'Maintenance automatique terminée',
                $message,
                'medium',
                $results
            );
        }
    }
    
    /**
     * Crée une tâche de maintenance
     */
    private function createMaintenanceTask($taskType) {
        $sql = "INSERT INTO " . Logger::getTablePrefix() . "maintenance_logs 
                (task_type, status, started_at, created_by) 
                VALUES (?, 'running', NOW(), ?)";
        
        $this->db->execute($sql, [$taskType, $_SESSION['admin_username'] ?? 'system']);
        return $this->db->getLastInsertId();
    }
    
    /**
     * Met à jour une tâche de maintenance
     */
    private function updateMaintenanceTask($taskId, $status, $results = [], $errorMessage = null) {
        $duration = 0; // Calculer la durée si nécessaire
        
        $data = [
            'status' => $status,
            'completed_at' => date('Y-m-d H:i:s'),
            'duration_seconds' => $duration
        ];
        
        if (!empty($results)) {
            $data['files_processed'] = $results['files_processed'] ?? 0;
            $data['files_deleted'] = $results['files_deleted'] ?? 0;
            $data['files_archived'] = $results['files_archived'] ?? 0;
            $data['space_freed'] = $results['space_freed'] ?? 0;
        }
        
        if ($errorMessage) {
            $data['details'] = $errorMessage;
        }
        
        $setParts = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $setParts[] = "{$key} = ?";
            $values[] = $value;
        }
        
        $values[] = $taskId;
        
        $sql = "UPDATE " . Logger::getTablePrefix() . "maintenance_logs SET " . implode(', ', $setParts) . " WHERE id = ?";
        return $this->db->execute($sql, $values);
    }
    
    /**
     * Retourne l'historique des tâches de maintenance
     */
    public function getMaintenanceHistory($limit = 50) {
        $sql = "SELECT * FROM " . Logger::getTablePrefix() . "maintenance_logs ORDER BY started_at DESC LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Retourne les statistiques de maintenance
     */
    public function getStatistics() {
        try {
            $stats = [];
            
            // Dernière maintenance
            $lastMaintenance = $this->db->fetchOne(
                "SELECT * FROM " . Logger::getTablePrefix() . "maintenance_logs ORDER BY started_at DESC LIMIT 1"
            );
            $stats['last_maintenance'] = $lastMaintenance;
            
            // Statistiques par type de tâche
            $taskStats = $this->db->fetchAll(
                "SELECT task_type, COUNT(*) as count, SUM(space_freed) as total_space_freed 
                 FROM " . Logger::getTablePrefix() . "maintenance_logs 
                 WHERE status = 'completed' 
                 GROUP BY task_type"
            );
            
            foreach ($taskStats as $stat) {
                $stats['by_task'][$stat['task_type']] = [
                    'count' => (int) $stat['count'],
                    'space_freed' => (int) $stat['total_space_freed']
                ];
            }
            
            // Espace total libéré
            $totalSpaceFreed = $this->db->fetchOne(
                "SELECT SUM(space_freed) as total FROM " . Logger::getTablePrefix() . "maintenance_logs WHERE status = 'completed'"
            );
            $stats['total_space_freed'] = (int) $totalSpaceFreed['total'];
            
            // Prochaine maintenance prévue
            $lastCleanup = (int) $this->getConfig('last_cleanup', 0);
            $schedule = $this->getConfig('cleanup_schedule', 'daily');
            $interval = $schedule === 'weekly' ? 7 * 24 * 3600 : 24 * 3600;
            $stats['next_cleanup'] = $lastCleanup + $interval;
            
            return $stats;
            
        } catch (Exception $e) {
            $this->logAction('Failed to get statistics', $e->getMessage(), LOG_LEVEL_ERROR);
            return [];
        }
    }
    
    /**
     * Force le nettoyage manuel
     */
    public function forceCleanup($tasks = ['logs', 'backups', 'temp_files', 'database']) {
        try {
            $this->checkPermissions();
            
            $this->logAction('Starting manual cleanup', 'Tasks: ' . implode(', ', $tasks));
            
            $results = [];
            
            if (in_array('logs', $tasks)) {
                $results['logs'] = $this->cleanupLogs();
            }
            
            if (in_array('backups', $tasks)) {
                $results['backups'] = $this->cleanupBackups();
            }
            
            if (in_array('temp_files', $tasks)) {
                $results['temp_files'] = $this->cleanupTempFiles();
            }
            
            if (in_array('database', $tasks)) {
                $results['database'] = $this->optimizeDatabase();
            }
            
            // Mettre à jour le timestamp
            $this->setConfig('last_cleanup', time());
            
            $this->logAction('Manual cleanup completed', json_encode($results));
            
            return $results;
            
        } catch (Exception $e) {
            $this->logAction('Manual cleanup failed', $e->getMessage(), LOG_LEVEL_ERROR);
            throw $e;
        }
    }
}