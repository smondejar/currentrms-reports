<?php
/**
 * Database Connection Class
 * Simple PDO wrapper for database operations
 */

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    public static function init(array $config): void
    {
        self::$config = $config;
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                self::$config['driver'],
                self::$config['host'],
                self::$config['port'],
                self::$config['database'],
                self::$config['charset']
            );

            self::$instance = new PDO(
                $dsn,
                self::$config['username'],
                self::$config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }

        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int
    {
        $prefix = self::$config['prefix'];
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$prefix}{$table} ({$columns}) VALUES ({$placeholders})";
        self::query($sql, array_values($data));

        return (int) self::getInstance()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $prefix = self::$config['prefix'];
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';

        $sql = "UPDATE {$prefix}{$table} SET {$set} WHERE {$where}";
        $stmt = self::query($sql, array_merge(array_values($data), $whereParams));

        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        $prefix = self::$config['prefix'];
        $sql = "DELETE FROM {$prefix}{$table} WHERE {$where}";
        $stmt = self::query($sql, $params);

        return $stmt->rowCount();
    }

    public static function getPrefix(): string
    {
        return self::$config['prefix'];
    }
}
