<?php
require_once __DIR__ . '/../config/database.php';

class Database {
    private static ?PDO $pdo = null;
    private static ?self $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        self::pdo();
        return self::$instance;
    }

    public static function pdo(): PDO {
        if (self::$pdo === null) {
            $config = getDatabaseConfig();
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );
            self::$pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array {
        return self::query($sql, $params)->fetch() ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $sql, array $params = []): int {
        self::query($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    public static function execute(string $sql, array $params = []): int {
        return self::query($sql, $params)->rowCount();
    }

    public static function exec(string $sql): int|false {
        return self::pdo()->exec($sql);
    }

    public static function beginTransaction(): bool { return self::pdo()->beginTransaction(); }
    public static function commit(): bool { return self::pdo()->commit(); }
    public static function rollBack(): bool { return self::pdo()->rollBack(); }
}
