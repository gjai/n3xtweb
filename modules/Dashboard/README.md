# Module Dashboard - N3XT WEB

Ce module gère le tableau de bord principal et les notifications système du back office N3XT WEB.

## Vue d'ensemble

Le module Dashboard fournit une interface centralisée pour surveiller l'état du système, gérer les notifications et afficher les informations importantes en temps réel.

## Widgets disponibles

### SystemNotificationsWidget

Widget principal qui affiche les notifications système et les alertes importantes du back office.

#### Fonctionnalités

- **Notifications système** : Affichage des alertes et messages importants
- **Statut système** : Surveillance en temps réel de l'état des services
- **Résumé d'activité** : Vue d'ensemble des événements récents
- **Gestion des alertes** : Interface pour traiter les notifications

#### Configuration

```php
$config = [
    'enabled' => true,
    'title' => 'Notifications système',
    'description' => 'Affiche les notifications système et les alertes importantes',
    'refresh_interval' => 60, // 1 minute
    'max_notifications' => 10,
    'show_priorities' => ['high', 'medium', 'low'],
    'auto_refresh' => true,
    'sound_alerts' => false
];
```

#### Utilisation

```php
// Instanciation du widget
$widget = new SystemNotificationsWidget();

// Récupération des données
$data = $widget->getData();

// Rendu HTML
echo $widget->render();
```

#### Structure des données

```php
$data = [
    'notifications' => [
        [
            'id' => int,
            'type' => string,
            'priority' => string, // critical, high, medium, low
            'title' => string,
            'message' => string,
            'created_at' => string,
            'status' => string, // active, read, dismissed
            'icon' => string,
            'action_url' => string|null
        ]
    ],
    'summary' => [
        'total' => int,
        'by_priority' => array,
        'by_type' => array,
        'unread' => int
    ],
    'system_status' => [
        'overall' => string, // good, warning, critical
        'services' => array,
        'uptime' => array,
        'last_check' => string
    ],
    'recent_activities' => array,
    'last_updated' => string
];
```

## Types de notifications

### Système
- Mises à jour disponibles
- Erreurs de configuration
- Problèmes de performance
- États des services

### Sécurité
- Tentatives de connexion suspectes
- Alertes de sécurité
- Mises à jour de sécurité
- Scan de vulnérabilités

### Maintenance
- Sauvegardes terminées
- Nettoyage automatique
- Tâches de maintenance
- Optimisations

### Avertissements
- Espace disque faible
- Ressources limitées
- Configurations obsolètes
- Erreurs mineures

## Surveillance système

### Services surveillés

#### Base de données
- **État** : Connectivité et réactivité
- **Performance** : Temps de réponse des requêtes
- **Espace** : Utilisation de l'espace disque
- **Intégrité** : Vérification de la structure

#### Système de fichiers
- **Espace disque** : Surveillance de l'espace disponible
- **Permissions** : Vérification des droits d'accès
- **Intégrité** : Contrôle des fichiers critiques
- **Sauvegardes** : État des backups automatiques

#### Sécurité
- **Firewall** : État et configuration
- **SSL/TLS** : Certificats et chiffrement
- **Authentification** : Systèmes d'accès
- **Mises à jour** : Patches de sécurité

#### Performance
- **Mémoire** : Utilisation RAM et swap
- **CPU** : Charge processeur
- **Réseau** : Connectivité et bande passante
- **Cache** : Efficacité du système de cache

### Indicateurs de statut

- 🟢 **BON** : Tous les systèmes fonctionnent normalement
- 🟡 **ATTENTION** : Problème mineur nécessitant surveillance
- 🔴 **CRITIQUE** : Intervention immédiate requise

## Gestion des notifications

### Priorités

#### Critique
- Pannes système majeures
- Failles de sécurité critiques
- Perte de données imminente
- Services indisponibles

#### Élevée
- Problèmes de performance
- Alertes de sécurité importantes
- Erreurs de configuration
- Ressources critiques

