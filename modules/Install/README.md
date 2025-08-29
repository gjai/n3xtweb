# Install Module - N3XT WEB

## Vue d'ensemble

Le module Install fournit un système complet de gestion et de surveillance de l'installation du système N3XT WEB. Il vérifie en continu les prérequis, surveille l'état de l'installation et fournit des outils de diagnostic pour assurer le bon fonctionnement du système.

## Fonctionnalités

### 🔍 Vérification complète de l'installation
- Contrôle automatique de l'intégrité de l'installation
- Validation des prérequis système (PHP, extensions, permissions)
- Vérification de la connectivité base de données et configuration
- Détection des problèmes d'installation avec suggestions de résolution

### 📊 Surveillance des ressources système
- Monitoring en temps réel de l'espace disque disponible
- Surveillance de l'utilisation mémoire et limites PHP
- Contrôle des permissions de fichiers et répertoires critiques
- Alertes proactives de ressources critiques

### ⚙️ Informations système détaillées
- Affichage complet des informations PHP (version, extensions, configuration)
- Détails du serveur web et environnement d'hébergement
- Métriques de performance et limites système
- Historique des mises à jour et modifications d'installation

### 🛠️ Outils de diagnostic et dépannage
- Tests de connectivité et performance automatisés
- Validation de l'intégrité des fichiers critiques
- Recommandations d'optimisation personnalisées
- Interface de résolution des problèmes guidée

## Configuration

### Paramètres disponibles

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| `enabled` | Active/désactive le module | `true` |
| `auto_check` | Vérifications automatiques | `true` |
| `check_interval` | Intervalle de vérification (secondes) | `300` (5min) |
| `show_php_info` | Affichage détaillé PHP | `true` |
| `show_db_info` | Informations base de données | `true` |
| `show_permissions` | État des permissions | `true` |
| `alert_disk_threshold` | Seuil alerte espace disque (%) | `90` |

### Configuration via interface admin

```php
// Accès au module
$installManager = new InstallManager();

// Configuration des vérifications
$installManager->setConfig('check_interval', 600); // 10 minutes
$installManager->setConfig('alert_disk_threshold', 85);
```

## Administration

**Interface disponible :** `/bo/install.php`

### Tableau de bord
- Statut global de l'installation avec indicateurs visuels
- Résumé des vérifications système avec codes couleur
- Métriques de ressources (CPU, mémoire, disque) en temps réel
- Historique des checks avec tendances d'évolution

### Actions disponibles
- Exécution manuelle de vérifications complètes
- Diagnostic approfondi avec rapport détaillé
- Consultation des recommandations d'optimisation
- Test de connectivité et performance système

## Schema de base de données

### Table `install_checks`

```sql
CREATE TABLE n3xt_install_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    check_type ENUM('requirements', 'permissions', 'resources', 'connectivity') NOT NULL,
    check_name VARCHAR(100) NOT NULL,
    status ENUM('pass', 'warning', 'fail') NOT NULL,
    current_value VARCHAR(255) NULL,
    expected_value VARCHAR(255) NULL,
    message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type_created (check_type, created_at)
);
```

### Table `install_status`

```sql
CREATE TABLE n3xt_install_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    installation_date TIMESTAMP NOT NULL,
    current_version VARCHAR(20) NOT NULL,
    last_update_date TIMESTAMP NULL,
    last_check_date TIMESTAMP NULL,
    overall_status ENUM('complete', 'incomplete', 'error') DEFAULT 'incomplete',
    notes TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Intégration

### Avec les autres modules

**SecurityManager :** Partage des vérifications de sécurité
- Intégration des contrôles de sécurité dans les vérifications système
- Partage des informations de permissions et accès
- Coordination des alertes de sécurité liées à l'installation

**MaintenanceManager :** Utilisation des métriques de ressources
- Partage des données d'espace disque pour nettoyage automatique
- Coordination des tâches de maintenance système
- Optimisation basée sur les recommandations d'installation

**NotificationManager :** Alertes automatiques de problèmes
- Notifications automatiques de problèmes d'installation détectés
- Alertes de ressources critiques (espace disque faible)
- Rapports périodiques de santé du système

### API et hooks

Le module expose les méthodes suivantes pour intégration :
- `checkSystemRequirements()` : Vérifie tous les prérequis système
- `getInstallationStatus()` : Retourne l'état global de l'installation
- `runDiagnostics()` : Lance un diagnostic complet du système

## Exemple d'utilisation

### Vérification de l'état d'installation

```php
$installManager = new InstallManager();

