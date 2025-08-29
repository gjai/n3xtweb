# BackupManager Module - N3XT WEB

## Vue d'ensemble

Le module BackupManager fournit un système complet de sauvegarde et restauration pour le système N3XT WEB. Il gère les sauvegardes automatiques et manuelles de la base de données et des fichiers, avec des fonctionnalités avancées de validation, compression et gestion de rétention.

## Fonctionnalités

### 💾 Création de sauvegardes intelligentes
- Sauvegardes manuelles et automatiques avec planification flexible
- Sauvegarde complète de la base de données (dump SQL optimisé)
- Sauvegarde sélective des fichiers avec filtres personnalisables
- Compression ZIP efficace avec niveaux configurables
- Support des gros volumes de données avec traitement par chunks

### 🔄 Restauration sécurisée
- Restauration complète depuis sauvegarde avec validation
- Sauvegarde de sécurité automatique avant restauration
- Validation de l'intégrité des fichiers de sauvegarde
- Logs détaillés de toutes les opérations de restauration
- Interface de gestion intuitive pour les opérations

### 📊 Gestion et suivi avancés
- Historique complet de toutes les sauvegardes
- Statistiques détaillées d'utilisation et performances
- Nettoyage automatique des anciennes sauvegardes
- Interface d'administration complète et moderne
- Monitoring de l'espace disque et alertes

### ⚙️ Configuration flexible
- Politique de rétention personnalisable par type
- Exclusion intelligente de fichiers/répertoires
- Planification automatique avec multiple fréquences
- Notifications intégrées pour tous les événements
- Support multi-environnements avec configuration adaptative

## Configuration

### Paramètres disponibles

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| `enabled` | Active/désactive le module | `true` |
| `auto_backup` | Sauvegardes automatiques | `true` |
| `retention_days` | Durée de conservation (jours) | `30` |
| `compression` | Compression ZIP activée | `true` |
| `compression_level` | Niveau de compression (0-9) | `6` |
| `include_files` | Inclure les fichiers système | `true` |
| `include_uploads` | Inclure les uploads utilisateur | `false` |
| `max_backup_size` | Taille max par sauvegarde (bytes) | `1073741824` (1GB) |

### Configuration via interface admin

```php
// Accès au module
$backupManager = new BackupManager();

// Modifier la configuration
$backupManager->setConfig('retention_days', 60);
$backupManager->setConfig('compression_level', 9);
```

## Administration

**Interface disponible :** `/bo/restore.php`

### Tableau de bord
- Statistiques en temps réel des sauvegardes
- État des dernières opérations de sauvegarde
- Espace disque utilisé par les sauvegardes
- Actions rapides pour opérations courantes

### Actions disponibles
- Création manuelle de sauvegarde complète ou sélective
- Restauration depuis sauvegarde avec prévisualisation
- Téléchargement des fichiers de sauvegarde
- Gestion et nettoyage des anciennes sauvegardes

## Schema de base de données

### Table `backups`

```sql
CREATE TABLE n3xt_backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    type ENUM('manual', 'automatic', 'pre_update') NOT NULL,
    backup_type ENUM('full', 'database', 'files') DEFAULT 'full',
    size_bytes BIGINT NOT NULL,
    compressed BOOLEAN DEFAULT TRUE,
    status ENUM('creating', 'completed', 'failed', 'deleted') DEFAULT 'creating',
    created_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    notes TEXT NULL,
    metadata JSON NULL
);
```

## Intégration

### Avec les autres modules

**UpdateManager :** Sauvegardes automatiques avant mises à jour
- Création automatique d'une sauvegarde avant chaque mise à jour système
- Sauvegarde de type 'pre_update' avec rétention prolongée
- Intégration dans le processus de mise à jour pour sécurité maximale

**NotificationManager :** Notifications complètes des opérations
- Notifications automatiques de fin de sauvegarde (succès/échec)
- Alertes d'espace disque insuffisant pour nouvelles sauvegardes
- Notifications de nettoyage automatique des anciennes sauvegardes

**MaintenanceManager :** Nettoyage automatique coordonné
- Nettoyage automatique des anciennes sauvegardes selon politique
- Respect des règles de rétention partagées entre modules
- Coordination pour éviter conflits lors des opérations

### API et hooks

Le module expose les méthodes suivantes pour intégration :
- `createBackup($type, $notes)` : Crée une nouvelle sauvegarde
- `restoreBackup($backupId)` : Restaure depuis une sauvegarde
- `getBackups($limit)` : Liste les sauvegardes disponibles

## Exemple d'utilisation

### Créer une sauvegarde manuelle

```php
$backupManager = new BackupManager();

$result = $backupManager->createBackup('manual', 'Sauvegarde avant mise à jour importante');

if ($result['success']) {
    echo "Sauvegarde créée : " . $result['filename'] . "\n";
    echo "Taille : " . FileHelper::formatFileSize($result['size']) . "\n";
    echo "ID : " . $result['id'] . "\n";
}
```

### Restaurer une sauvegarde

```php
$backupId = 123; // ID de la sauvegarde

$result = $backupManager->restoreBackup($backupId);

if ($result['success']) {
    echo "Restauration réussie\n";
    echo "Fichiers restaurés : " . $result['files_restored'] . "\n";
    echo "Sauvegarde de sécurité : ID " . $result['security_backup_id'] . "\n";
} else {
    echo "Erreur : " . $result['error'] . "\n";
}
```

### Gestion des sauvegardes

```php
// Lister les dernières sauvegardes
$backups = $backupManager->getBackups(10);

foreach ($backups as $backup) {
    echo "ID: {$backup['id']} - ";
    echo "Fichier: {$backup['filename']} - ";
    echo "Type: {$backup['type']} - ";
    echo "Taille: " . FileHelper::formatFileSize($backup['size_bytes']) . " - ";
    echo "Date: {$backup['created_at']}\n";
}

// Nettoyer les anciennes sauvegardes
$cleaned = $backupManager->cleanupOldBackups();
echo "Sauvegardes supprimées : " . $cleaned['count'] . "\n";
```

## Principes communs

### Sécurité
- Protection CSRF sur toutes les actions de sauvegarde/restauration
- Validation de l'intégrité des archives avant extraction
- Protection contre les attaques par chemin lors de l'extraction
- Vérification des permissions administrateur pour toutes opérations

### Configuration
- Tous les paramètres de sauvegarde stockés en base de données
- Configuration modifiable via interface d'administration intuitive
- Valeurs par défaut sécurisées pour protection des données
- Validation des paramètres de rétention et limites de taille

### Extensibilité
- Architecture modulaire permettant ajout de nouveaux types de sauvegarde
- Hooks disponibles pour extension par d'autres modules
- API standardisée pour intégration avec systèmes de sauvegarde externes
- Support de plugins pour formats de sauvegarde personnalisés

### Documentation
- README complet avec exemples détaillés d'utilisation
- Commentaires dans le code pour toutes les opérations complexes
- Documentation API complète pour toutes les méthodes publiques
- Guide de dépannage et résolution des problèmes courants

