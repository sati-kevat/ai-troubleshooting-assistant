<?php

declare(strict_types=1);

/**
 * Lazily created PDO connection. Prepared statements are real (not
 * emulated) and every error raises an exception.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            Config::get('DB_HOST', '127.0.0.1'),
            Config::int('DB_PORT', 3306),
            Config::get('DB_NAME', 'troubleshoot_assistant')
        );

        try {
            $pdo = new PDO($dsn, Config::get('DB_USER', 'root'), Config::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ]);
            // Keep MySQL timestamps in the same time zone as PHP.
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            Logger::error('Database connection failed', ['message' => $e->getMessage()]);
            throw new ApiException(503, 'database_unavailable', 'The database is currently unavailable. Please check that MySQL is running.');
        }

        return self::$pdo = $pdo;
    }
}
