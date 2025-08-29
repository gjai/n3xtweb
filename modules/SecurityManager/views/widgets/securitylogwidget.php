<?php
/**
 * N3XT WEB - Security Log Widget View
 * 
 * Vue pour l'affichage du widget de journaux de sécurité.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

$data = $widget->getData();
$config = $widget->getConfiguration();
?>

<div class="security-log-widget" id="security-log-widget-<?php echo $widget->getId(); ?>">
    <div class="widget-header d-flex justify-content-between align-items-center">
        <h5 class="widget-title">
            <?php echo Security::sanitizeInput($config['title']); ?>
            <span class="badge badge-<?php echo htmlspecialchars($data['system_status']['status_class']); ?> ml-2">
                <?php echo strtoupper($data['system_status']['global_level']); ?>
            </span>
        </h5>
        
        <div class="widget-controls">
            <?php if ($config['auto_refresh']): ?>
            <button class="btn btn-sm btn-outline-secondary" onclick="refreshSecurityLog()" title="Actualiser">
                <i class="fas fa-sync-alt"></i>
            </button>
            <?php endif; ?>
            
            <button class="btn btn-sm btn-outline-secondary" onclick="toggleSecurityLogDetails()" title="Détails">
                <i class="fas fa-expand-alt"></i>
            </button>
        </div>
    </div>

    <div class="widget-content">
        <?php if (!empty($data['error'])): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo Security::sanitizeInput($data['error']); ?>
            </div>
        <?php else: ?>
            
            <!-- System Status Summary -->
            <div class="security-status-summary mb-3">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card critical">
                            <div class="stat-number"><?php echo (int)$data['system_status']['alert_counts']['critical']; ?></div>
                            <div class="stat-label">Critiques</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card high">
                            <div class="stat-number"><?php echo (int)$data['system_status']['alert_counts']['high']; ?></div>
                            <div class="stat-label">Élevées</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card medium">
                            <div class="stat-number"><?php echo (int)$data['system_status']['alert_counts']['medium']; ?></div>
                            <div class="stat-label">Moyennes</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card low">
                            <div class="stat-number"><?php echo (int)$data['system_status']['alert_counts']['low']; ?></div>
                            <div class="stat-label">Faibles</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs nav-tabs-security" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#events-tab" role="tab">
                        📝 Événements
                        <?php if (!empty($data['recent_events'])): ?>
                            <span class="badge badge-info"><?php echo count($data['recent_events']); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#logins-tab" role="tab">
                        🔑 Connexions
                        <?php if (!empty($data['login_attempts'])): ?>
                            <span class="badge badge-warning"><?php echo count($data['login_attempts']); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#blocked-tab" role="tab">
                        🚫 IPs Bloquées
                        <?php if (!empty($data['blocked_ips'])): ?>
                            <span class="badge badge-danger"><?php echo count($data['blocked_ips']); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#alerts-tab" role="tab">
                        🚨 Alertes
                        <?php if (!empty($data['security_alerts'])): ?>
                            <span class="badge badge-danger"><?php echo count($data['security_alerts']); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content mt-3">
                
                <!-- Recent Events Tab -->
                <div class="tab-pane fade show active" id="events-tab" role="tabpanel">
                    <?php if (!empty($data['recent_events'])): ?>
                        <div class="security-events-list">
                            <?php foreach ($data['recent_events'] as $event): ?>
                                <div class="security-event-item border-left border-<?php echo htmlspecialchars($event['severity_class']); ?>">
                                    <div class="event-header d-flex justify-content-between">
                                        <span class="event-type">
                                            <?php echo htmlspecialchars($event['type_icon']); ?>
                                            <?php echo Security::sanitizeInput($event['event_type']); ?>
                                        </span>
                                        <span class="event-time text-muted">
                                            <?php echo Security::sanitizeInput($event['formatted_time']); ?>
                                        </span>
                                    </div>
                                    <div class="event-message">
                                        <?php echo Security::sanitizeInput($event['message']); ?>
                                    </div>
                                    <?php if (!empty($event['ip_address'])): ?>
                                        <div class="event-ip text-muted">
                                            IP: <?php echo Security::sanitizeInput($event['ip_address']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-shield-alt fa-2x"></i>
                            <p>Aucun événement récent</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Login Attempts Tab -->
                <div class="tab-pane fade" id="logins-tab" role="tabpanel">
                    <?php if (!empty($data['login_attempts'])): ?>
                        <div class="login-attempts-list">
                            <?php foreach ($data['login_attempts'] as $attempt): ?>
                                <div class="login-attempt-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="attempt-info">
                                            <span class="badge badge-<?php echo htmlspecialchars($attempt['status_class']); ?>">
                                                <?php echo Security::sanitizeInput($attempt['status_text']); ?>
                                            </span>
                                            <strong><?php echo Security::sanitizeInput($attempt['username']); ?></strong>
                                            <span class="text-muted">depuis</span>
                                            <code><?php echo Security::sanitizeInput($attempt['ip_address']); ?></code>
                                        </div>
                                        <div class="attempt-time text-muted">
                                            <?php echo Security::sanitizeInput($attempt['formatted_time']); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($attempt['failure_reason'])): ?>
                                        <div class="failure-reason text-danger">
                                            <?php echo Security::sanitizeInput($attempt['failure_reason']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-key fa-2x"></i>
                            <p>Aucune tentative de connexion récente</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Blocked IPs Tab -->
                <div class="tab-pane fade" id="blocked-tab" role="tabpanel">
                    <?php if (!empty($data['blocked_ips'])): ?>
                        <div class="blocked-ips-list">
                            <?php foreach ($data['blocked_ips'] as $blocked): ?>
                                <div class="blocked-ip-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="ip-info">
                                            <code class="ip-address"><?php echo Security::sanitizeInput($blocked['ip_address']); ?></code>
                                            <?php if (!empty($blocked['reason'])): ?>
                                                <span class="text-muted">- <?php echo Security::sanitizeInput($blocked['reason']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ip-actions">
                                            <button class="btn btn-sm btn-outline-success" 
                                                    onclick="unblockIP('<?php echo htmlspecialchars($blocked['ip_address']); ?>')"
                                                    title="Débloquer">
                                                <i class="fas fa-unlock"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="ip-details text-muted">
                                        <small>
                                            Bloqué: <?php echo Security::sanitizeInput($blocked['formatted_created']); ?>
                                            | Expire: <?php echo Security::sanitizeInput($blocked['time_remaining']); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-ban fa-2x"></i>
                            <p>Aucune IP bloquée actuellement</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Security Alerts Tab -->
                <div class="tab-pane fade" id="alerts-tab" role="tabpanel">
                    <?php if (!empty($data['security_alerts'])): ?>
                        <div class="security-alerts-list">
                            <?php foreach ($data['security_alerts'] as $alert): ?>
                                <div class="security-alert-item alert alert-<?php echo htmlspecialchars($alert['level_class']); ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="alert-content">
                                            <h6 class="alert-title">
                                                <?php echo htmlspecialchars($alert['level_icon']); ?>
                                                <?php echo Security::sanitizeInput($alert['title']); ?>
                                            </h6>
                                            <p class="alert-description mb-0">
                                                <?php echo Security::sanitizeInput($alert['description']); ?>
                                            </p>
                                        </div>
                                        <div class="alert-time text-muted">
                                            <?php echo Security::sanitizeInput($alert['formatted_time']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-shield-check fa-2x"></i>
                            <p>Aucune alerte de sécurité</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.security-log-widget {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.widget-header {
    padding: 15px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.widget-content {
    padding: 15px;
}

.security-status-summary .stat-card {
    text-align: center;
    padding: 10px;
    border-radius: 6px;
    background: #f8f9fa;
    border-left: 4px solid #dee2e6;
}

.stat-card.critical { border-left-color: #dc3545; }
.stat-card.high { border-left-color: #fd7e14; }
.stat-card.medium { border-left-color: #ffc107; }
.stat-card.low { border-left-color: #28a745; }

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: #495057;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
}

.nav-tabs-security {
    border-bottom: 2px solid #dee2e6;
}

.nav-tabs-security .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
}

.nav-tabs-security .nav-link.active {
    color: #495057;
    border-bottom: 2px solid #007bff;
    background: none;
}

.security-event-item,
.login-attempt-item,
.blocked-ip-item {
    padding: 10px;
    margin-bottom: 8px;
    background: #f8f9fa;
    border-radius: 4px;
    border-left: 3px solid #dee2e6;
}

.security-event-item:hover,
.login-attempt-item:hover,
.blocked-ip-item:hover {
    background: #e9ecef;
}

.event-header,
.attempt-info,
.ip-info {
    margin-bottom: 5px;
}

.ip-address {
    font-family: 'Courier New', monospace;
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 3px;
}

@media (max-width: 768px) {
    .security-status-summary .col-md-3 {
        margin-bottom: 10px;
    }
    
    .d-flex {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .event-time,
    .attempt-time,
    .alert-time {
        margin-top: 5px;
    }
}
</style>

<script>
function refreshSecurityLog() {
    const widget = document.getElementById('security-log-widget-<?php echo $widget->getId(); ?>');
    widget.style.opacity = '0.7';
    
    // Simuler le rechargement (à remplacer par un appel AJAX réel)
    setTimeout(() => {
        widget.style.opacity = '1';
        // Ici, on ferait un appel AJAX pour recharger les données
        location.reload();
    }, 1000);
}

function toggleSecurityLogDetails() {
    const widget = document.getElementById('security-log-widget-<?php echo $widget->getId(); ?>');
    widget.classList.toggle('expanded');
    
    if (widget.classList.contains('expanded')) {
        widget.style.position = 'fixed';
        widget.style.top = '10px';
        widget.style.left = '10px';
        widget.style.right = '10px';
        widget.style.bottom = '10px';
        widget.style.zIndex = '9999';
        widget.style.overflow = 'auto';
    } else {
        widget.style.position = '';
        widget.style.top = '';
        widget.style.left = '';
        widget.style.right = '';
        widget.style.bottom = '';
        widget.style.zIndex = '';
        widget.style.overflow = '';
    }
}

function unblockIP(ip) {
    if (confirm('Êtes-vous sûr de vouloir débloquer l\'IP ' + ip + ' ?')) {
        // Ici, on ferait un appel AJAX pour débloquer l'IP
        console.log('Unblocking IP:', ip);
        // Exemple d'appel AJAX (à implémenter)
        // fetch('/bo/security_manager.php', {
        //     method: 'POST',
        //     body: new FormData()...
        // });
    }
}

// Auto-refresh si activé
<?php if ($config['auto_refresh'] && $config['refresh_interval'] > 0): ?>
setInterval(refreshSecurityLog, <?php echo (int)$config['refresh_interval'] * 1000; ?>);
<?php endif; ?>
</script>