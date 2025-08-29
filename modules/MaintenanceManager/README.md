# MaintenanceManager Module - N3XT WEB

## Vue d'ensemble

Le module MaintenanceManager fournit un système complet de maintenance automatisée et manuelle pour le système N3XT WEB. Il gère le nettoyage intelligent des fichiers obsolètes, l'optimisation de la base de données et le maintien des performances globales du système.

## Fonctionnalités

### 🧹 Nettoyage automatique intelligent
- Suppression des anciens logs selon politique de rétention
- Nettoyage des sauvegardes expirées avec respect des règles
- Suppression des fichiers temporaires et sessions PHP obsolètes
- Planification automatique et déclenchement intelligent

### 📦 Archivage avant suppression
- Compression ZIP des fichiers avant suppression définitive
- Conservation temporaire des archives avec politique configurable
- Récupération possible des fichiers archivés en cas de besoin
- Structure d'archivage organisée par catégories et dates

### ⚡ Optimisation de la base de données
- Optimisation automatique des tables MySQL
- Défragmentation des index pour améliorer les performances
- Nettoyage des données temporaires et obsolètes
- Surveillance continue de l'espace disque et mémoire

### 📊 Surveillance et rapports détaillés
- Historique complet de toutes les opérations de maintenance
- Statistiques précises d'espace disque libéré
- Monitoring des performances et temps d'exécution
- Notifications automatiques via NotificationManager

## Configuration

### Paramètres disponibles

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| `enabled` | Active/désactive le module | `true` |
| `auto_cleanup` | Nettoyage automatique | `true` |
| `log_retention_days` | Rétention des logs (jours) | `7` |
| `backup_retention_days` | Rétention des sauvegardes (jours) | `30` |
| `temp_cleanup_hours` | Nettoyage fichiers temp (heures) | `24` |
| `archive_before_delete` | Archiver avant suppression | `true` |
| `cleanup_schedule` | Fréquence ('daily', 'weekly') | `daily` |
| `max_log_size_mb` | Taille max des logs (MB) | `50` |

### Configuration via interface admin

```php
// Accès au module
$maintenanceManager = new MaintenanceManager();

// Modifier la configuration
$maintenanceManager->setConfig('log_retention_days', 14);
$maintenanceManager->setConfig('auto_cleanup', true);
```

## Administration

**Interface disponible :** `/bo/maintenance.php`

### Tableau de bord
- Espace total libéré lors des dernières maintenances
- Statut de la dernière maintenance exécutée
- Prochaine maintenance automatique prévue
- Statistiques détaillées par type de tâche

### Actions disponibles
- Nettoyage manuel complet en un clic
- Nettoyage sélectif par catégorie (logs, backups, temp, database)
- Optimisation forcée de la base de données
- Consultation de l'historique des maintenances

## Schema de base de données

### Table `maintenance_logs`

```sql
CREATE TABLE n3xt_maintenance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_type ENUM('cleanup_logs', 'cleanup_backups', 'cleanup_temp', 'archive', 'optimize_db') NOT NULL,
    status ENUM('running', 'completed', 'failed') NOT NULL,
    files_processed INT DEFAULT 0,
    files_deleted INT DEFAULT 0,
    files_archived INT DEFAULT 0,
    space_freed BIGINT DEFAULT 0,
    duration_seconds INT DEFAULT 0,
    details TEXT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    created_by VARCHAR(50) NOT NULL DEFAULT 'system'
);
```

## Intégration

### Avec les autres modules

**BackupManager :** Intégration pour le nettoyage intelligent des sauvegardes
- Utilisation de l'API BackupManager pour nettoyer les anciennes sauvegardes
- Respect des politiques de rétention partagées
- Coordination des tâches de nettoyage pour éviter les conflits

**NotificationManager :** Notifications automatiques des opérations
- Notifications de fin de maintenance automatique
- Alertes en cas d'erreur ou d'espace disque critique
- Rapports périodiques de maintenance programmée

**EventManager :** Journalisation complète des activités
- Logging de toutes les opérations de maintenance
- Rotation automatique des logs de maintenance
- Archivage des logs anciens selon politique

### API et hooks

Le module expose les méthodes suivantes pour intégration :
- `runAutomaticCleanup()` : Exécute la maintenance automatique complète
- `forceCleanup($tasks)` : Force le nettoyage des tâches spécifiées
- `getMaintenanceHistory($limit)` : Retourne l'historique des maintenances

## Exemple d'utilisation

### Nettoyage manuel complet

```php
$maintenanceManager = new MaintenanceManager();

$result = $maintenanceManager->forceCleanup([
    'logs', 
    'backups', 
    'temp_files', 
    'database'
]);

foreach ($result as $task => $taskResult) {
    echo "Tâche: {$task}\n";
    echo "Fichiers supprimés: {$taskResult['files_deleted']}\n";
    echo "Espace libéré: " . FileHelper::formatFileSize($taskResult['space_freed']) . "\n";
}
```

### Nettoyage sélectif par catégorie

```php
// Nettoyer seulement les logs
$result = $maintenanceManager->cleanupLogs();
echo "Logs supprimés: {$result['files_deleted']}\n";

// Optimiser la base de données
$result = $maintenanceManager->optimizeDatabase();
echo "Tables optimisées: {$result['tables_optimized']}\n";

// Nettoyage automatique selon planification
$result = $maintenanceManager->runAutomaticCleanup();
```

## Principes communs

### Sécurité
- Protection CSRF sur toutes les actions de maintenance
- Vérification des permissions administrateur avant opérations
- Validation des chemins pour éviter suppression accidentelle
- Logging de toutes les opérations sensibles avec traçabilité

### Configuration
- Tous les paramètres stockés en base de données
- Configuration modifiable via interface d'administration
- Valeurs par défaut sécurisées pour éviter perte de données
- Validation des paramètres de rétention et seuils

### Extensibilité
- Architecture modulaire permettant ajout de nouveaux types de nettoyage
- Hooks disponibles pour extension par d'autres modules
- API standardisée pour intégration avec systèmes externes
- Système de plugins pour tâches personnalisées

### Documentation
- README complet avec exemples pratiques d'utilisation
- Commentaires détaillés dans le code pour parties complexes
- Documentation API complète pour toutes les méthodes publiques
- Guide de configuration et dépannage intégré