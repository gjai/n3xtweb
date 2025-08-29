# SecurityManager Module - N3XT WEB

## Vue d'ensemble

Le module SecurityManager fournit un système complet de sécurité et protection pour le système N3XT WEB. Il gère la détection des menaces en temps réel, la protection contre les attaques par force brute, et offre des mécanismes avancés de surveillance et d'audit de sécurité.

## Fonctionnalités

### 🛡️ Protection contre les attaques par force brute
- Blocage automatique des IP après tentatives de connexion échouées
- Politique de verrouillage configurable avec durée personnalisable
- Détection intelligente des patterns d'attaque suspects
- System de whitelist pour IP de confiance avec gestion granulaire

### 🔐 Gestion avancée des mots de passe et sessions
- Validation de la complexité des mots de passe avec règles configurables
- Exigences de longueur minimale et caractères spéciaux
- Gestion sécurisée des sessions avec timeout automatique
- Génération de tokens sécurisés pour authentification renforcée

### 📊 Monitoring et alertes de sécurité en temps réel
- Surveillance continue des tentatives d'accès et activités suspectes
- Système d'alertes multiniveaux (LOW, MEDIUM, HIGH, CRITICAL)
- Tableau de bord avec métriques de sécurité en temps réel
- Historique détaillé des événements de sécurité

### 🚫 Gestion intelligente des listes blanches/noires d'IP
- Système de blacklist automatique basé sur le comportement
- Whitelist pour adresses IP de confiance avec priorité absolue
- Gestion dynamique des règles selon contexte de menace
- Interface d'administration pour gestion manuelle des listes

## Configuration

### Paramètres disponibles

| Paramètre | Description | Valeur par défaut |
|-----------|-------------|-------------------|
| `enabled` | Active/désactive le module | `true` |
| `login_attempts_max` | Tentatives max avant blocage | `5` |
| `lockout_duration` | Durée de blocage (secondes) | `900` (15min) |
| `session_timeout` | Timeout de session (secondes) | `3600` (1h) |
| `password_min_length` | Longueur minimale mot de passe | `8` |
| `password_complexity` | Exigence de complexité | `true` |
| `captcha_enabled` | Protection CAPTCHA | `false` |
| `two_factor_enabled` | Authentification à deux facteurs | `false` |

### Configuration via interface admin

```php
// Accès au module
$securityManager = SecurityManager::getInstance();

// Modifier la configuration
$securityManager->setConfig('login_attempts_max', 3);
$securityManager->setConfig('lockout_duration', 1800); // 30 minutes
```

## Administration

**Interface disponible :** `/bo/security.php`

### Tableau de bord
- Niveau de menace actuel du système avec indicateurs visuels
- Statistiques des tentatives de connexion et blocages récents
- Liste des dernières alertes de sécurité avec actions possibles
- Monitoring en temps réel des IP bloquées et whitelistées

### Actions disponibles
- Gestion manuelle des listes blanches et noires d'IP
- Configuration des politiques de sécurité et seuils d'alerte
- Consultation des logs d'audit avec filtres avancés
- Déclenchement de scans de sécurité manuels complets

## Schema de base de données

### Table `login_attempts`

```sql
CREATE TABLE n3xt_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(50) NULL,
    success BOOLEAN NOT NULL,
    failure_reason VARCHAR(100) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_created (ip_address, created_at),
    INDEX idx_username_created (username, created_at)
);
```

### Table `security_events`

```sql
CREATE TABLE n3xt_security_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type ENUM('login_success', 'login_failed', 'ip_blocked', 'suspicious_activity', 'bruteforce_attempt', 'security_scan') NOT NULL,
    threat_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'LOW',
    ip_address VARCHAR(45) NULL,
    username VARCHAR(50) NULL,
    description TEXT NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Intégration

### Avec les autres modules

**EventManager :** Journalisation centralisée des événements de sécurité
- Enregistrement de tous les événements de sécurité avec contexte
- Rotation automatique des logs de sécurité selon politique
- Intégration dans le système global d'audit et monitoring

**NotificationManager :** Alertes automatiques de sécurité
- Notifications en temps réel des menaces critiques détectées
- Alertes email pour tentatives d'accès suspect ou blocages
- Rapports périodiques de sécurité avec statistiques

**LoginSystem :** Protection de l'authentification
- Intégration transparente avec système de connexion existant
- Validation automatique des tentatives de connexion
- Protection CSRF et contrôles de session renforcés

### API et hooks

Le module expose les méthodes suivantes pour intégration :
- `isIPBlocked($ip)` : Vérifie si une IP est bloquée
- `recordLoginAttempt($ip, $username, $success, $reason)` : Enregistre tentative
- `performSecurityScan()` : Lance un scan de sécurité complet

## Exemple d'utilisation

### Vérification du statut d'une IP

```php
$securityManager = SecurityManager::getInstance();

