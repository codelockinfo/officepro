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
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Database query failed");
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
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
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




