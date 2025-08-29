# NotificationManager Module - N3XT WEB

## Vue d'ensemble

Le module NotificationManager fournit un système complet de notifications pour le back office N3XT WEB. Il gère les notifications visuelles en temps réel dans l'interface d'administration et les notifications par email, avec un système extensible pour différents types d'événements.

## Fonctionnalités

### 📢 Notifications visuelles en temps réel
- Affichage instantané dans le back office avec interface moderne
- Système de priorités intuitif (low, medium, high, critical)
- Interface de gestion complète (lecture, suppression, filtrage)
- Compteur dynamique de notifications non lues
- Support des notifications persistantes et temporaires

### 📧 Notifications par email avancées
- Envoi automatique par SMTP sécurisé ou PHP mail()
- Templates HTML responsives et personnalisables
- Configuration flexible des types d'événements à envoyer
- Support multi-destinataires avec gestion des groupes
- Système anti-spam avec limitation de fréquence

### 📊 Gestion et historique complets
- Historique détaillé de toutes les notifications envoyées
- Nettoyage automatique des anciennes notifications
- Statistiques complètes par type, priorité et utilisateur
- Interface d'administration intuitive avec tableaux de bord
- Export des données de notification pour analyse

### 🔧 Extensibilité et personnalisation
- Types de notifications entièrement personnalisables
- Hooks et API pour intégration avec d'autres modules
- Système de templates modulaire pour emails
- Métadonnées JSON flexibles pour données contextuelles
- Architecture plugin-ready pour extensions

## Configuration

### Paramètres disponibles

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| `enabled` | Active/désactive le module | `true` |
| `email_enabled` | Notifications par email | `true` |
| `visual_enabled` | Notifications visuelles | `true` |
| `retention_days` | Durée de conservation (jours) | `30` |
| `auto_email_types` | Types envoyés par email | `update,backup,maintenance,system` |
| `smtp_enabled` | Utilisation SMTP | `false` |
| `rate_limit` | Limite par heure et par type | `10` |

### Configuration via interface admin

```php
// Accès au module
$notificationManager = new NotificationManager();

// Configuration SMTP
$notificationManager->setConfig('smtp_host', 'smtp.example.com');
$notificationManager->setConfig('smtp_port', 587);
$notificationManager->setConfig('smtp_user', 'user@example.com');
```

## Administration

**Interface disponible :** `/bo/notifications.php`

### Tableau de bord
- Compteur temps réel des notifications non lues
- Statistiques des notifications par type et priorité
- Statut du système de notification (email/visuel)
- Historique des dernières notifications importantes

### Actions disponibles
- Gestion complète des notifications (lecture, suppression, archivage)
- Configuration des types de notifications et destinataires
- Test du système email avec validation SMTP
- Consultation de l'historique avec filtres avancés

## Schema de base de données

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

### Avec les autres modules

**UpdateManager :** Notifications automatiques des mises à jour
- Notification de nouvelles versions disponibles
- Alertes de fin de mise à jour (succès/échec)
- Notifications de problèmes de compatibilité

**BackupManager :** Notifications des opérations de sauvegarde
- Confirmation de création de sauvegarde réussie
- Alertes d'échec de sauvegarde avec détails
- Notifications de nettoyage automatique des anciennes sauvegardes

**MaintenanceManager :** Notifications de maintenance système
- Rapports de fin de maintenance automatique
- Alertes d'espace disque critique nécessitant action
- Notifications de tâches de maintenance programmées

### API et hooks

Le module expose les méthodes suivantes pour intégration :
- `createNotification($type, $title, $message, $priority, $data)` : Crée une notification
- `markAsRead($notificationId, $userId)` : Marque comme lue
- `getUnreadCount($targetUser)` : Compte les notifications non lues

## Exemple d'utilisation

### Créer une notification simple

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

### Gestion des notifications

```php
// Récupérer les notifications non lues
$unreadNotifications = $notificationManager->getNotifications(null, 'unread', 10);

foreach ($unreadNotifications as $notification) {
    echo "Type: {$notification['type']}\n";
    echo "Titre: {$notification['title']}\n";
    echo "Priorité: {$notification['priority']}\n";
}

// Marquer comme lue
$notificationManager->markAsRead($notificationId, $userId);

// Obtenir le compte non lu
$unreadCount = $notificationManager->getUnreadCount($userId);
echo "Notifications non lues: {$unreadCount}\n";
```

### Configuration email avancée

```php
// Configuration SMTP complète
$notificationManager->setEmailConfig([
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 587,
    'smtp_user' => 'user@example.com',
    'smtp_pass' => 'password',
    'smtp_from' => 'noreply@example.com',
    'smtp_from_name' => 'N3XT WEB System'
]);

// Test d'envoi
$testResult = $notificationManager->testEmailConfiguration();
if ($testResult['success']) {
    echo "Configuration email fonctionnelle\n";
}
```

## Principes communs

### Sécurité
- Protection CSRF sur toutes les actions de gestion des notifications
- Validation et sanitation de tous les contenus de notifications
- Limitation de la taille des messages pour éviter abus
- Protection contre l'injection de logs et scripts malveillants

### Configuration
- Tous les paramètres de notification stockés en base de données
- Configuration email sécurisée avec chiffrement des mots de passe
- Valeurs par défaut sécurisées pour éviter spam et surcharge
- Validation des paramètres de configuration avec retour utilisateur

### Extensibilité
- Architecture modulaire permettant ajout de nouveaux types facilement
- Hooks disponibles pour extension par modules tiers
- API standardisée pour intégration avec systèmes de notification externes
- Système de templates extensible pour personnalisation avancée

### Documentation
- README complet avec exemples détaillés pour tous les cas d'usage
- Commentaires dans le code pour toutes les fonctionnalités complexes
- Documentation API complète avec exemples de retour
- Guide de configuration email et dépannage des problèmes courants

