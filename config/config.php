<?php
/**
 * N3XT WEB - Configuration File
 * 
 * This file will be automatically generated during installation.
 * If you see this message, the system has not been installed yet.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

// Check if system is installed - if this file still contains placeholder values, redirect to install
if (!file_exists(__DIR__ . '/.installed')) {
    // System not installed, redirect to installation
    if (php_sapi_name() !== 'cli') {
        header('Location: ' . dirname($_SERVER['SCRIPT_NAME']) . '/install.php');
        exit('System not installed. Please run the installation process.');
    } else {
        exit('System not installed. Please run the installation process via web interface.');
    }
}

// If we reach here, the config should have been generated during installation
// This should not happen in a properly installed system
exit('Configuration file corrupted. Please reinstall the system.');