<?php
require_once __DIR__ . '/includes/funcoes.php';
exigirMetodo(['POST']);
$usuario = exigirAutenticacao();
validarCsrf($_POST['csrf'] ?? null);
autenticacao()->auditar('logout', $usuario['id']);
invalidarSessao();
redirecionar('login.php');
