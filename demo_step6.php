<?php
// Demo page showing step 6 with our improvements
session_start();

// Simulate installation completion
$_SESSION['bo_directory'] = 'bo-demo123456';
$_SESSION['admin_username'] = 'admin';
$_SESSION['installation_ready'] = true;
$language = 'en';

// Define security constant
define('IN_N3XTWEB', true);

// Include functions for LanguageHelper
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>N3XT WEB Installation Complete - Demo</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .installation-complete {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        .success-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 28px;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: left;
        }
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 10px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #545b62;
            transform: translateY(-2px);
        }
        code {
            background-color: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #e83e8c;
        }
        ul {
            text-align: left;
            margin: 10px 0;
        }
        li {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="installation-complete">
        <div class="success-icon">🎉</div>
        <h2><?php echo LanguageHelper::get('installation_success', $language); ?></h2>
        
        <div class="alert alert-success">
            <p><?php echo LanguageHelper::get('check_email', $language); ?></p>
            <p><?php echo LanguageHelper::get('maintenance_mode_enabled', $language); ?></p>
        </div>
        
        <div class="alert alert-warning">
            <strong>🔒 Security Information:</strong>
            <ul style="text-align: left; margin: 10px 0;">
                <li>Back Office directory: <code><?php echo htmlspecialchars($_SESSION['bo_directory'] ?? 'bo'); ?></code></li>
                <li>Installation file will be automatically removed when you access the admin panel</li>
                <li>Enable HTTPS if possible</li>
            </ul>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="?finish=1" class="btn btn-primary">
                Access Admin Panel & Complete Installation
            </a>
            <br><br>
            <a href="?reset=1" class="btn btn-secondary" style="background-color: #6c757d;">
                Reset Installation (if needed)
            </a>
        </div>
    </div>
</body>
</html>