<?php
/**
 * N3XT WEB - Recent Events Widget View
 * Vue pour le widget d'événements récents
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}
?>

<div class="widget recent-events-widget">
    <div class="widget-header">
        <h3><i class="fas fa-history"></i> <?= htmlspecialchars($this->getConfig('title', 'Événements récents')) ?></h3>
        <div class="widget-actions">
            <span class="events-count"><?= $summary['total'] ?> événements</span>
            <span class="widget-refresh" title="Dernière mise à jour: <?= $last_updated ?>">
                <i class="fas fa-sync-alt"></i>
            </span>
        </div>
    </div>
    
    <div class="widget-content">
        <!-- Résumé des événements -->
        <div class="events-summary">
            <div class="summary-stats">
                <div class="stat-item">
                    <span class="stat-number"><?= $summary['by_time']['last_hour'] ?></span>
                    <span class="stat-label">Dernière heure</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $summary['by_time']['last_24h'] ?></span>
                    <span class="stat-label">24 heures</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $summary['by_time']['last_week'] ?></span>
                    <span class="stat-label">7 jours</span>
                </div>
            </div>
            
            <!-- Catégories -->
            <div class="categories-stats">
                <?php foreach ($categories as $categoryId => $category): ?>
                <div class="category-item" style="border-left: 3px solid <?= $category['color'] ?>">
                    <div class="category-icon" style="color: <?= $category['color'] ?>">
                        <i class="<?= $category['icon'] ?>"></i>
                    </div>
                    <div class="category-info">
                        <span class="category-name"><?= $category['name'] ?></span>
                        <span class="category-count"><?= $summary['by_category'][$categoryId] ?? 0 ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Filtres rapides -->
        <div class="events-filters">
            <div class="filter-buttons">
                <button class="filter-btn active" data-category="all">Tous</button>
                <?php foreach ($categories as $categoryId => $category): ?>
                <button class="filter-btn" data-category="<?= $categoryId ?>" style="color: <?= $category['color'] ?>">
                    <i class="<?= $category['icon'] ?>"></i>
                    <?= $category['name'] ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Timeline des événements -->
        <div class="events-timeline">
            <?php if (empty($timeline)): ?>
            <div class="no-events">
                <i class="fas fa-calendar-times"></i>
                <p>Aucun événement à afficher</p>
            </div>
            <?php else: ?>
            <?php foreach ($timeline as $dateGroup): ?>
            <div class="timeline-group" data-date="<?= $dateGroup['date'] ?>">
                <div class="timeline-date">
                    <h5><?= $dateGroup['formatted_date'] ?></h5>
                    <span class="events-count"><?= count($dateGroup['events']) ?> événement(s)</span>
                </div>
                
                <div class="timeline-events">
                    <?php foreach ($dateGroup['events'] as $event): ?>
                    <div class="event-item" 
                         data-category="<?= $event['category'] ?>" 
                         data-severity="<?= $event['severity'] ?>">
                         
                        <div class="event-indicator" style="background-color: <?= $this->getSeverityColor($event['severity']) ?>">
                            <i class="<?= $this->getEventIcon($event['type']) ?>"></i>
                        </div>
                        
                        <div class="event-content">
                            <div class="event-header">
                                <h6><?= htmlspecialchars($event['message']) ?></h6>
                                <span class="event-time"><?= $this->timeAgo($event['created_at']) ?></span>
                            </div>
                            
                            <?php if (!empty($event['details'])): ?>
                            <p class="event-details"><?= htmlspecialchars($event['details']) ?></p>
                            <?php endif; ?>
                            
                            <div class="event-meta">
                                <div class="event-tags">
                                    <span class="event-category" style="background-color: <?= $categories[$event['category']]['color'] ?>">
                                        <?= $categories[$event['category']]['name'] ?>
                                    </span>
                                    <span class="event-severity severity-<?= $event['severity'] ?>">
                                        <?= ucfirst($event['severity']) ?>
                                    </span>
                                </div>
                                
                                <div class="event-source">
                                    <?php if ($event['username']): ?>
                                    <span class="event-user">
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($event['username']) ?>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($event['ip_address']): ?>
                                    <span class="event-ip">
                                        <i class="fas fa-globe"></i>
                                        <?= htmlspecialchars($event['ip_address']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="event-actions">
                            <button class="btn-expand" onclick="toggleEventDetails(<?= $event['id'] ?>)" title="Voir détails">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Actions -->
        <div class="events-actions">
            <button class="btn btn-sm btn-outline-primary" onclick="refreshEvents()">
                <i class="fas fa-sync-alt"></i> Actualiser
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportEvents()">
                <i class="fas fa-download"></i> Exporter
            </button>
            <a href="/admin/events" class="btn btn-sm btn-primary">
                Voir tous les événements
            </a>
        </div>
    </div>
</div>

<style>
.recent-events-widget {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.recent-events-widget .widget-header {
    background: #28a745;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.recent-events-widget .widget-header h3 {
    margin: 0;
    font-size: 1.1em;
}

.widget-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.events-count {
    background: rgba(255,255,255,0.2);
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
}

.recent-events-widget .widget-content {
    padding: 20px;
    max-height: 700px;
    overflow-y: auto;
}

.events-summary {
    margin-bottom: 20px;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 15px;
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
    color: #28a745;
}

.stat-label {
    font-size: 0.8em;
    color: #6c757d;
}

.categories-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
}

.category-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
}

.category-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.category-info {
    flex: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.category-name {
    font-weight: bold;
    color: #333;
}

.category-count {
    background: #e9ecef;
    color: #6c757d;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.8em;
}

.events-filters {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.filter-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 6px 12px;
    border: 1px solid #e9ecef;
    background: white;
    border-radius: 20px;
    cursor: pointer;
    font-size: 0.9em;
    transition: all 0.2s;
}

.filter-btn:hover {
    background: #f8f9fa;
}

.filter-btn.active {
    background: #28a745;
    color: white;
    border-color: #28a745;
}

.timeline-group {
    margin-bottom: 25px;
}

.timeline-date {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e9ecef;
}

.timeline-date h5 {
    margin: 0;
    color: #333;
}

.timeline-events {
    position: relative;
    padding-left: 20px;
}

.timeline-events::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.event-item {
    position: relative;
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    padding: 15px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: white;
    transition: all 0.2s;
}

.event-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-color: #28a745;
}

.event-indicator {
    position: absolute;
    left: -30px;
    top: 50%;
    transform: translateY(-50%);
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.8em;
    border: 3px solid white;
    box-shadow: 0 0 0 1px #e9ecef;
}

.event-content {
    flex: 1;
}

.event-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.event-header h6 {
    margin: 0;
    font-size: 0.95em;
    color: #333;
    font-weight: bold;
}

.event-time {
    font-size: 0.8em;
    color: #6c757d;
    white-space: nowrap;
}

.event-details {
    margin: 0 0 10px 0;
    font-size: 0.9em;
    color: #666;
    line-height: 1.4;
}

.event-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.event-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.event-category {
    padding: 2px 8px;
    border-radius: 12px;
    color: white;
    font-size: 0.8em;
    font-weight: bold;
}

.event-severity {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}

.severity-success {
    background: #28a745;
    color: white;
}

.severity-info {
    background: #17a2b8;
    color: white;
}

.severity-warning {
    background: #ffc107;
    color: #333;
}

.severity-error {
    background: #dc3545;
    color: white;
}

.severity-critical {
    background: #6f42c1;
    color: white;
}

.event-source {
    display: flex;
    gap: 15px;
    font-size: 0.8em;
    color: #6c757d;
}

.event-user, .event-ip {
    display: flex;
    align-items: center;
    gap: 4px;
}

.event-actions {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.btn-expand {
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 5px;
    border-radius: 3px;
    font-size: 0.9em;
    transition: all 0.2s;
}

.btn-expand:hover {
    background: #e9ecef;
    color: #333;
}

.no-events {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.no-events i {
    font-size: 3em;
    margin-bottom: 15px;
    color: #dee2e6;
}

.events-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
    display: flex;
    gap: 10px;
    justify-content: center;
}

.btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 0.9em;
    transition: all 0.2s;
}

.btn-sm {
    padding: 4px 8px;
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
        grid-template-columns: 1fr;
    }
    
    .categories-stats {
        grid-template-columns: 1fr;
    }
    
    .filter-buttons {
        justify-content: center;
    }
    
    .event-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .events-actions {
        flex-direction: column;
    }
}

/* Animations pour les filtres */
.event-item.fade-out {
    opacity: 0.3;
    transform: scale(0.95);
    pointer-events: none;
}

