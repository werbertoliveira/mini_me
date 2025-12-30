<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/response.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true);

$xmlPath = trim((string)($body['xml_path'] ?? ''));
if ($xmlPath === '') {
  json_response(['sucesso' => false, 'mensagem' => 'Informe xml_path.'], 400);
}

$fullPath = realpath(__DIR__ . '/../../' . $xmlPath);
if (!$fullPath || !is_file($fullPath)) {
  json_response(['sucesso' => false, 'mensagem' => 'Arquivo XML não encontrado.', 'debug' => $xmlPath], 404);
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($fullPath);
if (!$xml) {
  $err = libxml_get_last_error();
  json_response(['sucesso' => false, 'mensagem' => 'XML inválido.', 'debug' => $err ? $err->message : ''], 400);
}

$ns = $xml->getNamespaces(true);
$xml->registerXPathNamespace('n', $ns[''] ?? 'http://www.portalfiscal.inf.br/nfe');

// pega infNFe
$inf = $xml->xpath('//n:infNFe');
if (!$inf || !isset($inf[0])) {
  json_response(['sucesso' => false, 'mensagem' => 'Não encontrei infNFe (NF-e modelo 55).'], 400);
}
$inf = $inf[0];

// dados básicos
$chave = (string)$inf['Id'];
$chave = preg_replace('/^NFe/', '', $chave ?? '');

$nNF   = (string)($inf->ide->nNF ?? '');
$serie = (string)($inf->ide->serie ?? '');
$dhEmi = (string)($inf->ide->dhEmi ?? '');
$dEmi  = $dhEmi ? substr($dhEmi, 0, 10) : null;

// fornecedor (emitente)
$emit = $inf->emit ?? null;
$cnpj = $emit ? (string)($emit->CNPJ ?? '') : '';
$razao= $emit ? (string)($emit->xNome ?? '') : '';
$fant = $emit ? (string)($emit->xFant ?? '') : '';
$ie   = $emit ? (string)($emit->IE ?? '') : '';

$ender = $emit ? ($emit->enderEmit ?? null) : null;
$logradouro = $ender ? (string)($ender->xLgr ?? '') : '';
$nro        = $ender ? (string)($ender->nro ?? '') : '';
$bairro     = $ender ? (string)($ender->xBairro ?? '') : '';
$mun        = $ender ? (string)($ender->xMun ?? '') : '';
$uf         = $ender ? (string)($ender->UF ?? '') : '';

$endTxt = trim(($logradouro ? $logradouro : '') . ($nro ? ', '.$nro : '') . ($bairro ? ' - '.$bairro : ''));

// totais
$totalProdutos = (string)($inf->total->ICMSTot->vProd ?? '0');
$totalNota     = (string)($inf->total->ICMSTot->vNF ?? '0');

// usuário criador (sessão)
$createdBy = null;
if (!empty($_SESSION['user']['id'])) {
  $createdBy = (int)$_SESSION['user']['id'];
}

try {
  $pdo = db();
  $pdo->beginTransaction();

  // 1) fornecedor upsert por CNPJ
  $st = $pdo->prepare("SELECT id FROM fornecedores WHERE cnpj = :cnpj LIMIT 1");
  $st->execute([':cnpj' => $cnpj]);
  $fornId = $st->fetchColumn();

  if (!$fornId) {
    $ins = $pdo->prepare("
      INSERT INTO fornecedores (cnpj, razao_social, nome_fantasia, ie, endereco, municipio, uf)
      VALUES (:cnpj, :razao, :fant, :ie, :endereco, :mun, :uf)
    ");
    $ins->execute([
      ':cnpj' => $cnpj,
      ':razao'=> $razao,
      ':fant' => $fant,
      ':ie'   => $ie,
      ':endereco' => $endTxt ?: null,
      ':mun'  => $mun ?: null,
      ':uf'   => $uf ?: null,
    ]);
    $fornId = (int)$pdo->lastInsertId();
  } else {
    $fornId = (int)$fornId;
  }

  // 2) se já existe pela chave, não duplica
  $st = $pdo->prepare("SELECT id, status FROM nfe_entrada WHERE chave = :chave LIMIT 1");
  $st->execute([':chave' => $chave]);
  $exists = $st->fetch();

  if ($exists) {
    $pdo->rollBack();
    json_response([
      'sucesso' => true,
      'mensagem' => 'NF-e já importada.',
      'nfe_id' => (int)$exists['id'],
      'status' => (string)$exists['status']
    ]);
  }

  // 3) cria cabeçalho da nota
  $insNfe = $pdo->prepare("
    INSERT INTO nfe_entrada
    (chave, fornecedor_id, numero, serie, dt_emissao, dt_entrada, total_produtos, total_nota, xml_path, status, created_by)
    VALUES
    (:chave, :forn, :num, :serie, :dtemi, :dtent, :vprod, :vnf, :xml, 'PENDENTE_MAPEAMENTO', :created_by)
  ");
  $insNfe->execute([
    ':chave' => $chave,
    ':forn'  => $fornId,
    ':num'   => $nNF ?: null,
    ':serie' => $serie ?: null,
    ':dtemi' => $dEmi,
    ':dtent' => date('Y-m-d'),
    ':vprod' => (float)$totalProdutos,
    ':vnf'   => (float)$totalNota,
    ':xml'   => $xmlPath,
    ':created_by' => $createdBy
  ]);
  $nfeId = (int)$pdo->lastInsertId();

  // 4) itens: grava como pendente de mapeamento (produto_id/sku_id null)
  $detList = $inf->det ?? [];
  $nItem = 0;

  $insItem = $pdo->prepare("
    INSERT INTO nfe_itens
    (nfe_id, n_item, cprod, cean, descricao, ncm, cfop, unidade, qtd, valor_unit, valor_total, produto_id, sku_id, precisa_mapeamento)
    VALUES
    (:nfe, :nitem, :cprod, :cean, :desc, :ncm, :cfop, :un, :qtd, :vun, :vtot, NULL, NULL, 1)
  ");

  foreach ($detList as $det) {
    $nItem++;
    $prod = $det->prod ?? null;

    $cprod = $prod ? (string)($prod->cProd ?? '') : '';
    $cean  = $prod ? (string)($prod->cEAN ?? '') : '';
    $desc  = $prod ? (string)($prod->xProd ?? '') : '';
    $ncm   = $prod ? (string)($prod->NCM ?? '') : '';
    $cfop  = $prod ? (string)($prod->CFOP ?? '') : '';
    $un    = $prod ? (string)($prod->uCom ?? '') : '';
    $qtd   = $prod ? (string)($prod->qCom ?? '0') : '0';
    $vun   = $prod ? (string)($prod->vUnCom ?? '0') : '0';
    $vtot  = $prod ? (string)($prod->vProd ?? '0') : '0';

    $insItem->execute([
      ':nfe' => $nfeId,
      ':nitem' => $nItem,
      ':cprod' => $cprod ?: null,
      ':cean'  => $cean ?: null,
      ':desc'  => $desc ?: 'ITEM',
      ':ncm'   => $ncm ?: null,
      ':cfop'  => $cfop ?: null,
      ':un'    => $un ?: null,
      ':qtd'   => (float)$qtd,
      ':vun'   => (float)$vun,
      ':vtot'  => (float)$vtot,
    ]);
  }

  $pdo->commit();

  json_response([
    'sucesso' => true,
    'mensagem' => 'NF-e importada. Agora é preciso mapear as variações (cor/tamanho) para concluir.',
    'nfe_id' => $nfeId,
    'status' => 'PENDENTE_MAPEAMENTO',
    'itens' => $nItem
  ]);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  json_response(['sucesso' => false, 'mensagem' => 'Erro no servidor.', 'debug' => $e->getMessage()], 500);
}
