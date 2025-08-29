<?php
/**
 * N3XT WEB - Admin Dashboard
 * 
 * Main admin panel interface with navigation to all back office modules.
 */

// Define security constant before including any files
define('IN_N3XTWEB', true);

require_once '../includes/functions.php';

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
                
            case 'test_database':
                try {
                    $db = Database::getInstance();
                    $result = $db->fetchOne("SELECT 1 as test, NOW() as current_time");
                    if ($result) {
                        $settingsMessage = 'Database connection successful! Server time: ' . $result['current_time'];
                        $settingsMessageType = 'success';
                        Logger::logAccess($_SESSION['admin_username'], true, 'Database connection test successful');
                    } else {
                        $settingsMessage = 'Database connection test failed - no result returned.';
                        $settingsMessageType = 'danger';
                    }
                } catch (Exception $e) {
                    $settingsMessage = 'Database connection test failed: ' . $e->getMessage();
                    $settingsMessageType = 'danger';
                    Logger::logAccess($_SESSION['admin_username'], false, 'Database connection test failed: ' . $e->getMessage());
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
                        <a href="?page=users" class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                            👤 Utilisateurs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?page=logs" class="nav-link <?php echo $currentPage === 'logs' ? 'active' : ''; ?>">
                            📋 Logs
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
                        <a href="?page=maintenance" class="nav-link <?php echo $currentPage === 'maintenance' ? 'active' : ''; ?>">
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
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                <input type="hidden" name="action" value="test_database">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Tester la connexion</button>
                                            </form>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Actions rapides</h2>
                        </div>
                        <div class="card-body">
                            <div class="btn-group">
                                <a href="update.php" class="btn btn-primary">Mise à jour</a>
                                <a href="restore.php" class="btn btn-success">Sauvegarde</a>
                                <a href="?page=maintenance" class="btn btn-warning">Mode maintenance</a>
                                <a href="?page=logs" class="btn btn-secondary">Voir les logs</a>
                                <a href="../security_scanner.php?action=quick_check" class="btn btn-info" onclick="return checkSecurity()" target="_blank">Scanner sécurité</a>
                                <a href="../system_monitor.php" class="btn btn-info" target="_blank">Monitoring</a>
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
                    
                <?php elseif ($currentPage === 'users'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Gestion des utilisateurs</h2>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Administration des utilisateurs</strong><br>
                                Gérez les comptes administrateurs et utilisateurs du système.
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h4>Administrateurs</h4>
                                    <p>Comptes avec accès total au système.</p>
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Nom d'utilisateur</th>
                                                <th>Dernière connexion</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?php echo htmlspecialchars($_SESSION['admin_username']); ?></td>
                                                <td>Maintenant</td>
                                                <td>
                                                    <a href="#" class="btn btn-sm btn-primary">Modifier</a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <a href="#" class="btn btn-success">Ajouter un administrateur</a>
                                </div>
                                <div class="col-md-6">
                                    <h4>Utilisateurs clients</h4>
                                    <p>Comptes clients avec accès à l'espace client.</p>
                                    <p class="text-muted">Aucun utilisateur client configuré.</p>
                                    <a href="#" class="btn btn-primary">Ajouter un utilisateur</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($currentPage === 'settings'): ?>
                    <?php if (!empty($settingsMessage)): ?>
                        <div class="alert alert-<?php echo $settingsMessageType; ?>">
                            <?php echo htmlspecialchars($settingsMessage); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Paramètres du système</h2>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Gestion du logo :</strong> Téléchargez et gérez le logo de votre système.
                            </div>
                            
                            <!-- Logo Management Section -->
                            <div class="card" style="margin-bottom: 20px;">
                                <div class="card-header">
                                    <h3 class="card-title">Logo actuel</h3>
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
                            
                            <!-- Language Settings Section -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Paramètres de langue</h3>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info">
                                        <strong>Note :</strong> Les paramètres de langue sont configurés lors de l'installation. 
                                        Le système prend en charge les langues française et anglaise.
                                    </div>
                                    
                                    <p><strong>Langues disponibles :</strong></p>
                                    <ul>
                                        <li>🇫🇷 Français (French) - Par défaut</li>
                                        <li>🇬🇧 English</li>
                                    </ul>
                                    
                                    <p><em>La sélection de langue est disponible pendant le processus d'installation et affecte tous les messages et interfaces du système.</em></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
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