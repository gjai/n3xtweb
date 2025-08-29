# Install Module - N3XT WEB

## Vue d'ensemble

Le module Install fournit un système complet de gestion et de surveillance de l'installation du système N3XT WEB. Il inclut une **routine de pré-check automatique** qui vérifie tous les prérequis système avant l'installation pour sécuriser et fiabiliser le parcours d'installation.

## 🔍 Routine de pré-check automatique

### Vérifications effectuées

Le système effectue automatiquement les vérifications suivantes avant toute installation :

#### ✅ Version PHP
- **Minimum requis** : PHP 7.4.0 ou supérieur
- **Vérification** : Compatible avec PHP 8.x
- **Diagnostic** : Affichage de la version actuelle et comparaison

#### ✅ Extensions PHP requises
- **mysqli** : Accès aux bases de données MySQL/MariaDB
- **json** : Manipulation des données JSON
- **mbstring** : Gestion des chaînes multi-octets (UTF-8)
- **curl** : Communications HTTP/HTTPS
- **openssl** : Chiffrement et certificats SSL
- **gd** : Manipulation d'images

#### ✅ Permissions des dossiers critiques
- **config/** : Lecture/écriture pour les fichiers de configuration
- **uploads/** : Stockage des fichiers téléchargés
- **logs/** : Écriture des journaux système
- **backups/** : Sauvegarde automatique

#### ✅ Fichier de configuration
- **config/config.php** : Vérification d'existence (installation déjà effectuée)
- **config/config.template.php** : Présence du modèle de configuration

#### ✅ Espace disque disponible
- **Minimum** : 100 MB d'espace libre
- **Recommandé** : 500 MB d'espace libre
- **Diagnostic** : Pourcentage d'utilisation et espace disponible

#### ✅ Connexion base de données
- Test de connectivité avec les paramètres fournis
- Validation des droits d'accès
- Vérification de l'existence de la base

## 🛠️ Dépendances système

### Serveur web
- **Apache 2.4+** ou **Nginx 1.14+**
- **Modules requis** : mod_rewrite (Apache), try_files (Nginx)

### PHP Configuration
```ini
; Configuration PHP minimale recommandée
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 50M
post_max_size = 50M
max_input_vars = 3000
```

### Base de données
- **MySQL 5.7+** ou **MariaDB 10.3+**
- **Droits requis** : CREATE, ALTER, INSERT, UPDATE, DELETE, SELECT

### Système de fichiers
- **Permissions** : 755 pour les dossiers, 644 pour les fichiers
- **Propriétaire** : www-data ou utilisateur du serveur web

## 🚨 Guide de diagnostic et dépannage

### Problèmes PHP courants

#### ❌ Version PHP obsolète
**Symptôme** : "Version PHP X.X.X trop ancienne"
```bash
# Vérifier la version actuelle
php -v

# Mettre à jour PHP (Ubuntu/Debian)
sudo apt update
sudo apt install php8.1 php8.1-cli php8.1-common

# Redémarrer le serveur web
sudo systemctl restart apache2
# ou
sudo systemctl restart nginx
```

#### ❌ Extensions PHP manquantes
**Symptôme** : "Extension XXX manquante"
```bash
# Ubuntu/Debian
sudo apt install php-mysql php-json php-mbstring php-curl php-openssl php-gd

# CentOS/RHEL
sudo yum install php-mysql php-json php-mbstring php-curl php-openssl php-gd

# Redémarrer le serveur web
sudo systemctl restart apache2
```

#### ❌ Limites PHP insuffisantes
**Symptôme** : Installation interrompue, erreurs de timeout
```ini
; Éditer /etc/php/8.1/apache2/php.ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 50M
post_max_size = 50M
```

### Problèmes de permissions

#### ❌ Dossiers non accessibles
**Symptôme** : "Dossier XXX non accessible en écriture"
```bash
# Corriger les permissions des dossiers
sudo chmod 755 config/ uploads/ logs/ backups/
sudo chown -R www-data:www-data config/ uploads/ logs/ backups/

# Vérifier les permissions
ls -la config/ uploads/ logs/ backups/
```

#### ❌ SELinux bloquant l'écriture
**Symptôme** : Permissions correctes mais écriture refusée
```bash
# Vérifier SELinux
sestatus

# Autoriser l'écriture web
sudo setsebool -P httpd_can_network_connect 1
sudo chcon -R -t httpd_exec_t /var/www/html/
```

### Problèmes de base de données

#### ❌ Connexion refusée
**Symptôme** : "Access denied for user"
```sql
-- Vérifier les droits utilisateur
SHOW GRANTS FOR 'username'@'localhost';

-- Créer un utilisateur avec tous les droits
CREATE USER 'n3xtweb'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON n3xtweb_db.* TO 'n3xtweb'@'localhost';
FLUSH PRIVILEGES;
```

#### ❌ Base de données inexistante
**Symptôme** : "Unknown database"
```sql
-- Créer la base de données
CREATE DATABASE n3xtweb_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### ❌ Serveur inaccessible
**Symptôme** : "Connection refused" ou "Host unknown"
```bash
# Vérifier que MySQL est démarré
sudo systemctl status mysql
sudo systemctl start mysql

# Vérifier la connectivité réseau
telnet database_host 3306
```

### Problèmes d'espace disque

#### ❌ Espace insuffisant
**Symptôme** : "Espace disque insuffisant"
```bash
# Vérifier l'espace disponible
df -h

# Nettoyer les fichiers temporaires
sudo apt autoclean
sudo apt autoremove

# Analyser l'utilisation
du -sh /var/www/html/*
```

### Problèmes de serveur web

#### ❌ Apache mod_rewrite manquant
```bash
# Activer mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### ❌ Configuration Nginx manquante
```nginx
# /etc/nginx/sites-available/n3xtweb
server {
    listen 80;
    server_name votre-domaine.com;
    root /var/www/html;
    index index.php index.html;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## 📋 Checklist de pré-installation

### Avant de commencer
- [ ] **Serveur web configuré** (Apache/Nginx)
- [ ] **PHP 7.4+ installé** avec toutes les extensions
- [ ] **MySQL/MariaDB configuré** avec base et utilisateur
- [ ] **Permissions correctes** sur les dossiers
- [ ] **Espace disque suffisant** (minimum 100MB)

### Vérifications automatiques
- [ ] **Version PHP** >= 7.4.0
- [ ] **Extension mysqli** pour MySQL
- [ ] **Extension json** pour JSON
- [ ] **Extension mbstring** pour UTF-8
- [ ] **Extension curl** pour HTTP
- [ ] **Extension openssl** pour SSL
- [ ] **Extension gd** pour images
- [ ] **Dossier config/** accessible en écriture
- [ ] **Dossier uploads/** accessible en écriture
- [ ] **Dossier logs/** accessible en écriture
- [ ] **Dossier backups/** accessible en écriture
- [ ] **Template config.template.php** présent
- [ ] **Espace disque** >= 100MB
- [ ] **Connexion base de données** fonctionnelle

## 🎯 Résolution de problèmes par environnement

### Hébergement mutualisé
- **Limitations** : Pas d'accès SSH, configuration PHP limitée
- **Solutions** : Contacter l'hébergeur pour les extensions manquantes
- **Permissions** : Utiliser le gestionnaire de fichiers ou FTP

### VPS/Serveur dédié
- **Avantages** : Contrôle total de la configuration
- **Responsabilités** : Installation et maintenance complètes
- **Outils** : SSH, gestionnaires de paquets

### Docker/Conteneurs
```dockerfile
# Dockerfile exemple pour N3XT WEB
FROM php:8.1-apache

# Installer les extensions requises
RUN docker-php-ext-install mysqli json mbstring curl openssl gd

# Configurer Apache
RUN a2enmod rewrite

# Copier les fichiers
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html/
```

## 📞 Support et ressources

### En cas de problème persistant
1. **Vérifier les logs** : `/logs/install.log`, `/var/log/apache2/error.log`
2. **Consulter la documentation** du serveur web et PHP
3. **Contacter l'hébergeur** pour l'assistance technique
4. **Forum communautaire** : Partager les messages d'erreur complets

### Informations utiles pour le support
- Version PHP : `php -v`
- Extensions chargées : `php -m`
- Configuration serveur : `phpinfo()`
- Logs d'erreur : Dernières lignes des fichiers de log

## Widgets disponibles


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