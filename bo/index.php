<?php
/**
 * N3XT WEB - Admin Dashboard
 * 
 * Main admin panel interface with navigation to all back office modules.
 */

// Define security constant before including any files
define('IN_N3XTWEB', true);

require_once dirname(__DIR__) . '/includes/functions.php';

// Start secure session  
Session::start();

// Check authentication
if (!Session::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    Logger::logAccess($_SESSION['admin_username'], true, 'Logout');
    Session::logout();
    header('Location: login.php');
    exit;
}

$currentPage = $_GET['page'] ?? 'dashboard';
$csrfToken = Security::generateCSRFToken();

// Handle settings form submissions
$settingsMessage = '';
$settingsMessageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $settingsMessage = 'Security token mismatch. Please try again.';
        $settingsMessageType = 'danger';
    } else {
        switch ($_POST['action']) {
            case 'upload_logo':
                if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                    $uploadFile = $_FILES['logo_file'];
                    $maxSize = 2 * 1024 * 1024; // 2MB
                    
                    // Validate file size
                    if ($uploadFile['size'] > $maxSize) {
                        $settingsMessage = 'File size too large. Maximum size is 2MB.';
                        $settingsMessageType = 'danger';
                        break;
                    }
                    
                    // Validate file type
                    $allowedTypes = ['image/png', 'image/jpeg', 'image/gif'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $uploadFile['tmp_name']);
                    finfo_close($finfo);
                    
                    if (!in_array($mimeType, $allowedTypes)) {
                        $settingsMessage = 'Invalid file type. Only PNG, JPG, and GIF files are allowed.';
                        $settingsMessageType = 'danger';
                        break;
                    }
                    
                    // Create assets/images directory if it doesn't exist
                    $assetsDir = '../assets/images';
                    if (!is_dir($assetsDir)) {
                        mkdir($assetsDir, 0755, true);
                    }
                    
                    // Move uploaded file
                    $logoPath = $assetsDir . '/logo.png';
                    if (move_uploaded_file($uploadFile['tmp_name'], $logoPath)) {
                        $settingsMessage = 'Logo uploaded successfully!';
                        $settingsMessageType = 'success';
                        Logger::logAccess($_SESSION['admin_username'], true, 'Logo uploaded');
                    } else {
                        $settingsMessage = 'Failed to upload logo. Please check file permissions.';
                        $settingsMessageType = 'danger';
                    }
                } else {
                    $settingsMessage = 'Please select a valid file to upload.';
                    $settingsMessageType = 'danger';
                }
                break;
                
            case 'remove_logo':
                $logoPath = '../assets/images/logo.png';
                if (file_exists($logoPath)) {
                    if (unlink($logoPath)) {
                        $settingsMessage = 'Logo supprimé avec succès !';
                        $settingsMessageType = 'success';
                        Logger::logAccess($_SESSION['admin_username'], true, 'Logo removed');
                    } else {
                        $settingsMessage = 'Échec de la suppression du logo. Veuillez vérifier les permissions des fichiers.';
                        $settingsMessageType = 'danger';
                    }
                } else {
                    $settingsMessage = 'Logo non trouvé.';
                    $settingsMessageType = 'warning';
                }
                break;

            case 'toggle_maintenance':
                $configPath = '../config/config.php';
                if (file_exists($configPath)) {
                    $configContent = file_get_contents($configPath);
                    $currentMode = MAINTENANCE_MODE;
                    $newMode = !$currentMode;
                    
                    // Update the maintenance mode in config file
                    $pattern = "/define\('MAINTENANCE_MODE',\s*(true|false)\);/";
                    $replacement = "define('MAINTENANCE_MODE', " . ($newMode ? 'true' : 'false') . ");";
                    $newConfigContent = preg_replace($pattern, $replacement, $configContent);
                    
                    if (file_put_contents($configPath, $newConfigContent)) {
                        $settingsMessage = 'Mode maintenance ' . ($newMode ? 'activé' : 'désactivé') . ' avec succès !';
                        $settingsMessageType = 'success';
                        Logger::logAccess($_SESSION['admin_username'], true, 'Maintenance mode ' . ($newMode ? 'enabled' : 'disabled'));
                        
                        // Refresh page to reflect changes
                        header('Location: ?page=maintenance&success=1');
                        exit;
                    } else {
                        $settingsMessage = 'Échec de la mise à jour du mode maintenance. Veuillez vérifier les permissions des fichiers.';
                        $settingsMessageType = 'danger';
                    }
                } else {
                    $settingsMessage = 'Fichier de configuration non trouvé.';
                    $settingsMessageType = 'danger';
                }
                break;
                
            case 'update_system_settings':
                $updatedSettings = [];
                $settingsToUpdate = [
                    'maintenance_mode', 'system_version', 'site_name', 'site_description',
                    'site_language', 'site_timezone'
                ];
                
                foreach ($settingsToUpdate as $key) {
                    if (array_key_exists($key, $_POST)) {
                        $value = Security::sanitizeInput($_POST[$key]);
                        if ($key === 'maintenance_mode') {
                            $value = isset($_POST[$key]) ? '1' : '0';
                        }
                        Configuration::set($key, $value);
                        $updatedSettings[] = $key;
                    }
                }
                
                if (!empty($updatedSettings)) {
                    $settingsMessage = 'Paramètres système mis à jour avec succès: ' . implode(', ', $updatedSettings);
                    $settingsMessageType = 'success';
                    Logger::logAccess($_SESSION['admin_username'], true, 'System settings updated: ' . implode(', ', $updatedSettings));
                } else {
                    $settingsMessage = 'Aucun paramètre à mettre à jour.';
                    $settingsMessageType = 'warning';
                }
                break;
                
            case 'update_security_settings':
                $updatedSettings = [];
                $settingsToUpdate = [
                    'csrf_token_lifetime', 'session_lifetime', 'admin_session_timeout',
                    'max_login_attempts', 'login_lockout_time', 'password_min_length',
                    'enable_captcha', 'enable_login_attempts_limit', 'enable_ip_blocking',
                    'enable_ip_tracking', 'enable_database_logging', 'enable_security_headers'
                ];
                
                foreach ($settingsToUpdate as $key) {
                    if (array_key_exists($key, $_POST)) {
                        $value = $_POST[$key];
                        if (strpos($key, 'enable_') === 0) {
                            $value = isset($_POST[$key]) ? '1' : '0';
                        } else {
                            $value = Security::sanitizeInput($value, 'int');
                        }
                        Configuration::set($key, $value);
                        $updatedSettings[] = $key;
                    }
                }
                
                if (!empty($updatedSettings)) {
                    $settingsMessage = 'Paramètres de sécurité mis à jour avec succès.';
                    $settingsMessageType = 'success';
                    Logger::logAccess($_SESSION['admin_username'], true, 'Security settings updated');
                } else {
                    $settingsMessage = 'Aucun paramètre de sécurité à mettre à jour.';
                    $settingsMessageType = 'warning';
                }
                break;
                
            case 'update_performance_settings':
                $updatedSettings = [];
                $settingsToUpdate = [
                    'enable_caching', 'cache_ttl_default', 'cache_ttl_queries',
                    'enable_gzip', 'enable_asset_optimization'
                ];
                
                foreach ($settingsToUpdate as $key) {
                    if (array_key_exists($key, $_POST)) {
                        $value = $_POST[$key];
                        if (strpos($key, 'enable_') === 0) {
                            $value = isset($_POST[$key]) ? '1' : '0';
                        } else {
                            $value = Security::sanitizeInput($value, 'int');
                        }
                        Configuration::set($key, $value);
                        $updatedSettings[] = $key;
                    }
                }
                
                if (!empty($updatedSettings)) {
                    $settingsMessage = 'Paramètres de performance mis à jour avec succès.';
                    $settingsMessageType = 'success';
                    Logger::logAccess($_SESSION['admin_username'], true, 'Performance settings updated');
                } else {
                    $settingsMessage = 'Aucun paramètre de performance à mettre à jour.';
                    $settingsMessageType = 'warning';
                }
                break;
                
            case 'update_email_settings':
                $updatedSettings = [];
                $settingsToUpdate = [
                    'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from', 'smtp_from_name'
                ];
                
                foreach ($settingsToUpdate as $key) {
                    if (isset($_POST[$key])) {
                        $value = Security::sanitizeInput($_POST[$key]);
                        Configuration::set($key, $value);
                        $updatedSettings[] = $key;
                    }
                }
                
                if (!empty($updatedSettings)) {
                    $settingsMessage = 'Paramètres email mis à jour avec succès.';
                    $settingsMessageType = 'success';
                    Logger::logAccess($_SESSION['admin_username'], true, 'Email settings updated');
                } else {
                    $settingsMessage = 'Aucun paramètre email à mettre à jour.';
                    $settingsMessageType = 'warning';
                }
                break;
                
            case 'update_theme_settings':
                $updatedSettings = [];
                $settingsToUpdate = [
                    'theme_primary_color', 'theme_secondary_color', 'theme_success_color',
                    'theme_danger_color', 'theme_warning_color', 'theme_info_color',
                    'theme_font_family', 'theme_font_size', 'theme_border_radius'
                ];
                
                foreach ($settingsToUpdate as $key) {
                    if (isset($_POST[$key])) {
                        $value = Security::sanitizeInput($_POST[$key]);
                        Configuration::set($key, $value);
                        $updatedSettings[] = $key;
                    }
                }
                
                if (!empty($updatedSettings)) {
                    $settingsMessage = 'Paramètres de thème mis à jour avec succès.';
                    $settingsMessageType = 'success';
                    Logger::logAccess($_SESSION['admin_username'], true, 'Theme settings updated');
                    
                    // Generate and save CSS file
                    $css = Configuration::generateCSS();
                    $cssPath = '../assets/css/theme-custom.css';
                    if (file_put_contents($cssPath, $css)) {
                        $settingsMessage .= ' CSS personnalisé généré.';
                    }
                } else {
                    $settingsMessage = 'Aucun paramètre de thème à mettre à jour.';
                    $settingsMessageType = 'warning';
                }
                break;
                
            case 'update_debug_settings':
                $updatedSettings = [];
                $settingsToUpdate = ['debug', 'enable_error_display', 'log_queries'];
                
                foreach ($settingsToUpdate as $key) {
                    if (array_key_exists($key, $_POST)) {
                        $value = isset($_POST[$key]) ? '1' : '0';
                        Configuration::set($key, $value);
                        $updatedSettings[] = $key;
                    }
                }
                
                if (!empty($updatedSettings)) {
                    $settingsMessage = 'Paramètres de débogage mis à jour avec succès.';
                    $settingsMessageType = 'success';
                    Logger::logAccess($_SESSION['admin_username'], true, 'Debug settings updated');
                } else {
                    $settingsMessage = 'Aucun paramètre de débogage à mettre à jour.';
                    $settingsMessageType = 'warning';
                }
                break;
                
            case 'update_admin_profile':
                $updatedSettings = [];
                $profileSettings = ['admin_first_name', 'admin_last_name', 'admin_email', 'admin_language'];
                
                foreach ($profileSettings as $key) {
                    if (isset($_POST[$key])) {
                        $value = Security::sanitizeInput($_POST[$key]);
                        if ($key === 'admin_email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $settingsMessage = 'Adresse email invalide.';
                            $settingsMessageType = 'danger';
                            break 2;
                        }
                        Configuration::set($key, $value);
                        $updatedSettings[] = $key;
                    }
                }
                
                if (!empty($updatedSettings)) {
                    $settingsMessage = 'Profil administrateur mis à jour avec succès.';
                    $settingsMessageType = 'success';
                    Logger::logAccess($_SESSION['admin_username'], true, 'Admin profile updated');
                } else {
                    $settingsMessage = 'Aucune modification à apporter au profil.';
                    $settingsMessageType = 'warning';
                }
                break;
                
            case 'change_admin_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    $settingsMessage = 'Tous les champs de mot de passe sont requis.';
                    $settingsMessageType = 'danger';
                    break;
                }
                
                if ($newPassword !== $confirmPassword) {
                    $settingsMessage = 'La confirmation du mot de passe ne correspond pas.';
                    $settingsMessageType = 'danger';
                    break;
                }
                
                if (strlen($newPassword) < 8) {
                    $settingsMessage = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
                    $settingsMessageType = 'danger';
                    break;
                }
                
                try {
                    $db = Database::getInstance();
                    $admin = $db->fetchOne("SELECT password FROM " . DB_PREFIX . "admin_users WHERE username = ?", [$_SESSION['admin_username']]);
                    
                    if (!$admin || !password_verify($currentPassword, $admin['password'])) {
                        $settingsMessage = 'Mot de passe actuel incorrect.';
                        $settingsMessageType = 'danger';
                        Logger::logAccess($_SESSION['admin_username'], false, 'Failed password change attempt');
                        break;
                    }
                    
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $db->execute("UPDATE " . DB_PREFIX . "admin_users SET password = ?, updated_at = NOW() WHERE username = ?", 
                                [$hashedPassword, $_SESSION['admin_username']]);
                    
                    $settingsMessage = 'Mot de passe changé avec succès.';
                    $settingsMessageType = 'success';
                    Logger::logAccess($_SESSION['admin_username'], true, 'Password changed successfully');
                    
                } catch (Exception $e) {
                    $settingsMessage = 'Erreur lors du changement de mot de passe.';
                    $settingsMessageType = 'danger';
                    Logger::log("Password change error: " . $e->getMessage(), LOG_LEVEL_ERROR);
                }
                break;
        }
    }
}

