<?php
/**
 * N3XT WEB - Theme Preview Widget
 * 
 * Widget pour afficher un aperçu et la gestion des thèmes.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

// Charger la classe de base
require_once __DIR__ . '/../BaseWidget.php';

class ThemePreviewWidget extends BaseWidget {
    
    public function __construct() {
        parent::__construct('ThemePreviewWidget', 'Theme');
    }
    
    /**
     * Configuration par défaut du widget
     */
    protected function getDefaultConfiguration() {
        return [
            'enabled' => true,
            'title' => 'Aperçu du thème',
            'description' => 'Affiche l\'aperçu du thème actuel et permet la gestion des thèmes',
            'refresh_interval' => 600,
            'show_theme_info' => true,
            'show_customization' => true,
            'max_recent_themes' => 5
        ];
    }
    
    /**
     * Génère les données du widget
     */
    public function getData() {
        $data = [
            'current_theme' => $this->getCurrentTheme(),
            'available_themes' => $this->getAvailableThemes(),
            'recent_changes' => $this->getRecentThemeChanges(),
            'customization_options' => $this->getCustomizationOptions(),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        return $data;
    }
    
    /**
     * Obtient les informations du thème actuel
     */
    private function getCurrentTheme() {
        $theme = [
            'name' => 'Default N3XT',
            'version' => '1.0.0',
            'author' => 'N3XT Communication',
            'description' => 'Thème par défaut de N3XT WEB',
            'screenshot' => null,
            'colors' => [
                'primary' => '#007cba',
                'secondary' => '#6c757d',
                'success' => '#28a745',
                'warning' => '#ffc107',
                'danger' => '#dc3545'
            ],
            'features' => [
                'responsive' => true,
                'dark_mode' => false,
                'customizable' => true
            ]
        ];
        
        try {
            // Tenter de charger le thème depuis la configuration
            if ($this->db) {
                $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
                $result = $this->db->fetchOne("SELECT config_value FROM {$prefix}configuration WHERE config_key = 'current_theme'");
                if ($result) {
                    $themeData = json_decode($result['config_value'], true);
                    if ($themeData) {
                        $theme = array_merge($theme, $themeData);
                    }
                }
            }
        } catch (Exception $e) {
            // Utiliser le thème par défaut en cas d'erreur
        }
        
        return $theme;
    }
    
    /**
     * Obtient la liste des thèmes disponibles
     */
    private function getAvailableThemes() {
        $themes = [
            'default' => [
                'name' => 'Default N3XT',
                'description' => 'Thème par défaut élégant et professionnel',
                'preview' => '/assets/themes/default/preview.jpg',
                'active' => true
            ],
            'dark' => [
                'name' => 'Dark Theme',
                'description' => 'Thème sombre pour réduire la fatigue oculaire',
                'preview' => '/assets/themes/dark/preview.jpg',
                'active' => false
            ],
            'minimal' => [
                'name' => 'Minimal',
                'description' => 'Design minimaliste et épuré',
                'preview' => '/assets/themes/minimal/preview.jpg',
                'active' => false
            ]
        ];
        
        // Vérifier les thèmes disponibles dans le système de fichiers
        $themesPath = __DIR__ . '/../../assets/themes/';
        if (is_dir($themesPath)) {
            $dirs = array_filter(glob($themesPath . '*'), 'is_dir');
            foreach ($dirs as $dir) {
                $themeName = basename($dir);
                if (!isset($themes[$themeName])) {
                    $themeConfig = $dir . '/theme.json';
                    if (file_exists($themeConfig)) {
                        $config = json_decode(file_get_contents($themeConfig), true);
                        if ($config) {
                            $themes[$themeName] = [
                                'name' => $config['name'] ?? $themeName,
                                'description' => $config['description'] ?? 'Thème personnalisé',
                                'preview' => '/assets/themes/' . $themeName . '/preview.jpg',
                                'active' => false
                            ];
                        }
                    }
                }
            }
        }
        
        return $themes;
    }
    
    /**
     * Obtient les changements récents de thème
     */
    private function getRecentThemeChanges() {
        $changes = [];
        
        try {
            if ($this->db) {
                $prefix = defined('TABLE_PREFIX') ? TABLE_PREFIX : 'n3xtweb_';
                $sql = "SELECT * FROM {$prefix}theme_changes 
                       ORDER BY changed_at DESC 
                       LIMIT " . $this->getConfig('max_recent_themes', 5);
                       
                $changes = $this->db->fetchAll($sql);
            }
        } catch (Exception $e) {
            // Ajouter quelques changements fictifs pour la démonstration
            $changes = [
                [
                    'theme_name' => 'Default N3XT',
                    'action' => 'activated',
                    'changed_by' => 'admin',
                    'changed_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
                ],
                [
                    'theme_name' => 'Dark Theme',
                    'action' => 'installed',
                    'changed_by' => 'admin',
                    'changed_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
                ]
            ];
        }
        
        return $changes;
    }
    
    /**
     * Obtient les options de personnalisation
     */
    private function getCustomizationOptions() {
        return [
            'logo' => [
                'name' => 'Logo du site',
                'type' => 'file',
                'value' => '/assets/images/logo.png',
                'description' => 'Logo affiché dans l\'en-tête'
            ],
            'primary_color' => [
                'name' => 'Couleur principale',
                'type' => 'color',
                'value' => '#007cba',
                'description' => 'Couleur principale du thème'
            ],
            'secondary_color' => [
                'name' => 'Couleur secondaire',
                'type' => 'color',
                'value' => '#6c757d',
                'description' => 'Couleur secondaire du thème'
            ],
            'font_family' => [
                'name' => 'Police de caractères',
                'type' => 'select',
                'value' => 'Inter',
                'options' => ['Inter', 'Arial', 'Helvetica', 'Georgia', 'Times'],
                'description' => 'Police principale du site'
            ],
            'sidebar_position' => [
                'name' => 'Position de la barre latérale',
                'type' => 'select',
                'value' => 'left',
                'options' => ['left', 'right'],
                'description' => 'Position de la barre latérale'
            ],
            'show_breadcrumbs' => [
                'name' => 'Afficher le fil d\'Ariane',
                'type' => 'checkbox',
                'value' => true,
                'description' => 'Afficher la navigation en fil d\'Ariane'
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