<?php
class Db {
    private static $instance = null;
    private $pdo;
    private $error;

    private function __construct() {
        $host = 'localhost';
        $db   = 'your_database';
        $user = 'your_user';
        $pass = 'your_password';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

        try {
            $this->pdo = new PDO($dsn, $user, $pass);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Db();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    // General query executor
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    // SELECT multiple rows
    public function select($table, $conditions = [], $fetchMode = PDO::FETCH_ASSOC) {
        $sql = "SELECT * FROM $table";
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                $where[] = "$key = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $stmt = $this->execute($sql, $params);
        return $stmt ? $stmt->fetchAll($fetchMode) : false;
    }

    // SELECT single row
    public function selectOne($table, $conditions = [], $fetchMode = PDO::FETCH_ASSOC) {
        $result = $this->select($table, $conditions, $fetchMode);
        return $result ? $result[0] : false;
    }

    // INSERT
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        
        $stmt = $this->execute($sql, array_values($data));
        return $stmt ? $this->pdo->lastInsertId() : false;
    }

    // UPDATE
    public function update($table, $data, $conditions) {
        $set = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $set[] = "$key = ?";
            $params[] = $value;
        }
        
        $where = [];
        foreach ($conditions as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }
        
        $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $where);
        $stmt = $this->execute($sql, $params);
        return $stmt ? $stmt->rowCount() : false;
    }

    // DELETE
    public function delete($table, $conditions) {
        $where = [];
        $params = [];
        
        foreach ($conditions as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }
        
        $sql = "DELETE FROM $table WHERE " . implode(' AND ', $where);
        $stmt = $this->execute($sql, $params);
        return $stmt ? $stmt->rowCount() : false;
    }

    // Get last error
    public function getError() {
        return $this->error;
    }
}
?>