.event-item.fade-in {
    opacity: 1;
    transform: scale(1);
}
</style>

<script>
// Gestion des filtres
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const eventItems = document.querySelectorAll('.event-item');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const category = this.dataset.category;
            
            // Mettre à jour les boutons actifs
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Filtrer les événements
            eventItems.forEach(item => {
                if (category === 'all' || item.dataset.category === category) {
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

function toggleEventDetails(eventId) {
    console.log('Toggle details for event:', eventId);
    // Implementation pour afficher/masquer les détails
}

function refreshEvents() {
    console.log('Refreshing events...');
    // Implementation pour actualiser les événements
    // Ici on ferait un appel AJAX pour recharger les données
    
    // Animation de rotation de l'icône
    const refreshIcon = document.querySelector('.widget-refresh i');
    refreshIcon.style.animation = 'rotate 1s linear';
    setTimeout(() => {
        refreshIcon.style.animation = '';
    }, 1000);
}

function exportEvents() {
    console.log('Exporting events...');
    // Implementation pour exporter les événements
}

// Animation CSS pour la rotation
const style = document.createElement('style');
style.textContent = `
    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Auto-refresh si activé
<?php if ($this->getConfig('auto_refresh', true)): ?>
setInterval(function() {
    refreshEvents();
}, <?= $this->getConfig('refresh_interval', 30) * 1000 ?>);
<?php endif; ?>
</script>