<?php

namespace App\Core;

use PDO;
use PDOException;
use Exception;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    /**
     * Private constructor to enforce Singleton pattern.
     * Prevents creating multiple instances via 'new'.
     */
    private function __construct()
    {
        $config = require BASE_PATH . '/src/Config/config.php';
        $dbConfig = $config['database'];

        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";

        $options = [
            // Always throw an exception when an error occurs so we can catch it
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return results as an associative array (e.g., ['id' => 1, 'name' => 'John'])
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Disable emulated prepared statements to ensure true prepared statements (better security)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            // Establish the PDO connection. We wrap this in a try-catch to prevent leaking 
            // raw database credentials directly to the user/logs on connection failure.
            $this->connection = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $options);
        } catch (PDOException $e) {
            // In a real production app, we would log this securely and show a generic error
            error_log("Database Connection Failed: " . $e->getMessage());
            throw new Exception("Database Connection Failed. Please check system logs.");
        }
    }

    /**
     * Get the single instance of the Database.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get the underlying PDO connection object.
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Helper method to execute a query securely.
     * 
     * @param string $sql The SQL query string
     * @param array $params An array of parameters to bind to the query
     * @return \PDOStatement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
