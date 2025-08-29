<?php
/**
 * N3XT WEB - Theme Preview Widget View
 * Vue pour le widget d'aperçu du thème
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}
?>

<div class="widget theme-preview-widget">
    <div class="widget-header">
        <h3><i class="fas fa-palette"></i> <?= htmlspecialchars($this->getConfig('title', 'Aperçu du thème')) ?></h3>
        <span class="widget-refresh" title="Dernière mise à jour: <?= $last_updated ?>">
            <i class="fas fa-sync-alt"></i>
        </span>
    </div>
    
    <div class="widget-content">
        <!-- Thème actuel -->
        <div class="current-theme">
            <h4>Thème actuel</h4>
            <div class="theme-card active">
                <div class="theme-info">
                    <h5><?= htmlspecialchars($current_theme['name']) ?></h5>
                    <p><?= htmlspecialchars($current_theme['description']) ?></p>
                    <div class="theme-meta">
                        <span class="version">v<?= htmlspecialchars($current_theme['version']) ?></span>
                        <span class="author">par <?= htmlspecialchars($current_theme['author']) ?></span>
                    </div>
                </div>
                
                <?php if ($this->getConfig('show_theme_info', true)): ?>
                <div class="theme-colors">
                    <h6>Palette de couleurs</h6>
                    <div class="color-palette">
                        <?php foreach ($current_theme['colors'] as $name => $color): ?>
                        <div class="color-item" title="<?= ucfirst($name) ?>: <?= $color ?>">
                            <div class="color-swatch" style="background-color: <?= $color ?>"></div>
                            <span><?= ucfirst($name) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="theme-features">
                    <h6>Fonctionnalités</h6>
                    <div class="features-list">
                        <?php foreach ($current_theme['features'] as $feature => $enabled): ?>
                        <div class="feature-item">
                            <i class="fas <?= $enabled ? 'fa-check-circle text-success' : 'fa-times-circle text-muted' ?>"></i>
                            <span><?= ucfirst(str_replace('_', ' ', $feature)) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Thèmes disponibles -->
        <div class="available-themes">
            <h4>Thèmes disponibles</h4>
            <div class="themes-grid">
                <?php foreach ($available_themes as $themeId => $theme): ?>
                <div class="theme-card <?= $theme['active'] ? 'active' : '' ?>">
                    <div class="theme-preview">
                        <?php if ($theme['preview'] && file_exists(__DIR__ . '/../../..' . $theme['preview'])): ?>
                        <img src="<?= htmlspecialchars($theme['preview']) ?>" alt="Aperçu <?= htmlspecialchars($theme['name']) ?>">
                        <?php else: ?>
                        <div class="no-preview">
                            <i class="fas fa-image"></i>
                            <span>Pas d'aperçu</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="theme-info">
                        <h6><?= htmlspecialchars($theme['name']) ?></h6>
                        <p><?= htmlspecialchars($theme['description']) ?></p>
                        <div class="theme-actions">
                            <?php if ($theme['active']): ?>
                            <span class="badge badge-success">Actif</span>
                            <?php else: ?>
                            <button class="btn btn-sm btn-primary" onclick="activateTheme('<?= $themeId ?>')">
                                Activer
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Options de personnalisation -->
        <?php if ($this->getConfig('show_customization', true)): ?>
        <div class="customization-options">
            <h4>Personnalisation</h4>
            <div class="options-grid">
                <?php foreach ($customization_options as $key => $option): ?>
                <div class="option-item">
                    <label for="<?= $key ?>"><?= htmlspecialchars($option['name']) ?></label>
                    
                    <?php if ($option['type'] === 'color'): ?>
                    <input type="color" 
                           id="<?= $key ?>" 
                           name="<?= $key ?>" 
                           value="<?= htmlspecialchars($option['value']) ?>"
                           class="form-control form-control-color">
                           
                    <?php elseif ($option['type'] === 'select'): ?>
                    <select id="<?= $key ?>" name="<?= $key ?>" class="form-control">
                        <?php foreach ($option['options'] as $optValue): ?>
                        <option value="<?= htmlspecialchars($optValue) ?>" 
                                <?= $option['value'] === $optValue ? 'selected' : '' ?>>
                            <?= htmlspecialchars($optValue) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php elseif ($option['type'] === 'checkbox'): ?>
                    <div class="form-check">
                        <input type="checkbox" 
                               id="<?= $key ?>" 
                               name="<?= $key ?>" 
                               class="form-check-input"
                               <?= $option['value'] ? 'checked' : '' ?>>
                    </div>
                    
                    <?php elseif ($option['type'] === 'file'): ?>
                    <input type="file" 
                           id="<?= $key ?>" 
                           name="<?= $key ?>" 
                           class="form-control"
                           accept="image/*">
                    <small class="form-text text-muted">Actuel: <?= htmlspecialchars($option['value']) ?></small>
                    
                    <?php else: ?>
                    <input type="text" 
                           id="<?= $key ?>" 
                           name="<?= $key ?>" 
                           value="<?= htmlspecialchars($option['value']) ?>"
                           class="form-control">
                    <?php endif; ?>
                    
                    <?php if (!empty($option['description'])): ?>
                    <small class="form-text text-muted"><?= htmlspecialchars($option['description']) ?></small>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="customization-actions">
                <button class="btn btn-primary" onclick="saveCustomization()">
                    <i class="fas fa-save"></i> Sauvegarder
                </button>
                <button class="btn btn-secondary" onclick="resetCustomization()">
                    <i class="fas fa-undo"></i> Réinitialiser
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Changements récents -->
        <?php if (!empty($recent_changes)): ?>
        <div class="recent-changes">
            <h4>Changements récents</h4>
            <div class="changes-list">
                <?php foreach ($recent_changes as $change): ?>
                <div class="change-item">
                    <div class="change-icon">
                        <i class="fas <?= $change['action'] === 'activated' ? 'fa-check' : 'fa-download' ?>"></i>
                    </div>
                    <div class="change-details">
                        <span class="theme-name"><?= htmlspecialchars($change['theme_name']) ?></span>
                        <span class="action"><?= $change['action'] === 'activated' ? 'activé' : 'installé' ?></span>
                        <span class="meta">par <?= htmlspecialchars($change['changed_by']) ?> - <?= htmlspecialchars($change['changed_at']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.theme-preview-widget {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.theme-preview-widget .widget-header {
    background: #6f42c1;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.theme-preview-widget .widget-header h3 {
    margin: 0;
    font-size: 1.1em;
}

.theme-preview-widget .widget-content {
    padding: 20px;
}

.theme-card {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    transition: all 0.2s;
}

.theme-card.active {
    border-color: #6f42c1;
    background: #f8f9fa;
}

.theme-card:hover {
    border-color: #6f42c1;
    box-shadow: 0 2px 8px rgba(111, 66, 193, 0.1);
}

.themes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.theme-preview {
    width: 100%;
    height: 120px;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
    background: #f8f9fa;
}

.theme-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6c757d;
}

.color-palette {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.color-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
}

.color-swatch {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px #dee2e6;
}

.color-item span {
    font-size: 0.8em;
    color: #6c757d;
}

.features-list {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-top: 10px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.option-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.option-item label {
    font-weight: bold;
    color: #333;
}

.customization-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

.changes-list {
    margin-top: 15px;
}

.change-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.change-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
}

.change-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.theme-name {
    font-weight: bold;
    color: #333;
}

.action {
    color: #6f42c1;
    font-size: 0.9em;
}

.meta {
    color: #6c757d;
    font-size: 0.8em;
}

.widget-refresh {
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.widget-refresh:hover {
    opacity: 1;
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

.btn-primary {
    background: #6f42c1;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-sm {
    padding: 3px 8px;
    font-size: 0.8em;
}

.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8em;
    font-weight: bold;
}

.badge-success {
    background: #28a745;
    color: white;
}

.text-success {
    color: #28a745 !important;
}

.text-muted {
    color: #6c757d !important;
}

.form-control {
    padding: 6px 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 0.9em;
}

.form-control-color {
    width: 50px;
    height: 35px;
    padding: 2px;
}

.form-check {
    display: flex;
    align-items: center;
}

.form-check-input {
    margin-right: 8px;
}

.form-text {
    font-size: 0.8em;
    margin-top: 5px;
}
</style>

<script>
function activateTheme(themeId) {
    if (confirm('Êtes-vous sûr de vouloir activer ce thème ?')) {
        // Implementation pour activer le thème
        console.log('Activation du thème:', themeId);
        // Ici on ferait un appel AJAX pour activer le thème
    }
}

function saveCustomization() {
    // Implementation pour sauvegarder la personnalisation
    console.log('Sauvegarde de la personnalisation');
    // Ici on ferait un appel AJAX pour sauvegarder
}

function resetCustomization() {
    if (confirm('Êtes-vous sûr de vouloir réinitialiser la personnalisation ?')) {
        // Implementation pour réinitialiser
        console.log('Réinitialisation de la personnalisation');
    }
}
</script>