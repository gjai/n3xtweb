<?php
/**
 * N3XT WEB - Database Migration for BackOffice Modules
 * 
 * Migration pour ajouter les tables nécessaires aux modules de back office.
 * Doit être exécuté une seule fois lors de l'installation des modules.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

class ModulesMigration {
    
    private $db;
    private $prefix;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->prefix = Logger::getTablePrefix();
    }
    
    /**
     * Applique la migration
     */
    public function migrate() {
        try {
            $this->createNotificationsTable();
            $this->createBackupsTable();
            $this->createMaintenanceLogsTable();
            $this->createUpdateHistoryTable();
            $this->insertDefaultModuleSettings();
            
            Logger::log('Modules migration completed successfully', LOG_LEVEL_INFO, 'system');
            return true;
            
        } catch (Exception $e) {
            Logger::log('Modules migration failed: ' . $e->getMessage(), LOG_LEVEL_ERROR, 'system');
            throw $e;
        }
    }
    
    /**
     * Créer la table des notifications
     */
    private function createNotificationsTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->prefix}notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type ENUM('update', 'backup', 'maintenance', 'system', 'warning', 'error') NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                status ENUM('unread', 'read', 'dismissed') DEFAULT 'unread',
                target_user VARCHAR(50) NULL COMMENT 'NULL = all admins',
                data JSON NULL COMMENT 'Additional notification data',
                email_sent BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                INDEX idx_type (type),
                INDEX idx_status (status),
                INDEX idx_priority (priority),
                INDEX idx_target_user (target_user),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $this->db->execute($sql);
    }
    
    /**
     * Créer la table des sauvegardes
     */
    private function createBackupsTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->prefix}backups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                filepath VARCHAR(500) NOT NULL,
                type ENUM('manual', 'automatic', 'pre_update') NOT NULL,
                backup_type ENUM('full', 'database', 'files') DEFAULT 'full',
                size_bytes BIGINT NOT NULL,
                compressed BOOLEAN DEFAULT TRUE,
                status ENUM('creating', 'completed', 'failed', 'deleted') DEFAULT 'creating',
                created_by VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                notes TEXT NULL,
                metadata JSON NULL COMMENT 'Additional backup metadata',
                INDEX idx_type (type),
                INDEX idx_status (status),
                INDEX idx_created_by (created_by),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $this->db->execute($sql);
    }
    
    /**
     * Créer la table des logs de maintenance
     */
    private function createMaintenanceLogsTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->prefix}maintenance_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                task_type ENUM('cleanup_logs', 'cleanup_backups', 'cleanup_temp', 'archive', 'optimize_db') NOT NULL,
                status ENUM('running', 'completed', 'failed') NOT NULL,
                files_processed INT DEFAULT 0,
                files_deleted INT DEFAULT 0,
                files_archived INT DEFAULT 0,
                space_freed BIGINT DEFAULT 0 COMMENT 'Bytes freed',
                duration_seconds INT DEFAULT 0,
                details TEXT NULL,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                created_by VARCHAR(50) NOT NULL DEFAULT 'system',
                INDEX idx_task_type (task_type),
                INDEX idx_status (status),
                INDEX idx_started_at (started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $this->db->execute($sql);
    }
    
    /**
     * Créer la table de l'historique des mises à jour
     */
    private function createUpdateHistoryTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->prefix}update_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                version_from VARCHAR(20) NOT NULL,
                version_to VARCHAR(20) NOT NULL,
                update_type ENUM('automatic', 'manual', 'zip_upload') NOT NULL,
                status ENUM('checking', 'downloading', 'backing_up', 'applying', 'completed', 'failed', 'rolled_back') NOT NULL,
                backup_id INT NULL,
                download_url VARCHAR(500) NULL,
                file_path VARCHAR(500) NULL,
                progress_percent TINYINT DEFAULT 0,
                error_message TEXT NULL,
                files_updated INT DEFAULT 0,
                started_by VARCHAR(50) NOT NULL,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                notes TEXT NULL,
                FOREIGN KEY (backup_id) REFERENCES {$this->prefix}backups(id) ON DELETE SET NULL,
                INDEX idx_status (status),
                INDEX idx_started_by (started_by),
                INDEX idx_started_at (started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $this->db->execute($sql);
    }
    
    /**
     * Insérer les paramètres par défaut des modules
     */
    private function insertDefaultModuleSettings() {
        $defaultSettings = [
            // UpdateManager settings
            'updatemanager_enabled' => 'true',
            'updatemanager_auto_check' => 'true',
            'updatemanager_github_repo' => 'gjai/n3xtweb',
            'updatemanager_check_frequency' => '86400', // 24 hours
            'updatemanager_auto_backup' => 'true',
            'updatemanager_last_check' => '0',
            'updatemanager_version' => '1.0.0',
            'updatemanager_description' => 'Gestionnaire de mises à jour automatiques depuis GitHub',
            
            // NotificationManager settings
            'notificationmanager_enabled' => 'true',
            'notificationmanager_email_enabled' => 'true',
            'notificationmanager_visual_enabled' => 'true',
            'notificationmanager_retention_days' => '30',
            'notificationmanager_email_template' => 'default',
            'notificationmanager_version' => '1.0.0',
            'notificationmanager_description' => 'Système de notifications pour le back office',
            
            // BackupManager settings
            'backupmanager_enabled' => 'true',
            'backupmanager_auto_backup' => 'true',
            'backupmanager_retention_days' => '30',
            'backupmanager_compression' => 'true',
            'backupmanager_include_files' => 'true',
            'backupmanager_include_uploads' => 'false',
            'backupmanager_max_backup_size' => '1073741824', // 1GB
            'backupmanager_version' => '1.0.0',
            'backupmanager_description' => 'Gestionnaire de sauvegardes automatiques',
            
            // MaintenanceManager settings
            'maintenancemanager_enabled' => 'true',
            'maintenancemanager_auto_cleanup' => 'true',
            'maintenancemanager_log_retention_days' => '7',
            'maintenancemanager_backup_retention_days' => '30',
            'maintenancemanager_temp_cleanup_hours' => '24',
            'maintenancemanager_archive_before_delete' => 'true',
            'maintenancemanager_version' => '1.0.0',
            'maintenancemanager_description' => 'Gestionnaire de maintenance et nettoyage automatique'
        ];
        
        foreach ($defaultSettings as $key => $value) {
            $this->db->execute(
                "INSERT IGNORE INTO {$this->prefix}system_settings (setting_key, setting_value) VALUES (?, ?)",
                [$key, $value]
            );
        }
    }
    
    /**
     * Vérifie si la migration a déjà été appliquée
     */
    public function isMigrated() {
        try {
            $tables = [
                "{$this->prefix}notifications",
                "{$this->prefix}backups", 
                "{$this->prefix}maintenance_logs",
                "{$this->prefix}update_history"
            ];
            
            foreach ($tables as $table) {
                $result = $this->db->fetchOne("SHOW TABLES LIKE '{$table}'");
                if (!$result) {
                    return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Rollback de la migration (pour les tests)
     */
    public function rollback() {
        try {
            $tables = [
                "{$this->prefix}update_history",
                "{$this->prefix}maintenance_logs",
                "{$this->prefix}backups",
                "{$this->prefix}notifications"
            ];
            
            foreach ($tables as $table) {
                $this->db->execute("DROP TABLE IF EXISTS {$table}");
            }
            
            // Supprimer les paramètres des modules
            $this->db->execute(
                "DELETE FROM {$this->prefix}system_settings WHERE setting_key LIKE 'updatemanager_%' OR setting_key LIKE 'notificationmanager_%' OR setting_key LIKE 'backupmanager_%' OR setting_key LIKE 'maintenancemanager_%'"
            );
            
            Logger::log('Modules migration rolled back', LOG_LEVEL_INFO, 'system');
            return true;
            
        } catch (Exception $e) {
            Logger::log('Modules migration rollback failed: ' . $e->getMessage(), LOG_LEVEL_ERROR, 'system');
            throw $e;
        }
    }
}