<?php
header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = new PDO(
  "mysql:host=127.0.0.1;port=3391;dbname=mini_me;charset=utf8mb4",
  "root",
  "", // ✅ sem senha
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);


  $r = $pdo->query("SELECT VERSION() v, @@port p, CURRENT_USER() u, DATABASE() d")->fetch(PDO::FETCH_ASSOC);
  echo json_encode(["ok"=>true, "info"=>$r], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  echo json_encode(["ok"=>false, "erro"=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
