<?php
/**
 * N3XT WEB - Main Index Page
 * 
 * Main entry point for the N3XT WEB system.
 */

// Define security constant before including any files
define('IN_N3XTWEB', true);

// Check if system is installed
if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

// Load config and check if properly configured
require_once 'config/config.php';

// Additional check: if database config is still default, redirect to install
if (!defined('DB_HOST') || (DB_HOST === 'nxtxyzylie618.mysql.db' && DB_NAME === 'nxtxyzylie618_db' && DB_USER === 'nxtxyzylie618_user')) {
    header('Location: install.php');
    exit;
}

// Check maintenance mode
if (MAINTENANCE_MODE) {
    // Allow admin access during maintenance
    session_start();
    $isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    
    if (!$isAdmin) {
        header('Location: maintenance.php');
        exit;
    }
}

// Find admin directory (could be 'admin' or a generated BO directory)
$adminDirectory = 'admin'; // default
if (defined('ADMIN_PATH')) {
    $adminDirectory = basename(ADMIN_PATH);
} else {
    // Look for directories starting with 'bo-'
    $dirs = glob('bo-*', GLOB_ONLYDIR);
    if (!empty($dirs)) {
        $adminDirectory = $dirs[0]; // Use the first found BO directory
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="N3XT WEB - Solution de communication visuelle moderne et responsive">
    <meta name="keywords" content="communication, design, web, graphisme">
    <meta name="author" content="N3XT WEB">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="<?php echo AssetOptimizer::getAssetUrl('assets/css/style.css'); ?>" as="style">
    <link rel="preload" href="fav.png" as="image">
    
    <!-- Security headers -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    
    <link rel="icon" type="image/png" href="fav.png">
    <title>N3XT WEB - Communication Visuelle</title>
    <link rel="stylesheet" href="<?php echo AssetOptimizer::getAssetUrl('assets/css/style.css'); ?>">
    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        
        /* Header */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .logo img {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }
        
        .main-nav {
            display: flex;
            gap: 30px;
        }
        
        .main-nav a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            padding: 10px 0;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .main-nav a:hover,
        .main-nav a.active {
            color: #3498db;
            border-bottom-color: #3498db;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .hero-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 40px;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .cta-button {
            background: #e74c3c;
            color: white;
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .cta-button:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        /* Services Section */
        .services-section {
            padding: 80px 0;
            background: white;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .section-subtitle {
            text-align: center;
            font-size: 1.2rem;
            color: #7f8c8d;
            margin-bottom: 60px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            margin-top: 40px;
        }
        
        .service-card {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .service-card:hover {
            transform: translateY(-10px);
        }
        
        .service-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        
        .service-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .service-description {
            color: #7f8c8d;
            line-height: 1.6;
        }
        
        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            padding: 60px 0 30px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-section h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: #3498db;
        }
        
        .footer-section p,
        .footer-section a {
            color: #bdc3c7;
            text-decoration: none;
            line-height: 1.6;
        }
        
        .footer-section a:hover {
            color: #3498db;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-links a {
            display: inline-block;
            width: 40px;
            height: 40px;
            background: #34495e;
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background: #3498db;
            transform: translateY(-2px);
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #34495e;
            color: #95a5a6;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }
            
            .main-nav {
                gap: 20px;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <nav class="main-nav">
                <a href="index.php" class="active">Accueil</a>
                <a href="#client-area">Espace Pro</a>
                <a href="#boutique" style="display: none;">Boutique</a>
            </nav>
            <div class="logo">
                <?php 
                $logoPath = 'fav.png';
                if (file_exists($logoPath)): ?>
                    <img src="<?php echo $logoPath; ?>" alt="N3XT WEB">
                <?php endif; ?>
                N3XT WEB
            </div>
        </div>
    </header>
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">Communication Visuelle Professionnelle</h1>
            <p class="hero-subtitle">
                Créez une identité visuelle forte et percutante pour votre entreprise. 
                De la conception graphique au développement web, nous donnons vie à vos idées.
            </p>
            <a href="#services" class="cta-button">Découvrir nos services</a>
        </div>
    </section>
    
    <!-- Services Section -->
    <section id="services" class="services-section">
        <div class="container">
            <h2 class="section-title">Nos Services</h2>
            <p class="section-subtitle">
                Une gamme complète de services pour répondre à tous vos besoins en communication visuelle
            </p>
            
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">🎨</div>
                    <h3 class="service-title">Design Graphique</h3>
                    <p class="service-description">
                        Création d'identités visuelles, logos, cartes de visite, brochures et supports de communication imprimés.
                    </p>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">💻</div>
                    <h3 class="service-title">Développement Web</h3>
                    <p class="service-description">
                        Sites web responsives, applications web modernes et solutions e-commerce sur mesure.
                    </p>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">📱</div>
                    <h3 class="service-title">Communication Digitale</h3>
                    <p class="service-description">
                        Stratégies de communication numérique, réseaux sociaux et marketing digital.
                    </p>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">📊</div>
                    <h3 class="service-title">Branding</h3>
                    <p class="service-description">
                        Développement de l'image de marque, charte graphique et positionnement stratégique.
                    </p>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">🎬</div>
                    <h3 class="service-title">Contenu Multimédia</h3>
                    <p class="service-description">
                        Production vidéo, animation graphique et contenus interactifs pour tous supports.
                    </p>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">🚀</div>
                    <h3 class="service-title">Conseil & Stratégie</h3>
                    <p class="service-description">
                        Accompagnement stratégique et conseil en communication pour optimiser votre impact.
                    </p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>N3XT WEB</h3>
                    <p>
                        Votre partenaire en communication visuelle. Nous créons des solutions créatives 
                        et innovantes pour donner vie à vos projets.
                    </p>
                    <div class="social-links">
                        <a href="#" title="Facebook">📘</a>
                        <a href="#" title="Twitter">🐦</a>
                        <a href="#" title="LinkedIn">💼</a>
                        <a href="#" title="Instagram">📸</a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h3>Services</h3>
                    <p><a href="#services">Design Graphique</a></p>
                    <p><a href="#services">Développement Web</a></p>
                    <p><a href="#services">Communication Digitale</a></p>
                    <p><a href="#services">Branding</a></p>
                </div>
                
                <div class="footer-section">
                    <h3>Contact</h3>
                    <p>📧 contact@n3xtweb.fr</p>
                    <p>📞 +33 1 23 45 67 89</p>
                    <p>📍 Paris, France</p>
                </div>
                
                <div class="footer-section">
                    <h3>Informations légales</h3>
                    <p><a href="#mentions">Mentions légales</a></p>
                    <p><a href="#privacy">Politique de confidentialité</a></p>
                    <p><a href="#terms">Conditions d'utilisation</a></p>
                    <p><a href="#cookies">Politique des cookies</a></p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> N3XT WEB. Tous droits réservés. Version <?php echo SYSTEM_VERSION; ?></p>
                <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
                    <p style="margin-top: 10px; font-size: 12px; opacity: 0.7;">
                        🔧 Mode debug actif
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </footer>
    
    <script>
        // Navigation fluide pour les liens d'ancrage
        document.addEventListener('DOMContentLoaded', function() {
            const links = document.querySelectorAll('a[href^="#"]');
            
            links.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
            
            // Animations d'entrée
            const serviceCards = document.querySelectorAll('.service-card');
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry, index) {
                    if (entry.isIntersecting) {
                        setTimeout(function() {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                    }
                });
            });
            
            serviceCards.forEach(function(card) {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>
