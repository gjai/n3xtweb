# NotificationManager Module

## Overview
The NotificationManager module provides email notification capabilities and messaging services for the N3XT WEB system.

## Features
- **Email Notifications**: SMTP-based email delivery
- **Template System**: Customizable email templates
- **Queue Management**: Email queue processing
- **Delivery Tracking**: Email delivery status monitoring
- **Multiple Recipients**: Support for multiple notification targets
- **Event Integration**: Automatic notifications for system events
- **Configuration Management**: Flexible SMTP configuration

## Configuration
Module configuration is stored in the `{prefix}notification_config` table:

| Setting | Default | Description |
|---------|---------|-------------|
| `notification_enabled` | 1 | Enable notification system |
| `notification_email_enabled` | 1 | Enable email notifications |
| `notification_admin_email` | '' | Administrator email |
| `notification_smtp_host` | '' | SMTP server host |
| `notification_smtp_port` | 587 | SMTP server port |
| `notification_smtp_user` | '' | SMTP username |
| `notification_smtp_pass` | '' | SMTP password |
| `notification_smtp_encryption` | tls | SMTP encryption type |
| `notification_from_name` | N3XT WEB System | Email sender name |

## Administration
Notification management will be available through the back office.

## Database Schema
The module uses the `{prefix}notification_config` table for configuration.

## Integration
Integrates with EventManager for automatic event notifications.