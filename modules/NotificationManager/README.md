# NotificationManager Module

## Description

Le module NotificationManager fournit un système complet de notifications pour le back office N3XT WEB. Il gère les notifications visuelles dans l'interface d'administration et les notifications par email.

## Fonctionnalités

### 📢 Notifications visuelles
- Affichage en temps réel dans le back office
- Système de priorités (low, medium, high, critical)
- Interface de gestion (lecture, suppression)
- Compteur de notifications non lues

### 📧 Notifications par email
- Envoi automatique par SMTP ou PHP mail()
- Templates HTML personnalisables
- Configuration flexible des types à envoyer
- Support multi-destinataires

### 📊 Gestion et historique
- Historique complet des notifications
- Nettoyage automatique des anciennes notifications
- Statistiques détaillées par type et priorité
- Interface d'administration complète

### 🔧 Extensibilité
- Types de notifications personnalisables
- Hooks pour d'autres modules
- API simple pour créer des notifications
- Système de métadonnées JSON

## Configuration

### Paramètres disponibles

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| `enabled` | Active/désactive le module | `true` |
| `email_enabled` | Notifications par email | `true` |
| `visual_enabled` | Notifications visuelles | `true` |
| `retention_days` | Durée de conservation | `30` |
| `auto_email_types` | Types envoyés par email | `update,backup,maintenance,system` |

## Utilisation

### Créer une notification

```php
$notificationManager = new NotificationManager();

$notificationManager->createNotification(
    'update',                    // Type
    'Mise à jour disponible',    // Titre
    'Version 2.1.0 disponible', // Message
    'high',                      // Priorité
    [                           // Données supplémentaires
        'version' => '2.1.0',
        'download_url' => 'https://...'
    ],
    null,                       // Utilisateur cible (null = tous)
    null                        // Date d'expiration
);
```

### Types de notifications disponibles

- **update** : Mises à jour système
- **backup** : Opérations de sauvegarde
- **maintenance** : Tâches de maintenance
- **system** : Notifications système générales
- **warning** : Avertissements
- **error** : Erreurs critiques

### Priorités

- **low** : Information générale
- **medium** : Information importante
- **high** : Action recommandée
- **critical** : Action urgente requise

## API

### Méthodes principales

#### `createNotification($type, $title, $message, $priority, $data, $targetUser, $expiresAt)`
Crée une nouvelle notification.

#### `getNotifications($targetUser, $status, $limit)`
Récupère les notifications filtrées.

#### `markAsRead($notificationId, $userId)`
Marque une notification comme lue.

#### `dismiss($notificationId, $userId)`
Ignore une notification.

#### `getUnreadCount($targetUser)`
Compte les notifications non lues.

#### `cleanupOldNotifications()`
Nettoie les anciennes notifications.

## Configuration Email

### SMTP
```php
Configuration::set('smtp_host', 'smtp.example.com');
Configuration::set('smtp_port', 587);
Configuration::set('smtp_user', 'user@example.com');
Configuration::set('smtp_pass', 'password');
Configuration::set('smtp_from', 'noreply@example.com');
Configuration::set('smtp_from_name', 'N3XT WEB');
```

### PHP Mail
Si aucune configuration SMTP n'est fournie, le système utilise la fonction `mail()` de PHP.

## Templates Email

Les emails utilisent des templates HTML responsives avec :
- Design moderne et professionnel
- Support des priorités avec couleurs
- Informations contextuelles
- Liens vers le back office
- Footer personnalisable

## Base de données

### Table `notifications`
```sql
CREATE TABLE n3xt_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('update', 'backup', 'maintenance', 'system', 'warning', 'error') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('unread', 'read', 'dismissed') DEFAULT 'unread',
    target_user VARCHAR(50) NULL,
    data JSON NULL,
    email_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL
);
```

## Intégration

### Avec autres modules
Le NotificationManager est automatiquement utilisé par :
- **UpdateManager** : Notifications de mises à jour
- **BackupManager** : Notifications de sauvegardes
- **MaintenanceManager** : Notifications de maintenance

### Interface utilisateur
- Compteur de notifications dans la navigation
- Interface de gestion complète
- Affichage temps réel des nouvelles notifications

## Sécurité

- Validation CSRF sur toutes les actions
- Sanitation des entrées utilisateur
- Limitation de la taille des messages
- Protection contre l'injection de logs
- Validation des types et priorités

## Performance

- Nettoyage automatique des anciennes notifications
- Indexation optimisée des requêtes
- Limitation du nombre de notifications par utilisateur
- Cache des compteurs de notifications non lues

## Extensibilité

### Ajouter un nouveau type
1. Ajouter le type dans l'énumération de la base de données
2. Mettre à jour la liste `$validTypes` dans la classe
3. Ajouter l'icône dans `getTypeIcon()`
4. Configurer l'envoi email si nécessaire

### Hooks personnalisés
```php
// Écouter les nouvelles notifications
add_action('notification_created', function($notificationId, $type) {
    // Action personnalisée
});
```