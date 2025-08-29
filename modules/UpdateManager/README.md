# UpdateManager Module


## Overview
The UpdateManager module handles system updates, version management, and deployment processes for the N3XT WEB system.

## Features
- **Automatic Updates**: GitHub integration for automatic updates
- **Manual Updates**: ZIP upload functionality
- **Update Validation**: Pre-update system checks
- **Rollback Capability**: Automatic rollback on failure
- **Backup Integration**: Automatic backup before updates
- **Maintenance Mode**: System protection during updates
- **Update Notifications**: Email alerts for update completion
- **Version Tracking**: Comprehensive version history

## Configuration
Module configuration is stored in the `{prefix}update_config` table:

| Setting | Default | Description |
|---------|---------|-------------|
| `update_check_frequency` | 24 | Hours between update checks |
| `update_auto_backup` | 1 | Create backup before update |
| `update_github_owner` | gjai | GitHub repository owner |
| `update_github_repo` | n3xtweb | GitHub repository name |
| `update_maintenance_mode` | 1 | Enable maintenance during update |
| `update_notification_email` | '' | Email for notifications |
| `update_rollback_enabled` | 1 | Enable automatic rollback |
| `update_exclude_files` | config/config.php,uploads/ | Files to exclude |

## Administration
Update management is available through the back office at `/bo/update.php`.

## Database Schema
The module uses the `{prefix}update_config` table for configuration.

## Integration
Integrates with the existing update functionality in `/bo/update.php`.
=======
## Description

Le module UpdateManager gère les mises à jour automatiques du système N3XT WEB depuis GitHub. Il vérifie automatiquement la disponibilité de nouvelles versions, télécharge les mises à jour et les applique de manière sécurisée.

## Fonctionnalités

### 🔄 Vérification automatique
- Vérification automatique des mises à jour à chaque connexion admin
- Configurable avec fréquence personnalisable (par défaut : 24h)
- Comparaison intelligente des versions (semantic versioning)

### 📥 Téléchargement sécurisé
- Téléchargement depuis les releases GitHub officielles
- Vérification de l'intégrité des fichiers
- Stockage temporaire sécurisé

### 🛡️ Sauvegarde automatique
- Sauvegarde automatique avant mise à jour (via BackupManager)
- Protection contre la perte de données
- Possibilité de rollback (roadmap)

### 📊 Suivi et logging
- Historique complet des mises à jour
- Logs détaillés de chaque opération
- Notifications intégrées via NotificationManager

## Configuration

### Paramètres disponibles

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| `enabled` | Active/désactive le module | `true` |
| `auto_check` | Vérification automatique | `true` |
| `github_repo` | Dépôt GitHub source | `gjai/n3xtweb` |
| `check_frequency` | Fréquence de vérification (secondes) | `86400` (24h) |
| `auto_backup` | Sauvegarde automatique | `true` |

### Configuration via interface admin

```php
// Accès au module
$updateManager = new UpdateManager();

// Modifier la configuration
$updateManager->setConfig('check_frequency', 43200); // 12 heures
$updateManager->setConfig('auto_backup', true);
```

## Utilisation

### Vérification manuelle des mises à jour

```php
$updateManager = new UpdateManager();

try {
    $result = $updateManager->checkForUpdates();
    
    if ($result['update_available']) {
        echo "Mise à jour disponible: " . $result['latest_version'];
        echo "Version actuelle: " . $result['current_version'];
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}
```

### Téléchargement et application

```php
// Télécharger une mise à jour
$download = $updateManager->downloadUpdate($downloadUrl, $version);

if ($download['success']) {
    // Appliquer la mise à jour
    $result = $updateManager->applyUpdate($download['update_id']);
    
    if ($result['success']) {
        echo "Mise à jour appliquée avec succès!";
        echo "Fichiers mis à jour: " . $result['files_updated'];
    }
}
```

### Consultation de l'historique

