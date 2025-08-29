# Module Install - N3XT WEB

Ce module gère le statut de l'installation et les informations système du back office N3XT WEB.

## Vue d'ensemble

Le module Install fournit des outils pour surveiller l'état de l'installation, vérifier les prérequis système et afficher les informations de configuration importantes.

## Dépendances

Ce module nécessite les fichiers suivants pour fonctionner correctement :

### BaseWidget.php
- **Emplacement** : `modules/BaseWidget.php`
- **Rôle** : Classe de base pour tous les widgets N3XT WEB
- **Nécessité** : Obligatoire pour le bon fonctionnement du widget InstallStatusWidget
- **Description** : Fournit les fonctionnalités communes pour l'affichage et la gestion des widgets

**Important** : Le fichier `modules/BaseWidget.php` doit être présent à la racine du dossier modules pour que le widget InstallStatusWidget puisse être chargé correctement.

## Widgets disponibles

### InstallStatusWidget

Widget principal qui affiche le statut complet de l'installation du système.

#### Fonctionnalités

- **Statut d'installation** : Vérifie si l'installation est complète et fonctionnelle
- **Informations système** : Affiche les détails PHP, serveur et ressources
- **Vérification des prérequis** : Contrôle les versions, extensions et permissions
- **Surveillance des ressources** : Affiche l'espace disque disponible et l'utilisation mémoire

#### Configuration

```php
$config = [
    'enabled' => true,
    'title' => 'Statut d\'installation',
    'description' => 'Affiche le statut de l\'installation et les informations système',
    'refresh_interval' => 300, // 5 minutes
    'show_php_info' => true,
    'show_db_info' => true,
    'show_file_permissions' => true
];
```

#### Utilisation

```php
// Instanciation du widget
$widget = new InstallStatusWidget();

// Récupération des données
$data = $widget->getData();

// Rendu HTML
echo $widget->render();
```

#### Structure des données

```php
$data = [
    'installation_status' => [
        'completed' => bool,
        'version' => string,
        'install_date' => string|null,
        'last_update' => string|null
    ],
    'system_info' => [
        'php_version' => string,
        'server_software' => string,
        'memory_limit' => string,
        'max_execution_time' => string,
        'upload_max_filesize' => string,
        'post_max_size' => string,
        'disk_free_space' => string,
        'extensions' => array
    ],
    'requirements_check' => [
        'php_version' => [
            'name' => string,
            'required' => string,
            'current' => string,
            'status' => bool
        ],
        'extensions' => array,
        'file_permissions' => array
    ],
    'last_updated' => string
];
```

## Vérifications effectuées

### Version PHP
- Vérification que la version PHP est >= 7.4.0
- Contrôle de compatibilité pour les fonctionnalités avancées

### Extensions PHP requises
- **mysqli** : Connexion à la base de données
- **json** : Manipulation des données JSON
- **mbstring** : Support des chaînes multi-octets
- **curl** : Requêtes HTTP externes
- **openssl** : Sécurité et chiffrement
- **gd** : Manipulation d'images (optionnel)

### Permissions de fichiers
- **config/** : Lecture et écriture pour les fichiers de configuration
- **uploads/** : Écriture pour les téléchargements
- **logs/** : Écriture pour les journaux
- **backups/** : Écriture pour les sauvegardes

### Ressources système
- **Espace disque** : Vérification de l'espace disponible
- **Mémoire** : Contrôle des limites de mémoire PHP
- **Permissions** : Vérification des droits d'accès aux répertoires

## Indicateurs de statut

- 🟢 **Vert** : Tout fonctionne correctement
- 🟡 **Jaune** : Attention requise, système fonctionnel
- 🔴 **Rouge** : Problème critique nécessitant une intervention

## Recommandations

### Maintenance préventive
1. Surveiller régulièrement l'espace disque disponible
2. Vérifier les permissions de fichiers après les mises à jour
3. Maintenir PHP à jour pour la sécurité
4. Contrôler les extensions PHP installées

### Optimisation
1. Ajuster la limite de mémoire PHP selon les besoins
2. Optimiser les permissions pour la sécurité
3. Configurer les limites de téléchargement appropriées
4. Surveiller les performances du serveur

## Intégration

### Avec d'autres modules
- **SecurityManager** : Partage les vérifications de sécurité système
- **MaintenanceManager** : Utilise les informations de ressources
- **NotificationManager** : Envoie des alertes en cas de problème

### API externe
- Aucune dépendance externe requise
- Utilise uniquement les fonctions PHP natives
- Compatible avec tous les environnements d'hébergement

## Dépannage

### Problèmes courants

**Installation marquée comme incomplète**
- Vérifier l'existence du fichier config/config.php
- Contrôler les permissions du répertoire config/
- Vérifier la connexion à la base de données

**Extensions PHP manquantes**
- Installer les extensions via le gestionnaire de paquets
- Redémarrer le serveur web après installation
- Vérifier la configuration PHP (php.ini)

**Permissions insuffisantes**
- Ajuster les permissions avec chmod approprié
- Vérifier la propriété des fichiers (chown)
- Contrôler les restrictions SELinux/AppArmor

**Espace disque faible**
- Nettoyer les anciens logs et sauvegardes
- Optimiser les médias téléchargés
- Considérer l'extension du stockage

## Sécurité

- Les informations système sensibles ne sont affichées qu'aux administrateurs
- Aucune donnée de configuration n'est exposée publiquement
- Les vérifications de permissions sont effectuées en lecture seule
- Protection contre l'exécution directe des scripts