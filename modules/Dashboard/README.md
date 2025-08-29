# Dashboard Module - N3XT WEB

## Vue d'ensemble

Le module Dashboard fournit un système complet de tableau de bord modulaire pour le back office N3XT WEB. Il gère l'interface centralisée de surveillance du système, l'affichage des widgets dynamiques, et la gestion intelligente des notifications avec une architecture extensible et personnalisable.

## Fonctionnalités

### 📊 Tableau de bord modulaire et personnalisable
- Interface drag & drop pour réorganisation des widgets en temps réel
- Système de grille responsive avec redimensionnement flexible
- Personnalisation par utilisateur avec sauvegarde des préférences
- Support de widgets personnalisés avec API complète

### 📢 Système de notifications intelligent
- Affichage centralisé des notifications avec priorisation automatique
- Gestion multi-niveaux (critique, élevée, moyenne, faible) avec codes couleur
- Système de filtrage avancé par type, date et statut
- Actions rapides (lecture, suppression, planification) avec interface intuitive

### 📈 Surveillance système en temps réel
- Monitoring continu des services critiques (BDD, fichiers, réseau)
- Indicateurs de performance avec seuils d'alerte configurables
- Tableau de bord de santé système avec statuts visuels
- Historique des métriques avec tendances et analyses

### 🔧 Widgets extensibles et intégrés
- Catalogue de widgets pré-construits pour tous les modules
- API standardisée pour développement de widgets personnalisés
- Système de cache intelligent pour optimiser les performances
- Configuration granulaire par widget avec prévisualisation

## Configuration

### Paramètres disponibles

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| `enabled` | Active/désactive le module | `true` |
| `auto_refresh` | Actualisation automatique des widgets | `true` |
| `refresh_interval` | Intervalle de rafraîchissement (secondes) | `60` |
| `max_notifications` | Nombre max de notifications affichées | `10` |
| `widget_cache_enabled` | Cache des données de widgets | `true` |
| `user_customization` | Personnalisation par utilisateur | `true` |
| `sound_alerts` | Alertes sonores pour notifications critiques | `false` |

### Configuration via interface admin

```php
// Accès au module
$dashboardManager = new DashboardManager();

// Configuration des widgets
$dashboardManager->configureWidget('system_status', [
    'refresh_interval' => 30,
    'show_alerts' => true,
    'max_items' => 5
]);

// Personnalisation utilisateur
$dashboardManager->setUserLayout($userId, $layoutConfig);
```

## Administration

**Interface disponible :** `/bo/dashboard.php`

### Tableau de bord
- Vue d'ensemble en temps réel de l'état de tous les systèmes
- Widgets configurables avec données actualisées automatiquement
- Centre de notifications avec gestion des priorités
- Métriques de performance avec graphiques interactifs

### Actions disponibles
- Configuration et personnalisation complète des widgets
- Gestion des notifications avec actions en lot
- Export des données et métriques pour analyse externe
- Administration des permissions et accès par utilisateur

## Schema de base de données

### Table `dashboard_widgets`

```sql
CREATE TABLE n3xt_dashboard_widgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    widget_id VARCHAR(50) NOT NULL,
    user_id VARCHAR(50) NULL,
    position_x INT NOT NULL DEFAULT 0,
    position_y INT NOT NULL DEFAULT 0,
    width INT NOT NULL DEFAULT 4,
    height INT NOT NULL DEFAULT 4,
    config JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Table `dashboard_notifications`

```sql
CREATE TABLE n3xt_dashboard_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('system', 'security', 'maintenance', 'warning', 'info') NOT NULL,
    priority ENUM('critical', 'high', 'medium', 'low') DEFAULT 'medium',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    target_user VARCHAR(50) NULL,
    action_url VARCHAR(500) NULL,
    status ENUM('active', 'read', 'dismissed') DEFAULT 'active',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Intégration

### Avec les autres modules

**SecurityManager :** Intégration des alertes de sécurité
- Affichage prioritaire des alertes de sécurité critiques
- Widget dédié pour surveillance des tentatives d'intrusion
- Notifications automatiques des événements de sécurité importants

