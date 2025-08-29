# Theme Module - N3XT WEB

## Vue d'ensemble

Le module Theme fournit un système complet de gestion des thèmes et de personnalisation visuelle pour le back office N3XT WEB. Il permet de gérer l'apparence du système, de prévisualiser les thèmes disponibles et de personnaliser l'interface utilisateur selon les préférences administrateur.

## Fonctionnalités

### 🎨 Gestion complète des thèmes
- Catalogue de thèmes pré-installés avec prévisualisations
- Support de thèmes personnalisés avec import/export
- Activation instantanée avec prévisualisation temps réel
- Système de versioning et compatibilité automatique

### ⚙️ Personnalisation avancée de l'interface
- Customisation des couleurs principales et d'accent
- Gestion des polices et typographie avec prévisualisation
- Configuration du layout (sidebar, breadcrumbs, densité)
- Mode sombre/clair avec basculement automatique

### 📱 Design responsive et accessibilité
- Interface adaptative pour tous les écrans (mobile, tablette, desktop)
- Respect des standards d'accessibilité WCAG 2.1 niveau AA
- Support complet des navigateurs modernes
- Optimisation des performances avec cache intelligent

### 🔧 Système de développement extensible
- API complète pour création de thèmes personnalisés
- Hooks et filtres pour extension par modules
- Documentation développeur avec exemples pratiques
- Validation automatique de l'intégrité des thèmes

## Configuration

### Paramètres disponibles

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| `enabled` | Active/désactive le module | `true` |
| `current_theme` | Identifiant du thème actuel | `default` |
| `allow_custom_themes` | Autorise les thèmes personnalisés | `true` |
| `cache_enabled` | Cache des CSS compilés | `true` |
| `auto_backup` | Sauvegarde auto des personnalisations | `true` |
| `performance_mode` | Mode performance (minification) | `true` |
| `dark_mode_enabled` | Support du mode sombre | `true` |

### Configuration via interface admin

```php
// Accès au module
$themeManager = new ThemeManager();

// Changer de thème
$themeManager->activateTheme('dark_theme');

// Personnaliser les couleurs
$themeManager->setCustomization([
    'primary_color' => '#007cba',
    'secondary_color' => '#6c757d',
    'sidebar_position' => 'left'
]);
```

## Administration

**Interface disponible :** `/bo/themes.php`

### Tableau de bord
- Aperçu du thème actuel avec informations détaillées
- Galerie des thèmes disponibles avec prévisualisations
- Options de personnalisation rapide avec aperçu temps réel
- Historique des changements récents avec possibilité de rollback

### Actions disponibles
- Activation instantanée de thèmes avec prévisualisation
- Personnalisation complète des couleurs et typographie
- Import/export de thèmes et configurations personnalisées
- Gestion du cache et optimisation des performances

## Schema de base de données

### Table `theme_config`

```sql
CREATE TABLE n3xt_theme_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    theme_id VARCHAR(50) NOT NULL,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT NOT NULL,
    is_custom BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_theme_key (theme_id, config_key)
);
```

### Table `theme_history`

```sql
CREATE TABLE n3xt_theme_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    theme_id VARCHAR(50) NOT NULL,
    action ENUM('activated', 'deactivated', 'customized', 'imported', 'exported') NOT NULL,
    previous_config JSON NULL,
    new_config JSON NULL,
    changed_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Intégration

### Avec les autres modules

**Dashboard :** Application cohérente du thème
- Application automatique du thème sélectionné à toute l'interface
- Respect des personnalisations dans tous les widgets
- Synchronisation des couleurs et styles globaux

**SecurityManager :** Validation et sécurité des thèmes
- Vérification de l'intégrité des fichiers de thème
- Scanning antimalware des ressources uploadées
- Protection contre l'injection CSS malveillante

**NotificationManager :** Notifications des changements
- Notifications de changement de thème réussi/échoué
- Alertes de problèmes de compatibilité détectés
- Rapports de performance post-changement

### API et hooks

Le module expose les méthodes suivantes pour intégration :
- `activateTheme($themeId)` : Active un thème spécifique
- `getCurrentTheme()` : Retourne le thème actuellement actif
- `setCustomization($options)` : Applique des personnalisations

## Exemple d'utilisation

### Gestion basique des thèmes

```php
$themeManager = new ThemeManager();

// Obtenir la liste des thèmes disponibles
$availableThemes = $themeManager->getAvailableThemes();

foreach ($availableThemes as $theme) {
    echo "Thème: {$theme['name']} - Version: {$theme['version']}\n";
    echo "Auteur: {$theme['author']}\n";
    echo "Description: {$theme['description']}\n";
    echo "---\n";
}

// Activer un thème spécifique
$result = $themeManager->activateTheme('dark_theme');
if ($result['success']) {
    echo "Thème activé avec succès\n";
} else {
    echo "Erreur: " . $result['error'] . "\n";
}
```

### Personnalisation avancée

```php
// Appliquer des personnalisations
$customizations = [
    'colors' => [
        'primary' => '#007cba',
        'secondary' => '#6c757d',
        'success' => '#28a745',
        'warning' => '#ffc107',
        'danger' => '#dc3545'
    ],
    'typography' => [
        'font_family' => 'Roboto, Arial, sans-serif',
        'font_size_base' => '14px',
        'font_weight_base' => '400'
    ],
    'layout' => [
        'sidebar_position' => 'left',
        'sidebar_width' => 'normal',
        'enable_breadcrumbs' => true,
        'compact_mode' => false
    ]
];

$result = $themeManager->setCustomization($customizations);
if ($result['success']) {
    echo "Personnalisations appliquées\n";
    
    // Sauvegarder la configuration
    $themeManager->saveCustomization();
}
```

### Import/Export de thèmes

```php
// Exporter la configuration actuelle
$exportResult = $themeManager->exportThemeConfig('my_custom_theme');
if ($exportResult['success']) {
    echo "Configuration exportée: " . $exportResult['file_path'] . "\n";
}

// Importer un thème personnalisé
$importResult = $themeManager->importTheme('/path/to/theme.zip');
if ($importResult['success']) {
    echo "Thème importé: " . $importResult['theme_id'] . "\n";
    
    // Activer le thème importé
    $themeManager->activateTheme($importResult['theme_id']);
}
```

## Principes communs

### Sécurité
- Protection CSRF sur toutes les actions de modification de thème
- Validation stricte des fichiers de thème uploadés
- Scanning antimalware des ressources avec quarantaine
- Vérification de l'intégrité avec checksums automatiques

### Configuration
- Tous les paramètres de thème stockés en base de données
- Configuration modifiable via interface d'administration intuitive
- Sauvegarde automatique des personnalisations avant changements
- Validation des paramètres avec retour utilisateur immédiat

### Extensibilité
- Architecture modulaire permettant création de thèmes tiers
- Hooks disponibles pour extension de fonctionnalités par modules
- API standardisée pour intégration avec systèmes de design externes
- Support de plugins pour fonctionnalités de thème avancées

### Documentation
- README complet avec guide de création de thèmes personnalisés
- Commentaires détaillés dans le code pour toutes les fonctions CSS
- Documentation API complète avec exemples de personnalisation
- Guide de dépannage pour résolution des problèmes d'affichage