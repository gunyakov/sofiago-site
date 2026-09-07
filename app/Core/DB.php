<?php

declare(strict_types=1);

namespace Sofiago\Core;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper. Every method goes through prepared statements — never build SQL by
 * concatenating user-supplied values into the query string.
 */
final class DB
{
    private PDO $pdo;

    public function __construct(Config $config)
    {
        $c = $config->get('db');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $c['host'],
            $c['port'] ?? 3306,
            $c['name']
        );

        $this->pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Force this connection's NOW()/CURRENT_TIMESTAMP to UTC regardless of the server's
        // system timezone. App::__construct() sets PHP's default timezone to UTC too — without
        // both sides pinned the same way, an expires_at computed in PHP (date('Y-m-d H:i:s', ...))
        // and compared against MySQL's NOW() can silently disagree by hours (caught this in dev:
        // PHP defaulted to UTC, local MariaDB's system timezone was EEST/+3, so every token came
        // back "already expired").
        $this->pdo->exec("SET time_zone = '+00:00'");
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @param array<int|string, mixed> $params */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<int|string, mixed> $params */
    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * Insert a row from an associative array of column => value. Column names come from our
     * own model code, never directly from request input.
     *
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): string
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);

        return $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $whereParams
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = [];
        $params = [];

        foreach ($data as $column => $value) {
            $placeholder = 'set_' . $column;
            $set[] = sprintf('%s = :%s', $column, $placeholder);
            $params[$placeholder] = $value;
        }

        foreach ($whereParams as $key => $value) {
            $params[$key] = $value;
        }

        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $set), $where);

        return $this->query($sql, $params)->rowCount();
    }

    /** @param array<int|string, mixed> $params */
    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query(sprintf('DELETE FROM %s WHERE %s', $table, $where), $params)->rowCount();
    }

    /** @template T @param callable(): T $fn @return T */
    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $fn();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
