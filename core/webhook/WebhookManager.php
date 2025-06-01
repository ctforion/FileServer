<?php

namespace Core\Webhook;

use Core\Database\Database;
use Core\Utils\EnvLoader;

/**
 * Webhook Management System
 * Handles webhook delivery, retry logic, signature verification, and event tracking
 */
class WebhookManager
{
    private $db;
    private $config;
    private $secret;
    private $retryAttempts;
    private $retryDelays;
    
    public function __construct()
    {
        $this->db = new Database();
        $this->config = EnvLoader::getEnv();
        $this->secret = $this->config['WEBHOOK_SECRET'] ?? '';
        $this->retryAttempts = $this->config['WEBHOOK_RETRY_ATTEMPTS'] ?? 3;
        $this->retryDelays = [30, 300, 1800]; // 30s, 5m, 30m
    }
    
    /**
     * Register a webhook endpoint
     */
    public function registerWebhook($url, $events, $name = '', $secret = '')
    {
        try {
            $webhookId = $this->db->insert('webhooks', [
                'name' => $name ?: 'Webhook ' . substr(md5($url), 0, 8),
                'url' => $url,
                'events' => json_encode($events),
                'secret' => $secret ?: $this->generateSecret(),
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'webhook_id' => $webhookId,
                'message' => 'Webhook registered successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Webhook registration failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to register webhook: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Trigger webhook for an event
     */
    public function triggerWebhook($event, $data, $userId = null)
    {
        try {
            // Get active webhooks that listen to this event
            $webhooks = $this->getWebhooksForEvent($event);
            
            if (empty($webhooks)) {
                return ['success' => true, 'message' => 'No webhooks registered for this event'];
            }
            
            $triggered = 0;
            
            foreach ($webhooks as $webhook) {
                $payload = $this->buildPayload($event, $data, $userId);
                $deliveryId = $this->queueDelivery($webhook, $payload);
                
                if ($deliveryId) {
                    $triggered++;
                }
            }
            
            return [
                'success' => true,
                'triggered' => $triggered,
                'message' => "Triggered {$triggered} webhooks for event: {$event}"
            ];
            
        } catch (Exception $e) {
            error_log("Webhook trigger failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to trigger webhooks: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process webhook delivery queue
     */
    public function processDeliveryQueue($limit = 10)
    {
        $deliveries = $this->db->query(
            "SELECT wd.*, w.url, w.secret 
             FROM webhook_deliveries wd
             JOIN webhooks w ON wd.webhook_id = w.id
             WHERE wd.status = 'pending' 
             AND (wd.next_attempt IS NULL OR wd.next_attempt <= ?)
             ORDER BY wd.created_at ASC
             LIMIT ?",
            [date('Y-m-d H:i:s'), $limit]
        )->fetchAll();
        
        $processed = 0;
        $failed = 0;
        
        foreach ($deliveries as $delivery) {
            $result = $this->deliverWebhook($delivery);
            
            if ($result['success']) {
                $this->markDeliverySuccess($delivery['id'], $result);
                $processed++;
            } else {
                $this->markDeliveryFailure($delivery['id'], $result);
                $failed++;
            }
        }
        
        return [
            'processed' => $processed,
            'failed' => $failed,
            'total' => count($deliveries)
        ];
    }
    
    /**
     * Deliver webhook to endpoint
     */
    private function deliverWebhook($delivery)
    {
        try {
            $payload = $delivery['payload'];
            $signature = $this->generateSignature($payload, $delivery['secret']);
            
            $headers = [
                'Content-Type: application/json',
                'User-Agent: FileServer-Webhook/1.0',
                'X-Webhook-Signature: sha256=' . $signature,
                'X-Webhook-Event: ' . $delivery['event'],
                'X-Webhook-Delivery: ' . $delivery['id'],
                'X-Webhook-Timestamp: ' . strtotime($delivery['created_at'])
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $delivery['url'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'FileServer-Webhook/1.0'
            ]);
            
            $startTime = microtime(true);
            $response = curl_exec($ch);
            $duration = round((microtime(true) - $startTime) * 1000); // milliseconds
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("cURL error: {$error}");
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'http_code' => $httpCode,
                    'response' => $response,
                    'duration' => $duration
                ];
            } else {
                throw new Exception("HTTP {$httpCode}: {$response}");
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'duration' => $duration ?? 0
            ];
        }
    }
    
    /**
     * Queue webhook delivery
     */
    private function queueDelivery($webhook, $payload)
    {
        return $this->db->insert('webhook_deliveries', [
            'webhook_id' => $webhook['id'],
            'event' => $payload['event'],
            'payload' => json_encode($payload),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Mark delivery as successful
     */
    private function markDeliverySuccess($deliveryId, $result)
    {
        $this->db->query(
            "UPDATE webhook_deliveries 
             SET status = 'delivered',
                 delivered_at = ?,
                 response_code = ?,
                 response_body = ?,
                 duration = ?
             WHERE id = ?",
            [
                date('Y-m-d H:i:s'),
                $result['http_code'],
                substr($result['response'], 0, 1000), // Limit response size
                $result['duration'],
                $deliveryId
            ]
        );
    }
    
    /**
     * Mark delivery as failed and schedule retry
     */
    private function markDeliveryFailure($deliveryId, $result)
    {
        $delivery = $this->db->query(
            "SELECT attempts FROM webhook_deliveries WHERE id = ?",
            [$deliveryId]
        )->fetch();
        
        $attempts = $delivery['attempts'] + 1;
        
        if ($attempts >= $this->retryAttempts) {
            // Final failure
            $this->db->query(
                "UPDATE webhook_deliveries 
                 SET status = 'failed',
                     attempts = ?,
                     error_message = ?,
                     duration = ?
                 WHERE id = ?",
                [$attempts, $result['error'], $result['duration'] ?? 0, $deliveryId]
            );
        } else {
            // Schedule retry
            $nextAttempt = date(
                'Y-m-d H:i:s',
                time() + $this->retryDelays[min($attempts - 1, count($this->retryDelays) - 1)]
            );
            
            $this->db->query(
                "UPDATE webhook_deliveries 
                 SET attempts = ?,
                     error_message = ?,
                     next_attempt = ?,
                     duration = ?
                 WHERE id = ?",
                [$attempts, $result['error'], $nextAttempt, $result['duration'] ?? 0, $deliveryId]
            );
        }
    }
    
    /**
     * Build webhook payload
     */
    private function buildPayload($event, $data, $userId = null)
    {
        return [
            'event' => $event,
            'timestamp' => time(),
            'data' => $data,
            'user_id' => $userId,
            'server' => [
                'name' => $this->config['APP_NAME'] ?? 'FileServer',
                'version' => $this->config['APP_VERSION'] ?? '1.0.0',
                'url' => $this->config['APP_URL'] ?? ''
            ]
        ];
    }
    
    /**
     * Get webhooks for specific event
     */
    private function getWebhooksForEvent($event)
    {
        $webhooks = $this->db->query(
            "SELECT * FROM webhooks WHERE active = 1"
        )->fetchAll();
        
        $filtered = [];
        
        foreach ($webhooks as $webhook) {
            $events = json_decode($webhook['events'], true) ?: [];
            
            if (in_array('*', $events) || in_array($event, $events)) {
                $filtered[] = $webhook;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Generate webhook signature
     */
    private function generateSignature($payload, $secret)
    {
        return hash_hmac('sha256', $payload, $secret);
    }
    
    /**
     * Verify webhook signature
     */
    public function verifySignature($payload, $signature, $secret)
    {
        $expectedSignature = 'sha256=' . $this->generateSignature($payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Generate random secret
     */
    private function generateSecret($length = 32)
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Get webhook statistics
     */
    public function getWebhookStats($webhookId = null, $days = 30)
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $whereClause = $webhookId ? "AND wd.webhook_id = ?" : "";
        $params = [$since];
        
        if ($webhookId) {
            $params[] = $webhookId;
        }
        
        return $this->db->query(
            "SELECT 
                DATE(wd.created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN wd.status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN wd.status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(wd.duration) as avg_duration
             FROM webhook_deliveries wd
             WHERE wd.created_at >= ? {$whereClause}
             GROUP BY DATE(wd.created_at)
             ORDER BY date DESC",
            $params
        )->fetchAll();
    }
    
    /**
     * Get webhook delivery history
     */
    public function getDeliveryHistory($webhookId, $limit = 50)
    {
        return $this->db->query(
            "SELECT wd.*, w.name as webhook_name, w.url
             FROM webhook_deliveries wd
             JOIN webhooks w ON wd.webhook_id = w.id
             WHERE wd.webhook_id = ?
             ORDER BY wd.created_at DESC
             LIMIT ?",
            [$webhookId, $limit]
        )->fetchAll();
    }
    
    /**
     * Get all webhooks
     */
    public function getWebhooks()
    {
        return $this->db->query(
            "SELECT w.*,
                    COUNT(wd.id) as total_deliveries,
                    SUM(CASE WHEN wd.status = 'delivered' THEN 1 ELSE 0 END) as successful_deliveries
             FROM webhooks w
             LEFT JOIN webhook_deliveries wd ON w.id = wd.webhook_id
             GROUP BY w.id
             ORDER BY w.created_at DESC"
        )->fetchAll();
    }
    
    /**
     * Update webhook
     */
    public function updateWebhook($webhookId, $data)
    {
        $allowedFields = ['name', 'url', 'events', 'secret', 'active'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        if (isset($updateData['events']) && is_array($updateData['events'])) {
            $updateData['events'] = json_encode($updateData['events']);
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        $success = $this->db->update('webhooks', $updateData, ['id' => $webhookId]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Webhook updated successfully' : 'Failed to update webhook'
        ];
    }
    
    /**
     * Delete webhook
     */
    public function deleteWebhook($webhookId)
    {
        $this->db->beginTransaction();
        
        try {
            // Delete delivery history
            $this->db->query(
                "DELETE FROM webhook_deliveries WHERE webhook_id = ?",
                [$webhookId]
            );
            
            // Delete webhook
            $success = $this->db->query(
                "DELETE FROM webhooks WHERE id = ?",
                [$webhookId]
            )->rowCount() > 0;
            
            $this->db->commit();
            
            return [
                'success' => $success,
                'message' => $success ? 'Webhook deleted successfully' : 'Webhook not found'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'Failed to delete webhook: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Test webhook endpoint
     */
    public function testWebhook($webhookId)
    {
        $webhook = $this->db->query(
            "SELECT * FROM webhooks WHERE id = ?",
            [$webhookId]
        )->fetch();
        
        if (!$webhook) {
            return [
                'success' => false,
                'message' => 'Webhook not found'
            ];
        }
        
        $testPayload = $this->buildPayload('webhook.test', [
            'message' => 'This is a test webhook delivery',
            'webhook_id' => $webhookId
        ]);
        
        $delivery = [
            'id' => 'test-' . time(),
            'url' => $webhook['url'],
            'secret' => $webhook['secret'],
            'event' => 'webhook.test',
            'payload' => json_encode($testPayload),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $this->deliverWebhook($delivery);
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Test webhook delivered successfully' : 'Test webhook failed: ' . $result['error'],
            'details' => $result
        ];
    }
    
    /**
     * Clean old webhook deliveries
     */
    public function cleanOldDeliveries($days = 30)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $this->db->query(
            "DELETE FROM webhook_deliveries WHERE created_at < ?",
            [$cutoff]
        )->rowCount();
        
        return $deleted;
    }
    
    // Event triggers for common file operations
    
    /**
     * File uploaded event
     */
    public function onFileUploaded($file, $userId)
    {
        return $this->triggerWebhook('file.uploaded', [
            'file_id' => $file['id'],
            'filename' => $file['original_name'],
            'size' => $file['size'],
            'mime_type' => $file['mime_type'],
            'uploaded_at' => $file['created_at']
        ], $userId);
    }
    
    /**
     * File downloaded event
     */
    public function onFileDownloaded($file, $userId)
    {
        return $this->triggerWebhook('file.downloaded', [
            'file_id' => $file['id'],
            'filename' => $file['original_name'],
            'size' => $file['size'],
            'downloaded_at' => date('Y-m-d H:i:s')
        ], $userId);
    }
    
    /**
     * File deleted event
     */
    public function onFileDeleted($file, $userId)
    {
        return $this->triggerWebhook('file.deleted', [
            'file_id' => $file['id'],
            'filename' => $file['original_name'],
            'size' => $file['size'],
            'deleted_at' => date('Y-m-d H:i:s')
        ], $userId);
    }
    
    /**
     * File shared event
     */
    public function onFileShared($file, $shareData, $userId)
    {
        return $this->triggerWebhook('file.shared', [
            'file_id' => $file['id'],
            'filename' => $file['original_name'],
            'share_token' => $shareData['token'],
            'expires_at' => $shareData['expires_at'],
            'permissions' => $shareData['permissions'],
            'shared_at' => date('Y-m-d H:i:s')
        ], $userId);
    }
    
    /**
     * User registered event
     */
    public function onUserRegistered($user)
    {
        return $this->triggerWebhook('user.registered', [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'registered_at' => $user['created_at']
        ]);
    }
    
    /**
     * User login event
     */
    public function onUserLogin($user)
    {
        return $this->triggerWebhook('user.login', [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'login_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * System alert event
     */
    public function onSystemAlert($type, $message, $severity = 'info')
    {
        return $this->triggerWebhook('system.alert', [
            'alert_type' => $type,
            'message' => $message,
            'severity' => $severity,
            'server_time' => date('Y-m-d H:i:s'),
            'memory_usage' => memory_get_usage(true),
            'disk_usage' => $this->getDiskUsage()
        ]);
    }
    
    /**
     * Get disk usage percentage
     */
    private function getDiskUsage()
    {
        $bytes = disk_free_space(".");
        $total = disk_total_space(".");
        
        if ($total > 0) {
            return round((($total - $bytes) / $total) * 100, 2);
        }
        
        return 0;
    }
}
