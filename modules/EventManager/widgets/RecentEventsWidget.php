<?php
/**
 * N3XT WEB - Recent Events Widget
 * 
 * Widget pour afficher les événements récents et les activités du système.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

// Charger la classe de base
require_once __DIR__ . '/../../BaseWidget.php';

class RecentEventsWidget extends BaseWidget {
    
    public function __construct() {
        parent::__construct('RecentEventsWidget', 'EventManager');
    }
    
    /**
     * Configuration par défaut du widget
     */
    protected function getDefaultConfiguration() {
        return [
            'enabled' => true,
            'title' => 'Événements récents',
            'description' => 'Affiche les événements récents et les activités du système',
            'refresh_interval' => 30,
            'max_events' => 15,
            'show_categories' => ['system', 'security', 'user', 'maintenance'],
            'show_chart' => true,
            'group_by_date' => true
        ];
    }
    
    /**
     * Génère les données du widget
     */
    public function getData() {
        $data = [
            'events' => $this->getRecentEvents(),
            'summary' => $this->getEventsSummary(),
            'categories' => $this->getEventCategories(),
            'timeline' => $this->getEventsTimeline(),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        return $data;
    }
    
    /**
     * Obtient les événements récents
     */
    private function getRecentEvents() {
        $events = [];
        
        try {
            if ($this->db) {
                $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
                $categories = implode("','", $this->getConfig('show_categories', ['system', 'security', 'user', 'maintenance']));
                $limit = $this->getConfig('max_events', 15);
                
                $sql = "SELECT * FROM {$prefix}events 
                       WHERE category IN ('{$categories}')
                       ORDER BY created_at DESC 
                       LIMIT {$limit}";
                       
                $events = $this->db->fetchAll($sql);
            }
        } catch (Exception $e) {
            // Ajouter des événements d'exemple en cas d'erreur
            $events = $this->getSampleEvents();
        }
        
        return $events;
    }
    
    /**
     * Obtient des événements d'exemple
     */
    private function getSampleEvents() {
        return [
            [
                'id' => 1,
                'type' => 'login',
                'category' => 'security',
                'message' => 'Connexion administrateur réussie',
                'details' => 'IP: 192.168.1.100, Navigateur: Chrome',
                'severity' => 'info',
                'user_id' => 1,
                'username' => 'admin',
                'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                'ip_address' => '192.168.1.100'
            ],
            [
                'id' => 2,
                'type' => 'backup_completed',
                'category' => 'maintenance',
                'message' => 'Sauvegarde automatique terminée',
                'details' => 'Taille: 125 MB, Durée: 3m 42s',
                'severity' => 'success',
                'user_id' => null,
                'username' => 'system',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'ip_address' => null
            ],
            [
                'id' => 3,
                'type' => 'security_scan',
                'category' => 'security',
                'message' => 'Scan de sécurité automatique',
                'details' => '0 vulnérabilité détectée',
                'severity' => 'success',
                'user_id' => null,
                'username' => 'system',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'ip_address' => null
            ],
            [
                'id' => 4,
                'type' => 'login_failed',
                'category' => 'security',
                'message' => 'Tentative de connexion échouée',
                'details' => 'IP: 203.0.113.42, Tentatives: 3',
                'severity' => 'warning',
                'user_id' => null,
                'username' => 'unknown',
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                'ip_address' => '203.0.113.42'
            ],
            [
                'id' => 5,
                'type' => 'system_update',
                'category' => 'system',
                'message' => 'Vérification des mises à jour',
                'details' => 'Aucune mise à jour disponible',
                'severity' => 'info',
                'user_id' => null,
                'username' => 'system',
                'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours')),
                'ip_address' => null
            ],
            [
                'id' => 6,
                'type' => 'maintenance_cleanup',
                'category' => 'maintenance',
                'message' => 'Nettoyage automatique des logs',
                'details' => '45 fichiers supprimés, 230 MB libérés',
                'severity' => 'success',
                'user_id' => null,
                'username' => 'system',
                'created_at' => date('Y-m-d H:i:s', strtotime('-6 hours')),
                'ip_address' => null
            ],
            [
                'id' => 7,
                'type' => 'user_action',
                'category' => 'user',
                'message' => 'Configuration modifiée',
                'details' => 'Paramètres de sécurité mis à jour',
                'severity' => 'info',
                'user_id' => 1,
                'username' => 'admin',
                'created_at' => date('Y-m-d H:i:s', strtotime('-8 hours')),
                'ip_address' => '192.168.1.100'
            ]
        ];
    }
    
    /**
     * Obtient le résumé des événements
     */
    private function getEventsSummary() {
        $events = $this->getRecentEvents();
        
        $summary = [
            'total' => count($events),
            'by_category' => [
                'system' => 0,
                'security' => 0,
                'user' => 0,
                'maintenance' => 0
            ],
            'by_severity' => [
                'success' => 0,
                'info' => 0,
                'warning' => 0,
                'error' => 0,
                'critical' => 0
            ],
            'by_time' => [
                'last_hour' => 0,
                'last_24h' => 0,
                'last_week' => 0
            ]
        ];
        
        $now = time();
        
        foreach ($events as $event) {
            $category = $event['category'] ?? 'system';
            $severity = $event['severity'] ?? 'info';
            $eventTime = strtotime($event['created_at']);
            
            // Par catégorie
            if (isset($summary['by_category'][$category])) {
                $summary['by_category'][$category]++;
            }
            
            // Par sévérité
            if (isset($summary['by_severity'][$severity])) {
                $summary['by_severity'][$severity]++;
            }
            
            // Par période
            $timeDiff = $now - $eventTime;
            if ($timeDiff <= 3600) {
                $summary['by_time']['last_hour']++;
            }
            if ($timeDiff <= 86400) {
                $summary['by_time']['last_24h']++;
            }
            if ($timeDiff <= 604800) {
                $summary['by_time']['last_week']++;
            }
        }
        
        return $summary;
    }
    
    /**
     * Obtient les catégories d'événements
     */
    private function getEventCategories() {
        return [
            'system' => [
                'name' => 'Système',
                'icon' => 'fas fa-server',
                'color' => '#007bff',
                'description' => 'Événements système et application'
            ],
            'security' => [
                'name' => 'Sécurité',
                'icon' => 'fas fa-shield-alt',
                'color' => '#dc3545',
                'description' => 'Événements de sécurité et authentification'
            ],
            'user' => [
                'name' => 'Utilisateur',
                'icon' => 'fas fa-user',
                'color' => '#28a745',
                'description' => 'Actions utilisateur et modifications'
            ],
            'maintenance' => [
                'name' => 'Maintenance',
                'icon' => 'fas fa-tools',
                'color' => '#ffc107',
                'description' => 'Tâches de maintenance et nettoyage'
            ]
        ];
    }
    
    /**
     * Obtient la timeline des événements
     */
    private function getEventsTimeline() {
        $events = $this->getRecentEvents();
        $timeline = [];
        
        // Grouper par date si activé
        if ($this->getConfig('group_by_date', true)) {
            foreach ($events as $event) {
                $date = date('Y-m-d', strtotime($event['created_at']));
                if (!isset($timeline[$date])) {
                    $timeline[$date] = [
                        'date' => $date,
                        'formatted_date' => $this->formatDate($date),
                        'events' => []
                    ];
                }
                $timeline[$date]['events'][] = $event;
            }
        } else {
            $timeline['all'] = [
                'date' => 'all',
                'formatted_date' => 'Tous les événements',
                'events' => $events
            ];
        }
        
        return $timeline;
    }
    
    /**
     * Formate une date
     */
    private function formatDate($date) {
        $timestamp = strtotime($date);
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        if ($date === $today) {
            return 'Aujourd\'hui';
        } elseif ($date === $yesterday) {
            return 'Hier';
        } else {
            return date('d/m/Y', $timestamp);
        }
    }
    
    /**
     * Obtient l'icône pour un type d'événement
     */
    public function getEventIcon($type) {
        $icons = [
            'login' => 'fas fa-sign-in-alt',
            'logout' => 'fas fa-sign-out-alt',
            'login_failed' => 'fas fa-times-circle',
            'backup_completed' => 'fas fa-save',
            'backup_failed' => 'fas fa-exclamation-triangle',
            'security_scan' => 'fas fa-search',
            'system_update' => 'fas fa-download',
            'maintenance_cleanup' => 'fas fa-broom',
            'user_action' => 'fas fa-edit',
            'error' => 'fas fa-bug',
            'warning' => 'fas fa-exclamation-triangle',
            'default' => 'fas fa-info-circle'
        ];
        
        return $icons[$type] ?? $icons['default'];
    }
    
    /**
     * Obtient la couleur pour un niveau de sévérité
     */
    public function getSeverityColor($severity) {
        $colors = [
            'success' => '#28a745',
            'info' => '#17a2b8',
            'warning' => '#ffc107',
            'error' => '#dc3545',
            'critical' => '#6f42c1'
        ];
        
        return $colors[$severity] ?? $colors['info'];
    }
    
    /**
     * Formate le temps écoulé
     */
    public function timeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'À l\'instant';
        if ($time < 3600) return floor($time/60) . ' min';
        if ($time < 86400) return floor($time/3600) . ' h';
        if ($time < 2592000) return floor($time/86400) . ' j';
        
        return date('d/m/Y H:i', strtotime($datetime));
    }
    
    /**
     * Rendu HTML du widget
     */
    public function render() {
        $data = $this->getData();
        return $this->loadView($data);
    }
}