// Get system status
$systemInfo = [
    'version' => SYSTEM_VERSION,
    'maintenance_mode' => MAINTENANCE_MODE,
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'disk_free' => FileHelper::formatFileSize(disk_free_space('.')),
    'memory_limit' => ini_get('memory_limit'),
    'upload_max' => ini_get('upload_max_filesize'),
    'session_timeout' => ADMIN_SESSION_TIMEOUT / 60 . ' minutes'
];

// Get recent logs
$recentLogs = [];
$accessLogFile = LOG_PATH . '/access.log';
if (file_exists($accessLogFile)) {
    $lines = file($accessLogFile);
    $recentLogs = array_slice(array_reverse($lines), 0, 10);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <link rel="icon" type="image/png" href="../fav.png">
    <title>N3XT WEB - Panneau d'Administration</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme-custom.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
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
                    Panneau d'Administration N3XT WEB
                </h1>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: #3498db; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                            <?php echo strtoupper(substr($_SESSION['admin_username'], 0, 1)); ?>
                        </div>
                        <span style="opacity: 0.9;"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    </div>
                    <a href="?logout=1" style="color: #ecf0f1; text-decoration: none; padding: 8px 12px; background: rgba(255,255,255,0.1); border-radius: 4px;">Déconnexion</a>
                </div>
            </div>
        </div>
        
        <div class="main-content">
            <nav class="nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="?page=dashboard" class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                            📊 Général
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link" target="_blank" style="color: #27ae60;">
                            🌍 Voir le site
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?page=showcase" class="nav-link <?php echo $currentPage === 'showcase' ? 'active' : ''; ?>">
                            🌐 Site vitrine
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?page=client-area" class="nav-link <?php echo $currentPage === 'client-area' ? 'active' : ''; ?>">
                            👥 Espace pro
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?page=ecommerce" class="nav-link <?php echo $currentPage === 'ecommerce' ? 'active' : ''; ?>">
                            🛒 Boutique e-commerce
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?page=logs" class="nav-link <?php echo $currentPage === 'logs' ? 'active' : ''; ?>">
                            📋 Logs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?page=admin-config" class="nav-link <?php echo $currentPage === 'admin-config' ? 'active' : ''; ?>">
                            👤 Configuration administrateur
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../security_scanner.php" class="nav-link" target="_blank">
                            🔒 Scanner sécurité
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../system_monitor.php" class="nav-link" target="_blank">
                            📊 Monitoring système
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="update.php" class="nav-link">
                            🔄 Mise à jour
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="restore.php" class="nav-link">
                            💾 Sauvegarde
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?page=settings" class="nav-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                            ⚙️ Paramètres
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="maintenance.php" class="nav-link">
                            🔧 Maintenance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="uninstall.php" class="nav-link" style="color: #e74c3c;">
                            🗑️ Désinstallation
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="content-area">
                <?php if ($currentPage === 'dashboard'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Vue d'ensemble du système</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <tr>
                                        <td><strong>Version du système</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['version']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Mode maintenance</strong></td>
                                        <td>
                                            <span class="<?php echo $systemInfo['maintenance_mode'] ? 'alert-warning' : 'alert-success'; ?>" style="padding: 2px 8px; border-radius: 3px; font-size: 12px;">
                                                <?php echo $systemInfo['maintenance_mode'] ? 'ACTIVÉ' : 'DÉSACTIVÉ'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Version PHP</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['php_version']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Web Server</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['server']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Free Disk Space</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['disk_free']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>PHP Memory Limit</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['memory_limit']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Taille max téléchargement</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['upload_max']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Délai d'expiration session</strong></td>
                                        <td><?php echo htmlspecialchars($systemInfo['session_timeout']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Base de données</strong></td>
                                        <td>
                                            <span class="badge badge-success">Connectée</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">État de sécurité</h2>
                        </div>
                        <div class="card-body">
                            <div id="security-status">
                                <div style="text-align: center;">
                                    <div style="margin: 20px 0;">Chargement du statut de sécurité...</div>
                                </div>
                            </div>
                            <div style="margin-top: 15px;">
                                <a href="../security_scanner.php?action=report" class="btn btn-sm btn-outline-primary" target="_blank">Rapport complet</a>
                                <a href="../cleanup.php?action=security_scan" class="btn btn-sm btn-outline-secondary" onclick="return runSecurityScan()">Scanner maintenant</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Journal d'accès récent</h2>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentLogs)): ?>
                                <p>Aucune entrée de log récente trouvée.</p>
                            <?php else: ?>
                                <div style="font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 4px;">
                                    <?php foreach ($recentLogs as $log): ?>
                                        <div style="margin-bottom: 5px; word-break: break-all;">
                                            <?php echo htmlspecialchars(trim($log)); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php elseif ($currentPage === 'logs'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Journaux système</h2>
                        </div>
                        <div class="card-body">
                            <div class="btn-group">
                                <a href="?page=logs&type=access" class="btn btn-secondary">Journal d'accès</a>
                                <a href="?page=logs&type=update" class="btn btn-secondary">Journal de mise à jour</a>
                                <a href="?page=logs&type=system" class="btn btn-secondary">Journal système</a>
                            </div>
                            
                            <?php 
                            $logType = $_GET['type'] ?? 'access';
                            $logFile = LOG_PATH . "/{$logType}.log";
                            $logTypeNames = [
                                'access' => 'Accès',
                                'update' => 'Mise à jour', 
                                'system' => 'Système'
                            ];
                            ?>
                            
                            <div style="margin-top: 20px;">
                                <h3>Journal : <?php echo $logTypeNames[$logType] ?? ucfirst($logType); ?></h3>
                                
                                <?php if (file_exists($logFile)): ?>
                                    <?php 
                                    $logContent = file_get_contents($logFile);
                                    $logLines = array_reverse(explode("\n", trim($logContent)));
                                    $logLines = array_slice($logLines, 0, 100); // Show last 100 lines
                                    ?>
                                    
                                    <div style="font-family: monospace; font-size: 12px; max-height: 500px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 10px;">
                                        <?php foreach ($logLines as $line): ?>
                                            <?php if (trim($line)): ?>
                                                <div style="margin-bottom: 3px; word-break: break-all;">
                                                    <?php echo htmlspecialchars($line); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <p style="margin-top: 10px; font-size: 12px; color: #7f8c8d;">
                                        Affichage des 100 dernières entrées. Taille du fichier journal : <?php echo FileHelper::formatFileSize(filesize($logFile)); ?>
                                    </p>
                                <?php else: ?>
                                    <p>Fichier journal non trouvé ou vide.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($currentPage === 'admin-config'): ?>
                    <?php if (!empty($settingsMessage)): ?>
                        <div class="alert alert-<?php echo $settingsMessageType; ?>">
                            <?php echo htmlspecialchars($settingsMessage); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">👤 Configuration Administrateur</h2>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Configuration du compte administrateur</strong><br>
                                Gérez les informations personnelles, sécurité et préférences de votre compte administrateur.
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h4>Informations personnelles</h4>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="action" value="update_admin_profile">
                                        
                                        <div class="form-group">
                                            <label for="admin_first_name" class="form-label">Prénom</label>
                                            <input type="text" 
                                                   id="admin_first_name" 
                                                   name="admin_first_name" 
                                                   class="form-control" 
                                                   value="<?php echo htmlspecialchars(Configuration::get('admin_first_name', '')); ?>"
                                                   placeholder="Votre prénom">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="admin_last_name" class="form-label">Nom</label>
                                            <input type="text" 
                                                   id="admin_last_name" 
                                                   name="admin_last_name" 
                                                   class="form-control" 
                                                   value="<?php echo htmlspecialchars(Configuration::get('admin_last_name', '')); ?>"
                                                   placeholder="Votre nom de famille">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="admin_email" class="form-label">Email</label>
                                            <input type="email" 
                                                   id="admin_email" 
                                                   name="admin_email" 
                                                   class="form-control" 
                                                   value="<?php echo htmlspecialchars(Configuration::get('admin_email', '')); ?>"
                                                   placeholder="votre@email.com">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="admin_language" class="form-label">Langue préférée</label>
                                            <select id="admin_language" name="admin_language" class="form-control">
                                                <option value="fr" <?php echo Configuration::get('admin_language', 'fr') === 'fr' ? 'selected' : ''; ?>>Français</option>
                                                <option value="en" <?php echo Configuration::get('admin_language', 'fr') === 'en' ? 'selected' : ''; ?>>English</option>
                                            </select>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">Mettre à jour le profil</button>
                                    </form>
                                </div>
                                
                                <div class="col-md-6">
                                    <h4>Sécurité</h4>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="action" value="change_admin_password">
                                        
                                        <div class="form-group">
                                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                                            <input type="password" 
                                                   id="current_password" 
                                                   name="current_password" 
                                                   class="form-control" 
                                                   required
                                                   placeholder="Votre mot de passe actuel">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                            <input type="password" 
                                                   id="new_password" 
                                                   name="new_password" 
                                                   class="form-control" 
                                                   required
                                                   minlength="8"
                                                   placeholder="Nouveau mot de passe (8 caractères min)">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                            <input type="password" 
                                                   id="confirm_password" 
                                                   name="confirm_password" 
                                                   class="form-control" 
                                                   required
                                                   placeholder="Confirmez le nouveau mot de passe">
                                        </div>
                                        
                                        <button type="submit" class="btn btn-warning">Changer le mot de passe</button>
                                    </form>
                                    
                                    <hr>
                                    
                                    <h5>Avatar</h5>
                                    <div class="avatar-preview" style="margin: 15px 0;">
                                        <div style="width: 64px; height: 64px; border-radius: 50%; background: #3498db; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 24px;">
                                            <?php echo strtoupper(substr($_SESSION['admin_username'], 0, 1)); ?>
                                        </div>
                                    </div>
                                    <p class="text-muted"><small>L'avatar est généré automatiquement à partir de votre nom d'utilisateur.</small></p>
                                    
                                    <h5>Sessions actives</h5>
                                    <p>Session actuelle : <span class="badge badge-success">Connecté</span></p>
                                    <p class="text-muted"><small>Dernière connexion : <?php echo date('d/m/Y H:i:s'); ?></small></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($currentPage === 'maintenance'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Mode Maintenance</h2>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Mode Maintenance</strong><br>
                                Lorsqu'il est activé, le mode maintenance affichera une page de maintenance à tous les visiteurs sauf aux administrateurs.
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Statut actuel :</label>
                                <p>
                                    <span class="<?php echo MAINTENANCE_MODE ? 'alert-warning' : 'alert-success'; ?>" style="padding: 5px 10px; border-radius: 4px;">
                                        <?php echo MAINTENANCE_MODE ? 'ACTIVÉ' : 'DÉSACTIVÉ'; ?>
                                    </span>
                                </p>
                            </div>
                            
                            <form method="POST" style="margin-top: 20px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="toggle_maintenance">
                                
                                <button type="submit" class="btn <?php echo MAINTENANCE_MODE ? 'btn-success' : 'btn-warning'; ?>" 
                                        onclick="return confirm('Êtes-vous sûr de vouloir <?php echo MAINTENANCE_MODE ? 'désactiver' : 'activer'; ?> le mode maintenance ?')">
                                    <?php echo MAINTENANCE_MODE ? 'Désactiver' : 'Activer'; ?> le mode maintenance
                                </button>
                            </form>
                            
                            <div style="margin-top: 20px;">
                                <a href="../maintenance.php?preview=1" target="_blank" class="btn btn-secondary">
                                    Prévisualiser la page de maintenance
                                </a>
                            </div>
                        </div>
                        </div>
                    </div>
                    
                <?php elseif ($currentPage === 'showcase'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Site vitrine</h2>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Gestion du site vitrine</strong><br>
                                Configurez et gérez le contenu de votre site vitrine public.
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h4>Contenu de la page d'accueil</h4>
                                    <p>Configuration du contenu principal affiché sur la page d'accueil.</p>
                                    <a href="#" class="btn btn-primary">Modifier le contenu</a>
                                </div>
                                <div class="col-md-6">
                                    <h4>Thèmes et modèles</h4>
                                    <p>Gérez l'apparence et les modèles de votre site vitrine.</p>
                                    <a href="#" class="btn btn-secondary">Gérer les thèmes</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($currentPage === 'client-area'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Espace Pro</h2>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Gestion de l'espace professionnel</strong><br>
                                Configurez l'accès et les fonctionnalités disponibles pour vos clients professionnels.
                            </div>
                            
                            <!-- Configuration de redirection -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h3>Configuration de redirection</h3>
                                </div>
                                <div class="card-body">
                                    <?php if (isset($_POST['update_pro_space_config'])): ?>
                                        <?php if (Security::verifyCSRFToken($_POST['csrf_token'] ?? '')): ?>
                                            <?php
                                            $proSpaceUrl = Security::sanitizeInput($_POST['pro_space_url'] ?? '');
                                            $enableRedirect = isset($_POST['enable_redirect']) ? 1 : 0;
                                            
                                            // Save to config file or database
                                            $configFile = '../config/pro_space.php';
                                            $config = "<?php\n// Espace Pro Configuration\ndefine('PRO_SPACE_URL', '{$proSpaceUrl}');\ndefine('PRO_SPACE_REDIRECT_ENABLED', {$enableRedirect});\n?>";
                                            file_put_contents($configFile, $config);
                                            ?>
                                            <div class="alert alert-success">Configuration mise à jour avec succès.</div>
                                        <?php else: ?>
                                            <div class="alert alert-error">Erreur de token de sécurité.</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Load current configuration
                                    $proSpaceUrl = 'client.n3xt.xyz';
                                    $enableRedirect = false;
                                    $configFile = '../config/pro_space.php';
                                    if (file_exists($configFile)) {
                                        include $configFile;
                                        if (defined('PRO_SPACE_URL')) $proSpaceUrl = PRO_SPACE_URL;
                                        if (defined('PRO_SPACE_REDIRECT_ENABLED')) $enableRedirect = PRO_SPACE_REDIRECT_ENABLED;
                                    }
                                    ?>
                                    
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="update_pro_space_config" value="1">
                                        
                                        <div class="form-group">
                                            <label for="pro_space_url" class="form-label">URL de l'espace pro</label>
                                            <input type="url" 
                                                   id="pro_space_url" 
                                                   name="pro_space_url" 
                                                   class="form-control"
                                                   value="<?php echo htmlspecialchars($proSpaceUrl); ?>"
                                                   placeholder="https://client.n3xt.xyz">
                                            <div class="form-help">URL vers laquelle rediriger les clients pour accéder à l'espace pro.</div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-check-label">
                                                <input type="checkbox" 
                                                       name="enable_redirect" 
                                                       class="form-check-input"
                                                       <?php echo $enableRedirect ? 'checked' : ''; ?>>
                                                Activer la redirection automatique
                                            </label>
                                            <div class="form-help">Si activé, les liens vers l'espace client redirigeront automatiquement vers l'URL configurée.</div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">Mettre à jour la configuration</button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <h4>Accès professionnels</h4>
                                    <p>Gérez les comptes et permissions des clients professionnels.</p>
                                    <a href="#" class="btn btn-primary">Gérer les comptes</a>
                                </div>
                                <div class="col-md-4">
                                    <h4>Documents partagés</h4>
                                    <p>Partagez des documents avec vos clients professionnels.</p>
                                    <a href="#" class="btn btn-secondary">Gérer les documents</a>
                                </div>
                                <div class="col-md-4">
                                    <h4>Communications pro</h4>
                                    <p>Système de messagerie professionnelle.</p>
                                    <a href="#" class="btn btn-info">Messages</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($currentPage === 'ecommerce'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Boutique e-commerce</h2>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <strong>Module e-commerce</strong><br>
                                Ce module peut être activé ou désactivé selon vos besoins.
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Statut de la boutique :</label>
                                <p>
                                    <span class="alert-success" style="padding: 5px 10px; border-radius: 4px;">
                                        DÉSACTIVÉ
                                    </span>
                                </p>
                            </div>
                            
                            <form method="POST" style="margin-top: 20px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="toggle_ecommerce">
                                
                                <button type="submit" class="btn btn-warning" 
                                        onclick="return confirm('Êtes-vous sûr de vouloir activer le module e-commerce ?')">
                                    Activer la boutique e-commerce
                                </button>
                            </form>
                            
                            <div style="margin-top: 20px;">
                                <h4>Fonctionnalités disponibles</h4>
                                <ul>
                                    <li>Catalogue de produits</li>
                                    <li>Gestion des commandes</li>
                                    <li>Système de paiement</li>
                                    <li>Gestion des stocks</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($currentPage === 'settings'): ?>
                    <?php if (!empty($settingsMessage)): ?>
                        <div class="alert alert-<?php echo $settingsMessageType; ?>">
                            <?php echo htmlspecialchars($settingsMessage); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Configuration System -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">🔧 Configuration du Système</h2>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Configuration centralisée :</strong> Tous les paramètres du système sont stockés en base de données et modifiables depuis cette interface.
                            </div>
                            
                            <!-- Navigation tabs for different configuration categories -->
                            <div class="config-tabs" style="margin-bottom: 20px;">
                                <div class="nav nav-tabs" style="display: flex; flex-wrap: wrap; border-bottom: 2px solid #e0e0e0;">
                                    <button class="nav-link active" onclick="showConfigTab('system')" id="tab-system">
                                        🏠 Système
                                    </button>
                                    <button class="nav-link" onclick="showConfigTab('security')" id="tab-security">
                                        🔒 Sécurité
                                    </button>
                                    <button class="nav-link" onclick="showConfigTab('performance')" id="tab-performance">
                                        ⚡ Performance
                                    </button>
                                    <button class="nav-link" onclick="showConfigTab('email')" id="tab-email">
                                        📧 Email
                                    </button>
                                    <button class="nav-link" onclick="showConfigTab('theme')" id="tab-theme">
                                        🎨 Thème
                                    </button>
                                    <button class="nav-link" onclick="showConfigTab('debug')" id="tab-debug">
                                        🐛 Debug
                                    </button>
                                    <button class="nav-link" onclick="showConfigTab('logo')" id="tab-logo">
                                        🖼️ Logo
                                    </button>
                                </div>
                            </div>
                            
                            <!-- System Configuration Tab -->
                            <div id="config-system" class="config-tab-content">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Paramètres Système</h3>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="update_system_settings">
                                            
                                            <div class="form-group">
                                                <label class="form-label">Mode Maintenance</label>
                                                <div class="form-check">
                                                    <input type="checkbox" 
                                                           name="maintenance_mode" 
                                                           id="maintenance_mode" 
                                                           class="form-check-input"
                                                           <?php echo Configuration::get('maintenance_mode') ? 'checked' : ''; ?>>
                                                    <label for="maintenance_mode" class="form-check-label">
                                                        Activer le mode maintenance
                                                    </label>
                                                </div>
                                                <small class="form-help">Désactive l'accès public au site</small>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="site_name" class="form-label">Nom du site</label>
                                                <input type="text" 
                                                       name="site_name" 
                                                       id="site_name" 
                                                       class="form-control"
                                                       value="<?php echo htmlspecialchars(Configuration::get('site_name')); ?>">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="site_description" class="form-label">Description du site</label>
                                                <textarea name="site_description" 
                                                          id="site_description" 
                                                          class="form-control" 
                                                          rows="3"><?php echo htmlspecialchars(Configuration::get('site_description')); ?></textarea>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="site_language" class="form-label">Langue</label>
                                                <select name="site_language" id="site_language" class="form-control">
                                                    <option value="fr" <?php echo Configuration::get('site_language') === 'fr' ? 'selected' : ''; ?>>🇫🇷 Français</option>
                                                    <option value="en" <?php echo Configuration::get('site_language') === 'en' ? 'selected' : ''; ?>>🇬🇧 English</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="site_timezone" class="form-label">Fuseau horaire</label>
                                                <select name="site_timezone" id="site_timezone" class="form-control">
                                                    <option value="Europe/Paris" <?php echo Configuration::get('site_timezone') === 'Europe/Paris' ? 'selected' : ''; ?>>Europe/Paris</option>
                                                    <option value="Europe/London" <?php echo Configuration::get('site_timezone') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                                                    <option value="America/New_York" <?php echo Configuration::get('site_timezone') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                                                </select>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Sauvegarder les paramètres système</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Security Configuration Tab -->
                            <div id="config-security" class="config-tab-content" style="display: none;">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Paramètres de Sécurité</h3>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="update_security_settings">
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="max_login_attempts" class="form-label">Tentatives de connexion max</label>
                                                        <input type="number" 
                                                               name="max_login_attempts" 
                                                               id="max_login_attempts" 
                                                               class="form-control"
                                                               value="<?php echo Configuration::get('max_login_attempts'); ?>"
                                                               min="1" max="20">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="login_lockout_time" class="form-label">Temps de blocage (secondes)</label>
                                                        <input type="number" 
                                                               name="login_lockout_time" 
                                                               id="login_lockout_time" 
                                                               class="form-control"
                                                               value="<?php echo Configuration::get('login_lockout_time'); ?>"
                                                               min="60" max="3600">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="session_lifetime" class="form-label">Durée de session (secondes)</label>
                                                        <input type="number" 
                                                               name="session_lifetime" 
                                                               id="session_lifetime" 
                                                               class="form-control"
                                                               value="<?php echo Configuration::get('session_lifetime'); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="password_min_length" class="form-label">Longueur min. mot de passe</label>
                                                        <input type="number" 
                                                               name="password_min_length" 
                                                               id="password_min_length" 
                                                               class="form-control"
                                                               value="<?php echo Configuration::get('password_min_length'); ?>"
                                                               min="6" max="32">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="security-toggles">
                                                <h4>Options de sécurité</h4>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input type="checkbox" 
                                                                   name="enable_captcha" 
                                                                   id="enable_captcha" 
                                                                   class="form-check-input"
                                                                   <?php echo Configuration::get('enable_captcha') ? 'checked' : ''; ?>>
                                                            <label for="enable_captcha" class="form-check-label">Activer CAPTCHA</label>
                                                        </div>
                                                        
                                                        <div class="form-check">
                                                            <input type="checkbox" 
                                                                   name="enable_login_attempts_limit" 
                                                                   id="enable_login_attempts_limit" 
                                                                   class="form-check-input"
                                                                   <?php echo Configuration::get('enable_login_attempts_limit') ? 'checked' : ''; ?>>
                                                            <label for="enable_login_attempts_limit" class="form-check-label">Limiter les tentatives de connexion</label>
                                                        </div>
                                                        
                                                        <div class="form-check">
                                                            <input type="checkbox" 
                                                                   name="enable_ip_blocking" 
                                                                   id="enable_ip_blocking" 
                                                                   class="form-check-input"
                                                                   <?php echo Configuration::get('enable_ip_blocking') ? 'checked' : ''; ?>>
                                                            <label for="enable_ip_blocking" class="form-check-label">Blocage d'IP</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input type="checkbox" 
                                                                   name="enable_ip_tracking" 
                                                                   id="enable_ip_tracking" 
                                                                   class="form-check-input"
                                                                   <?php echo Configuration::get('enable_ip_tracking') ? 'checked' : ''; ?>>
                                                            <label for="enable_ip_tracking" class="form-check-label">Suivi des adresses IP</label>
                                                        </div>
                                                        
                                                        <div class="form-check">
                                                            <input type="checkbox" 
                                                                   name="enable_database_logging" 
                                                                   id="enable_database_logging" 
                                                                   class="form-check-input"
                                                                   <?php echo Configuration::get('enable_database_logging') ? 'checked' : ''; ?>>
                                                            <label for="enable_database_logging" class="form-check-label">Journalisation en base</label>
                                                        </div>
                                                        
                                                        <div class="form-check">
                                                            <input type="checkbox" 
                                                                   name="enable_security_headers" 
                                                                   id="enable_security_headers" 
                                                                   class="form-check-input"
                                                                   <?php echo Configuration::get('enable_security_headers') ? 'checked' : ''; ?>>
                                                            <label for="enable_security_headers" class="form-check-label">En-têtes de sécurité</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Sauvegarder les paramètres de sécurité</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Performance Configuration Tab -->
                            <div id="config-performance" class="config-tab-content" style="display: none;">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Paramètres de Performance</h3>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="update_performance_settings">
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="cache_ttl_default" class="form-label">TTL Cache par défaut (secondes)</label>
                                                        <input type="number" 
                                                               name="cache_ttl_default" 
                                                               id="cache_ttl_default" 
                                                               class="form-control"
                                                               value="<?php echo Configuration::get('cache_ttl_default'); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="cache_ttl_queries" class="form-label">TTL Cache requêtes (secondes)</label>
                                                        <input type="number" 
                                                               name="cache_ttl_queries" 
                                                               id="cache_ttl_queries" 
                                                               class="form-control"
                                                               value="<?php echo Configuration::get('cache_ttl_queries'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="performance-toggles">
                                                <h4>Options de performance</h4>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input type="checkbox" 
                                                                   name="enable_caching" 
                                                                   id="enable_caching" 
                                                                   class="form-check-input"
                                                                   <?php echo Configuration::get('enable_caching') ? 'checked' : ''; ?>>
                                                            <label for="enable_caching" class="form-check-label">Activer le cache</label>
                                                        </div>
                                                        
                                                        <div class="form-check">
                                                            <input type="checkbox" 
                                                                   name="enable_gzip" 
                                                                   id="enable_gzip" 
                                                                   class="form-check-input"
                                                                   <?php echo Configuration::get('enable_gzip') ? 'checked' : ''; ?>>
                                                            <label for="enable_gzip" class="form-check-label">Compression GZIP</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input type="checkbox" 
                                                                   name="enable_asset_optimization" 
                                                                   id="enable_asset_optimization" 
                                                                   class="form-check-input"
                                                                   <?php echo Configuration::get('enable_asset_optimization') ? 'checked' : ''; ?>>
                                                            <label for="enable_asset_optimization" class="form-check-label">Optimisation des ressources</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Sauvegarder les paramètres de performance</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Email Configuration Tab -->
                            <div id="config-email" class="config-tab-content" style="display: none;">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Configuration Email (SMTP)</h3>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="update_email_settings">
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="smtp_host" class="form-label">Serveur SMTP</label>
                                                        <input type="text" 
                                                               name="smtp_host" 
                                                               id="smtp_host" 
                                                               class="form-control"
                                                               value="<?php echo htmlspecialchars(Configuration::get('smtp_host')); ?>"
                                                               placeholder="smtp.example.com">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="smtp_port" class="form-label">Port SMTP</label>
                                                        <input type="number" 
                                                               name="smtp_port" 
                                                               id="smtp_port" 
                                                               class="form-control"
                                                               value="<?php echo Configuration::get('smtp_port'); ?>"
                                                               placeholder="587">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="smtp_user" class="form-label">Utilisateur SMTP</label>
                                                        <input type="text" 
                                                               name="smtp_user" 
                                                               id="smtp_user" 
                                                               class="form-control"
                                                               value="<?php echo htmlspecialchars(Configuration::get('smtp_user')); ?>"
                                                               placeholder="user@example.com">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="smtp_pass" class="form-label">Mot de passe SMTP</label>
                                                        <input type="password" 
                                                               name="smtp_pass" 
                                                               id="smtp_pass" 
                                                               class="form-control"
                                                               value="<?php echo htmlspecialchars(Configuration::get('smtp_pass')); ?>"
                                                               placeholder="••••••••">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="smtp_from" class="form-label">Email expéditeur</label>
                                                        <input type="email" 
                                                               name="smtp_from" 
                                                               id="smtp_from" 
                                                               class="form-control"
                                                               value="<?php echo htmlspecialchars(Configuration::get('smtp_from')); ?>"
                                                               placeholder="noreply@example.com">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="smtp_from_name" class="form-label">Nom expéditeur</label>
                                                        <input type="text" 
                                                               name="smtp_from_name" 
                                                               id="smtp_from_name" 
                                                               class="form-control"
                                                               value="<?php echo htmlspecialchars(Configuration::get('smtp_from_name')); ?>"
                                                               placeholder="N3XT WEB">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Sauvegarder la configuration email</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Theme Configuration Tab -->
                            <div id="config-theme" class="config-tab-content" style="display: none;">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Personnalisation du Thème</h3>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="update_theme_settings">
                                            
                                            <div class="alert alert-info">
                                                <strong>Aperçu en temps réel :</strong> Modifiez les couleurs et voyez le résultat instantanément.
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="theme_primary_color" class="form-label">Couleur primaire</label>
                                                        <input type="color" 
                                                               name="theme_primary_color" 
                                                               id="theme_primary_color" 
                                                               class="form-control"
                                                               value="<?php echo Configuration::get('theme_primary_color'); ?>"
                                                               onchange="updatePreview()">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="theme_secondary_color" class="form-label">Couleur secondaire</label>
                                                        <input type="color" 
                                                               name="theme_secondary_color" 
                                                               id="theme_secondary_color" 
                                                               class="form-control"
                                                               value="<?php echo Configuration::get('theme_secondary_color'); ?>"
                                                               onchange="updatePreview()">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="theme_success_color" class="form-label">Couleur succès</label>
                                                        <input type="color" 
                                                               name="theme_success_color" 
                                                               id="theme_success_color" 
                                                               class="form-control"
                                                               value="<?php echo Configuration::get('theme_success_color'); ?>"
                                                               onchange="updatePreview()">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="theme_danger_color" class="form-label">Couleur danger</label>
                                                        <input type="color" 
                                                               name="theme_danger_color" 
                                                               id="theme_danger_color" 
                                                               class="form-control"
                                                               value="<?php echo Configuration::get('theme_danger_color'); ?>"
                                                               onchange="updatePreview()">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="theme_warning_color" class="form-label">Couleur avertissement</label>
                                                        <input type="color" 
                                                               name="theme_warning_color" 
                                                               id="theme_warning_color" 
                                                               class="form-control"
                                                               value="<?php echo Configuration::get('theme_warning_color'); ?>"
                                                               onchange="updatePreview()">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="theme_info_color" class="form-label">Couleur info</label>
                                                        <input type="color" 
                                                               name="theme_info_color" 
                                                               id="theme_info_color" 
                                                               class="form-control"
                                                               value="<?php echo Configuration::get('theme_info_color'); ?>"
                                                               onchange="updatePreview()">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="theme_font_family" class="form-label">Police de caractères</label>
                                                        <select name="theme_font_family" id="theme_font_family" class="form-control">
                                                            <option value="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif" 
                                                                    <?php echo Configuration::get('theme_font_family') === '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif' ? 'selected' : ''; ?>>
                                                                Système par défaut
                                                            </option>
                                                            <option value="'Inter', -apple-system, BlinkMacSystemFont, sans-serif"
                                                                    <?php echo strpos(Configuration::get('theme_font_family'), 'Inter') !== false ? 'selected' : ''; ?>>
                                                                Inter (Google Fonts)
                                                            </option>
                                                            <option value="'Roboto', sans-serif"
                                                                    <?php echo strpos(Configuration::get('theme_font_family'), 'Roboto') !== false ? 'selected' : ''; ?>>
                                                                Roboto
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="theme_font_size" class="form-label">Taille de police</label>
                                                        <select name="theme_font_size" id="theme_font_size" class="form-control">
                                                            <option value="12px" <?php echo Configuration::get('theme_font_size') === '12px' ? 'selected' : ''; ?>>12px - Petit</option>
                                                            <option value="14px" <?php echo Configuration::get('theme_font_size') === '14px' ? 'selected' : ''; ?>>14px - Normal</option>
                                                            <option value="16px" <?php echo Configuration::get('theme_font_size') === '16px' ? 'selected' : ''; ?>>16px - Grand</option>
                                                            <option value="18px" <?php echo Configuration::get('theme_font_size') === '18px' ? 'selected' : ''; ?>>18px - Très grand</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Theme Preview -->
                                            <div class="theme-preview" style="margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                                                <h4>Aperçu du thème</h4>
                                                <div style="display: flex; gap: 10px; margin: 10px 0;">
                                                    <button type="button" class="btn btn-primary" id="preview-primary">Primaire</button>
                                                    <button type="button" class="btn btn-secondary" id="preview-secondary">Secondaire</button>
                                                    <button type="button" class="btn btn-success" id="preview-success">Succès</button>
                                                    <button type="button" class="btn btn-danger" id="preview-danger">Danger</button>
                                                </div>
                                                <div style="margin: 10px 0;">
                                                    <div class="alert alert-info" id="preview-info">Exemple d'alerte info</div>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Sauvegarder le thème</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Debug Configuration Tab -->
                            <div id="config-debug" class="config-tab-content" style="display: none;">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Paramètres de Débogage</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-warning">
                                            <strong>Attention :</strong> Les paramètres de débogage ne doivent être activés qu'en développement.
                                        </div>
                                        
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="update_debug_settings">
                                            
                                            <div class="form-check">
                                                <input type="checkbox" 
                                                       name="debug" 
                                                       id="debug" 
                                                       class="form-check-input"
                                                       <?php echo Configuration::get('debug') ? 'checked' : ''; ?>>
                                                <label for="debug" class="form-check-label">
                                                    <strong>Mode Debug</strong>
                                                </label>
                                                <small class="form-help d-block">Active les informations de débogage détaillées</small>
                                            </div>
                                            
                                            <div class="form-check">
                                                <input type="checkbox" 
                                                       name="enable_error_display" 
                                                       id="enable_error_display" 
                                                       class="form-check-input"
                                                       <?php echo Configuration::get('enable_error_display') ? 'checked' : ''; ?>>
                                                <label for="enable_error_display" class="form-check-label">
                                                    <strong>Affichage des erreurs</strong>
                                                </label>
                                                <small class="form-help d-block">Affiche les erreurs PHP à l'écran</small>
                                            </div>
                                            
                                            <div class="form-check">
                                                <input type="checkbox" 
                                                       name="log_queries" 
                                                       id="log_queries" 
                                                       class="form-check-input"
                                                       <?php echo Configuration::get('log_queries') ? 'checked' : ''; ?>>
                                                <label for="log_queries" class="form-check-label">
                                                    <strong>Journalisation des requêtes</strong>
                                                </label>
                                                <small class="form-help d-block">Enregistre toutes les requêtes SQL dans les logs</small>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Sauvegarder les paramètres de debug</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Logo Management Tab -->
                            <div id="config-logo" class="config-tab-content" style="display: none;">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Gestion du Logo</h3>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        // Check for fav.png first, then assets/images/logo.png
                                        $favPath = '../fav.png';
                                        $logoPath = '../assets/images/logo.png';
                                        $currentLogo = '';
                                        $logoExists = false;
                                        
                                        if (file_exists($favPath)) {
                                            $currentLogo = $favPath;
                                            $logoExists = true;
                                        } elseif (file_exists($logoPath)) {
                                            $currentLogo = $logoPath;
                                            $logoExists = true;
                                        }
                                        ?>
                                        
                                        <?php if ($logoExists): ?>
                                            <div style="text-align: center; margin-bottom: 20px;">
                                                <img src="<?php echo $currentLogo; ?>?v=<?php echo time(); ?>" 
                                                     alt="Logo actuel" 
                                                     style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                                            </div>
                                            <p style="text-align: center; color: #666; font-size: 12px;">
                                                <?php echo $currentLogo === $favPath ? 'Logo principal (fav.png)' : 'Logo personnalisé'; ?>
                                            </p>
                                        <?php else: ?>
                                            <div style="text-align: center; margin-bottom: 20px;">
                                                <div style="width: 200px; height: 100px; border: 2px dashed #ddd; margin: 0 auto; display: flex; align-items: center; justify-content: center; color: #999; border-radius: 4px;">
                                                    <span>🚀 N3XT WEB</span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <form method="POST" enctype="multipart/form-data" style="text-align: center;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="upload_logo">
                                            
                                            <div class="form-group">
                                                <label for="logo_file" class="form-label">Télécharger un nouveau logo (PNG, JPG, GIF - Max 2MB)</label>
                                                <input type="file" 
                                                       id="logo_file" 
                                                       name="logo_file" 
                                                       class="form-control"
                                                       accept=".png,.jpg,.jpeg,.gif"
                                                       required>
                                            </div>
                                            
                                            <div class="btn-group">
                                                <button type="submit" class="btn btn-primary">Télécharger le logo</button>
                                                <?php if ($logoExists): ?>
                                                    <button type="submit" name="action" value="remove_logo" class="btn btn-danger" 
                                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer le logo actuel ?')">
                                                        Supprimer le logo
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- JavaScript for tabs and theme preview -->
                    <script>
                    function showConfigTab(tabName) {
                        // Hide all tab contents
                        const contents = document.querySelectorAll('.config-tab-content');
                        contents.forEach(content => content.style.display = 'none');
                        
                        // Remove active class from all tabs
                        const tabs = document.querySelectorAll('.nav-link');
                        tabs.forEach(tab => tab.classList.remove('active'));
                        
                        // Show selected tab content
                        document.getElementById('config-' + tabName).style.display = 'block';
                        
                        // Add active class to selected tab
                        document.getElementById('tab-' + tabName).classList.add('active');
                    }
                    
                    function updatePreview() {
                        const primary = document.getElementById('theme_primary_color').value;
                        const secondary = document.getElementById('theme_secondary_color').value;
                        const success = document.getElementById('theme_success_color').value;
                        const danger = document.getElementById('theme_danger_color').value;
                        const warning = document.getElementById('theme_warning_color').value;
                        const info = document.getElementById('theme_info_color').value;
                        
                        // Update preview buttons
                        document.getElementById('preview-primary').style.backgroundColor = primary;
                        document.getElementById('preview-secondary').style.backgroundColor = secondary;
                        document.getElementById('preview-success').style.backgroundColor = success;
                        document.getElementById('preview-danger').style.backgroundColor = danger;
                        
                        // Update preview alert
                        document.getElementById('preview-info').style.borderColor = info;
                        document.getElementById('preview-info').style.backgroundColor = info + '22';
                        document.getElementById('preview-info').style.color = info;
                    }
                    
                    // Initialize preview on page load
                    document.addEventListener('DOMContentLoaded', function() {
                        updatePreview();
                    });
                    </script>
                    
                    <style>
                    .nav-link {
                        padding: 10px 15px;
                        margin-right: 5px;
                        background: #f8f9fa;
                        border: 1px solid #ddd;
                        border-bottom: none;
                        cursor: pointer;
                        border-radius: 8px 8px 0 0;
                        text-decoration: none;
                        color: #333;
                        display: inline-block;
                        margin-bottom: -1px;
                    }
                    
                    .nav-link.active {
                        background: white;
                        border-bottom: 1px solid white;
                        font-weight: bold;
                    }
                    
                    .nav-link:hover {
                        background: #e9ecef;
                    }
                    
                    .form-check {
                        margin-bottom: 15px;
                    }
                    
                    .form-check-input {
                        margin-right: 8px;
                    }
                    
                    .btn-group {
                        display: flex;
                        gap: 10px;
                        justify-content: center;
                    }
                    
                    .theme-preview {
                        background: #f8f9fa;
                    }
                    
                    @media (max-width: 768px) {
                        .nav-link {
                            display: block;
                            margin-bottom: 5px;
                            margin-right: 0;
                        }
                        
                        .btn-group {
                            flex-direction: column;
                        }
                    }
                    </style>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh dashboard every 30 seconds
        <?php if ($currentPage === 'dashboard'): ?>
        setInterval(function() {
            if (document.hasFocus()) {
                // Only refresh if page has focus
                window.location.reload();
            }
        }, 30000);
        
        // Load security status on dashboard
        function loadSecurityStatus() {
            fetch('../cleanup.php?action=security_scan')
                .then(response => response.json())
                .then(data => {
                    const statusDiv = document.getElementById('security-status');
                    if (data.error) {
                        statusDiv.innerHTML = '<div style="color: #e74c3c;">⚠️ Erreur lors du scan de sécurité</div>';
                        return;
                    }
                    
                    let statusHtml = '';
                    let overallStatus = 'good';
                    
                    if (data.critical_issues && data.critical_issues.length > 0) {
                        overallStatus = 'critical';
                        statusHtml += '<div style="color: #e74c3c; margin-bottom: 10px;"><strong>🚨 ' + data.critical_issues.length + ' problème(s) critique(s)</strong></div>';
                    }
                    
                    if (data.warnings && data.warnings.length > 0) {
                        if (overallStatus === 'good') overallStatus = 'warning';
                        statusHtml += '<div style="color: #f39c12; margin-bottom: 10px;"><strong>⚠️ ' + data.warnings.length + ' avertissement(s)</strong></div>';
                    }
                    
                    if (overallStatus === 'good') {
                        statusHtml = '<div style="color: #27ae60;">✅ Système sécurisé</div>';
                    }
                    
                    statusHtml += '<div style="margin-top: 10px; font-size: 14px;">Score de sécurité: <strong>' + (data.score || 100) + '/100</strong></div>';
                    
                    statusDiv.innerHTML = statusHtml;
                })
                .catch(error => {
                    console.error('Security status error:', error);
                    document.getElementById('security-status').innerHTML = '<div style="color: #7f8c8d;">Impossible de charger le statut</div>';
                });
        }
        
        // Load security status when page loads
        loadSecurityStatus();
        
        function checkSecurity() {
            loadSecurityStatus();
            return true;
        }
        
        function runSecurityScan() {
            const statusDiv = document.getElementById('security-status');
            statusDiv.innerHTML = '<div style="text-align: center;">🔄 Scan en cours...</div>';
            loadSecurityStatus();
            return false;
        }
        <?php endif; ?>
        
        // Session timeout warning
        setTimeout(function() {
            if (confirm('Your session will expire soon. Do you want to stay logged in?')) {
                window.location.reload();
            }
        }, <?php echo (ADMIN_SESSION_TIMEOUT - 300) * 1000; ?>); // 5 minutes before timeout
    </script>
</body>
</html>