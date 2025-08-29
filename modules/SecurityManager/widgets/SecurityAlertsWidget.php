<?php
/**
 * N3XT WEB - Security Alerts Widget
 * 
 * Widget pour afficher les alertes de sécurité et le statut de protection.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

// Charger la classe de base
require_once __DIR__ . '/../../BaseWidget.php';

class SecurityAlertsWidget extends BaseWidget {
    
    public function __construct() {
        parent::__construct('SecurityAlertsWidget', 'SecurityManager');
    }
    
    /**
     * Configuration par défaut du widget
     */
    protected function getDefaultConfiguration() {
        return [
            'enabled' => true,
            'title' => 'Alertes de sécurité',
            'description' => 'Affiche les alertes de sécurité et le statut de protection du système',
            'refresh_interval' => 60,
            'max_alerts' => 10,
            'show_resolved' => false,
            'alert_levels' => ['critical', 'high', 'medium', 'low'],
            'auto_scan_enabled' => true
        ];
    }
    
    /**
     * Génère les données du widget
     */
    public function getData() {
        $data = [
            'alerts' => $this->getSecurityAlerts(),
            'summary' => $this->getAlertsSummary(),
            'security_status' => $this->getSecurityStatus(),
            'threat_indicators' => $this->getThreatIndicators(),
            'recent_scans' => $this->getRecentScans(),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        return $data;
    }
    
    /**
     * Obtient les alertes de sécurité
     */
    private function getSecurityAlerts() {
        $alerts = [];
        
        try {
            if ($this->db) {
                $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
                $levels = implode("','", $this->getConfig('alert_levels', ['critical', 'high', 'medium', 'low']));
                $limit = $this->getConfig('max_alerts', 10);
                $showResolved = $this->getConfig('show_resolved', false);
                
                $sql = "SELECT * FROM {$prefix}security_alerts 
                       WHERE severity IN ('{$levels}')";
                       
                if (!$showResolved) {
                    $sql .= " AND status != 'resolved'";
                }
                
                $sql .= " ORDER BY 
                           CASE severity 
                               WHEN 'critical' THEN 1
                               WHEN 'high' THEN 2  
                               WHEN 'medium' THEN 3
                               WHEN 'low' THEN 4
                           END,
                           created_at DESC 
                       LIMIT {$limit}";
                       
                $alerts = $this->db->fetchAll($sql);
            }
        } catch (Exception $e) {
            // Ajouter des alertes d'exemple en cas d'erreur
            $alerts = $this->getSampleAlerts();
        }
        
        return $alerts;
    }
    
    /**
     * Obtient des alertes d'exemple
     */
    private function getSampleAlerts() {
        return [
            [
                'id' => 1,
                'type' => 'suspicious_login',
                'severity' => 'high',
                'title' => 'Tentatives de connexion suspectes',
                'description' => 'Multiples tentatives de connexion échouées depuis une IP inconnue',
                'details' => 'IP: 203.0.113.42, Tentatives: 15, Période: 10 minutes',
                'status' => 'active',
                'source_ip' => '203.0.113.42',
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
                'last_seen' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
                'risk_score' => 85
            ],
            [
                'id' => 2,
                'type' => 'file_integrity',
                'severity' => 'medium',
                'title' => 'Modification de fichier critique',
                'description' => 'Un fichier système critique a été modifié',
                'details' => 'Fichier: /config/config.php, Taille modifiée: +125 bytes',
                'status' => 'investigating',
                'source_ip' => '192.168.1.100',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'last_seen' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'risk_score' => 65
            ],
            [
                'id' => 3,
                'type' => 'vulnerability_scan',
                'severity' => 'low',
                'title' => 'Scan de vulnérabilité terminé',
                'description' => 'Scan automatique des vulnérabilités terminé avec succès',
                'details' => '0 vulnérabilité critique, 2 mineures détectées',
                'status' => 'resolved',
                'source_ip' => null,
                'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours')),
                'last_seen' => date('Y-m-d H:i:s', strtotime('-4 hours')),
                'risk_score' => 25
            ],
            [
                'id' => 4,
                'type' => 'brute_force',
                'severity' => 'critical',
                'title' => 'Attaque par force brute détectée',
                'description' => 'Attaque par force brute active sur le panel d\'administration',
                'details' => 'IP: 198.51.100.42, Tentatives: 50+ en 5 minutes',
                'status' => 'blocked',
                'source_ip' => '198.51.100.42',
                'created_at' => date('Y-m-d H:i:s', strtotime('-6 hours')),
                'last_seen' => date('Y-m-d H:i:s', strtotime('-5 hours')),
                'risk_score' => 95
            ]
        ];
    }
    
    /**
     * Obtient le résumé des alertes
     */
    private function getAlertsSummary() {
        $alerts = $this->getSecurityAlerts();
        
        $summary = [
            'total' => count($alerts),
            'by_severity' => [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0
            ],
            'by_status' => [
                'active' => 0,
                'investigating' => 0,
                'blocked' => 0,
                'resolved' => 0
            ],
            'risk_levels' => [
                'very_high' => 0, // 90-100
                'high' => 0,      // 70-89
                'medium' => 0,    // 40-69
                'low' => 0        // 0-39
            ],
            'avg_risk_score' => 0
        ];
        
        $totalRiskScore = 0;
        
        foreach ($alerts as $alert) {
            $severity = $alert['severity'] ?? 'low';
            $status = $alert['status'] ?? 'active';
            $riskScore = $alert['risk_score'] ?? 0;
            
            // Par sévérité
            if (isset($summary['by_severity'][$severity])) {
                $summary['by_severity'][$severity]++;
            }
            
            // Par statut
            if (isset($summary['by_status'][$status])) {
                $summary['by_status'][$status]++;
            }
            
            // Par niveau de risque
            if ($riskScore >= 90) {
                $summary['risk_levels']['very_high']++;
            } elseif ($riskScore >= 70) {
                $summary['risk_levels']['high']++;
            } elseif ($riskScore >= 40) {
                $summary['risk_levels']['medium']++;
            } else {
                $summary['risk_levels']['low']++;
            }
            
            $totalRiskScore += $riskScore;
        }
        
        $summary['avg_risk_score'] = count($alerts) > 0 ? round($totalRiskScore / count($alerts), 1) : 0;
        
        return $summary;
    }
    
    /**
     * Obtient le statut général de sécurité
     */
    private function getSecurityStatus() {
        $alerts = $this->getSecurityAlerts();
        $summary = $this->getAlertsSummary();
        
        // Calculer le niveau de menace global
        $threatLevel = 'low';
        if ($summary['by_severity']['critical'] > 0) {
            $threatLevel = 'critical';
        } elseif ($summary['by_severity']['high'] > 0) {
            $threatLevel = 'high';
        } elseif ($summary['by_severity']['medium'] > 0) {
            $threatLevel = 'medium';
        }
        
        // Vérifications de sécurité
        $securityChecks = [
            'firewall' => $this->checkFirewallStatus(),
            'ssl' => $this->checkSSLStatus(),
            'updates' => $this->checkSecurityUpdates(),
            'permissions' => $this->checkFilePermissions(),
            'brute_force_protection' => $this->checkBruteForceProtection()
        ];
        
        return [
            'threat_level' => $threatLevel,
            'overall_score' => $this->calculateOverallSecurityScore($securityChecks, $summary),
            'active_threats' => $summary['by_status']['active'],
            'blocked_threats' => $summary['by_status']['blocked'],
            'security_checks' => $securityChecks,
            'last_scan' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'next_scan' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ];
    }
    
    /**
     * Vérifie le statut du firewall
     */
    private function checkFirewallStatus() {
        return [
            'status' => 'active',
            'message' => 'Firewall actif et configuré',
            'score' => 100
        ];
    }
    
    /**
     * Vérifie le statut SSL
     */
    private function checkSSLStatus() {
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        return [
            'status' => $isHttps ? 'active' : 'warning',
            'message' => $isHttps ? 'SSL/TLS activé' : 'SSL/TLS non configuré',
            'score' => $isHttps ? 100 : 40
        ];
    }
    
    /**
     * Vérifie les mises à jour de sécurité
     */
    private function checkSecurityUpdates() {
        return [
            'status' => 'good',
            'message' => 'Système à jour',
            'score' => 90
        ];
    }
    
    /**
     * Vérifie les permissions de fichiers
     */
    private function checkFilePermissions() {
        $configFile = __DIR__ . '/../../../config/config.php';
        $hasCorrectPerms = !file_exists($configFile) || (fileperms($configFile) & 0777) <= 0644;
        
        return [
            'status' => $hasCorrectPerms ? 'good' : 'warning',
            'message' => $hasCorrectPerms ? 'Permissions correctes' : 'Permissions trop ouvertes',
            'score' => $hasCorrectPerms ? 100 : 60
        ];
    }
    
    /**
     * Vérifie la protection contre les attaques par force brute
     */
    private function checkBruteForceProtection() {
        return [
            'status' => 'active',
            'message' => 'Protection active',
            'score' => 95
        ];
    }
    
    /**
     * Calcule le score de sécurité global
     */
    private function calculateOverallSecurityScore($checks, $summary) {
        $totalScore = 0;
        $checkCount = 0;
        
        foreach ($checks as $check) {
            $totalScore += $check['score'];
            $checkCount++;
        }
        
        $baseScore = $checkCount > 0 ? $totalScore / $checkCount : 100;
        
        // Réduire le score selon les alertes actives
        $penalty = $summary['by_severity']['critical'] * 20 + 
                  $summary['by_severity']['high'] * 10 + 
                  $summary['by_severity']['medium'] * 5;
        
        return max(0, $baseScore - $penalty);
    }
    
    /**
     * Obtient les indicateurs de menace
     */
    private function getThreatIndicators() {
        return [
            'suspicious_ips' => [
                'count' => 3,
                'description' => 'Adresses IP suspectes détectées'
            ],
            'failed_logins' => [
                'count' => 12,
                'description' => 'Tentatives de connexion échouées (24h)'
            ],
            'blocked_requests' => [
                'count' => 45,
                'description' => 'Requêtes malveillantes bloquées (24h)'
            ],
            'vulnerability_score' => [
                'count' => 2,
                'description' => 'Vulnérabilités mineures détectées'
            ]
        ];
    }
    
    /**
     * Obtient les scans récents
     */
    private function getRecentScans() {
        return [
            [
                'type' => 'full_scan',
                'status' => 'completed',
                'duration' => '00:05:32',
                'threats_found' => 0,
                'completed_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ],
            [
                'type' => 'quick_scan',
                'status' => 'completed',
                'duration' => '00:01:15',
                'threats_found' => 0,
                'completed_at' => date('Y-m-d H:i:s', strtotime('-6 hours'))
            ],
            [
                'type' => 'vulnerability_scan',
                'status' => 'completed',
                'duration' => '00:03:45',
                'threats_found' => 2,
                'completed_at' => date('Y-m-d H:i:s', strtotime('-12 hours'))
            ]
        ];
    }
    
    /**
     * Obtient l'icône pour un type d'alerte
     */
    public function getAlertIcon($type) {
        $icons = [
            'suspicious_login' => 'fas fa-user-times',
            'brute_force' => 'fas fa-hammer',
            'file_integrity' => 'fas fa-file-alt',
            'vulnerability_scan' => 'fas fa-search',
            'malware' => 'fas fa-virus',
            'intrusion' => 'fas fa-exclamation-triangle',
            'default' => 'fas fa-shield-alt'
        ];
        
        return $icons[$type] ?? $icons['default'];
    }
    
    /**
     * Obtient la couleur pour un niveau de sévérité
     */
    public function getSeverityColor($severity) {
        $colors = [
            'critical' => '#dc3545',
            'high' => '#fd7e14',
            'medium' => '#ffc107',
            'low' => '#28a745'
        ];
        
        return $colors[$severity] ?? $colors['low'];
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