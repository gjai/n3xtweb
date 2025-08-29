<?php
/**
 * N3XT WEB - Security Manager Controller
 * 
 * Contrôleur pour la gestion des fonctionnalités de sécurité.
 * Gère les requêtes HTTP et orchestre les opérations de sécurité.
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

require_once __DIR__ . '/model.php';
require_once __DIR__ . '/../BaseModule.php';

class SecurityManagerController extends BaseModule {
    
    private $model;
    
    /**
     * Constructeur
     */
    public function __construct() {
        parent::__construct('SecurityManager');
        $this->model = new SecurityManagerModel();
    }
    
    /**
     * Configuration par défaut du module
     */
    protected function getDefaultConfiguration() {
        return [
            'enabled' => true,
            'security_scan_interval' => 3600,
            'max_alerts_display' => 10,
            'auto_ip_blocking' => true,
            'alert_notifications' => true,
            'log_level' => LOG_LEVEL_INFO
        ];
    }
    
    /**
     * Traite une requête de sécurité
     */
    public function handleRequest() {
        if (!$this->checkPermissions()) {
            $this->logAction('Access denied to SecurityManager', '', LOG_LEVEL_WARNING);
            return $this->jsonResponse(['error' => 'Accès refusé'], 403);
        }
        
        $action = $this->sanitizeInput($_GET['action'] ?? 'dashboard', 'string');
        
        switch ($action) {
            case 'dashboard':
                return $this->getDashboard();
                
            case 'alerts':
                return $this->getAlerts();
                
            case 'scan':
                return $this->performScan();
                
            case 'block_ip':
                return $this->blockIP();
                
            case 'unblock_ip':
                return $this->unblockIP();
                
            case 'settings':
                return $this->getSettings();
                
            case 'update_settings':
                return $this->updateSettings();
                
            default:
                return $this->jsonResponse(['error' => 'Action non reconnue'], 400);
        }
    }
    
    /**
     * Retourne le tableau de bord de sécurité
     */
    private function getDashboard() {
        try {
            $data = [
                'alerts' => $this->model->getActiveAlerts(),
                'threat_level' => $this->model->getCurrentThreatLevel(),
                'blocked_ips' => $this->model->getBlockedIPsCount(),
                'recent_activities' => $this->model->getRecentSecurityActivities(),
                'scan_status' => $this->model->getLastScanStatus()
            ];
            
            $this->logAction('Security dashboard accessed');
            return $this->jsonResponse(['success' => true, 'data' => $data]);
            
        } catch (Exception $e) {
            $this->logAction('Failed to load security dashboard', $e->getMessage(), LOG_LEVEL_ERROR);
            return $this->jsonResponse(['error' => 'Erreur lors du chargement'], 500);
        }
    }
    
    /**
     * Retourne les alertes de sécurité
     */
    private function getAlerts() {
        try {
            $limit = $this->sanitizeInput($_GET['limit'] ?? 10, 'int');
            $level = $this->sanitizeInput($_GET['level'] ?? 'all', 'string');
            
            $alerts = $this->model->getAlerts($limit, $level);
            
            $this->logAction("Security alerts retrieved (limit: $limit, level: $level)");
            return $this->jsonResponse(['success' => true, 'alerts' => $alerts]);
            
        } catch (Exception $e) {
            $this->logAction('Failed to retrieve alerts', $e->getMessage(), LOG_LEVEL_ERROR);
            return $this->jsonResponse(['error' => 'Erreur lors de la récupération des alertes'], 500);
        }
    }
    
    /**
     * Lance un scan de sécurité
     */
    private function performScan() {
        if (!$this->validateCSRF($_POST['csrf_token'] ?? '')) {
            return $this->jsonResponse(['error' => 'Token CSRF invalide'], 403);
        }
        
        try {
            $scanResults = $this->model->performSecurityScan();
            
            $this->logAction('Security scan performed', "Threat level: {$scanResults['threat_level']}", LOG_LEVEL_INFO);
            return $this->jsonResponse(['success' => true, 'results' => $scanResults]);
            
        } catch (Exception $e) {
            $this->logAction('Security scan failed', $e->getMessage(), LOG_LEVEL_ERROR);
            return $this->jsonResponse(['error' => 'Erreur lors du scan de sécurité'], 500);
        }
    }
    
    /**
     * Bloque une adresse IP
     */
    private function blockIP() {
        if (!$this->validateCSRF($_POST['csrf_token'] ?? '')) {
            return $this->jsonResponse(['error' => 'Token CSRF invalide'], 403);
        }
        
        try {
            $ip = $this->sanitizeInput($_POST['ip'] ?? '', 'string');
            $reason = $this->sanitizeInput($_POST['reason'] ?? '', 'string');
            $duration = $this->sanitizeInput($_POST['duration'] ?? 3600, 'int');
            
            if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
                return $this->jsonResponse(['error' => 'Adresse IP invalide'], 400);
            }
            
            $success = $this->model->blockIP($ip, $reason, $duration);
            
            if ($success) {
                $this->logAction("IP blocked manually: $ip", "Reason: $reason, Duration: {$duration}s", LOG_LEVEL_WARNING);
                return $this->jsonResponse(['success' => true, 'message' => 'IP bloquée avec succès']);
            } else {
                return $this->jsonResponse(['error' => 'Erreur lors du blocage de l\'IP'], 500);
            }
            
        } catch (Exception $e) {
            $this->logAction('Failed to block IP', $e->getMessage(), LOG_LEVEL_ERROR);
            return $this->jsonResponse(['error' => 'Erreur lors du blocage'], 500);
        }
    }
    
    /**
     * Débloque une adresse IP
     */
    private function unblockIP() {
        if (!$this->validateCSRF($_POST['csrf_token'] ?? '')) {
            return $this->jsonResponse(['error' => 'Token CSRF invalide'], 403);
        }
        
        try {
            $ip = $this->sanitizeInput($_POST['ip'] ?? '', 'string');
            
            if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
                return $this->jsonResponse(['error' => 'Adresse IP invalide'], 400);
            }
            
            $success = $this->model->unblockIP($ip);
            
            if ($success) {
                $this->logAction("IP unblocked manually: $ip", '', LOG_LEVEL_INFO);
                return $this->jsonResponse(['success' => true, 'message' => 'IP débloquée avec succès']);
            } else {
                return $this->jsonResponse(['error' => 'Erreur lors du déblocage de l\'IP'], 500);
            }
            
        } catch (Exception $e) {
            $this->logAction('Failed to unblock IP', $e->getMessage(), LOG_LEVEL_ERROR);
            return $this->jsonResponse(['error' => 'Erreur lors du déblocage'], 500);
        }
    }
    
    /**
     * Retourne les paramètres de sécurité
     */
    private function getSettings() {
        try {
            $settings = $this->model->getSecuritySettings();
            
            $this->logAction('Security settings accessed');
            return $this->jsonResponse(['success' => true, 'settings' => $settings]);
            
        } catch (Exception $e) {
            $this->logAction('Failed to retrieve settings', $e->getMessage(), LOG_LEVEL_ERROR);
            return $this->jsonResponse(['error' => 'Erreur lors de la récupération des paramètres'], 500);
        }
    }
    
    /**
     * Met à jour les paramètres de sécurité
     */
    private function updateSettings() {
        if (!$this->validateCSRF($_POST['csrf_token'] ?? '')) {
            return $this->jsonResponse(['error' => 'Token CSRF invalide'], 403);
        }
        
        try {
            $settings = $this->sanitizeInput($_POST['settings'] ?? [], 'array');
            
            $success = $this->model->updateSecuritySettings($settings);
            
            if ($success) {
                $this->logAction('Security settings updated', json_encode($settings), LOG_LEVEL_INFO);
                return $this->jsonResponse(['success' => true, 'message' => 'Paramètres mis à jour avec succès']);
            } else {
                return $this->jsonResponse(['error' => 'Erreur lors de la mise à jour'], 500);
            }
            
        } catch (Exception $e) {
            $this->logAction('Failed to update settings', $e->getMessage(), LOG_LEVEL_ERROR);
            return $this->jsonResponse(['error' => 'Erreur lors de la mise à jour des paramètres'], 500);
        }
    }
    
    /**
     * Retourne une réponse JSON
     */
    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        return json_encode($data);
    }
}