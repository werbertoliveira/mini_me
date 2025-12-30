<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/response.php';

start_session();

if (!isset($_SESSION['user'])) {
    json_response(['sucesso'=>false,'mensagem'=>'Não autenticado.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['sucesso'=>false,'mensagem'=>'Método não permitido.'], 405);
}

if (!isset($_FILES['xml']) || $_FILES['xml']['error'] !== UPLOAD_ERR_OK) {
    json_response(['sucesso'=>false,'mensagem'=>'Envie um arquivo XML válido.'], 400);
}

$ext = strtolower(pathinfo($_FILES['xml']['name'], PATHINFO_EXTENSION));
if ($ext !== 'xml') {
    json_response(['sucesso'=>false,'mensagem'=>'O arquivo precisa ser .xml'], 400);
}

$xmlContent = file_get_contents($_FILES['xml']['tmp_name']);
if (!$xmlContent) {
    json_response(['sucesso'=>false,'mensagem'=>'Não foi possível ler o XML.'], 400);
}

/**
 * NF-e modelo 55: pega a chave (Id="NFe3519...") dentro de infNFe
 */
libxml_use_internal_errors(true);
$dom = new DOMDocument();
if (!$dom->loadXML($xmlContent)) {
    json_response(['sucesso'=>false,'mensagem'=>'XML inválido/Corrompido.'], 400);
}

$infNFe = $dom->getElementsByTagName('infNFe')->item(0);
if (!$infNFe) {
    json_response(['sucesso'=>false,'mensagem'=>'Não encontrei <infNFe>. Confirme que é NF-e modelo 55.'], 400);
}

$idAttr = $infNFe->getAttribute('Id'); // ex: "NFe3519..."
$chave = preg_replace('/\D+/', '', $idAttr); // só números

if (strlen($chave) !== 44) {
    json_response(['sucesso'=>false,'mensagem'=>'Chave NF-e inválida (não tem 44 dígitos).'], 400);
}

// Pega alguns dados básicos (emitente, datas, totais) - de forma segura
$emit = $dom->getElementsByTagName('emit')->item(0);
$cnpj = $emit?->getElementsByTagName('CNPJ')->item(0)?->nodeValue ?? null;
$nome = $emit?->getElementsByTagName('xNome')->item(0)?->nodeValue ?? null;
$fant = $emit?->getElementsByTagName('xFant')->item(0)?->nodeValue ?? null;

$ide = $dom->getElementsByTagName('ide')->item(0);
$nNF = $ide?->getElementsByTagName('nNF')->item(0)?->nodeValue ?? null;
$serie = $ide?->getElementsByTagName('serie')->item(0)?->nodeValue ?? null;
$dhEmi = $ide?->getElementsByTagName('dhEmi')->item(0)?->nodeValue ?? null;

$tot = $dom->getElementsByTagName('ICMSTot')->item(0);
$vProd = $tot?->getElementsByTagName('vProd')->item(0)?->nodeValue ?? '0';
$vNF   = $tot?->getElementsByTagName('vNF')->item(0)?->nodeValue ?? '0';

$dtEmissao = null;
if ($dhEmi) {
    // "2025-12-29T09:10:00-04:00" -> "2025-12-29"
    $dtEmissao = substr($dhEmi, 0, 10);
}

$pdo = db();
$pdo->beginTransaction();

try {
    // 1) upsert fornecedor por CNPJ
    if (!$cnpj) {
        throw new RuntimeException('NF-e sem CNPJ do emitente.');
    }
    $cnpjNum = preg_replace('/\D+/', '', $cnpj);

    $st = $pdo->prepare("SELECT id FROM fornecedores WHERE cnpj = :cnpj LIMIT 1");
    $st->execute([':cnpj'=>$cnpjNum]);
    $fornId = $st->fetchColumn();

    if (!$fornId) {
        $ins = $pdo->prepare("INSERT INTO fornecedores (cnpj, razao_social, nome_fantasia) VALUES (:cnpj,:rs,:nf)");
        $ins->execute([
            ':cnpj' => $cnpjNum,
            ':rs'   => (string)$nome,
            ':nf'   => $fant ? (string)$fant : null,
        ]);
        $fornId = (int)$pdo->lastInsertId();
    } else {
        $fornId = (int)$fornId;
    }

    // 2) salva arquivo em storage com a chave
    $storageDir = realpath(__DIR__ . '/../../storage');
    if (!$storageDir) {
        throw new RuntimeException('Pasta storage não encontrada.');
    }
    $xmlDir = $storageDir . DIRECTORY_SEPARATOR . 'xml';
    if (!is_dir($xmlDir)) {
        @mkdir($xmlDir, 0775, true);
    }

    $filename = $chave . '.xml';
    $fullpath = $xmlDir . DIRECTORY_SEPARATOR . $filename;

    if (!file_put_contents($fullpath, $xmlContent)) {
        throw new RuntimeException('Falha ao salvar XML em storage/xml.');
    }

    // 3) insere na nfe_entrada (se já existir, retorna mensagem)
    $createdBy = (int)($_SESSION['user']['id'] ?? 0);

    $sql = "INSERT INTO nfe_entrada
        (chave, fornecedor_id, numero, serie, dt_emissao, dt_entrada, total_produtos, total_nota, xml_path, status, created_by)
        VALUES
        (:chave, :forn, :numero, :serie, :dt_emissao, :dt_entrada, :tp, :tn, :xml_path, 'PENDENTE_MAPEAMENTO', :created_by)";

    $st = $pdo->prepare($sql);
    $st->execute([
        ':chave'      => $chave,
        ':forn'       => $fornId,
        ':numero'     => $nNF,
        ':serie'      => $serie,
        ':dt_emissao' => $dtEmissao,
        ':dt_entrada' => date('Y-m-d'),
        ':tp'         => (float)$vProd,
        ':tn'         => (float)$vNF,
        ':xml_path'   => 'storage/xml/' . $filename,
        ':created_by' => $createdBy ?: null,
    ]);

    $nfeId = (int)$pdo->lastInsertId();

    $pdo->commit();

    json_response([
        'sucesso' => true,
        'mensagem' => 'NF-e importada. Pendente de mapeamento.',
        'nfe' => [
            'id' => $nfeId,
            'chave' => $chave,
            'fornecedor_id' => $fornId,
            'total_nota' => (float)$vNF,
            'xml_path' => 'storage/xml/' . $filename,
            'status' => 'PENDENTE_MAPEAMENTO',
        ]
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();

    // Se já existe pela chave, devolve 409
    if (str_contains((string)$e->getMessage(), 'Duplicate') || str_contains((string)$e->getMessage(), 'uk_nfe_chave')) {
        json_response(['sucesso'=>false,'mensagem'=>'Essa NF-e (chave) já foi importada.'], 409);
    }

    json_response(['sucesso'=>false,'mensagem'=>'Erro ao importar NF-e.','debug'=>$e->getMessage()], 500);
}
