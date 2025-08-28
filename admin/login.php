<?php
/**
 * N3XT Communication - Admin Login
 * 
 * Enhanced admin authentication with captcha, rate limiting, and security logging.
 */

// Define security constant before including any files
define('IN_N3XTWEB', true);

require_once '../includes/functions.php';

// Check if already logged in
if (Session::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$showCaptcha = false;
$isBlocked = false;
$blockTimeRemaining = 0;

// Check if IP is currently blocked
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$blockFile = LOG_PATH . '/blocked_ips.json';
$blockedIPs = [];

if (file_exists($blockFile)) {
    $blockedIPs = json_decode(file_get_contents($blockFile), true) ?: [];
}

if (isset($blockedIPs[$clientIP])) {
    $blockTime = $blockedIPs[$clientIP]['time'];
    $blockTimeRemaining = ($blockTime + LOGIN_LOCKOUT_TIME) - time();
    
    if ($blockTimeRemaining > 0) {
        $isBlocked = true;
        $error = "IP blocked due to too many failed login attempts. Try again in " . ceil($blockTimeRemaining / 60) . " minutes.";
    } else {
        // Remove expired block
        unset($blockedIPs[$clientIP]);
        file_put_contents($blockFile, json_encode($blockedIPs));
    }
}

// Get current attempt count
$attemptFile = LOG_PATH . '/login_attempts.json';
$attempts = [];

if (file_exists($attemptFile)) {
    $attempts = json_decode(file_get_contents($attemptFile), true) ?: [];
}

$currentAttempts = $attempts[$clientIP]['count'] ?? 0;
$lastAttemptTime = $attempts[$clientIP]['time'] ?? 0;

// Reset attempts if more than an hour has passed
if (time() - $lastAttemptTime > 3600) {
    $currentAttempts = 0;
}

// Show captcha after first failed attempt
$showCaptcha = $currentAttempts > 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBlocked) {
    $username = Security::sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = Security::sanitizeInput($_POST['captcha'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!Security::validateCSRFToken($csrfToken)) {
        $error = 'Invalid security token. Please try again.';
        Logger::log("CSRF token validation failed for login attempt from IP: {$clientIP}", LOG_LEVEL_WARNING, 'access');
    } 
    // Validate captcha if required
    elseif ($showCaptcha && !Captcha::validate($captcha)) {
        $error = 'Invalid captcha. Please try again.';
        Logger::logAccess($username, false, 'Invalid captcha');
    }
    // Validate credentials
    elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
        Logger::logAccess($username, false, 'Empty credentials');
    }
    else {
        // Check credentials against database
        try {
            $db = Database::getInstance();
            $user = $db->fetchOne(
                "SELECT id, username, password_hash FROM admin_users WHERE username = ? AND active = 1", 
                [$username]
            );
            
            if ($user && Security::verifyPassword($password, $user['password_hash'])) {
                // Update last login time
                $db->execute(
                    "UPDATE admin_users SET last_login = NOW() WHERE id = ?", 
                    [$user['id']]
                );
            }
        } catch (Exception $e) {
            Logger::log("Database error during login: " . $e->getMessage(), LOG_LEVEL_ERROR, 'access');
            $user = false;
        }
        
        if ($user) {
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
            // Failed login
            $error = 'Invalid username or password.';
            Logger::logAccess($username, false, 'Invalid credentials');
            
            // Increment attempt counter
            $currentAttempts++;
            $attempts[$clientIP] = [
                'count' => $currentAttempts,
                'time' => time()
            ];
            
            file_put_contents($attemptFile, json_encode($attempts));
            
            // Block IP if max attempts reached
            if ($currentAttempts >= MAX_LOGIN_ATTEMPTS) {
                $blockedIPs[$clientIP] = [
                    'time' => time(),
                    'attempts' => $currentAttempts
                ];
                file_put_contents($blockFile, json_encode($blockedIPs));
                
                $isBlocked = true;
                $blockTimeRemaining = LOGIN_LOCKOUT_TIME;
                $error = "Too many failed login attempts. IP blocked for " . ($blockTimeRemaining / 60) . " minutes.";
                
                Logger::log("IP {$clientIP} blocked after {$currentAttempts} failed login attempts", LOG_LEVEL_WARNING, 'access');
            } else {
                $showCaptcha = true;
                $remainingAttempts = MAX_LOGIN_ATTEMPTS - $currentAttempts;
                $error .= " {$remainingAttempts} attempts remaining before account lockout.";
            }
        }
    }
}

