# N3XT WEB - Modules

Ce dossier contient les modules de gestion du back office N3XT WEB.

## Structure des modules

Chaque module suit une architecture standardisée :

- **classes/** - Classes principales du module
- **views/** - Interfaces utilisateur du module  
- **README.md** - Documentation spécifique du module

## Modules disponibles

### 1. UpdateManager
**Gestionnaire de mises à jour automatiques**
- Vérification automatique des mises à jour depuis GitHub
- Téléchargement et application des mises à jour
- Sauvegarde automatique avant mise à jour
- Journalisation complète des opérations

### 2. NotificationManager  
**Système de notifications**
- Notifications visuelles en back office
- Notifications par email
- Historique des notifications
- Système extensible pour différents types de notifications

### 3. BackupManager
**Gestionnaire de sauvegardes**
- Sauvegarde de la base de données (dump SQL)
- Sauvegarde des fichiers de configuration
- Restauration depuis sauvegarde
- Gestion de l'historique des sauvegardes

### 4. MaintenanceManager
**Gestionnaire de maintenance**
- Nettoyage automatique des anciens logs et backups
- Archivage ZIP avant suppression
- Nettoyage des fichiers temporaires
- Interface de maintenance dédiée

## Principes communs

- **Sécurité** : Protection CSRF, validation des entrées, logging des accès
- **Configuration** : Tous les paramètres stockés en base de données
- **Extensibilité** : Architecture modulaire et extensible
- **Documentation** : Chaque module documenté avec exemples d'utilisation