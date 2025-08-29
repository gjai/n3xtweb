# Instructions personnalisées pour la gestion des widgets N3XT WEB

## Vue d'ensemble

Ce document fournit les instructions détaillées pour la gestion, l'intégration et le développement des widgets dans le système modulaire N3XT WEB.

## Structure des widgets

### Hiérarchie des fichiers

```
modules/
├── BaseWidget.php                     # Classe de base pour tous les widgets
├── {Module}/
│   ├── widgets/
│   │   └── {WidgetName}.php           # Classe du widget
│   ├── views/widgets/
│   │   └── {widgetname}.php           # Vue du widget (nom en minuscules)
│   └── README.md                      # Documentation du module
└── loader.php                         # Chargeur de modules et widgets
```

### Nomenclature

- **Classe widget** : PascalCase (ex: `InstallStatusWidget`)
- **Fichier classe** : PascalCase (ex: `InstallStatusWidget.php`)
- **Vue widget** : lowercase (ex: `installstatuswidget.php`)
- **Module** : PascalCase (ex: `SecurityManager`)

## Développement d'un widget

### 1. Création de la classe widget

```php
<?php
/**
 * N3XT WEB - Mon Widget Personnalisé
 * 
 * Description du widget et de ses fonctionnalités.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

// Charger la classe de base
require_once __DIR__ . '/../../BaseWidget.php';

class MonWidgetPersonnalise extends BaseWidget {
    
    public function __construct() {
        parent::__construct('MonWidgetPersonnalise', 'MonModule');
    }
    
    /**
     * Configuration par défaut du widget
     */
    protected function getDefaultConfiguration() {
        return [
            'enabled' => true,
            'title' => 'Mon widget',
            'description' => 'Description de mon widget',
            'refresh_interval' => 300,
            // Autres paramètres...
        ];
    }
    
    /**
     * Génère les données du widget
     */
    public function getData() {
        return [
            'data' => $this->fetchData(),
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Méthodes privées pour récupérer les données
     */
    private function fetchData() {
        // Logique de récupération des données
        return [];
    }
    
    /**
     * Rendu HTML du widget
     */
    public function render() {
        $data = $this->getData();
        return $this->loadView($data);
    }
}
```

### 2. Création de la vue

```php
<?php
/**
 * N3XT WEB - Vue de Mon Widget Personnalisé
 * Vue pour mon widget personnalisé
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}
?>

<div class="widget mon-widget-personnalise">
    <div class="widget-header">
        <h3><i class="fas fa-icon"></i> <?= htmlspecialchars($this->getConfig('title')) ?></h3>
        <span class="widget-refresh" title="Dernière mise à jour: <?= $last_updated ?>">
            <i class="fas fa-sync-alt"></i>
        </span>
    </div>
    
    <div class="widget-content">
        <!-- Contenu du widget -->
    </div>
</div>

<style>
/* Styles CSS spécifiques au widget */
.mon-widget-personnalise {
    /* Styles... */
}
</style>

<script>
// JavaScript spécifique au widget
</script>
```

## Configuration des widgets

### Paramètres de base

Chaque widget doit définir ces paramètres minimum :

```php
protected function getDefaultConfiguration() {
    return [
        'enabled' => true,                    // Activer/désactiver le widget
        'title' => 'Titre du widget',        // Titre affiché
        'description' => 'Description...',   // Description fonctionnelle
        'refresh_interval' => 300,           // Intervalle de rafraîchissement (secondes)
        // Paramètres spécifiques...
    ];
}
```

### Paramètres avancés

```php
protected function getDefaultConfiguration() {
    return [
        // Paramètres de base
        'enabled' => true,
        'title' => 'Mon Widget',
        'description' => 'Description du widget',
        'refresh_interval' => 300,
        
        // Affichage
        'show_header' => true,
        'show_footer' => false,
        'compact_mode' => false,
        
        // Données
        'max_items' => 10,
        'sort_order' => 'desc',
        'date_format' => 'Y-m-d H:i:s',
        
        // Filtres
        'show_categories' => ['all'],
        'priority_levels' => ['high', 'medium', 'low'],
        
        // Interactions
        'auto_refresh' => true,
        'allow_export' => true,
        'show_actions' => true,
        
        // Sécurité
        'require_permission' => 'view_widget',
        'log_access' => false
    ];
}
```

## Intégration des widgets

### 1. Enregistrement dans le loader

```php
// Dans modules/loader.php
self::loadModuleWithWidgets('MonModule', ['MonWidgetPersonnalise']);
```

### 2. Utilisation dans une page

```php
<?php
// Chargement du widget
try {
    $widget = ModulesLoader::getWidget('MonModule', 'MonWidgetPersonnalise');
    echo $widget->render();
} catch (Exception $e) {
    echo '<div class="widget-error">Erreur: ' . $e->getMessage() . '</div>';
}
?>
```

### 3. Intégration AJAX

```javascript
function refreshWidget(moduleName, widgetName, containerId) {
    fetch('/api/widgets/' + moduleName + '/' + widgetName)
        .then(response => response.text())
        .then(html => {
            document.getElementById(containerId).innerHTML = html;
        })
        .catch(error => {
            console.error('Erreur de rafraîchissement:', error);
        });
}

// Auto-refresh
setInterval(() => {
    refreshWidget('MonModule', 'MonWidgetPersonnalise', 'widget-container');
}, 300000); // 5 minutes
```

