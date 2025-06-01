<?php
/**
 * Database Abstraction Layer
 * OS-independent database management for SQLite, MySQL, PostgreSQL
 */

namespace FileServer\Core\Database;

use PDO;
use PDOException;
use Exception;

class Database {
    private static $instance = null;
    private $pdo = null;
    private $config = [];
    private $lastInsertId = null;
    private $affectedRows = 0;
    private $queryLog = [];
    private $transactionDepth = 0;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->config = [
            'type' => DB_TYPE,
            'host' => DB_HOST,
            'port' => DB_PORT,
            'name' => DB_NAME,
            'user' => DB_USER,
            'pass' => DB_PASS,
            'charset' => DB_CHARSET,
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_PERSISTENT => DB_PERSISTENT
            ]
        ];

        $this->connect();
        $this->createDatabaseIfNotExists();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Connect to database
     */
    private function connect(): void {
        try {
            $dsn = $this->buildDSN();
            $this->pdo = new PDO($dsn, $this->config['user'], $this->config['pass'], $this->config['options']);
            
            // Set specific options per database type
            switch ($this->config['type']) {
                case 'mysql':
                    $this->pdo->exec("SET sql_mode='STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
                    $this->pdo->exec("SET time_zone = '+00:00'");
                    break;
                case 'pgsql':
                    $this->pdo->exec("SET timezone = 'UTC'");
                    break;
                case 'sqlite':
                    $this->pdo->exec("PRAGMA foreign_keys = ON");
                    $this->pdo->exec("PRAGMA journal_mode = WAL");
                    $this->pdo->exec("PRAGMA synchronous = NORMAL");
                    break;
            }

            $this->log("Database connected successfully", 'info');
        } catch (PDOException $e) {
            $this->log("Database connection failed: " . $e->getMessage(), 'error');
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Build DSN string based on database type
     */
    private function buildDSN(): string {
        switch ($this->config['type']) {
            case 'mysql':
                return sprintf(
                    "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['name'],
                    $this->config['charset']
                );
            
            case 'pgsql':
                return sprintf(
                    "pgsql:host=%s;port=%s;dbname=%s",
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['name']
                );
            
            case 'sqlite':
                $dbPath = storage_path('database' . DIRECTORY_SEPARATOR . $this->config['name'] . '.db');
                $dbDir = dirname($dbPath);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }
                return "sqlite:" . $dbPath;
            
            default:
                throw new Exception("Unsupported database type: " . $this->config['type']);
        }
    }

    /**
     * Create database if it doesn't exist (MySQL/PostgreSQL only)
     */
    private function createDatabaseIfNotExists(): void {
        if ($this->config['type'] === 'sqlite') {
            return; // SQLite creates database automatically
        }

        try {
            // Try to select from the database
            $this->pdo->query("SELECT 1")->fetchAll();
        } catch (PDOException $e) {
            // Database doesn't exist, create it
            try {
                $tempDsn = $this->buildTempDSN();
                $tempPdo = new PDO($tempDsn, $this->config['user'], $this->config['pass']);
                
                if ($this->config['type'] === 'mysql') {
                    $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->config['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                } else {
                    $tempPdo->exec("CREATE DATABASE \"{$this->config['name']}\"");
                }
                
                $tempPdo = null;
                $this->connect(); // Reconnect to the new database
                
                $this->log("Database '{$this->config['name']}' created successfully", 'info');
            } catch (PDOException $createException) {
                $this->log("Failed to create database: " . $createException->getMessage(), 'error');
                throw new Exception("Failed to create database: " . $createException->getMessage());
            }
        }
    }

    /**
     * Build temporary DSN for database creation
     */
    private function buildTempDSN(): string {
        switch ($this->config['type']) {
            case 'mysql':
                return sprintf(
                    "mysql:host=%s;port=%s;charset=%s",
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['charset']
                );
            case 'pgsql':
                return sprintf(
                    "pgsql:host=%s;port=%s;dbname=postgres",
                    $this->config['host'],
                    $this->config['port']
                );
            default:
                throw new Exception("Cannot create database for type: " . $this->config['type']);
        }
    }

    /**
     * Execute a query
     */
    public function query(string $sql, array $params = []): \PDOStatement {
        try {
            $startTime = microtime(true);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $this->affectedRows = $stmt->rowCount();
            $this->lastInsertId = $this->pdo->lastInsertId();
            
            $executionTime = microtime(true) - $startTime;
            $this->logQuery($sql, $params, $executionTime);
            
            return $stmt;
        } catch (PDOException $e) {
            $this->log("Query failed: " . $e->getMessage() . " SQL: " . $sql, 'error');
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Execute multiple queries in transaction
     */
    public function transaction(callable $callback) {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): void {
        if ($this->transactionDepth === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT level{$this->transactionDepth}");
        }
        $this->transactionDepth++;
    }

    /**
     * Commit transaction
     */
    public function commit(): void {
        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec("RELEASE SAVEPOINT level{$this->transactionDepth}");
        }
    }

    /**
     * Rollback transaction
     */
    public function rollback(): void {
        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            $this->pdo->rollBack();
        } else {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT level{$this->transactionDepth}");
        }
    }

    /**
     * Get all records
     */
    public function select(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Get single record
     */
    public function selectOne(string $sql, array $params = []): ?array {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Insert record
     */
    public function insert(string $table, array $data): bool {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->affectedRows > 0;
    }

    /**
     * Update record
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): bool {
        $setParts = [];
        foreach (array_keys($data) as $key) {
            $setParts[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        $this->query($sql, $params);
        
        return $this->affectedRows > 0;
    }

    /**
     * Delete record
     */
    public function delete(string $table, string $where, array $params = []): bool {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $this->query($sql, $params);
        
        return $this->affectedRows > 0;
    }

    /**
     * Get table schema
     */
    public function getTableSchema(string $table): array {
        switch ($this->config['type']) {
            case 'mysql':
                return $this->select("DESCRIBE {$table}");
            case 'pgsql':
                return $this->select(
                    "SELECT column_name, data_type, is_nullable, column_default 
                     FROM information_schema.columns 
                     WHERE table_name = ?",
                    [$table]
                );
            case 'sqlite':
                return $this->select("PRAGMA table_info({$table})");
            default:
                return [];
        }
    }

    /**
     * Check if table exists
     */
    public function tableExists(string $table): bool {
        switch ($this->config['type']) {
            case 'mysql':
                $result = $this->selectOne(
                    "SELECT COUNT(*) as count FROM information_schema.tables 
                     WHERE table_schema = ? AND table_name = ?",
                    [$this->config['name'], $table]
                );
                break;
            case 'pgsql':
                $result = $this->selectOne(
                    "SELECT COUNT(*) as count FROM information_schema.tables 
                     WHERE table_name = ?",
                    [$table]
                );
                break;
            case 'sqlite':
                $result = $this->selectOne(
                    "SELECT COUNT(*) as count FROM sqlite_master 
                     WHERE type = 'table' AND name = ?",
                    [$table]
                );
                break;
            default:
                return false;
        }
        
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Get database type specific SQL
     */
    public function getSQL(string $key, array $replacements = []): string {
        $sqlMap = [
            'auto_increment' => [
                'mysql' => 'AUTO_INCREMENT',
                'pgsql' => 'SERIAL',
                'sqlite' => 'AUTOINCREMENT'
            ],
            'current_timestamp' => [
                'mysql' => 'CURRENT_TIMESTAMP',
                'pgsql' => 'CURRENT_TIMESTAMP',
                'sqlite' => 'CURRENT_TIMESTAMP'
            ],
            'text_type' => [
                'mysql' => 'TEXT',
                'pgsql' => 'TEXT',
                'sqlite' => 'TEXT'
            ],
            'blob_type' => [
                'mysql' => 'LONGBLOB',
                'pgsql' => 'BYTEA',
                'sqlite' => 'BLOB'
            ],
            'json_type' => [
                'mysql' => 'JSON',
                'pgsql' => 'JSONB',
                'sqlite' => 'TEXT'
            ]
        ];

        $sql = $sqlMap[$key][$this->config['type']] ?? '';
        
        foreach ($replacements as $search => $replace) {
            $sql = str_replace($search, $replace, $sql);
        }
        
        return $sql;
    }

    /**
     * Get last insert ID
     */
    public function getLastInsertId(): ?string {
        return $this->lastInsertId;
    }

    /**
     * Get affected rows count
     */
    public function getAffectedRows(): int {
        return $this->affectedRows;
    }

    /**
     * Get database type
     */
    public function getType(): string {
        return $this->config['type'];
    }

    /**
     * Get PDO instance
     */
    public function getPDO(): PDO {
        return $this->pdo;
    }

    /**
     * Log query execution
     */
    private function logQuery(string $sql, array $params, float $executionTime): void {
        if (DB_QUERY_LOG) {
            $this->queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'execution_time' => $executionTime,
                'timestamp' => time()
            ];

            // Keep only last 100 queries to prevent memory issues
            if (count($this->queryLog) > 100) {
                array_shift($this->queryLog);
            }
        }
    }

    /**
     * Get query log
     */
    public function getQueryLog(): array {
        return $this->queryLog;
    }

    /**
     * Clear query log
     */
    public function clearQueryLog(): void {
        $this->queryLog = [];
    }

    /**
     * Log database events
     */
    private function log(string $message, string $level = 'info'): void {
        if (defined('LOG_ENABLED') && LOG_ENABLED) {
            $logMessage = "[" . date('Y-m-d H:i:s') . "] [DATABASE] [{$level}] {$message}" . PHP_EOL;
            $logFile = storage_path('logs' . DIRECTORY_SEPARATOR . 'database.log');
            
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Close connection
     */
    public function close(): void {
        $this->pdo = null;
        self::$instance = null;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
    }
}
