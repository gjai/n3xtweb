<?php
/**
 * N3XT WEB - BackupManager View
 * Interface d'administration pour le gestionnaire de sauvegardes
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

$backupManager = ModulesLoader::getModule('BackupManager');
$backups = $backupManager->getBackups(20);
$statistics = $backupManager->getStatistics();

// Traitement des actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (Security::validateCSRFToken($_POST['csrf_token'])) {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'create_backup':
                    $notes = Security::sanitizeInput($_POST['notes'] ?? '');
                    $result = $backupManager->createBackup('manual', $notes);
                    $message = "Sauvegarde créée avec succès : {$result['filename']}";
                    $messageType = 'success';
                    break;
                    
                case 'restore_backup':
                    $backupId = (int) ($_POST['backup_id'] ?? 0);
                    if ($backupId) {
                        $result = $backupManager->restoreBackup($backupId);
                        $message = "Sauvegarde restaurée avec succès. Fichiers restaurés : {$result['files_restored']}";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'delete_backup':
                    $backupId = (int) ($_POST['backup_id'] ?? 0);
                    if ($backupId && $backupManager->deleteBackup($backupId)) {
                        $message = "Sauvegarde supprimée avec succès";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'cleanup_backups':
                    $deleted = $backupManager->cleanupOldBackups();
                    $message = "Nettoyage terminé. {$deleted} anciennes sauvegardes supprimées";
                    $messageType = 'success';
                    break;
                    
                case 'update_settings':
                    $settings = ['auto_backup', 'retention_days', 'compression', 'include_files', 'include_uploads'];
                    foreach ($settings as $setting) {
                        if (isset($_POST[$setting])) {
                            $value = $_POST[$setting];
                            if (in_array($setting, ['auto_backup', 'compression', 'include_files', 'include_uploads'])) {
                                $value = isset($_POST[$setting]) ? 'true' : 'false';
                            }
                            $backupManager->setConfig($setting, $value);
                        }
                    }
                    $message = "Paramètres mis à jour avec succès";
                    $messageType = 'success';
                    break;
            }
            
            // Rafraîchir les données après action
            $backups = $backupManager->getBackups(20);
            $statistics = $backupManager->getStatistics();
            
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

<div class="modules-section" id="backupmanager-section">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">💾 Gestionnaire de Sauvegardes</h2>
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
                            <h3><?php echo $statistics['total_backups'] ?? 0; ?></h3>
                            <p>Sauvegardes totales</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3><?php echo FileHelper::formatFileSize($statistics['total_size'] ?? 0); ?></h3>
                            <p>Espace utilisé</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $statistics['by_type']['automatic'] ?? 0; ?></h3>
                            <p>Automatiques</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h3>
                                <?php 
                                if ($statistics['last_backup']) {
                                    echo date('d/m', strtotime($statistics['last_backup']['created_at']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </h3>
                            <p>Dernière sauvegarde</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Actions Rapides</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="create_backup">
                                
                                <div class="form-group">
                                    <label for="notes">Notes (optionnel)</label>
                                    <textarea name="notes" id="notes" class="form-control" rows="3" 
                                              placeholder="Description ou raison de cette sauvegarde..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-block">
                                    Créer une sauvegarde manuelle
                                </button>
                            </form>
                        </div>
                        
                        <div class="col-md-6">
                            <form method="post" style="margin-bottom: 10px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="cleanup_backups">
                                <button type="submit" class="btn btn-warning btn-block" 
                                        onclick="return confirm('Supprimer les anciennes sauvegardes selon la politique de rétention ?')">
                                    Nettoyer les anciennes sauvegardes
                                </button>
                            </form>
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
                                        <input type="checkbox" name="auto_backup" <?php echo $backupManager->getConfig('auto_backup', true) ? 'checked' : ''; ?>>
                                        Sauvegardes automatiques activées
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="compression" <?php echo $backupManager->getConfig('compression', true) ? 'checked' : ''; ?>>
                                        Compression des sauvegardes (ZIP)
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label for="retention_days">Rétention (jours)</label>
                                    <select name="retention_days" id="retention_days" class="form-control">
                                        <option value="7" <?php echo $backupManager->getConfig('retention_days', 30) == 7 ? 'selected' : ''; ?>>7 jours</option>
                                        <option value="14" <?php echo $backupManager->getConfig('retention_days', 30) == 14 ? 'selected' : ''; ?>>14 jours</option>
                                        <option value="30" <?php echo $backupManager->getConfig('retention_days', 30) == 30 ? 'selected' : ''; ?>>30 jours</option>
                                        <option value="60" <?php echo $backupManager->getConfig('retention_days', 30) == 60 ? 'selected' : ''; ?>>60 jours</option>
                                        <option value="90" <?php echo $backupManager->getConfig('retention_days', 30) == 90 ? 'selected' : ''; ?>>90 jours</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="include_files" <?php echo $backupManager->getConfig('include_files', true) ? 'checked' : ''; ?>>
                                        Inclure les fichiers système
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="include_uploads" <?php echo $backupManager->getConfig('include_uploads', false) ? 'checked' : ''; ?>>
                                        Inclure les fichiers uploadés
                                    </label>
                                    <small class="form-text text-muted">Attention : peut créer de très gros fichiers</small>
                                </div>
                                
                                <div class="form-group">
                                    <label>Taille max par sauvegarde</label>
                                    <p class="form-control-static">
                                        <?php echo FileHelper::formatFileSize($backupManager->getConfig('max_backup_size', 1073741824)); ?>
                                    </p>
                                    <small class="form-text text-muted">Configuration avancée dans la base de données</small>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">Sauvegarder la configuration</button>
                    </form>
                </div>
            </div>
            
            <!-- Liste des sauvegardes -->
            <div class="card">
                <div class="card-header">
                    <h4>Sauvegardes Disponibles</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($backups)): ?>
                        <p class="text-muted">Aucune sauvegarde disponible.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Nom du fichier</th>
                                        <th>Type</th>
                                        <th>Taille</th>
                                        <th>Statut</th>
                                        <th>Créée par</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($backup['created_at'])); ?></td>
                                            <td>
                                                <span title="<?php echo htmlspecialchars($backup['filepath']); ?>">
                                                    <?php echo htmlspecialchars($backup['filename']); ?>
                                                </span>
                                                <?php if ($backup['compressed']): ?>
                                                    <span class="badge badge-info">ZIP</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo match($backup['type']) {
                                                        'manual' => 'primary',
                                                        'automatic' => 'success',
                                                        'pre_update' => 'warning',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($backup['type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo FileHelper::formatFileSize($backup['size_bytes']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo match($backup['status']) {
                                                        'completed' => 'success',
                                                        'failed' => 'danger',
                                                        'creating' => 'warning',
                                                        'deleted' => 'secondary',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($backup['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($backup['created_by']); ?></td>
                                            <td>
                                                <?php if ($backup['status'] === 'completed'): ?>
                                                    <div class="btn-group btn-group-sm">
                                                        <form method="post" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                            <input type="hidden" name="action" value="restore_backup">
                                                            <input type="hidden" name="backup_id" value="<?php echo $backup['id']; ?>">
                                                            <button type="submit" class="btn btn-warning btn-sm" 
                                                                    onclick="return confirm('Restaurer cette sauvegarde ? Cette action est irréversible !')">
                                                                Restaurer
                                                            </button>
                                                        </form>
                                                        
                                                        <form method="post" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                            <input type="hidden" name="action" value="delete_backup">
                                                            <input type="hidden" name="backup_id" value="<?php echo $backup['id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm" 
                                                                    onclick="return confirm('Supprimer cette sauvegarde ?')">
                                                                Supprimer
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if ($backup['notes']): ?>
                                            <tr class="table-light">
                                                <td colspan="7">
                                                    <small class="text-muted">
                                                        <strong>Notes :</strong> <?php echo htmlspecialchars($backup['notes']); ?>
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