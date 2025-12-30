<?php
declare(strict_types=1);

function db(): PDO
{
    $host = '127.0.0.1';
    $port = '3391';
    $db   = 'mini_me';

    $user = 'mini_me_user';
    $pass = 'MiniMe@123';

    $charset = 'utf8mb4';

      $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}
