# UpdateManager Module

## Overview
The UpdateManager module handles system updates, version management, and deployment processes for the N3XT WEB system.

## Features
- **Automatic Updates**: GitHub integration for automatic updates
- **Manual Updates**: ZIP upload functionality
- **Update Validation**: Pre-update system checks
- **Rollback Capability**: Automatic rollback on failure
- **Backup Integration**: Automatic backup before updates
- **Maintenance Mode**: System protection during updates
- **Update Notifications**: Email alerts for update completion
- **Version Tracking**: Comprehensive version history

## Configuration
Module configuration is stored in the `{prefix}update_config` table:

| Setting | Default | Description |
|---------|---------|-------------|
| `update_check_frequency` | 24 | Hours between update checks |
| `update_auto_backup` | 1 | Create backup before update |
| `update_github_owner` | gjai | GitHub repository owner |
| `update_github_repo` | n3xtweb | GitHub repository name |
| `update_maintenance_mode` | 1 | Enable maintenance during update |
| `update_notification_email` | '' | Email for notifications |
| `update_rollback_enabled` | 1 | Enable automatic rollback |
| `update_exclude_files` | config/config.php,uploads/ | Files to exclude |

## Administration
Update management is available through the back office at `/bo/update.php`.

## Database Schema
The module uses the `{prefix}update_config` table for configuration.

## Integration
Integrates with the existing update functionality in `/bo/update.php`.