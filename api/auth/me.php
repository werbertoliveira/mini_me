<?php
declare(strict_types=1);

// 🔥 ORDEM CORRETA
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/response.php';

start_session();

if (!isset($_SESSION['user'])) {
    json_response([
        'sucesso' => false,
        'mensagem' => 'Não autenticado.'
    ], 401);
}

json_response([
    'sucesso' => true,
    'user' => $_SESSION['user']
]);
