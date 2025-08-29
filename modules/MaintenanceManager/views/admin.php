<?php
/**
 * N3XT WEB - MaintenanceManager View
 * Interface d'administration pour le gestionnaire de maintenance
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

$maintenanceManager = ModulesLoader::getModule('MaintenanceManager');
$maintenanceHistory = $maintenanceManager->getMaintenanceHistory(20);
$statistics = $maintenanceManager->getStatistics();

// Traitement des actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (Security::validateCSRFToken($_POST['csrf_token'])) {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'cleanup_logs':
                    $result = $maintenanceManager->cleanupLogs();
                    $message = "Nettoyage des logs terminé. Fichiers supprimés : {$result['files_deleted']}, Espace libéré : " . FileHelper::formatFileSize($result['space_freed']);
                    $messageType = 'success';
                    break;
                    
                case 'cleanup_backups':
                    $result = $maintenanceManager->cleanupBackups();
                    $message = "Nettoyage des sauvegardes terminé. Fichiers supprimés : {$result['files_deleted']}, Espace libéré : " . FileHelper::formatFileSize($result['space_freed']);
                    $messageType = 'success';
                    break;
                    
                case 'cleanup_temp':
                    $result = $maintenanceManager->cleanupTempFiles();
                    $message = "Nettoyage des fichiers temporaires terminé. Fichiers supprimés : {$result['files_deleted']}, Espace libéré : " . FileHelper::formatFileSize($result['space_freed']);
                    $messageType = 'success';
                    break;
                    
                case 'optimize_database':
                    $result = $maintenanceManager->optimizeDatabase();
                    $message = "Optimisation de la base de données terminée. Tables optimisées : {$result['tables_optimized']}";
                    $messageType = 'success';
                    break;
                    
                case 'full_maintenance':
                    $result = $maintenanceManager->forceCleanup(['logs', 'backups', 'temp_files', 'database']);
                    $totalDeleted = 0;
                    $totalSpaceFreed = 0;
                    foreach ($result as $taskResult) {
                        $totalDeleted += $taskResult['files_deleted'] ?? 0;
                        $totalSpaceFreed += $taskResult['space_freed'] ?? 0;
                    }
                    $message = "Maintenance complète terminée. Total supprimé : {$totalDeleted} fichiers, Espace libéré : " . FileHelper::formatFileSize($totalSpaceFreed);
                    $messageType = 'success';
                    break;
                    
                case 'auto_cleanup':
                    $result = $maintenanceManager->runAutomaticCleanup();
                    $message = "Nettoyage automatique exécuté avec succès";
                    $messageType = 'success';
                    break;
                    
                case 'update_settings':
                    $settings = ['auto_cleanup', 'log_retention_days', 'backup_retention_days', 'temp_cleanup_hours', 'archive_before_delete', 'cleanup_schedule'];
                    foreach ($settings as $setting) {
                        if (isset($_POST[$setting])) {
                            $value = $_POST[$setting];
                            if (in_array($setting, ['auto_cleanup', 'archive_before_delete'])) {
                                $value = isset($_POST[$setting]) ? 'true' : 'false';
                            }
                            $maintenanceManager->setConfig($setting, $value);
                        }
                    }
                    $message = "Paramètres mis à jour avec succès";
                    $messageType = 'success';
                    break;
            }
            
            // Rafraîchir les données après action
            $maintenanceHistory = $maintenanceManager->getMaintenanceHistory(20);
            $statistics = $maintenanceManager->getStatistics();
            
        } catch (Exception $e) {
            $message = "Erreur : " . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = "Token CSRF invalide";
        $messageType = 'danger';
    }
}

$csrfToken = Security::generateCSRFToken();
?>

<div class="modules-section" id="maintenancemanager-section">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🔧 Gestionnaire de Maintenance</h2>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistiques -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3><?php echo FileHelper::formatFileSize($statistics['total_space_freed'] ?? 0); ?></h3>
                            <p>Espace total libéré</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3>
                                <?php 
                                if ($statistics['last_maintenance']) {
                                    echo date('d/m', strtotime($statistics['last_maintenance']['started_at']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </h3>
                            <p>Dernière maintenance</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3><?php echo date('d/m', $statistics['next_cleanup'] ?? time()); ?></h3>
                            <p>Prochaine maintenance</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h3><?php echo ($statistics['by_task']['cleanup_logs']['count'] ?? 0) + ($statistics['by_task']['cleanup_backups']['count'] ?? 0); ?></h3>
                            <p>Nettoyages effectués</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Actions de Maintenance</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card border-primary">
                                <div class="card-header">
                                    <h6>Nettoyage Individuel</h6>
                                </div>
                                <div class="card-body">
                                    <form method="post" style="margin-bottom: 10px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="action" value="cleanup_logs">
                                        <button type="submit" class="btn btn-sm btn-outline-primary btn-block">
                                            Nettoyer les logs
                                        </button>
                                    </form>
                                    
                                    <form method="post" style="margin-bottom: 10px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="action" value="cleanup_backups">
                                        <button type="submit" class="btn btn-sm btn-outline-primary btn-block">
                                            Nettoyer les sauvegardes
                                        </button>
                                    </form>
                                    
                                    <form method="post" style="margin-bottom: 10px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="action" value="cleanup_temp">
                                        <button type="submit" class="btn btn-sm btn-outline-primary btn-block">
                                            Nettoyer les fichiers temp
                                        </button>
                                    </form>
                                    
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="action" value="optimize_database">
                                        <button type="submit" class="btn btn-sm btn-outline-primary btn-block">
                                            Optimiser la BDD
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-success">
                                <div class="card-header">
                                    <h6>Maintenance Complète</h6>
                                </div>
                                <div class="card-body">
                                    <form method="post" style="margin-bottom: 15px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="action" value="full_maintenance">
                                        <button type="submit" class="btn btn-success btn-block" 
                                                onclick="return confirm('Exécuter une maintenance complète ? Cela peut prendre plusieurs minutes.')">
                                            Maintenance complète
                                        </button>
                                    </form>
                                    
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="action" value="auto_cleanup">
                                        <button type="submit" class="btn btn-outline-success btn-block">
                                            Nettoyage automatique
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-info">
                                <div class="card-header">
                                    <h6>Informations Système</h6>
                                </div>
                                <div class="card-body">
                                    <p><small>
                                        <strong>Logs :</strong> <?php echo $maintenanceManager->getConfig('log_retention_days', 7); ?> jours<br>
                                        <strong>Sauvegardes :</strong> <?php echo $maintenanceManager->getConfig('backup_retention_days', 30); ?> jours<br>
                                        <strong>Fichiers temp :</strong> <?php echo $maintenanceManager->getConfig('temp_cleanup_hours', 24); ?>h<br>
                                        <strong>Archivage :</strong> <?php echo $maintenanceManager->getConfig('archive_before_delete', true) ? 'Activé' : 'Désactivé'; ?>
                                    </small></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Configuration -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Configuration</h4>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="auto_cleanup" <?php echo $maintenanceManager->getConfig('auto_cleanup', true) ? 'checked' : ''; ?>>
                                        Nettoyage automatique activé
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="archive_before_delete" <?php echo $maintenanceManager->getConfig('archive_before_delete', true) ? 'checked' : ''; ?>>
                                        Archiver avant suppression
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cleanup_schedule">Fréquence du nettoyage automatique</label>
                                    <select name="cleanup_schedule" id="cleanup_schedule" class="form-control">
                                        <option value="daily" <?php echo $maintenanceManager->getConfig('cleanup_schedule', 'daily') === 'daily' ? 'selected' : ''; ?>>Quotidien</option>
                                        <option value="weekly" <?php echo $maintenanceManager->getConfig('cleanup_schedule', 'daily') === 'weekly' ? 'selected' : ''; ?>>Hebdomadaire</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="log_retention_days">Rétention des logs (jours)</label>
                                    <input type="number" name="log_retention_days" id="log_retention_days" class="form-control" 
                                           value="<?php echo $maintenanceManager->getConfig('log_retention_days', 7); ?>" min="1" max="365">
                                </div>
                                
                                <div class="form-group">
                                    <label for="backup_retention_days">Rétention des sauvegardes (jours)</label>
                                    <input type="number" name="backup_retention_days" id="backup_retention_days" class="form-control" 
                                           value="<?php echo $maintenanceManager->getConfig('backup_retention_days', 30); ?>" min="1" max="365">
                                </div>
                                
                                <div class="form-group">
                                    <label for="temp_cleanup_hours">Nettoyage fichiers temp (heures)</label>
                                    <input type="number" name="temp_cleanup_hours" id="temp_cleanup_hours" class="form-control" 
                                           value="<?php echo $maintenanceManager->getConfig('temp_cleanup_hours', 24); ?>" min="1" max="168">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">Sauvegarder la configuration</button>
                    </form>
                </div>
            </div>
            
            <!-- Historique des maintenances -->
            <div class="card">
                <div class="card-header">
                    <h4>Historique des Maintenances</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($maintenanceHistory)): ?>
                        <p class="text-muted">Aucune maintenance dans l'historique.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Tâche</th>
                                        <th>Statut</th>
                                        <th>Fichiers traités</th>
                                        <th>Fichiers supprimés</th>
                                        <th>Espace libéré</th>
                                        <th>Durée</th>
                                        <th>Lancée par</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maintenanceHistory as $maintenance): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($maintenance['started_at'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo match($maintenance['task_type']) {
                                                        'cleanup_logs' => 'primary',
                                                        'cleanup_backups' => 'success',
                                                        'cleanup_temp' => 'info',
                                                        'optimize_db' => 'warning',
                                                        'archive' => 'secondary',
                                                        default => 'light'
                                                    };
                                                ?>">
                                                    <?php echo str_replace('_', ' ', ucfirst($maintenance['task_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo match($maintenance['status']) {
                                                        'completed' => 'success',
                                                        'failed' => 'danger',
                                                        'running' => 'warning',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($maintenance['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($maintenance['files_processed']); ?></td>
                                            <td><?php echo number_format($maintenance['files_deleted']); ?></td>
                                            <td><?php echo FileHelper::formatFileSize($maintenance['space_freed']); ?></td>
                                            <td>
                                                <?php 
                                                if ($maintenance['duration_seconds'] > 0) {
                                                    if ($maintenance['duration_seconds'] < 60) {
                                                        echo $maintenance['duration_seconds'] . 's';
                                                    } else {
                                                        echo round($maintenance['duration_seconds'] / 60, 1) . 'min';
                                                    }
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($maintenance['created_by']); ?></td>
                                        </tr>
                                        <?php if ($maintenance['details']): ?>
                                            <tr class="table-light">
                                                <td colspan="8">
                                                    <small class="text-muted">
                                                        <strong>Détails :</strong> <?php echo htmlspecialchars($maintenance['details']); ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>