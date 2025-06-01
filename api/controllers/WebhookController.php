<?php

/**
 * WebhookController
 * 
 * Handles webhook management including creation, deletion, testing,
 * and delivery of webhook events to external services.
 */
class WebhookController extends BaseController {
    private $db;
    private $auth;
    
    public function __construct($db, $auth) {
        parent::__construct();
        $this->db = $db;
        $this->auth = $auth;
    }
    
    /**
     * List all webhooks
     */
    public function list() {
        try {
            if (!$this->auth->hasPermission('admin.webhooks')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $webhooks = $this->db->query("
                SELECT id, name, url, events, is_active, secret, 
                       created_at, updated_at, last_delivery_at, delivery_count
                FROM webhooks 
                ORDER BY created_at DESC
            ")->fetchAll();
            
            foreach ($webhooks as &$webhook) {
                $webhook['events'] = json_decode($webhook['events'], true);
                $webhook['is_active'] = (bool)$webhook['is_active'];
                $webhook['delivery_count'] = (int)$webhook['delivery_count'];
                
                // Mask secret for security
                $webhook['secret'] = $webhook['secret'] ? '***masked***' : null;
            }
            
            return $this->success($webhooks);
            
        } catch (Exception $e) {
            return $this->error('Failed to list webhooks: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get webhook details
     */
    public function get($id) {
        try {
            if (!$this->auth->hasPermission('admin.webhooks')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $webhook = $this->db->query("
                SELECT id, name, url, events, is_active, secret, headers,
                       created_at, updated_at, last_delivery_at, delivery_count
                FROM webhooks WHERE id = ?
            ", [$id])->fetch();
            
            if (!$webhook) {
                return $this->error('Webhook not found', 404);
            }
            
            $webhook['events'] = json_decode($webhook['events'], true);
            $webhook['headers'] = json_decode($webhook['headers'], true);
            $webhook['is_active'] = (bool)$webhook['is_active'];
            $webhook['delivery_count'] = (int)$webhook['delivery_count'];
            
            // Mask secret for security
            $webhook['secret'] = $webhook['secret'] ? '***masked***' : null;
            
            return $this->success($webhook);
            
        } catch (Exception $e) {
            return $this->error('Failed to get webhook: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create a new webhook
     */
    public function create() {
        try {
            if (!$this->auth->hasPermission('admin.webhooks')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $data = $this->getJsonInput();
            
            // Validate required fields
            if (empty($data['name'])) {
                return $this->error('Webhook name is required', 400);
            }
            
            if (empty($data['url'])) {
                return $this->error('Webhook URL is required', 400);
            }
            
            if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
                return $this->error('Invalid webhook URL', 400);
            }
            
            if (empty($data['events']) || !is_array($data['events'])) {
                return $this->error('Webhook events are required', 400);
            }
            
            // Validate events
            $validEvents = [
                'file.uploaded', 'file.downloaded', 'file.deleted', 'file.shared',
                'user.created', 'user.updated', 'user.deleted', 'user.login',
                'system.startup', 'system.maintenance', 'plugin.activated'
            ];
            
            foreach ($data['events'] as $event) {
                if (!in_array($event, $validEvents)) {
                    return $this->error("Invalid event: $event", 400);
                }
            }
            
            // Generate secret if not provided
            $secret = $data['secret'] ?? bin2hex(random_bytes(32));
            
            $webhookId = $this->db->query("
                INSERT INTO webhooks (name, url, events, secret, headers, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ", [
                $data['name'],
                $data['url'],
                json_encode($data['events']),
                $secret,
                json_encode($data['headers'] ?? []),
                isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1
            ])->insertId();
            
            // Log webhook creation
            $this->db->query("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())", 
                [$this->auth->getCurrentUser()['id'], 'webhook.create', json_encode(['id' => $webhookId, 'name' => $data['name']]), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            return $this->success([
                'id' => $webhookId,
                'message' => 'Webhook created successfully',
                'secret' => $secret
            ]);
            
        } catch (Exception $e) {
            return $this->error('Failed to create webhook: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update a webhook
     */
    public function update($id) {
        try {
            if (!$this->auth->hasPermission('admin.webhooks')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $webhook = $this->db->query("SELECT id FROM webhooks WHERE id = ?", [$id])->fetch();
            if (!$webhook) {
                return $this->error('Webhook not found', 404);
            }
            
            $data = $this->getJsonInput();
            
            $updates = [];
            $params = [];
            
            if (isset($data['name'])) {
                if (empty($data['name'])) {
                    return $this->error('Webhook name cannot be empty', 400);
                }
                $updates[] = 'name = ?';
                $params[] = $data['name'];
            }
            
            if (isset($data['url'])) {
                if (empty($data['url']) || !filter_var($data['url'], FILTER_VALIDATE_URL)) {
                    return $this->error('Invalid webhook URL', 400);
                }
                $updates[] = 'url = ?';
                $params[] = $data['url'];
            }
            
            if (isset($data['events'])) {
                if (!is_array($data['events'])) {
                    return $this->error('Events must be an array', 400);
                }
                
                $validEvents = [
                    'file.uploaded', 'file.downloaded', 'file.deleted', 'file.shared',
                    'user.created', 'user.updated', 'user.deleted', 'user.login',
                    'system.startup', 'system.maintenance', 'plugin.activated'
                ];
                
                foreach ($data['events'] as $event) {
                    if (!in_array($event, $validEvents)) {
                        return $this->error("Invalid event: $event", 400);
                    }
                }
                
                $updates[] = 'events = ?';
                $params[] = json_encode($data['events']);
            }
            
            if (isset($data['secret'])) {
                $updates[] = 'secret = ?';
                $params[] = $data['secret'];
            }
            
            if (isset($data['headers'])) {
                $updates[] = 'headers = ?';
                $params[] = json_encode($data['headers']);
            }
            
            if (isset($data['is_active'])) {
                $updates[] = 'is_active = ?';
                $params[] = $data['is_active'] ? 1 : 0;
            }
            
            if (empty($updates)) {
                return $this->error('No fields to update', 400);
            }
            
            $updates[] = 'updated_at = NOW()';
            $params[] = $id;
            
            $this->db->query("UPDATE webhooks SET " . implode(', ', $updates) . " WHERE id = ?", $params);
            
            // Log webhook update
            $this->db->query("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())", 
                [$this->auth->getCurrentUser()['id'], 'webhook.update', json_encode(['id' => $id, 'updates' => array_keys($data)]), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            return $this->success(['message' => 'Webhook updated successfully']);
            
        } catch (Exception $e) {
            return $this->error('Failed to update webhook: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete a webhook
     */
    public function delete($id) {
        try {
            if (!$this->auth->hasPermission('admin.webhooks')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $webhook = $this->db->query("SELECT name FROM webhooks WHERE id = ?", [$id])->fetch();
            if (!$webhook) {
                return $this->error('Webhook not found', 404);
            }
            
            $this->db->query("DELETE FROM webhooks WHERE id = ?", [$id]);
            
            // Log webhook deletion
            $this->db->query("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())", 
                [$this->auth->getCurrentUser()['id'], 'webhook.delete', json_encode(['id' => $id, 'name' => $webhook['name']]), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            return $this->success(['message' => 'Webhook deleted successfully']);
            
        } catch (Exception $e) {
            return $this->error('Failed to delete webhook: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Test a webhook
     */
    public function test($id) {
        try {
            if (!$this->auth->hasPermission('admin.webhooks')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $webhook = $this->db->query("
                SELECT name, url, secret, headers
                FROM webhooks WHERE id = ?
            ", [$id])->fetch();
            
            if (!$webhook) {
                return $this->error('Webhook not found', 404);
            }
            
            // Create test payload
            $payload = [
                'event' => 'webhook.test',
                'timestamp' => date('c'),
                'data' => [
                    'message' => 'This is a test webhook delivery',
                    'webhook_id' => $id,
                    'webhook_name' => $webhook['name']
                ]
            ];
            
            $result = $this->deliverWebhook($webhook, $payload);
            
            // Log test delivery
            $this->db->query("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())", 
                [$this->auth->getCurrentUser()['id'], 'webhook.test', json_encode(['id' => $id, 'success' => $result['success']]), $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            return $this->success($result);
            
        } catch (Exception $e) {
            return $this->error('Failed to test webhook: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get webhook delivery history
     */
    public function deliveryHistory($id) {
        try {
            if (!$this->auth->hasPermission('admin.webhooks')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            // Check if webhook exists
            $webhook = $this->db->query("SELECT id FROM webhooks WHERE id = ?", [$id])->fetch();
            if (!$webhook) {
                return $this->error('Webhook not found', 404);
            }
            
            // Get delivery history from audit logs
            $deliveries = $this->db->query("
                SELECT details, ip_address, created_at
                FROM audit_logs 
                WHERE action = 'webhook.delivery' 
                AND JSON_EXTRACT(details, '$.webhook_id') = ?
                ORDER BY created_at DESC
                LIMIT 50
            ", [$id])->fetchAll();
            
            foreach ($deliveries as &$delivery) {
                $delivery['details'] = json_decode($delivery['details'], true);
            }
            
            return $this->success($deliveries);
            
        } catch (Exception $e) {
            return $this->error('Failed to get delivery history: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Trigger webhook for event
     */
    public function triggerEvent($event, $data) {
        try {
            // Get all active webhooks that listen to this event
            $webhooks = $this->db->query("
                SELECT id, name, url, secret, headers
                FROM webhooks 
                WHERE is_active = 1 
                AND JSON_CONTAINS(events, ?)
            ", [json_encode($event)])->fetchAll();
            
            $results = [];
            
            foreach ($webhooks as $webhook) {
                $payload = [
                    'event' => $event,
                    'timestamp' => date('c'),
                    'data' => $data
                ];
                
                $result = $this->deliverWebhook($webhook, $payload);
                $results[] = [
                    'webhook_id' => $webhook['id'],
                    'webhook_name' => $webhook['name'],
                    'success' => $result['success'],
                    'response_code' => $result['response_code'],
                    'error' => $result['error'] ?? null
                ];
                
                // Update delivery count
                if ($result['success']) {
                    $this->db->query("
                        UPDATE webhooks 
                        SET delivery_count = delivery_count + 1, last_delivery_at = NOW() 
                        WHERE id = ?
                    ", [$webhook['id']]);
                }
                
                // Log delivery attempt
                $this->db->query("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())", 
                    [null, 'webhook.delivery', json_encode([
                        'webhook_id' => $webhook['id'],
                        'event' => $event,
                        'success' => $result['success'],
                        'response_code' => $result['response_code'],
                        'error' => $result['error'] ?? null
                    ]), 'system']);
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Webhook trigger error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Deliver webhook to URL
     */
    private function deliverWebhook($webhook, $payload) {
        try {
            $jsonPayload = json_encode($payload);
            
            // Calculate signature
            $signature = hash_hmac('sha256', $jsonPayload, $webhook['secret']);
            
            // Prepare headers
            $headers = [
                'Content-Type: application/json',
                'User-Agent: PHP-FileServer-Webhook/1.0',
                'X-Webhook-Signature: sha256=' . $signature,
                'X-Webhook-Event: ' . $payload['event'],
                'X-Webhook-Delivery: ' . uniqid()
            ];
            
            // Add custom headers
            $customHeaders = json_decode($webhook['headers'], true);
            if ($customHeaders) {
                foreach ($customHeaders as $name => $value) {
                    $headers[] = $name . ': ' . $value;
                }
            }
            
            // Initialize cURL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $webhook['url'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            
            $response = curl_exec($ch);
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                return [
                    'success' => false,
                    'error' => $error,
                    'response_code' => 0
                ];
            }
            
            $success = $responseCode >= 200 && $responseCode < 300;
            
            return [
                'success' => $success,
                'response_code' => $responseCode,
                'response' => $response
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response_code' => 0
            ];
        }
    }
    
    /**
     * Get available webhook events
     */
    public function getEvents() {
        try {
            if (!$this->auth->hasPermission('admin.webhooks')) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $events = [
                'file.uploaded' => 'Triggered when a file is uploaded',
                'file.downloaded' => 'Triggered when a file is downloaded',
                'file.deleted' => 'Triggered when a file is deleted',
                'file.shared' => 'Triggered when a file is shared',
                'user.created' => 'Triggered when a user account is created',
                'user.updated' => 'Triggered when a user account is updated',
                'user.deleted' => 'Triggered when a user account is deleted',
                'user.login' => 'Triggered when a user logs in',
                'system.startup' => 'Triggered when the system starts up',
                'system.maintenance' => 'Triggered when maintenance is performed',
                'plugin.activated' => 'Triggered when a plugin is activated'
            ];
            
            return $this->success($events);
            
        } catch (Exception $e) {
            return $this->error('Failed to get webhook events: ' . $e->getMessage(), 500);
        }
    }
}
