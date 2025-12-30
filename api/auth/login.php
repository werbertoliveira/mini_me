<?php
declare(strict_types=1);

/**
 * Mini Me - Login (Apache + Sessão PHP)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/response.php';
start_session();


// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response([
        'sucesso'  => false,
        'mensagem' => 'Método não permitido.'
    ], 405);
}

// Lê JSON do body
$raw  = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true);

$usuario = trim((string)($body['usuario'] ?? ''));
$senha   = (string)($body['senha'] ?? '');

// Validação básica
if ($usuario === '' || $senha === '') {
    json_response([
        'sucesso'  => false,
        'mensagem' => 'Informe usuário e senha.'
    ], 400);
}

try {
    // Conexão
    $pdo = db();

    // Busca usuário
    $sql = "
        SELECT 
            id,
            nome,
            usuario,
            senha_hash,
            perfil,
            ativo
        FROM usuarios
        WHERE usuario = :usuario
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['usuario' => $usuario]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    // Usuário inválido ou inativo
    if (!$u || (int)$u['ativo'] !== 1) {
        json_response([
            'sucesso'  => false,
            'mensagem' => 'Usuário inválido ou inativo.'
        ], 401);
    }

    // Senha inválida
    if (!password_verify($senha, (string)$u['senha_hash'])) {
        json_response([
            'sucesso'  => false,
            'mensagem' => 'Senha incorreta.'
        ], 401);
    }

    // Cria sessão
    $_SESSION['user'] = [
        'id'      => (int)$u['id'],
        'nome'    => (string)$u['nome'],
        'usuario' => (string)$u['usuario'],
        'perfil'  => (string)$u['perfil'],
    ];

    // OK
    json_response([
        'sucesso'  => true,
        'mensagem' => 'Login realizado com sucesso.',
        'user'     => $_SESSION['user'],
    ]);

} catch (Throwable $e) {

    // Log de erro (mini_me/logs/error.log)
    $logDir  = __DIR__ . '/../../logs';
    $logFile = $logDir . '/error.log';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }

    @file_put_contents(
        $logFile,
        "[" . date('Y-m-d H:i:s') . "] login.php -> " . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    // Retorno (DEV)
    json_response([
        'sucesso'  => false,
        'mensagem' => 'Erro no servidor.',
        'debug'    => $e->getMessage()
    ], 500);
}
