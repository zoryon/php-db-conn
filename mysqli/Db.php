<?php

class DB {
    private static $instance = null;
    private $connection;
    private $error = null;

    private string $host = 'localhost';
    private string $db   = 'your_database';
    private string $user = 'your_user';     
    private string $pass = 'your_password'; 

    private function __construct() {
        try {
            $this->connection = new mysqli($this->host, $this->user, $this->pass, $this->db);
        } catch (mysqli_sql_exception $e) {
            throw $e;
        }
    }

    public static function getInstance(): DB {
        if (self::$instance === null) {
            self::$instance = new DB();
        }
        return self::$instance;
    }

    private function getType(mixed $var): string {
        if (is_int($var)) return 'i';
        if (is_float($var)) return 'd'; 
        if (is_bool($var)) return 'i';
        return 's';
    }

    public function execute(string $sql, array $params = []): mysqli_stmt | false {
        try {
            $stmt = $this->connection->prepare($sql);
            if ($stmt === false) {
                $this->error = $this->connection->error;
                return false;
            }

            if (!empty($params)) {
                $types = '';
                foreach ($params as $param) {
                    $types .= $this->getType($param);
                }
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();
            $this->error = null; // Reset error after successful execution
            return $stmt;
        } catch (mysqli_sql_exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    private function buildWhereClause(array $conditions): array {
        if (empty($conditions)) {
            return ['clause' => '', 'params' => []];
        }

        $whereParts = [];
        $params = [];
        foreach ($conditions as $key => $value) {
            // Es: <column name> = ?
            $whereParts[] = "$key = ?";
            // Add the <column name>'s value to the params array
            $params[] = $value;

            // The two arrays work in parallel: the index of an element in $whereParts
            // will be the same as the index of its corresponding value in $params. 
        }

        return [
            'clause' => " WHERE " . implode(' AND ', $whereParts),
            'params' => $params
        ];
    }

    public function select(string $table, array $conditions = []): array | false {
        $where = $this->buildWhereClause($conditions);
        $sql = "SELECT * FROM $table" . $where['clause'];

        $stmt = $this->execute($sql, $where['params']);
        if ($stmt === false) {
            return false;
        }

        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function selectOne(string $table, array $conditions = []): array | null | false {
        $where = $this->buildWhereClause($conditions);
        $sql = "SELECT * FROM $table" . $where['clause'] . " LIMIT 1";

        $stmt = $this->execute($sql, $where['params']);
        if ($stmt === false) {
            return false;
        }

        $result = $stmt->get_result();
        $data = $result->fetch_array(MYSQLI_ASSOC);
        $stmt->close();
        return $data; // Returns array record or null if it was not found
    }

    public function insert(string $table, array $data): int | false {
        if (empty($data)) {
            $this->error = "Missing data.";
            return false;
        }
        $keys = array_keys($data);
        $columns = implode(', ', $keys);
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        $stmt = $this->execute($sql, array_values($data));
        return $stmt ? $this->connection->insert_id : false;
    }

    public function update(string $table, array $data, array $conditions): int | false {
         if (empty($data) || empty($conditions)) {
            $this->error = "Missing Data or conditions.";
            return false;
        }

        $setParts = [];
        $params = [];
        foreach ($data as $key => $value) {
            $setParts[] = "$key = ?";
            $params[] = $value;
        }
        $setClause = implode(', ', $setParts);

        $where = $this->buildWhereClause($conditions);
        $allParams = array_merge($params, $where['params']);

        $sql = "UPDATE $table SET $setClause" . $where['clause'];

        $stmt = $this->execute($sql, $allParams);
        return $stmt ? $stmt->affected_rows : false;
    }

    public function delete(string $table, array $conditions): int|false {
        if (empty($conditions)) {
            $this->error = "Missing conditions.";
            return false;
        }
        $where = $this->buildWhereClause($conditions);
        $sql = "DELETE FROM $table" . $where['clause'];

        $stmt = $this->execute($sql, $where['params']);
        return $stmt ? $stmt->affected_rows : false;
    }

    public function getError(): ?string {
        return $this->error;
    }
}
?>