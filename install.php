<?php
/**
 * N3XT Communication - Installation Interface
 * 
 * Initial system setup with database configuration and admin account creation.
 */

// Check if system is already installed
if (file_exists('config/config.php')) {
    $config = include 'config/config.php';
    if (defined('DB_HOST') && DB_HOST !== 'localhost') {
        header('Location: admin/login.php');
        exit;
    }
}

require_once 'includes/functions.php';

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (int) ($_POST['step'] ?? 1);
    
    switch ($step) {
        case 1:
            // Environment check passed, go to step 2
            $step = 2;
            break;
            
        case 2:
            // Database configuration
            $dbHost = Security::sanitizeInput($_POST['db_host'] ?? '');
            $dbName = Security::sanitizeInput($_POST['db_name'] ?? '');
            $dbUser = Security::sanitizeInput($_POST['db_user'] ?? '');
            $dbPass = $_POST['db_pass'] ?? '';
            
            if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
                $error = 'Please fill in all database fields.';
            } else {
                try {
                    // Test database connection
                    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
                    $testPdo = new PDO($dsn, $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    
                    // Store database configuration in session
                    $_SESSION['db_config'] = [
                        'host' => $dbHost,
                        'name' => $dbName,
                        'user' => $dbUser,
                        'pass' => $dbPass
                    ];
                    
                    $step = 3;
                    $success = 'Database connection successful!';
                    
                } catch (PDOException $e) {
                    $error = 'Database connection failed: ' . $e->getMessage();
                }
            }
            break;
            
        case 3:
            // Admin account creation
            $adminUser = Security::sanitizeInput($_POST['admin_user'] ?? '');
            $adminPass = $_POST['admin_pass'] ?? '';
            $adminPassConfirm = $_POST['admin_pass_confirm'] ?? '';
            
            if (empty($adminUser) || empty($adminPass)) {
                $error = 'Please fill in all admin account fields.';
            } elseif (strlen($adminPass) < 8) {
                $error = 'Password must be at least 8 characters long.';
            } elseif ($adminPass !== $adminPassConfirm) {
                $error = 'Passwords do not match.';
            } else {
                try {
                    // Create configuration file
                    $dbConfig = $_SESSION['db_config'];
                    $configContent = self::generateConfigFile($dbConfig);
                    
                    if (!file_put_contents('config/config.php', $configContent)) {
                        throw new Exception('Failed to write configuration file');
                    }
                    
                    // Create database tables
                    self::createDatabaseTables($dbConfig);
                    
                    // Create admin user
                    self::createAdminUser($dbConfig, $adminUser, $adminPass);
                    
                    // Create initial directories
                    $dirs = ['backups', 'logs', 'uploads'];
                    foreach ($dirs as $dir) {
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                    }
                    
                    $step = 4;
                    $success = 'Installation completed successfully!';
                    
                    // Clear session data
                    unset($_SESSION['db_config']);
                    
                } catch (Exception $e) {
                    $error = 'Installation failed: ' . $e->getMessage();
                }
            }
            break;
    }
}

/**
 * Generate configuration file content
 */
function generateConfigFile($dbConfig) {
    $template = file_get_contents('config/config.php');
    
    $replacements = [
        "'localhost'" => "'{$dbConfig['host']}'",
        "'n3xt_communication'" => "'{$dbConfig['name']}'",
        "'n3xt_user'" => "'{$dbConfig['user']}'",
        "'secure_password'" => "'{$dbConfig['pass']}'"
    ];
    
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

/**
 * Create database tables
 */
function createDatabaseTables($dbConfig) {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Create admin users table
    $sql = "
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            active BOOLEAN DEFAULT TRUE
        )
    ";
    $pdo->exec($sql);
    
    // Create system settings table
    $sql = "
        CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($sql);
    
    // Insert default settings
    $settings = [
        ['maintenance_mode', '0'],
        ['system_version', SYSTEM_VERSION],
        ['install_date', date('Y-m-d H:i:s')]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
}

/**
 * Create admin user
 */
function createAdminUser($dbConfig, $username, $password) {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $passwordHash = Security::hashPassword($password);
    
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = ?");
    $stmt->execute([$username, $passwordHash, $passwordHash]);
}

/**
 * Check system requirements
 */
function checkSystemRequirements() {
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'GD Extension' => extension_loaded('gd'),
        'ZIP Extension' => extension_loaded('zip'),
        'OpenSSL Extension' => extension_loaded('openssl'),
        'Config Directory Writable' => is_writable('config'),
        'Logs Directory Writable' => is_writable('logs') || !file_exists('logs'),
        'Backups Directory Writable' => is_writable('backups') || !file_exists('backups')
    ];
    
    return $requirements;
}

