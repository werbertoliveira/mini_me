<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/response.php';

start_session();

try {
  $pdo = db();
  $st = $pdo->query("
    SELECT n.id, n.chave, n.numero, n.serie, n.dt_emissao, n.dt_entrada, n.total_nota, n.status,
           f.razao_social, f.cnpj
    FROM nfe_entrada n
    JOIN fornecedores f ON f.id = n.fornecedor_id
    WHERE n.status = 'PENDENTE_MAPEAMENTO'
    ORDER BY n.id DESC
    LIMIT 50
  ");
  $rows = $st->fetchAll();
  json_response(['sucesso' => true, 'dados' => $rows]);
} catch (Throwable $e) {
  json_response(['sucesso' => false, 'mensagem' => 'Erro no servidor.', 'debug' => $e->getMessage()], 500);
}
