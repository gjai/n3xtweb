<?php
/**
 * N3XT WEB - Back Office Maintenance Interface
 * 
 * Provides a user-friendly interface for system maintenance tasks
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

$message = '';
$messageType = '';
$csrfToken = Security::generateCSRFToken();

// Handle maintenance actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security token mismatch. Please try again.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'full_maintenance':
                    require_once dirname(__DIR__) . '/auto_maintenance.php';
                    $results = MaintenanceManager::runFullMaintenance();
                    $message = "Maintenance complète effectuée avec succès. Temps d'exécution: " . 
                              number_format($results['total_time'], 2) . "s";
                    $messageType = 'success';
                    break;
                    
                case 'clear_cache':
                    $cleared = Cache::clear();
                    $message = "Cache vidé avec succès. {$cleared} entrées supprimées.";
                    $messageType = 'success';
                    break;
                    
                case 'cleanup_logs':
                    $deleted = Logger::cleanupOldLogs(30);
                    $message = "Nettoyage des logs effectué. {$deleted} fichiers supprimés.";
                    $messageType = 'success';
                    break;
                    
                case 'optimize_database':
                    require_once dirname(__DIR__) . '/auto_maintenance.php';
                    $results = MaintenanceManager::performDatabaseOptimization();
                    if ($results['status'] === 'success') {
                        $message = "Base de données optimisée. {$results['optimized_tables']} tables traitées.";
                        $messageType = 'success';
                    } else {
                        $message = "Erreur lors de l'optimisation: " . $results['error'];
                        $messageType = 'danger';
                    }
                    break;
                    
                case 'cleanup_temp':
                    require_once dirname(__DIR__) . '/auto_maintenance.php';
                    $results = MaintenanceManager::performTempCleanup();
                    if ($results['status'] === 'success') {
                        $freed = FileHelper::formatFileSize($results['freed_space']);
                        $message = "Fichiers temporaires nettoyés. {$freed} d'espace libéré.";
                        $messageType = 'success';
                    } else {
                        $message = "Erreur lors du nettoyage: " . $results['error'];
                        $messageType = 'danger';
                    }
                    break;
                    
                case 'health_check':
                    require_once dirname(__DIR__) . '/auto_maintenance.php';
                    $results = MaintenanceManager::performHealthCheck();
                    if ($results['status'] === 'success') {
                        $score = $results['health_score'] ?? 0;
                        $message = "Vérification système terminée. Score de santé: {$score}%";
                        $messageType = $score >= 90 ? 'success' : ($score >= 70 ? 'warning' : 'danger');
                    } else {
                        $message = "Erreur lors de la vérification: " . $results['error'];
                        $messageType = 'danger';
                    }
                    break;
                    
                default:
                    $message = 'Action non reconnue.';
                    $messageType = 'warning';
            }
        } catch (Exception $e) {
            $message = 'Erreur: ' . $e->getMessage();
            $messageType = 'danger';
            Logger::log("Maintenance error: " . $e->getMessage(), LOG_LEVEL_ERROR, 'maintenance');
        }
    }
}

// Get system information
$systemInfo = [
    'php_version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit'),
    'memory_usage' => FileHelper::formatFileSize(memory_get_usage(true)),
    'peak_memory' => FileHelper::formatFileSize(memory_get_peak_usage(true)),
    'disk_free' => FileHelper::formatFileSize(disk_free_space(ROOT_PATH)),
    'server_load' => function_exists('sys_getloadavg') ? sys_getloadavg()[0] : 'N/A'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="../fav.png">
    <title>N3XT WEB - Maintenance Système</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme-custom.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <?php if (file_exists('../fav.png')): ?>
                    <img src="../fav.png" 
                         alt="N3XT WEB" 
                         style="max-width: 40px; max-height: 30px; margin-right: 10px; vertical-align: middle;">
                <?php endif; ?>
                N3XT WEB
            </h1>
            <p style="margin-top: 10px; opacity: 0.9;">Maintenance Système</p>
        </div>
        
        <div class="main-content">
            <nav class="nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a href="update.php" class="nav-link">System Update</a>
                    </li>
                    <li class="nav-item">
                        <a href="maintenance.php" class="nav-link active">Maintenance</a>
                    </li>
                    <li class="nav-item">
                        <a href="restore.php" class="nav-link">Backup & Restore</a>
                    </li>
                </ul>
            </nav>
            
            <div class="content-area">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- System Information -->
                <div class="card" style="margin-bottom: 25px;">
                    <div class="card-header">
                        <h2 class="card-title">Informations Système</h2>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <div>
                                <strong>Version PHP:</strong><br>
                                <span style="color: #666;"><?php echo $systemInfo['php_version']; ?></span>
                            </div>
                            <div>
                                <strong>Limite mémoire:</strong><br>
                                <span style="color: #666;"><?php echo $systemInfo['memory_limit']; ?></span>
                            </div>
                            <div>
                                <strong>Mémoire utilisée:</strong><br>
                                <span style="color: #666;"><?php echo $systemInfo['memory_usage']; ?></span>
                            </div>
                            <div>
                                <strong>Pic mémoire:</strong><br>
                                <span style="color: #666;"><?php echo $systemInfo['peak_memory']; ?></span>
                            </div>
                            <div>
                                <strong>Espace disque libre:</strong><br>
                                <span style="color: #666;"><?php echo $systemInfo['disk_free']; ?></span>
                            </div>
                            <div>
                                <strong>Charge serveur:</strong><br>
                                <span style="color: #666;"><?php echo $systemInfo['server_load']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card" style="margin-bottom: 25px;">
                    <div class="card-header">
                        <h2 class="card-title">Actions Rapides</h2>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="clear_cache">
                                <button type="submit" class="btn btn-primary btn-block">
                                    🗑️ Vider le cache
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="cleanup_logs">
                                <button type="submit" class="btn btn-warning btn-block">
                                    📋 Nettoyer les logs
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="cleanup_temp">
                                <button type="submit" class="btn btn-secondary btn-block">
                                    🧹 Nettoyer fichiers temp
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="optimize_database">
                                <button type="submit" class="btn btn-info btn-block">
                                    ⚡ Optimiser BDD
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Maintenance -->
                <div class="card" style="margin-bottom: 25px;">
                    <div class="card-header">
                        <h2 class="card-title">Maintenance Avancée</h2>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>Important:</strong> Ces opérations peuvent prendre du temps et affecter temporairement les performances.
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="health_check">
                                <button type="submit" class="btn btn-success btn-block">
                                    🔍 Vérification système
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="full_maintenance">
                                <button type="submit" class="btn btn-danger btn-block" 
                                        onclick="return confirm('Êtes-vous sûr de vouloir effectuer une maintenance complète ? Cette opération peut prendre plusieurs minutes.')">
                                    🔧 Maintenance complète
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Maintenance Schedule -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Programmation de Maintenance</h2>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>Maintenance automatique:</strong> Le système dispose d'un script de maintenance automatique 
                            (<code>auto_maintenance.php</code>) qui peut être exécuté via cron job.
                        </div>
                        <h3>Cron job recommandé :</h3>
                        <pre style="background: #f8f9fa; padding: 15px; border-radius: 8px; overflow-x: auto; font-family: monospace;">0 2 * * * /usr/bin/php <?php echo ROOT_PATH; ?>/auto_maintenance.php</pre>
                        <p style="margin-top: 15px; color: #666;">
                            Cette configuration exécute la maintenance automatique chaque jour à 2h du matin.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Show loading indicator for maintenance operations
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function() {
                    const button = form.querySelector('button[type="submit"]');
                    if (button) {
                        button.disabled = true;
                        button.innerHTML = '⏳ Traitement...';
                    }
                });
            });
        });
    </script>
</body>
</html>