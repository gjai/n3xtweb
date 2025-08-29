<?php
/**
 * N3XT WEB - NotificationManager Module
 * 
 * Système de notifications pour le back office.
 * Gère les notifications visuelles et par email.
 */

// Prevent direct access
if (!defined('IN_N3XTWEB')) {
    exit('Direct access not allowed');
}

class NotificationManager extends BaseModule {
    
    public function __construct() {
        parent::__construct('notificationmanager');
        $this->initialize();
    }
    
    /**
     * Configuration par défaut du module
     */
    protected function getDefaultConfiguration() {
        return [
            'enabled' => true,
            'email_enabled' => true,
            'visual_enabled' => true,
            'retention_days' => 30,
            'email_template' => 'default',
            'max_notifications_per_user' => 100,
            'auto_email_types' => 'update,backup,maintenance,system',
            'version' => '1.0.0',
            'description' => 'Système de notifications pour le back office'
        ];
    }
    
    /**
     * Initialise le module
     */
    public function initialize() {
        // Nettoyer les anciennes notifications si activé
        if ($this->getConfig('enabled', true)) {
            $this->cleanupOldNotifications();
        }
    }
    
    /**
     * Crée une nouvelle notification
     */
    public function createNotification($type, $title, $message, $priority = 'medium', $data = null, $targetUser = null, $expiresAt = null) {
        try {
            $this->checkPermissions();
            
            // Valider les paramètres
            $validTypes = ['update', 'backup', 'maintenance', 'system', 'warning', 'error'];
            $validPriorities = ['low', 'medium', 'high', 'critical'];
            
            if (!in_array($type, $validTypes)) {
                throw new Exception("Invalid notification type: {$type}");
            }
            
            if (!in_array($priority, $validPriorities)) {
                throw new Exception("Invalid notification priority: {$priority}");
            }
            
            // Nettoyer les données
            $title = $this->sanitizeInput($title);
            $message = $this->sanitizeInput($message);
            $targetUser = $targetUser ? $this->sanitizeInput($targetUser) : null;
            
            // Préparer les données JSON
            $jsonData = $data ? json_encode($data) : null;
            
            // Insérer la notification
            $sql = "INSERT INTO " . Logger::getTablePrefix() . "notifications 
                    (type, title, message, priority, target_user, data, expires_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $this->db->execute($sql, [
                $type, $title, $message, $priority, $targetUser, $jsonData, $expiresAt
            ]);
            
            $notificationId = $this->db->getLastInsertId();
            
            $this->logAction('Notification created', "ID: {$notificationId}, Type: {$type}, Priority: {$priority}");
            
            // Envoyer par email si activé
            if ($this->shouldSendEmail($type)) {
                $this->sendEmailNotification($notificationId, $type, $title, $message, $priority, $targetUser);
            }
            
            return [
                'success' => true,
                'notification_id' => $notificationId
            ];
            
        } catch (Exception $e) {
            $this->logAction('Failed to create notification', $e->getMessage(), LOG_LEVEL_ERROR);
            throw $e;
        }
    }
    
    /**
     * Vérifie si un email doit être envoyé pour ce type de notification
     */
    private function shouldSendEmail($type) {
        if (!$this->getConfig('email_enabled', true)) {
            return false;
        }
        
        $autoEmailTypes = explode(',', $this->getConfig('auto_email_types', 'update,backup,maintenance,system'));
        return in_array($type, $autoEmailTypes);
    }
    
    /**
     * Envoie une notification par email
     */
    private function sendEmailNotification($notificationId, $type, $title, $message, $priority, $targetUser = null) {
        try {
            // Obtenir l'email de l'administrateur
            $adminEmail = Configuration::get('admin_email', '');
            if (empty($adminEmail)) {
                $this->logAction('Email notification skipped', 'No admin email configured', LOG_LEVEL_WARNING);
                return false;
            }
            
            // Préparer le contenu de l'email
            $emailSubject = "[N3XT WEB] {$title}";
            $emailBody = $this->buildEmailBody($type, $title, $message, $priority);
            
            // Paramètres SMTP
            $smtpHost = Configuration::get('smtp_host', '');
            $smtpPort = Configuration::get('smtp_port', 587);
            $smtpUser = Configuration::get('smtp_user', '');
            $smtpPass = Configuration::get('smtp_pass', '');
            $smtpFrom = Configuration::get('smtp_from', $adminEmail);
            $smtpFromName = Configuration::get('smtp_from_name', 'N3XT WEB');
            
            // Envoyer l'email
            if (!empty($smtpHost) && !empty($smtpUser)) {
                // Utiliser SMTP
                $emailSent = $this->sendSMTPEmail(
                    $adminEmail, $emailSubject, $emailBody, 
                    $smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpFromName
                );
            } else {
                // Utiliser mail() PHP
                $headers = [
                    'From: ' . $smtpFromName . ' <' . $smtpFrom . '>',
                    'Reply-To: ' . $smtpFrom,
                    'X-Mailer: N3XT WEB NotificationManager',
                    'Content-Type: text/html; charset=UTF-8'
                ];
                
                $emailSent = mail($adminEmail, $emailSubject, $emailBody, implode("\r\n", $headers));
            }
            
            // Mettre à jour le statut d'envoi
            if ($emailSent) {
                $this->db->execute(
                    "UPDATE " . Logger::getTablePrefix() . "notifications SET email_sent = 1 WHERE id = ?",
                    [$notificationId]
                );
                
                $this->logAction('Email notification sent', "To: {$adminEmail}, Subject: {$emailSubject}");
            } else {
                $this->logAction('Email notification failed', "To: {$adminEmail}", LOG_LEVEL_WARNING);
            }
            
            return $emailSent;
            
        } catch (Exception $e) {
            $this->logAction('Email notification error', $e->getMessage(), LOG_LEVEL_ERROR);
            return false;
        }
    }
    
    /**
     * Construit le corps de l'email
     */
    private function buildEmailBody($type, $title, $message, $priority) {
        $priorityColors = [
            'low' => '#28a745',
            'medium' => '#ffc107', 
            'high' => '#fd7e14',
            'critical' => '#dc3545'
        ];
        
        $priorityLabels = [
            'low' => 'Faible',
            'medium' => 'Moyenne',
            'high' => 'Élevée', 
            'critical' => 'Critique'
        ];
        
        $typeLabels = [
            'update' => 'Mise à jour',
            'backup' => 'Sauvegarde',
            'maintenance' => 'Maintenance',
            'system' => 'Système',
            'warning' => 'Avertissement',
            'error' => 'Erreur'
        ];
        
        $color = $priorityColors[$priority] ?? '#6c757d';
        $priorityLabel = $priorityLabels[$priority] ?? 'Inconnue';
        $typeLabel = $typeLabels[$type] ?? 'Notification';
        
        $siteName = Configuration::get('site_name', 'N3XT WEB');
        $timestamp = date('d/m/Y à H:i:s');
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>{$title}</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;'>
                <div style='background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                    <div style='text-align: center; margin-bottom: 30px;'>
                        <h1 style='color: #343a40; margin: 0;'>{$siteName}</h1>
                        <p style='color: #6c757d; margin: 5px 0 0 0;'>Notification Back Office</p>
                    </div>
                    
                    <div style='border-left: 4px solid {$color}; padding-left: 20px; margin-bottom: 25px;'>
                        <h2 style='color: {$color}; margin: 0 0 10px 0;'>{$title}</h2>
                        <p style='margin: 0; font-size: 14px; color: #6c757d;'>
                            <strong>Type:</strong> {$typeLabel} | 
                            <strong>Priorité:</strong> {$priorityLabel} | 
                            <strong>Date:</strong> {$timestamp}
                        </p>
                    </div>
                    
                    <div style='background-color: #f8f9fa; padding: 20px; border-radius: 4px; margin-bottom: 25px;'>
                        <p style='margin: 0; font-size: 16px; line-height: 1.5;'>{$message}</p>
                    </div>
                    
                    <div style='text-align: center; margin-top: 30px;'>
                        <a href='" . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . "/bo/index.php' 
                           style='display: inline-block; background-color: #007bff; color: white; text-decoration: none; 
                                  padding: 12px 24px; border-radius: 4px; font-weight: bold;'>
                            Accéder au Back Office
                        </a>
                    </div>
                    
                    <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center;'>
                        <p style='margin: 0; font-size: 12px; color: #6c757d;'>
                            Cet email a été généré automatiquement par le système N3XT WEB.<br>
                            Pour modifier vos préférences de notification, connectez-vous au back office.
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Envoie un email via SMTP
     */
    private function sendSMTPEmail($to, $subject, $body, $host, $port, $user, $pass, $from, $fromName) {
        // Implémentation SMTP basique
        // Pour une implémentation complète, utiliser PHPMailer ou SwiftMailer
        
        try {
            $socket = fsockopen($host, $port, $errno, $errstr, 30);
            if (!$socket) {
                throw new Exception("Cannot connect to SMTP server: {$errstr}");
            }
            
            // Lire la réponse du serveur
            $this->readSMTPResponse($socket);
            
            // EHLO
            fwrite($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
            $this->readSMTPResponse($socket);
            
            // STARTTLS si nécessaire
            if ($port == 587) {
                fwrite($socket, "STARTTLS\r\n");
                $this->readSMTPResponse($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                
                fwrite($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
                $this->readSMTPResponse($socket);
            }
            
            // AUTH LOGIN
            fwrite($socket, "AUTH LOGIN\r\n");
            $this->readSMTPResponse($socket);
            
            fwrite($socket, base64_encode($user) . "\r\n");
            $this->readSMTPResponse($socket);
            
            fwrite($socket, base64_encode($pass) . "\r\n");
            $this->readSMTPResponse($socket);
            
            // MAIL FROM
            fwrite($socket, "MAIL FROM: <{$from}>\r\n");
            $this->readSMTPResponse($socket);
            
            // RCPT TO
            fwrite($socket, "RCPT TO: <{$to}>\r\n");
            $this->readSMTPResponse($socket);
            
            // DATA
            fwrite($socket, "DATA\r\n");
            $this->readSMTPResponse($socket);
            
            // Headers et corps
            $headers = "From: {$fromName} <{$from}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "X-Mailer: N3XT WEB NotificationManager\r\n";
            $headers .= "\r\n";
            
            fwrite($socket, $headers . $body . "\r\n.\r\n");
            $this->readSMTPResponse($socket);
            
            // QUIT
            fwrite($socket, "QUIT\r\n");
            $this->readSMTPResponse($socket);
            
            fclose($socket);
            return true;
            
        } catch (Exception $e) {
            $this->logAction('SMTP error', $e->getMessage(), LOG_LEVEL_ERROR);
            return false;
        }
    }
    
    /**
     * Lit une réponse SMTP
     */
    private function readSMTPResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 256)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    }
    
    /**
     * Récupère les notifications pour un utilisateur
     */
    public function getNotifications($targetUser = null, $status = null, $limit = 50) {
        try {
            $where = ['1=1'];
            $params = [];
            
            // Filtrer par utilisateur (null = toutes les notifications globales)
            if ($targetUser !== null) {
                $where[] = '(target_user = ? OR target_user IS NULL)';
                $params[] = $targetUser;
            } else {
                $where[] = 'target_user IS NULL';
            }
            
            // Filtrer par statut
            if ($status !== null) {
                $where[] = 'status = ?';
                $params[] = $status;
            }
            
            // Exclure les notifications expirées
            $where[] = '(expires_at IS NULL OR expires_at > NOW())';
            
            $sql = "SELECT * FROM " . Logger::getTablePrefix() . "notifications 
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY priority DESC, created_at DESC 
                    LIMIT ?";
            
            $params[] = $limit;
            
            $notifications = $this->db->fetchAll($sql, $params);
            
            // Décoder les données JSON
            foreach ($notifications as &$notification) {
                $notification['data'] = $notification['data'] ? json_decode($notification['data'], true) : null;
            }
            
            return $notifications;
            
        } catch (Exception $e) {
            $this->logAction('Failed to fetch notifications', $e->getMessage(), LOG_LEVEL_ERROR);
            return [];
        }
    }
    
    /**
     * Marque une notification comme lue
     */
    public function markAsRead($notificationId, $userId = null) {
        try {
            // Vérifier que la notification existe et appartient à l'utilisateur
            $where = 'id = ?';
            $params = [$notificationId];
            
            if ($userId) {
                $where .= ' AND (target_user = ? OR target_user IS NULL)';
                $params[] = $userId;
            }
            
            $sql = "UPDATE " . Logger::getTablePrefix() . "notifications 
                    SET status = 'read', updated_at = NOW() 
                    WHERE {$where} AND status = 'unread'";
            
            $result = $this->db->execute($sql, $params);
            
            if ($result) {
                $this->logAction('Notification marked as read', "ID: {$notificationId}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logAction('Failed to mark notification as read', $e->getMessage(), LOG_LEVEL_ERROR);
            return false;
        }
    }
    
    /**
     * Marque une notification comme ignorée
     */
    public function dismiss($notificationId, $userId = null) {
        try {
            $where = 'id = ?';
            $params = [$notificationId];
            
            if ($userId) {
                $where .= ' AND (target_user = ? OR target_user IS NULL)';
                $params[] = $userId;
            }
            
            $sql = "UPDATE " . Logger::getTablePrefix() . "notifications 
                    SET status = 'dismissed', updated_at = NOW() 
                    WHERE {$where}";
            
            $result = $this->db->execute($sql, $params);
            
            if ($result) {
                $this->logAction('Notification dismissed', "ID: {$notificationId}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logAction('Failed to dismiss notification', $e->getMessage(), LOG_LEVEL_ERROR);
            return false;
        }
    }
    
    /**
     * Compte les notifications non lues
     */
    public function getUnreadCount($targetUser = null) {
        try {
            $where = ['status = "unread"'];
            $params = [];
            
            if ($targetUser !== null) {
                $where[] = '(target_user = ? OR target_user IS NULL)';
                $params[] = $targetUser;
            } else {
                $where[] = 'target_user IS NULL';
            }
            
            // Exclure les notifications expirées
            $where[] = '(expires_at IS NULL OR expires_at > NOW())';
            
            $sql = "SELECT COUNT(*) as count FROM " . Logger::getTablePrefix() . "notifications 
                    WHERE " . implode(' AND ', $where);
            
            $result = $this->db->fetchOne($sql, $params);
            return (int) $result['count'];
            
        } catch (Exception $e) {
            $this->logAction('Failed to count unread notifications', $e->getMessage(), LOG_LEVEL_ERROR);
            return 0;
        }
    }
    
    /**
     * Nettoie les anciennes notifications
     */
    public function cleanupOldNotifications() {
        try {
            $retentionDays = (int) $this->getConfig('retention_days', 30);
            
            $sql = "DELETE FROM " . Logger::getTablePrefix() . "notifications 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                    OR (expires_at IS NOT NULL AND expires_at < NOW())";
            
            $result = $this->db->execute($sql, [$retentionDays]);
            
            if ($result) {
                $this->logAction('Old notifications cleaned up', "Retention: {$retentionDays} days");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logAction('Failed to cleanup old notifications', $e->getMessage(), LOG_LEVEL_ERROR);
            return false;
        }
    }
    
    /**
     * Retourne les statistiques des notifications
     */
    public function getStatistics() {
        try {
            $stats = [];
            
            // Total par statut
            $sql = "SELECT status, COUNT(*) as count FROM " . Logger::getTablePrefix() . "notifications 
                    WHERE (expires_at IS NULL OR expires_at > NOW()) 
                    GROUP BY status";
            $statusStats = $this->db->fetchAll($sql);
            
            foreach ($statusStats as $stat) {
                $stats['by_status'][$stat['status']] = (int) $stat['count'];
            }
            
            // Total par type
            $sql = "SELECT type, COUNT(*) as count FROM " . Logger::getTablePrefix() . "notifications 
                    WHERE (expires_at IS NULL OR expires_at > NOW()) 
                    GROUP BY type";
            $typeStats = $this->db->fetchAll($sql);
            
            foreach ($typeStats as $stat) {
                $stats['by_type'][$stat['type']] = (int) $stat['count'];
            }
            
            // Total par priorité
            $sql = "SELECT priority, COUNT(*) as count FROM " . Logger::getTablePrefix() . "notifications 
                    WHERE (expires_at IS NULL OR expires_at > NOW()) 
                    GROUP BY priority";
            $priorityStats = $this->db->fetchAll($sql);
            
            foreach ($priorityStats as $stat) {
                $stats['by_priority'][$stat['priority']] = (int) $stat['count'];
            }
            
            // Notifications récentes (7 derniers jours)
            $sql = "SELECT COUNT(*) as count FROM " . Logger::getTablePrefix() . "notifications 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $recent = $this->db->fetchOne($sql);
            $stats['recent_count'] = (int) $recent['count'];
            
            return $stats;
            
        } catch (Exception $e) {
            $this->logAction('Failed to get statistics', $e->getMessage(), LOG_LEVEL_ERROR);
            return [];
        }
    }
    
    /**
     * Génère le HTML pour l'affichage des notifications
     */
    public function renderNotifications($targetUser = null, $limit = 10) {
        $notifications = $this->getNotifications($targetUser, null, $limit);
        
        if (empty($notifications)) {
            return '<div class="notification-empty">Aucune notification</div>';
        }
        
        $html = '<div class="notifications-list">';
        
        foreach ($notifications as $notification) {
            $priorityClass = 'notification-' . $notification['priority'];
            $typeIcon = $this->getTypeIcon($notification['type']);
            $timeAgo = $this->timeAgo($notification['created_at']);
            
            $html .= '
            <div class="notification-item ' . $priorityClass . ' notification-' . $notification['status'] . '" data-id="' . $notification['id'] . '">
                <div class="notification-icon">' . $typeIcon . '</div>
                <div class="notification-content">
                    <div class="notification-title">' . htmlspecialchars($notification['title']) . '</div>
                    <div class="notification-message">' . htmlspecialchars($notification['message']) . '</div>
                    <div class="notification-meta">
                        <span class="notification-time">' . $timeAgo . '</span>
                        <span class="notification-type">' . ucfirst($notification['type']) . '</span>
                    </div>
                </div>
                <div class="notification-actions">
                    <button onclick="markNotificationAsRead(' . $notification['id'] . ')" class="btn-mark-read" title="Marquer comme lu">✓</button>
                    <button onclick="dismissNotification(' . $notification['id'] . ')" class="btn-dismiss" title="Ignorer">✕</button>
                </div>
            </div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Retourne l'icône pour un type de notification
     */
    private function getTypeIcon($type) {
        $icons = [
            'update' => '🔄',
            'backup' => '💾',
            'maintenance' => '🔧',
            'system' => '⚙️',
            'warning' => '⚠️',
            'error' => '❌'
        ];
        
        return $icons[$type] ?? '📢';
    }
    
    /**
     * Calcule le temps écoulé depuis une date
     */
    private function timeAgo($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'À l\'instant';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return "Il y a {$minutes} minute" . ($minutes > 1 ? 's' : '');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "Il y a {$hours} heure" . ($hours > 1 ? 's' : '');
        } else {
            $days = floor($diff / 86400);
            return "Il y a {$days} jour" . ($days > 1 ? 's' : '');
        }
    }
}