# Module SecurityManager - N3XT WEB

## Vue d'ensemble
Le module SecurityManager fournit des politiques de sécurité complètes, la détection de menaces et des mécanismes de protection pour le système N3XT WEB.

## Widgets disponibles

### SecurityAlertsWidget

Widget principal qui affiche les alertes de sécurité et le statut de protection du système.

#### Fonctionnalités
- **Alertes de sécurité** : Affichage des menaces et incidents en temps réel
- **Statut de protection** : Vue d'ensemble de l'état de sécurité global
- **Indicateurs de menace** : Métriques et analyses des risques
- **Actions de sécurité** : Interface pour traiter les alertes

#### Configuration

```php
$config = [
    'enabled' => true,
    'title' => 'Alertes de sécurité',
    'description' => 'Affiche les alertes de sécurité et le statut de protection du système',
    'refresh_interval' => 60,
    'max_alerts' => 10,
    'show_resolved' => false,
    'alert_levels' => ['critical', 'high', 'medium', 'low'],
    'auto_scan_enabled' => true
];
```

#### Utilisation

```php
// Instanciation du widget
$widget = new SecurityAlertsWidget();

// Récupération des données
$data = $widget->getData();

// Rendu HTML
echo $widget->render();
```

## Fonctionnalités principales
- **Protection de connexion** : Protection contre la force brute et blocage d'IP
- **Sécurité des mots de passe** : Validation de la force et exigences de complexité
- **Gestion de session** : Gestion sécurisée des sessions avec contrôles de timeout
- **Gestion IP** : Fonctionnalité de liste blanche/noire
- **Scan de sécurité** : Évaluation automatisée de la sécurité
- **Détection de menaces** : Surveillance du niveau de menace en temps réel
- **Audit des logs** : Journalisation complète des événements de sécurité
- **Authentification à deux facteurs** : Sécurité d'authentification renforcée (configurable)

## Configuration
Module configuration is stored in the `{prefix}security_config` table:

| Setting | Default | Description |
|---------|---------|-------------|
| `security_login_attempts_max` | 5 | Maximum login attempts before lockout |
| `security_lockout_duration` | 900 | Lockout duration in seconds |
| `security_session_timeout` | 3600 | Session timeout in seconds |
| `security_password_min_length` | 8 | Minimum password length |
| `security_password_complexity` | 1 | Require complex passwords |
| `security_ip_whitelist` | '' | Comma-separated IP whitelist |
| `security_ip_blacklist` | '' | Comma-separated IP blacklist |
| `security_captcha_enabled` | 0 | Enable CAPTCHA protection |
| `security_two_factor_enabled` | 0 | Enable two-factor authentication |
| `security_audit_logging` | 1 | Enable security audit logging |

## Security Threat Levels
- `LOW` - Normal security state
- `MEDIUM` - Increased caution required
- `HIGH` - Active security concerns
- `CRITICAL` - Immediate security action required

## Security Events
- `login_success` - Successful authentication
- `login_failed` - Failed authentication attempt
- `login_blocked` - Login attempt from blocked IP
- `ip_blocked` - IP address blocked due to suspicious activity
- `suspicious_activity` - Unusual behavior detected
- `bruteforce_attempt` - Brute force attack detected
- `security_scan` - Security scan performed

## Usage

### Check IP Status
```php
$securityManager = SecurityManager::getInstance();

// Check if IP is blocked
if ($securityManager->isIPBlocked($ip)) {
    // Handle blocked IP
}

// Check if IP is whitelisted
if ($securityManager->isIPWhitelisted($ip)) {
    // Allow access
}
```

### Record Login Attempts
```php
$securityManager->recordLoginAttempt(
    $ip,
    $username,
    $success,
    $failureReason
);
```

### Validate Password Strength
```php
$validation = $securityManager->validatePasswordStrength($password);
if (!$validation['valid']) {
    foreach ($validation['errors'] as $error) {
        echo $error . "\n";
    }
}
```

### Security Scanning
```php
$scanResults = $securityManager->performSecurityScan();
echo "Threat Level: " . $scanResults['threat_level'];
echo "Issues: " . implode(', ', $scanResults['issues']);
```

### Session Management
```php
// Generate secure token
$token = $securityManager->generateSecureToken();

// Check session validity
$isValid = $securityManager->isSessionValid($sessionStart);
```

### Input Sanitization
```php
$cleanInput = $securityManager->sanitizeInput($_POST['data']);
```

## Database Schema
The module uses the following tables:
- `{prefix}login_attempts` - Login attempt tracking
- `{prefix}security_config` - Module configuration

## Security Features

### Brute Force Protection
- Automatic IP blocking after configured failed attempts
- Configurable lockout duration
- Real-time threat assessment

### Password Security
- Configurable minimum length requirements
- Optional complexity requirements (uppercase, lowercase, numbers, special characters)
- Password strength validation

### Session Security
- Configurable session timeouts
- Secure token generation
- Session validity checking

### IP Management
- IP whitelist for trusted addresses
- IP blacklist for known threats
- Dynamic IP blocking based on behavior

### Security Monitoring
- Real-time security scanning
- Threat level assessment
- Security recommendations
- Audit trail logging

## Integration
The SecurityManager integrates with:
- EventManager (security event logging)
- Login system (authentication protection)
- Session management (security controls)

## Administration
Security management is available through the back office at `/bo/security.php` (when implemented).

## Best Practices
1. Regularly review blocked IPs
2. Monitor security scan results
3. Update security configurations based on threat landscape
4. Enable two-factor authentication for enhanced security
5. Regularly review audit logs
6. Keep IP whitelists and blacklists updated

## Migration
Module migrations are tracked in the `{prefix}module_migrations` table.