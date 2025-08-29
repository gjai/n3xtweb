# MaintenanceManager Module

## Overview
The MaintenanceManager module provides automated system maintenance, cleanup operations, and system optimization for the N3XT WEB system.

## Features
- **Scheduled Maintenance**: Automated daily maintenance tasks
- **Log Management**: Automatic log cleanup and archival
- **Temporary File Cleanup**: Clean temporary and cache files
- **Database Optimization**: Optimize database tables
- **System Monitoring**: Monitor system health and performance
- **Maintenance Reports**: Email reports on maintenance activities
- **Configurable Schedule**: Flexible maintenance timing

## Configuration
Module configuration is stored in the `{prefix}maintenance_config` table:

| Setting | Default | Description |
|---------|---------|-------------|
| `maintenance_auto_enabled` | 1 | Enable automatic maintenance |
| `maintenance_schedule_time` | 02:00 | Daily maintenance time (HH:MM) |
| `maintenance_log_cleanup_days` | 7 | Days to keep log files |
| `maintenance_temp_cleanup_enabled` | 1 | Clean temporary files |
| `maintenance_cache_cleanup_enabled` | 1 | Clean cache files |
| `maintenance_database_optimize` | 1 | Optimize database tables |
| `maintenance_notification_enabled` | 1 | Send maintenance reports |
| `maintenance_max_execution_time` | 300 | Maximum execution time in seconds |

## Administration
Maintenance management is available through the back office at `/bo/maintenance.php`.

## Database Schema
The module uses the `{prefix}maintenance_config` table for configuration.

## Integration
Integrates with existing maintenance functionality and auto_maintenance.php.