$requirements = checkSystemRequirements();
$allRequirementsMet = !in_array(false, $requirements);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>N3XT Communication - Installation</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>N3XT Communication</h1>
            <p style="margin-top: 10px; opacity: 0.9;">System Installation</p>
        </div>
        
        <div class="main-content">
            <div class="card" style="max-width: 600px; margin: 0 auto;">
                
                <!-- Step Progress -->
                <div style="padding: 20px; border-bottom: 1px solid #ecf0f1;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <div style="text-align: center; flex: 1;">
                                <div style="
                                    width: 30px; 
                                    height: 30px; 
                                    border-radius: 50%; 
                                    margin: 0 auto 5px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    color: white;
                                    font-weight: bold;
                                    background: <?php echo $step >= $i ? '#3498db' : '#bdc3c7'; ?>;
                                "><?php echo $i; ?></div>
                                <div style="font-size: 12px; color: #7f8c8d;">
                                    <?php 
                                    $stepNames = ['Requirements', 'Database', 'Admin Account', 'Complete'];
                                    echo $stepNames[$i-1];
                                    ?>
                                </div>
                            </div>
                            <?php if ($i < 4): ?>
                                <div style="flex: 1; height: 2px; background: <?php echo $step > $i ? '#3498db' : '#bdc3c7'; ?>; margin: 0 10px;"></div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($step == 1): ?>
                        <!-- Step 1: System Requirements -->
                        <h2>System Requirements Check</h2>
                        <p>Please ensure all requirements are met before proceeding.</p>
                        
                        <div class="table-responsive">
                            <table class="table">
                                <?php foreach ($requirements as $requirement => $status): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($requirement); ?></td>
                                        <td>
                                            <span style="
                                                padding: 2px 8px; 
                                                border-radius: 3px; 
                                                font-size: 12px;
                                                background: <?php echo $status ? '#27ae60' : '#e74c3c'; ?>;
                                                color: white;
                                            ">
                                                <?php echo $status ? 'OK' : 'FAILED'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                        
                        <?php if ($allRequirementsMet): ?>
                            <form method="POST">
                                <input type="hidden" name="step" value="1">
                                <button type="submit" class="btn btn-primary btn-block">Continue</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-error">
                                Please fix the failed requirements before continuing.
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif ($step == 2): ?>
                        <!-- Step 2: Database Configuration -->
                        <h2>Database Configuration</h2>
                        <p>Configure your MySQL database connection.</p>
                        
                        <form method="POST">
                            <input type="hidden" name="step" value="2">
                            
                            <div class="form-group">
                                <label for="db_host" class="form-label">Database Host:</label>
                                <input type="text" 
                                       id="db_host" 
                                       name="db_host" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="db_name" class="form-label">Database Name:</label>
                                <input type="text" 
                                       id="db_name" 
                                       name="db_name" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'n3xt_communication'); ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="db_user" class="form-label">Database Username:</label>
                                <input type="text" 
                                       id="db_user" 
                                       name="db_user" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="db_pass" class="form-label">Database Password:</label>
                                <input type="password" 
                                       id="db_pass" 
                                       name="db_pass" 
                                       class="form-control">
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">Test Connection</button>
                        </form>
                        
                    <?php elseif ($step == 3): ?>
                        <!-- Step 3: Admin Account -->
                        <h2>Create Admin Account</h2>
                        <p>Create the administrator account for the back office.</p>
                        
                        <form method="POST">
                            <input type="hidden" name="step" value="3">
                            
                            <div class="form-group">
                                <label for="admin_user" class="form-label">Admin Username:</label>
                                <input type="text" 
                                       id="admin_user" 
                                       name="admin_user" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['admin_user'] ?? 'admin'); ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="admin_pass" class="form-label">Admin Password:</label>
                                <input type="password" 
                                       id="admin_pass" 
                                       name="admin_pass" 
                                       class="form-control"
                                       minlength="8"
                                       required>
                                <small style="color: #7f8c8d;">Minimum 8 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="admin_pass_confirm" class="form-label">Confirm Password:</label>
                                <input type="password" 
                                       id="admin_pass_confirm" 
                                       name="admin_pass_confirm" 
                                       class="form-control"
                                       required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">Complete Installation</button>
                        </form>
                        
                    <?php elseif ($step == 4): ?>
                        <!-- Step 4: Complete -->
                        <h2>Installation Complete!</h2>
                        
                        <div class="alert alert-success">
                            <strong>Congratulations!</strong> N3XT Communication has been successfully installed.
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3>What's Next?</h3>
                            <ul style="margin: 10px 0; padding-left: 20px;">
                                <li>Access the admin panel to configure your system</li>
                                <li>Create regular backups of your data</li>
                                <li>Keep your system updated with the latest releases</li>
                                <li>Review security settings and logs regularly</li>
                            </ul>
                        </div>
                        
                        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;">
                            <h4>Security Recommendations:</h4>
                            <ul style="margin: 10px 0; padding-left: 20px; font-size: 14px;">
                                <li>Remove or restrict access to this installation file</li>
                                <li>Ensure your .htaccess file is properly configured</li>
                                <li>Use strong passwords for all accounts</li>
                                <li>Keep your database credentials secure</li>
                                <li>Enable HTTPS if possible</li>
                            </ul>
                        </div>
                        
                        <div style="text-align: center; margin-top: 30px;">
                            <a href="admin/login.php" class="btn btn-primary">Access Admin Panel</a>
                        </div>
                        
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Password confirmation validation
        <?php if ($step == 3): ?>
        document.getElementById('admin_pass_confirm').addEventListener('input', function() {
            const password = document.getElementById('admin_pass').value;
            const confirm = this.value;
            
            if (password && confirm) {
                if (password === confirm) {
                    this.style.borderColor = '#27ae60';
                } else {
                    this.style.borderColor = '#e74c3c';
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>