# MaintenanceManager Module

## Description

Le module MaintenanceManager gère la maintenance automatique et manuelle du système N3XT WEB. Il nettoie les fichiers obsolètes, optimise la base de données et maintient les performances système.

## Fonctionnalités

### 🧹 Nettoyage automatique
- Suppression des anciens logs
- Nettoyage des sauvegardes expirées
- Suppression des fichiers temporaires
- Nettoyage des sessions PHP expirées

### 📦 Archivage avant suppression
- Compression ZIP des fichiers avant suppression
- Conservation temporaire des archives
- Politique d'archivage configurable
- Récupération possible des fichiers archivés

### ⚡ Optimisation de la base de données
- Optimisation automatique des tables
- Défragmentation des index
- Nettoyage des données obsolètes
- Surveillance de l'espace disque

### 📊 Surveillance et rapports
- Historique détaillé des maintenances
- Statistiques d'espace libéré
- Monitoring des performances
- Notifications intégrées

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

## Utilisation

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
    echo "Tâche: {$task}";
    echo "Fichiers supprimés: {$taskResult['files_deleted']}";
    echo "Espace libéré: " . FileHelper::formatFileSize($taskResult['space_freed']);
}
```

### Nettoyage spécifique

```php
// Nettoyer seulement les logs
$result = $maintenanceManager->cleanupLogs();
echo "Logs supprimés: {$result['files_deleted']}";

// Optimiser la base de données
$result = $maintenanceManager->optimizeDatabase();
echo "Tables optimisées: {$result['tables_optimized']}";
```

### Nettoyage automatique

```php
// Exécuter le nettoyage automatique selon la planification
$result = $maintenanceManager->runAutomaticCleanup();
```

## Types de nettoyage

### Logs
- Suppression des logs anciens selon la rétention configurée
- Suppression des logs volumineux (> taille max)
- Archivage optionnel avant suppression
- Préservation des logs récents critiques

**Fichiers concernés :**
- `*.log` dans le répertoire logs
- Logs rotatés (`.log.1`, `.log.2`, etc.)
- Logs système et d'application

### Sauvegardes
- Suppression des sauvegardes expirées
- Respect de la politique de rétention
- Intégration avec BackupManager
- Archivage des métadonnées

**Critères de suppression :**
- Âge selon `backup_retention_days`
- Statut de la sauvegarde
- Type de sauvegarde (pré-update conservées plus longtemps)

### Fichiers temporaires
- Nettoyage des répertoires temporaires
- Suppression des fichiers d'upload temporaires
- Nettoyage des caches expirés
- Suppression des sessions PHP obsolètes

**Répertoires nettoyés :**
- `/tmp`
- `/uploads/tmp`
- `sys_get_temp_dir()`
- Sessions PHP

### Base de données
- Optimisation des tables MySQL
- Défragmentation des index
- Nettoyage des données temporaires
- Mise à jour des statistiques

## Archivage

### Fonctionnement
Avant la suppression, les fichiers peuvent être archivés selon la configuration :

1. **Compression ZIP** : Création d'archives par catégorie
2. **Stockage temporaire** : Conservation dans `/backups/archives/`
3. **Nettoyage différé** : Suppression des archives après délai
4. **Récupération** : Possibilité de restaurer depuis archive

### Structure des archives
```
/backups/archives/
├── logs/
│   ├── logs_archive_2024-01-15_10-30-00.zip
│   └── logs_archive_2024-01-14_10-30-00.zip
├── backups/
│   └── backups_archive_2024-01-10_10-30-00.zip
└── temp/
    └── temp_archive_2024-01-15_10-30-00.zip
```

## Planification

### Nettoyage automatique
Le nettoyage automatique s'exécute selon la configuration :

- **Daily** : Chaque jour lors de la première connexion admin
- **Weekly** : Une fois par semaine
- **Manual** : Uniquement sur demande

### Déclencheurs
- Connexion administrateur (vérification de planification)
- Appel API direct
- Interface d'administration
- Tâche cron (si configurée)

## Sécurité

### Protections
- Vérification des permissions administrateur
- Validation CSRF sur toutes les actions
- Protection contre les chemins dangereux
- Logging de toutes les opérations

### Exclusions de sécurité
Fichiers/répertoires jamais touchés :
- Fichiers de configuration sensibles
- Clés et certificats
- Fichiers système critiques
- Répertoires protégés par `.htaccess`

## Performance

### Optimisations
- Traitement par lots pour éviter les timeouts
- Limitation de mémoire sur les gros fichiers
- Nettoyage incrémental
- Monitoring des ressources système

### Surveillance
- Temps d'exécution des tâches
- Espace disque libéré
- Nombre de fichiers traités
- Erreurs et avertissements

## Base de données

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

## API

### Méthodes principales

#### `runAutomaticCleanup()`
Exécute le nettoyage automatique complet.

#### `forceCleanup($tasks)`
Force le nettoyage manuel des tâches spécifiées.

**Paramètres :**
- `$tasks` : Array des tâches ('logs', 'backups', 'temp_files', 'database')

#### `cleanupLogs()`
Nettoie les anciens logs.

#### `cleanupBackups()`
Nettoie les anciennes sauvegardes.

#### `cleanupTempFiles()`
Nettoie les fichiers temporaires.

#### `optimizeDatabase()`
Optimise la base de données.

#### `getMaintenanceHistory($limit)`
Retourne l'historique des maintenances.

#### `getStatistics()`
Retourne les statistiques de maintenance.

## Interface d'administration

### Tableau de bord
- Espace total libéré
- Dernière maintenance
- Prochaine maintenance prévue
- Statistiques par type de tâche

### Actions rapides
- Nettoyage individuel par catégorie
- Maintenance complète en un clic
- Optimisation base de données
- Nettoyage automatique forcé

### Historique
- Journal détaillé des maintenances
- Durée et performances
- Erreurs et avertissements
- Statistiques d'espace libéré

### Configuration
- Politique de rétention
- Fréquence de nettoyage
- Options d'archivage
- Seuils d'alerte

## Intégration

### Avec NotificationManager
Notifications automatiques pour :
- Nettoyage automatique terminé
- Erreurs de maintenance
- Espace disque critique
- Maintenance programmée

### Avec BackupManager
- Utilisation de l'API BackupManager pour nettoyer les sauvegardes
- Respect des politiques de rétention partagées
- Coordination des tâches de nettoyage

### Avec le système de logs
- Logging de toutes les opérations
- Rotation des logs de maintenance
- Archivage des logs anciens

## Monitoring

### Métriques surveillées
- Espace disque total/utilisé/libre
- Nombre de fichiers par catégorie
- Fréquence des nettoyages
- Temps d'exécution des tâches

### Alertes
- Espace disque critique (< 10%)
- Échec de maintenance répétés
- Tâches bloquées/timeouts
- Croissance anormale des logs

## Dépannage

### Problèmes courants

#### Nettoyage bloqué
- Vérifier les permissions de fichiers
- Contrôler l'espace disque disponible
- Vérifier les processus en cours

#### Fichiers non supprimés
- Permissions insuffisantes
- Fichiers verrouillés par processus
- Exclusions de sécurité

#### Base de données non optimisée
- Permissions MySQL insuffisantes
- Tables InnoDB verrouillées
- Espace disque insuffisant

### Logs de débogage
Activer le niveau DEBUG pour diagnostiquer :
```php
$maintenanceManager->setConfig('debug_mode', true);
```