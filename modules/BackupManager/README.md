# BackupManager Module

## Overview
The BackupManager module provides comprehensive backup and restore functionality for the N3XT WEB system.

## Features
- **Automated Backups**: Scheduled automatic backup creation
- **Manual Backups**: On-demand backup generation
- **Selective Backup**: Choose specific components to backup
- **Backup Validation**: Verify backup integrity
- **Restore Operations**: Full and selective restore capabilities
- **Backup Compression**: Configurable compression levels
- **Retention Management**: Automatic cleanup of old backups
- **Email Notifications**: Backup completion notifications

## Configuration
Module configuration is stored in the `{prefix}backup_config` table:

| Setting | Default | Description |
|---------|---------|-------------|
| `backup_retention_days` | 30 | Days to keep backup files |
| `backup_compression_level` | 6 | ZIP compression level (0-9) |
| `backup_include_logs` | 0 | Include log files in backup |
| `backup_max_size_mb` | 500 | Maximum backup file size in MB |
| `backup_auto_cleanup` | 1 | Automatically cleanup old backups |
| `backup_exclude_patterns` | tmp/,cache/,*.tmp | Patterns to exclude |
| `backup_notification_email` | '' | Email for notifications |

## Administration
Backup management is available through the back office at `/bo/restore.php`.

## Database Schema
The module uses the `{prefix}backup_config` table for configuration.

## Integration
Integrates with the existing backup and restore functionality in `/bo/restore.php`.