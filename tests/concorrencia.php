<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/local.php';
require_once __DIR__ . '/../CLASSES/Autenticacao.php';
$auth = new Autenticacao(pdoTeste(bancoTesteLocal()));
$entrada = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
if (($entrada['acao'] ?? '') === 'login') {
    echo json_encode($auth->login($entrada['email'], 'incorreta', $entrada['ip'])['bloqueado']);
} elseif (($entrada['acao'] ?? '') === 'redefinir') {
    echo json_encode($auth->redefinir($entrada['token'], $entrada['senha']));
} elseif (($entrada['acao'] ?? '') === 'registrar') {
    echo json_encode($auth->registrar($entrada['dados'], $entrada['ip'])['bloqueado']);
} else exit(1);
