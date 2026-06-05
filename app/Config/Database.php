<?php
/**
 * Database.php
 * MySQL Database connection configuration using PDO
 * Follows PSR-12 coding standards.
 */

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private const DB_HOST = 'localhost';
    private const DB_USER = 'root';
    private const DB_PASS = '123';
    private const DB_NAME = 'tienlen';

    /**
     * Establish database connection, creating database if not exists.
     *
     * @return PDO
     */
    public static function connect(): PDO
    {
        static $pdo = null;
        if ($pdo !== null) {
            return $pdo;
        }

        // 1. Connect to MySQL server without selecting database (to check/create database)
        try {
            $tempPdo = new PDO("mysql:host=" . self::DB_HOST, self::DB_USER, self::DB_PASS);
            $tempPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . self::DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'MySQL server connection/database creation failed: ' . $e->getMessage()]);
            exit;
        }

        // 2. Connect to the specific database
        try {
            $pdo = new PDO(
                "mysql:host=" . self::DB_HOST . ";dbname=" . self::DB_NAME . ";charset=utf8mb4",
                self::DB_USER,
                self::DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'MySQL database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
}
