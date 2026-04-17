<?php
/**
 * Database Connection and Operations Class
 * Simple MySQLi-based database handler with environment variable support
 */

require_once __DIR__ . '/../config/env.php';

// Load environment variables
loadEnv();

class Database {
    private $connection;
    private static $instance = null;
    private $lastQuery = '';
    private $lastParams = [];
    private $queryLog = [];
    private $logQueries = true;

    private function __construct() {
        $this->connect();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Connect to database using environment variables
     */
    private function connect() {
        try {
            $host = env('DB_HOST', 'localhost');
            $user = env('DB_USER', 'root');
            $pass = env('DB_PASS', '');
            $name = env('DB_NAME', 'sales');

            $this->connection = new mysqli($host, $user, $pass, $name);

            if ($this->connection->connect_error) {
                throw new Exception('Connection failed: ' . $this->connection->connect_error);
            }

            $this->connection->set_charset('utf8mb4');

        } catch (Exception $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }
    
    /**
     * Get connection
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Execute query with parameters
     */
    public function query($sql, $params = []) {
        try {
            // Log the query
            $this->logQuery($sql, $params);
            
            $stmt = $this->connection->prepare($sql);
            
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $this->connection->error);
            }
            
            if (!empty($params)) {
                $types = '';
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                }
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            return $stmt;
            
        } catch (Exception $e) {
            $errorMessage = 'Database query error: ' . $e->getMessage() . ' SQL: ' . $sql;
            if (!empty($params)) {
                $errorMessage .= ' Params: ' . json_encode($params);
            }
            error_log($errorMessage);
            
            // In development mode, show detailed error
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                throw new Exception('Database query failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
            } else {
                throw new Exception('Database query failed');
            }
        }
    }
    
    /**
     * Fetch all records
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
    }
    
    /**
     * Fetch single record
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->get_result();
        
        $data = $result->fetch_assoc();
        $stmt->close();
        
        return $data ?: null;
    }
    
    /**
     * Execute insert/update/delete
     */
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $affected_rows = $stmt->affected_rows;
        $insert_id = $this->connection->insert_id;
        $stmt->close();
        
        return [
            'affected_rows' => $affected_rows,
            'insert_id' => $insert_id
        ];
    }
    
    /**
     * Get last insert ID
     */
    public function getLastInsertId() {
        return $this->connection->insert_id;
    }
    
    /**
     * Get affected rows
     */
    public function getAffectedRows() {
        return $this->connection->affected_rows;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        $this->connection->autocommit(false);
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        $this->connection->commit();
        $this->connection->autocommit(true);
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        $this->connection->rollback();
        $this->connection->autocommit(true);
    }
    
    /**
     * Escape string
     */
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    /**
     * Get count of records
     */
    public function getCount($table, $where = '', $params = []) {
        $sql = "SELECT COUNT(*) as count FROM $table";
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        
        $result = $this->fetchOne($sql, $params);
        return (int)($result['count'] ?? 0);
    }
    
    /**
     * Check if table exists
     */
    public function tableExists($tableName) {
        $sql = "SHOW TABLES LIKE ?";
        $result = $this->fetchOne($sql, [$tableName]);
        return !empty($result);
    }
    
    /**
     * Get table columns
     */
    public function getTableColumns($tableName) {
        $sql = "DESCRIBE $tableName";
        return $this->fetchAll($sql);
    }
    
    /**
     * Close connection
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Log query for debugging
     */
    private function logQuery($sql, $params = []) {
        $this->lastQuery = $sql;
        $this->lastParams = $params;
        
        if ($this->logQueries) {
            $this->queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'timestamp' => microtime(true),
                'datetime' => date('Y-m-d H:i:s')
            ];
            
            // Keep only last 100 queries to prevent memory issues
            if (count($this->queryLog) > 100) {
                array_shift($this->queryLog);
            }
        }
    }
    
    /**
     * Get last executed query
     */
    public function getLastQuery() {
        return $this->lastQuery;
    }
    
    /**
     * Get last query parameters
     */
    public function getLastParams() {
        return $this->lastParams;
    }
    
    /**
     * Get formatted last query with parameters
     */
    public function getLastQueryFormatted() {
        if (empty($this->lastQuery)) {
            return 'No queries executed yet';
        }
        
        $query = $this->lastQuery;
        $params = $this->lastParams;
        
        if (!empty($params)) {
            // Replace ? with actual parameter values for display
            $paramIndex = 0;
            $formattedQuery = preg_replace_callback('/\?/', function($matches) use ($params, &$paramIndex) {
                if (isset($params[$paramIndex])) {
                    $value = $params[$paramIndex];
                    $paramIndex++;
                    
                    // Format value based on type
                    if (is_string($value)) {
                        return "'" . addslashes($value) . "'";
                    } elseif (is_null($value)) {
                        return 'NULL';
                    } else {
                        return $value;
                    }
                }
                return '?';
            }, $query);
            
            return $formattedQuery;
        }
        
        return $query;
    }
    
    /**
     * Get all query log
     */
    public function getQueryLog() {
        return $this->queryLog;
    }
    
    /**
     * Get recent queries (last N queries)
     */
    public function getRecentQueries($limit = 10) {
        return array_slice($this->queryLog, -$limit);
    }
    
    /**
     * Clear query log
     */
    public function clearQueryLog() {
        $this->queryLog = [];
        $this->lastQuery = '';
        $this->lastParams = [];
    }
    
    /**
     * Enable/disable query logging
     */
    public function setQueryLogging($enabled = true) {
        $this->logQueries = $enabled;
    }
    
    /**
     * Get query statistics
     */
    public function getQueryStats() {
        $totalQueries = count($this->queryLog);
        $selectQueries = 0;
        $insertQueries = 0;
        $updateQueries = 0;
        $deleteQueries = 0;
        
        foreach ($this->queryLog as $query) {
            $sql = strtoupper(trim($query['sql']));
            if (strpos($sql, 'SELECT') === 0) {
                $selectQueries++;
            } elseif (strpos($sql, 'INSERT') === 0) {
                $insertQueries++;
            } elseif (strpos($sql, 'UPDATE') === 0) {
                $updateQueries++;
            } elseif (strpos($sql, 'DELETE') === 0) {
                $deleteQueries++;
            }
        }
        
        return [
            'total_queries' => $totalQueries,
            'select_queries' => $selectQueries,
            'insert_queries' => $insertQueries,
            'update_queries' => $updateQueries,
            'delete_queries' => $deleteQueries
        ];
    }
    
    /**
     * Debug: Print last query
     */
    public function debugLastQuery() {
        echo "<div style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; border-radius: 4px; margin: 10px 0; font-family: monospace;'>";
        echo "<strong>Last Query:</strong><br>";
        echo "<code>" . htmlspecialchars($this->getLastQueryFormatted()) . "</code><br>";
        if (!empty($this->lastParams)) {
            echo "<strong>Parameters:</strong> " . json_encode($this->lastParams);
        }
        echo "</div>";
    }
    
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
}
?>