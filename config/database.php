<?php
/**
 * Database connection using Railway MySQL.
 * Falls back to XAMPP settings when DATABASE_URL is not available.
 */

function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $databaseUrl = getenv('DATABASE_URL');

            if ($databaseUrl) {
                $url = parse_url($databaseUrl);

                $host = $url['host'];
                $port = $url['port'] ?? 3306;
                $user = $url['user'];
                $pass = $url['pass'];
                $name = ltrim($url['path'], '/');

                $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

                $pdo = new PDO(
                    $dsn,
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
         } else {
    throw new PDOException("DATABASE_URL environment variable is not set. Please configure it in Railway Variables.");
}
                
        } catch (PDOException $e) {
            die("Database connection failed: " . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}