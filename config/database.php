<?php
/**
 * Database connection using Railway MySQL.
 */

function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $databaseUrl = getenv('DATABASE_URL');

            error_log("DEBUG DATABASE_URL: " . var_export($databaseUrl, true));

            if (!$databaseUrl) {
                throw new PDOException("DATABASE_URL environment variable is not set. Please configure it in Railway Variables.");
            }

            $url = parse_url($databaseUrl);

            if ($url === false || !isset($url['host'])) {
                throw new PDOException("DATABASE_URL is malformed: " . $databaseUrl);
            }

            $host = $url['host'];
            $port = $url['port'] ?? 3306;
            $user = $url['user'] ?? '';
            $pass = $url['pass'] ?? '';
            $name = ltrim($url['path'] ?? '', '/');

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

        } catch (PDOException $e) {
            die("Database connection failed: " . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}