<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/session.php';

start_session();

try {
    if (!isset($_FILES['xml'])) {
        throw new Exception('Arquivo XML não enviado.');
    }

    $xml = simplexml_load_file($_FILES['xml']['tmp_name']);
    if (!$xml) {
        throw new Exception('XML inválido.');
    }

    // ===============================
    // DADOS DO EMITENTE
    // ===============================
    $emit = $xml->NFe->infNFe->emit;

    $cnpj = preg_replace('/\D/', '', (string)$emit->CNPJ);
    $razao = (string)$emit->xNome;
    $fantasia = (string)($emit->xFant ?? '');

    if (!$cnpj) {
        throw new Exception('CNPJ do emitente não encontrado no XML.');
    }

    // ===============================
    // FORNECEDOR É OBRIGATÓRIO
    // ===============================
    $pdo = db();

    $st = $pdo->prepare("
        SELECT id 
        FROM fornecedores 
        WHERE cnpj = :cnpj 
          AND ativo = 1
        LIMIT 1
    ");
    $st->execute([':cnpj' => $cnpj]);

    $fornecedorId = $st->fetchColumn();

    if (!$fornecedorId) {
        json_response([
            'sucesso' => false,
            'mensagem' => 'Fornecedor não cadastrado. Cadastre o fornecedor antes de importar a NF-e.',
            'fornecedor' => [
                'cnpj' => $cnpj,
                'razao_social' => $razao,
                'nome_fantasia' => $fantasia
            ]
        ], 422);
    }

    // ===============================
    // CHAVE DA NF-e
    // ===============================
    $chave = (string)$xml->NFe->infNFe['Id'];
    $chave = str_replace('NFe', '', $chave);

    // ===============================
    // INSERE NF-e
    // ===============================
    $st = $pdo->prepare("
        INSERT INTO nfe_entrada
        (chave, fornecedor_id, dt_emissao, total_nota, status, created_by)
        VALUES
        (:chave, :fornecedor, :emissao, :total, 'PENDENTE_MAPEAMENTO', :user)
    ");

    $st->execute([
        ':chave'      => $chave,
        ':fornecedor' => $fornecedorId,
        ':emissao'    => (string)$xml->NFe->infNFe->ide->dEmi,
        ':total'      => (float)$xml->NFe->infNFe->total->ICMSTot->vNF,
        ':user'       => $_SESSION['user_id'] ?? null
    ]);

    json_response([
        'sucesso' => true,
        'mensagem' => 'NF-e importada com sucesso.'
    ]);

} catch (Throwable $e) {
    json_response([
        'sucesso' => false,
        'mensagem' => 'Erro ao importar NF-e.',
        'debug' => $e->getMessage()
    ], 500);
}
