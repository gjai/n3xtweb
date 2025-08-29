<?php
/**
 * N3XT WEB - Base Widget Class
 * 
 * Classe de base pour tous les widgets des modules N3XT WEB.
 * Fournit les fonctionnalités communes pour l'affichage et la gestion des widgets.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

abstract class BaseWidget {
    
    protected $widgetName;
    protected $moduleName;
    protected $config = [];
    protected $db;
    
    /**
     * Constructeur
     */
    public function __construct($widgetName, $moduleName) {
        $this->widgetName = $widgetName;
        $this->moduleName = $moduleName;
        $this->db = Database::getInstance();
        $this->loadConfiguration();
    }
    
    /**
     * Charge la configuration du widget
     */
    protected function loadConfiguration() {
        try {
            $this->config = $this->getDefaultConfiguration();
            // Possibilité d'override via la configuration module
            if (class_exists('Configuration')) {
                $moduleConfig = Configuration::getCategory($this->moduleName);
                if (isset($moduleConfig['widgets'][$this->widgetName])) {
                    $this->config = array_merge($this->config, $moduleConfig['widgets'][$this->widgetName]);
                }
            }
        } catch (Exception $e) {
            // Utiliser la configuration par défaut en cas d'erreur
            $this->config = $this->getDefaultConfiguration();
        }
    }
    
    /**
     * Retourne la configuration par défaut du widget
     * Doit être implémentée par chaque widget
     */
    abstract protected function getDefaultConfiguration();
    
    /**
     * Obtient une valeur de configuration
     */
    protected function getConfig($key, $default = null) {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }
    
    /**
     * Génère les données du widget
     * Doit être implémentée par chaque widget
     */
    abstract public function getData();
    
    /**
     * Rendu HTML du widget
     * Doit être implémentée par chaque widget
     */
    abstract public function render();
    
    /**
     * Retourne le chemin de la vue du widget
     */
    protected function getViewPath() {
        return __DIR__ . "/{$this->moduleName}/views/widgets/" . strtolower($this->widgetName) . ".php";
    }
    
    /**
     * Charge et exécute la vue du widget
     */
    protected function loadView($data = []) {
        $viewPath = $this->getViewPath();
        
        if (file_exists($viewPath)) {
            // Extraire les variables pour la vue
            extract($data);
            
            // Capturer le contenu
            ob_start();
            include $viewPath;
            return ob_get_clean();
        }
        
        return $this->renderFallback($data);
    }
    
    /**
     * Rendu de secours si la vue n'existe pas
     */
    protected function renderFallback($data = []) {
        return '<div class="widget-error">Widget view not found: ' . $this->widgetName . '</div>';
    }
    
    /**
     * Retourne le nom du widget
     */
    public function getWidgetName() {
        return $this->widgetName;
    }
    
    /**
     * Retourne le nom du module
     */
    public function getModuleName() {
        return $this->moduleName;
    }
    
    /**
     * Retourne les informations sur le widget
     */
    public function getWidgetInfo() {
        return [
            'name' => $this->widgetName,
            'module' => $this->moduleName,
            'enabled' => $this->getConfig('enabled', true),
            'title' => $this->getConfig('title', $this->widgetName),
            'description' => $this->getConfig('description', ''),
            'refresh_interval' => $this->getConfig('refresh_interval', 300) // 5 minutes par défaut
        ];
    }
    
    /**
     * Sanitise les données d'entrée
     */
    protected function sanitizeInput($input, $type = 'string') {
        if (class_exists('Security')) {
            return Security::sanitizeInput($input, $type);
        }
        
        // Fallback basique
        switch ($type) {
            case 'int':
                return (int) $input;
            case 'float':
                return (float) $input;
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            default:
                return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
        }
    }
}