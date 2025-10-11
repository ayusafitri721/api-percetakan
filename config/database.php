<?php
/**
 * File: config/database.php
 * Koneksi Database - Dipakai untuk semua API
 */

class Database {
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $db   = "percetakan_db";
    public $conn;

    // Koneksi database
    public function connect() {
        $this->conn = null;
        
        try {
            $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->db);
            
            if ($this->conn->connect_error) {
                throw new Exception("Koneksi gagal: " . $this->conn->connect_error);
            }
            
            // Set charset UTF-8
            $this->conn->set_charset("utf8mb4");
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ]);
            die();
        }
        
        return $this->conn;
    }

    // Escape string untuk mencegah SQL injection
    public function escape($string) {
        if ($string === null) return null;
        return $this->conn->real_escape_string($string);
    }

    // Query dengan error handling
    public function query($sql) {
        $result = $this->conn->query($sql);
        
        if (!$result) {
            throw new Exception("Query error: " . $this->conn->error . " | SQL: " . $sql);
        }
        
        return $result;
    }

    // Prepared statement untuk keamanan lebih
    public function prepare($sql) {
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }
        
        return $stmt;
    }

    // Get last insert ID
    public function lastInsertId() {
        return $this->conn->insert_id;
    }

    // Get affected rows
    public function affectedRows() {
        return $this->conn->affected_rows;
    }

    // Begin transaction
    public function beginTransaction() {
        $this->conn->begin_transaction();
    }

    // Commit transaction
    public function commit() {
        $this->conn->commit();
    }

    // Rollback transaction
    public function rollback() {
        $this->conn->rollback();
    }

    // Close connection
    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Inisialisasi database global
function getDB() {
    $database = new Database();
    return $database->connect();
}
?>