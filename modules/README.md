# N3XT WEB - Modules

## Vue d'ensemble

Ce dossier contient l'ensemble des modules de gestion du back office N3XT WEB. Chaque module est conçu pour apporter une fonctionnalité spécifique au système tout en respectant l'architecture globale et les principes de sécurité du projet.

## Structure des modules

Chaque module suit une architecture standardisée :

- **classes/** - Classes principales du module (logique métier)
- **views/** - Interfaces utilisateur du module (fichiers de vue)
- **README.md** - Documentation spécifique détaillée du module
- **Configuration** - Paramètres stockés en base de données
- **Intégration** - Hooks et API pour communication inter-modules

## Modules disponibles

### 1. UpdateManager
**[Gestionnaire de mises à jour automatiques](UpdateManager/README.md)**
- 🔄 Vérification automatique des mises à jour depuis GitHub
- 📥 Téléchargement et application sécurisés des mises à jour
- 🛡️ Sauvegarde automatique avant mise à jour
- 📊 Journalisation complète des opérations et historique

### 2. NotificationManager  
**[Système de notifications intégré](NotificationManager/README.md)**
- 📢 Notifications visuelles en temps réel dans le back office
- 📧 Notifications par email avec templates personnalisables
- 📊 Historique et gestion complète des notifications
- 🔧 Système extensible pour différents types de notifications

### 3. BackupManager
**[Gestionnaire de sauvegardes et restauration](BackupManager/README.md)**
- 💾 Sauvegarde automatique de la base de données (dump SQL)
- 📁 Sauvegarde sélective des fichiers de configuration
- 🔄 Restauration complète ou sélective depuis sauvegarde
- 📊 Gestion intelligente de l'historique et rétention

### 4. MaintenanceManager
**[Gestionnaire de maintenance automatisée](MaintenanceManager/README.md)**
- 🧹 Nettoyage automatique des anciens logs et backups
- 📦 Archivage ZIP intelligent avant suppression
- 🗂️ Nettoyage des fichiers temporaires et optimisation
- ⚙️ Interface de maintenance dédiée et planification

### 5. SecurityManager
**[Gestionnaire de sécurité et protection](SecurityManager/README.md)**
- 🛡️ Protection contre les attaques par force brute
- 🔐 Gestion avancée des mots de passe et sessions
- 📊 Monitoring et alertes de sécurité en temps réel
- 🚫 Gestion des listes blanches/noires d'IP

### 6. EventManager
**[Gestionnaire d'événements et logs](EventManager/README.md)**
- 📝 Journalisation centralisée des événements système
- 🔍 Monitoring et surveillance des activités
- 📊 Tableaux de bord et statistiques détaillées
- 🔗 Intégration avec tous les autres modules

### 7. Dashboard
**[Tableau de bord modulaire](Dashboard/README.md)**
- 📊 Widgets dynamiques et configurables
- 📈 Métriques et indicateurs de performance
- 🎨 Interface moderne responsive
- ⚙️ Personnalisation par utilisateur

### 8. Theme
**[Gestionnaire de thèmes](Theme/README.md)**
- 🎨 Gestion des thèmes et apparence
- 📱 Support responsive et moderne
- ⚙️ Personnalisation avancée
- 🔧 API pour développeurs de thèmes

## Configuration

### Architecture modulaire
- **Indépendance** : Chaque module fonctionne de manière autonome
- **Interopérabilité** : Communication via EventManager et API standardisées
- **Configuration centralisée** : Paramètres stockés en base de données
- **Migration automatique** : Système de migration pour les mises à jour

### Installation et activation
- Détection automatique des modules disponibles
- Installation et activation via interface d'administration
- Vérification des dépendances et prérequis
- Rollback automatique en cas d'erreur

## Administration

**Interface principale :** `/bo/modules.php`

### Gestion globale
- Liste et statut de tous les modules
- Activation/désactivation des modules
- Configuration centralisée
- Monitoring des performances

### Actions disponibles
- Installation de nouveaux modules
- Configuration des paramètres
- Consultation des logs et statistiques
- Mise à jour des modules

## Intégration

### Communication inter-modules
- **EventManager** : Système d'événements centralisé
- **API standardisée** : Méthodes communes à tous les modules
- **Hooks** : Points d'extension pour personnalisation
- **Configuration partagée** : Paramètres globaux accessibles

### Développement de modules
- Template de base disponible (BaseModule.php)
- Guide de développement complet
- Standards de codage respectés
- Tests automatisés intégrés

## Principes communs

### Sécurité
- **Protection CSRF** : Toutes les actions sensibles protégées
- **Validation des entrées** : Sanitation et validation systématiques
- **Logging des accès** : Traçabilité complète des opérations
- **Permissions granulaires** : Contrôle d'accès fin par module

### Configuration
- **Base de données centralisée** : Tous les paramètres en BDD
- **Interface d'administration** : Configuration via back office
- **Valeurs par défaut sécurisées** : Configuration sûre out-of-the-box
- **Validation des paramètres** : Contrôles de cohérence automatiques

### Extensibilité
- **Architecture modulaire** : Ajout facile de nouvelles fonctionnalités
- **Hooks et filtres** : Points d'extension standardisés
- **API documentée** : Intégration simplifiée avec systèmes tiers
- **Migration automatique** : Évolutions transparentes de la structure

### Documentation
- **README complets** : Chaque module entièrement documenté
- **Exemples d'utilisation** : Code d'exemple pour tous les cas d'usage
- **API référence** : Documentation technique détaillée
- **Guide de développement** : Instructions pour créer de nouveaux modules