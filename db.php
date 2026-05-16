<?php
/**
 * Layer 1.3 — PDO Wrapper
 * Satu-satunya cara berinteraksi dengan database.
 * Tidak ada raw query tanpa parameter binding — by design.
 */

/**
 * Singleton PDO getter.
 * DSN dari config, default SQLite.
 */
function db(?string $dsn = null): PDO
{
    static $pdo = null;
    static $current_dsn = null;

    // DSN eksplisit diberikan → reconnect HANYA kalau DSN berbeda dari sebelumnya.
    // Mencegah connection leak saat db($dsn) di-call berkali-kali dengan DSN yang sama.
    if ($dsn !== null && $dsn !== $current_dsn) {
        $pdo         = null;
        $current_dsn = $dsn;
    }

    if ($pdo === null) {
        if ($dsn === null) {
            $dsn = function_exists('config')
                ? config('DB_DSN', 'sqlite:' . __DIR__ . '/_storage/database.sqlite')
                : 'sqlite:' . __DIR__ . '/_storage/database.sqlite';
        }

        $user = function_exists('config') ? (config('DB_USER') ?: null) : null;
        $pass = function_exists('config') ? (config('DB_PASS') ?: null) : null;

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}

/**
 * Satu-satunya cara execute query. Selalu gunakan parameter binding.
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Shortcut: ambil satu row.
 */
function db_one(string $sql, array $params = []): ?array
{
    $result = db_query($sql, $params)->fetch();
    return $result === false ? null : $result;
}

/**
 * Shortcut: ambil semua rows.
 */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/**
 * Shortcut: insert dan return last insert ID.
 */
function db_insert(string $sql, array $params = []): string
{
    db_query($sql, $params);
    return db()->lastInsertId();
}
