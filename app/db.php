<?php
declare(strict_types=1);

/**
 * Conexão PDO única com o MySQL.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = config('db');
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'],
        (int) $cfg['port'],
        $cfg['nome'],
        $cfg['charset']
    );

    try {
        $pdo = new PDO($dsn, $cfg['usuario'], $cfg['senha'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        if (config('debug')) {
            throw $e;
        }
        http_response_code(500);
        exit('Não foi possível conectar ao banco de dados. Confira config/config.php ou rode o setup.php.');
    }

    $pdo->exec("SET time_zone = '-03:00'");

    return $pdo;
}

/** Conexão sem selecionar o banco — usada pelo instalador. */
function db_servidor(array $cfg): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $cfg['host'], (int) $cfg['port'], $cfg['charset']);

    return new PDO($dsn, $cfg['usuario'], $cfg['senha'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/** SELECT que devolve todas as linhas. */
function q(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);

    return $st->fetchAll();
}

/** SELECT que devolve uma linha (ou null). */
function q1(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();

    return $row === false ? null : $row;
}

/** INSERT/UPDATE/DELETE — devolve linhas afetadas. */
function exec_sql(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);

    return $st->rowCount();
}