// Vérifier l'état global
$status = $installManager->getInstallationStatus();

echo "Installation: " . ($status['completed'] ? 'Complète' : 'Incomplète') . "\n";
echo "Version: " . $status['version'] . "\n";
echo "Dernière mise à jour: " . ($status['last_update'] ?? 'Jamais') . "\n";

if (!$status['completed']) {
    echo "Problèmes détectés:\n";
    foreach ($status['issues'] as $issue) {
        echo "- " . $issue['message'] . "\n";
    }
}
```

### Vérification des prérequis système

```php
// Lancer les vérifications complètes
$requirements = $installManager->checkSystemRequirements();

echo "=== Vérification des prérequis ===\n";

// Vérification PHP
echo "PHP Version: " . $requirements['php']['current'];
echo " (requis: " . $requirements['php']['required'] . ")";
echo " - " . ($requirements['php']['status'] ? '✓' : '✗') . "\n";

// Extensions PHP
echo "\nExtensions PHP:\n";
foreach ($requirements['extensions'] as $ext => $status) {
    echo "- {$ext}: " . ($status ? '✓ Installée' : '✗ Manquante') . "\n";
}

// Permissions
echo "\nPermissions:\n";
foreach ($requirements['permissions'] as $path => $perm) {
    echo "- {$path}: " . ($perm['writable'] ? '✓ Écriture OK' : '✗ Pas d\'écriture') . "\n";
}
```

### Surveillance des ressources

```php
// Obtenir les métriques de ressources
$resources = $installManager->getResourceMetrics();

echo "=== Ressources système ===\n";
echo "Espace disque libre: " . $resources['disk_free_space'] . "\n";
echo "Utilisation disque: " . $resources['disk_usage_percent'] . "%\n";

if ($resources['disk_usage_percent'] > 90) {
    echo "⚠️ ALERTE: Espace disque critique!\n";
}

echo "Mémoire PHP limite: " . $resources['memory_limit'] . "\n";
echo "Temps d'exécution max: " . $resources['max_execution_time'] . "s\n";
echo "Taille upload max: " . $resources['upload_max_filesize'] . "\n";
```

### Diagnostic complet

```php
// Lancer un diagnostic complet
$diagnostic = $installManager->runDiagnostics();

echo "=== Diagnostic système ===\n";
echo "Statut global: " . $diagnostic['overall_status'] . "\n";
echo "Tests réussis: " . $diagnostic['tests_passed'] . "/" . $diagnostic['total_tests'] . "\n";

if ($diagnostic['warnings']) {
    echo "\nAvertissements:\n";
    foreach ($diagnostic['warnings'] as $warning) {
        echo "⚠️ " . $warning['message'] . "\n";
        if ($warning['recommendation']) {
            echo "   Recommandation: " . $warning['recommendation'] . "\n";
        }
    }
}

if ($diagnostic['errors']) {
    echo "\nErreurs critiques:\n";
    foreach ($diagnostic['errors'] as $error) {
        echo "❌ " . $error['message'] . "\n";
        echo "   Solution: " . $error['solution'] . "\n";
    }
}
```

## Principes communs

### Sécurité
- Protection CSRF sur toutes les actions de diagnostic et vérification
- Accès restreint aux informations système sensibles (administrateurs uniquement)
- Validation de toutes les données système avant affichage
- Pas d'exposition publique des détails de configuration

### Configuration
- Tous les paramètres de vérification stockés en base de données
- Configuration modifiable via interface d'administration sécurisée
- Seuils d'alerte personnalisables selon environnement
- Validation des paramètres avec feedback immédiat

### Extensibilité
- Architecture modulaire permettant ajout de nouvelles vérifications
- Hooks disponibles pour extension des contrôles par modules tiers
- API standardisée pour intégration avec systèmes de monitoring
- Support de plugins pour vérifications personnalisées

### Documentation
- README complet avec guide de résolution des problèmes d'installation
- Commentaires détaillés dans le code pour toutes les vérifications
- Documentation API complète avec exemples de diagnostic
- Guide de dépannage pour problèmes courants d'hébergement