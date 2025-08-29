<?php
/**
 * N3XT WEB - Security Alerts Widget View
 * Vue pour le widget d'alertes de sécurité
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}
?>

<div class="widget security-alerts-widget">
    <div class="widget-header">
        <h3><i class="fas fa-shield-alt"></i> <?= htmlspecialchars($this->getConfig('title', 'Alertes de sécurité')) ?></h3>
        <div class="widget-actions">
            <span class="security-score" title="Score de sécurité global">
                <i class="fas fa-star"></i>
                <?= round($security_status['overall_score']) ?>%
            </span>
            <span class="widget-refresh" title="Dernière mise à jour: <?= $last_updated ?>">
                <i class="fas fa-sync-alt"></i>
            </span>
        </div>
    </div>
    
    <div class="widget-content">
        <!-- Statut de sécurité global -->
        <div class="security-overview">
            <div class="threat-level threat-<?= $security_status['threat_level'] ?>">
                <div class="threat-indicator">
                    <i class="fas fa-<?= $security_status['threat_level'] === 'critical' ? 'exclamation-triangle' : ($security_status['threat_level'] === 'high' ? 'exclamation' : ($security_status['threat_level'] === 'medium' ? 'minus' : 'check')) ?>"></i>
                </div>
                <div class="threat-info">
                    <h4>Niveau de menace: <?= ucfirst($security_status['threat_level']) ?></h4>
                    <p>Score de sécurité: <?= round($security_status['overall_score']) ?>%</p>
                </div>
            </div>
            
            <div class="security-stats">
                <div class="stat-item">
                    <span class="stat-number text-danger"><?= $security_status['active_threats'] ?></span>
                    <span class="stat-label">Menaces actives</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number text-success"><?= $security_status['blocked_threats'] ?></span>
                    <span class="stat-label">Menaces bloquées</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $summary['total'] ?></span>
                    <span class="stat-label">Total alertes</span>
                </div>
            </div>
        </div>
        
        <!-- Vérifications de sécurité -->
        <div class="security-checks">
            <h5>Vérifications de sécurité</h5>
            <div class="checks-grid">
                <?php foreach ($security_status['security_checks'] as $checkName => $check): ?>
                <div class="check-item status-<?= $check['status'] ?>">
                    <div class="check-icon">
                        <i class="fas fa-<?= $check['status'] === 'active' || $check['status'] === 'good' ? 'check' : ($check['status'] === 'warning' ? 'exclamation' : 'times') ?>"></i>
                    </div>
                    <div class="check-details">
                        <span class="check-name"><?= ucfirst(str_replace('_', ' ', $checkName)) ?></span>
                        <small><?= htmlspecialchars($check['message']) ?></small>
                        <div class="check-score">Score: <?= $check['score'] ?>%</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Indicateurs de menace -->
        <div class="threat-indicators">
            <h5>Indicateurs de menace</h5>
            <div class="indicators-grid">
                <?php foreach ($threat_indicators as $indicator => $data): ?>
                <div class="indicator-item">
                    <div class="indicator-number 
                        <?= $data['count'] > 10 ? 'text-danger' : ($data['count'] > 5 ? 'text-warning' : 'text-success') ?>">
                        <?= $data['count'] ?>
                    </div>
                    <div class="indicator-description">
                        <?= htmlspecialchars($data['description']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Alertes récentes -->
        <div class="security-alerts">
            <div class="alerts-header">
                <h5>Alertes récentes</h5>
                <div class="alert-filters">
                    <button class="filter-btn active" data-severity="all">Toutes</button>
                    <button class="filter-btn" data-severity="critical">Critiques</button>
                    <button class="filter-btn" data-severity="high">Élevées</button>
                    <button class="filter-btn" data-severity="medium">Moyennes</button>
                </div>
            </div>
            
            <?php if (empty($alerts)): ?>
            <div class="no-alerts">
                <i class="fas fa-shield-alt"></i>
                <p>Aucune alerte de sécurité active</p>
                <small>Votre système est sécurisé</small>
            </div>
            <?php else: ?>
            <div class="alerts-list">
                <?php foreach ($alerts as $alert): ?>
                <div class="alert-item severity-<?= $alert['severity'] ?> status-<?= $alert['status'] ?>" 
                     data-severity="<?= $alert['severity'] ?>" data-id="<?= $alert['id'] ?>">
                     
                    <div class="alert-indicator" style="background-color: <?= $this->getSeverityColor($alert['severity']) ?>">
                        <i class="<?= $this->getAlertIcon($alert['type']) ?>"></i>
                    </div>
                    
                    <div class="alert-content">
                        <div class="alert-header">
                            <h6><?= htmlspecialchars($alert['title']) ?></h6>
                            <div class="alert-meta">
                                <span class="alert-time"><?= $this->timeAgo($alert['created_at']) ?></span>
                                <span class="alert-risk">Risque: <?= $alert['risk_score'] ?>%</span>
                            </div>
                        </div>
                        
                        <p class="alert-description"><?= htmlspecialchars($alert['description']) ?></p>
                        
                        <?php if (!empty($alert['details'])): ?>
                        <div class="alert-details">
                            <small><?= htmlspecialchars($alert['details']) ?></small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="alert-tags">
                            <span class="alert-severity" style="background-color: <?= $this->getSeverityColor($alert['severity']) ?>">
                                <?= ucfirst($alert['severity']) ?>
                            </span>
                            <span class="alert-status status-<?= $alert['status'] ?>">
                                <?= ucfirst($alert['status']) ?>
                            </span>
                            <?php if ($alert['source_ip']): ?>
                            <span class="alert-ip">
                                <i class="fas fa-globe"></i>
                                <?= htmlspecialchars($alert['source_ip']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="alert-actions">
                        <?php if ($alert['status'] === 'active'): ?>
                        <button class="btn-action btn-investigate" onclick="investigateAlert(<?= $alert['id'] ?>)" title="Enquêter">
                            <i class="fas fa-search"></i>
                        </button>
                        <button class="btn-action btn-block" onclick="blockThreat(<?= $alert['id'] ?>)" title="Bloquer">
                            <i class="fas fa-ban"></i>
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($alert['status'] !== 'resolved'): ?>
                        <button class="btn-action btn-resolve" onclick="resolveAlert(<?= $alert['id'] ?>)" title="Résoudre">
                            <i class="fas fa-check"></i>
                        </button>
                        <?php endif; ?>
                        
                        <button class="btn-action btn-details" onclick="showAlertDetails(<?= $alert['id'] ?>)" title="Détails">
                            <i class="fas fa-info"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Scans récents -->
        <?php if (!empty($recent_scans)): ?>
        <div class="recent-scans">
            <h5>Scans récents</h5>
            <div class="scans-list">
                <?php foreach ($recent_scans as $scan): ?>
                <div class="scan-item">
                    <div class="scan-icon status-<?= $scan['status'] ?>">
                        <i class="fas fa-<?= $scan['status'] === 'completed' ? 'check-circle' : 'spinner' ?>"></i>
                    </div>
                    <div class="scan-details">
                        <span class="scan-type"><?= ucfirst(str_replace('_', ' ', $scan['type'])) ?></span>
                        <small>
                            Durée: <?= $scan['duration'] ?> | 
                            Menaces: <?= $scan['threats_found'] ?> | 
                            <?= $this->timeAgo($scan['completed_at']) ?>
                        </small>
                    </div>
                    <div class="scan-result">
                        <?php if ($scan['threats_found'] > 0): ?>
                        <span class="threats-found"><?= $scan['threats_found'] ?></span>
                        <?php else: ?>
                        <i class="fas fa-shield-alt text-success"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Actions de sécurité -->
        <div class="security-actions">
            <button class="btn btn-primary" onclick="runSecurityScan()">
                <i class="fas fa-search"></i> Scanner maintenant
            </button>
            <button class="btn btn-secondary" onclick="updateSecurityRules()">
                <i class="fas fa-download"></i> Mettre à jour règles
            </button>
            <button class="btn btn-outline-secondary" onclick="exportSecurityReport()">
                <i class="fas fa-file-export"></i> Rapport
            </button>
            <a href="/admin/security" class="btn btn-outline-primary">
                Gestion avancée
            </a>
        </div>
    </div>
</div>

<style>
.security-alerts-widget {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.security-alerts-widget .widget-header {
    background: #dc3545;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.security-alerts-widget .widget-header h3 {
    margin: 0;
    font-size: 1.1em;
}

.widget-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.security-score {
    background: rgba(255,255,255,0.2);
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 0.9em;
    font-weight: bold;
}

.security-alerts-widget .widget-content {
    padding: 20px;
    max-height: 800px;
    overflow-y: auto;
}

.security-overview {
    margin-bottom: 25px;
}

.threat-level {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.threat-low {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.threat-medium {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.threat-high {
    background: #ffe6cc;
    border: 1px solid #ffcc99;
    color: #b45309;
}

.threat-critical {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.threat-indicator {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5em;
}

.threat-info h4 {
    margin: 0 0 5px 0;
    font-size: 1.1em;
}

.threat-info p {
    margin: 0;
    opacity: 0.8;
}

.security-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.stat-item {
    text-align: center;
    padding: 10px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
}

.stat-number {
    display: block;
    font-size: 1.5em;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.8em;
    color: #6c757d;
}

.checks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.check-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
}

.check-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9em;
}

.status-good .check-icon,
.status-active .check-icon {
    background: #d4edda;
    color: #155724;
}

.status-warning .check-icon {
    background: #fff3cd;
    color: #856404;
}

.status-error .check-icon {
    background: #f8d7da;
    color: #721c24;
}

.check-details {
    flex: 1;
}

.check-name {
    display: block;
    font-weight: bold;
    color: #333;
    font-size: 0.9em;
}

.check-details small {
    color: #6c757d;
    font-size: 0.8em;
}

.check-score {
    font-size: 0.8em;
    color: #28a745;
    font-weight: bold;
}

.indicators-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.indicator-item {
    text-align: center;
    padding: 15px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
}

.indicator-number {
    display: block;
    font-size: 2em;
    font-weight: bold;
    margin-bottom: 5px;
}

.indicator-description {
    font-size: 0.8em;
    color: #6c757d;
}

.alerts-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 10px;
}

.alert-filters {
    display: flex;
    gap: 5px;
}

.filter-btn {
    padding: 4px 8px;
    border: 1px solid #e9ecef;
    background: white;
    border-radius: 15px;
    cursor: pointer;
    font-size: 0.8em;
    transition: all 0.2s;
}

.filter-btn:hover {
    background: #f8f9fa;
}

.filter-btn.active {
    background: #dc3545;
    color: white;
    border-color: #dc3545;
}

.alert-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 10px;
    transition: all 0.2s;
}

.alert-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.severity-critical {
    border-left: 4px solid #dc3545;
}

.severity-high {
    border-left: 4px solid #fd7e14;
}

.severity-medium {
    border-left: 4px solid #ffc107;
}

.severity-low {
    border-left: 4px solid #28a745;
}

.alert-indicator {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9em;
    flex-shrink: 0;
}

.alert-content {
    flex: 1;
}

.alert-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.alert-header h6 {
    margin: 0;
    font-size: 0.95em;
    color: #333;
}

.alert-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.alert-time, .alert-risk {
    font-size: 0.8em;
    color: #6c757d;
}

.alert-description {
    margin: 0 0 8px 0;
    font-size: 0.9em;
    color: #666;
    line-height: 1.4;
}

.alert-details {
    margin-bottom: 10px;
}

.alert-details small {
    color: #6c757d;
    font-size: 0.8em;
}

.alert-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.alert-severity {
    padding: 2px 8px;
    border-radius: 12px;
    color: white;
    font-size: 0.8em;
    font-weight: bold;
}

.alert-status {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}

.status-active {
    background: #dc3545;
    color: white;
}

.status-investigating {
    background: #ffc107;
    color: #333;
}

.status-blocked {
    background: #6c757d;
    color: white;
}

.status-resolved {
    background: #28a745;
    color: white;
}

.alert-ip {
    font-size: 0.8em;
    color: #6c757d;
    display: flex;
    align-items: center;
    gap: 4px;
}

.alert-actions {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.btn-action {
    background: none;
    border: 1px solid #e9ecef;
    color: #6c757d;
    cursor: pointer;
    padding: 5px;
    border-radius: 4px;
    font-size: 0.8em;
    transition: all 0.2s;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-action:hover {
    background: #f8f9fa;
}

.btn-investigate:hover {
    color: #17a2b8;
    border-color: #17a2b8;
}

.btn-block:hover {
    color: #dc3545;
    border-color: #dc3545;
}

.btn-resolve:hover {
    color: #28a745;
    border-color: #28a745;
}

.btn-details:hover {
    color: #6f42c1;
    border-color: #6f42c1;
}

.no-alerts {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.no-alerts i {
    font-size: 3em;
    margin-bottom: 15px;
    color: #28a745;
}

.scans-list {
    margin-top: 10px;
}

.scan-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.scan-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9em;
}

.scan-details {
    flex: 1;
}

.scan-type {
    display: block;
    font-weight: bold;
    color: #333;
    font-size: 0.9em;
}

.scan-details small {
    color: #6c757d;
    font-size: 0.8em;
}

.scan-result {
    text-align: center;
}

.threats-found {
    background: #dc3545;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}

.security-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 0.9em;
    transition: all 0.2s;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-secondary {
    background: #6c757d;
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

.text-success {
    color: #28a745 !important;
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
    .security-stats {
        grid-template-columns: 1fr;
    }
    
    .checks-grid {
        grid-template-columns: 1fr;
    }
    
    .indicators-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .alert-item {
        flex-direction: column;
        gap: 10px;
    }
    
    .security-actions {
        flex-direction: column;
    }
}

/* Animations pour les filtres */
.alert-item.fade-out {
    opacity: 0.3;
    transform: scale(0.95);
    pointer-events: none;
}

