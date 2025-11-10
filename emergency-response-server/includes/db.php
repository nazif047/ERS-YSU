<?php
/**
 * Database Connection
 * Yobe State University Emergency Response System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

class DatabaseConnection {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $this->connect();
    }

    /**
     * Get Database Instance (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish Database Connection
     */
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Log successful connection
            logActivity("Database connected successfully", "INFO");

        } catch (PDOException $e) {
            // If database doesn't exist, try to create it
            if ($e->getCode() == 1049) {
                $this->createDatabase();
                $this->connect();
            } else {
                logActivity("Database connection failed: " . $e->getMessage(), "ERROR");
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Create Database if it doesn't exist
     */
    private function createDatabase() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            logActivity("Database created: " . DB_NAME, "INFO");

        } catch (PDOException $e) {
            throw new Exception("Failed to create database: " . $e->getMessage());
        }
    }

    /**
     * Get Database Connection
     */
    public function getConnection() {
        if ($this->connection === null) {
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Close Database Connection
     */
    public function closeConnection() {
        $this->connection = null;
    }

    /**
     * Execute Query
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            logActivity("Query executed: " . substr($sql, 0, 100), "DEBUG");

            return $stmt;

        } catch (PDOException $e) {
            logActivity("Query failed: " . $e->getMessage() . " SQL: " . $sql, "ERROR");
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Fetch Single Row
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Fetch Multiple Rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch Single Column
     */
    public function fetchColumn($sql, $params = [], $column = 0) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($column);
    }

    /**
     * Insert Record
     */
    public function insert($table, $data) {
        try {
            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');

            $sql = "INSERT INTO " . $table . " (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $placeholders) . ")";

            $stmt = $this->connection->prepare($sql);
            $stmt->execute(array_values($data));

            logActivity("Record inserted into $table", "INFO");

            return $this->connection->lastInsertId();

        } catch (PDOException $e) {
            logActivity("Insert failed: " . $e->getMessage(), "ERROR");
            throw new Exception("Insert failed: " . $e->getMessage());
        }
    }

    /**
     * Update Record
     */
    public function update($table, $data, $where, $whereParams = []) {
        try {
            $set = [];
            $params = [];

            foreach ($data as $column => $value) {
                $set[] = "$column = ?";
                $params[] = $value;
            }

            $params = array_merge($params, $whereParams);

            $sql = "UPDATE " . $table . " SET " . implode(', ', $set) . " WHERE " . $where;

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            logActivity("Record updated in $table", "INFO");

            return $stmt->rowCount();

        } catch (PDOException $e) {
            logActivity("Update failed: " . $e->getMessage(), "ERROR");
            throw new Exception("Update failed: " . $e->getMessage());
        }
    }

    /**
     * Delete Record
     */
    public function delete($table, $where, $params = []) {
        try {
            $sql = "DELETE FROM " . $table . " WHERE " . $where;
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            logActivity("Record deleted from $table", "INFO");

            return $stmt->rowCount();

        } catch (PDOException $e) {
            logActivity("Delete failed: " . $e->getMessage(), "ERROR");
            throw new Exception("Delete failed: " . $e->getMessage());
        }
    }

    /**
     * Begin Transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit Transaction
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * Rollback Transaction
     */
    public function rollback() {
        return $this->connection->rollBack();
    }

    /**
     * Get Last Insert ID
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    /**
     * Check if Table Exists
     */
    public function tableExists($table) {
        $sql = "SHOW TABLES LIKE ?";
        $result = $this->fetch($sql, [$table]);
        return !empty($result);
    }

    /**
     * Get Table Info
     */
    public function getTableInfo($table) {
        $sql = "DESCRIBE " . $table;
        return $this->fetchAll($sql);
    }

    /**
     * Backup Database
     */
    public function backup($backupPath) {
        try {
            $tables = $this->fetchAll("SHOW TABLES");
            $backup = "-- Database Backup - " . date('Y-m-d H:i:s') . "\n\n";

            foreach ($tables as $table) {
                $tableName = $table['Tables_in_' . DB_NAME];
                $backup .= "-- Table: $tableName\n";

                // Get create table statement
                $createTable = $this->fetch("SHOW CREATE TABLE $tableName");
                $backup .= $createTable['Create Table'] . ";\n\n";

                // Get table data
                $data = $this->fetchAll("SELECT * FROM $tableName");
                if (!empty($data)) {
                    $backup .= "-- Data for table: $tableName\n";
                    foreach ($data as $row) {
                        $values = array_map(function($value) {
                            return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                        }, $row);
                        $backup .= "INSERT INTO $tableName VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $backup .= "\n";
                }
            }

            file_put_contents($backupPath, $backup);
            logActivity("Database backup created: $backupPath", "INFO");

            return true;

        } catch (Exception $e) {
            logActivity("Database backup failed: " . $e->getMessage(), "ERROR");
            throw new Exception("Backup failed: " . $e->getMessage());
        }
    }

    /**
     * Restore Database from Backup
     */
    public function restore($backupPath) {
        try {
            if (!file_exists($backupPath)) {
                throw new Exception("Backup file not found");
            }

            $sql = file_get_contents($backupPath);
            $statements = explode(';', $sql);

            $this->connection->beginTransaction();

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    $this->connection->exec($statement);
                }
            }

            $this->connection->commit();
            logActivity("Database restored from: $backupPath", "INFO");

            return true;

        } catch (Exception $e) {
            $this->connection->rollBack();
            logActivity("Database restore failed: " . $e->getMessage(), "ERROR");
            throw new Exception("Restore failed: " . $e->getMessage());
        }
    }
}

// Global database connection function
function getDB() {
    return DatabaseConnection::getInstance()->getConnection();
}
?>