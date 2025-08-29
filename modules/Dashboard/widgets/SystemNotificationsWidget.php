<?php
/**
 * N3XT WEB - System Notifications Widget
 * 
 * Widget pour afficher les notifications système et les alertes importantes.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

// Charger la classe de base
require_once __DIR__ . '/../BaseWidget.php';

class SystemNotificationsWidget extends BaseWidget {
    
    public function __construct() {
        parent::__construct('SystemNotificationsWidget', 'Dashboard');
    }
    
    /**
     * Configuration par défaut du widget
     */
    protected function getDefaultConfiguration() {
        return [
            'enabled' => true,
            'title' => 'Notifications système',
            'description' => 'Affiche les notifications système et les alertes importantes',
            'refresh_interval' => 60,
            'max_notifications' => 10,
            'show_priorities' => ['high', 'medium', 'low'],
            'auto_refresh' => true,
            'sound_alerts' => false
        ];
    }
    
    /**
     * Génère les données du widget
     */
    public function getData() {
        $data = [
            'notifications' => $this->getSystemNotifications(),
            'summary' => $this->getNotificationsSummary(),
            'system_status' => $this->getSystemStatus(),
            'recent_activities' => $this->getRecentActivities(),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        return $data;
    }
    
    /**
     * Obtient les notifications système
     */
    private function getSystemNotifications() {
        $notifications = [];
        
        try {
            if ($this->db) {
                $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
                $priorities = implode("','", $this->getConfig('show_priorities', ['high', 'medium', 'low']));
                $limit = $this->getConfig('max_notifications', 10);
                
                $sql = "SELECT * FROM {$prefix}notifications 
                       WHERE priority IN ('{$priorities}') 
                       AND status = 'active'
                       ORDER BY 
                           CASE priority 
                               WHEN 'critical' THEN 1
                               WHEN 'high' THEN 2  
                               WHEN 'medium' THEN 3
                               WHEN 'low' THEN 4
                           END,
                           created_at DESC 
                       LIMIT {$limit}";
                       
                $notifications = $this->db->fetchAll($sql);
            }
        } catch (Exception $e) {
            // Ajouter des notifications d'exemple en cas d'erreur
            $notifications = $this->getSampleNotifications();
        }
        
        return $notifications;
    }
    
    /**
     * Obtient des notifications d'exemple
     */
    private function getSampleNotifications() {
        return [
            [
                'id' => 1,
                'type' => 'system',
                'priority' => 'high',
                'title' => 'Mise à jour de sécurité disponible',
                'message' => 'Une mise à jour de sécurité critique est disponible pour votre installation.',
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
                'status' => 'active',
                'icon' => 'fas fa-shield-alt',
                'action_url' => '/admin/updates'
            ],
            [
                'id' => 2,
                'type' => 'maintenance',
                'priority' => 'medium',
                'title' => 'Sauvegarde automatique terminée',
                'message' => 'La sauvegarde automatique quotidienne s\'est terminée avec succès.',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'status' => 'active',
                'icon' => 'fas fa-save',
                'action_url' => '/admin/backups'
            ],
            [
                'id' => 3,
                'type' => 'warning',
                'priority' => 'medium',
                'title' => 'Espace disque faible',
                'message' => 'L\'espace disque disponible est inférieur à 1 GB. Considérez nettoyer les anciens fichiers.',
                'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours')),
                'status' => 'active',
                'icon' => 'fas fa-exclamation-triangle',
                'action_url' => '/admin/maintenance'
            ],
            [
                'id' => 4,
                'type' => 'info',
                'priority' => 'low',
                'title' => 'Nouvelle fonctionnalité disponible',
                'message' => 'Découvrez les nouvelles fonctionnalités ajoutées dans cette version.',
                'created_at' => date('Y-m-d H:i:s', strtotime('-6 hours')),
                'status' => 'active',
                'icon' => 'fas fa-star',
                'action_url' => '/admin/changelog'
            ]
        ];
    }
    
    /**
     * Obtient le résumé des notifications
     */
    private function getNotificationsSummary() {
        $notifications = $this->getSystemNotifications();
        
        $summary = [
            'total' => count($notifications),
            'by_priority' => [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0
            ],
            'by_type' => [
                'system' => 0,
                'security' => 0,
                'maintenance' => 0,
                'warning' => 0,
                'info' => 0
            ],
            'unread' => 0
        ];
        
        foreach ($notifications as $notification) {
            $priority = $notification['priority'] ?? 'low';
            $type = $notification['type'] ?? 'info';
            
            if (isset($summary['by_priority'][$priority])) {
                $summary['by_priority'][$priority]++;
            }
            
            if (isset($summary['by_type'][$type])) {
                $summary['by_type'][$type]++;
            }
            
            if (($notification['read_at'] ?? null) === null) {
                $summary['unread']++;
            }
        }
        
        return $summary;
    }
    
    /**
     * Obtient le statut général du système
     */
    private function getSystemStatus() {
        $status = [
            'overall' => 'good', // good, warning, critical
            'services' => [
                'database' => $this->checkDatabaseStatus(),
                'file_system' => $this->checkFileSystemStatus(),
                'security' => $this->checkSecurityStatus(),
                'performance' => $this->checkPerformanceStatus()
            ],
            'uptime' => $this->getSystemUptime(),
            'last_check' => date('Y-m-d H:i:s')
        ];
        
        // Déterminer le statut global
        $criticalIssues = 0;
        $warningIssues = 0;
        
        foreach ($status['services'] as $service) {
            if ($service['status'] === 'critical') {
                $criticalIssues++;
            } elseif ($service['status'] === 'warning') {
                $warningIssues++;
            }
        }
        
        if ($criticalIssues > 0) {
            $status['overall'] = 'critical';
        } elseif ($warningIssues > 0) {
            $status['overall'] = 'warning';
        }
        
        return $status;
    }
    
    /**
     * Vérifie le statut de la base de données
     */
    private function checkDatabaseStatus() {
        try {
            if ($this->db) {
                $this->db->fetchOne("SELECT 1");
                return [
                    'status' => 'good',
                    'message' => 'Base de données accessible'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Erreur de connexion à la base de données'
            ];
        }
        
        return [
            'status' => 'warning',
            'message' => 'Base de données non configurée'
        ];
    }
    
    /**
     * Vérifie le statut du système de fichiers
     */
    private function checkFileSystemStatus() {
        $freeSpace = disk_free_space(__DIR__);
        $totalSpace = disk_total_space(__DIR__);
        
        if ($freeSpace === false || $totalSpace === false) {
            return [
                'status' => 'warning',
                'message' => 'Impossible de vérifier l\'espace disque'
            ];
        }
        
        $freePercentage = ($freeSpace / $totalSpace) * 100;
        
        if ($freePercentage < 5) {
            return [
                'status' => 'critical',
                'message' => 'Espace disque critique (< 5%)'
            ];
        } elseif ($freePercentage < 15) {
            return [
                'status' => 'warning',
                'message' => 'Espace disque faible (< 15%)'
            ];
        }
        
        return [
            'status' => 'good',
            'message' => 'Espace disque suffisant (' . round($freePercentage, 1) . '%)'
        ];
    }
    
    /**
     * Vérifie le statut de sécurité
     */
    private function checkSecurityStatus() {
        // Vérifications de sécurité basiques
        $issues = [];
        
        // Vérifier les permissions de fichiers sensibles
        $configPath = __DIR__ . '/../../config/config.php';
        if (file_exists($configPath) && (fileperms($configPath) & 0777) > 0644) {
            $issues[] = 'Permissions trop ouvertes sur config.php';
        }
        
        // Vérifier la version PHP
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $issues[] = 'Version PHP obsolète';
        }
        
        if (empty($issues)) {
            return [
                'status' => 'good',
                'message' => 'Aucun problème de sécurité détecté'
            ];
        } elseif (count($issues) === 1) {
            return [
                'status' => 'warning',
                'message' => $issues[0]
            ];
        } else {
            return [
                'status' => 'critical',
                'message' => count($issues) . ' problèmes de sécurité détectés'
            ];
        }
    }
    
    /**
     * Vérifie le statut de performance
     */
    private function checkPerformanceStatus() {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        if ($memoryLimit > 0) {
            $memoryPercentage = ($memoryUsage / $memoryLimit) * 100;
            
            if ($memoryPercentage > 90) {
                return [
                    'status' => 'critical',
                    'message' => 'Utilisation mémoire critique (> 90%)'
                ];
            } elseif ($memoryPercentage > 75) {
                return [
                    'status' => 'warning',
                    'message' => 'Utilisation mémoire élevée (> 75%)'
                ];
            }
        }
        
        return [
            'status' => 'good',
            'message' => 'Performance normale'
        ];
    }
    
    /**
     * Parse la limite de mémoire
     */
    private function parseMemoryLimit($limit) {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $limit = (int) $limit;
        
        switch($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }
        
        return $limit;
    }
    
    /**
     * Obtient l'uptime du système
     */
    private function getSystemUptime() {
        // Approximation basée sur le temps de démarrage de la session
        $startTime = $_SERVER['REQUEST_TIME'] ?? time();
        $uptime = time() - $startTime;
        
        return [
            'seconds' => $uptime,
            'formatted' => $this->formatUptime($uptime)
        ];
    }
    
    /**
     * Formate l'uptime
     */
    private function formatUptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($days > 0) {
            return "{$days}j {$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }
    
    /**
     * Obtient les activités récentes
     */
    private function getRecentActivities() {
        return [
            [
                'type' => 'login',
                'message' => 'Connexion administrateur',
                'time' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
                'icon' => 'fas fa-sign-in-alt'
            ],
            [
                'type' => 'backup',
                'message' => 'Sauvegarde automatique',
                'time' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'icon' => 'fas fa-save'
            ],
            [
                'type' => 'update',
                'message' => 'Vérification des mises à jour',
                'time' => date('Y-m-d H:i:s', strtotime('-6 hours')),
                'icon' => 'fas fa-download'
            ]
        ];
    }
    
    /**
     * Rendu HTML du widget
     */
    public function render() {
        $data = $this->getData();
        return $this->loadView($data);
    }
}