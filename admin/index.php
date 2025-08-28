<?php
/**
 * N3XT WEB - Fake Admin Panel (Security Decoy)
 * 
 * This is a fake admin panel designed to catch unauthorized access attempts.
 * All login attempts here will be logged and the admin will be notified.
 */

// Start session for tracking attempts
session_start();

$error = '';
$attempted_login = false;

// Get client IP
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Handle fake login attempts
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attempted_login = true;
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Log the intrusion attempt
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $clientIP,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'username_attempt' => $username,
        'password_length' => strlen($password),
        'referrer' => $_SERVER['HTTP_REFERER'] ?? 'direct'
    ];
    
    // Save to intrusion log
    $logFile = '../logs/intrusion_attempts.json';
    $logs = [];
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
    }
    $logs[] = $logEntry;
    
    // Keep only last 1000 entries
    if (count($logs) > 1000) {
        $logs = array_slice($logs, -1000);
    }
    
    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
    
    // Send email notification to admin (if config exists)
    if (file_exists('../config/config.php')) {
        try {
            define('IN_N3XTWEB', true);
            require_once '../includes/functions.php';
            
            // Get admin email from database
            $db = Database::getInstance();
            $prefix = Logger::getTablePrefix();
            $admin = $db->fetchOne("SELECT email FROM {$prefix}admin_users WHERE active = 1 LIMIT 1");
            
            if ($admin && !empty($admin['email'])) {
                $subject = "🚨 N3XT WEB - Tentative d'intrusion détectée";
                $message = "
                <h2>Tentative d'accès non autorisé détectée</h2>
                <p><strong>Date/Heure:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>Adresse IP:</strong> {$clientIP}</p>
                <p><strong>Nom d'utilisateur tenté:</strong> " . htmlspecialchars($username) . "</p>
                <p><strong>User Agent:</strong> " . htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . "</p>
                <p><strong>Referrer:</strong> " . htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'direct') . "</p>
                <br>
                <p>Cette tentative a été automatiquement bloquée et loggée.</p>
                <p>Si ce n'était pas vous, veuillez vérifier la sécurité de votre site.</p>
                ";
                
                EmailHelper::sendMail($admin['email'], $subject, $message);
            }
        } catch (Exception $e) {
            // Silently fail email notification
            error_log("Failed to send intrusion notification: " . $e->getMessage());
        }
    }
    
    // Always show fake error message
    $error = 'Nom d\'utilisateur ou mot de passe incorrect.';
    
    // Add small delay to make it seem real
    sleep(2);
}

// Generate fake CSRF token for appearance
$fake_csrf = bin2hex(random_bytes(16));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <link rel="icon" type="image/png" href="../fav.png">
    <title>N3XT WEB - Administration</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 400px;
            width: 100%;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo img {
            max-width: 60px;
            max-height: 60px;
            margin-bottom: 15px;
        }

        .logo h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .logo p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            background: #f9f9f9;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .alert-danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }

        .security-badge {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <?php if (file_exists('../fav.png')): ?>
                <img src="../fav.png" alt="N3XT WEB">
            <?php endif; ?>
            <h1>N3XT WEB</h1>
            <p>Panneau d'administration</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $fake_csrf; ?>">
            
            <div class="form-group">
                <label for="username" class="form-label">Nom d'utilisateur</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       class="form-control"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Mot de passe</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-control"
                       required>
            </div>

            <button type="submit" class="btn">Se connecter</button>
        </form>

        <div class="security-badge">
            🔒 Connexion sécurisée SSL
        </div>

        <div class="footer-text">
            N3XT WEB v2.1.0<br>
            © <?php echo date('Y'); ?> N3XT Communication
        </div>
    </div>

    <script>
        // Make it look more realistic with some JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Add some fake loading behavior
            const form = document.querySelector('form');
            form.addEventListener('submit', function() {
                const btn = document.querySelector('.btn');
                btn.textContent = 'Connexion...';
                btn.disabled = true;
            });
        });
    </script>
</body>
</html>