// Vérifier si IP est bloquée
$clientIP = $_SERVER['REMOTE_ADDR'];
if ($securityManager->isIPBlocked($clientIP)) {
    // Bloquer l'accès et enregistrer l'événement
    $securityManager->logSecurityEvent(
        'ip_blocked', 
        'HIGH', 
        $clientIP, 
        'Tentative d\'accès depuis IP bloquée'
    );
    die('Accès refusé');
}

// Vérifier si IP est en whitelist
if ($securityManager->isIPWhitelisted($clientIP)) {
    // Autoriser l'accès prioritaire
    echo "Accès autorisé - IP de confiance";
}
```

### Gestion des tentatives de connexion

```php
// Enregistrer une tentative de connexion
$loginSuccess = false; // Résultat de l'authentification
$username = $_POST['username'];
$ip = $_SERVER['REMOTE_ADDR'];

$securityManager->recordLoginAttempt(
    $ip,
    $username,
    $loginSuccess,
    $loginSuccess ? null : 'Mot de passe incorrect'
);

// Vérifier si l'IP doit être bloquée
if (!$loginSuccess) {
    $attemptsCount = $securityManager->getFailedAttempts($ip, 3600); // 1 heure
    if ($attemptsCount >= $securityManager->getConfig('login_attempts_max')) {
        $securityManager->blockIP($ip, 'Trop de tentatives échouées');
    }
}
```

### Validation de la sécurité des mots de passe

```php
$password = $_POST['new_password'];
$validation = $securityManager->validatePasswordStrength($password);

if (!$validation['valid']) {
    foreach ($validation['errors'] as $error) {
        echo "Erreur: " . $error . "\n";
    }
    // Exemple d'erreurs:
    // - "Le mot de passe doit contenir au moins 8 caractères"
    // - "Le mot de passe doit contenir au moins une majuscule"
    // - "Le mot de passe doit contenir au moins un chiffre"
} else {
    echo "Mot de passe accepté - Force: " . $validation['strength'] . "/100\n";
}
```

### Scan de sécurité complet

```php
// Exécuter un scan de sécurité
$scanResults = $securityManager->performSecurityScan();

echo "Niveau de menace global: " . $scanResults['threat_level'] . "\n";
echo "Problèmes détectés: " . count($scanResults['issues']) . "\n";

foreach ($scanResults['issues'] as $issue) {
    echo "- {$issue['type']}: {$issue['description']}\n";
    if ($issue['severity'] === 'CRITICAL') {
        echo "  ⚠️  ACTION IMMÉDIATE REQUISE\n";
    }
}
```

## Principes communs

### Sécurité
- Protection CSRF sur toutes les actions de configuration de sécurité
- Validation rigoureuse de toutes les entrées utilisateur
- Chiffrement des données sensibles en base de données
- Audit trail complet de toutes les modifications de sécurité

### Configuration
- Tous les paramètres de sécurité stockés en base de données chiffrée
- Configuration modifiable uniquement par super-administrateurs
- Valeurs par défaut sécurisées suivant les meilleures pratiques
- Validation en temps réel des paramètres avec feedback immédiat

### Extensibilité
- Architecture modulaire permettant ajout de nouvelles règles de sécurité
- Hooks disponibles pour intégration avec systèmes de sécurité externes
- API standardisée pour monitoring et alertes
- Support de plugins pour détection de menaces personnalisées

### Documentation
- README complet avec exemples de configuration de sécurité
- Commentaires détaillés dans le code pour toutes les fonctions critiques
- Documentation API complète avec codes de retour de sécurité
- Guide de réponse aux incidents et procédures d'urgence