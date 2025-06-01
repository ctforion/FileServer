<?php
/**
 * JSON Database System
 * Provides CRUD operations for JSON-based data storage
 */

class JsonDatabase {
    private $dataPath;
    private $cache = [];
    private $lockFiles = [];
    
    public function __construct($dataPath = null) {
        $this->dataPath = $dataPath ?? __DIR__ . '/../../data';
        $this->ensureDataDirectory();
    }
    
    /**
     * Ensure data directory exists with proper permissions
     */
    private function ensureDataDirectory() {
        if (!is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0755, true);
        }
        
        // Create subdirectories
        $subdirs = ['logs'];
        foreach ($subdirs as $dir) {
            $fullPath = $this->dataPath . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
        }
        
        // Create .htaccess to protect data directory
        $htaccessPath = $this->dataPath . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "Order Deny,Allow\nDeny from all\n");
        }
    }
    
    /**
     * Get file path for a table
     */
    private function getTablePath($table) {
        return $this->dataPath . '/' . $table . '.json';
    }
    
    /**
     * Get lock file path for a table
     */
    private function getLockPath($table) {
        return $this->dataPath . '/' . $table . '.lock';
    }
    
    /**
     * Acquire lock for table operations
     */
    private function acquireLock($table) {
        $lockFile = $this->getLockPath($table);
        $handle = fopen($lockFile, 'w');
        
        if (!$handle || !flock($handle, LOCK_EX)) {
            throw new Exception("Could not acquire lock for table: $table");
        }
        
        $this->lockFiles[$table] = $handle;
        return true;
    }
    
    /**
     * Release lock for table operations
     */
    private function releaseLock($table) {
        if (isset($this->lockFiles[$table])) {
            flock($this->lockFiles[$table], LOCK_UN);
            fclose($this->lockFiles[$table]);
            unset($this->lockFiles[$table]);
            
            $lockFile = $this->getLockPath($table);
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }
    
    /**
     * Load table data from JSON file
     */
    public function load($table) {
        if (isset($this->cache[$table])) {
            return $this->cache[$table];
        }
        
        $filePath = $this->getTablePath($table);
        
        if (!file_exists($filePath)) {
            $this->cache[$table] = [];
            return [];
        }
        
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in table $table: " . json_last_error_msg());
        }
        
        $this->cache[$table] = $data ?? [];
        return $this->cache[$table];
    }
    
    /**
     * Save table data to JSON file
     */
    public function save($table, $data) {
        $this->acquireLock($table);
        
        try {
            $filePath = $this->getTablePath($table);
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON encoding error: " . json_last_error_msg());
            }
            
            // Atomic write
            $tempFile = $filePath . '.tmp';
            if (file_put_contents($tempFile, $json) === false) {
                throw new Exception("Could not write to temporary file");
            }
            
            if (!rename($tempFile, $filePath)) {
                unlink($tempFile);
                throw new Exception("Could not move temporary file to final location");
            }
            
            // Update cache
            $this->cache[$table] = $data;
            
        } finally {
            $this->releaseLock($table);
        }
        
        return true;
    }
    
    /**
     * Insert new record into table
     */
    public function insert($table, $id, $data) {
        $tableData = $this->load($table);
        
        if (isset($tableData[$id])) {
            throw new Exception("Record with ID '$id' already exists in table '$table'");
        }
        
        $data['id'] = $id;
        $data['created'] = date('c');
        $data['updated'] = date('c');
        
        $tableData[$id] = $data;
        
        return $this->save($table, $tableData);
    }
    
    /**
     * Update existing record in table
     */
    public function update($table, $id, $data) {
        $tableData = $this->load($table);
        
        if (!isset($tableData[$id])) {
            throw new Exception("Record with ID '$id' not found in table '$table'");
        }
        
        $data['id'] = $id;
        $data['created'] = $tableData[$id]['created'] ?? date('c');
        $data['updated'] = date('c');
        
        $tableData[$id] = array_merge($tableData[$id], $data);
        
        return $this->save($table, $tableData);
    }
    
    /**
     * Get single record from table
     */
    public function get($table, $id) {
        $tableData = $this->load($table);
        return $tableData[$id] ?? null;
    }
    
    /**
     * Get all records from table
     */
    public function getAll($table) {
        return $this->load($table);
    }
    
    /**
     * Delete record from table
     */
    public function delete($table, $id) {
        $tableData = $this->load($table);
        
        if (!isset($tableData[$id])) {
            return false;
        }
        
        unset($tableData[$id]);
        
        return $this->save($table, $tableData);
    }
    
    /**
     * Search records in table
     */
    public function search($table, $criteria = []) {
        $tableData = $this->load($table);
        
        if (empty($criteria)) {
            return $tableData;
        }
        
        $results = [];
        
        foreach ($tableData as $id => $record) {
            $match = true;
            
            foreach ($criteria as $field => $value) {
                if (!isset($record[$field]) || $record[$field] !== $value) {
                    $match = false;
                    break;
                }
            }
            
            if ($match) {
                $results[$id] = $record;
            }
        }
        
        return $results;
    }
    
    /**
     * Count records in table
     */
    public function count($table) {
        $tableData = $this->load($table);
        return count($tableData);
    }
    
    /**
     * Backup table to file
     */
    public function backup($table, $backupPath = null) {
        $tableData = $this->load($table);
        
        if ($backupPath === null) {
            $backupPath = $this->dataPath . '/backups/' . $table . '_' . date('Y-m-d_H-i-s') . '.json';
        }
        
        $backupDir = dirname($backupPath);
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $json = json_encode($tableData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        return file_put_contents($backupPath, $json) !== false;
    }
    
    /**
     * Restore table from backup
     */
    public function restore($table, $backupPath) {
        if (!file_exists($backupPath)) {
            throw new Exception("Backup file not found: $backupPath");
        }
        
        $content = file_get_contents($backupPath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in backup file: " . json_last_error_msg());
        }
        
        return $this->save($table, $data);
    }
    
    /**
     * Clear cache for table
     */
    public function clearCache($table = null) {
        if ($table === null) {
            $this->cache = [];
        } else {
            unset($this->cache[$table]);
        }
    }
    
    /**
     * Get database statistics
     */
    public function getStats() {
        $stats = [
            'tables' => [],
            'total_records' => 0,
            'total_size' => 0
        ];
        
        $files = glob($this->dataPath . '/*.json');
        
        foreach ($files as $file) {
            $table = basename($file, '.json');
            $size = filesize($file);
            $count = $this->count($table);
            
            $stats['tables'][$table] = [
                'records' => $count,
                'size' => $size,
                'last_modified' => date('c', filemtime($file))
            ];
            
            $stats['total_records'] += $count;
            $stats['total_size'] += $size;
        }
        
        return $stats;
    }
    
    /**
     * Save entire dataset to table (replaces all data)
     */
    public function saveAll($table, $data) {
        // Ensure proper format for data
        $formattedData = [];
        foreach ($data as $record) {
            $id = $record['id'] ?? uniqid();
            $record['id'] = $id;
            $record['updated'] = date('c');
            if (!isset($record['created'])) {
                $record['created'] = date('c');
            }
            $formattedData[$id] = $record;
        }
        
        return $this->save($table, $formattedData);
    }

    /**
     * Cleanup: Close all locks
     */
    public function __destruct() {
        foreach ($this->lockFiles as $table => $handle) {
            $this->releaseLock($table);
        }
    }
}
?>
