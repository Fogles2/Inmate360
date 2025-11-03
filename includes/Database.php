<?php

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    // Constructor to initialize the database connection parameters
    public function __construct() {
        $this->host = 'localhost'; // Database host
        $this->db_name = 'inmate360'; // Database name
        $this->username = 'root'; // Database username
        $this->password = ''; // Database password

        $this->connect(); // Call connect method
    }

    // Method to establish a database connection
    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo 'Database connection established successfully';
        } catch (PDOException $exception) {
            echo 'Connection error: ' . $exception->getMessage();
        }
        return $this->conn;
    }

    // Method to run a query
    public function runQuery($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // Method to fetch all records from a table
    public function fetchAll($table) {
        $sql = 'SELECT * FROM ' . $table;
        return $this->runQuery($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to fetch a single record by ID
    public function fetchById($table, $id) {
        $sql = 'SELECT * FROM ' . $table . ' WHERE id = :id';
        $stmt = $this->runQuery($sql, [':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Method to insert a record
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = 'INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . $placeholders . ')';
        return $this->runQuery($sql, $data);
    }

    // Method to update a record
    public function update($table, $data, $id) {
        $set = '';
        foreach ($data as $key => $value) {
            $set .= $key . ' = :' . $key . ',';
        }
        $set = rtrim($set, ',');
        $sql = 'UPDATE ' . $table . ' SET ' . $set . ' WHERE id = :id';
        $data['id'] = $id;
        return $this->runQuery($sql, $data);
    }

    // Method to delete a record
    public function delete($table, $id) {
        $sql = 'DELETE FROM ' . $table . ' WHERE id = :id';
        return $this->runQuery($sql, [':id' => $id]);
    }
}

?>