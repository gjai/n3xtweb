<?php
/**
 * N3XT WEB - Modules Dashboard
 * Vue d'ensemble de tous les modules du back office
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

// Charger tous les modules
$moduleStatuses = [];
$moduleInfos = [];

try {
    $moduleNames = ['UpdateManager', 'NotificationManager', 'BackupManager', 'MaintenanceManager'];
    
    foreach ($moduleNames as $moduleName) {
        try {
            $module = ModulesLoader::getModule($moduleName);
            $moduleInfos[$moduleName] = $module->getModuleInfo();
            $moduleStatuses[$moduleName] = 'loaded';
        } catch (Exception $e) {
            $moduleStatuses[$moduleName] = 'error';
            $moduleInfos[$moduleName] = [
                'name' => $moduleName,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Obtenir les statistiques rapides
    $quickStats = [];
    
    if ($moduleStatuses['NotificationManager'] === 'loaded') {
        $notificationManager = ModulesLoader::getModule('NotificationManager');
        $quickStats['unread_notifications'] = $notificationManager->getUnreadCount();
    }
    
    if ($moduleStatuses['UpdateManager'] === 'loaded') {
        $updateManager = ModulesLoader::getModule('UpdateManager');
        $updateStatus = $updateManager->getStatus();
        $quickStats['update_available'] = false; // Sera déterminé par vérification
    }
    
    if ($moduleStatuses['BackupManager'] === 'loaded') {
        $backupManager = ModulesLoader::getModule('BackupManager');
        $backupStats = $backupManager->getStatistics();
        $quickStats['last_backup'] = $backupStats['last_backup'];
    }
    
    if ($moduleStatuses['MaintenanceManager'] === 'loaded') {
        $maintenanceManager = ModulesLoader::getModule('MaintenanceManager');
        $maintenanceStats = $maintenanceManager->getStatistics();
        $quickStats['next_maintenance'] = $maintenanceStats['next_cleanup'] ?? time();
    }
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des modules : " . $e->getMessage();
}

$csrfToken = Security::generateCSRFToken();
?>

<div class="modules-dashboard">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🎛️ Tableau de Bord des Modules</h2>
        </div>
        <div class="card-body">
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistiques rapides -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $quickStats['unread_notifications'] ?? 0; ?></h3>
                            <p>Notifications non lues</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-<?php echo isset($quickStats['update_available']) && $quickStats['update_available'] ? 'warning' : 'success'; ?> text-white">
                        <div class="card-body text-center">
                            <h3><?php echo isset($quickStats['update_available']) && $quickStats['update_available'] ? 'Oui' : 'Non'; ?></h3>
                            <p>Mise à jour disponible</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3>
                                <?php 
                                if (isset($quickStats['last_backup'])) {
                                    echo date('d/m', strtotime($quickStats['last_backup']['created_at']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </h3>
                            <p>Dernière sauvegarde</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body text-center">
                            <h3><?php echo date('d/m', $quickStats['next_maintenance'] ?? time()); ?></h3>
                            <p>Prochaine maintenance</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statut des modules -->
            <div class="row mb-4">
                <?php foreach ($moduleInfos as $moduleName => $moduleInfo): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card module-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <?php 
                                    echo match($moduleName) {
                                        'UpdateManager' => '🔄 Gestionnaire de Mises à Jour',
                                        'NotificationManager' => '📢 Gestionnaire de Notifications',
                                        'BackupManager' => '💾 Gestionnaire de Sauvegardes',
                                        'MaintenanceManager' => '🔧 Gestionnaire de Maintenance',
                                        default => $moduleName
                                    };
                                    ?>
                                </h5>
                                <span class="badge badge-<?php 
                                    echo match($moduleInfo['status']) {
                                        'enabled' => 'success',
                                        'disabled' => 'warning',
                                        'error' => 'danger',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php 
                                    echo match($moduleInfo['status']) {
                                        'enabled' => 'Activé',
                                        'disabled' => 'Désactivé',
                                        'error' => 'Erreur',
                                        default => 'Inconnu'
                                    };
                                    ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if (isset($moduleInfo['error'])): ?>
                                    <div class="alert alert-danger">
                                        <small><?php echo htmlspecialchars($moduleInfo['error']); ?></small>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted"><?php echo htmlspecialchars($moduleInfo['description'] ?? ''); ?></p>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <small>
                                                <strong>Version :</strong> <?php echo htmlspecialchars($moduleInfo['version'] ?? '1.0.0'); ?><br>
                                                <strong>Dernière action :</strong> <?php echo $moduleInfo['last_action'] ? date('d/m/Y H:i', strtotime($moduleInfo['last_action'])) : 'N/A'; ?>
                                            </small>
                                        </div>
                                        <div class="col-6 text-right">
                                            <a href="#" onclick="showModule('<?php echo strtolower($moduleName); ?>')" class="btn btn-sm btn-primary">
                                                Gérer
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Actions rapides -->
            <div class="card">
                <div class="card-header">
                    <h4>Actions Rapides</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <button onclick="showModule('updatemanager')" class="btn btn-outline-primary btn-block">
                                Vérifier les mises à jour
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button onclick="showModule('backupmanager')" class="btn btn-outline-success btn-block">
                                Créer une sauvegarde
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button onclick="showModule('maintenancemanager')" class="btn btn-outline-warning btn-block">
                                Lancer la maintenance
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button onclick="showModule('notificationmanager')" class="btn btn-outline-info btn-block">
                                Voir les notifications
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.module-card {
    height: 200px;
    transition: transform 0.2s;
}

.module-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.modules-dashboard .card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.modules-dashboard .badge {
    font-size: 0.75em;
}
</style>

<script>
function showModule(moduleId) {
    // Masquer toutes les sections de modules
    const sections = document.querySelectorAll('.modules-section');
    sections.forEach(section => {
        section.style.display = 'none';
    });
    
    // Masquer le dashboard
    const dashboard = document.querySelector('.modules-dashboard');
    if (dashboard) {
        dashboard.style.display = 'none';
    }
    
    // Afficher la section du module demandé
    const targetSection = document.getElementById(moduleId + '-section');
    if (targetSection) {
        targetSection.style.display = 'block';
    }
    
    // Mettre à jour la navigation si nécessaire
    const navLinks = document.querySelectorAll('[data-page]');
    navLinks.forEach(link => {
        link.classList.remove('active');
    });
    
    const moduleNavLink = document.querySelector(`[data-page="modules"]`);
    if (moduleNavLink) {
        moduleNavLink.classList.add('active');
    }
    
    // Ajouter un bouton de retour
    addBackButton();
}

function addBackButton() {
    const sections = document.querySelectorAll('.modules-section .card .card-header');
    sections.forEach(header => {
        // Supprimer un bouton de retour existant
        const existingButton = header.querySelector('.btn-back-dashboard');
        if (existingButton) {
            existingButton.remove();
        }
        
        // Ajouter le nouveau bouton
        const backButton = document.createElement('button');
        backButton.className = 'btn btn-sm btn-secondary btn-back-dashboard';
        backButton.innerHTML = '← Retour au tableau de bord';
        backButton.style.float = 'right';
        backButton.onclick = function() {
            showDashboard();
        };
        
        header.appendChild(backButton);
    });
}

function showDashboard() {
    // Masquer toutes les sections de modules
    const sections = document.querySelectorAll('.modules-section');
    sections.forEach(section => {
        section.style.display = 'none';
    });
    
    // Afficher le dashboard
    const dashboard = document.querySelector('.modules-dashboard');
    if (dashboard) {
        dashboard.style.display = 'block';
    }
}

// Initialiser l'affichage
document.addEventListener('DOMContentLoaded', function() {
    // Masquer toutes les sections de modules au chargement
    const sections = document.querySelectorAll('.modules-section');
    sections.forEach(section => {
        section.style.display = 'none';
    });
});
</script>