.alert-item.fade-in {
    opacity: 1;
    transform: scale(1);
}
</style>

<script>
// Gestion des filtres
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const alertItems = document.querySelectorAll('.alert-item');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const severity = this.dataset.severity;
            
            // Mettre à jour les boutons actifs
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Filtrer les alertes
            alertItems.forEach(item => {
                if (severity === 'all' || item.dataset.severity === severity) {
                    item.style.display = 'flex';
                    item.classList.add('fade-in');
                    item.classList.remove('fade-out');
                } else {
                    item.classList.add('fade-out');
                    item.classList.remove('fade-in');
                    setTimeout(() => {
                        if (item.classList.contains('fade-out')) {
                            item.style.display = 'none';
                        }
                    }, 200);
                }
            });
        });
    });
});

function investigateAlert(alertId) {
    console.log('Investigating alert:', alertId);
    // Implementation pour enquêter sur l'alerte
}

function blockThreat(alertId) {
    if (confirm('Êtes-vous sûr de vouloir bloquer cette menace ?')) {
        console.log('Blocking threat:', alertId);
        // Implementation pour bloquer la menace
    }
}

function resolveAlert(alertId) {
    if (confirm('Marquer cette alerte comme résolue ?')) {
        console.log('Resolving alert:', alertId);
        // Implementation pour résoudre l'alerte
        
        // Mettre à jour visuellement
        const alertElement = document.querySelector(`[data-id="${alertId}"]`);
        if (alertElement) {
            alertElement.classList.add('status-resolved');
            const statusBadge = alertElement.querySelector('.alert-status');
            if (statusBadge) {
                statusBadge.textContent = 'Resolved';
                statusBadge.className = 'alert-status status-resolved';
            }
        }
    }
}

function showAlertDetails(alertId) {
    console.log('Showing details for alert:', alertId);
    // Implementation pour afficher les détails
}

function runSecurityScan() {
    console.log('Running security scan...');
    // Implementation pour lancer un scan
    
    // Animation de chargement
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scan en cours...';
    button.disabled = true;
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
        alert('Scan de sécurité terminé. Aucune nouvelle menace détectée.');
    }, 3000);
}

function updateSecurityRules() {
    console.log('Updating security rules...');
    // Implementation pour mettre à jour les règles
}

function exportSecurityReport() {
    console.log('Exporting security report...');
    // Implementation pour exporter le rapport
}

// Auto-refresh des données de sécurité
setInterval(function() {
    console.log('Auto-refreshing security data...');
    // Ici on pourrait recharger les données via AJAX
}, <?= $this->getConfig('refresh_interval', 60) * 1000 ?>);
</script>