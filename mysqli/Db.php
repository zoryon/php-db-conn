<?php

class DB
{
    private static $instance = null;
    private $connection;
    private $error = null;

    private function __construct()
    {
        try {
            $this->connection = new mysqli('localhost', 'root', '', 'test');
        } catch (mysqli_sql_exception $e) {
            $this->error = $e->getMessage();
            throw $e;
        }
    }

    public static function getInstance(): DB
    {
        if (self::$instance === null) {
            self::$instance = new DB();
        }
        return self::$instance;
    }

    private function formatParam(mixed $param): string
    {
        if ($param === null) {
            return 'NULL';
        }
        
        if (is_int($param) || is_bool($param)) {
            return (string)(int)$param;
        }
        
        if (is_float($param)) {
            return (string)(float)$param;
        }
        
        return "'" . $this->connection->real_escape_string((string)$param) . "'";
    }

    public function execute(string $sql): mysqli_result | bool
    {
        try {
            $result = $this->connection->query($sql);
            if ($result === false) {
                $this->error = $this->connection->error;
                return false;
            }
            $this->error = null;
            return $result;
        } catch (mysqli_sql_exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    private function buildWhereClause(array $conditions): string
    {
        if (empty($conditions)) {
            return "";
        }

        $whereParts = [];
        foreach ($conditions as $key => $value) {
            $formattedValue = $this->formatParam($value);
            $whereParts[] = "$key = $formattedValue";
        }

        return " WHERE " . implode(' AND ', $whereParts);
    }

    public function select(string $table, array $conditions = []): array | false
    {
        $where = $this->buildWhereClause($conditions);
        $sql = "SELECT * FROM $table " . $where;

        $result = $this->execute($sql);
        if ($result === false) {
            return false;
        }

        $data = $result->fetch_all(MYSQLI_ASSOC);
        return $data;
    }

    public function selectOne(string $table, array $conditions = []): array | null | false
    {
        $result = $this->select($table, $conditions);
        if ($result === false) {
            return false;
        }
        return $result[0] ?? null;
    }

    public function insert(string $table, array $data): int | false
    {
        $columns = implode(', ', array_keys($data));
        $values = array_map([$this, 'formatParam'], array_values($data));
        $valuesStr = implode(', ', $values);
        $sql = "INSERT INTO $table ($columns) VALUES ($valuesStr)";

        $result = $this->execute($sql);
        return $result !== false ? $this->connection->insert_id : false;
    }

    public function update(string $table, array $data, array $conditions): int | false
    {
        $setParts = [];
        foreach ($data as $key => $value) {
            $formattedValue = $this->formatParam($value);
            $setParts[] = "$key = $formattedValue";
        }
        $setClause = implode(', ', $setParts);

        $where = $this->buildWhereClause($conditions);
        $sql = "UPDATE $table SET $setClause " . $where;

        $result = $this->execute($sql);
        return $result !== false ? $this->connection->affected_rows : false;
    }

    public function delete(string $table, array $conditions): int | false
    {
        $where = $this->buildWhereClause($conditions);
        $sql = "DELETE FROM $table " . $where;

        $result = $this->execute($sql);
        return $result !== false ? $this->connection->affected_rows : false;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
?>

<!-- 
    public function create(Request $request, Response $response, $args)
    {
        // curl -X 'POST' -d '{"alunno_id": 3, "titolo": "CREATA ORA", "votazione": 54, "ente": "CREATA ORA"}' http://localhost:8080/alunni/3/certificazioni

        $db = DB::getInstance();
        $data = json_decode($request->getBody()->getContents(), true);

        $newId = $db->insert("certificazioni", $data);

        $response->getBody()->write(json_encode($newId, true));
        return $response->withHeader("Content-type","application/json")->withStatus(200);
    } 

    // execute method example
    $result = $db->execute('SELECT * FROM users WHERE id = 7');
    $users = $result->fetch_all(MYSQLI_ASSOC);
-->