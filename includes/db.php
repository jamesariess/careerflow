<?php
// ============================================================
// CareerFlow – Database Connection (PDO singleton)
// ============================================================
require_once __DIR__ . '/config.php';

class DB {
    private static ?PDO $pdo = null;

    public static function conn(): PDO {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $opts = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
            } catch (PDOException $e) {
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed.']));
            }
        }
        return self::$pdo;
    }

    /** Execute a prepared query and return the statement */
    public static function run(string $sql, array $params = []): PDOStatement {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch all rows */
    public static function all(string $sql, array $params = []): array {
        return self::run($sql, $params)->fetchAll();
    }

    /** Fetch one row */
    public static function one(string $sql, array $params = []): array|false {
        return self::run($sql, $params)->fetch();
    }

    /** Last insert id */
    public static function lastId(): string {
        return self::conn()->lastInsertId();
    }
}