## Bonnes pratiques

### Sécurité

1. **Validation des entrées**
```php
// Utiliser les méthodes de sanitization
$cleanInput = $this->sanitizeInput($userInput, 'string');
$cleanNumber = $this->sanitizeInput($userNumber, 'int');
```

2. **Protection d'accès direct**
```php
// Toujours ajouter en début de fichier
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}
```

3. **Échappement des données**
```php
// Dans les vues
echo htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
```

### Performance

1. **Cache des données**
```php
public function getData() {
    $cacheKey = 'widget_' . $this->widgetName . '_data';
    $data = Cache::get($cacheKey);
    
    if ($data === null) {
        $data = $this->fetchData();
        Cache::set($cacheKey, $data, 300); // 5 minutes
    }
    
    return $data;
}
```

2. **Lazy loading**
```php
// Charger les données uniquement si nécessaire
public function getData() {
    if (!$this->getConfig('enabled', true)) {
        return ['disabled' => true];
    }
    
    return $this->fetchData();
}
```

3. **Pagination**
```php
private function fetchData($page = 1, $limit = null) {
    $limit = $limit ?: $this->getConfig('max_items', 10);
    $offset = ($page - 1) * $limit;
    
    // Requête avec LIMIT et OFFSET
    return $this->db->fetchAll($sql, $params, $limit, $offset);
}
```

### Interface utilisateur

1. **Responsive design**
```css
.mon-widget {
    display: grid;
    grid-template-columns: 1fr;
}

@media (min-width: 768px) {
    .mon-widget {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .mon-widget {
        grid-template-columns: repeat(3, 1fr);
    }
}
```

2. **États de chargement**
```javascript
function showLoading(containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '<div class="widget-loading"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>';
}
```

3. **Gestion d'erreurs**
```php
protected function renderFallback($data = []) {
    return '<div class="widget-error">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Impossible de charger le widget</p>
                <button onclick="location.reload()">Réessayer</button>
            </div>';
}
```

## API et hooks

### Hooks disponibles

```php
// Avant le rendu du widget
add_hook('widget_before_render', function($widgetName, $data) {
    // Modifier les données avant affichage
    return $data;
});

// Après le rendu du widget
add_hook('widget_after_render', function($widgetName, $html) {
    // Modifier le HTML généré
    return $html;
});

// Configuration chargée
add_hook('widget_config_loaded', function($widgetName, $config) {
    // Modifier la configuration
    return $config;
});
```

### API REST

```php
// GET /api/widgets/{module}/{widget}
// Récupère le HTML du widget

// GET /api/widgets/{module}/{widget}/data
// Récupère les données JSON du widget

// POST /api/widgets/{module}/{widget}/config
// Met à jour la configuration du widget
```

## Dépannage

### Problèmes courants

1. **Widget ne s'affiche pas**
   - Vérifier le nom du fichier de vue (lowercase)
   - Contrôler les permissions de fichiers
   - Vérifier les erreurs PHP dans les logs

2. **Données non mises à jour**
   - Vider le cache du widget
   - Vérifier la méthode getData()
   - Contrôler la connexion base de données

3. **Erreurs JavaScript**
   - Valider la syntaxe dans la console
   - Vérifier les conflits de librairies
   - Contrôler l'ordre de chargement des scripts

### Debug et logging

```php
// Dans la classe widget
protected function logDebug($message, $data = null) {
    if ($this->getConfig('debug_mode', false)) {
        Logger::log("Widget {$this->widgetName}: {$message}", LOG_LEVEL_DEBUG, $data);
    }
}

// Utilisation
$this->logDebug('Début de récupération des données');
$data = $this->fetchData();
$this->logDebug('Données récupérées', ['count' => count($data)]);
```

## Migration et versioning

### Versioning des widgets

```php
// Dans la classe widget
public function getVersion() {
    return '1.2.0';
}

public function getRequiredVersion() {
    return '1.0.0'; // Version minimum du système
}
```

### Migration de configuration

```php
public function migrateConfig($oldVersion, $newVersion) {
    $config = $this->config;
    
    if (version_compare($oldVersion, '1.1.0', '<')) {
        // Migration de 1.0.x vers 1.1.0
        $config['new_feature'] = true;
    }
    
    return $config;
}
```

## Tests et qualité

### Tests unitaires

```php
class MonWidgetTest extends PHPUnit\Framework\TestCase {
    
    protected $widget;
    
    protected function setUp(): void {
        $this->widget = new MonWidgetPersonnalise();
    }
    
    public function testGetData() {
        $data = $this->widget->getData();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('last_updated', $data);
    }
    
    public function testRender() {
        $html = $this->widget->render();
        $this->assertStringContains('widget', $html);
        $this->assertStringContains('widget-header', $html);
    }
}
```

### Validation de code

```bash
# PHP CodeSniffer
phpcs --standard=PSR2 modules/MonModule/widgets/

# PHP Mess Detector
phpmd modules/MonModule/widgets/ text cleancode,codesize,controversial,design,naming,unusedcode
```

Cette documentation fournit un guide complet pour développer, intégrer et maintenir les widgets dans le système N3XT WEB. Respecter ces instructions garantit une cohérence et une qualité élevée du code.