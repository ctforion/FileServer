<?php
/**
 * ShareManager - File Sharing Management
 * 
 * Handles file sharing functionality including:
 * - Creating and managing file shares
 * - Access control and permissions
 * - Share link generation and validation
 * - Download tracking and analytics
 * - Share expiration and cleanup
 */

class ShareManager {
    private $db;
    private $config;
    private $logger;

    public function __construct($database, $config) {
        $this->db = $database;
        $this->config = $config;
        $this->logger = new Logger($database);
    }

    /**
     * Create a new file share
     */
    public function createShare($fileId, $userId, $options = []) {
        try {
            // Validate file exists and user has access
            if (!$this->canUserAccessFile($fileId, $userId)) {
                throw new Exception('Access denied to file');
            }

            $shareToken = $this->generateShareToken();
            $expiresAt = isset($options['expires_at']) ? $options['expires_at'] : null;
            $password = isset($options['password']) ? password_hash($options['password'], PASSWORD_DEFAULT) : null;
            $downloadLimit = isset($options['download_limit']) ? (int)$options['download_limit'] : null;
            $allowPreview = isset($options['allow_preview']) ? (bool)$options['allow_preview'] : true;

            $stmt = $this->db->prepare("
                INSERT INTO shares (
                    file_id, user_id, share_token, password_hash, 
                    expires_at, download_limit, allow_preview, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $fileId, $userId, $shareToken, $password,
                $expiresAt, $downloadLimit, $allowPreview
            ]);

            $shareId = $this->db->lastInsertId();

            $this->logger->log('share_created', [
                'share_id' => $shareId,
                'file_id' => $fileId,
                'user_id' => $userId,
                'expires_at' => $expiresAt,
                'download_limit' => $downloadLimit
            ]);

            return [
                'share_id' => $shareId,
                'share_token' => $shareToken,
                'share_url' => $this->config['BASE_URL'] . $this->config['BASE_PATH'] . '/share/' . $shareToken
            ];

        } catch (Exception $e) {
            $this->logger->log('share_creation_failed', [
                'file_id' => $fileId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get share information by token
     */
    public function getShareByToken($shareToken) {
        $stmt = $this->db->prepare("
            SELECT s.*, f.original_name, f.file_size, f.mime_type, f.file_path,
                   u.username as owner_username
            FROM shares s
            JOIN files f ON s.file_id = f.file_id
            JOIN users u ON s.user_id = u.user_id
            WHERE s.share_token = ? AND s.is_active = 1
        ");
        
        $stmt->execute([$shareToken]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Validate share access
     */
    public function validateShareAccess($shareToken, $password = null) {
        $share = $this->getShareByToken($shareToken);
        
        if (!$share) {
            return ['valid' => false, 'error' => 'Share not found'];
        }

        // Check if share is expired
        if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
            return ['valid' => false, 'error' => 'Share has expired'];
        }

        // Check download limit
        if ($share['download_limit'] && $share['download_count'] >= $share['download_limit']) {
            return ['valid' => false, 'error' => 'Download limit exceeded'];
        }

        // Check password
        if ($share['password_hash'] && !password_verify($password, $share['password_hash'])) {
            return ['valid' => false, 'error' => 'Invalid password'];
        }

        return ['valid' => true, 'share' => $share];
    }

    /**
     * Download file via share
     */
    public function downloadViaShare($shareToken, $password = null, $userAgent = null, $ipAddress = null) {
        $validation = $this->validateShareAccess($shareToken, $password);
        
        if (!$validation['valid']) {
            throw new Exception($validation['error']);
        }

        $share = $validation['share'];

        // Increment download count
        $this->incrementDownloadCount($share['share_id']);

        // Log download
        $this->logger->log('share_download', [
            'share_id' => $share['share_id'],
            'file_id' => $share['file_id'],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);

        return [
            'file_path' => $share['file_path'],
            'original_name' => $share['original_name'],
            'file_size' => $share['file_size'],
            'mime_type' => $share['mime_type']
        ];
    }

    /**
     * Get shares for a user
     */
    public function getUserShares($userId, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;

        $stmt = $this->db->prepare("
            SELECT s.*, f.original_name, f.file_size, f.mime_type
            FROM shares s
            JOIN files f ON s.file_id = f.file_id
            WHERE s.user_id = ? AND s.is_active = 1
            ORDER BY s.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$userId, $limit, $offset]);
        $shares = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM shares s
            WHERE s.user_id = ? AND s.is_active = 1
        ");
        $countStmt->execute([$userId]);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'shares' => $shares,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * Delete/deactivate a share
     */
    public function deleteShare($shareId, $userId) {
        // Verify ownership
        $stmt = $this->db->prepare("
            SELECT share_id FROM shares 
            WHERE share_id = ? AND user_id = ?
        ");
        $stmt->execute([$shareId, $userId]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Share not found or access denied');
        }

        $stmt = $this->db->prepare("
            UPDATE shares SET is_active = 0, deleted_at = NOW()
            WHERE share_id = ?
        ");
        $stmt->execute([$shareId]);

        $this->logger->log('share_deleted', [
            'share_id' => $shareId,
            'user_id' => $userId
        ]);

        return true;
    }

    /**
     * Update share settings
     */
    public function updateShare($shareId, $userId, $updates) {
        // Verify ownership
        $stmt = $this->db->prepare("
            SELECT share_id FROM shares 
            WHERE share_id = ? AND user_id = ?
        ");
        $stmt->execute([$shareId, $userId]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Share not found or access denied');
        }

        $allowedFields = ['expires_at', 'download_limit', 'allow_preview', 'password'];
        $setClause = [];
        $values = [];

        foreach ($updates as $field => $value) {
            if (in_array($field, $allowedFields)) {
                if ($field === 'password') {
                    $setClause[] = "password_hash = ?";
                    $values[] = $value ? password_hash($value, PASSWORD_DEFAULT) : null;
                } else {
                    $setClause[] = "$field = ?";
                    $values[] = $value;
                }
            }
        }

        if (empty($setClause)) {
            throw new Exception('No valid fields to update');
        }

        $values[] = $shareId;
        $sql = "UPDATE shares SET " . implode(', ', $setClause) . " WHERE share_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);

        $this->logger->log('share_updated', [
            'share_id' => $shareId,
            'user_id' => $userId,
            'updates' => array_keys($updates)
        ]);

        return true;
    }

    /**
     * Clean up expired shares
     */
    public function cleanupExpiredShares() {
        $stmt = $this->db->prepare("
            UPDATE shares 
            SET is_active = 0, deleted_at = NOW()
            WHERE expires_at < NOW() AND is_active = 1
        ");
        $stmt->execute();

        $deletedCount = $stmt->rowCount();
        
        if ($deletedCount > 0) {
            $this->logger->log('expired_shares_cleanup', [
                'deleted_count' => $deletedCount
            ]);
        }

        return $deletedCount;
    }

    /**
     * Get share analytics
     */
    public function getShareAnalytics($userId, $days = 30) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(s.created_at) as date,
                COUNT(*) as shares_created,
                SUM(s.download_count) as total_downloads
            FROM shares s
            WHERE s.user_id = ? 
            AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(s.created_at)
            ORDER BY date DESC
        ");

        $stmt->execute([$userId, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Private helper methods
     */
    private function generateShareToken() {
        return bin2hex(random_bytes(16));
    }

    private function canUserAccessFile($fileId, $userId) {
        $stmt = $this->db->prepare("
            SELECT file_id FROM files 
            WHERE file_id = ? AND user_id = ?
        ");
        $stmt->execute([$fileId, $userId]);
        return $stmt->fetch() !== false;
    }

    private function incrementDownloadCount($shareId) {
        $stmt = $this->db->prepare("
            UPDATE shares 
            SET download_count = download_count + 1, last_downloaded_at = NOW()
            WHERE share_id = ?
        ");
        $stmt->execute([$shareId]);
    }
}

/**
 * Logger class for activity tracking
 */
class Logger {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function log($action, $data = [], $userId = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO logs (user_id, action, data, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $userId,
                $action,
                json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            // Silent fail for logging to prevent disruption
            error_log("Logging failed: " . $e->getMessage());
        }
    }
}
