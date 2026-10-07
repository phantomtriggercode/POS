<?php
declare(strict_types=1);

function db_connect(array $c): PDO
{
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['sqlite_path'], null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $c['host'] ?? 'localhost',
        (int) ($c['port'] ?? 3306),
        $c['name'] ?? ''
    );
    $pdo = new PDO($dsn, $c['user'] ?? '', $c['pass'] ?? '', $opts);
    $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");
    return $pdo;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = db_connect(config('db', []));
    }
    return $pdo;
}

function db_driver(): string
{
    return db()->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/** Row-lock suffix: MySQL supports SELECT ... FOR UPDATE, SQLite locks the whole DB on write. */
function for_update(): string
{
    return db_driver() === 'mysql' ? ' FOR UPDATE' : '';
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute(array_values($params));
    return $st;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function q_val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function insert(string $table, array $row): int
{
    $cols = array_keys($row);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($row));
    return (int) db()->lastInsertId();
}

function update(string $table, int $id, array $row): void
{
    $set = implode(', ', array_map(static fn ($c) => "$c = ?", array_keys($row)));
    q("UPDATE $table SET $set WHERE id = ?", array_merge(array_values($row), [$id]));
}

function transaction(callable $fn)
{
    $pdo = db();
    $sqlite = db_driver() === 'sqlite';
    if ($sqlite) {
        // Take the write lock up front so balance/stock checks cannot race.
        $pdo->exec('BEGIN IMMEDIATE');
    } else {
        $pdo->beginTransaction();
    }
    try {
        $result = $fn();
        $sqlite ? $pdo->exec('COMMIT') : $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $sqlite ? $pdo->exec('ROLLBACK') : $pdo->rollBack();
        throw $e;
    }
}
