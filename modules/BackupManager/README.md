# BackupManager Module

## Description

Le module BackupManager gère les sauvegardes automatiques et manuelles du système N3XT WEB. Il permet de créer des sauvegardes complètes de la base de données et des fichiers, ainsi que de restaurer le système depuis une sauvegarde.

## Fonctionnalités

### 💾 Création de sauvegardes
- Sauvegardes manuelles et automatiques
- Sauvegarde de la base de données (dump SQL)
- Sauvegarde sélective des fichiers
- Compression ZIP optionnelle
- Support des gros volumes de données

### 🔄 Restauration
- Restauration complète depuis sauvegarde
- Sauvegarde de sécurité avant restauration
- Validation des fichiers de sauvegarde
- Logs détaillés des opérations

### 📊 Gestion et suivi
- Historique complet des sauvegardes
- Statistiques d'utilisation
- Nettoyage automatique des anciennes sauvegardes
- Interface d'administration complète

### ⚙️ Configuration flexible
- Politique de rétention personnalisable
- Exclusion de fichiers/répertoires
- Planification automatique
- Notifications intégrées

## Configuration

### Paramètres disponibles

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| `enabled` | Active/désactive le module | `true` |
| `auto_backup` | Sauvegardes automatiques | `true` |
| `retention_days` | Durée de conservation (jours) | `30` |
| `compression` | Compression ZIP | `true` |
| `include_files` | Inclure les fichiers système | `true` |
| `include_uploads` | Inclure les uploads | `false` |
| `max_backup_size` | Taille max par sauvegarde | `1073741824` (1GB) |

## Utilisation

### Créer une sauvegarde manuelle

```php
$backupManager = new BackupManager();

$result = $backupManager->createBackup('manual', 'Sauvegarde avant mise à jour importante');

if ($result['success']) {
    echo "Sauvegarde créée : " . $result['filename'];
    echo "Taille : " . FileHelper::formatFileSize($result['size']);
}
```

### Restaurer une sauvegarde

```php
$backupId = 123; // ID de la sauvegarde

$result = $backupManager->restoreBackup($backupId);

if ($result['success']) {
    echo "Restauration réussie";
    echo "Fichiers restaurés : " . $result['files_restored'];
    echo "Sauvegarde de sécurité : ID " . $result['security_backup_id'];
}
```

### Lister les sauvegardes

```php
$backups = $backupManager->getBackups(10); // 10 dernières sauvegardes

foreach ($backups as $backup) {
    echo "ID: {$backup['id']}";
    echo "Fichier: {$backup['filename']}";
    echo "Type: {$backup['type']}";
    echo "Taille: " . FileHelper::formatFileSize($backup['size_bytes']);
    echo "Date: {$backup['created_at']}";
}
```

## Types de sauvegardes

### Manual
Sauvegardes créées manuellement par l'administrateur via l'interface ou l'API.

### Automatic
Sauvegardes créées automatiquement selon la planification configurée.

### Pre_update
Sauvegardes créées automatiquement avant une mise à jour système.

## Contenu des sauvegardes

### Base de données
- Dump SQL complet avec structure et données
- Toutes les tables avec le préfixe configuré
- Gestion des contraintes de clés étrangères
- Encoding UTF-8

### Fichiers système
- Tous les fichiers PHP, CSS, JS
- Fichiers de configuration (hors sensibles)
- Assets et ressources
- Documentation

### Exclusions par défaut
- Répertoire `backups/`
- Répertoire `tmp/`
- Répertoire `logs/`
- Fichiers `.log`, `.tmp`, `.cache`
- Uploads utilisateur (configurable)

## Formats de sauvegarde

### ZIP (par défaut)
- Compression efficace
- Support universel
- Intégrité vérifiable
- Extraction facile

### TAR (alternatif)
- Format standard Unix
- Meilleure préservation des permissions
- Support des liens symboliques

## Sécurité

### Validation des sauvegardes
- Vérification de l'intégrité des archives
- Validation des chemins lors de l'extraction
- Protection contre les attaques par chemin
- Sanitation des noms de fichiers

### Permissions
- Vérification des droits d'accès
- Protection CSRF sur toutes les actions
- Logging de toutes les opérations sensibles
- Accès restreint aux administrateurs

## Performance

### Optimisations
- Traitement par chunks pour les gros fichiers
- Compression à la volée
- Nettoyage automatique des fichiers temporaires
- Limitation de la mémoire utilisée

### Surveillance
- Monitoring de l'espace disque
- Alertes de taille maximale
- Timeout sur les opérations longues
- Journalisation des performances

## Base de données

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

## API

### Méthodes principales

#### `createBackup($type, $notes)`
Crée une nouvelle sauvegarde.

**Paramètres :**
- `$type` : Type de sauvegarde ('manual', 'automatic', 'pre_update')
- `$notes` : Notes optionnelles

**Retour :**
```php
[
    'success' => true,
    'id' => 123,
    'filename' => 'backup_file.zip',
    'filepath' => '/path/to/backup.zip',
    'size' => 1048576
]
```

#### `restoreBackup($backupId)`
Restaure une sauvegarde.

#### `deleteBackup($backupId)`
Supprime une sauvegarde.

#### `getBackups($limit)`
Retourne la liste des sauvegardes.

#### `cleanupOldBackups()`
Nettoie les anciennes sauvegardes selon la politique de rétention.

#### `getStatistics()`
Retourne les statistiques des sauvegardes.

## Intégration

### Avec UpdateManager
Création automatique d'une sauvegarde avant chaque mise à jour système.

### Avec NotificationManager
Notifications automatiques pour :
- Sauvegardes créées avec succès
- Échecs de sauvegarde
- Nettoyage des anciennes sauvegardes

### Avec MaintenanceManager
Nettoyage automatique des anciennes sauvegardes lors des tâches de maintenance.

## Interface d'administration

### Tableau de bord
- Statistiques en temps réel
- État des dernières sauvegardes
- Espace disque utilisé
- Actions rapides

### Gestion des sauvegardes
- Liste paginée des sauvegardes
- Filtres par type et statut
- Actions en lot
- Téléchargement des sauvegardes

### Configuration
- Paramètres de rétention
- Options de compression
- Sélection des contenus
- Planification automatique

## Dépannage

### Problèmes courants

#### Échec de création de sauvegarde
- Vérifier l'espace disque disponible
- Contrôler les permissions d'écriture
- Vérifier la configuration PHP (memory_limit, max_execution_time)

#### Échec de restauration
- Vérifier l'intégrité du fichier de sauvegarde
- Contrôler les permissions de la base de données
- Vérifier la compatibilité des versions

#### Sauvegardes trop volumineuses
- Exclure les uploads utilisateur
- Ajuster la politique de rétention
- Utiliser la compression
- Séparer base de données et fichiers

### Logs
Tous les événements sont enregistrés dans `backupmanager.log` avec différents niveaux :
- `INFO` : Opérations normales
- `WARNING` : Problèmes non critiques
- `ERROR` : Échecs d'opérations