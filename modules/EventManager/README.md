# Module EventManager - N3XT WEB

## Vue d'ensemble
Le module EventManager fournit un système complet de gestion des événements, permettant de tracer toutes les activités du système, surveiller les actions utilisateur et maintenir un audit détaillé pour la sécurité et le dépannage.

## Widgets disponibles

### RecentEventsWidget

Widget principal qui affiche les événements récents et les activités du système en temps réel.

#### Fonctionnalités
- **Événements récents** : Affichage chronologique des dernières activités
- **Filtrage par catégorie** : Système, sécurité, utilisateur, maintenance
- **Timeline interactive** : Groupement par date avec navigation intuitive
- **Analyses statistiques** : Résumés et tendances des événements

#### Configuration

```php
$config = [
    'enabled' => true,
    'title' => 'Événements récents',
    'description' => 'Affiche les événements récents et les activités du système',
    'refresh_interval' => 30, // 30 secondes
    'max_events' => 15,
    'show_categories' => ['system', 'security', 'user', 'maintenance'],
    'show_chart' => true,
    'group_by_date' => true
];
```

#### Utilisation

```php
// Instanciation du widget
$widget = new RecentEventsWidget();

// Récupération des données
$data = $widget->getData();

// Rendu HTML
echo $widget->render();
```

## Fonctionnalités principales
- **Event Logging**: Enregistrement des événements avec catégorisation et niveaux de sévérité
- **Event Monitoring**: Surveillance en temps réel des activités système
- **Event Analytics**: Analyse statistique des patterns d'événements
- **Automatic Cleanup**: Rétention et archivage configurables des anciens événements
- **Critical Notifications**: Alertes immédiates pour les événements critiques
- **Webhook Integration**: Envoi d'événements vers des systèmes externes

## Configuration
Module configuration is stored in the `{prefix}event_config` table:

| Setting | Default | Description |
|---------|---------|-------------|
| `event_logging_enabled` | 1 | Enable/disable event logging |
| `event_retention_days` | 90 | Days to keep event logs |
| `event_critical_notification` | 1 | Send notifications for critical events |
| `event_debug_mode` | 0 | Enable debug logging |
| `event_max_log_size_mb` | 50 | Maximum log size before archival |
| `event_auto_archive` | 1 | Automatically archive old events |
| `event_webhook_enabled` | 0 | Enable webhook notifications |

## Event Types
- `LOGIN` - Authentication events
- `LOGOUT` - Session termination events  
- `UPDATE` - System update events
- `BACKUP` - Backup operation events
- `MAINTENANCE` - System maintenance events
- `SECURITY` - Security-related events
- `ERROR` - System errors
- `SYSTEM` - General system events

## Event Categories
- `authentication` - Login/logout activities
- `system` - System operations
- `security` - Security incidents
- `maintenance` - Maintenance activities
- `user_action` - User-initiated actions

## Severity Levels
- `DEBUG` - Debug information
- `INFO` - Informational messages
- `WARNING` - Warning conditions
- `ERROR` - Error conditions
- `CRITICAL` - Critical system issues

## Usage

### Basic Event Logging
```php
$eventManager = EventManager::getInstance();
$eventManager->logEvent(
    EventManager::EVENT_TYPE_LOGIN,
    EventManager::CATEGORY_AUTHENTICATION,
    'User logged in successfully',
    ['username' => 'admin'],
    EventManager::SEVERITY_INFO
);
```

### Retrieving Events
```php
$events = $eventManager->getEvents([
    'type' => 'LOGIN',
    'severity' => 'ERROR'
], 50, 0);
```

### Event Statistics
```php
$stats = $eventManager->getEventStats(7); // Last 7 days
```

## Database Schema
The module uses the following tables:
- `{prefix}events` - Main events table
- `{prefix}event_config` - Module configuration

## Integration
The EventManager integrates with:
- SecurityManager (security event logging)
- NotificationManager (critical event alerts)
- MaintenanceManager (cleanup operations)

## Administration
Event management is available through the back office at `/bo/events.php` (when implemented).

## Migration
Module migrations are tracked in the `{prefix}module_migrations` table.