#### Moyenne
- Mises à jour disponibles
- Avertissements de maintenance
- Optimisations recommandées
- Notifications informatives

#### Faible
- Informations générales
- Conseils d'optimisation
- Rappels de maintenance
- Confirmations d'actions

### Actions disponibles

- **Marquer comme lu** : Masquer de la vue principale
- **Ignorer** : Supprimer définitivement
- **Planifier** : Reporter le traitement
- **Voir détails** : Accéder aux informations complètes

## Tableaux de bord personnalisés

### Widgets configurables
- Drag & drop pour réorganiser
- Redimensionnement flexible
- Masquage/affichage selon besoins
- Personnalisation par utilisateur

### Métriques disponibles
- Statistiques de trafic
- Performance système
- Activité utilisateurs
- Statuts des modules

### Filtres et recherche
- Filtrage par date/période
- Recherche dans notifications
- Tri par priorité/type
- Export des données

## API de notifications

### Création de notifications

```php
// Notification simple
NotificationManager::create([
    'type' => 'system',
    'priority' => 'medium',
    'title' => 'Titre de la notification',
    'message' => 'Contenu du message',
    'target_user' => null // null = tous les admins
]);

// Notification avec action
NotificationManager::create([
    'type' => 'warning',
    'priority' => 'high',
    'title' => 'Espace disque faible',
    'message' => 'L\'espace disque est inférieur à 1GB',
    'action_url' => '/admin/maintenance',
    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
]);
```

### Gestion programmatique

```php
// Marquer comme lue
NotificationManager::markAsRead($notificationId, $userId);

// Supprimer
NotificationManager::delete($notificationId);

// Obtenir les notifications d'un utilisateur
$notifications = NotificationManager::getForUser($userId, [
    'limit' => 10,
    'priority' => ['high', 'critical'],
    'unread_only' => true
]);
```

## Intégration

### Avec d'autres modules
- **SecurityManager** : Reçoit les alertes de sécurité
- **MaintenanceManager** : Notifications de maintenance
- **BackupManager** : États des sauvegardes
- **UpdateManager** : Alertes de mises à jour

### Webhooks et API
- Notifications via webhook externe
- API REST pour intégrations tierces
- Support des notifications push
- Intégration email/SMS

## Configuration avancée

### Paramètres de notification

```php
$config = [
    // Rétention des notifications
    'retention_days' => 30,
    
    // Limite par utilisateur
    'max_notifications_per_user' => 100,
    
    // Types d'auto-notification
    'auto_notify' => [
        'system_errors' => true,
        'security_alerts' => true,
        'maintenance_tasks' => false,
        'updates_available' => true
    ],
    
    // Intégrations
    'email_notifications' => true,
    'webhook_url' => null,
    'slack_integration' => false
];
```

### Personnalisation interface

```css
/* Styles personnalisés pour les notifications */
.notification-critical {
    border-left: 4px solid #dc3545;
    background: #fff5f5;
}

.notification-high {
    border-left: 4px solid #fd7e14;
    background: #fff8f0;
}
```

## Performance

### Optimisations
- Cache des notifications fréquentes
- Pagination automatique
- Compression des données
- Lazy loading des détails

### Monitoring
- Temps de réponse des requêtes
- Utilisation mémoire du widget
- Fréquence de mise à jour
- Taux d'interaction utilisateur

## Sécurité

- Validation des données de notification
- Protection contre le spam
- Limitation du taux de création
- Audit des actions utilisateur
- Chiffrement des données sensibles

## Dépannage

### Problèmes courants

**Notifications non affichées**
- Vérifier les permissions utilisateur
- Contrôler la configuration du module
- Vérifier la base de données

**Performance dégradée**
- Nettoyer les anciennes notifications
- Optimiser les requêtes de base
- Ajuster la fréquence de refresh

**Erreurs JavaScript**
- Vérifier la compatibilité navigateur
- Contrôler les conflits de scripts
- Valider la syntaxe des templates