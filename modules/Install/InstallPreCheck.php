<?php
/**
 * N3XT WEB - Install Pre-Check System
 * 
 * Classe complète pour vérifier tous les prérequis système avant installation.
 * Inclut vérifications PHP, extensions, permissions, espace disque et connectivité DB.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

class InstallPreCheck {
    
    const REQUIRED_PHP_VERSION = '7.4.0';
    const REQUIRED_EXTENSIONS = ['mysqli', 'json', 'mbstring', 'curl', 'openssl', 'gd'];
    const CRITICAL_DIRECTORIES = ['config/', 'uploads/', 'logs/', 'backups/'];
    const MIN_DISK_SPACE_MB = 100; // 100MB minimum
    const RECOMMENDED_DISK_SPACE_MB = 500; // 500MB recommended
    
    private $results = [];
    private $overallStatus = true;
    private $basePath;
    
    public function __construct($basePath = null) {
        $this->basePath = $basePath ?: dirname(__DIR__, 2);
        $this->results = [];
        $this->overallStatus = true;
    }
    
    /**
     * Exécute toutes les vérifications prérequises
     */
    public function runAllChecks() {
        $this->checkPhpVersion();
        $this->checkPhpExtensions();
        $this->checkDirectoryPermissions();
        $this->checkConfigFile();
        $this->checkDiskSpace();
        
        return $this->getResults();
    }
    
    /**
     * Exécute les vérifications avec test de base de données
     */
    public function runAllChecksWithDatabase($dbConfig = null) {
        $this->runAllChecks();
        
        if ($dbConfig) {
            $this->checkDatabaseConnection($dbConfig);
        }
        
        return $this->getResults();
    }
    
    /**
     * Vérifie la version PHP
     */
    public function checkPhpVersion() {
        $currentVersion = PHP_VERSION;
        $status = version_compare($currentVersion, self::REQUIRED_PHP_VERSION, '>=');
        
        $this->results['php_version'] = [
            'name' => 'Version PHP',
            'required' => '>= ' . self::REQUIRED_PHP_VERSION,
            'current' => $currentVersion,
            'status' => $status,
            'level' => $status ? 'success' : 'error',
            'message' => $status 
                ? "Version PHP $currentVersion compatible"
                : "Version PHP $currentVersion trop ancienne (minimum requis: " . self::REQUIRED_PHP_VERSION . ")",
            'recommendation' => $status ? null : "Mettez à jour PHP vers la version " . self::REQUIRED_PHP_VERSION . " ou supérieure"
        ];
        
        if (!$status) {
            $this->overallStatus = false;
        }
    }
    
    /**
     * Vérifie les extensions PHP requises
     */
    public function checkPhpExtensions() {
        $this->results['extensions'] = [];
        
        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $loaded = extension_loaded($extension);
            
            $this->results['extensions'][$extension] = [
                'name' => "Extension PHP $extension",
                'required' => true,
                'loaded' => $loaded,
                'status' => $loaded,
                'level' => $loaded ? 'success' : 'error',
                'message' => $loaded 
                    ? "Extension $extension disponible"
                    : "Extension $extension manquante",
                'recommendation' => $loaded ? null : $this->getExtensionInstallRecommendation($extension)
            ];
            
            if (!$loaded) {
                $this->overallStatus = false;
            }
        }
    }
    
    /**
     * Vérifie les permissions des dossiers critiques
     */
    public function checkDirectoryPermissions() {
        $this->results['permissions'] = [];
        
        foreach (self::CRITICAL_DIRECTORIES as $directory) {
            $fullPath = $this->basePath . '/' . $directory;
            $exists = file_exists($fullPath);
            $readable = $exists ? is_readable($fullPath) : false;
            $writable = $exists ? is_writable($fullPath) : false;
            $status = $exists && $readable && $writable;
            
            $level = 'error';
            $message = '';
            $recommendation = '';
            
            if (!$exists) {
                $message = "Dossier $directory n'existe pas";
                $recommendation = "Créez le dossier $directory avec les permissions 755";
            } elseif (!$readable) {
                $message = "Dossier $directory non accessible en lecture";
                $recommendation = "Donnez les permissions de lecture au dossier $directory (chmod 755)";
            } elseif (!$writable) {
                $message = "Dossier $directory non accessible en écriture";
                $recommendation = "Donnez les permissions d'écriture au dossier $directory (chmod 755)";
            } else {
                $level = 'success';
                $message = "Dossier $directory accessible";
                $recommendation = null;
            }
            
            $this->results['permissions'][$directory] = [
                'name' => "Permissions $directory",
                'path' => $directory,
                'exists' => $exists,
                'readable' => $readable,
                'writable' => $writable,
                'status' => $status,
                'level' => $level,
                'message' => $message,
                'recommendation' => $recommendation
            ];
            
            if (!$status) {
                $this->overallStatus = false;
            }
        }
    }
    
    /**
     * Vérifie la présence du fichier de configuration
     */
    public function checkConfigFile() {
        $configPath = $this->basePath . '/config/config.php';
        $exists = file_exists($configPath);
        $readable = $exists ? is_readable($configPath) : false;
        
        // Pour l'installation initiale, l'absence du fichier config.php est normale
        // On vérifie plutôt la présence du template
        $templatePath = $this->basePath . '/config/config.template.php';
        $templateExists = file_exists($templatePath);
        
        if ($exists) {
            $level = 'warning';
            $message = "Fichier config/config.php déjà présent - installation déjà effectuée ?";
            $recommendation = "Vérifiez si l'installation a déjà été réalisée";
            $status = true; // Pas d'erreur bloquante
        } elseif ($templateExists) {
            $level = 'success';
            $message = "Template de configuration disponible";
            $recommendation = null;
            $status = true;
        } else {
            $level = 'error';
            $message = "Template de configuration manquant";
            $recommendation = "Assurez-vous que le fichier config/config.template.php est présent";
            $status = false;
            $this->overallStatus = false;
        }
        
        $this->results['config_file'] = [
            'name' => 'Fichier de configuration',
            'config_exists' => $exists,
            'template_exists' => $templateExists,
            'status' => $status,
            'level' => $level,
            'message' => $message,
            'recommendation' => $recommendation
        ];
    }
    
    /**
     * Vérifie l'espace disque disponible
     */
    public function checkDiskSpace() {
        $freeBytes = disk_free_space($this->basePath);
        $totalBytes = disk_total_space($this->basePath);
        
        if ($freeBytes === false || $totalBytes === false) {
            $this->results['disk_space'] = [
                'name' => 'Espace disque',
                'status' => false,
                'level' => 'warning',
                'message' => 'Impossible de déterminer l\'espace disque disponible',
                'recommendation' => 'Vérifiez les permissions du système de fichiers'
            ];
            return;
        }
        
        $freeMB = round($freeBytes / (1024 * 1024), 2);
        $totalMB = round($totalBytes / (1024 * 1024), 2);
        $usagePercent = round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1);
        
        $level = 'success';
        $status = true;
        $message = "Espace disponible: {$freeMB} MB ({$usagePercent}% utilisé)";
        $recommendation = null;
        
        if ($freeMB < self::MIN_DISK_SPACE_MB) {
            $level = 'error';
            $status = false;
            $message = "Espace disque insuffisant: {$freeMB} MB (minimum: " . self::MIN_DISK_SPACE_MB . " MB)";
            $recommendation = "Libérez de l'espace disque pour atteindre au minimum " . self::MIN_DISK_SPACE_MB . " MB";
            $this->overallStatus = false;
        } elseif ($freeMB < self::RECOMMENDED_DISK_SPACE_MB) {
            $level = 'warning';
            $message = "Espace disque limité: {$freeMB} MB (recommandé: " . self::RECOMMENDED_DISK_SPACE_MB . " MB)";
            $recommendation = "Il est recommandé d'avoir au moins " . self::RECOMMENDED_DISK_SPACE_MB . " MB d'espace libre";
        }
        
        $this->results['disk_space'] = [
            'name' => 'Espace disque',
            'free_mb' => $freeMB,
            'total_mb' => $totalMB,
            'usage_percent' => $usagePercent,
            'status' => $status,
            'level' => $level,
            'message' => $message,
            'recommendation' => $recommendation
        ];
    }
    
    /**
     * Teste la connexion à la base de données
     */
    public function checkDatabaseConnection($dbConfig) {
        try {
            $dsn = "mysql:host={$dbConfig['host']};charset=utf8mb4";
            if (!empty($dbConfig['name'])) {
                $dsn .= ";dbname={$dbConfig['name']}";
            }
            
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 10
            ]);
            
            // Test simple query
            $pdo->query("SELECT 1");
            
            $this->results['database'] = [
                'name' => 'Connexion base de données',
                'host' => $dbConfig['host'],
                'database' => $dbConfig['name'] ?? '',
                'status' => true,
                'level' => 'success',
                'message' => "Connexion réussie à {$dbConfig['host']}",
                'recommendation' => null
            ];
            
        } catch (PDOException $e) {
            $this->results['database'] = [
                'name' => 'Connexion base de données',
                'host' => $dbConfig['host'],
                'database' => $dbConfig['name'] ?? '',
                'status' => false,
                'level' => 'error',
                'message' => "Échec de connexion: " . $e->getMessage(),
                'recommendation' => $this->getDatabaseErrorRecommendation($e->getCode(), $e->getMessage())
            ];
            $this->overallStatus = false;
        }
    }
    
    /**
     * Retourne tous les résultats de vérification
     */
    public function getResults() {
        return [
            'overall_status' => $this->overallStatus,
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => $this->results,
            'summary' => $this->getSummary()
        ];
    }
    
    /**
     * Génère un résumé des vérifications
     */
    private function getSummary() {
        $total = 0;
        $passed = 0;
        $warnings = 0;
        $errors = 0;
        
        foreach ($this->results as $category => $checks) {
            if ($category === 'extensions' || $category === 'permissions') {
                foreach ($checks as $check) {
                    $total++;
                    if ($check['status']) {
                        $passed++;
                    } elseif ($check['level'] === 'warning') {
                        $warnings++;
                    } else {
                        $errors++;
                    }
                }
            } else {
                $total++;
                if ($checks['status']) {
                    $passed++;
                } elseif ($checks['level'] === 'warning') {
                    $warnings++;
                } else {
                    $errors++;
                }
            }
        }
        
        return [
            'total' => $total,
            'passed' => $passed,
            'warnings' => $warnings,
            'errors' => $errors,
            'success_rate' => $total > 0 ? round(($passed / $total) * 100, 1) : 0
        ];
    }
    
    /**
     * Recommandations d'installation pour les extensions PHP
     */
    private function getExtensionInstallRecommendation($extension) {
        $recommendations = [
            'mysqli' => 'Installez l\'extension MySQL: sudo apt-get install php-mysql ou activez dans php.ini',
            'json' => 'Installez l\'extension JSON: sudo apt-get install php-json (généralement incluse)',
            'mbstring' => 'Installez l\'extension mbstring: sudo apt-get install php-mbstring',
            'curl' => 'Installez l\'extension cURL: sudo apt-get install php-curl',
            'openssl' => 'Installez l\'extension OpenSSL: sudo apt-get install php-openssl (généralement incluse)',
            'gd' => 'Installez l\'extension GD: sudo apt-get install php-gd'
        ];
        
        return $recommendations[$extension] ?? "Installez l'extension PHP $extension";
    }
    
    /**
     * Recommandations pour les erreurs de base de données
     */
    private function getDatabaseErrorRecommendation($code, $message) {
        if (strpos($message, 'Access denied') !== false) {
            return 'Vérifiez les identifiants de connexion (nom d\'utilisateur/mot de passe)';
        }
        
        if (strpos($message, 'Unknown database') !== false) {
            return 'Vérifiez que la base de données existe ou créez-la';
        }
        
        if (strpos($message, 'Connection refused') !== false || strpos($message, 'timed out') !== false) {
            return 'Vérifiez que le serveur de base de données est démarré et accessible';
        }
        
        if (strpos($message, 'Unknown MySQL server host') !== false) {
            return 'Vérifiez l\'adresse du serveur de base de données';
        }
        
        return 'Contactez votre hébergeur ou administrateur système pour résoudre ce problème de base de données';
    }
    
    /**
     * Vérifie si tous les prérequis critiques sont remplis
     */
    public function areRequirementsMet() {
        return $this->overallStatus;
    }
    
    /**
     * Retourne uniquement les erreurs critiques
     */
    public function getCriticalErrors() {
        $errors = [];
        
        foreach ($this->results as $category => $checks) {
            if ($category === 'extensions' || $category === 'permissions') {
                foreach ($checks as $name => $check) {
                    if (!$check['status'] && $check['level'] === 'error') {
                        $errors[] = $check;
                    }
                }
            } else {
                if (!$checks['status'] && $checks['level'] === 'error') {
                    $errors[] = $checks;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Retourne uniquement les avertissements
     */
    public function getWarnings() {
        $warnings = [];
        
        foreach ($this->results as $category => $checks) {
            if ($category === 'extensions' || $category === 'permissions') {
                foreach ($checks as $name => $check) {
                    if ($check['level'] === 'warning') {
                        $warnings[] = $check;
                    }
                }
            } else {
                if ($checks['level'] === 'warning') {
                    $warnings[] = $checks;
                }
            }
        }
        
        return $warnings;
    }
}