```php
$history = $updateManager->getUpdateHistory(10); // 10 dernières mises à jour

foreach ($history as $update) {
    echo "Version: {$update['version_from']} → {$update['version_to']}\n";
    echo "Statut: {$update['status']}\n";
    echo "Date: {$update['started_at']}\n";
    echo "---\n";
}
```

## Sécurité

### Mesures de protection
- Vérification des permissions admin avant toute opération
- Validation CSRF sur toutes les actions
- Exclusion des répertoires sensibles lors de la mise à jour
- Sauvegarde automatique avant application

### Fichiers/répertoires protégés
- `config/` - Configuration système
- `uploads/` - Fichiers utilisateur
- `logs/` - Journaux système
- `backups/` - Sauvegardes
- `tmp/` - Fichiers temporaires

## API et méthodes principales

### Méthodes publiques

#### `checkForUpdates()`
Vérifie la disponibilité d'une mise à jour depuis GitHub.

**Retour :**
```php
[
    'update_available' => bool,
    'current_version' => string,
    'latest_version' => string,
    'download_url' => string,
    'release_info' => array
]
```

#### `downloadUpdate($downloadUrl, $version)`
Télécharge une mise à jour depuis l'URL fournie.

**Paramètres :**
- `$downloadUrl` : URL de téléchargement de la release
- `$version` : Version à télécharger

**Retour :**
```php
[
    'success' => bool,
    'update_id' => int,
    'filepath' => string,
    'filename' => string,
    'size' => int
]
```

#### `applyUpdate($updateId)`
Applique une mise à jour téléchargée.

**Paramètres :**
- `$updateId` : ID de la mise à jour dans l'historique

**Retour :**
```php
[
    'success' => bool,
    'version' => string,
    'files_updated' => int
]
```

#### `getUpdateHistory($limit = 50)`
Retourne l'historique des mises à jour.

#### `getStatus()`
Retourne l'état actuel du système de mise à jour.

## Base de données

### Table `update_history`
Stocke l'historique de toutes les mises à jour.

```sql
CREATE TABLE n3xt_update_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version_from VARCHAR(20) NOT NULL,
    version_to VARCHAR(20) NOT NULL,
    update_type ENUM('automatic', 'manual', 'zip_upload') NOT NULL,
    status ENUM('checking', 'downloading', 'backing_up', 'applying', 'completed', 'failed', 'rolled_back') NOT NULL,
    backup_id INT NULL,
    download_url VARCHAR(500) NULL,
    file_path VARCHAR(500) NULL,
    progress_percent TINYINT DEFAULT 0,
    error_message TEXT NULL,
    files_updated INT DEFAULT 0,
    started_by VARCHAR(50) NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    notes TEXT NULL
);
```

## Intégration

### Avec BackupManager
Le module crée automatiquement une sauvegarde avant chaque mise à jour si le BackupManager est disponible et activé.

### Avec NotificationManager
Les événements importants (mise à jour disponible, succès, échec) génèrent automatiquement des notifications.

### Dans le back office
Le module s'intègre dans l'interface d'administration pour permettre :
- Vérification manuelle des mises à jour
- Consultation de l'historique
- Configuration des paramètres
- Suivi du progrès en temps réel

## Logs

Tous les événements sont journalisés avec différents niveaux :
- `INFO` : Vérifications de routine, démarrage d'opérations
- `WARNING` : Erreurs non critiques
- `ERROR` : Échecs d'opérations importantes

Les logs sont accessibles dans le fichier `updatemanager.log` et via l'interface d'administration.

## Dépendances

### Requises
- PHP 7.4+
- Extension cURL ou allow_url_fopen
- Extension ZipArchive
- Permissions d'écriture sur les répertoires système

### Optionnelles
- BackupManager (pour sauvegardes automatiques)
- NotificationManager (pour notifications)

## Roadmap

### Version 1.1
- [ ] Système de rollback automatique
- [ ] Validation des checksums
- [ ] Mode maintenance automatique pendant mise à jour

### Version 1.2
- [ ] Mise à jour incrémentale (deltas)
- [ ] Planification des mises à jour
- [ ] Interface de prévisualisation des changements
