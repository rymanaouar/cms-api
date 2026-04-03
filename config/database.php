<?php
function getDB(): PDO {
    $host   = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'cms_api';
    $user   = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
    $pass   = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed']));
    }
}