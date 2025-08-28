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
if (!defined('DB_HOST') || DB_HOST === 'localhost' && DB_NAME === 'n3xtweb_database' && DB_USER === 'n3xtweb_user') {
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>N3XT WEB</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .hero-section {
            text-align: center;
            padding: 100px 0 80px;
            color: white;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 40px;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }
        
        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .hero-btn {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .hero-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .features-section {
            background: white;
            padding: 80px 0;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            margin-top: 60px;
        }
        
        .feature-card {
            text-align: center;
            padding: 40px 30px;
            border-radius: 15px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        
        .feature-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .feature-description {
            color: #7f8c8d;
            line-height: 1.6;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .section-subtitle {
            text-align: center;
            font-size: 1.1rem;
            color: #7f8c8d;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .section-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="container">
            <h1 class="hero-title">N3XT WEB</h1>
            <p class="hero-subtitle">
                A powerful, secure, and responsive content management system with advanced 
                back office capabilities, automated updates, and comprehensive backup solutions.
            </p>
            <div class="hero-buttons">
                <a href="<?php echo htmlspecialchars($adminDirectory); ?>/login.php" class="hero-btn">
                    🔐 Admin Panel
                </a>
                <a href="#features" class="hero-btn">
                    ✨ Learn More
                </a>
                <?php if (MAINTENANCE_MODE): ?>
                    <a href="maintenance.php?preview=1" class="hero-btn">
                        🔧 Maintenance Preview
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div id="features" class="features-section">
        <div class="container">
            <h2 class="section-title">Key Features</h2>
            <p class="section-subtitle">
                Everything you need to manage your web communication platform securely and efficiently.
            </p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🔐</div>
                    <h3 class="feature-title">Enhanced Security</h3>
                    <p class="feature-description">
                        Multi-layer security with captcha protection, rate limiting, IP blocking, 
                        and comprehensive access logging. PDO prepared statements prevent SQL injection.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔄</div>
                    <h3 class="feature-title">Automated Updates</h3>
                    <p class="feature-description">
                        Seamless GitHub integration for downloading and applying updates with 
                        automatic backup creation and file integrity checking.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">💾</div>
                    <h3 class="feature-title">Backup & Restore</h3>
                    <p class="feature-description">
                        Complete backup and restore functionality with database dumps, 
                        file archiving, and selective restoration options.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3 class="feature-title">Mobile-First Design</h3>
                    <p class="feature-description">
                        Responsive, mobile-first interface that works perfectly on all devices 
                        from smartphones to desktop computers.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3 class="feature-title">Comprehensive Logging</h3>
                    <p class="feature-description">
                        Detailed logging system tracks access attempts, system updates, 
                        errors, and administrative actions for audit and debugging.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">⚙️</div>
                    <h3 class="feature-title">Easy Installation</h3>
                    <p class="feature-description">
                        Step-by-step installation wizard with system requirements checking, 
                        database setup, and initial configuration.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <footer style="background: #2c3e50; color: white; padding: 40px 0; text-align: center;">
        <div class="container">
            <p style="margin-bottom: 10px;">
                <strong>N3XT WEB</strong> v<?php echo SYSTEM_VERSION; ?>
            </p>
            <p style="opacity: 0.7; font-size: 14px;">
                Secure • Responsive • Powerful
            </p>
            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
                <p style="margin-top: 20px; padding: 10px; background: rgba(231, 76, 60, 0.2); border-radius: 4px; font-size: 12px;">
                    🔧 Debug Mode Active
                </p>
            <?php endif; ?>
        </div>
    </footer>
    
    <script>
        // Smooth scrolling for anchor links
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
            
            // Add entrance animations
            const featureCards = document.querySelectorAll('.feature-card');
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
            
            featureCards.forEach(function(card) {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>