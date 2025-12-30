<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$senha = $_GET['senha'] ?? 'admin';
echo password_hash($senha, PASSWORD_DEFAULT);
