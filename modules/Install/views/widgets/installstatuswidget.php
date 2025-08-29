<?php
/**
 * N3XT WEB - Install Status Widget View
 * Vue pour le widget de statut d'installation
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}
?>

<div class="widget install-status-widget">
    <div class="widget-header">
        <h3><i class="fas fa-cog"></i> <?= htmlspecialchars($this->getConfig('title', 'Statut d\'installation')) ?></h3>
        <span class="widget-refresh" title="Dernière mise à jour: <?= $last_updated ?>">
            <i class="fas fa-sync-alt"></i>
        </span>
    </div>
    
    <div class="widget-content">
        <!-- Statut général de l'installation -->
        <div class="install-status">
            <h4>Statut de l'installation</h4>
            <div class="status-indicator <?= $installation_status['completed'] ? 'status-success' : 'status-warning' ?>">
                <i class="fas <?= $installation_status['completed'] ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                <span><?= $installation_status['completed'] ? 'Installation complète' : 'Installation incomplète' ?></span>
            </div>
            
            <div class="install-details">
                <div class="detail-item">
                    <label>Version:</label>
                    <span><?= htmlspecialchars($installation_status['version']) ?></span>
                </div>
                <?php if ($installation_status['install_date']): ?>
                <div class="detail-item">
                    <label>Date d'installation:</label>
                    <span><?= htmlspecialchars($installation_status['install_date']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($installation_status['last_update']): ?>
                <div class="detail-item">
                    <label>Dernière mise à jour:</label>
                    <span><?= htmlspecialchars($installation_status['last_update']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Informations système -->
        <?php if ($this->getConfig('show_php_info', true)): ?>
        <div class="system-info">
            <h4>Informations système</h4>
            <div class="info-grid">
                <div class="info-item">
                    <label>PHP Version:</label>
                    <span class="<?= version_compare($system_info['php_version'], '7.4.0', '>=') ? 'text-success' : 'text-warning' ?>">
                        <?= htmlspecialchars($system_info['php_version']) ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Serveur:</label>
                    <span><?= htmlspecialchars($system_info['server_software']) ?></span>
                </div>
                <div class="info-item">
                    <label>Limite mémoire:</label>
                    <span><?= htmlspecialchars($system_info['memory_limit']) ?></span>
                </div>
                <div class="info-item">
                    <label>Espace libre:</label>
                    <span><?= htmlspecialchars($system_info['disk_free_space']) ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Vérification des prérequis -->
        <div class="requirements-check">
            <h4>Vérification des prérequis</h4>
            
            <!-- Version PHP -->
            <div class="requirement-item">
                <div class="requirement-status <?= $requirements_check['php_version']['status'] ? 'status-success' : 'status-error' ?>">
                    <i class="fas <?= $requirements_check['php_version']['status'] ? 'fa-check' : 'fa-times' ?>"></i>
                </div>
                <div class="requirement-details">
                    <label><?= htmlspecialchars($requirements_check['php_version']['name']) ?></label>
                    <span>Requis: <?= htmlspecialchars($requirements_check['php_version']['required']) ?> | 
                          Actuel: <?= htmlspecialchars($requirements_check['php_version']['current']) ?></span>
                </div>
            </div>
            
            <!-- Extensions PHP -->
            <div class="requirement-section">
                <h5>Extensions PHP</h5>
                <?php foreach ($requirements_check['extensions'] as $ext => $info): ?>
                <div class="requirement-item">
                    <div class="requirement-status <?= $info['status'] ? 'status-success' : 'status-error' ?>">
                        <i class="fas <?= $info['status'] ? 'fa-check' : 'fa-times' ?>"></i>
                    </div>
                    <div class="requirement-details">
                        <label><?= htmlspecialchars($info['name']) ?></label>
                        <span><?= $info['loaded'] ? 'Chargée' : 'Non chargée' ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Permissions de fichiers -->
            <?php if ($this->getConfig('show_file_permissions', true)): ?>
            <div class="requirement-section">
                <h5>Permissions de fichiers</h5>
                <?php foreach ($requirements_check['file_permissions'] as $path => $perm): ?>
                <div class="requirement-item">
                    <div class="requirement-status <?= $perm['status'] ? 'status-success' : 'status-error' ?>">
                        <i class="fas <?= $perm['status'] ? 'fa-check' : 'fa-times' ?>"></i>
                    </div>
                    <div class="requirement-details">
                        <label><?= htmlspecialchars($perm['path']) ?></label>
                        <span>
                            <?= $perm['exists'] ? 'Existe' : 'N\'existe pas' ?> |
                            <?= $perm['readable'] ? 'Lecture' : 'Pas de lecture' ?> |
                            <?= $perm['writable'] ? 'Écriture' : 'Pas d\'écriture' ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.install-status-widget {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.install-status-widget .widget-header {
    background: #007cba;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.install-status-widget .widget-header h3 {
    margin: 0;
    font-size: 1.1em;
}

.install-status-widget .widget-content {
    padding: 20px;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.status-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.install-details, .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.detail-item, .info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.detail-item label, .info-item label {
    font-weight: bold;
    color: #333;
}

.requirement-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.requirement-status {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8em;
}

.requirement-details {
    flex: 1;
}

.requirement-details label {
    display: block;
    font-weight: bold;
    color: #333;
}

.requirement-details span {
    color: #666;
    font-size: 0.9em;
}

.requirement-section {
    margin-top: 15px;
}

.requirement-section h5 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 0.95em;
}

.text-success {
    color: #28a745 !important;
}

.text-warning {
    color: #ffc107 !important;
}

.text-error {
    color: #dc3545 !important;
}

.widget-refresh {
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.widget-refresh:hover {
    opacity: 1;
}
</style>