<?php
/**
 * N3XT WEB - Install Status Widget
 * 
 * Widget pour afficher le statut de l'installation et les informations système.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

// Charger la classe de base
require_once __DIR__ . '/../../BaseWidget.php';

class InstallStatusWidget extends BaseWidget {
    
    public function __construct() {
        parent::__construct('InstallStatusWidget', 'Install');
    }
    
    /**
     * Configuration par défaut du widget
     */
    protected function getDefaultConfiguration() {
        return [
            'enabled' => true,
            'title' => 'Statut d\'installation',
            'description' => 'Affiche le statut de l\'installation et les informations système',
            'refresh_interval' => 300,
            'show_php_info' => true,
            'show_db_info' => true,
            'show_file_permissions' => true
        ];
    }
    
    /**
     * Génère les données du widget
     */
    public function getData() {
        $data = [
            'installation_status' => $this->getInstallationStatus(),
            'system_info' => $this->getSystemInfo(),
            'requirements_check' => $this->checkRequirements(),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        return $data;
    }
    
    /**
     * Vérifie le statut de l'installation
     */
    private function getInstallationStatus() {
        $status = [
            'completed' => false,
            'version' => '1.0.0',
            'install_date' => null,
            'last_update' => null
        ];
        
        try {
            // Vérifier si l'installation est complète
            $configFile = __DIR__ . '/../../config/config.php';
            if (file_exists($configFile)) {
                $status['completed'] = true;
                $status['install_date'] = date('Y-m-d H:i:s', filemtime($configFile));
            }
            
            // Obtenir la version depuis la base de données si disponible
            if ($this->db && $status['completed']) {
                $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
                $result = $this->db->fetchOne("SELECT config_value FROM {$prefix}configuration WHERE config_key = 'version'");
                if ($result) {
                    $status['version'] = $result['config_value'];
                }
                
                // Date de dernière mise à jour
                $result = $this->db->fetchOne("SELECT config_value FROM {$prefix}configuration WHERE config_key = 'last_update'");
                if ($result) {
                    $status['last_update'] = $result['config_value'];
                }
            }
        } catch (Exception $e) {
            // En cas d'erreur, considérer l'installation comme incomplète
            $status['completed'] = false;
        }
        
        return $status;
    }
    
    /**
     * Obtient les informations système
     */
    private function getSystemInfo() {
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'disk_free_space' => $this->getFormattedDiskSpace(),
            'extensions' => $this->getRequiredExtensions()
        ];
    }
    
    /**
     * Vérifie les prérequis système
     */
    private function checkRequirements() {
        $requirements = [
            'php_version' => [
                'name' => 'Version PHP',
                'required' => '7.4.0',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '7.4.0', '>=')
            ],
            'extensions' => []
        ];
        
        // Vérifier les extensions requises
        $requiredExtensions = ['mysqli', 'json', 'mbstring', 'curl', 'openssl'];
        foreach ($requiredExtensions as $ext) {
            $requirements['extensions'][$ext] = [
                'name' => $ext,
                'required' => true,
                'loaded' => extension_loaded($ext),
                'status' => extension_loaded($ext)
            ];
        }
        
        // Vérifier les permissions de fichiers
        $requirements['file_permissions'] = $this->checkFilePermissions();
        
        return $requirements;
    }
    
    /**
     * Vérifie les permissions de fichiers
     */
    private function checkFilePermissions() {
        $paths = [
            __DIR__ . '/../../config/',
            __DIR__ . '/../../uploads/',
            __DIR__ . '/../../logs/',
            __DIR__ . '/../../backups/'
        ];
        
        $permissions = [];
        foreach ($paths as $path) {
            $relativePath = str_replace(__DIR__ . '/../../', '', $path);
            $permissions[$relativePath] = [
                'path' => $relativePath,
                'exists' => file_exists($path),
                'readable' => is_readable($path),
                'writable' => is_writable($path),
                'status' => file_exists($path) && is_readable($path) && is_writable($path)
            ];
        }
        
        return $permissions;
    }
    
    /**
     * Obtient l'espace disque formaté
     */
    private function getFormattedDiskSpace() {
        $bytes = disk_free_space(__DIR__);
        if ($bytes === false) {
            return 'Unknown';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Obtient les extensions requises
     */
    private function getRequiredExtensions() {
        $extensions = get_loaded_extensions();
        $required = ['mysqli', 'json', 'mbstring', 'curl', 'openssl', 'gd'];
        
        $result = [];
        foreach ($required as $ext) {
            $result[$ext] = in_array($ext, $extensions);
        }
        
        return $result;
    }
    
    /**
     * Rendu HTML du widget
     */
    public function render() {
        $data = $this->getData();
        return $this->loadView($data);
    }
}