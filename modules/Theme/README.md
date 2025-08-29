# Module Theme - N3XT WEB

Ce module gère les thèmes, l'apparence et la personnalisation visuelle du back office N3XT WEB.

## Vue d'ensemble

Le module Theme fournit une interface complète pour gérer l'apparence du système, prévisualiser les thèmes disponibles et personnaliser l'interface utilisateur selon les préférences.

## Widgets disponibles

### ThemePreviewWidget

Widget principal qui affiche l'aperçu du thème actuel et permet la gestion des thèmes.

#### Fonctionnalités

- **Thème actuel** : Affiche les informations et l'aperçu du thème en cours
- **Thèmes disponibles** : Liste tous les thèmes installés avec prévisualisations
- **Personnalisation** : Options de customisation (couleurs, polices, layout)
- **Historique** : Suivi des changements de thème récents

#### Configuration

```php
$config = [
    'enabled' => true,
    'title' => 'Aperçu du thème',
    'description' => 'Affiche l\'aperçu du thème actuel et permet la gestion des thèmes',
    'refresh_interval' => 600, // 10 minutes
    'show_theme_info' => true,
    'show_customization' => true,
    'max_recent_themes' => 5
];
```

#### Utilisation

```php
// Instanciation du widget
$widget = new ThemePreviewWidget();

// Récupération des données
$data = $widget->getData();

// Rendu HTML
echo $widget->render();
```

#### Structure des données

```php
$data = [
    'current_theme' => [
        'name' => string,
        'version' => string,
        'author' => string,
        'description' => string,
        'screenshot' => string|null,
        'colors' => [
            'primary' => string,
            'secondary' => string,
            'success' => string,
            'warning' => string,
            'danger' => string
        ],
        'features' => [
            'responsive' => bool,
            'dark_mode' => bool,
            'customizable' => bool
        ]
    ],
    'available_themes' => array,
    'recent_changes' => array,
    'customization_options' => array,
    'last_updated' => string
];
```

## Gestion des thèmes

### Structure d'un thème

```
/assets/themes/nom_du_theme/
├── theme.json          # Configuration du thème
├── style.css           # Styles principaux
├── preview.jpg         # Image de prévisualisation
├── assets/             # Ressources du thème
│   ├── images/
│   ├── fonts/
│   └── js/
└── templates/          # Templates spécifiques (optionnel)
```

### Fichier theme.json

```json
{
    "name": "Nom du thème",
    "version": "1.0.0",
    "author": "Auteur",
    "description": "Description du thème",
    "colors": {
        "primary": "#007cba",
        "secondary": "#6c757d",
        "success": "#28a745",
        "warning": "#ffc107",
        "danger": "#dc3545"
    },
    "features": {
        "responsive": true,
        "dark_mode": false,
        "customizable": true
    },
    "dependencies": [],
    "compatibility": "1.0+"
}
```

## Options de personnalisation

### Couleurs
- **Couleur principale** : Couleur de base du thème
- **Couleur secondaire** : Couleur d'accent et éléments secondaires
- **Couleurs d'état** : Succès, avertissement, erreur

### Typographie
- **Police principale** : Police pour le contenu général
- **Police de titre** : Police pour les en-têtes
- **Taille de base** : Taille de police de référence

### Layout
- **Position sidebar** : Gauche ou droite
- **Largeur sidebar** : Compacte ou étendue
- **Affichage breadcrumbs** : Activer/désactiver le fil d'Ariane

### Interface
- **Mode sombre** : Basculer vers le thème sombre
- **Animations** : Activer/désactiver les transitions
- **Compact mode** : Interface plus dense

## Thèmes par défaut

### Default N3XT
- **Description** : Thème par défaut élégant et professionnel
- **Couleurs** : Bleu N3XT (#007cba) et nuances grises
- **Caractéristiques** : Responsive, personnalisable, accessible

### Dark Theme
- **Description** : Thème sombre pour réduire la fatigue oculaire
- **Couleurs** : Palette sombre avec accents colorés
- **Caractéristiques** : Mode nuit, contrastes élevés

### Minimal
- **Description** : Design minimaliste et épuré
- **Couleurs** : Palette neutre avec touches de couleur
- **Caractéristiques** : Interface simplifiée, focus contenu

## Fonctionnalités avancées

### Prévisualisation en temps réel
- Aperçu instantané des modifications
- Simulation sur différents écrans
- Test de compatibilité automatique

### Import/Export de thèmes
- Export des thèmes personnalisés
- Import de thèmes externes
- Sauvegarde des configurations

### Système de cache
- Cache des CSS compilés
- Optimisation des performances
- Invalidation automatique

## API de développement

### Création d'un thème

```php
// Enregistrer un nouveau thème
ThemeManager::registerTheme([
    'id' => 'mon_theme',
    'name' => 'Mon thème personnalisé',
    'path' => '/assets/themes/mon_theme/',
    'config' => $themeConfig
]);

// Activer un thème
ThemeManager::activateTheme('mon_theme');

// Obtenir le thème actuel
$currentTheme = ThemeManager::getCurrentTheme();
```

### Hooks disponibles

```php
// Avant changement de thème
add_hook('theme_before_change', function($oldTheme, $newTheme) {
    // Actions à effectuer
});

// Après changement de thème
add_hook('theme_after_change', function($theme) {
    // Actions à effectuer
});

// Personnalisation appliquée
add_hook('theme_customization_applied', function($options) {
    // Actions à effectuer
});
```

## Intégration

### Avec d'autres modules
- **Dashboard** : Applique le thème à l'interface principale
- **SecurityManager** : Vérifie l'intégrité des fichiers de thème
- **NotificationManager** : Notifications des changements de thème

### Compatibilité
- Responsive design pour mobile/tablette
- Support des navigateurs modernes
- Accessibilité WCAG 2.1 niveau AA

## Maintenance

### Performance
- Minification automatique des CSS
- Compression des images
- Lazy loading des ressources

### Mises à jour
- Vérification de compatibilité
- Migration automatique des configurations
- Sauvegarde avant changement

### Sauvegarde
- Export automatique des personnalisations
- Historique des configurations
- Restauration rapide

## Dépannage

### Problèmes courants

**Thème ne s'affiche pas correctement**
- Vider le cache du navigateur
- Vérifier les permissions des fichiers
- Contrôler les erreurs CSS

**Personnalisations perdues**
- Vérifier la sauvegarde automatique
- Contrôler les permissions d'écriture
- Restaurer depuis l'historique

**Performance dégradée**
- Optimiser les images du thème
- Minimiser les CSS personnalisés
- Utiliser le cache des thèmes

## Sécurité

- Validation des fichiers de thème
- Restriction des uploads de thèmes
- Scanning antimalware des ressources
- Protection contre l'injection CSS