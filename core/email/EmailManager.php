<?php

namespace Core\Email;

use Core\Database\Database;
use Core\Template\TemplateEngine;
use Core\Utils\EnvLoader;

/**
 * Email Management System
 * Handles SMTP sending, template processing, queue management, and email notifications
 */
class EmailManager
{
    private $db;
    private $template;
    private $config;
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $smtpSecure;
    private $fromEmail;
    private $fromName;
    private $queueEnabled;
    
    public function __construct()
    {
        $this->db = new Database();
        $this->template = new TemplateEngine();
        $this->config = EnvLoader::getEnv();
        
        // SMTP Configuration
        $this->smtpHost = $this->config['SMTP_HOST'] ?? '';
        $this->smtpPort = $this->config['SMTP_PORT'] ?? 587;
        $this->smtpUser = $this->config['SMTP_USER'] ?? '';
        $this->smtpPass = $this->config['SMTP_PASS'] ?? '';
        $this->smtpSecure = $this->config['SMTP_SECURE'] ?? 'tls';
        $this->fromEmail = $this->config['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com';
        $this->fromName = $this->config['MAIL_FROM_NAME'] ?? 'File Server';
        $this->queueEnabled = $this->config['MAIL_QUEUE_ENABLED'] ?? true;
    }
    
    /**
     * Send email (queue or immediate)
     */
    public function send($to, $subject, $template, $data = [], $priority = 'normal')
    {
        $email = [
            'to' => $to,
            'subject' => $subject,
            'template' => $template,
            'data' => json_encode($data),
            'priority' => $priority,
            'created_at' => date('Y-m-d H:i:s'),
            'status' => 'pending',
            'attempts' => 0
        ];
        
        if ($this->queueEnabled) {
            // Add to queue
            $this->db->insert('email_queue', $email);
            return ['success' => true, 'queued' => true];
        } else {
            // Send immediately
            return $this->sendEmail($email);
        }
    }
    
    /**
     * Send email immediately
     */
    public function sendNow($to, $subject, $template, $data = [])
    {
        $email = [
            'to' => $to,
            'subject' => $subject,
            'template' => $template,
            'data' => json_encode($data),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->sendEmail($email);
    }
    
    /**
     * Process email queue
     */
    public function processQueue($limit = 10)
    {
        $emails = $this->db->query(
            "SELECT * FROM email_queue 
             WHERE status = 'pending' 
             ORDER BY priority DESC, created_at ASC 
             LIMIT ?",
            [$limit]
        )->fetchAll();
        
        $processed = 0;
        $failed = 0;
        
        foreach ($emails as $email) {
            $result = $this->sendEmail($email);
            
            if ($result['success']) {
                $this->db->query(
                    "UPDATE email_queue SET status = 'sent', sent_at = ? WHERE id = ?",
                    [date('Y-m-d H:i:s'), $email['id']]
                );
                $processed++;
            } else {
                $attempts = $email['attempts'] + 1;
                $maxAttempts = 3;
                
                if ($attempts >= $maxAttempts) {
                    $this->db->query(
                        "UPDATE email_queue SET status = 'failed', attempts = ?, last_error = ? WHERE id = ?",
                        [$attempts, $result['error'], $email['id']]
                    );
                } else {
                    $this->db->query(
                        "UPDATE email_queue SET attempts = ?, last_error = ? WHERE id = ?",
                        [$attempts, $result['error'], $email['id']]
                    );
                }
                $failed++;
            }
        }
        
        return [
            'processed' => $processed,
            'failed' => $failed,
            'total' => count($emails)
        ];
    }
    
    /**
     * Send actual email
     */
    private function sendEmail($email)
    {
        try {
            $data = json_decode($email['data'], true) ?: [];
            
            // Render email content
            $content = $this->renderEmailTemplate($email['template'], $data);
            
            if (!$content) {
                throw new Exception('Failed to render email template');
            }
            
            // Send via SMTP or fallback to mail()
            if ($this->smtpHost) {
                return $this->sendViaSmtp($email['to'], $email['subject'], $content);
            } else {
                return $this->sendViaMail($email['to'], $email['subject'], $content);
            }
            
        } catch (Exception $e) {
            error_log("Email send failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send via SMTP
     */
    private function sendViaSmtp($to, $subject, $content)
    {
        try {
            // Create socket connection
            $socket = fsockopen($this->smtpHost, $this->smtpPort, $errno, $errstr, 30);
            
            if (!$socket) {
                throw new Exception("Failed to connect to SMTP server: $errstr ($errno)");
            }
            
            // Read welcome message
            $this->readSmtpResponse($socket);
            
            // EHLO
            fwrite($socket, "EHLO " . $this->config['APP_DOMAIN'] . "\r\n");
            $this->readSmtpResponse($socket);
            
            // STARTTLS if required
            if ($this->smtpSecure === 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                $this->readSmtpResponse($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                
                // EHLO again after STARTTLS
                fwrite($socket, "EHLO " . $this->config['APP_DOMAIN'] . "\r\n");
                $this->readSmtpResponse($socket);
            }
            
            // AUTH LOGIN
            if ($this->smtpUser && $this->smtpPass) {
                fwrite($socket, "AUTH LOGIN\r\n");
                $this->readSmtpResponse($socket);
                
                fwrite($socket, base64_encode($this->smtpUser) . "\r\n");
                $this->readSmtpResponse($socket);
                
                fwrite($socket, base64_encode($this->smtpPass) . "\r\n");
                $this->readSmtpResponse($socket);
            }
            
            // MAIL FROM
            fwrite($socket, "MAIL FROM: <{$this->fromEmail}>\r\n");
            $this->readSmtpResponse($socket);
            
            // RCPT TO
            fwrite($socket, "RCPT TO: <{$to}>\r\n");
            $this->readSmtpResponse($socket);
            
            // DATA
            fwrite($socket, "DATA\r\n");
            $this->readSmtpResponse($socket);
            
            // Email headers and content
            $headers = $this->buildHeaders($to, $subject);
            fwrite($socket, $headers . "\r\n" . $content['html'] . "\r\n.\r\n");
            $this->readSmtpResponse($socket);
            
            // QUIT
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            if (isset($socket) && is_resource($socket)) {
                fclose($socket);
            }
            throw $e;
        }
    }
    
    /**
     * Send via PHP mail() function
     */
    private function sendViaMail($to, $subject, $content)
    {
        $headers = $this->buildHeaders($to, $subject, false);
        
        // Use text content for mail() function
        $message = $content['text'] ?: strip_tags($content['html']);
        
        if (mail($to, $subject, $message, $headers)) {
            return ['success' => true];
        } else {
            throw new Exception('Failed to send email via mail() function');
        }
    }
    
    /**
     * Read SMTP response
     */
    private function readSmtpResponse($socket)
    {
        $response = fgets($socket, 512);
        $code = substr($response, 0, 3);
        
        if ($code >= 400) {
            throw new Exception("SMTP Error: $response");
        }
        
        return $response;
    }
    
    /**
     * Build email headers
     */
    private function buildHeaders($to, $subject, $includeContentType = true)
    {
        $headers = [];
        
        if ($includeContentType) {
            $headers[] = "From: {$this->fromName} <{$this->fromEmail}>";
            $headers[] = "To: {$to}";
            $headers[] = "Subject: {$subject}";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: 8bit";
        } else {
            $headers[] = "From: {$this->fromName} <{$this->fromEmail}>";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        }
        
        $headers[] = "X-Mailer: FileServer Email System";
        $headers[] = "Date: " . date('r');
        
        return implode($includeContentType ? "\r\n" : "\r\n", $headers);
    }
    
    /**
     * Render email template
     */
    private function renderEmailTemplate($template, $data)
    {
        try {
            $templatePath = dirname(__DIR__, 2) . "/templates/emails/{$template}";
            
            // Check for HTML template
            $htmlTemplate = $templatePath . '.html.php';
            $textTemplate = $templatePath . '.txt.php';
            
            $html = '';
            $text = '';
            
            if (file_exists($htmlTemplate)) {
                $html = $this->template->render("emails/{$template}.html", $data);
            }
            
            if (file_exists($textTemplate)) {
                $text = $this->template->render("emails/{$template}.txt", $data);
            }
            
            // If no templates found, use default
            if (!$html && !$text) {
                $html = $this->getDefaultTemplate($template, $data);
                $text = strip_tags($html);
            }
            
            return [
                'html' => $html,
                'text' => $text
            ];
            
        } catch (Exception $e) {
            error_log("Email template render failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get default email template
     */
    private function getDefaultTemplate($template, $data)
    {
        $templates = [
            'welcome' => $this->getWelcomeTemplate($data),
            'verification' => $this->getVerificationTemplate($data),
            'password_reset' => $this->getPasswordResetTemplate($data),
            '2fa_setup' => $this->get2FASetupTemplate($data),
            'file_shared' => $this->getFileSharedTemplate($data),
            'quota_warning' => $this->getQuotaWarningTemplate($data),
            'admin_notification' => $this->getAdminNotificationTemplate($data)
        ];
        
        return $templates[$template] ?? $this->getGenericTemplate($data);
    }
    
    /**
     * Welcome email template
     */
    private function getWelcomeTemplate($data)
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 8px;'>
                <h1 style='color: #333; text-align: center;'>Welcome to {$this->fromName}!</h1>
                <p>Hello {$data['username']},</p>
                <p>Your account has been successfully created. You can now start uploading and managing your files.</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$data['login_url']}' style='background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>Login to Your Account</a>
                </div>
                <p>If you have any questions, please don't hesitate to contact our support team.</p>
                <p>Best regards,<br>The {$this->fromName} Team</p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Email verification template
     */
    private function getVerificationTemplate($data)
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 8px;'>
                <h1 style='color: #333; text-align: center;'>Verify Your Email Address</h1>
                <p>Hello {$data['username']},</p>
                <p>Please click the button below to verify your email address:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$data['verification_url']}' style='background-color: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>Verify Email</a>
                </div>
                <p>This link will expire in 24 hours.</p>
                <p>If you didn't create this account, please ignore this email.</p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Password reset template
     */
    private function getPasswordResetTemplate($data)
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 8px;'>
                <h1 style='color: #333; text-align: center;'>Password Reset Request</h1>
                <p>Hello {$data['username']},</p>
                <p>You requested a password reset for your account. Click the button below to reset your password:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$data['reset_url']}' style='background-color: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>Reset Password</a>
                </div>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request this reset, please ignore this email.</p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * 2FA Setup template
     */
    private function get2FASetupTemplate($data)
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 8px;'>
                <h1 style='color: #333; text-align: center;'>Two-Factor Authentication Setup</h1>
                <p>Hello {$data['username']},</p>
                <p>Two-factor authentication has been {$data['action']} for your account.</p>
                <p>Your backup codes:</p>
                <div style='background-color: #f8f9fa; padding: 15px; margin: 20px 0; font-family: monospace;'>
                    " . implode('<br>', $data['backup_codes'] ?? []) . "
                </div>
                <p style='color: #dc3545;'><strong>Important:</strong> Save these backup codes in a safe place. You can use them to access your account if you lose your authenticator device.</p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * File shared template
     */
    private function getFileSharedTemplate($data)
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 8px;'>
                <h1 style='color: #333; text-align: center;'>File Shared With You</h1>
                <p>Hello,</p>
                <p>{$data['sender_name']} has shared a file with you:</p>
                <div style='background-color: #f8f9fa; padding: 15px; margin: 20px 0; border-left: 4px solid #007bff;'>
                    <strong>{$data['filename']}</strong><br>
                    Size: {$data['filesize']}<br>
                    " . ($data['message'] ? "Message: {$data['message']}" : "") . "
                </div>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$data['download_url']}' style='background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>Download File</a>
                </div>
                " . ($data['expires_at'] ? "<p>This link will expire on {$data['expires_at']}.</p>" : "") . "
            </div>
        </body>
        </html>";
    }
    
    /**
     * Quota warning template
     */
    private function getQuotaWarningTemplate($data)
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 8px;'>
                <h1 style='color: #dc3545; text-align: center;'>Storage Quota Warning</h1>
                <p>Hello {$data['username']},</p>
                <p>Your storage usage is approaching the limit:</p>
                <div style='background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                    <strong>Used:</strong> {$data['used_space']}<br>
                    <strong>Total:</strong> {$data['total_space']}<br>
                    <strong>Percentage:</strong> {$data['usage_percentage']}%
                </div>
                <p>Please consider deleting old files or upgrading your storage plan.</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$data['manage_url']}' style='background-color: #ffc107; color: #212529; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>Manage Files</a>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Admin notification template
     */
    private function getAdminNotificationTemplate($data)
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 8px;'>
                <h1 style='color: #333; text-align: center;'>Admin Notification</h1>
                <p>Hello Administrator,</p>
                <p><strong>Event:</strong> {$data['event']}</p>
                <p><strong>Details:</strong> {$data['details']}</p>
                " . (isset($data['user']) ? "<p><strong>User:</strong> {$data['user']}</p>" : "") . "
                " . (isset($data['ip']) ? "<p><strong>IP Address:</strong> {$data['ip']}</p>" : "") . "
                <p><strong>Time:</strong> {$data['timestamp']}</p>
                " . (isset($data['action_url']) ? "
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$data['action_url']}' style='background-color: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>Take Action</a>
                </div>
                " : "") . "
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generic template
     */
    private function getGenericTemplate($data)
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 8px;'>
                <h1 style='color: #333; text-align: center;'>{$data['subject']}</h1>
                <p>{$data['message']}</p>
                <p>Best regards,<br>The {$this->fromName} Team</p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Send welcome email
     */
    public function sendWelcomeEmail($user)
    {
        return $this->send(
            $user['email'],
            'Welcome to ' . $this->fromName,
            'welcome',
            [
                'username' => $user['username'],
                'login_url' => $this->config['APP_URL'] . '/login'
            ]
        );
    }
    
    /**
     * Send verification email
     */
    public function sendVerificationEmail($user, $token)
    {
        return $this->send(
            $user['email'],
            'Verify Your Email Address',
            'verification',
            [
                'username' => $user['username'],
                'verification_url' => $this->config['APP_URL'] . '/verify?token=' . $token
            ]
        );
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($user, $token)
    {
        return $this->send(
            $user['email'],
            'Password Reset Request',
            'password_reset',
            [
                'username' => $user['username'],
                'reset_url' => $this->config['APP_URL'] . '/reset-password?token=' . $token
            ]
        );
    }
    
    /**
     * Send 2FA setup email
     */
    public function send2FASetupEmail($user, $backupCodes, $action = 'enabled')
    {
        return $this->send(
            $user['email'],
            'Two-Factor Authentication ' . ucfirst($action),
            '2fa_setup',
            [
                'username' => $user['username'],
                'action' => $action,
                'backup_codes' => $backupCodes
            ]
        );
    }
    
    /**
     * Send file sharing notification
     */
    public function sendFileSharedEmail($recipient, $file, $sender, $message = '', $expiresAt = null)
    {
        return $this->send(
            $recipient,
            'File Shared: ' . $file['original_name'],
            'file_shared',
            [
                'filename' => $file['original_name'],
                'filesize' => $this->formatBytes($file['size']),
                'sender_name' => $sender['username'],
                'message' => $message,
                'download_url' => $this->config['APP_URL'] . '/download/' . $file['share_token'],
                'expires_at' => $expiresAt
            ]
        );
    }
    
    /**
     * Send quota warning email
     */
    public function sendQuotaWarningEmail($user, $usedSpace, $totalSpace)
    {
        $usagePercentage = round(($usedSpace / $totalSpace) * 100, 1);
        
        return $this->send(
            $user['email'],
            'Storage Quota Warning',
            'quota_warning',
            [
                'username' => $user['username'],
                'used_space' => $this->formatBytes($usedSpace),
                'total_space' => $this->formatBytes($totalSpace),
                'usage_percentage' => $usagePercentage,
                'manage_url' => $this->config['APP_URL'] . '/files'
            ]
        );
    }
    
    /**
     * Send admin notification
     */
    public function sendAdminNotification($event, $details, $user = null, $actionUrl = null)
    {
        $admins = $this->db->query(
            "SELECT email FROM users WHERE role = 'admin'"
        )->fetchAll();
        
        foreach ($admins as $admin) {
            $this->send(
                $admin['email'],
                'Admin Alert: ' . $event,
                'admin_notification',
                [
                    'event' => $event,
                    'details' => $details,
                    'user' => $user ? $user['username'] : null,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'action_url' => $actionUrl
                ],
                'high'
            );
        }
    }
    
    /**
     * Format bytes for display
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Get email queue status
     */
    public function getQueueStatus()
    {
        $stats = $this->db->query(
            "SELECT 
                status,
                COUNT(*) as count
             FROM email_queue 
             GROUP BY status"
        )->fetchAll();
        
        $result = [
            'pending' => 0,
            'sent' => 0,
            'failed' => 0
        ];
        
        foreach ($stats as $stat) {
            $result[$stat['status']] = $stat['count'];
        }
        
        return $result;
    }
    
    /**
     * Get email statistics
     */
    public function getEmailStats($days = 30)
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $this->db->query(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM email_queue 
             WHERE created_at >= ?
             GROUP BY DATE(created_at)
             ORDER BY date DESC",
            [$since]
        )->fetchAll();
    }
    
    /**
     * Clean old emails from queue
     */
    public function cleanOldEmails($days = 30)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $this->db->query(
            "DELETE FROM email_queue WHERE created_at < ? AND status IN ('sent', 'failed')",
            [$cutoff]
        )->rowCount();
        
        return $deleted;
    }
}
