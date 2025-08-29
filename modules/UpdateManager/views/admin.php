<?php
/**
 * N3XT WEB - UpdateManager View
 * Interface d'administration pour le gestionnaire de mises à jour
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

$updateManager = ModulesLoader::getModule('UpdateManager');
$updateStatus = $updateManager->getStatus();
$updateHistory = $updateManager->getUpdateHistory(10);

// Traitement des actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (Security::validateCSRFToken($_POST['csrf_token'])) {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'check_updates':
                    $result = $updateManager->checkForUpdates();
                    if ($result['update_available']) {
                        $message = "Mise à jour disponible : version {$result['latest_version']}";
                        $messageType = 'info';
                    } else {
                        $message = "Aucune mise à jour disponible. Version actuelle : {$result['current_version']}";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'download_update':
                    $downloadUrl = $_POST['download_url'] ?? '';
                    $version = $_POST['version'] ?? '';
                    
                    if ($downloadUrl && $version) {
                        $result = $updateManager->downloadUpdate($downloadUrl, $version);
                        $message = "Mise à jour téléchargée avec succès : {$result['filename']}";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'apply_update':
                    $updateId = (int) ($_POST['update_id'] ?? 0);
                    
                    if ($updateId) {
                        $result = $updateManager->applyUpdate($updateId);
                        $message = "Mise à jour appliquée avec succès vers la version {$result['version']}";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'update_settings':
                    $settings = ['auto_check', 'check_frequency', 'auto_backup', 'github_repo'];
                    foreach ($settings as $setting) {
                        if (isset($_POST[$setting])) {
                            $value = $_POST[$setting];
                            if ($setting === 'auto_check' || $setting === 'auto_backup') {
                                $value = isset($_POST[$setting]) ? 'true' : 'false';
                            }
                            $updateManager->setConfig($setting, $value);
                        }
                    }
                    $message = "Paramètres mis à jour avec succès";
                    $messageType = 'success';
                    break;
            }
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

<div class="modules-section" id="updatemanager-section">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🔄 Gestionnaire de Mises à Jour</h2>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statut actuel -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Statut Actuel</h4>
                        </div>
                        <div class="card-body">
                            <p><strong>Version actuelle :</strong> <?php echo htmlspecialchars($updateStatus['current_version']); ?></p>
                            <p><strong>Dernière vérification :</strong> <?php echo $updateStatus['last_check_formatted']; ?></p>
                            <p><strong>Vérification automatique :</strong> 
                                <span class="badge badge-<?php echo $updateStatus['auto_check'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $updateStatus['auto_check'] ? 'Activée' : 'Désactivée'; ?>
                                </span>
                            </p>
                            <p><strong>Dépôt GitHub :</strong> <?php echo htmlspecialchars($updateStatus['github_repo']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Actions Rapides</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" style="margin-bottom: 10px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="check_updates">
                                <button type="submit" class="btn btn-primary btn-block">
                                    Vérifier les mises à jour
                                </button>
                            </form>
                            
                            <?php if ($updateStatus['ongoing_update']): ?>
                                <div class="alert alert-info">
                                    <strong>Mise à jour en cours :</strong><br>
                                    Statut : <?php echo $updateStatus['ongoing_update']['status']; ?><br>
                                    Progression : <?php echo $updateStatus['ongoing_update']['progress_percent']; ?>%
                                </div>
                            <?php endif; ?>
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
                                        <input type="checkbox" name="auto_check" <?php echo $updateManager->getConfig('auto_check', true) ? 'checked' : ''; ?>>
                                        Vérification automatique
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label for="check_frequency">Fréquence de vérification (heures)</label>
                                    <select name="check_frequency" id="check_frequency" class="form-control">
                                        <option value="3600" <?php echo $updateManager->getConfig('check_frequency', 86400) == 3600 ? 'selected' : ''; ?>>1 heure</option>
                                        <option value="21600" <?php echo $updateManager->getConfig('check_frequency', 86400) == 21600 ? 'selected' : ''; ?>>6 heures</option>
                                        <option value="43200" <?php echo $updateManager->getConfig('check_frequency', 86400) == 43200 ? 'selected' : ''; ?>>12 heures</option>
                                        <option value="86400" <?php echo $updateManager->getConfig('check_frequency', 86400) == 86400 ? 'selected' : ''; ?>>24 heures</option>
                                        <option value="604800" <?php echo $updateManager->getConfig('check_frequency', 86400) == 604800 ? 'selected' : ''; ?>>7 jours</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="auto_backup" <?php echo $updateManager->getConfig('auto_backup', true) ? 'checked' : ''; ?>>
                                        Sauvegarde automatique avant mise à jour
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label for="github_repo">Dépôt GitHub</label>
                                    <input type="text" name="github_repo" id="github_repo" class="form-control" 
                                           value="<?php echo htmlspecialchars($updateManager->getConfig('github_repo', 'gjai/n3xtweb')); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">Sauvegarder la configuration</button>
                    </form>
                </div>
            </div>
            
            <!-- Historique des mises à jour -->
            <div class="card">
                <div class="card-header">
                    <h4>Historique des Mises à Jour</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($updateHistory)): ?>
                        <p class="text-muted">Aucune mise à jour dans l'historique.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Version</th>
                                        <th>Type</th>
                                        <th>Statut</th>
                                        <th>Progression</th>
                                        <th>Initiée par</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($updateHistory as $update): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($update['started_at'])); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($update['version_from']); ?> 
                                                → 
                                                <?php echo htmlspecialchars($update['version_to']); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo ucfirst($update['update_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo match($update['status']) {
                                                        'completed' => 'success',
                                                        'failed' => 'danger',
                                                        'applying', 'downloading' => 'warning',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($update['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $update['progress_percent']; ?>%">
                                                        <?php echo $update['progress_percent']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($update['started_by']); ?></td>
                                        </tr>
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