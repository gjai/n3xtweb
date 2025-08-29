<?php
/**
 * N3XT WEB - Security Log Widget
 * 
 * Widget pour afficher les journaux de sécurité et les événements récents.
 * 
 * @package N3xtWeb
 * @subpackage SecurityManager
 * @version 1.0.0
 * @author N3XT Communication
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

// Charger la classe de base
require_once __DIR__ . '/../../BaseWidget.php';

class SecurityLogWidget extends BaseWidget {
    
    public function __construct() {
        parent::__construct('SecurityLogWidget', 'SecurityManager');
    }
    
    /**
     * Configuration par défaut du widget
     */
    protected function getDefaultConfiguration() {
        return [
            'enabled' => true,
            'title' => 'Journal de sécurité',
            'description' => 'Affiche les événements de sécurité récents et les logs d\'audit',
            'refresh_interval' => 30,
            'max_entries' => 15,
            'show_levels' => ['critical', 'high', 'medium', 'low'],
            'show_timestamps' => true,
            'auto_refresh' => true,
            'compact_view' => false
        ];
    }
    
    /**
     * Génère les données du widget
     */
    protected function generateData() {
        try {
            $data = [
                'recent_events' => $this->getRecentSecurityEvents(),
                'login_attempts' => $this->getRecentLoginAttempts(),
                'blocked_ips' => $this->getBlockedIPs(),
                'security_alerts' => $this->getSecurityAlerts(),
                'system_status' => $this->getSystemSecurityStatus()
            ];
            
            return $data;
            
        } catch (Exception $e) {
            error_log("SecurityLogWidget: Failed to generate data - " . $e->getMessage());
            return [
                'error' => 'Erreur lors du chargement des données de sécurité',
                'recent_events' => [],
                'login_attempts' => [],
                'blocked_ips' => [],
                'security_alerts' => []
            ];
        }
    }
    
    /**
     * Récupère les événements de sécurité récents
     */
    private function getRecentSecurityEvents() {
        try {
            $db = Database::getInstance();
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $maxEntries = $this->getConfig('max_entries');
            
            $sql = "SELECT 
                        event_type,
                        severity,
                        message,
                        details,
                        ip_address,
                        user_agent,
                        created_at
                    FROM {$prefix}security_events 
                    ORDER BY created_at DESC 
                    LIMIT ?";
            
            $events = $db->fetchAll($sql, [$maxEntries]);
            
            // Formater les événements
            foreach ($events as &$event) {
                $event['formatted_time'] = $this->formatTimestamp($event['created_at']);
                $event['severity_class'] = $this->getSeverityClass($event['severity']);
                $event['type_icon'] = $this->getEventTypeIcon($event['event_type']);
                
                // Décoder les détails JSON si présents
                if (!empty($event['details'])) {
                    $event['details_parsed'] = json_decode($event['details'], true);
                }
            }
            
            return $events;
            
        } catch (Exception $e) {
            error_log("SecurityLogWidget: Failed to get recent security events - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère les tentatives de connexion récentes
     */
    private function getRecentLoginAttempts() {
        try {
            $db = Database::getInstance();
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "SELECT 
                        ip_address,
                        username,
                        success,
                        failure_reason,
                        user_agent,
                        attempt_time
                    FROM {$prefix}login_attempts 
                    WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    ORDER BY attempt_time DESC 
                    LIMIT 10";
            
            $attempts = $db->fetchAll($sql);
            
            // Formater les tentatives
            foreach ($attempts as &$attempt) {
                $attempt['formatted_time'] = $this->formatTimestamp($attempt['attempt_time']);
                $attempt['status_class'] = $attempt['success'] ? 'success' : 'danger';
                $attempt['status_text'] = $attempt['success'] ? 'Succès' : 'Échec';
                $attempt['short_user_agent'] = $this->shortenUserAgent($attempt['user_agent']);
            }
            
            return $attempts;
            
        } catch (Exception $e) {
            error_log("SecurityLogWidget: Failed to get recent login attempts - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère les IPs bloquées
     */
    private function getBlockedIPs() {
        try {
            $db = Database::getInstance();
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "SELECT 
                        ip_address,
                        reason,
                        blocked_until,
                        created_at
                    FROM {$prefix}blocked_ips 
                    WHERE blocked_until > NOW()
                    ORDER BY created_at DESC 
                    LIMIT 10";
            
            $blockedIPs = $db->fetchAll($sql);
            
            // Formater les IPs bloquées
            foreach ($blockedIPs as &$blocked) {
                $blocked['formatted_blocked_until'] = $this->formatTimestamp($blocked['blocked_until']);
                $blocked['formatted_created'] = $this->formatTimestamp($blocked['created_at']);
                $blocked['time_remaining'] = $this->getTimeRemaining($blocked['blocked_until']);
            }
            
            return $blockedIPs;
            
        } catch (Exception $e) {
            error_log("SecurityLogWidget: Failed to get blocked IPs - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère les alertes de sécurité récentes
     */
    private function getSecurityAlerts() {
        try {
            $db = Database::getInstance();
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "SELECT 
                        title,
                        level,
                        description,
                        status,
                        created_at
                    FROM {$prefix}security_alerts 
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY 
                        CASE level 
                            WHEN 'critical' THEN 1 
                            WHEN 'high' THEN 2 
                            WHEN 'medium' THEN 3 
                            WHEN 'low' THEN 4 
                        END,
                        created_at DESC 
                    LIMIT 5";
            
            $alerts = $db->fetchAll($sql);
            
            // Formater les alertes
            foreach ($alerts as &$alert) {
                $alert['formatted_time'] = $this->formatTimestamp($alert['created_at']);
                $alert['level_class'] = $this->getSeverityClass($alert['level']);
                $alert['level_icon'] = $this->getLevelIcon($alert['level']);
            }
            
            return $alerts;
            
        } catch (Exception $e) {
            error_log("SecurityLogWidget: Failed to get security alerts - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère le statut de sécurité du système
     */
    private function getSystemSecurityStatus() {
        try {
            $db = Database::getInstance();
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            // Compter les alertes actives par niveau
            $sql = "SELECT 
                        level, 
                        COUNT(*) as count 
                    FROM {$prefix}security_alerts 
                    WHERE status = 'active' 
                    GROUP BY level";
            
            $alertCounts = $db->fetchAll($sql);
            
            $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
            foreach ($alertCounts as $alert) {
                $counts[$alert['level']] = (int)$alert['count'];
            }
            
            // Déterminer le niveau global
            $globalLevel = 'low';
            if ($counts['critical'] > 0) {
                $globalLevel = 'critical';
            } elseif ($counts['high'] > 0) {
                $globalLevel = 'high';
            } elseif ($counts['medium'] > 0) {
                $globalLevel = 'medium';
            }
            
            return [
                'global_level' => $globalLevel,
                'alert_counts' => $counts,
                'total_alerts' => array_sum($counts),
                'status_class' => $this->getSeverityClass($globalLevel)
            ];
            
        } catch (Exception $e) {
            error_log("SecurityLogWidget: Failed to get system security status - " . $e->getMessage());
            return [
                'global_level' => 'unknown',
                'alert_counts' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0],
                'total_alerts' => 0,
                'status_class' => 'secondary'
            ];
        }
    }
    
    /**
     * Formate un timestamp pour l'affichage
     */
    private function formatTimestamp($timestamp) {
        try {
            $date = new DateTime($timestamp);
            $now = new DateTime();
            $interval = $now->diff($date);
            
            if ($interval->d > 0) {
                return $interval->d . 'j ' . $interval->h . 'h';
            } elseif ($interval->h > 0) {
                return $interval->h . 'h ' . $interval->i . 'min';
            } elseif ($interval->i > 0) {
                return $interval->i . 'min';
            } else {
                return 'À l\'instant';
            }
        } catch (Exception $e) {
            return $timestamp;
        }
    }
    
    /**
     * Retourne la classe CSS pour un niveau de sévérité
     */
    private function getSeverityClass($severity) {
        switch (strtolower($severity)) {
            case 'critical':
                return 'danger';
            case 'high':
                return 'warning';
            case 'medium':
                return 'info';
            case 'low':
                return 'success';
            default:
                return 'secondary';
        }
    }
    
    /**
     * Retourne l'icône pour un type d'événement
     */
    private function getEventTypeIcon($eventType) {
        switch (strtolower($eventType)) {
            case 'login':
                return '🔑';
            case 'security':
                return '🛡️';
            case 'access':
                return '🚪';
            case 'admin':
                return '⚙️';
            case 'error':
                return '❌';
            default:
                return '📝';
        }
    }
    
    /**
     * Retourne l'icône pour un niveau d'alerte
     */
    private function getLevelIcon($level) {
        switch (strtolower($level)) {
            case 'critical':
                return '🚨';
            case 'high':
                return '⚠️';
            case 'medium':
                return '⚡';
            case 'low':
                return 'ℹ️';
            default:
                return '❓';
        }
    }
    
    /**
     * Raccourcit le user agent pour l'affichage
     */
    private function shortenUserAgent($userAgent) {
        if (strlen($userAgent) > 50) {
            return substr($userAgent, 0, 47) . '...';
        }
        return $userAgent;
    }
    
    /**
     * Calcule le temps restant avant déblocage
     */
    private function getTimeRemaining($blockedUntil) {
        try {
            $until = new DateTime($blockedUntil);
            $now = new DateTime();
            
            if ($until <= $now) {
                return 'Expiré';
            }
            
            $interval = $now->diff($until);
            
            if ($interval->d > 0) {
                return $interval->d . 'j ' . $interval->h . 'h';
            } elseif ($interval->h > 0) {
                return $interval->h . 'h ' . $interval->i . 'min';
            } else {
                return $interval->i . 'min';
            }
        } catch (Exception $e) {
            return 'Inconnu';
        }
    }
}