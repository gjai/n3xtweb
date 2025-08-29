# INSTRUCTIONS – N3XT WEB

Ce document présente les consignes fondamentales à respecter pour toute contribution ou évolution du projet N3XT WEB.

---

## 1. Checklist obligatoire avant chaque PR

Avant toute création de Pull Request sur le projet N3XT WEB, vérifier et documenter :

1. Analyse du dépôt : relire la structure, les modules et la documentation pour cohérence et intégrité.
2. Respect de l'environnement de test : s'assurer de la compatibilité OVH mutualisé (PHP/MySQL/Extensions).
3. Sécurité : valider la protection des accès, formulaires, uploads, et la conformité aux règles de sécurité du projet.
4. Organisation modulaire : garantir l'indépendance de chaque fonctionnalité et la non-régression sur les modules existants.
5. Gestion des fichiers personnalisés : vérifier qu'aucun fichier de configuration ou personnalisé n'est écrasé par la mise à jour.
6. Documentation et versioning : mettre à jour la documentation, incrémenter la version et consigner les évolutions.
7. Maintenance et nettoyage : supprimer les fichiers temporaires ou obsolètes, préparer/exécuter les scripts de maintenance.
8. Tests & validation : effectuer et documenter des tests (automatiques ou manuels) sur les fonctionnalités impactées.
9. Principe de non-régression : s'assurer qu'aucune modification ne casse ou altère les fonctionnalités existantes.
10. Mise à jour du fichier INSTRUCTIONS.md : consigner toute nouvelle règle ou évolution.

Aucune PR ne doit être soumise sans validation complète de cette checklist.

---

## 2. Principes généraux
- Lire et respecter les instructions avant toute proposition.
- Ne jamais exposer de détails d'erreur en front office.
- Seuls les comptes Back office peuvent consulter/gérer logs et langues.

---

## 3. Sécurité
- SSL/TLS obligatoire sur toutes les pages.
- Requêtes SQL préparées.
- Protection CSRF et XSS sur tous les formulaires.
- Limitation des tentatives de login, blocage IP, audit sécurité (headers, sessions, logs).
- Uploads : types paramétrables, stockage hors accès direct, sanitation des fichiers.

---

## 4. Structure du projet
- Modules indépendants : logs, configuration admin, sauvegarde/restauration, etc.
- Dossiers dédiés, fichiers séparés (controller, model, view), documentation interne.
- Fichiers personnalisés/config exclus des mises à jour (update.excludes).

---

## 5. Fonctionnalités principales
- Installation : sélection langue, vérification prérequis, collecte admin, config BDD, suppression auto des fichiers d'installation.
- Authentification : captcha, blocage IP, logging des accès, sessions sécurisées.
- Logging : logger avec rotation, niveau, format, accès, update, erreurs, archivage et nettoyage auto.
- Gestion fichiers : upload sécurisé, noms sûrs, stockage dans dossier sécurisé.
- Gestion multilingue : fichiers JSON.
- Maintenance & mise à jour : via FTP, GitHub ou ZIP, interface dédiée, surveillance système, script de nettoyage.

---

## 6. Points de vigilance Copilot
- Analyser systématiquement le dépôt et les instructions avant toute proposition.
- Utiliser les méthodes du projet (Database, Logger, FileHelper, AssetOptimizer, etc.).
- Protéger toutes les opérations sensibles : CSRF, vérification des droits, sanitation des entrées.
- Jamais d'exposition de détails d'erreur en front office.
- Seuls les comptes Back office peuvent consulter/gérer logs et langues.

---

## 7. Maintenance & nettoyage
- Nettoyage des fichiers temporaires après chaque PR/installation.
- Tests automatiques ou manuels après chaque mise à jour.
- Documentation des problèmes et corrections dans les cycles suivants.

---

## 8. Principe de non-régression
Toute évolution, correction ou ajout ne doit en aucun cas altérer, casser ou régresser le fonctionnement existant du portail N3XT WEB.
Les modifications doivent être testées et validées sur l'environnement de test avant toute fusion.
En cas de régression détectée, la modification doit être annulée ou corrigée avant validation.

---

## 9. Workflow de modularisation systématique

### Découpage modulaire obligatoire
Tout développement sur N3XT WEB doit suivre une approche modulaire systématique :

1. **Analyse du besoin** : Chaque fonctionnalité doit être évaluée pour son potentiel modulaire
2. **Utilisation du CHECKLIST_DECOUPAGE.md** : Validation complète de tous les points
3. **Structure standardisée** : Respect de l'arborescence définie (controller.php, model.php, widgets/, views/, README.md, CHECKLIST.md)
4. **Documentation systématique** : Usage du template README_modele.md pour chaque nouveau module
5. **Validation sécurité** : Audit complet avant intégration
6. **Tests de non-régression** : Validation de l'impact sur l'existant

### Liste des modules actifs
- **SecurityManager** : Gestion de la sécurité, détection de menaces, audit
- **EventManager** : Gestion des événements système et logs
- **UpdateManager** : Gestion des mises à jour et déploiements
- **NotificationManager** : Système de notifications
- **BackupManager** : Sauvegarde et restauration
- **MaintenanceManager** : Maintenance automatique et monitoring
- **Dashboard** : Tableau de bord principal
- **Theme** : Gestion des thèmes et interface
- **Install** : Processus d'installation

### Consignes de découpage
1. **Indépendance** : Chaque module doit pouvoir fonctionner de manière autonome
2. **Responsabilité unique** : Un module = une responsabilité métier
3. **Interfaces claires** : API publique bien définie entre modules
4. **Configuration centralisée** : Paramètres en base de données
5. **Logging intégré** : Traçabilité complète des actions
6. **Sécurité renforcée** : Protection à tous les niveaux

### Priorisation des modules à créer
1. **SecurityManager** ✅ - Critique pour la sécurité globale
2. **AdminManager** - Gestion avancée des comptes administrateurs
3. **LogManager** - Centralisation et analyse des logs
4. **CacheManager** - Optimisation des performances
5. **ApiManager** - Exposition d'API REST sécurisées
6. **MonitoringManager** - Surveillance système avancée

---

## 10. Documentation systématique

### Templates obligatoires
- **README_modele.md** : Template pour la documentation de tout nouveau module
- **CHECKLIST_DECOUPAGE.md** : Checklist complète de modularisation

### Règles de documentation
1. **README.md obligatoire** pour chaque module avec sections standardisées
2. **CHECKLIST.md spécifique** pour le suivi du développement et maintenance
3. **Commentaires de code** complets et à jour
4. **Documentation API** pour les méthodes publiques
5. **Exemples d'utilisation** fonctionnels
6. **Guide d'installation et configuration**

---

## 11. Historique des évolutions
Renseigner ici les dates et types de modifications majeures apportées au projet.