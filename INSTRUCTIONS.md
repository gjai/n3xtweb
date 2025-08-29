# Instructions personnalisées Copilot – Projet N3XT WEB

Ce document est la référence pour Copilot et l'équipe technique.  
Corrigez, complétez et faites évoluer ce fichier à chaque modification du projet.

---

## 0. Règle de base Copilot

**Avant chaque proposition de modification ou d'évolution, Copilot doit systématiquement analyser le dépôt `@gjai/n3xtweb` ainsi que l'ensemble du projet et des présentes instructions.  
Aucune suggestion ne doit être faite sans cette vérification de cohérence et d'impact global.**

---

## 1. Présentation du portail

N3XT WEB est un portail web modulaire composé de :
- **Front office** :
  - **Site vitrine** : pages publiques, actualités, présentation.
  - **Espace client pro** : accès sécurisé (non géré par le portail, redirection vers Dolibarr : https://clients.n3xt.xyz).
  - **Boutique en ligne** : catalogue, panier, paiement, gestion des commandes.
- **Back office (BO)** : administration complète du portail (contenus, utilisateurs, commandes, configuration, sécurité).

---

## 2. Rôles et accès

- **Admin** : accès et gestion du back office (toutes opérations).
- **Visiteur** : accès au front office (site vitrine, boutique).
- **Client boutique** : visiteur identifié, peut passer commande sur la boutique.
- **Client pro** : non géré via le portail, redirigé vers Dolibarr (https://clients.n3xt.xyz).

---

## 3. Sécurité (Priorité absolue)

- **SSL/TLS obligatoire** : tout le portail accessible uniquement en HTTPS.
- **Sécurisation points d'entrée** :
  - SQL : requêtes préparées, sanitation des inputs.
  - Formulaires : protection XSS et CSRF.
  - Uploads : types autorisés paramétrables dans le BO, PDF/images par défaut, stockage sécurisé non accessible directement.
  - Headers HTTP de sécurité (CSP, HSTS, X-Frame, etc.).
  - Sessions renforcées (fingerprint, timeout, cookies sécurisés).
- **Détection et blocage des intrusions** :
  - Limitation des tentatives de login (IP/utilisateur).
  - Blocage des IP suspectes, notification admin.

---

## 4. Logging (structure et gestion)

- **Actions loguées** : login, accès, modification, erreurs, uploads, commandes, etc.
- **Stockage** : en base de données, accès uniquement BO/admin.
- **Structure optimale** :
  - `id` (INT, PK, auto)
  - `timestamp` (DATETIME)
  - `user_id` (INT, nullable)
  - `role` (ENUM/admin/visiteur/client_boutique)
  - `ip_address` (VARCHAR)
  - `user_agent` (TEXT)
  - `action` (VARCHAR, ex : login, upload, commande)
  - `detail` (TEXT, données additionnelles)
  - `level` (ENUM/info/warning/error/debug)
- **Archivage/compression automatique** : toutes les 24 h.
- **Nettoyage automatique** : tous les 7 jours (durée paramétrable dans le BO).

### Structure actuelle implémentée

La classe `Logger` utilise actuellement :
- Table `access_logs` : username, ip_address, user_agent, action, status, notes
- Table `login_attempts` : ip_address, username, success, failure_reason, user_agent
- Rotation automatique des logs fichier > 10MB
- Compression gzip et nettoyage 30 jours
- Fallback vers logs fichier si base indisponible

---

## 5. Gestion des erreurs et debug

- **Ne jamais exposer les détails d'erreur MySQL en front.**
  - Pas de traduction ni d'affichage pour l'utilisateur final.
  - Mode debug : bascule par un **paramètre du BO**, affichage possible uniquement pour les admins.
  - Toutes les erreurs sont consignées dans les logs BO pour audit.

### Configuration debug actuelle

Le mode debug est configurable via :
- Back office → Paramètres de débogage
- Variables : `debug`, `enable_error_display`, `log_queries`
- Stockage en base via classe `Configuration`
- Constantes : `DEBUG`, `ENABLE_ERROR_DISPLAY`, `LOG_QUERIES`

---

## 6. Gestion des langues

- **Portail traduit en français (par défaut) et anglais.**
- **Bascule dynamique sur toutes les pages** (front et back, selon choix utilisateur).
- **Persistance de la langue** : cookie + session.
- **Gestion globale et extensible** :
  - Fichiers JSON pour chaque langue, centralisés (`lang/`), organisés par modules si besoin.
  - Ajout/modification de langue uniquement par les admins via le BO (interface sécurisée).
  - Edition du JSON via BO : validation syntaxe, preview avant publication.
  - Processus d'ajout : import modèle JSON, édition, activation.
- **Sécurité** : seuls les admins peuvent gérer, consulter et modifier les langues.

### État actuel des langues

- Implémentation actuelle : classe `LanguageHelper` avec tableaux PHP statiques
- Langues supportées : français (fr), anglais (en)
- Fallback automatique vers français si clé manquante
- **Évolution prévue** : migration vers fichiers JSON avec gestion BO

---

## 7. Fonctionnalités principales

1. **Installation**
   - Génération dossier BO aléatoire
   - Envoi identifiants admin par mail
   - Vérification prérequis système et BDD

2. **Authentification & Sécurité**
   - Login admin : captcha, tentatives limitées, blocage IP
   - Mot de passe oublié, réinitialisation sécurisée
   - Logging des accès (base)
   - Sessions sécurisées, fingerprint, timeout

3. **Base de données**
   - PDO sécurisé, SQL mode strict
   - Table prefix configurable
   - Méthodes : fetchOne, fetchAll, execute, lastInsertId, testConnection

4. **Logging**
   - Logger (niveau, rotation, format, accès, update, erreurs)
   - Stockage en base, archivage/compression, nettoyage auto

5. **Gestion des fichiers**
   - Upload sécurisé (types paramétrables, PDF/images par défaut)
   - Noms sûrs, stockage dossier sécurisé

6. **Fonctions de sécurité**
   - CSRF global, sanitation des inputs
   - Headers HTTP avancés

7. **Captcha**
   - Génération, validation, expiration

8. **Cache & performance**
   - Cache file-based, optimisation assets (minify, versioning, hash, combine)
   - Performance timer

9. **Utilitaires divers**
   - InstallHelper, EmailHelper (langues), LanguageHelper (JSON), SystemHealth

---

## 8. Points de vigilance pour Copilot

- Analyser systématiquement le dépôt `@gjai/n3xtweb` et l'ensemble du projet avant toute proposition.
- Vérifier les accès BO et appliquer les règles de sécurité.
- Utiliser les méthodes Database pour toute opération SQL.
- Logger toutes les actions critiques via Logger.
- Ne jamais exposer de détails d'erreur en front.
- Utiliser CSRF sur tous les formulaires.
- Vérifier droits, contexte et entrées pour toute opération sensible.
- Générer des noms de fichiers sûrs via FileHelper.
- Respecter AssetOptimizer et Cache.
- Toujours prioriser la sécurité dans les suggestions et corrections.
- Supporter gestion multilingue extensible (fichiers JSON).
- Respecter la configuration pour uploads, langue, logs.
- Seuls les admins peuvent consulter/gérer logs et langues.

---

## 9. Structure du projet (raccourci)

- `includes/functions.php` : utilitaires, sécurité, session, database, logger, gestion langues
- `bo-XXXXXXXXXXXX/login.php` : authentification, accès admin
- `config/config.php` : constantes de config (DB, sécurité, langue, etc.)
- `assets/css`, `assets/js` : fichiers statiques optimisés
- `LOG_PATH`, `BACKUP_PATH`, `UPLOAD_PATH` : stockage fichiers, logs, sauvegardes
- `lang/` : fichiers JSON de langues (prévue)

### Architecture réelle

```
n3xtweb/
├── bo/                 # Back office (dossier aléatoire en production)
├── admin/              # Alias vers BO (nettoyé après install)
├── includes/           # Classes core (Database, Logger, Security, etc.)
├── config/             # Configuration (config.php, Configuration.php)
├── assets/             # CSS/JS optimisés
├── logs/               # Logs système
├── backups/            # Sauvegardes
├── uploads/            # Fichiers utilisateur
└── lang/               # Fichiers JSON langues (à implémenter)
```

---

## 10. Suggestions d'amélioration

1. **Ajouter des exemples concrets**  
   - Exemple de fichier JSON de langue (structure, conventions de clés).
   - Exemple de modèle de log (schéma base).
   - Exemple de workflow d'ajout de langue via le BO.

2. **Préciser la gestion des traductions manquantes**  
   - Fallback sur la langue par défaut ou message d'erreur clair côté admin.

3. **Ajouter une section sur la gestion des droits**  
   - Définir précisément les rôles (admin, visiteur, client boutique, client pro externe).

4. **Uniformiser la terminologie**  
   - "Back office" (BO), "admin", "front office", "langue", "logs" : harmoniser dans tout le doc.

5. **Documenter le mode debug**  
   - Préciser comment l'activer/désactiver, quels logs ou erreurs sont affichés.

6. **Ajout d'une arborescence type du projet**  
   - Un schéma visuel ou textuel de l'organisation des dossiers/fichiers.

### Améliorations spécifiques identifiées

1. **Migration logging vers structure optimale**
   - Ajouter champs `user_id`, `role`, `detail`, `level` aux tables existantes
   - Maintenir compatibilité avec système actuel

2. **Implémentation gestion JSON langues**
   - Créer interface BO pour édition JSON
   - Migration depuis LanguageHelper vers système fichiers
   - Validation syntaxe et preview

3. **Optimisation debug BO**
   - Interface toggle plus intuitive
   - Logs temps réel pour admins
   - Séparation niveaux debug/production

---

## 11. Format des réponses attendues de Copilot

- Se référer explicitement aux classes/fonctions/utilitaires du projet
- Proposer des patchs complets en cas de correction
- Indiquer si la réponse repose sur le contexte du projet ou sur des connaissances générales
- Utiliser bloc de fichier pour toute proposition de code
- Attention à la sécurité, robustesse et gestion multilingue

---

## 12. Historique et évolutions

- Fichier à mettre à jour à chaque évolution du projet
- Sert de base à Copilot pour suggestion, analyse, correction

### Versions du document

- **v1.0** : Création initiale basée sur analyse du projet existant
- Alignement avec architecture réelle (Logger, Configuration, LanguageHelper)
- Documentation debug mode via BO
- Structure logging actuelle et optimale prévue
- Plan migration langues vers JSON

---

**Corrige, complète ou adapte ce fichier selon tes besoins !**