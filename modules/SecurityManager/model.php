<?php
/**
 * N3XT WEB - Security Manager Model
 * 
 * Modèle pour la gestion des données de sécurité.
 * Gère l'interaction avec la base de données et la logique métier.
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

class SecurityManagerModel {
    
    private $db;
    private $securityManager;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->db = Database::getInstance();
        
        // Utiliser le SecurityManager singleton si disponible
        if (class_exists('SecurityManager')) {
            $this->securityManager = SecurityManager::getInstance();
        }
    }
    
    /**
     * Retourne les alertes actives
     */
    public function getActiveAlerts($limit = 10) {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "SELECT * FROM {$prefix}security_alerts 
                    WHERE status = 'active' 
                    ORDER BY created_at DESC 
                    LIMIT ?";
            
            return $this->db->fetchAll($sql, [$limit]);
            
        } catch (Exception $e) {
            error_log("SecurityManagerModel: Failed to get active alerts - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Retourne le niveau de menace actuel
     */
    public function getCurrentThreatLevel() {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            // Vérifier les alertes critiques récentes
            $sql = "SELECT COUNT(*) as critical_count 
                    FROM {$prefix}security_alerts 
                    WHERE level = 'critical' 
                    AND status = 'active' 
                    AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            
            $result = $this->db->fetch($sql);
            $criticalCount = $result['critical_count'] ?? 0;
            
            if ($criticalCount > 0) {
                return 'CRITICAL';
            }
            
            // Vérifier les alertes élevées
            $sql = "SELECT COUNT(*) as high_count 
                    FROM {$prefix}security_alerts 
                    WHERE level IN ('high', 'medium') 
                    AND status = 'active' 
                    AND created_at > DATE_SUB(NOW(), INTERVAL 6 HOUR)";
            
            $result = $this->db->fetch($sql);
            $highCount = $result['high_count'] ?? 0;
            
            if ($highCount > 5) {
                return 'HIGH';
            } elseif ($highCount > 2) {
                return 'MEDIUM';
            }
            
            return 'LOW';
            
        } catch (Exception $e) {
            error_log("SecurityManagerModel: Failed to get threat level - " . $e->getMessage());
            return 'UNKNOWN';
        }
    }
    
    /**
     * Retourne le nombre d'IPs bloquées
     */
    public function getBlockedIPsCount() {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "SELECT COUNT(*) as blocked_count 
                    FROM {$prefix}login_attempts 
                    WHERE blocked_until > NOW()";
            
            $result = $this->db->fetch($sql);
            return $result['blocked_count'] ?? 0;
            
        } catch (Exception $e) {
            error_log("SecurityManagerModel: Failed to get blocked IPs count - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Retourne les activités de sécurité récentes
     */
    public function getRecentSecurityActivities($limit = 20) {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "SELECT 
                        'security_alert' as type,
                        level as severity,
                        title as message,
                        created_at as timestamp
                    FROM {$prefix}security_alerts 
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    
                    UNION ALL
                    
                    SELECT 
                        'login_attempt' as type,
                        CASE WHEN success = 1 THEN 'low' ELSE 'medium' END as severity,
                        CONCAT('Login attempt from ', ip_address) as message,
                        attempt_time as timestamp
                    FROM {$prefix}login_attempts 
                    WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    
                    ORDER BY timestamp DESC 
                    LIMIT ?";
            
            return $this->db->fetchAll($sql, [$limit]);
            
        } catch (Exception $e) {
            error_log("SecurityManagerModel: Failed to get recent activities - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Retourne le statut du dernier scan
     */
    public function getLastScanStatus() {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "SELECT * FROM {$prefix}security_scans 
                    ORDER BY created_at DESC 
                    LIMIT 1";
            
            $result = $this->db->fetch($sql);
            
            if ($result) {
                return [
                    'last_scan' => $result['created_at'],
                    'threat_level' => $result['threat_level'],
                    'issues_found' => $result['issues_count'],
                    'duration' => $result['scan_duration']
                ];
            }
            
            return [
                'last_scan' => null,
                'threat_level' => 'UNKNOWN',
                'issues_found' => 0,
                'duration' => 0
            ];
            
        } catch (Exception $e) {
            error_log("SecurityManagerModel: Failed to get last scan status - " . $e->getMessage());
            return [
                'last_scan' => null,
                'threat_level' => 'UNKNOWN',
                'issues_found' => 0,
                'duration' => 0
            ];
        }
    }
    
    /**
     * Retourne les alertes selon les critères
     */
    public function getAlerts($limit = 10, $level = 'all') {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "SELECT * FROM {$prefix}security_alerts WHERE 1=1";
            $params = [];
            
            if ($level !== 'all') {
                $sql .= " AND level = ?";
                $params[] = $level;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("SecurityManagerModel: Failed to get alerts - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Effectue un scan de sécurité
     */
    public function performSecurityScan() {
        $startTime = microtime(true);
        
        try {
            $results = [
                'timestamp' => date('Y-m-d H:i:s'),
                'threat_level' => 'LOW',
                'issues' => [],
                'recommendations' => []
            ];
            
            // Utiliser SecurityManager si disponible
            if ($this->securityManager) {
                $results = $this->securityManager->performSecurityScan();
            } else {
                // Scan basique si SecurityManager n'est pas disponible
                $results = $this->performBasicScan();
            }
            
            $duration = round((microtime(true) - $startTime) * 1000);
            
            // Enregistrer les résultats du scan
            $this->saveScanResults($results, $duration);
            
            return $results;
            
        } catch (Exception $e) {
            error_log("SecurityManagerModel: Security scan failed - " . $e->getMessage());
            return [
                'timestamp' => date('Y-m-d H:i:s'),
                'threat_level' => 'UNKNOWN',
                'issues' => ['Erreur lors du scan de sécurité'],
                'recommendations' => []
            ];
        }
    }
    
    /**
     * Scan de sécurité basique
     */
    private function performBasicScan() {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'threat_level' => 'LOW',
            'issues' => [],
            'recommendations' => []
        ];
        
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            // Vérifier les tentatives de connexion suspectes
            $sql = "SELECT COUNT(*) as failed_attempts 
                    FROM {$prefix}login_attempts 
                    WHERE success = 0 
                    AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            
            $result = $this->db->fetch($sql);
            $failedAttempts = $result['failed_attempts'] ?? 0;
            
            if ($failedAttempts > 10) {
                $results['issues'][] = "Nombre élevé de tentatives de connexion échouées: {$failedAttempts}";
                $results['threat_level'] = 'MEDIUM';
            }
            
            // Vérifier les IPs bloquées
            $blockedIPs = $this->getBlockedIPsCount();
            if ($blockedIPs > 5) {
                $results['issues'][] = "Nombre élevé d'IPs bloquées: {$blockedIPs}";
                $results['threat_level'] = 'MEDIUM';
            }
            
            // Générer des recommandations
            if ($failedAttempts > 5) {
                $results['recommendations'][] = 'Surveiller les tentatives de connexion';
            }
            
            if ($blockedIPs > 0) {
                $results['recommendations'][] = 'Réviser les IPs bloquées périodiquement';
            }
            
            if (empty($results['issues'])) {
                $results['recommendations'][] = 'Système sécurisé - continuer la surveillance';
            }
            
        } catch (Exception $e) {
            error_log("SecurityManagerModel: Basic scan failed - " . $e->getMessage());
            $results['issues'][] = 'Erreur lors de l\'analyse de sécurité';
        }
        
        return $results;
    }
    
    /**
     * Sauvegarde les résultats de scan
     */
    private function saveScanResults($results, $duration) {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "INSERT INTO {$prefix}security_scans 
                    (threat_level, issues_count, issues_details, recommendations, scan_duration, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            $this->db->execute($sql, [
                $results['threat_level'],
                count($results['issues']),
                json_encode($results['issues']),
                json_encode($results['recommendations']),
                $duration
            ]);
            
        } catch (Exception $e) {
            error_log("SecurityManagerModel: Failed to save scan results - " . $e->getMessage());
        }
    }
    
    /**
     * Bloque une adresse IP
     */
    public function blockIP($ip, $reason = '', $duration = 3600) {
        try {
            if ($this->securityManager) {
                return $this->securityManager->blockIP($ip, $reason, $duration);
            }
            
            // Implémentation basique si SecurityManager n'est pas disponible
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $blockedUntil = date('Y-m-d H:i:s', time() + $duration);
            
            $sql = "INSERT INTO {$prefix}blocked_ips (ip_address, reason, blocked_until, created_at) 
                    VALUES (?, ?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                    reason = VALUES(reason), 
                    blocked_until = VALUES(blocked_until)";
            
            $this->db->execute($sql, [$ip, $reason, $blockedUntil]);
            return true;
            
        } catch (Exception $e) {
            error_log("SecurityManagerModel: Failed to block IP - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Débloque une adresse IP
     */
    public function unblockIP($ip) {
        try {
            if ($this->securityManager) {
                return $this->securityManager->unblockIP($ip);
            }
            
            // Implémentation basique si SecurityManager n'est pas disponible
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "DELETE FROM {$prefix}blocked_ips WHERE ip_address = ?";
            $this->db->execute($sql, [$ip]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("SecurityManagerModel: Failed to unblock IP - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retourne les paramètres de sécurité
     */
    public function getSecuritySettings() {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            $sql = "SELECT config_key, config_value FROM {$prefix}security_config";
            $results = $this->db->fetchAll($sql);
            
            $settings = [];
            foreach ($results as $row) {
                $settings[$row['config_key']] = $row['config_value'];
            }
            
            // Valeurs par défaut si aucune configuration
            if (empty($settings)) {
                $settings = [
                    'security_login_attempts_max' => '5',
                    'security_lockout_duration' => '900',
                    'security_session_timeout' => '3600',
                    'security_password_min_length' => '8',
                    'security_password_complexity' => '1',
                    'security_audit_logging' => '1'
                ];
            }
            
            return $settings;
            
        } catch (Exception $e) {
            error_log("SecurityManagerModel: Failed to get security settings - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Met à jour les paramètres de sécurité
     */
    public function updateSecuritySettings($settings) {
        try {
            $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
            
            foreach ($settings as $key => $value) {
                $sql = "INSERT INTO {$prefix}security_config (config_key, config_value) 
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)";
                
                $this->db->execute($sql, [$key, $value]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("SecurityManagerModel: Failed to update security settings - " . $e->getMessage());
            return false;
        }
    }
}