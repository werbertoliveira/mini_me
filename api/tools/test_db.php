<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();

    $version = $pdo->query("SELECT VERSION() AS v")->fetch();
    $whoami  = $pdo->query("SELECT CURRENT_USER() AS u")->fetch();
    $db      = $pdo->query("SELECT DATABASE() AS d")->fetch();

    echo json_encode([
        'ok' => true,
        'version' => $version['v'] ?? null,
        'current_user' => $whoami['u'] ?? null,
        'database' => $db['d'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'erro' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
