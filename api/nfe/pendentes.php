<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/response.php';

start_session();

if (!isset($_SESSION['user'])) {
    json_response(['sucesso'=>false,'mensagem'=>'Não autenticado.'], 401);
}

$pdo = db();

$sql = "SELECT
          n.id,
          n.dt_emissao,
          f.nome_fantasia,
          f.razao_social,
          n.total_nota,
          n.status
        FROM nfe_entrada n
        JOIN fornecedores f ON f.id = n.fornecedor_id
        WHERE n.status = 'PENDENTE_MAPEAMENTO'
        ORDER BY n.id DESC
        LIMIT 50";

$rows = $pdo->query($sql)->fetchAll();

json_response(['sucesso'=>true,'dados'=>$rows]);
