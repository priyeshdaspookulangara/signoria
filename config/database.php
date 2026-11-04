<?php
/**
 * Database Connection Wrapper (PDO)
 * MLM 3x Matrix Auto-Spillover Platform
 */

declare(strict_types=1);

if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection(): PDO {
        static $pdo = null;

        if ($pdo !== null) {
            return $pdo;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'mlm_matrix';
        $user = getenv('DB_USER') ?: 'mlm_user';
        $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'mlm_pass123';
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            // Return user-friendly error in production
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed. Please ensure the database server is running.'
            ]));
        }
    }
}
