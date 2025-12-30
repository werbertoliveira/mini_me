<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/response.php';

function require_auth(): array
{
    start_session();

    if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
        json_response(['sucesso' => false, 'mensagem' => 'Não autenticado.'], 401);
    }

    return $_SESSION['user'];
}
