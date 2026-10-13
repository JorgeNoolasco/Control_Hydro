<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/seguranca.php';
require_once __DIR__ . '/local.php';
$testes = 0;
function verificar(bool $condicao, string $nome): void
{
    global $testes;
    if (!$condicao) throw new RuntimeException('FALHOU: ' . $nome);
    $testes++;
    echo "OK: $nome\n";
}
function rejeita(callable $fn, string $nome): void
{
    try {
        $fn();
    } catch (InvalidArgumentException | ErroHttp $ex) {
        verificar(true, $nome);
        return;
    }
    verificar(false, $nome);
}
putenv('APP_SECRET=' . bin2hex(random_bytes(32)));
putenv('SESSION_IDLE_TIMEOUT=1800');
putenv('SESSION_ABSOLUTE_TIMEOUT=28800');
foreach (PERFIS as $perfil => $_) {
    $u = ['perfil' => $perfil, 'ativo' => true];
    verificar(pode($u, 'consultar'), "$perfil consulta");
    verificar(pode($u, 'editar') === ($perfil !== 'visualizador'), "$perfil edita conforme RBAC");
    verificar(pode($u, 'administrar') === ($perfil === 'administrador'), "$perfil administra conforme RBAC");
    verificar(pode($u, 'excluir') === ($perfil === 'administrador'), "$perfil exclui conforme RBAC");
}
verificar(!pode(['ativo' => false, 'perfil' => 'administrador'], 'administrar'), 'conta desativada sem permissão');
verificar(!pode(['ativo' => true, 'perfil' => 'root'], 'consultar'), 'perfil desconhecido falha fechado');
$_SESSION = [];
$csrf = csrfToken();
validarCsrf($csrf);
verificar(strlen($csrf) === 64, 'CSRF aleatório');
foreach ([null, [], 'x', str_repeat('a', 64)] as $token) rejeita(fn() => validarCsrf($token), 'CSRF inválido rejeitado');
verificar(!sessaoExpirada(['inicio' => 100, 'atividade' => 200], 201), 'sessão ativa');
verificar(sessaoExpirada(['inicio' => 100, 'atividade' => 200], 2000), 'inatividade expira no limite');
verificar(sessaoExpirada(['inicio' => 100, 'atividade' => 28890], 28900), 'duração absoluta expira');
verificar(sessaoExpirada([], 100), 'sessão incompleta rejeitada');
verificar(emailValido(' Pessoa@Example.COM ') === 'pessoa@example.com', 'e-mail normalizado');
foreach (['invalido', "user\n@example.com"] as $email) rejeita(fn() => emailValido($email), 'e-mail inválido');
foreach (['curta', str_repeat('a', 73), "uma senha longa\0"] as $senha) rejeita(fn() => hashSenha($senha), 'senha inválida');
rejeita(fn() => textoEntrada(['nome' => []], 'nome'), 'entrada array rejeitada');
rejeita(fn() => uuidValido("' OR 1=1--"), 'UUID com SQL rejeitado');
$senha = bin2hex(random_bytes(10));
$hash = hashSenha($senha);
verificar(password_verify($senha, $hash) && !password_verify('incorreta', $hash), 'password_hash e password_verify');
putenv('VERCEL=');
$_SERVER = ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8'];
verificar(ipCliente() === '127.0.0.1', 'proxy arbitrário ignorado');
putenv('VERCEL=1');
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
verificar(ipCliente() === '8.8.8.8' && requisicaoHttps(), 'proxy conhecido da Vercel');
putenv('VERCEL=');
putenv('TEST_DATABASE_URL=postgresql://test@db.example.com/hidrocontrol_test');
$recusado = false;
try {
    bancoTesteLocal();
} catch (RuntimeException $ex) {
    $recusado = true;
}
verificar($recusado, 'testes recusam banco externo');
echo "$testes verificações de segurança passaram. Sem conexões de rede.\n";
