# EventManager Module

## Overview
The EventManager module provides comprehensive event logging, monitoring, and notification capabilities for the N3XT WEB system.

## Features
- **Event Logging**: Record system events with categorization and severity levels
- **Event Monitoring**: Real-time monitoring of system activities
- **Event Analytics**: Statistical analysis of event patterns
- **Automatic Cleanup**: Configurable retention and archival of old events
- **Critical Notifications**: Immediate alerts for critical system events
- **Webhook Integration**: Send events to external systems

## Configuration
Module configuration is stored in the `{prefix}event_config` table:

| Setting | Default | Description |
|---------|---------|-------------|
| `event_logging_enabled` | 1 | Enable/disable event logging |
| `event_retention_days` | 90 | Days to keep event logs |
| `event_critical_notification` | 1 | Send notifications for critical events |
| `event_debug_mode` | 0 | Enable debug logging |
| `event_max_log_size_mb` | 50 | Maximum log size before archival |
| `event_auto_archive` | 1 | Automatically archive old events |
| `event_webhook_enabled` | 0 | Enable webhook notifications |

## Event Types
- `LOGIN` - Authentication events
- `LOGOUT` - Session termination events  
- `UPDATE` - System update events
- `BACKUP` - Backup operation events
- `MAINTENANCE` - System maintenance events
- `SECURITY` - Security-related events
- `ERROR` - System errors
- `SYSTEM` - General system events

## Event Categories
- `authentication` - Login/logout activities
- `system` - System operations
- `security` - Security incidents
- `maintenance` - Maintenance activities
- `user_action` - User-initiated actions

## Severity Levels
- `DEBUG` - Debug information
- `INFO` - Informational messages
- `WARNING` - Warning conditions
- `ERROR` - Error conditions
- `CRITICAL` - Critical system issues

## Usage

### Basic Event Logging
```php
$eventManager = EventManager::getInstance();
$eventManager->logEvent(
    EventManager::EVENT_TYPE_LOGIN,
    EventManager::CATEGORY_AUTHENTICATION,
    'User logged in successfully',
    ['username' => 'admin'],
    EventManager::SEVERITY_INFO
);
```

### Retrieving Events
```php
$events = $eventManager->getEvents([
    'type' => 'LOGIN',
    'severity' => 'ERROR'
], 50, 0);
```

### Event Statistics
```php
$stats = $eventManager->getEventStats(7); // Last 7 days
```

## Database Schema
The module uses the following tables:
- `{prefix}events` - Main events table
- `{prefix}event_config` - Module configuration

## Integration
The EventManager integrates with:
- SecurityManager (security event logging)
- NotificationManager (critical event alerts)
- MaintenanceManager (cleanup operations)

## Administration
Event management is available through the back office at `/bo/events.php` (when implemented).

## Migration
Module migrations are tracked in the `{prefix}module_migrations` table.