**MaintenanceManager :** Monitoring des tâches de maintenance
- Widget de statut des maintenances avec planning automatique
- Notifications de fin de maintenance avec résumé détaillé
- Intégration dans le monitoring global de santé système

**BackupManager :** Surveillance des sauvegardes
- Widget de statut des sauvegardes avec historique
- Alertes automatiques d'échec de sauvegarde
- Métriques d'espace utilisé et tendances de croissance

### API et hooks

Le module expose les méthodes suivantes pour intégration :
- `addWidget($widgetId, $config)` : Ajoute un widget au tableau de bord
- `createNotification($type, $priority, $title, $message)` : Crée une notification
- `getSystemStatus()` : Retourne l'état global du système

## Exemple d'utilisation

### Gestion des widgets

```php
$dashboardManager = new DashboardManager();

// Ajouter un widget personnalisé
$widgetConfig = [
    'title' => 'Statut des services',
    'refresh_interval' => 30,
    'show_graph' => true,
    'services' => ['database', 'cache', 'storage']
];

$dashboardManager->addWidget('system_services', $widgetConfig);

// Configurer la position du widget
$dashboardManager->setWidgetPosition('system_services', [
    'x' => 0,
    'y' => 0,
    'width' => 6,
    'height' => 4
]);
```

### Création de notifications

```php
// Notification critique système
$dashboardManager->createNotification(
    'system',
    'critical',
    'Espace disque critique',
    'L\'espace disque disponible est inférieur à 5%. Action immédiate requise.',
    '/bo/maintenance',
    null,  // Tous les utilisateurs
    date('Y-m-d H:i:s', strtotime('+24 hours'))  // Expire dans 24h
);

// Notification informative
$dashboardManager->createNotification(
    'info',
    'medium',
    'Sauvegarde terminée',
    'La sauvegarde automatique s\'est terminée avec succès.',
    '/bo/backup',
    'admin',  // Utilisateur spécifique
    null  // N'expire pas
);
```

### Développement de widgets personnalisés

```php
// Créer un widget personnalisé
class CustomSystemWidget extends BaseWidget {
    public function getData() {
        return [
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'active_sessions' => $this->getActiveSessions()
        ];
    }
    
    public function render() {
        $data = $this->getData();
        return $this->renderTemplate('custom_system_widget', $data);
    }
}

// Enregistrer le widget
$dashboardManager->registerWidget('custom_system', CustomSystemWidget::class);
```

### Surveillance système avancée

```php
// Obtenir l'état global du système
$systemStatus = $dashboardManager->getSystemStatus();

echo "État général: " . $systemStatus['overall'] . "\n";
echo "Services surveillés: " . count($systemStatus['services']) . "\n";

foreach ($systemStatus['services'] as $service => $status) {
    echo "- {$service}: " . $status['status'];
    if ($status['status'] !== 'good') {
        echo " (problème: " . $status['issue'] . ")";
    }
    echo "\n";
}

// Métriques de performance
$metrics = $dashboardManager->getPerformanceMetrics();
echo "CPU: " . $metrics['cpu_usage'] . "%\n";
echo "RAM: " . $metrics['memory_usage'] . "%\n";
echo "Disque: " . $metrics['disk_usage'] . "%\n";
```

## Principes communs

### Sécurité
- Protection CSRF sur toutes les actions de configuration de dashboard
- Validation rigoureuse des données de widgets pour éviter injection
- Contrôle d'accès granulaire selon permissions utilisateur
- Sanitation de toutes les données affichées dans les notifications

### Configuration
- Tous les paramètres de dashboard stockés en base de données
- Configuration modifiable via interface d'administration moderne
- Sauvegarde automatique des personnalisations utilisateur
- Validation en temps réel des paramètres avec retour immédiat

### Extensibilité
- Architecture modulaire permettant ajout facile de nouveaux widgets
- Hooks disponibles pour extension de fonctionnalités par modules tiers
- API standardisée pour intégration avec systèmes de monitoring externes
- Support de plugins pour widgets et notifications personnalisés

### Documentation
- README complet avec guide de développement de widgets personnalisés
- Commentaires détaillés dans le code pour toutes les fonctionnalités
- Documentation API complète avec exemples de widgets
- Guide de personnalisation et bonnes pratiques d'interface