<?php
/**
 * Database Helper - PDO Wrapper with Multi-Tenant Support
 */

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $config = require __DIR__ . '/../config/database.php';
        
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
            
            // Set MySQL session timezone to Asia/Kolkata (IST) for proper timestamp handling
            $appConfig = require __DIR__ . '/../config/app.php';
            $timezone = $appConfig['timezone'] ?? 'Asia/Kolkata';
            $this->pdo->exec("SET time_zone = '+05:30'"); // IST is UTC+5:30
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            if (!$stmt) {
                $errorInfo = $this->pdo->errorInfo();
                $errorMsg = "PDO Prepare Error: " . ($errorInfo[2] ?? 'Unknown error') . " | SQL: " . $sql;
                error_log($errorMsg);
                throw new Exception("Database query failed: " . ($errorInfo[2] ?? 'Prepare statement failed'));
            }
            
            $result = $stmt->execute($params);
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                $errorMsg = "PDO Execute Error: " . ($errorInfo[2] ?? 'Unknown error') . " | SQL: " . $sql . " | Params: " . json_encode($params);
                error_log($errorMsg);
                throw new Exception("Database query failed: " . ($errorInfo[2] ?? 'Execute failed'));
            }
            
            return $stmt;
        } catch (PDOException $e) {
            $errorMsg = "PDO Exception: " . $e->getMessage() . " | Code: " . $e->getCode() . " | SQL: " . $sql . " | Params: " . json_encode($params);
            error_log($errorMsg);
            // Preserve the actual error message
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            $rowCount = $stmt->rowCount();
            error_log("Execute successful - SQL: " . substr($sql, 0, 100) . "... | Rows affected: {$rowCount}");
            return $rowCount;
        } catch (Exception $e) {
            error_log("Database Execute Error: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . json_encode($params));
            throw $e;
        }
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollBack() {
        return $this->pdo->rollBack();
    }
    
    /**
     * Scoped query - automatically inject company_id filter
     */
    public function scopedQuery($sql, $companyId, $params = []) {
        // Add company_id to params
        array_unshift($params, $companyId);
        
        // Inject WHERE company_id = ? or AND company_id = ?
        if (stripos($sql, 'WHERE') !== false) {
            $sql = str_ireplace('WHERE', 'WHERE company_id = ? AND', $sql);
        } else if (stripos($sql, 'FROM') !== false) {
            // Add WHERE clause after FROM table_name
            $sql = preg_replace('/FROM\s+`?(\w+)`?/i', 'FROM `$1` WHERE company_id = ?', $sql, 1);
        }
        
        return $this->query($sql, $params);
    }
}




