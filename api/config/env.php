<?php
declare(strict_types=1);

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_name('MINIMESESSID');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',   // 🔑 ESSENCIAL
        'secure'   => false, // localhost
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
