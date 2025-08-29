# EventManager Module - N3XT WEB

## Vue d'ensemble

Le module EventManager fournit un système complet de gestion centralisée des événements pour le système N3XT WEB. Il permet de tracer toutes les activités du système, surveiller les actions utilisateur et maintenir un audit détaillé pour la sécurité, le dépannage et l'analyse des performances.

## Fonctionnalités

### 📝 Journalisation centralisée des événements
- Enregistrement automatique de tous les événements système avec horodatage précis
- Catégorisation intelligente par type d'événement et niveau de sévérité
- Support des métadonnées contextuelles avec format JSON flexible
- Stockage optimisé avec indexation pour recherches rapides

### 🔍 Surveillance et monitoring en temps réel
- Dashboard temps réel des activités système avec widgets interactifs
- Détection automatique des patterns anormaux et événements suspects
- Alertes proactives pour événements critiques nécessitant intervention
- Intégration complète avec le système de notifications

### 📊 Analyses et statistiques avancées
- Génération de rapports détaillés par période et catégorie
- Analyses de tendances et patterns d'utilisation
- Statistiques de performance et temps de réponse
- Export des données pour analyse externe

### 🧹 Gestion automatique du cycle de vie
- Rétention automatique selon politique configurable
- Archivage intelligent des anciens événements
- Compression et rotation des logs volumineux
- Nettoyage automatique coordonné avec MaintenanceManager

## Configuration

### Paramètres disponibles

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| `enabled` | Active/désactive la journalisation | `true` |
| `retention_days` | Durée de conservation (jours) | `90` |
| `critical_notification` | Notifications pour événements critiques | `true` |
| `debug_mode` | Mode debug avec logs étendus | `false` |
| `max_log_size_mb` | Taille max avant archivage (MB) | `50` |
| `auto_archive` | Archivage automatique | `true` |
| `webhook_enabled` | Notifications webhook externes | `false` |

### Configuration via interface admin

```php
// Accès au module
$eventManager = EventManager::getInstance();

// Modifier la configuration
$eventManager->setConfig('retention_days', 120);
$eventManager->setConfig('debug_mode', true);
```

## Administration

**Interface disponible :** `/bo/events.php`

### Tableau de bord
- Vue d'ensemble temps réel des événements récents
- Statistiques par catégorie et niveau de sévérité
- Graphiques de tendances et pics d'activité
- Alertes actives nécessitant attention

### Actions disponibles
- Consultation des logs avec filtres avancés par date, type, sévérité
- Recherche textuelle dans les descriptions et métadonnées
- Export des événements pour analyse externe
- Configuration des politiques de rétention et archivage

## Schema de base de données

### Table `events`

```sql
CREATE TABLE n3xt_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type ENUM('LOGIN', 'LOGOUT', 'UPDATE', 'BACKUP', 'MAINTENANCE', 'SECURITY', 'ERROR', 'SYSTEM') NOT NULL,
    category ENUM('authentication', 'system', 'security', 'maintenance', 'user_action') NOT NULL,
    severity ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL') DEFAULT 'INFO',
    description TEXT NOT NULL,
    metadata JSON NULL,
    user_id VARCHAR(50) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type_created (event_type, created_at),
    INDEX idx_category_severity (category, severity),
    INDEX idx_user_created (user_id, created_at)
);
```

## Intégration

### Avec les autres modules

**SecurityManager :** Journalisation des événements de sécurité
- Enregistrement automatique de toutes les tentatives de connexion
- Logging des blocages d'IP et activités suspectes
- Intégration dans l'audit trail de sécurité complet

**NotificationManager :** Alertes automatiques pour événements critiques
- Notifications immédiates pour événements CRITICAL
- Rapports périodiques d'activité système
- Intégration avec système d'alerte global

**MaintenanceManager :** Coordination pour archivage et nettoyage
- Archivage automatique des anciens événements
- Nettoyage coordonné avec autres tâches de maintenance
- Respect des politiques de rétention partagées

### API et hooks

Le module expose les méthodes suivantes pour intégration :
- `logEvent($type, $category, $description, $metadata, $severity)` : Enregistre un événement
- `getEvents($filters, $limit, $offset)` : Récupère les événements filtrés
- `getEventStats($days)` : Retourne les statistiques d'événements

## Exemple d'utilisation

### Journalisation d'événements basiques

```php
$eventManager = EventManager::getInstance();

// Événement de connexion réussie
$eventManager->logEvent(
    EventManager::EVENT_TYPE_LOGIN,
    EventManager::CATEGORY_AUTHENTICATION,
    'Connexion administrateur réussie',
    [
        'username' => 'admin',
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ],
    EventManager::SEVERITY_INFO
);

// Événement de sécurité critique
$eventManager->logEvent(
    EventManager::EVENT_TYPE_SECURITY,
    EventManager::CATEGORY_SECURITY,
    'Tentative d\'accès non autorisé détectée',
    [
        'attempted_resource' => '/bo/config.php',
        'blocked_ip' => $suspiciousIP,
        'attempt_count' => 5
    ],
    EventManager::SEVERITY_CRITICAL
);
```

### Récupération et analyse des événements

```php
// Récupérer les événements de sécurité des 7 derniers jours
$securityEvents = $eventManager->getEvents([
    'category' => 'security',
    'severity' => ['ERROR', 'CRITICAL'],
    'date_from' => date('Y-m-d', strtotime('-7 days'))
], 100, 0);

foreach ($securityEvents as $event) {
    echo "Date: {$event['created_at']}\n";
    echo "Type: {$event['event_type']}\n";
    echo "Description: {$event['description']}\n";
    echo "Sévérité: {$event['severity']}\n";
    
    if ($event['metadata']) {
        $metadata = json_decode($event['metadata'], true);
        echo "IP: " . ($metadata['ip_address'] ?? 'N/A') . "\n";
    }
    echo "---\n";
}
```

### Génération de statistiques

```php
// Statistiques des 30 derniers jours
$stats = $eventManager->getEventStats(30);

echo "Événements par catégorie:\n";
foreach ($stats['by_category'] as $category => $count) {
    echo "- {$category}: {$count} événements\n";
}

echo "\nÉvénements par sévérité:\n";
foreach ($stats['by_severity'] as $severity => $count) {
    echo "- {$severity}: {$count} événements\n";
}

echo "\nÉvénements critiques récents: " . $stats['critical_recent'] . "\n";
echo "Tendance d'activité: " . $stats['trend'] . "%\n";
```

## Principes communs

### Sécurité
- Protection CSRF sur toutes les actions de consultation et configuration
- Validation rigoureuse de tous les paramètres d'entrée pour éviter injection
- Chiffrement des métadonnées sensibles avant stockage
- Contrôle d'accès granulaire selon permissions utilisateur

### Configuration
- Tous les paramètres de logging stockés en base de données
- Configuration modifiable via interface d'administration sécurisée
- Valeurs par défaut optimisées pour performance et sécurité
- Validation en temps réel des paramètres avec retour immédiat

### Extensibilité
- Architecture modulaire permettant ajout de nouveaux types d'événements
- Hooks disponibles pour intégration avec systèmes de monitoring externes
- API standardisée pour collecte d'événements par modules tiers
- Support de webhooks pour intégration avec systèmes d'alerte

### Documentation
- README complet avec exemples détaillés pour tous les cas d'usage
- Commentaires dans le code pour toutes les fonctionnalités de logging
- Documentation API complète avec exemples de métadonnées
- Guide de configuration et bonnes pratiques d'audit