<?php
/**
 * N3XT WEB - NotificationManager View
 * Interface d'administration pour le gestionnaire de notifications
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

$notificationManager = ModulesLoader::getModule('NotificationManager');
$notifications = $notificationManager->getNotifications(null, null, 20);
$statistics = $notificationManager->getStatistics();
$unreadCount = $notificationManager->getUnreadCount();

// Traitement des actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (Security::validateCSRFToken($_POST['csrf_token'])) {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'mark_read':
                    $notificationId = (int) ($_POST['notification_id'] ?? 0);
                    if ($notificationId && $notificationManager->markAsRead($notificationId)) {
                        $message = "Notification marquée comme lue";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'dismiss':
                    $notificationId = (int) ($_POST['notification_id'] ?? 0);
                    if ($notificationId && $notificationManager->dismiss($notificationId)) {
                        $message = "Notification ignorée";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'cleanup':
                    $deleted = $notificationManager->cleanupOldNotifications();
                    $message = "Nettoyage terminé. Notifications supprimées : " . ($deleted ? "Oui" : "Aucune");
                    $messageType = 'success';
                    break;
                    
                case 'create_test':
                    $notificationManager->createNotification(
                        'system',
                        'Notification de test',
                        'Ceci est une notification de test créée depuis l\'interface d\'administration.',
                        'medium',
                        ['test' => true, 'created_at' => date('Y-m-d H:i:s')]
                    );
                    $message = "Notification de test créée";
                    $messageType = 'success';
                    break;
                    
                case 'update_settings':
                    $settings = ['email_enabled', 'visual_enabled', 'retention_days', 'auto_email_types'];
                    foreach ($settings as $setting) {
                        if (isset($_POST[$setting])) {
                            $value = $_POST[$setting];
                            if ($setting === 'email_enabled' || $setting === 'visual_enabled') {
                                $value = isset($_POST[$setting]) ? 'true' : 'false';
                            }
                            $notificationManager->setConfig($setting, $value);
                        }
                    }
                    $message = "Paramètres mis à jour avec succès";
                    $messageType = 'success';
                    break;
            }
            
            // Rafraîchir les données après action
            $notifications = $notificationManager->getNotifications(null, null, 20);
            $statistics = $notificationManager->getStatistics();
            $unreadCount = $notificationManager->getUnreadCount();
            
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

<div class="modules-section" id="notificationmanager-section">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📢 Gestionnaire de Notifications</h2>
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
                            <h3><?php echo $unreadCount; ?></h3>
                            <p>Non lues</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $statistics['by_status']['read'] ?? 0; ?></h3>
                            <p>Lues</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $statistics['by_priority']['high'] ?? 0; ?></h3>
                            <p>Priorité élevée</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $statistics['recent_count'] ?? 0; ?></h3>
                            <p>Cette semaine</p>
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
                                        <input type="checkbox" name="visual_enabled" <?php echo $notificationManager->getConfig('visual_enabled', true) ? 'checked' : ''; ?>>
                                        Notifications visuelles activées
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="email_enabled" <?php echo $notificationManager->getConfig('email_enabled', true) ? 'checked' : ''; ?>>
                                        Notifications par email activées
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label for="retention_days">Rétention (jours)</label>
                                    <select name="retention_days" id="retention_days" class="form-control">
                                        <option value="7" <?php echo $notificationManager->getConfig('retention_days', 30) == 7 ? 'selected' : ''; ?>>7 jours</option>
                                        <option value="14" <?php echo $notificationManager->getConfig('retention_days', 30) == 14 ? 'selected' : ''; ?>>14 jours</option>
                                        <option value="30" <?php echo $notificationManager->getConfig('retention_days', 30) == 30 ? 'selected' : ''; ?>>30 jours</option>
                                        <option value="60" <?php echo $notificationManager->getConfig('retention_days', 30) == 60 ? 'selected' : ''; ?>>60 jours</option>
                                        <option value="90" <?php echo $notificationManager->getConfig('retention_days', 30) == 90 ? 'selected' : ''; ?>>90 jours</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="auto_email_types">Types envoyés par email</label>
                                    <div class="form-check-group">
                                        <?php 
                                        $autoEmailTypes = explode(',', $notificationManager->getConfig('auto_email_types', 'update,backup,maintenance,system'));
                                        $allTypes = ['update' => 'Mises à jour', 'backup' => 'Sauvegardes', 'maintenance' => 'Maintenance', 'system' => 'Système', 'warning' => 'Avertissements', 'error' => 'Erreurs'];
                                        
                                        foreach ($allTypes as $type => $label): ?>
                                            <label class="form-check-label">
                                                <input type="checkbox" name="email_types[]" value="<?php echo $type; ?>" 
                                                       <?php echo in_array($type, $autoEmailTypes) ? 'checked' : ''; ?>>
                                                <?php echo $label; ?>
                                            </label><br>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">Sauvegarder la configuration</button>
                    </form>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Actions</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <form method="post" style="margin-bottom: 10px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="create_test">
                                <button type="submit" class="btn btn-primary btn-block">
                                    Créer une notification de test
                                </button>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <form method="post" style="margin-bottom: 10px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="action" value="cleanup">
                                <button type="submit" class="btn btn-warning btn-block" 
                                        onclick="return confirm('Supprimer les anciennes notifications ?')">
                                    Nettoyer les anciennes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Liste des notifications -->
            <div class="card">
                <div class="card-header">
                    <h4>Notifications Récentes</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <p class="text-muted">Aucune notification.</p>
                    <?php else: ?>
                        <div class="notifications-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item notification-<?php echo $notification['priority']; ?> notification-<?php echo $notification['status']; ?>" 
                                     data-id="<?php echo $notification['id']; ?>">
                                    <div class="notification-content">
                                        <div class="notification-header">
                                            <span class="notification-type badge badge-<?php 
                                                echo match($notification['type']) {
                                                    'update' => 'primary',
                                                    'backup' => 'success',
                                                    'maintenance' => 'warning',
                                                    'system' => 'info',
                                                    'warning' => 'warning',
                                                    'error' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($notification['type']); ?>
                                            </span>
                                            
                                            <span class="notification-priority badge badge-<?php 
                                                echo match($notification['priority']) {
                                                    'critical' => 'danger',
                                                    'high' => 'warning',
                                                    'medium' => 'info',
                                                    'low' => 'secondary',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($notification['priority']); ?>
                                            </span>
                                            
                                            <span class="notification-time text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="notification-title">
                                            <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                        </div>
                                        
                                        <div class="notification-message">
                                            <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                        </div>
                                        
                                        <?php if ($notification['data']): ?>
                                            <div class="notification-data">
                                                <details>
                                                    <summary>Données supplémentaires</summary>
                                                    <pre class="text-muted small"><?php echo json_encode($notification['data'], JSON_PRETTY_PRINT); ?></pre>
                                                </details>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="notification-actions">
                                        <?php if ($notification['status'] === 'unread'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Marquer comme lue">
                                                    ✓
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="action" value="dismiss">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary" title="Ignorer">
                                                ✕
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.notification-item {
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    background-color: #fff;
}

.notification-item.notification-unread {
    border-left: 4px solid #007bff;
    background-color: #f8f9fa;
}

.notification-item.notification-critical {
    border-left-color: #dc3545;
}

.notification-item.notification-high {
    border-left-color: #fd7e14;
}

.notification-item.notification-medium {
    border-left-color: #ffc107;
}

.notification-content {
    flex: 1;
}

.notification-header {
    margin-bottom: 8px;
}

.notification-header .badge {
    margin-right: 5px;
}

.notification-title {
    margin-bottom: 5px;
}

.notification-message {
    color: #6c757d;
    margin-bottom: 10px;
}

.notification-actions {
    display: flex;
    gap: 5px;
}

.notification-data {
    margin-top: 10px;
}

.notification-data pre {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 10px;
    font-size: 12px;
}

.form-check-group .form-check-label {
    display: block;
    margin-bottom: 5px;
}
</style>