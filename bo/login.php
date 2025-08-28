<?php
/**
 * N3XT WEB - Admin Login
 * 
 * Enhanced admin authentication with captcha, rate limiting, security logging,
 * and forgot password functionality.
 */

// Define security constant before including any files
define('IN_N3XTWEB', true);

require_once '../includes/functions.php';

// Start secure session
Session::start();

// Check if already logged in
if (Session::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$showCaptcha = false;
$isBlocked = false;
$blockTimeRemaining = 0;
$forgotPasswordMode = isset($_GET['forgot']) && $_GET['forgot'] === '1';
$resetMode = isset($_GET['reset']) && !empty($_GET['token']);

// Check if IP is currently blocked (only if IP blocking is enabled)
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$blockFile = LOG_PATH . '/blocked_ips.json';
$blockedIPs = [];
$isBlocked = false;
$blockTimeRemaining = 0;

if (SecuritySettings::isIpBlockingEnabled() && file_exists($blockFile)) {
    $blockedIPs = json_decode(file_get_contents($blockFile), true) ?: [];
    
    if (isset($blockedIPs[$clientIP])) {
        $blockTime = $blockedIPs[$clientIP]['time'];
        $lockoutTime = SecuritySettings::getLoginLockoutTime();
        $blockTimeRemaining = ($blockTime + $lockoutTime) - time();
        
        if ($blockTimeRemaining > 0) {
            $isBlocked = true;
            $error = "IP bloquée en raison de trop nombreuses tentatives de connexion échouées. Réessayez dans " . ceil($blockTimeRemaining / 60) . " minutes.";
        } else {
            // Remove expired block
            unset($blockedIPs[$clientIP]);
            file_put_contents($blockFile, json_encode($blockedIPs));
        }
    }
}

// Get current attempt count (only if attempts limiting is enabled)
$attemptFile = LOG_PATH . '/login_attempts.json';
$attempts = [];
$currentAttempts = 0;

if (SecuritySettings::isLoginAttemptsLimitEnabled() && file_exists($attemptFile)) {
    $attempts = json_decode(file_get_contents($attemptFile), true) ?: [];
    $currentAttempts = $attempts[$clientIP]['count'] ?? 0;
    $lastAttemptTime = $attempts[$clientIP]['time'] ?? 0;

    // Reset attempts if more than an hour has passed
    if (time() - $lastAttemptTime > 3600) {
        $currentAttempts = 0;
    }
}

// Show captcha after first failed attempt (only if captcha is enabled)
$showCaptcha = SecuritySettings::isCaptchaEnabled() && $currentAttempts > 0;
$showCaptcha = $currentAttempts > 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBlocked) {
    $action = $_POST['action'] ?? 'login';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!Security::validateCSRFToken($csrfToken)) {
        $error = 'Invalid security token. Please try again.';
        Logger::log("CSRF token validation failed for login attempt from IP: {$clientIP}", LOG_LEVEL_WARNING, 'access');
    } else {
        switch ($action) {
            case 'login':
                $username = Security::sanitizeInput($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $captcha = Security::sanitizeInput($_POST['captcha'] ?? '');
                
                // Validate captcha if required
                if ($showCaptcha && !Captcha::validate($captcha)) {
                    $error = 'Captcha invalide. Veuillez réessayer.';
                    Logger::logAccess($username, false, 'Invalid captcha');
                }
                // Validate credentials
                elseif (empty($username) || empty($password)) {
                    $error = 'Veuillez saisir votre nom d\'utilisateur et mot de passe.';
                    Logger::logAccess($username, false, 'Empty credentials');
                }
                else {
                    // Check credentials against database
                    try {
                        $db = Database::getInstance();
                        $prefix = Logger::getTablePrefix();
                        $user = $db->fetchOne(
                            "SELECT id, username, password_hash, email FROM {$prefix}admin_users WHERE username = ? AND active = 1", 
                            [$username]
                        );
                        
                        if ($user && Security::verifyPassword($password, $user['password_hash'])) {
                            // Update last login time
                            $db->execute(
                                "UPDATE {$prefix}admin_users SET last_login = NOW() WHERE id = ?", 
                                [$user['id']]
                            );
                            
                            // Successful login
                            Session::login($username);
                            Logger::logAccess($username, true, 'Successful login');
                            
                            // Clear failed attempts
                            if (isset($attempts[$clientIP])) {
                                unset($attempts[$clientIP]);
                                file_put_contents($attemptFile, json_encode($attempts));
                            }
                            
                            header('Location: index.php');
                            exit;
                        } else {
                            $error = 'Nom d\'utilisateur ou mot de passe incorrect.';
                            Logger::logAccess($username, false, 'Invalid credentials');
                            
                            // Increment failed attempts
                            $currentAttempts++;
                            $attempts[$clientIP] = [
                                'count' => $currentAttempts,
                                'time' => time()
                            ];
                            file_put_contents($attemptFile, json_encode($attempts));
                            
                            // Block IP after max attempts (only if IP blocking is enabled)
                            $maxAttempts = SecuritySettings::getMaxLoginAttempts();
                            if (SecuritySettings::isIpBlockingEnabled() && $currentAttempts >= $maxAttempts) {
                                $blockedIPs[$clientIP] = ['time' => time()];
                                file_put_contents($blockFile, json_encode($blockedIPs));
                                $isBlocked = true;
                                $lockoutTime = SecuritySettings::getLoginLockoutTime();
                                $error = "Trop de tentatives échouées. IP bloquée pour " . ($lockoutTime / 60) . " minutes.";
                                Logger::log("IP {$clientIP} blocked after {$currentAttempts} failed login attempts", LOG_LEVEL_WARNING, 'access');
                            } else {
                                $showCaptcha = SecuritySettings::isCaptchaEnabled();
                                $remainingAttempts = $maxAttempts - $currentAttempts;
                                if (SecuritySettings::isLoginAttemptsLimitEnabled()) {
                                    $error .= " {$remainingAttempts} tentatives restantes avant blocage.";
                                }
                            }
                        }
                    } catch (Exception $e) {
                        Logger::log("Database error during login: " . $e->getMessage(), LOG_LEVEL_ERROR, 'access');
                        
                        // Provide more detailed database connection diagnostics
                        $dbDiagnostic = getDatabaseDiagnostic($e);
                        $error = $dbDiagnostic['message'];
                        
                        // Store diagnostic info for potential config testing
                        $_SESSION['last_db_error'] = [
                            'message' => $e->getMessage(),
                            'code' => $e->getCode(),
                            'time' => time()
                        ];
                    }
                }
                break;
                
            case 'forgot_password':
                $email = Security::sanitizeInput($_POST['email'] ?? '');
                if (empty($email)) {
                    $error = 'Veuillez saisir votre adresse email.';
                } elseif (!Security::validateEmail($email)) {
                    $error = 'Adresse email invalide.';
                } else {
                    try {
                        $db = Database::getInstance();
                        $prefix = Logger::getTablePrefix();
                        $user = $db->fetchOne(
                            "SELECT id, username, email FROM {$prefix}admin_users WHERE email = ? AND active = 1", 
                            [$email]
                        );
                        
                        if ($user) {
                            // Generate reset token
                            $resetToken = bin2hex(random_bytes(32));
                            $expiryTime = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                            
                            // Save reset token
                            $db->execute(
                                "UPDATE {$prefix}admin_users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?",
                                [$resetToken, $expiryTime, $user['id']]
                            );
                            
                            // Send reset email
                            $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '?reset=1&token=' . $resetToken;
                            
                            $subject = "N3XT WEB - Réinitialisation de mot de passe";
                            $message = "
                            <h2>Réinitialisation de votre mot de passe</h2>
                            <p>Bonjour " . htmlspecialchars($user['username']) . ",</p>
                            <p>Vous avez demandé la réinitialisation de votre mot de passe pour N3XT WEB.</p>
                            <p>Cliquez sur le lien suivant pour réinitialiser votre mot de passe :</p>
                            <p><a href='{$resetLink}' style='color: #667eea; text-decoration: none; font-weight: bold;'>{$resetLink}</a></p>
                            <p>Ce lien expire dans 1 heure.</p>
                            <p>Si vous n'avez pas demandé cette réinitialisation, ignorez ce message.</p>
                            ";
                            
                            if (EmailHelper::sendMail($email, $subject, $message)) {
                                $success = 'Un email de réinitialisation a été envoyé à votre adresse.';
                                Logger::log("Password reset requested for user: {$user['username']}", LOG_LEVEL_INFO, 'access');
                            } else {
                                $error = 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.';
                            }
                        } else {
                            // Don't reveal if email exists or not
                            $success = 'Si cette adresse email existe, un lien de réinitialisation a été envoyé.';
                        }
                    } catch (Exception $e) {
                        Logger::log("Forgot password error: " . $e->getMessage(), LOG_LEVEL_ERROR, 'access');
                        
                        // Check if it's a database connection issue
                        if (strpos($e->getMessage(), 'Database connection') !== false || 
                            strpos(get_class($e), 'PDO') !== false) {
                            $dbDiagnostic = getDatabaseDiagnostic($e);
                            $error = 'Erreur de base de données: ' . $dbDiagnostic['message'];
                        } else {
                            $error = 'Erreur interne. Veuillez réessayer.';
                        }
                    }
                }
                break;
                
            case 'reset_password':
                $token = Security::sanitizeInput($_POST['token'] ?? '');
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (empty($newPassword) || empty($confirmPassword)) {
                    $error = 'Veuillez remplir tous les champs.';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'Les mots de passe ne correspondent pas.';
                } elseif (strlen($newPassword) < 8) {
                    $error = 'Le mot de passe doit contenir au moins 8 caractères.';
                } else {
                    try {
                        $db = Database::getInstance();
                        $prefix = Logger::getTablePrefix();
                        $user = $db->fetchOne(
                            "SELECT id, username FROM {$prefix}admin_users WHERE reset_token = ? AND reset_token_expiry > NOW() AND active = 1",
                            [$token]
                        );
                        
                        if ($user) {
                            // Update password
                            $hashedPassword = Security::hashPassword($newPassword);
                            $db->execute(
                                "UPDATE {$prefix}admin_users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?",
                                [$hashedPassword, $user['id']]
                            );
                            
                            $success = 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.';
                            Logger::log("Password reset completed for user: {$user['username']}", LOG_LEVEL_INFO, 'access');
                            $resetMode = false;
                        } else {
                            $error = 'Token de réinitialisation invalide ou expiré.';
                        }
                    } catch (Exception $e) {
                        Logger::log("Password reset error: " . $e->getMessage(), LOG_LEVEL_ERROR, 'access');
                        
                        // Check if it's a database connection issue
                        if (strpos($e->getMessage(), 'Database connection') !== false || 
                            strpos(get_class($e), 'PDO') !== false) {
                            $dbDiagnostic = getDatabaseDiagnostic($e);
                            $error = 'Erreur de base de données: ' . $dbDiagnostic['message'];
                        } else {
                            $error = 'Erreur lors de la réinitialisation. Veuillez réessayer.';
                        }
                    }
                }
                break;
                
            case 'test_db':
                // Test database connection
                if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
                    $testResult = Database::testConnection(DB_HOST, DB_NAME, DB_USER, DB_PASS);
                    
                    if ($testResult['success']) {
                        $success = 'Connexion à la base de données réussie ! Configuration correcte.';
                    } else {
                        $error = 'Test de connexion échoué: ' . $testResult['message'];
                    }
                } else {
                    $error = 'Configuration de base de données manquante. Vérifiez le fichier config/config.php';
                }
                break;
        }
    }
}
// Handle reset token validation for reset mode
if ($resetMode) {
    $token = Security::sanitizeInput($_GET['token']);
    try {
        $db = Database::getInstance();
        $prefix = Logger::getTablePrefix();
        $user = $db->fetchOne(
            "SELECT id FROM {$prefix}admin_users WHERE reset_token = ? AND reset_token_expiry > NOW() AND active = 1",
            [$token]
        );
        
        if (!$user) {
            $error = 'Token de réinitialisation invalide ou expiré.';
            $resetMode = false;
        }
    } catch (Exception $e) {
        $dbDiagnostic = getDatabaseDiagnostic($e);
        $error = 'Erreur de validation du token: ' . $dbDiagnostic['message'];
        $resetMode = false;
    }
}

