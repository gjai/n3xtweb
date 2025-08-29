<?php
/**
 * N3XT WEB - Base Module Class
 * 
 * Classe de base pour tous les modules du back office N3XT WEB.
 * Fournit les fonctionnalités communes : configuration, logging, sécurité.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

abstract class BaseModule {
    
    protected $moduleName;
    protected $moduleConfig = [];
    protected $db;
    
    /**
     * Constructeur
     */
    public function __construct($moduleName) {
        $this->moduleName = $moduleName;
        $this->db = Database::getInstance();
        $this->loadConfiguration();
    }
    
    /**
     * Charge la configuration du module depuis la base de données
     */
    protected function loadConfiguration() {
        try {
            $prefix = $this->moduleName . '_';
            $this->moduleConfig = Configuration::getCategory($this->moduleName);
        } catch (Exception $e) {
            Logger::log("Failed to load configuration for module {$this->moduleName}: " . $e->getMessage(), LOG_LEVEL_ERROR);
            $this->moduleConfig = $this->getDefaultConfiguration();
        }
    }
    
    /**
     * Retourne la configuration par défaut du module
     * Doit être implémentée par chaque module
     */
    abstract protected function getDefaultConfiguration();
    
    /**
     * Obtient une valeur de configuration
     */
    protected function getConfig($key, $default = null) {
        $fullKey = $this->moduleName . '_' . $key;
        return Configuration::get($fullKey, $default);
    }
    
    /**
     * Définit une valeur de configuration
     */
    protected function setConfig($key, $value) {
        $fullKey = $this->moduleName . '_' . $key;
        return Configuration::set($fullKey, $value);
    }
    
    /**
     * Vérifie les permissions d'accès au module
     */
    protected function checkPermissions() {
        if (!Session::isLoggedIn()) {
            throw new Exception('Access denied: Authentication required');
        }
        
        // Vérification additionnelle si le module nécessite des permissions spéciales
        if (method_exists($this, 'checkModulePermissions')) {
            return $this->checkModulePermissions();
        }
        
        return true;
    }
    
    /**
     * Log une action du module
     */
    protected function logAction($action, $details = '', $level = LOG_LEVEL_INFO) {
        $message = "[{$this->moduleName}] {$action}";
        if (!empty($details)) {
            $message .= " - {$details}";
        }
        
        Logger::log($message, $level, $this->moduleName);
        
        // Log dans la base de données pour les actions importantes
        if ($level <= LOG_LEVEL_WARNING) {
            Logger::logAccess($_SESSION['admin_username'] ?? 'system', true, $message);
        }
    }
    
    /**
     * Valide le token CSRF
     */
    protected function validateCSRF($token) {
        return Security::validateCSRFToken($token);
    }
    
    /**
     * Nettoie et valide les données d'entrée
     */
    protected function sanitizeInput($input, $type = 'string') {
        return Security::sanitizeInput($input, $type);
    }
    
    /**
     * Initialise le module
     * Doit être implémentée par chaque module
     */
    abstract public function initialize();
    
    /**
     * Retourne le nom du module
     */
    public function getModuleName() {
        return $this->moduleName;
    }
    
    /**
     * Retourne le statut du module
     */
    public function getStatus() {
        return $this->getConfig('enabled', false) ? 'enabled' : 'disabled';
    }
    
    /**
     * Active le module
     */
    public function enable() {
        $this->setConfig('enabled', true);
        $this->logAction('Module enabled');
        return true;
    }
    
    /**
     * Désactive le module
     */
    public function disable() {
        $this->setConfig('enabled', false);
        $this->logAction('Module disabled');
        return true;
    }
    
    /**
     * Retourne les informations sur le module
     */
    public function getModuleInfo() {
        return [
            'name' => $this->moduleName,
            'status' => $this->getStatus(),
            'version' => $this->getConfig('version', '1.0.0'),
            'description' => $this->getConfig('description', ''),
            'last_action' => $this->getConfig('last_action', null),
            'last_update' => $this->getConfig('last_update', null)
        ];
    }
}