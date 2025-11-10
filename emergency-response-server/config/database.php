<?php
/**
 * Database Configuration
 * Yobe State University Emergency Response System
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'emergency_response_system';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';

    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }

    // Alternative method for local XAMPP configuration
    public function getXAMPPConnection() {
        $this->conn = null;

        try {
            // Default XAMPP MySQL settings
            $dsn = "mysql:host=localhost;dbname=emergency_response_system;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->conn = new PDO($dsn, 'root', '', $options);
        } catch(PDOException $exception) {
            // If database doesn't exist, create it
            if ($exception->getCode() == 1049) {
                try {
                    // Connect without database name
                    $dsn = "mysql:host=localhost;charset=utf8mb4";
                    $this->conn = new PDO($dsn, 'root', '', [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);

                    // Create database
                    $this->conn->exec("CREATE DATABASE emergency_response_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                    // Reconnect with database name
                    $this->conn = new PDO("mysql:host=localhost;dbname=emergency_response_system;charset=utf8mb4", 'root', '', $options);

                } catch(PDOException $createException) {
                    throw new Exception("Could not create database: " . $createException->getMessage());
                }
            } else {
                throw new Exception("Database connection failed: " . $exception->getMessage());
            }
        }

        return $this->conn;
    }
}
?>