<?php
/**
 * N3XT WEB - Modules Autoloader
 * 
 * Autoloader pour les modules du back office.
 * Charge automatiquement les classes des modules.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

class ModulesLoader {
    
    private static $loaded = false;
    private static $modules = [];
    
    /**
     * Initialise le chargeur de modules
     */
    public static function init() {
        if (self::$loaded) {
            return;
        }
        
        // Charger la classe de base
        require_once __DIR__ . '/BaseModule.php';
        
        // Charger la migration
        require_once __DIR__ . '/migration.php';
        
        // Charger tous les modules
        self::loadModule('UpdateManager');
        self::loadModule('NotificationManager');
        self::loadModule('BackupManager');
        self::loadModule('MaintenanceManager');
        
        self::$loaded = true;
    }
    
    /**
     * Charge un module spécifique
     */
    private static function loadModule($moduleName) {
        $modulePath = __DIR__ . "/{$moduleName}/classes/{$moduleName}.php";
        if (file_exists($modulePath)) {
            require_once $modulePath;
            self::$modules[] = $moduleName;
        }
    }
    
    /**
     * Retourne la liste des modules chargés
     */
    public static function getLoadedModules() {
        return self::$modules;
    }
    
    /**
     * Vérifie si un module est chargé
     */
    public static function isModuleLoaded($moduleName) {
        return in_array($moduleName, self::$modules);
    }
    
    /**
     * Applique la migration si nécessaire
     */
    public static function migrate() {
        $migration = new ModulesMigration();
        
        if (!$migration->isMigrated()) {
            return $migration->migrate();
        }
        
        return true;
    }
    
    /**
     * Retourne une instance d'un module
     */
    public static function getModule($moduleName) {
        if (!self::isModuleLoaded($moduleName)) {
            throw new Exception("Module {$moduleName} not loaded");
        }
        
        if (!class_exists($moduleName)) {
            throw new Exception("Module class {$moduleName} not found");
        }
        
        return new $moduleName();
    }
    
    /**
     * Retourne toutes les instances des modules actifs
     */
    public static function getActiveModules() {
        $activeModules = [];
        
        foreach (self::$modules as $moduleName) {
            try {
                $module = self::getModule($moduleName);
                if ($module->getStatus() === 'enabled') {
                    $activeModules[$moduleName] = $module;
                }
            } catch (Exception $e) {
                Logger::log("Failed to load module {$moduleName}: " . $e->getMessage(), LOG_LEVEL_WARNING);
            }
        }
        
        return $activeModules;
    }
}

// Auto-initialiser le chargeur
ModulesLoader::init();