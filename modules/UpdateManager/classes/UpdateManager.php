<?php
/**
 * N3XT WEB - UpdateManager Module
 * 
 * Gestionnaire de mises à jour automatiques depuis GitHub.
 * Vérifie, télécharge et applique les mises à jour système.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

class UpdateManager extends BaseModule {
    
    private $githubApiUrl = 'https://api.github.com/repos';
    private $backupManager;
    private $notificationManager;
    
    public function __construct() {
        parent::__construct('updatemanager');
        $this->initialize();
    }
    
    /**
     * Configuration par défaut du module
     */
    protected function getDefaultConfiguration() {
        return [
            'enabled' => true,
            'auto_check' => true,
            'github_repo' => 'gjai/n3xtweb',
            'check_frequency' => 86400, // 24 hours
            'auto_backup' => true,
            'last_check' => 0,
            'current_version' => Configuration::get('system_version', '2.0.0'),
            'version' => '1.0.0',
            'description' => 'Gestionnaire de mises à jour automatiques depuis GitHub'
        ];
    }
    
    /**
     * Initialise le module
     */
    public function initialize() {
        // Vérifier la disponibilité des modules dépendants
        if (class_exists('BackupManager')) {
            $this->backupManager = new BackupManager();
        }
        if (class_exists('NotificationManager')) {
            $this->notificationManager = new NotificationManager();
        }
        
        // Vérifier automatiquement les mises à jour si activé
        if ($this->getConfig('auto_check', true) && $this->shouldCheckForUpdates()) {
            $this->checkForUpdates();
        }
    }
    
    /**
     * Vérifie si une vérification de mise à jour est nécessaire
     */
    private function shouldCheckForUpdates() {
        $lastCheck = (int) $this->getConfig('last_check', 0);
        $frequency = (int) $this->getConfig('check_frequency', 86400);
        
        return (time() - $lastCheck) >= $frequency;
    }
    
    /**
     * Vérifie la disponibilité d'une mise à jour
     */
    public function checkForUpdates() {
        try {
            $this->checkPermissions();
            
            $repo = $this->getConfig('github_repo', 'gjai/n3xtweb');
            $currentVersion = $this->getConfig('current_version', '2.0.0');
            
            $this->logAction('Checking for updates', "Repo: {$repo}, Current: {$currentVersion}");
            
            // Obtenir la dernière release depuis GitHub
            $latestRelease = $this->getLatestRelease($repo);
            
            if (!$latestRelease) {
                throw new Exception('Unable to fetch latest release information');
            }
            
            // Mettre à jour le timestamp de dernière vérification
            $this->setConfig('last_check', time());
            
            // Comparer les versions
            $latestVersion = ltrim($latestRelease['tag_name'], 'v');
            $updateAvailable = version_compare($latestVersion, $currentVersion, '>');
            
            if ($updateAvailable) {
                $this->logAction('Update available', "Latest: {$latestVersion}");
                
                // Créer une notification
                if ($this->notificationManager) {
                    $this->notificationManager->createNotification(
                        'update',
                        'Mise à jour disponible',
                        "Une nouvelle version ({$latestVersion}) est disponible. Version actuelle: {$currentVersion}",
                        'high',
                        [
                            'current_version' => $currentVersion,
                            'latest_version' => $latestVersion,
                            'download_url' => $latestRelease['zipball_url'],
                            'release_notes' => $latestRelease['body'] ?? ''
                        ]
                    );
                }
                
                return [
                    'update_available' => true,
                    'current_version' => $currentVersion,
                    'latest_version' => $latestVersion,
                    'download_url' => $latestRelease['zipball_url'],
                    'release_info' => $latestRelease
                ];
            } else {
                $this->logAction('No update available', "Latest: {$latestVersion}");
                return [
                    'update_available' => false,
                    'current_version' => $currentVersion,
                    'latest_version' => $latestVersion
                ];
            }
            
        } catch (Exception $e) {
            $this->logAction('Check for updates failed', $e->getMessage(), LOG_LEVEL_ERROR);
            throw $e;
        }
    }
    
    /**
     * Obtient les informations de la dernière release depuis GitHub
     */
    private function getLatestRelease($repo) {
        $url = "{$this->githubApiUrl}/{$repo}/releases/latest";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'N3XT-WEB-UpdateManager/1.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            // Fallback: essayer d'obtenir toutes les releases
            $url = "{$this->githubApiUrl}/{$repo}/releases";
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return null;
            }
            
            $releases = json_decode($response, true);
            return !empty($releases) ? $releases[0] : null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Télécharge une mise à jour
     */
    public function downloadUpdate($downloadUrl, $version) {
        try {
            $this->checkPermissions();
            
            $this->logAction('Starting update download', "Version: {$version}");
            
            // Créer le répertoire de téléchargement
            $downloadDir = ROOT_PATH . '/tmp/updates';
            if (!is_dir($downloadDir)) {
                mkdir($downloadDir, 0755, true);
            }
            
            // Nom du fichier de téléchargement
            $filename = "n3xtweb-{$version}-" . date('Y-m-d-H-i-s') . '.zip';
            $filepath = $downloadDir . '/' . $filename;
            
            // Enregistrer le téléchargement dans l'historique
            $updateId = $this->createUpdateRecord($version, 'downloading', $downloadUrl, $filepath);
            
            // Télécharger le fichier
            $context = stream_context_create([
                'http' => [
                    'timeout' => 300, // 5 minutes
                    'user_agent' => 'N3XT-WEB-UpdateManager/1.0'
                ]
            ]);
            
            $this->updateProgress($updateId, 'downloading', 10);
            
            $data = file_get_contents($downloadUrl, false, $context);
            
            if ($data === false) {
                throw new Exception('Failed to download update file');
            }
            
            $this->updateProgress($updateId, 'downloading', 50);
            
            // Sauvegarder le fichier
            if (file_put_contents($filepath, $data) === false) {
                throw new Exception('Failed to save update file');
            }
            
            $this->updateProgress($updateId, 'downloading', 100);
            
            $fileSize = filesize($filepath);
            $this->logAction('Update downloaded', "File: {$filename}, Size: " . FileHelper::formatFileSize($fileSize));
            
            // Mettre à jour l'enregistrement
            $this->updateUpdateRecord($updateId, [
                'status' => 'downloaded',
                'progress_percent' => 100
            ]);
            
            return [
                'success' => true,
                'update_id' => $updateId,
                'filepath' => $filepath,
                'filename' => $filename,
                'size' => $fileSize
            ];
            
        } catch (Exception $e) {
            $this->logAction('Download failed', $e->getMessage(), LOG_LEVEL_ERROR);
            
            if (isset($updateId)) {
                $this->updateUpdateRecord($updateId, [
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
            }
            
            throw $e;
        }
    }
    
    /**
     * Applique une mise à jour
     */
    public function applyUpdate($updateId) {
        try {
            $this->checkPermissions();
            
            // Récupérer les informations de la mise à jour
            $updateInfo = $this->getUpdateRecord($updateId);
            if (!$updateInfo) {
                throw new Exception('Update record not found');
            }
            
            $this->logAction('Starting update application', "Update ID: {$updateId}");
            $this->updateProgress($updateId, 'applying', 0);
            
            // Créer une sauvegarde si activé
            if ($this->getConfig('auto_backup', true) && $this->backupManager) {
                $this->logAction('Creating pre-update backup');
                $this->updateProgress($updateId, 'backing_up', 10);
                
                $backup = $this->backupManager->createBackup('pre_update', 'Sauvegarde automatique avant mise à jour ' . $updateInfo['version_to']);
                
                if ($backup) {
                    $this->updateUpdateRecord($updateId, ['backup_id' => $backup['id']]);
                    $this->logAction('Pre-update backup created', "Backup ID: {$backup['id']}");
                }
            }
            
            $this->updateProgress($updateId, 'applying', 30);
            
            // Extraire l'archive
            $extractPath = ROOT_PATH . '/tmp/extract/' . basename($updateInfo['file_path'], '.zip');
            $this->extractUpdate($updateInfo['file_path'], $extractPath);
            
            $this->updateProgress($updateId, 'applying', 50);
            
            // Appliquer les fichiers
            $filesUpdated = $this->applyUpdateFiles($extractPath);
            
            $this->updateProgress($updateId, 'applying', 80);
            
            // Mettre à jour la version système
            Configuration::set('system_version', $updateInfo['version_to']);
            $this->setConfig('current_version', $updateInfo['version_to']);
            
            $this->updateProgress($updateId, 'applying', 100);
            
            // Finaliser la mise à jour
            $this->updateUpdateRecord($updateId, [
                'status' => 'completed',
                'progress_percent' => 100,
                'files_updated' => $filesUpdated,
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            // Nettoyer les fichiers temporaires
            $this->cleanupUpdateFiles($updateInfo['file_path'], $extractPath);
            
            $this->logAction('Update applied successfully', "Version: {$updateInfo['version_to']}, Files updated: {$filesUpdated}");
            
            // Créer une notification de succès
            if ($this->notificationManager) {
                $this->notificationManager->createNotification(
                    'update',
                    'Mise à jour appliquée',
                    "La mise à jour vers la version {$updateInfo['version_to']} a été appliquée avec succès.",
                    'medium'
                );
            }
            
            return [
                'success' => true,
                'version' => $updateInfo['version_to'],
                'files_updated' => $filesUpdated
            ];
            
        } catch (Exception $e) {
            $this->logAction('Update application failed', $e->getMessage(), LOG_LEVEL_ERROR);
            
            if (isset($updateId)) {
                $this->updateUpdateRecord($updateId, [
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
            }
            
            throw $e;
        }
    }
    
    /**
     * Extrait l'archive de mise à jour
     */
    private function extractUpdate($zipPath, $extractPath) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive class not available');
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($zipPath);
        
        if ($result !== TRUE) {
            throw new Exception("Failed to open update archive: {$result}");
        }
        
        // Créer le répertoire d'extraction
        if (!is_dir($extractPath)) {
            mkdir($extractPath, 0755, true);
        }
        
        // Extraire l'archive
        $zip->extractTo($extractPath);
        $zip->close();
        
        return true;
    }
    
    /**
     * Applique les fichiers de mise à jour
     */
    private function applyUpdateFiles($extractPath) {
        $filesUpdated = 0;
        $excludeDirs = ['config', 'uploads', 'logs', 'backups', 'tmp'];
        $excludeFiles = ['.htaccess', 'robots.txt', 'favicon.ico'];
        
        // Trouver le répertoire source (premier sous-répertoire dans l'extraction GitHub)
        $items = scandir($extractPath);
        $sourceDir = null;
        
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($extractPath . '/' . $item)) {
                $sourceDir = $extractPath . '/' . $item;
                break;
            }
        }
        
        if (!$sourceDir) {
            throw new Exception('Could not find source directory in extracted archive');
        }
        
        // Copier les fichiers récursivement
        $filesUpdated = $this->copyUpdateFiles($sourceDir, ROOT_PATH, $excludeDirs, $excludeFiles);
        
        return $filesUpdated;
    }
    
    /**
     * Copie les fichiers de mise à jour récursivement
     */
    private function copyUpdateFiles($source, $destination, $excludeDirs, $excludeFiles) {
        $filesUpdated = 0;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $relativePath = str_replace($source . '/', '', $item->getPathname());
            $destPath = $destination . '/' . $relativePath;
            
            // Vérifier les exclusions
            $pathParts = explode('/', $relativePath);
            if (in_array($pathParts[0], $excludeDirs)) {
                continue;
            }
            
            if ($item->isFile()) {
                if (in_array($item->getFilename(), $excludeFiles)) {
                    continue;
                }
                
                // Créer le répertoire de destination si nécessaire
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                
                // Copier le fichier
                if (copy($item->getPathname(), $destPath)) {
                    $filesUpdated++;
                }
            }
        }
        
        return $filesUpdated;
    }
    
    /**
     * Nettoie les fichiers temporaires de mise à jour
     */
    private function cleanupUpdateFiles($zipPath, $extractPath) {
        // Supprimer le fichier ZIP
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
        
        // Supprimer le répertoire d'extraction
        if (is_dir($extractPath)) {
            $this->removeDirectory($extractPath);
        }
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
     * Crée un enregistrement de mise à jour
     */
    private function createUpdateRecord($versionTo, $status, $downloadUrl = null, $filePath = null) {
        $currentVersion = $this->getConfig('current_version', '2.0.0');
        
        $data = [
            'version_from' => $currentVersion,
            'version_to' => $versionTo,
            'update_type' => 'automatic',
            'status' => $status,
            'download_url' => $downloadUrl,
            'file_path' => $filePath,
            'started_by' => $_SESSION['admin_username'] ?? 'system'
        ];
        
        $sql = "INSERT INTO " . Logger::getTablePrefix() . "update_history 
                (version_from, version_to, update_type, status, download_url, file_path, started_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->execute($sql, array_values($data));
        return $this->db->getLastInsertId();
    }
    
    /**
     * Met à jour un enregistrement de mise à jour
     */
    private function updateUpdateRecord($updateId, $data) {
        $setParts = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $setParts[] = "{$key} = ?";
            $values[] = $value;
        }
        
        $values[] = $updateId;
        
        $sql = "UPDATE " . Logger::getTablePrefix() . "update_history SET " . implode(', ', $setParts) . " WHERE id = ?";
        return $this->db->execute($sql, $values);
    }
    
    /**
     * Récupère un enregistrement de mise à jour
     */
    private function getUpdateRecord($updateId) {
        $sql = "SELECT * FROM " . Logger::getTablePrefix() . "update_history WHERE id = ?";
        return $this->db->fetchOne($sql, [$updateId]);
    }
    
    /**
     * Met à jour le progrès d'une mise à jour
     */
    private function updateProgress($updateId, $status, $progress) {
        $this->updateUpdateRecord($updateId, [
            'status' => $status,
            'progress_percent' => $progress
        ]);
    }
    
    /**
     * Retourne l'historique des mises à jour
     */
    public function getUpdateHistory($limit = 50) {
        $sql = "SELECT * FROM " . Logger::getTablePrefix() . "update_history ORDER BY started_at DESC LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Retourne l'état actuel du système de mise à jour
     */
    public function getStatus() {
        $currentVersion = $this->getConfig('current_version', '2.0.0');
        $lastCheck = (int) $this->getConfig('last_check', 0);
        
        // Vérifier s'il y a une mise à jour en cours
        $ongoingUpdate = $this->db->fetchOne(
            "SELECT * FROM " . Logger::getTablePrefix() . "update_history WHERE status IN ('checking', 'downloading', 'backing_up', 'applying') ORDER BY started_at DESC LIMIT 1"
        );
        
        return [
            'enabled' => $this->getConfig('enabled', true),
            'auto_check' => $this->getConfig('auto_check', true),
            'current_version' => $currentVersion,
            'last_check' => $lastCheck,
            'last_check_formatted' => $lastCheck ? date('d/m/Y H:i:s', $lastCheck) : 'Jamais',
            'ongoing_update' => $ongoingUpdate,
            'github_repo' => $this->getConfig('github_repo', 'gjai/n3xtweb')
        ];
    }
}