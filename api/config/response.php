<?php
declare(strict_types=1);

{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    // evita conflito com outras apps
    session_name('MINIMESESSID');

    // cookie para o projeto todo
    $secure = false; // localhost
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',     // ⭐ MUITO IMPORTANTE
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
