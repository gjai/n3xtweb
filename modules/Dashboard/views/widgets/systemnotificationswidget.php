<?php
/**
 * N3XT WEB - System Notifications Widget View
 * Vue pour le widget de notifications système
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}
?>

<div class="widget system-notifications-widget">
    <div class="widget-header">
        <h3><i class="fas fa-bell"></i> <?= htmlspecialchars($this->getConfig('title', 'Notifications système')) ?></h3>
        <div class="widget-actions">
            <?php if ($summary['unread'] > 0): ?>
            <span class="unread-badge"><?= $summary['unread'] ?></span>
            <?php endif; ?>
            <span class="widget-refresh" title="Dernière mise à jour: <?= $last_updated ?>">
                <i class="fas fa-sync-alt"></i>
            </span>
        </div>
    </div>
    
    <div class="widget-content">
        <!-- Statut système global -->
        <div class="system-status">
            <div class="status-indicator status-<?= $system_status['overall'] ?>">
                <i class="fas fa-<?= $system_status['overall'] === 'good' ? 'check-circle' : ($system_status['overall'] === 'warning' ? 'exclamation-triangle' : 'times-circle') ?>"></i>
                <span>Système <?= $system_status['overall'] === 'good' ? 'Opérationnel' : ($system_status['overall'] === 'warning' ? 'Attention' : 'Critique') ?></span>
            </div>
            <small>Dernière vérification: <?= $system_status['last_check'] ?></small>
        </div>
        
        <!-- Services système -->
        <div class="services-status">
            <h5>Services</h5>
            <div class="services-grid">
                <?php foreach ($system_status['services'] as $serviceName => $service): ?>
                <div class="service-item">
                    <div class="service-status status-<?= $service['status'] ?>">
                        <i class="fas fa-<?= $service['status'] === 'good' ? 'check' : ($service['status'] === 'warning' ? 'exclamation' : 'times') ?>"></i>
                    </div>
                    <div class="service-details">
                        <span class="service-name"><?= ucfirst(str_replace('_', ' ', $serviceName)) ?></span>
                        <small><?= htmlspecialchars($service['message']) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Résumé des notifications -->
        <div class="notifications-summary">
            <h5>Résumé</h5>
            <div class="summary-stats">
                <div class="stat-item">
                    <span class="stat-number"><?= $summary['total'] ?></span>
                    <span class="stat-label">Total</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number text-danger"><?= $summary['by_priority']['critical'] + $summary['by_priority']['high'] ?></span>
                    <span class="stat-label">Importantes</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number text-warning"><?= $summary['by_priority']['medium'] ?></span>
                    <span class="stat-label">Moyennes</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $summary['unread'] ?></span>
                    <span class="stat-label">Non lues</span>
                </div>
            </div>
        </div>
        
        <!-- Liste des notifications -->
        <div class="notifications-list">
            <div class="notifications-header">
                <h5>Notifications récentes</h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="markAllAsRead()">
                    <i class="fas fa-check"></i> Tout marquer comme lu
                </button>
            </div>
            
            <?php if (empty($notifications)): ?>
            <div class="no-notifications">
                <i class="fas fa-inbox"></i>
                <p>Aucune notification à afficher</p>
            </div>
            <?php else: ?>
            <div class="notifications-items">
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-item priority-<?= $notification['priority'] ?> <?= empty($notification['read_at']) ? 'unread' : 'read' ?>" 
                     data-id="<?= $notification['id'] ?>">
                     
                    <div class="notification-icon">
                        <i class="<?= $notification['icon'] ?? 'fas fa-info-circle' ?>"></i>
                    </div>
                    
                    <div class="notification-content">
                        <div class="notification-header">
                            <h6><?= htmlspecialchars($notification['title']) ?></h6>
                            <span class="notification-time"><?= $this->timeAgo($notification['created_at']) ?></span>
                        </div>
                        <p><?= htmlspecialchars($notification['message']) ?></p>
                        
                        <?php if (!empty($notification['action_url'])): ?>
                        <div class="notification-actions">
                            <a href="<?= htmlspecialchars($notification['action_url']) ?>" class="btn btn-sm btn-primary">
                                Voir détails
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notification-controls">
                        <button class="btn-dismiss" onclick="dismissNotification(<?= $notification['id'] ?>)" title="Marquer comme lu">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($notifications) >= $this->getConfig('max_notifications', 10)): ?>
            <div class="notifications-footer">
                <a href="/admin/notifications" class="btn btn-sm btn-outline-primary">
                    Voir toutes les notifications
                </a>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Activités récentes -->
        <?php if (!empty($recent_activities)): ?>
        <div class="recent-activities">
            <h5>Activités récentes</h5>
            <div class="activities-list">
                <?php foreach ($recent_activities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="<?= $activity['icon'] ?>"></i>
                    </div>
                    <div class="activity-details">
                        <span><?= htmlspecialchars($activity['message']) ?></span>
                        <small><?= $this->timeAgo($activity['time']) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.system-notifications-widget {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.system-notifications-widget .widget-header {
    background: #17a2b8;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.system-notifications-widget .widget-header h3 {
    margin: 0;
    font-size: 1.1em;
}

.widget-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.unread-badge {
    background: #dc3545;
    color: white;
    border-radius: 50%;
    padding: 4px 8px;
    font-size: 0.8em;
    font-weight: bold;
    min-width: 20px;
    text-align: center;
}

.system-notifications-widget .widget-content {
    padding: 20px;
    max-height: 600px;
    overflow-y: auto;
}

.system-status {
    margin-bottom: 20px;
    text-align: center;
}

.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    border-radius: 6px;
    font-weight: bold;
    margin-bottom: 5px;
}

.status-good {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-critical {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.service-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border: 1px solid #e9ecef;
    border-radius: 4px;
}

.service-status {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8em;
}

.service-details {
    flex: 1;
}

.service-name {
    display: block;
    font-weight: bold;
    font-size: 0.9em;
}

.service-details small {
    color: #6c757d;
    font-size: 0.8em;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-top: 10px;
}

.stat-item {
    text-align: center;
    padding: 10px;
    border: 1px solid #e9ecef;
    border-radius: 4px;
}

.stat-number {
    display: block;
    font-size: 1.5em;
    font-weight: bold;
    color: #333;
}

.stat-label {
    font-size: 0.8em;
    color: #6c757d;
}

.notifications-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.notifications-header h5 {
    margin: 0;
}

.notification-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    margin-bottom: 10px;
    position: relative;
    transition: all 0.2s;
}

.notification-item.unread {
    border-left: 4px solid #17a2b8;
    background: #f8f9fa;
}

.notification-item.priority-critical {
    border-left-color: #dc3545;
}

.notification-item.priority-high {
    border-left-color: #fd7e14;
}

.notification-item.priority-medium {
    border-left-color: #ffc107;
}

.notification-item.priority-low {
    border-left-color: #28a745;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.priority-critical .notification-icon {
    background: #f8d7da;
    color: #721c24;
}

.priority-high .notification-icon {
    background: #ffeaa7;
    color: #856404;
}

.priority-medium .notification-icon {
    background: #fff3cd;
    color: #856404;
}

.priority-low .notification-icon {
    background: #d4edda;
    color: #155724;
}

.notification-content {
    flex: 1;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 5px;
}

.notification-header h6 {
    margin: 0;
    font-size: 0.95em;
    color: #333;
}

.notification-time {
    font-size: 0.8em;
    color: #6c757d;
    white-space: nowrap;
}

.notification-content p {
    margin: 0 0 10px 0;
    font-size: 0.9em;
    color: #666;
    line-height: 1.4;
}

.notification-controls {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.btn-dismiss {
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 5px;
    border-radius: 3px;
    font-size: 0.9em;
}

.btn-dismiss:hover {
    background: #e9ecef;
    color: #333;
}

.no-notifications {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.no-notifications i {
    font-size: 3em;
    margin-bottom: 15px;
    color: #dee2e6;
}

.activities-list {
    margin-top: 10px;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.activity-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8em;
    color: #6c757d;
}

.activity-details {
    flex: 1;
}

.activity-details span {
    display: block;
    font-size: 0.9em;
    color: #333;
}

.activity-details small {
    color: #6c757d;
    font-size: 0.8em;
}

.notifications-footer {
    text-align: center;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.btn {
    padding: 5px 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 0.9em;
}

.btn-sm {
    padding: 3px 8px;
    font-size: 0.8em;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-outline-primary {
    background: transparent;
    color: #007bff;
    border: 1px solid #007bff;
}

.btn-outline-secondary {
    background: transparent;
    color: #6c757d;
    border: 1px solid #6c757d;
}

.text-danger {
    color: #dc3545 !important;
}

.text-warning {
    color: #ffc107 !important;
}

.widget-refresh {
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.widget-refresh:hover {
    opacity: 1;
}

/* Responsive */
@media (max-width: 768px) {
    .summary-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .services-grid {
        grid-template-columns: 1fr;
    }
    
    .notification-item {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<script>
function dismissNotification(notificationId) {
    if (confirm('Marquer cette notification comme lue ?')) {
        // Implementation pour marquer comme lue
        console.log('Dismissing notification:', notificationId);
        
        // Masquer visuellement la notification
        const notification = document.querySelector(`[data-id="${notificationId}"]`);
        if (notification) {
            notification.style.opacity = '0.5';
            notification.classList.remove('unread');
            notification.classList.add('read');
        }
        
        // Ici on ferait un appel AJAX pour marquer comme lue
    }
}

function markAllAsRead() {
    if (confirm('Marquer toutes les notifications comme lues ?')) {
        // Implementation pour marquer toutes comme lues
        console.log('Marking all notifications as read');
        
        // Masquer visuellement toutes les notifications non lues
        const unreadNotifications = document.querySelectorAll('.notification-item.unread');
        unreadNotifications.forEach(notification => {
            notification.classList.remove('unread');
            notification.classList.add('read');
        });
        
        // Masquer le badge de notifications non lues
        const unreadBadge = document.querySelector('.unread-badge');
        if (unreadBadge) {
            unreadBadge.style.display = 'none';
        }
        
        // Ici on ferait un appel AJAX pour marquer toutes comme lues
    }
}

// Auto-refresh si activé
<?php if ($this->getConfig('auto_refresh', true)): ?>
setInterval(function() {
    // Ici on pourrait recharger les notifications via AJAX
    console.log('Auto-refreshing notifications...');
}, <?= $this->getConfig('refresh_interval', 60) * 1000 ?>);
<?php endif; ?>
</script>

<?php
// Helper function pour calculer le temps écoulé
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'À l\'instant';
        if ($time < 3600) return floor($time/60) . ' min';
        if ($time < 86400) return floor($time/3600) . ' h';
        if ($time < 2592000) return floor($time/86400) . ' j';
        
        return date('d/m/Y', strtotime($datetime));
    }
}

// Ajouter la méthode timeAgo à la classe widget si elle n'existe pas
if (!method_exists($this, 'timeAgo')) {
    $this->timeAgo = function($datetime) {
        return timeAgo($datetime);
    };
}
?>