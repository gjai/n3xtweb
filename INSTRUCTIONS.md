# Instructions personnalisées Copilot – Projet N3XT WEB

Ce document est la référence pour Copilot et l'équipe technique.  
Corrigez, complétez et faites évoluer ce fichier à chaque modification du projet.  
**À chaque mise à jour de ce fichier, une Pull Request doit être créée sur le dépôt.**

---

## 0. Règle de base Copilot

**Avant chaque proposition de modification ou d'évolution, Copilot doit systématiquement analyser le dépôt `@gjai/n3xtweb`, l'ensemble du projet, l'environnement de test et les présentes instructions.  
Aucune suggestion ne doit être faite sans cette vérification de cohérence et d'impact global.**

---

## 1. Environnement de test

Le système est actuellement en test sur [https://communicationvisuelle.fr](https://communicationvisuelle.fr).

- Serveur : mutualisé OVH
- PHP : 8.2
- MySQL : 8.0
- Contraintes spécifiques :  
  - Accès limité (pas de sudo/root)
  - Extensions PHP standards (vérifier avant toute nouvelle dépendance)
  - Espace disque et mémoire partagés
- Toute évolution doit être validée sur cet environnement.

---

## 2. Rôles et accès

| Rôle             | Accès principal                 | Description                                                    |
|------------------|---------------------------------|----------------------------------------------------------------|
| Back office      | Gestion complète du portail     | Accès à l'administration du portail, gestion des contenus, utilisateurs, commandes, configuration, sécurité. |
| Visiteur         | Front office                    | Accès site vitrine, consultation du catalogue, actualités, pages publiques.                          |
| Client boutique  | Front office + boutique         | Visiteur identifié, peut passer commande sur la boutique.                                            |
| Client pro       | Redirection Dolibarr            | Non géré via le portail, redirigé vers Dolibarr (https://clients.n3xt.xyz)                          |

---

## 3. Sécurité

- **SSL/TLS obligatoire** : tout le portail accessible uniquement en HTTPS.
- **Sécurisation points d'entrée** :
  - Requêtes SQL : requêtes préparées, sanitation des inputs.
  - Formulaires : protection XSS et CSRF.
  - Uploads : types autorisés paramétrables dans le Back office, PDF/images par défaut, stockage sécurisé non accessible directement.
  - Headers HTTP de sécurité (CSP, HSTS, X-Frame, etc.).
  - Sessions renforcées (fingerprint, timeout, cookies sécurisés).
- **Détection et blocage des intrusions** :
  - Limitation des tentatives de login (IP/utilisateur).
  - Blocage des IP suspectes, notification Back office.
- **Audit sécurité** :  
  - Utiliser outils comme [Mozilla Observatory](https://observatory.mozilla.org/) et [SecurityHeaders.com](https://securityheaders.com).
  - Vérification régulière des points critiques (SQL, XSS, CSRF, uploads, accès Back office).
  - Documenter les failles et corrections dans le Back office (section sécurité).

---

## 4. Logging (structure et gestion)

Toutes les actions critiques doivent être loguées et consultables uniquement via le Back office.

### Exemple de modèle SQL pour la table de logs

```sql
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME NOT NULL,
    user_id INT NULL,
    role ENUM('back_office', 'visiteur', 'client_boutique') NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    action VARCHAR(50) NOT NULL,
    detail TEXT,
    level ENUM('info', 'warning', 'error', 'debug') DEFAULT 'info'
);
```

- Archivage/compression automatique : toutes les 24 h.
- Nettoyage automatique : tous les 7 jours (durée configurable dans le Back office, variable : `LOG_RETENTION_DAYS`).

---

## 5. Gestion des erreurs et mode debug

- **Ne jamais exposer les détails d'erreur MySQL en front office.**
- Le mode debug, activable via le Back office, affiche les erreurs à l'écran pour les administrateurs uniquement (utiliser `ini_set('display_errors', 1)` en mode debug).
- Toutes les erreurs sont consignées dans les logs consultables dans le Back office.

---

## 6. Gestion des langues

Le portail est traduit en français (par défaut) et anglais.  
La gestion des langues est extensible (JSON par langue, centralisé dans `/lang/`).

### Procédure de gestion des langues via Back office

1. **Ajout d'une langue**
   - Importer un modèle JSON vierge via le Back office.
   - Compléter les traductions via l'éditeur intégré (validation syntaxe, preview avant publication).
   - Activer la langue (visible sur le portail).

2. **Modification**
   - Sélectionner la langue dans le Back office.
   - Modifier les clés/valeurs, valider la syntaxe.
   - Sauvegarder et publier.

3. **Suppression**
   - Désactiver la langue (ne pas supprimer le fichier directement).
   - Supprimer via l'interface si nécessaire.

4. **Sécurité**
   - Seuls les comptes Back office peuvent gérer, consulter et modifier les langues.

5. **Gestion des traductions manquantes**
   - Fallback automatique sur la langue par défaut.
   - Notification côté Back office des clés manquantes (à corriger).

---

## 7. Fonctionnalités principales

1. **Installation**
   - Génération dossier Back office aléatoire
   - Envoi identifiants Back office par mail
   - Vérification prérequis système et BDD

2. **Authentification & Sécurité**
   - Login Back office : captcha, tentatives limitées, blocage IP
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

- Analyser systématiquement le dépôt, l'environnement de test, et les instructions avant toute proposition.
- Vérifier les accès Back office et appliquer les règles de sécurité.
- Utiliser les méthodes Database pour toute opération SQL.
- Logger toutes les actions critiques via Logger.
- Ne jamais exposer de détails d'erreur en front office.
- Utiliser CSRF sur tous les formulaires.
- Vérifier droits, contexte et entrées pour toute opération sensible.
- Générer des noms de fichiers sûrs via FileHelper.
- Respecter AssetOptimizer et Cache.
- Toujours prioriser la sécurité dans les suggestions et corrections.
- Supporter gestion multilingue extensible (fichiers JSON).
- Respecter la configuration pour uploads, langue, logs.
- Seuls les comptes Back office peuvent consulter/gérer logs et langues.

---

## 9. Structure du projet (raccourci)

- `includes/functions.php` : utilitaires, sécurité, session, database, logger, gestion langues
- `back-office-XXXXXXXXXXXX/login.php` : authentification, accès Back office
- `config/config.php` : constantes de config (DB, sécurité, langue, etc.)
- `assets/css`, `assets/js` : fichiers statiques optimisés
- `LOG_PATH`, `BACKUP_PATH`, `UPLOAD_PATH` : stockage fichiers, logs, sauvegardes
- `lang/` : fichiers JSON de langues

---

## 10. Processus de mise à jour des instructions

- À chaque évolution du projet, ce fichier doit être mis à jour.
- **Chaque modification du fichier doit faire l'objet d'une Pull Request dédiée sur le dépôt.**
- La validation se fait par revue (Back office ou responsable technique).
- L'historique des modifications est suivi via Git et les PR.

---

## 11. Format des réponses attendues de Copilot

- Se référer explicitement aux classes/fonctions/utilitaires du projet
- Proposer des patchs complets en cas de correction
- Indiquer si la réponse repose sur le contexte du projet ou sur des connaissances générales
- Utiliser bloc de fichier pour toute proposition de code
- Attention à la sécurité, robustesse et gestion multilingue

**Exemple de réponse attendue :**
```php name=includes/functions.php
// Correction de la fonction login pour renforcer la sécurité SQL
function login($username, $password) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username');
    $stmt->execute(['username' => $username]);
    // ...
}
```
---

## 12. Historique et évolutions

- Fichier à mettre à jour à chaque évolution du projet
- Sert de base à Copilot pour suggestion, analyse, correction

---

**Corrige, complète ou adapte ce fichier selon tes besoins !**