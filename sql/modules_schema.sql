-- N3XT WEB - Modular Configuration Schema
-- This file contains the SQL schema for modular configuration tables
-- Each module has its own dedicated configuration table

-- Backup Module Configuration
CREATE TABLE IF NOT EXISTS {prefix}backup_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_backup_config (config_key)
);

-- Insert default backup configuration
INSERT IGNORE INTO {prefix}backup_config (config_key, config_value, description) VALUES
('backup_retention_days', '30', 'Number of days to keep backup files'),
('backup_compression_level', '6', 'ZIP compression level (0-9)'),
('backup_include_logs', '0', 'Include log files in backup (0=no, 1=yes)'),
('backup_max_size_mb', '500', 'Maximum backup file size in MB'),
('backup_auto_cleanup', '1', 'Automatically cleanup old backups (0=no, 1=yes)'),
('backup_exclude_patterns', 'tmp/,cache/,*.tmp', 'Comma-separated patterns to exclude from backup'),
('backup_notification_email', '', 'Email to notify on backup completion');

-- Update Module Configuration  
CREATE TABLE IF NOT EXISTS {prefix}update_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_update_config (config_key)
);

-- Insert default update configuration
INSERT IGNORE INTO {prefix}update_config (config_key, config_value, description) VALUES
('update_check_frequency', '24', 'Hours between update checks'),
('update_auto_backup', '1', 'Create backup before update (0=no, 1=yes)'),
('update_github_owner', 'gjai', 'GitHub repository owner'),
('update_github_repo', 'n3xtweb', 'GitHub repository name'),
('update_maintenance_mode', '1', 'Enable maintenance mode during update (0=no, 1=yes)'),
('update_notification_email', '', 'Email to notify on update completion'),
('update_rollback_enabled', '1', 'Enable automatic rollback on failure (0=no, 1=yes)'),
('update_exclude_files', 'config/config.php,uploads/', 'Files/folders to exclude from update');

-- Notification Module Configuration
CREATE TABLE IF NOT EXISTS {prefix}notification_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_notification_config (config_key)
);

-- Insert default notification configuration
INSERT IGNORE INTO {prefix}notification_config (config_key, config_value, description) VALUES
('notification_enabled', '1', 'Enable notification system (0=no, 1=yes)'),
('notification_email_enabled', '1', 'Enable email notifications (0=no, 1=yes)'),
('notification_admin_email', '', 'Administrator email for notifications'),
('notification_smtp_host', '', 'SMTP server host'),
('notification_smtp_port', '587', 'SMTP server port'),
('notification_smtp_user', '', 'SMTP username'),
('notification_smtp_pass', '', 'SMTP password'),
('notification_smtp_encryption', 'tls', 'SMTP encryption (tls, ssl, none)'),
('notification_from_name', 'N3XT WEB System', 'Email sender name');

-- Maintenance Module Configuration
CREATE TABLE IF NOT EXISTS {prefix}maintenance_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_maintenance_config (config_key)
);

-- Insert default maintenance configuration
INSERT IGNORE INTO {prefix}maintenance_config (config_key, config_value, description) VALUES
('maintenance_auto_enabled', '1', 'Enable automatic maintenance (0=no, 1=yes)'),
('maintenance_schedule_time', '02:00', 'Daily maintenance time (HH:MM)'),
('maintenance_log_cleanup_days', '7', 'Days to keep log files'),
('maintenance_temp_cleanup_enabled', '1', 'Clean temporary files (0=no, 1=yes)'),
('maintenance_cache_cleanup_enabled', '1', 'Clean cache files (0=no, 1=yes)'),
('maintenance_database_optimize', '1', 'Optimize database tables (0=no, 1=yes)'),
('maintenance_notification_enabled', '1', 'Send maintenance reports (0=no, 1=yes)'),
('maintenance_max_execution_time', '300', 'Maximum execution time in seconds');

-- Security Module Configuration
CREATE TABLE IF NOT EXISTS {prefix}security_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_security_config (config_key)
);

-- Insert default security configuration
INSERT IGNORE INTO {prefix}security_config (config_key, config_value, description) VALUES
('security_login_attempts_max', '5', 'Maximum login attempts before lockout'),
('security_lockout_duration', '900', 'Lockout duration in seconds'),
('security_session_timeout', '3600', 'Session timeout in seconds'),
('security_password_min_length', '8', 'Minimum password length'),
('security_password_complexity', '1', 'Require complex passwords (0=no, 1=yes)'),
('security_ip_whitelist', '', 'Comma-separated IP whitelist'),
('security_ip_blacklist', '', 'Comma-separated IP blacklist'),
('security_captcha_enabled', '0', 'Enable CAPTCHA (0=no, 1=yes)'),
('security_two_factor_enabled', '0', 'Enable two-factor authentication (0=no, 1=yes)'),
('security_audit_logging', '1', 'Enable security audit logging (0=no, 1=yes)');

-- Event Manager Module Configuration
CREATE TABLE IF NOT EXISTS {prefix}event_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_event_config (config_key)
);

-- Insert default event configuration
INSERT IGNORE INTO {prefix}event_config (config_key, config_value, description) VALUES
('event_logging_enabled', '1', 'Enable event logging (0=no, 1=yes)'),
('event_retention_days', '90', 'Days to keep event logs'),
('event_critical_notification', '1', 'Notify on critical events (0=no, 1=yes)'),
('event_debug_mode', '0', 'Enable debug event logging (0=no, 1=yes)'),
('event_max_log_size_mb', '50', 'Maximum event log size in MB'),
('event_auto_archive', '1', 'Auto-archive old events (0=no, 1=yes)'),
('event_webhook_url', '', 'Webhook URL for event notifications'),
('event_webhook_enabled', '0', 'Enable webhook notifications (0=no, 1=yes)');

-- Event Manager Events Table
CREATE TABLE IF NOT EXISTS {prefix}events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    event_category VARCHAR(50) NOT NULL,
    event_message TEXT NOT NULL,
    event_data JSON,
    severity ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL') DEFAULT 'INFO',
    user_id INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_category (event_category),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at),
    INDEX idx_user_ip (user_id, ip_address)
);

-- Module Migration Tracking
CREATE TABLE IF NOT EXISTS {prefix}module_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_name VARCHAR(50) NOT NULL,
    migration_version VARCHAR(20) NOT NULL,
    migration_file VARCHAR(255) NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_module_migration (module_name, migration_version)
);

-- Insert initial migration records
INSERT IGNORE INTO {prefix}module_migrations (module_name, migration_version, migration_file) VALUES
('backup', '1.0.0', 'modules_schema.sql'),
('update', '1.0.0', 'modules_schema.sql'),
('notification', '1.0.0', 'modules_schema.sql'),
('maintenance', '1.0.0', 'modules_schema.sql'),
('security', '1.0.0', 'modules_schema.sql'),
('event', '1.0.0', 'modules_schema.sql');