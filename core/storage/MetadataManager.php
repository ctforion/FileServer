<?php
/**
 * Metadata Manager
 * File metadata and tracking system
 */

require_once __DIR__ . '/../database/DatabaseManager.php';
require_once __DIR__ . '/../logging/Logger.php';

class MetadataManager {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->logger = new Logger();
    }
    
    /**
     * Add file metadata
     */
    public function addFileMetadata($filePath, $metadata) {
        try {
            $fileId = $this->generateFileId($filePath);
            
            $fileData = [
                'id' => $fileId,
                'filename' => basename($filePath),
                'path' => $filePath,
                'owner' => $_SESSION['username'] ?? 'unknown',
                'size' => filesize($filePath),
                'mime_type' => $this->getMimeType($filePath),
                'uploaded' => date('c'),
                'last_accessed' => date('c'),
                'last_modified' => date('c', filemtime($filePath)),
                'permissions' => $metadata['permissions'] ?? ['owner'],
                'tags' => $metadata['tags'] ?? [],
                'description' => $metadata['description'] ?? '',
                'category' => $metadata['category'] ?? 'general',
                'public' => $metadata['public'] ?? false,
                'download_count' => 0,
                'checksum' => $this->calculateChecksum($filePath),
                'version' => 1,
                'versions' => [],
                'metadata' => $metadata
            ];
            
            $result = $this->db->addFile($fileId, $fileData);
            
            $this->logger->logAccess('file_metadata_added', [
                'file_id' => $fileId,
                'filename' => $fileData['filename'],
                'user' => $fileData['owner'],
                'size' => $fileData['size']
            ]);
            
            return $result ? $fileId : false;
        } catch (Exception $e) {
            $this->logger->logError('metadata_add_error', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update file metadata
     */
    public function updateFileMetadata($fileId, $metadata) {
        try {
            $currentData = $this->db->getFile($fileId);
            if (!$currentData) {
                throw new Exception("File not found: $fileId");
            }
            
            // Merge new metadata with existing
            $updatedData = array_merge($currentData, [
                'last_modified' => date('c'),
                'tags' => $metadata['tags'] ?? $currentData['tags'],
                'description' => $metadata['description'] ?? $currentData['description'],
                'category' => $metadata['category'] ?? $currentData['category'],
                'permissions' => $metadata['permissions'] ?? $currentData['permissions'],
                'metadata' => array_merge($currentData['metadata'] ?? [], $metadata)
            ]);
            
            $result = $this->db->updateFile($fileId, $updatedData);
            
            $this->logger->logAccess('file_metadata_updated', [
                'file_id' => $fileId,
                'user' => $_SESSION['username'] ?? 'unknown',
                'changes' => array_keys($metadata)
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->logger->logError('metadata_update_error', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get file metadata
     */
    public function getFileMetadata($fileId) {
        try {
            $metadata = $this->db->getFile($fileId);
            
            if ($metadata) {
                // Update last accessed time
                $this->updateLastAccessed($fileId);
            }
            
            return $metadata;
        } catch (Exception $e) {
            $this->logger->logError('metadata_get_error', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Search files by metadata
     */
    public function searchFiles($criteria) {
        try {
            $files = $this->db->getFiles();
            $results = [];
            
            foreach ($files['files'] ?? [] as $fileId => $file) {
                if ($this->matchesCriteria($file, $criteria)) {
                    $results[$fileId] = $file;
                }
            }
            
            // Sort results by relevance
            $results = $this->sortByRelevance($results, $criteria);
            
            $this->logger->logAccess('file_search', [
                'criteria' => $criteria,
                'results_count' => count($results),
                'user' => $_SESSION['username'] ?? 'unknown'
            ]);
            
            return $results;
        } catch (Exception $e) {
            $this->logger->logError('file_search_error', [
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Add file version
     */
    public function addFileVersion($fileId, $newFilePath, $versionNote = '') {
        try {
            $fileData = $this->db->getFile($fileId);
            if (!$fileData) {
                throw new Exception("File not found: $fileId");
            }
            
            // Create version entry
            $version = [
                'version' => $fileData['version'] + 1,
                'path' => $newFilePath,
                'uploaded' => date('c'),
                'user' => $_SESSION['username'] ?? 'unknown',
                'size' => filesize($newFilePath),
                'checksum' => $this->calculateChecksum($newFilePath),
                'note' => $versionNote
            ];
            
            // Add current version to history
            $fileData['versions'][] = [
                'version' => $fileData['version'],
                'path' => $fileData['path'],
                'uploaded' => $fileData['uploaded'],
                'user' => $fileData['owner'],
                'size' => $fileData['size'],
                'checksum' => $fileData['checksum']
            ];
            
            // Update current file data
            $fileData['version'] = $version['version'];
            $fileData['path'] = $newFilePath;
            $fileData['size'] = $version['size'];
            $fileData['checksum'] = $version['checksum'];
            $fileData['last_modified'] = $version['uploaded'];
            
            $result = $this->db->updateFile($fileId, $fileData);
            
            $this->logger->logAccess('file_version_added', [
                'file_id' => $fileId,
                'version' => $version['version'],
                'user' => $version['user'],
                'note' => $versionNote
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->logger->logError('file_version_error', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get file versions
     */
    public function getFileVersions($fileId) {
        try {
            $fileData = $this->db->getFile($fileId);
            if (!$fileData) {
                return [];
            }
            
            $versions = $fileData['versions'] ?? [];
            
            // Add current version
            $versions[] = [
                'version' => $fileData['version'],
                'path' => $fileData['path'],
                'uploaded' => $fileData['uploaded'],
                'user' => $fileData['owner'],
                'size' => $fileData['size'],
                'checksum' => $fileData['checksum'],
                'current' => true
            ];
            
            // Sort by version number (latest first)
            usort($versions, function($a, $b) {
                return $b['version'] - $a['version'];
            });
            
            return $versions;
        } catch (Exception $e) {
            $this->logger->logError('file_versions_error', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Track file download
     */
    public function trackDownload($fileId, $userInfo = []) {
        try {
            $fileData = $this->db->getFile($fileId);
            if (!$fileData) {
                return false;
            }
            
            // Update download count
            $fileData['download_count'] = ($fileData['download_count'] ?? 0) + 1;
            $fileData['last_accessed'] = date('c');
            
            // Add to download history
            if (!isset($fileData['download_history'])) {
                $fileData['download_history'] = [];
            }
            
            $fileData['download_history'][] = [
                'timestamp' => date('c'),
                'user' => $_SESSION['username'] ?? 'anonymous',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
            
            // Keep only last 100 downloads
            if (count($fileData['download_history']) > 100) {
                $fileData['download_history'] = array_slice($fileData['download_history'], -100);
            }
            
            $result = $this->db->updateFile($fileId, $fileData);
            
            $this->logger->logAccess('file_downloaded', [
                'file_id' => $fileId,
                'filename' => $fileData['filename'],
                'user' => $_SESSION['username'] ?? 'anonymous',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'size' => $fileData['size']
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->logger->logError('download_tracking_error', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get file statistics
     */
    public function getFileStatistics($fileId) {
        try {
            $fileData = $this->db->getFile($fileId);
            if (!$fileData) {
                return null;
            }
            
            $downloadHistory = $fileData['download_history'] ?? [];
            
            $stats = [
                'total_downloads' => $fileData['download_count'] ?? 0,
                'unique_users' => count(array_unique(array_column($downloadHistory, 'user'))),
                'last_downloaded' => $fileData['last_accessed'] ?? null,
                'upload_date' => $fileData['uploaded'] ?? null,
                'file_age_days' => $this->calculateFileAge($fileData['uploaded'] ?? null),
                'average_downloads_per_day' => $this->calculateAverageDownloads($fileData),
                'popular_hours' => $this->getPopularDownloadHours($downloadHistory),
                'recent_activity' => array_slice($downloadHistory, -10) // Last 10 downloads
            ];
            
            return $stats;
        } catch (Exception $e) {
            $this->logger->logError('file_statistics_error', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Clean up orphaned metadata
     */
    public function cleanupOrphanedMetadata() {
        try {
            $files = $this->db->getFiles();
            $orphaned = [];
            
            foreach ($files['files'] ?? [] as $fileId => $fileData) {
                $filePath = $fileData['path'] ?? '';
                if (!empty($filePath) && !file_exists($filePath)) {
                    $orphaned[] = $fileId;
                }
            }
            
            $cleaned = 0;
            foreach ($orphaned as $fileId) {
                if ($this->db->deleteFile($fileId)) {
                    $cleaned++;
                }
            }
            
            $this->logger->logSystem('metadata_cleanup', [
                'orphaned_found' => count($orphaned),
                'cleaned' => $cleaned
            ]);
            
            return $cleaned;
        } catch (Exception $e) {
            $this->logger->logError('metadata_cleanup_error', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Get files by owner
     */
    public function getFilesByOwner($username, $includeShared = false) {
        try {
            $files = $this->db->getFiles();
            $userFiles = [];
            
            foreach ($files['files'] ?? [] as $fileId => $file) {
                $isOwner = ($file['owner'] ?? '') === $username;
                $hasAccess = $includeShared && in_array($username, $file['permissions'] ?? []);
                
                if ($isOwner || $hasAccess) {
                    $userFiles[$fileId] = $file;
                }
            }
            
            return $userFiles;
        } catch (Exception $e) {
            $this->logger->logError('get_files_by_owner_error', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Generate file ID
     */
    private function generateFileId($filePath) {
        return 'file_' . hash('sha256', $filePath . time() . rand());
    }
    
    /**
     * Get MIME type
     */
    private function getMimeType($filePath) {
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        } elseif (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            return finfo_file($finfo, $filePath);
        } else {
            // Fallback to extension-based detection
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'txt' => 'text/plain',
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'zip' => 'application/zip',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];
            return $mimeTypes[$ext] ?? 'application/octet-stream';
        }
    }
    
    /**
     * Calculate file checksum
     */
    private function calculateChecksum($filePath) {
        return hash_file('sha256', $filePath);
    }
    
    /**
     * Update last accessed time
     */
    private function updateLastAccessed($fileId) {
        try {
            $fileData = $this->db->getFile($fileId);
            if ($fileData) {
                $fileData['last_accessed'] = date('c');
                $this->db->updateFile($fileId, $fileData);
            }
        } catch (Exception $e) {
            // Silent fail for this non-critical operation
        }
    }
    
    /**
     * Check if file matches search criteria
     */
    private function matchesCriteria($file, $criteria) {
        // Text search in filename and description
        if (!empty($criteria['query'])) {
            $query = strtolower($criteria['query']);
            $filename = strtolower($file['filename'] ?? '');
            $description = strtolower($file['description'] ?? '');
            
            if (strpos($filename, $query) === false && strpos($description, $query) === false) {
                return false;
            }
        }
        
        // Category filter
        if (!empty($criteria['category']) && $criteria['category'] !== ($file['category'] ?? '')) {
            return false;
        }
        
        // Tag filter
        if (!empty($criteria['tags'])) {
            $fileTags = $file['tags'] ?? [];
            foreach ($criteria['tags'] as $tag) {
                if (!in_array($tag, $fileTags)) {
                    return false;
                }
            }
        }
        
        // File type filter
        if (!empty($criteria['type'])) {
            $ext = strtolower(pathinfo($file['filename'] ?? '', PATHINFO_EXTENSION));
            if ($ext !== strtolower($criteria['type'])) {
                return false;
            }
        }
        
        // Owner filter
        if (!empty($criteria['owner']) && $criteria['owner'] !== ($file['owner'] ?? '')) {
            return false;
        }
        
        // Date range filter
        if (!empty($criteria['date_from']) || !empty($criteria['date_to'])) {
            $fileDate = strtotime($file['uploaded'] ?? '');
            
            if (!empty($criteria['date_from']) && $fileDate < strtotime($criteria['date_from'])) {
                return false;
            }
            
            if (!empty($criteria['date_to']) && $fileDate > strtotime($criteria['date_to'])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Sort search results by relevance
     */
    private function sortByRelevance($results, $criteria) {
        if (empty($criteria['query'])) {
            return $results;
        }
        
        $query = strtolower($criteria['query']);
        
        uasort($results, function($a, $b) use ($query) {
            $scoreA = $this->calculateRelevanceScore($a, $query);
            $scoreB = $this->calculateRelevanceScore($b, $query);
            return $scoreB - $scoreA;
        });
        
        return $results;
    }
    
    /**
     * Calculate relevance score for search results
     */
    private function calculateRelevanceScore($file, $query) {
        $score = 0;
        $filename = strtolower($file['filename'] ?? '');
        $description = strtolower($file['description'] ?? '');
        
        // Exact filename match gets highest score
        if ($filename === $query) {
            $score += 100;
        }
        
        // Filename starts with query
        if (strpos($filename, $query) === 0) {
            $score += 50;
        }
        
        // Filename contains query
        if (strpos($filename, $query) !== false) {
            $score += 25;
        }
        
        // Description contains query
        if (strpos($description, $query) !== false) {
            $score += 10;
        }
        
        // Boost score for popular files
        $score += min(($file['download_count'] ?? 0) / 10, 10);
        
        return $score;
    }
    
    /**
     * Calculate file age in days
     */
    private function calculateFileAge($uploadDate) {
        if (!$uploadDate) {
            return 0;
        }
        
        $uploadTime = strtotime($uploadDate);
        $currentTime = time();
        
        return round(($currentTime - $uploadTime) / 86400);
    }
    
    /**
     * Calculate average downloads per day
     */
    private function calculateAverageDownloads($fileData) {
        $downloads = $fileData['download_count'] ?? 0;
        $age = $this->calculateFileAge($fileData['uploaded'] ?? null);
        
        if ($age <= 0) {
            return $downloads;
        }
        
        return round($downloads / $age, 2);
    }
    
    /**
     * Get popular download hours
     */
    private function getPopularDownloadHours($downloadHistory) {
        $hours = [];
        
        foreach ($downloadHistory as $download) {
            $hour = date('H', strtotime($download['timestamp']));
            $hours[$hour] = ($hours[$hour] ?? 0) + 1;
        }
        
        arsort($hours);
        return array_slice($hours, 0, 5, true);
    }
}