/**
 * Get detailed database diagnostic message
 */
function getDatabaseDiagnostic($exception) {
    $code = $exception->getCode();
    $message = $exception->getMessage();
    
    // Try to test connection with current config to provide specific feedback
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        $testResult = Database::testConnection(DB_HOST, DB_NAME, DB_USER, DB_PASS);
        
        if (!$testResult['success']) {
            return [
                'message' => 'Erreur de connexion à la base de données: ' . $testResult['message'],
                'code' => $testResult['code'] ?? $code,
                'suggestion' => 'Vérifiez la configuration de la base de données dans config/config.php'
            ];
        }
    }
    
    // Fallback to generic database error with suggestion
    return [
        'message' => 'Erreur de connexion à la base de données. Vérifiez la configuration et les credentials.',
        'code' => $code,
        'suggestion' => 'Consultez les logs pour plus de détails ou testez la connexion dans la configuration.'
    ];
}

// Generate new captcha if needed
if ($showCaptcha) {
    $captchaCode = Captcha::generate();
}

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <link rel="icon" type="image/png" href="../fav.png">
    <title>N3XT WEB - Connexion Administrateur</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .forgot-password-link {
            text-align: center;
            margin-top: 15px;
        }
        .forgot-password-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        .forgot-password-link a:hover {
            text-decoration: underline;
        }
        .mode-switch {
            text-align: center;
            margin-bottom: 20px;
        }
        .mode-switch a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        .password-strength {
            font-size: 12px;
            margin-top: 5px;
        }
        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <?php 
                $logoPath = '../fav.png';
                if (file_exists($logoPath)): ?>
                    <img src="<?php echo $logoPath; ?>?v=<?php echo time(); ?>" 
                         alt="N3XT WEB" 
                         style="max-width: 40px; max-height: 30px; margin-right: 10px; vertical-align: middle;">
                <?php else:
                    $logoPath = '../assets/images/logo.png';
                    if (file_exists($logoPath)): ?>
                        <img src="<?php echo $logoPath; ?>?v=<?php echo time(); ?>" 
                             alt="N3XT WEB" 
                             style="max-width: 40px; max-height: 30px; margin-right: 10px; vertical-align: middle;">
                    <?php endif; 
                endif; ?>
                N3XT WEB
            </h1>
            <p style="margin-top: 10px; opacity: 0.9;">
                <?php if ($resetMode): ?>
                    Réinitialisation du mot de passe
                <?php elseif ($forgotPasswordMode): ?>
                    Mot de passe oublié
                <?php else: ?>
                    Accès au panneau d'administration
                <?php endif; ?>
            </p>
        </div>
        
        <div class="main-content">
            <div class="card" style="max-width: 400px; margin: 50px auto;">
                <div class="card-header">
                    <h2 class="card-title">
                        <?php if ($resetMode): ?>
                            Nouveau mot de passe
                        <?php elseif ($forgotPasswordMode): ?>
                            Récupération du mot de passe
                        <?php else: ?>
                            Connexion Administrateur
                        <?php endif; ?>
                    </h2>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($isBlocked): ?>
                        <div class="alert alert-danger">
                            <strong>Accès bloqué</strong><br>
                            Votre adresse IP a été temporairement bloquée en raison de plusieurs tentatives de connexion échouées.
                            <br><br>
                            <strong>Temps restant:</strong> <?php echo ceil($blockTimeRemaining / 60); ?> minutes
                        </div>
                        
                        <script>
                            // Auto-refresh when block expires
                            setTimeout(function() {
                                window.location.reload();
                            }, <?php echo $blockTimeRemaining * 1000; ?>);
                        </script>
                        
                    <?php elseif ($resetMode): ?>
                        <!-- Password Reset Form -->
                        <div class="mode-switch">
                            <a href="login.php">← Retour à la connexion</a>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <div class="form-group">
                                <label for="new_password" class="form-label">Nouveau mot de passe:</label>
                                <input type="password" 
                                       id="new_password" 
                                       name="new_password" 
                                       class="form-control"
                                       onkeyup="checkPasswordStrength(this.value)"
                                       minlength="8"
                                       required>
                                <div id="password-strength" class="password-strength"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe:</label>
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       class="form-control"
                                       onkeyup="checkPasswordMatch()"
                                       minlength="8"
                                       required>
                                <div id="password-match" class="password-strength"></div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">
                                Réinitialiser le mot de passe
                            </button>
                        </form>
                        
                    <?php elseif ($forgotPasswordMode): ?>
                        <!-- Forgot Password Form -->
                        <div class="mode-switch">
                            <a href="login.php">← Retour à la connexion</a>
                        </div>
                        
                        <p style="text-align: center; color: #666; margin-bottom: 20px;">
                            Saisissez votre adresse email pour recevoir un lien de réinitialisation.
                        </p>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="forgot_password">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Adresse email:</label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">
                                Envoyer le lien de réinitialisation
                            </button>
                        </form>
                        
                    <?php else: ?>
                        <!-- Login Form -->
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="login">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <div class="form-group">
                                <label for="username" class="form-label">Nom d'utilisateur:</label>
                                <input type="text" 
                                       id="username" 
                                       name="username" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                       autocomplete="username"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="password" class="form-label">Mot de passe:</label>
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       class="form-control"
                                       autocomplete="current-password"
                                       required>
                            </div>
                            
                            <?php if ($showCaptcha): ?>
                                <div class="form-group">
                                    <label for="captcha" class="form-label">Code de sécurité:</label>
                                    <div class="captcha-container">
                                        <img src="captcha.php" 
                                             alt="Captcha" 
                                             class="captcha-image"
                                             id="captcha-image">
                                        <button type="button" 
                                                class="captcha-refresh" 
                                                onclick="refreshCaptcha()"
                                                title="Actualiser le captcha">↻</button>
                                    </div>
                                    <input type="text" 
                                           id="captcha" 
                                           name="captcha" 
                                           class="form-control"
                                           placeholder="Saisissez le code de sécurité"
                                           autocomplete="off"
                                           required>
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary btn-block">
                                Se connecter
                            </button>
                        </form>
                        
                        <!-- Database Test Form -->
                        <form method="POST" action="" style="margin-top: 10px;">
                            <input type="hidden" name="action" value="test_db">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <button type="submit" class="btn btn-secondary btn-block" style="background: #6c757d; font-size: 12px; padding: 8px;">
                                🔧 Tester la connexion BDD
                            </button>
                        </form>
                        
                        <div class="forgot-password-link">
                            <a href="?forgot=1">Mot de passe oublié ?</a>
                        </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ecf0f1; font-size: 12px; color: #7f8c8d; text-align: center;">
                        <p><strong>Sécurité:</strong></p>
                        <?php if (SecuritySettings::isLoginAttemptsLimitEnabled()): ?>
                            <p>• Maximum <?php echo SecuritySettings::getMaxLoginAttempts(); ?> tentatives de connexion autorisées</p>
                        <?php endif; ?>
                        <?php if (SecuritySettings::isIpBlockingEnabled()): ?>
                            <p>• Compte bloqué pendant <?php echo SecuritySettings::getLoginLockoutTime() / 60; ?> minutes après échec</p>
                        <?php endif; ?>
                        <p>• Toutes les tentatives d'accès sont enregistrées</p>
                        <?php if ($currentAttempts > 0 && !$isBlocked && SecuritySettings::isLoginAttemptsLimitEnabled()): ?>
                            <p style="color: #e74c3c; font-weight: bold;">
                                ⚠️ <?php echo $currentAttempts; ?> tentative(s) échouée(s)
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function refreshCaptcha() {
            document.getElementById('captcha-image').src = 'captcha.php?' + Math.random();
            document.getElementById('captcha').value = '';
        }
        
        function checkPasswordStrength(password) {
            const strengthDiv = document.getElementById('password-strength');
            if (!strengthDiv) return;
            
            let score = 0;
            let feedback = [];
            
            if (password.length >= 8) score++;
            else feedback.push('Au moins 8 caractères');
            
            if (/[A-Z]/.test(password)) score++;
            else feedback.push('Une majuscule');
            
            if (/[a-z]/.test(password)) score++;
            else feedback.push('Une minuscule');
            
            if (/[0-9]/.test(password)) score++;
            else feedback.push('Un chiffre');
            
            if (/[^A-Za-z0-9]/.test(password)) score++;
            else feedback.push('Un caractère spécial');
            
            if (score < 3) {
                strengthDiv.className = 'password-strength strength-weak';
                strengthDiv.textContent = 'Faible - Manque: ' + feedback.join(', ');
            } else if (score < 5) {
                strengthDiv.className = 'password-strength strength-medium';
                strengthDiv.textContent = 'Moyen - Manque: ' + feedback.join(', ');
            } else {
                strengthDiv.className = 'password-strength strength-strong';
                strengthDiv.textContent = 'Fort ✓';
            }
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password-match');
            
            if (!matchDiv) return;
            
            if (confirm === '') {
                matchDiv.textContent = '';
                return;
            }
            
            if (password === confirm) {
                matchDiv.className = 'password-strength strength-strong';
                matchDiv.textContent = 'Les mots de passe correspondent ✓';
            } else {
                matchDiv.className = 'password-strength strength-weak';
                matchDiv.textContent = 'Les mots de passe ne correspondent pas';
            }
        }
    </script>
</body>
</html>