// Generate new captcha if needed
if ($showCaptcha) {
    $captchaCode = Captcha::generate();
}

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>N3XT Communication - Admin Login</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>N3XT Communication</h1>
            <p style="margin-top: 10px; opacity: 0.9;">Admin Panel Access</p>
        </div>
        
        <div class="main-content">
            <div class="card" style="max-width: 400px; margin: 50px auto;">
                <div class="card-header">
                    <h2 class="card-title">Administrator Login</h2>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($isBlocked): ?>
                        <div class="alert alert-danger">
                            <strong>Access Blocked</strong><br>
                            Your IP address has been temporarily blocked due to multiple failed login attempts.
                            <br><br>
                            <strong>Time remaining:</strong> <?php echo ceil($blockTimeRemaining / 60); ?> minutes
                        </div>
                        
                        <script>
                            // Auto-refresh when block expires
                            setTimeout(function() {
                                window.location.reload();
                            }, <?php echo $blockTimeRemaining * 1000; ?>);
                        </script>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <div class="form-group">
                                <label for="username" class="form-label">Username:</label>
                                <input type="text" 
                                       id="username" 
                                       name="username" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                       autocomplete="username"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="password" class="form-label">Password:</label>
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       class="form-control"
                                       autocomplete="current-password"
                                       required>
                            </div>
                            
                            <?php if ($showCaptcha): ?>
                                <div class="form-group">
                                    <label for="captcha" class="form-label">Security Code:</label>
                                    <div class="captcha-container">
                                        <img src="captcha.php" 
                                             alt="Captcha" 
                                             class="captcha-image"
                                             id="captcha-image">
                                        <button type="button" 
                                                class="captcha-refresh" 
                                                onclick="refreshCaptcha()"
                                                title="Refresh Captcha">↻</button>
                                    </div>
                                    <input type="text" 
                                           id="captcha" 
                                           name="captcha" 
                                           class="form-control"
                                           placeholder="Enter security code"
                                           autocomplete="off"
                                           required>
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary btn-block">
                                Login
                            </button>
                        </form>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ecf0f1; font-size: 12px; color: #7f8c8d; text-align: center;">
                            <p><strong>Security Notice:</strong></p>
                            <p>• Maximum <?php echo MAX_LOGIN_ATTEMPTS; ?> login attempts allowed</p>
                            <p>• Account locked for <?php echo LOGIN_LOCKOUT_TIME / 60; ?> minutes after failed attempts</p>
                            <p>• All access attempts are logged</p>
                            <?php if ($currentAttempts > 0): ?>
                                <p style="color: #e74c3c; font-weight: bold;">
                                    Failed attempts: <?php echo $currentAttempts; ?>/<?php echo MAX_LOGIN_ATTEMPTS; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function refreshCaptcha() {
            document.getElementById('captcha-image').src = 'captcha.php?r=' + Math.random();
            document.getElementById('captcha').value = '';
            document.getElementById('captcha').focus();
        }
        
        // Auto-focus first empty field
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            const captchaField = document.getElementById('captcha');
            
            if (!usernameField.value) {
                usernameField.focus();
            } else if (!passwordField.value) {
                passwordField.focus();
            } else if (captchaField && !captchaField.value) {
                captchaField.focus();
            }
        });
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>