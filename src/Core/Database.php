<?php

declare(strict_types=1);

namespace ChiperX\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Koneksi PDO singleton. SEMUA query wajib prepared statement
 * (mencegah SQL Injection secara struktural).
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static bool $failed = false;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $cfg = Config::database();
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );
        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // prepared statement asli
            ]);
            // Selaraskan timezone MySQL dengan WIB
            self::$pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            self::$failed = true;
            throw new PDOException('Koneksi database gagal: ' . $e->getMessage(), (int) $e->getCode());
        }
        return self::$pdo;
    }

    public static function isAvailable(): bool
    {
        if (self::$failed) {
            return false;
        }
        try {
            self::pdo();
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        return self::run($sql, $params)->fetchColumn();
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function lastInsertId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    /** @template T @param callable():T $fn @return T */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
