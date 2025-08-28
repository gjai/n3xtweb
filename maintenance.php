<?php
/**
 * N3XT Communication - Maintenance Mode Page
 * 
 * Displays a responsive maintenance page when the system is under maintenance.
 */

// Check if maintenance mode is enabled
require_once 'config/config.php';

// Allow admin access during maintenance
session_start();
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!MAINTENANCE_MODE && !isset($_GET['preview'])) {
    header('Location: index.php');
    exit;
}

// If admin is logged in and no preview requested, redirect to index
if ($isAdmin && !isset($_GET['preview'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Site en maintenance - N3XT WEB">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="<?php echo AssetOptimizer::getAssetUrl('assets/css/style.css'); ?>" as="style">
    
    <link rel="icon" type="image/png" href="fav.png">
    <title>N3XT WEB - Maintenance en cours</title>
    <link rel="stylesheet" href="<?php echo AssetOptimizer::getAssetUrl('assets/css/style.css'); ?>">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .maintenance-container {
            text-align: center;
            color: white;
            max-width: 600px;
            padding: 40px 20px;
        }
        
        .maintenance-logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        .maintenance-logo img {
            max-width: 80px;
            max-height: 80px;
            border-radius: 50%;
        }
        
        .maintenance-title {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .maintenance-subtitle {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .maintenance-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }
        
        .maintenance-info {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }
        
        .maintenance-info h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .maintenance-info ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .maintenance-info li {
            margin-bottom: 8px;
        }
        
        .maintenance-contact {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .maintenance-contact a {
            color: #fff;
            text-decoration: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
        }
        
        .maintenance-contact a:hover {
            border-bottom-color: #fff;
        }
        
        .maintenance-social {
            margin-top: 20px;
        }
        
        .maintenance-social a {
            display: inline-block;
            margin: 0 10px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            border: none;
            transition: all 0.3s ease;
        }
        
        .maintenance-social a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .admin-notice {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(52, 152, 219, 0.9);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 1000;
        }
        
        .admin-notice a {
            color: #fff;
            text-decoration: underline;
            margin-left: 10px;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .maintenance-title {
                font-size: 2rem;
            }
            
            .maintenance-subtitle {
                font-size: 1rem;
            }
            
            .maintenance-card {
                padding: 30px 20px;
            }
            
            .maintenance-icon {
                font-size: 48px;
            }
            
            .admin-notice {
                top: 10px;
                right: 10px;
                left: 10px;
                font-size: 14px;
            }
        }
        
        /* Animation for entrance */
        .maintenance-container {
            animation: fadeInUp 1s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <?php if ($isAdmin): ?>
        <div class="admin-notice">
            👤 Admin Preview Mode
        </div>
    <?php endif; ?>
    
    <div class="maintenance-container">
        <div class="maintenance-card">
            <div class="maintenance-logo">
                <?php 
                $logoPath = 'fav.png';
                if (file_exists($logoPath)): ?>
                    <img src="<?php echo $logoPath; ?>" alt="N3XT WEB">
                <?php else: ?>
                    <span style="font-size: 48px; color: white;">🚀</span>
                <?php endif; ?>
            </div>
            
            <h1 class="maintenance-title">N3XT WEB</h1>
            
            <p class="maintenance-subtitle">
                <strong>Retour prochain</strong>
            </p>
            
            <div class="maintenance-info">
                <h3>Que se passe-t-il ?</h3>
                <ul>
                    <li>Mise à jour du système et correctifs de sécurité</li>
                    <li>Optimisations de performance</li>
                    <li>Maintenance de la base de données</li>
                    <li>Améliorations du serveur</li>
                </ul>
                
                <h3>Durée estimée</h3>
                <p>
                    <span class="loading-spinner"></span>
                    La maintenance devrait se terminer dans les prochaines heures.
                </p>
            </div>
            
            <div class="maintenance-contact">
                <h3>Besoin d'une assistance immédiate ?</h3>
                <p>
                    Si vous avez un problème urgent, veuillez nous contacter à :<br>
                    <a href="mailto:contact@n3xtweb.fr">contact@n3xtweb.fr</a>
                </p>
                
                <div class="maintenance-social">
                    <a href="#" title="Twitter">🐦</a>
                    <a href="#" title="Facebook">📘</a>
                    <a href="#" title="LinkedIn">💼</a>
                </div>
            </div>
            
            <div style="margin-top: 40px; font-size: 14px; opacity: 0.8;">
                <p>Merci pour votre patience !</p>
                <p><strong>Équipe N3XT WEB</strong></p>
            </div>
        </div>
    </div>
    
    <script>
        // Actualisation automatique toutes les 5 minutes pour vérifier si la maintenance est terminée
        setTimeout(function() {
            if (!window.location.href.includes('preview')) {
                window.location.reload();
            }
        }, 300000); // 5 minutes
        
        // Ajouter quelques éléments interactifs
        document.addEventListener('DOMContentLoaded', function() {
            // Animate social icons
            const socialLinks = document.querySelectorAll('.maintenance-social a');
            socialLinks.forEach(function(link, index) {
                link.style.animationDelay = (index * 0.2) + 's';
                link.style.animation = 'fadeInUp 0.6s ease-out both';
            });
            
            // Add hover effect to the card
            const card = document.querySelector('.maintenance-card');
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.transition = 'transform 0.3s ease';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Easter egg: Konami code for admin access hint
        let konamiCode = [];
        const konamiSequence = [
            'ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown',
            'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight',
            'KeyB', 'KeyA'
        ];
        
        document.addEventListener('keydown', function(e) {
            konamiCode.push(e.code);
            
            if (konamiCode.length > konamiSequence.length) {
                konamiCode.shift();
            }
            
            if (konamiCode.length === konamiSequence.length &&
                konamiCode.every((key, index) => key === konamiSequence[index])) {
                
                const hint = document.createElement('div');
                hint.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: rgba(0, 0, 0, 0.8);
                    color: white;
                    padding: 10px 20px;
                    border-radius: 20px;
                    font-size: 14px;
                    z-index: 1001;
                    animation: fadeInUp 0.5s ease-out;
                `;
                hint.textContent = '💡 Hint: Site under maintenance, please check back later';
                document.body.appendChild(hint);
                
                setTimeout(() => {
                    hint.remove();
                }, 5000);
                
                konamiCode = [];
            }
        });
    </script>
</body>
</html>