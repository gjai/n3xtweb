# UpdateManager Module - N3XT WEB

## Vue d'ensemble

Le module UpdateManager gère le système de mises à jour automatiques du système N3XT WEB depuis GitHub. Il assure la vérification, le téléchargement et l'application sécurisée des mises à jour avec sauvegarde automatique et système de rollback pour garantir la continuité de service.

## Fonctionnalités

### 🔄 Vérification automatique intelligente
- Vérification automatique des mises à jour à chaque connexion administrateur
- Fréquence configurable avec respect du cache pour optimiser les performances
- Comparaison intelligente des versions avec support du semantic versioning
- Détection des mises à jour critiques avec notification prioritaire

### 📥 Téléchargement et validation sécurisés
- Téléchargement depuis les releases GitHub officielles uniquement
- Vérification complète de l'intégrité des fichiers téléchargés
- Validation des signatures et checksums pour sécurité maximale
- Stockage temporaire sécurisé avec isolation des fichiers

### 🛡️ Sauvegarde et protection automatiques
- Sauvegarde automatique complète avant chaque mise à jour
- Protection intégrée via BackupManager avec politique de rétention
- Mode maintenance automatique pendant les opérations critiques
- Système de rollback automatique en cas d'échec détecté

### 📊 Suivi et logging complets
- Historique détaillé de toutes les mises à jour effectuées
- Logs complets de chaque étape du processus
- Notifications automatiques via NotificationManager
- Monitoring des performances et temps d'exécution

## Configuration

### Paramètres disponibles

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| `enabled` | Active/désactive le module | `true` |
| `auto_check` | Vérification automatique | `true` |
| `github_repo` | Dépôt GitHub source | `gjai/n3xtweb` |
| `check_frequency` | Fréquence de vérification (secondes) | `86400` (24h) |
| `auto_backup` | Sauvegarde automatique avant mise à jour | `true` |
| `maintenance_mode` | Mode maintenance pendant mise à jour | `true` |
| `rollback_enabled` | Rollback automatique en cas d'échec | `true` |

### Configuration via interface admin

```php
// Accès au module
$updateManager = new UpdateManager();

// Modifier la configuration
$updateManager->setConfig('check_frequency', 43200); // 12 heures
$updateManager->setConfig('auto_backup', true);
$updateManager->setConfig('maintenance_mode', true);
```

## Administration

**Interface disponible :** `/bo/update.php`

### Tableau de bord
- Statut de la dernière vérification de mise à jour
- Version actuelle installée et dernière version disponible
- Historique des mises à jour avec statuts détaillés
- Statistiques des opérations et temps d'exécution

### Actions disponibles
- Vérification manuelle forcée des mises à jour disponibles
- Déclenchement manuel du processus de mise à jour
- Consultation détaillée de l'historique avec logs
- Configuration des paramètres et politiques de mise à jour

## Schema de base de données

### Table `update_history`

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

### Avec les autres modules

**BackupManager :** Sauvegardes automatiques avant mises à jour
- Création automatique d'une sauvegarde de type 'pre_update'
- Validation de la sauvegarde avant proceeding avec la mise à jour
- Rétention prolongée pour les sauvegardes pré-mise à jour

**NotificationManager :** Notifications complètes du processus
- Notification de nouvelles versions disponibles avec détails
- Alertes en temps réel du progrès de mise à jour
- Notifications de succès/échec avec informations contextuelles

**MaintenanceManager :** Coordination des opérations système
- Activation automatique du mode maintenance pendant mise à jour
- Coordination pour éviter conflits avec tâches de maintenance
- Nettoyage des fichiers temporaires post-mise à jour

### API et hooks

Le module expose les méthodes suivantes pour intégration :
- `checkForUpdates()` : Vérifie la disponibilité de mises à jour
- `downloadUpdate($downloadUrl, $version)` : Télécharge une mise à jour
- `applyUpdate($updateId)` : Applique une mise à jour téléchargée

## Exemple d'utilisation

### Vérification manuelle des mises à jour

```php
$updateManager = new UpdateManager();

try {
    $result = $updateManager->checkForUpdates();
    
    if ($result['update_available']) {
        echo "Mise à jour disponible: " . $result['latest_version'] . "\n";
        echo "Version actuelle: " . $result['current_version'] . "\n";
        echo "URL de téléchargement: " . $result['download_url'] . "\n";
        
        // Informations de la release
        echo "Notes de version: " . $result['release_info']['body'] . "\n";
    } else {
        echo "Système à jour - Version: " . $result['current_version'] . "\n";
    }
} catch (Exception $e) {
    echo "Erreur lors de la vérification: " . $e->getMessage() . "\n";
}
```

### Processus complet de mise à jour

```php
// 1. Vérifier les mises à jour
$checkResult = $updateManager->checkForUpdates();

if ($checkResult['update_available']) {
    // 2. Télécharger la mise à jour
    $download = $updateManager->downloadUpdate(
        $checkResult['download_url'], 
        $checkResult['latest_version']
    );
    
    if ($download['success']) {
        echo "Téléchargement réussi: " . $download['filename'] . "\n";
        
        // 3. Appliquer la mise à jour
        $result = $updateManager->applyUpdate($download['update_id']);
        
        if ($result['success']) {
            echo "Mise à jour appliquée avec succès!\n";
            echo "Version: " . $result['version'] . "\n";
            echo "Fichiers mis à jour: " . $result['files_updated'] . "\n";
        } else {
            echo "Erreur lors de l'application: " . $result['error'] . "\n";
        }
    }
}
```

### Consultation de l'historique

```php
$history = $updateManager->getUpdateHistory(10); // 10 dernières mises à jour

foreach ($history as $update) {
    echo "=== Mise à jour #{$update['id']} ===\n";
    echo "Version: {$update['version_from']} → {$update['version_to']}\n";
    echo "Type: {$update['update_type']}\n";
    echo "Statut: {$update['status']}\n";
    echo "Date: {$update['started_at']}\n";
    if ($update['completed_at']) {
        echo "Durée: " . $updateManager->calculateDuration($update) . "\n";
    }
    echo "\n";
}
```

## Principes communs

### Sécurité
- Protection CSRF sur toutes les actions de mise à jour
- Vérification des permissions administrateur avant toute opération
- Validation des sources et intégrité des fichiers téléchargés
- Exclusion automatique des répertoires sensibles lors des mises à jour

### Configuration
- Tous les paramètres de mise à jour stockés en base de données
- Configuration modifiable via interface d'administration sécurisée
- Valeurs par défaut sécurisées pour éviter interruptions de service
- Validation des paramètres avec feedback utilisateur immédiat

### Extensibilité
- Architecture modulaire permettant ajout de sources de mise à jour
- Hooks disponibles pour extension par modules tiers
- API standardisée pour intégration avec systèmes de déploiement
- Support de plugins pour validation personnalisée

### Documentation
- README complet avec exemples détaillés pour tous les scénarios
- Commentaires dans le code pour toutes les opérations critiques
- Documentation API complète avec codes de retour détaillés
- Guide de dépannage pour résolution des problèmes courants
