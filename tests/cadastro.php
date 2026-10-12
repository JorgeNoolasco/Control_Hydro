<?php
declare(strict_types=1);
// Incluído pela integração: reutiliza apenas seu banco e seus servidores loopback.
if (PHP_SAPI !== 'cli' || !isset($base, $base2, $pdo, $servico, $envWorker)) { exit(1); }

function cadastrarContaTeste(string $base, array $dados, array &$cookies): array {
    $formulario = http($base, '/registrar.php', $cookies);
    return http($base, '/registrar.php', $cookies, ['csrf' => tokenForm($formulario)] + $dados);
}

$cadDados = ['nome' => 'Nova Pessoa', 'email' => 'pessoa@example.test', 'senha' => $senha, 'confirmacao' => $senha];
$cadCookies = [];
$cadForm = http($base, '/registrar.php', $cadCookies);
ok($cadForm['status'] === 200 && str_contains($cadForm['body'], 'href="registrar.php" aria-current="page"'), 'aba pública de registro disponível');
ok(!str_contains($cadForm['body'], 'name="perfil"') && !str_contains($cadForm['body'], 'name="ativo"'), 'formulário não oferece privilégios');
ok(http($base, '/registrar.php', $cadCookies, $cadDados)['status'] === 403, 'cadastro público exige CSRF');
ok(http($base, '/registrar.php', $cadCookies, null, [], 'PUT')['status'] === 405, 'cadastro público recusa PUT');
foreach (['perfil' => 'administrador', 'ativo' => '1', 'versao_sessao' => '5', 'senha_hash' => 'arbitrario'] as $cadCampo => $cadValor) {
    ok(cadastrarContaTeste($base, [$cadCampo => $cadValor] + $cadDados, $cadCookies)['status'] === 403, 'cadastro rejeita campo privilegiado ' . $cadCampo);
}
foreach ([['nome' => ''], ['email' => "' OR 1=1--"], ['senha' => []], ['senha' => 'curta', 'confirmacao' => 'curta'], ['confirmacao' => 'diferente']] as $cadInvalido) {
    ok(cadastrarContaTeste($base, $cadInvalido + $cadDados, $cadCookies)['status'] === 422, 'validação de cadastro público no backend');
}
$cadXss = '<img src=x onerror=alert(1)>';
$cadResposta = cadastrarContaTeste($base, ['nome' => $cadXss, 'email' => 'invalido'] + $cadDados, $cadCookies);
ok(!str_contains($cadResposta['body'], $cadXss) && str_contains($cadResposta['body'], '&lt;img'), 'nome escapado ao reapresentar erro de cadastro');
ok((int) $pdo->query("SELECT count(*) FROM usuarios WHERE email='pessoa@example.test'")->fetchColumn() === 0, 'cadastros inválidos não criam contas');

$cadResposta = cadastrarContaTeste($base, ['email' => ' PESSOA@EXAMPLE.TEST '] + $cadDados, $cadCookies);
ok($cadResposta['status'] === 303 && $cadResposta['headers']['location'] === '/login.php', 'cadastro válido redireciona ao login');
$cadUsuario = $pdo->query("SELECT * FROM usuarios WHERE email='pessoa@example.test'")->fetch();
ok($cadUsuario && $cadUsuario['perfil'] === 'visualizador' && !$cadUsuario['ativo'], 'conta pública nasce visualizadora e aguarda aprovação');
ok(password_verify($senha, $cadUsuario['senha_hash']) && $cadUsuario['senha_hash'] !== $senha, 'cadastro armazena hash seguro');
ok(http($base2, '/', $cadCookies)['status'] === 303, 'cadastro não cria sessão autenticada');
ok(entrar($base, $cadDados['email'], $senha, $cadCookies)['status'] === 401, 'cadastro pendente não autentica');
$cadAviso = http($base, '/login.php', $cadCookies);
// A mensagem pode ter sido consumida pelo GET usado por entrar(); faça outro pedido duplicado.
$cadOutraSenha = bin2hex(random_bytes(10));
$cadDuplicado = ['nome' => 'Outro Nome', 'senha' => $cadOutraSenha, 'confirmacao' => $cadOutraSenha] + $cadDados;
ok(cadastrarContaTeste($base2, $cadDuplicado, $cadCookies)['status'] === 303, 'duplicata mantém resposta genérica');
ok(str_contains(http($base, '/login.php', $cadCookies)['body'], 'Solicitação recebida.'), 'mensagem genérica após duplicata');
$cadDepois = $pdo->query("SELECT * FROM usuarios WHERE email='pessoa@example.test'")->fetch();
ok($cadDepois === $cadUsuario, 'duplicata não sobrescreve nome, senha ou permissões');
ok(cadastrarContaTeste($base, ['email' => 'ADMIN@example.test'] + $cadDuplicado, $cadCookies)['status'] === 303, 'e-mail administrativo recebe a mesma resposta');
ok((bool) $servico->login('admin@example.test', $senha, '127.0.0.56')['usuario'], 'cadastro não toma a conta administrativa');
cadastrarContaTeste($base, $cadDados, $cadCookies);
$cadLimite = cadastrarContaTeste($base2, $cadDados, $cadCookies);
ok($cadLimite['status'] === 429 && $cadLimite['headers']['retry-after'] === '900', 'cadastro limitado por conta entre instâncias');
ok((int) $pdo->query("SELECT count(*) FROM logs_auditoria WHERE acao='cadastro_bloqueado'")->fetchColumn() > 0, 'bloqueio de cadastro auditado');

$cadFalha = ['email' => 'atomicidade@example.test'] + $cadDados;
$pdo->exec('REVOKE INSERT ON logs_auditoria FROM hidrocontrol_app');
try {
    ok(cadastrarContaTeste($base, $cadFalha, $cadCookies)['status'] === 503, 'falha de auditoria impede cadastro público');
    ok((int) $pdo->query("SELECT count(*) FROM usuarios WHERE email='atomicidade@example.test'")->fetchColumn() === 0, 'cadastro revertido quando auditoria falha');
} finally { $pdo->exec('GRANT INSERT ON logs_auditoria TO hidrocontrol_app'); }

$cadAdminCookies = [];
entrar($base, 'admin@example.test', $senha, $cadAdminCookies);
$cadAprovar = ['csrf' => tokenForm(http($base, '/usuarios.php', $cadAdminCookies)), 'acao' => 'atualizar', 'id' => $cadUsuario['id'], 'perfil' => 'visualizador', 'ativo' => '1'];
ok(http($base, '/usuarios.php', $cadAdminCookies, ['senha_atual' => 'incorreta'] + $cadAprovar)['status'] === 403, 'aprovação requer senha administrativa');
ok(http($base, '/usuarios.php', $cadAdminCookies, ['senha_atual' => $senha] + $cadAprovar)['status'] === 303, 'administrador aprova cadastro público');
$cadNovoCsrf = tokenForm(http($base, '/perfil.php', $cadAdminCookies));
ok(http($base, '/registrar.php', $cadAdminCookies, ['csrf' => $cadNovoCsrf] + $cadDados)['status'] === 303, 'usuário autenticado não repete cadastro público');
ok(entrar($base2, $cadDados['email'], $senha, $cadCookies)['status'] === 303 && http($base2, '/', $cadCookies)['status'] === 200, 'conta aprovada acessa consultas');
ok(http($base, '/cadastrar.php', $cadCookies)['status'] === 403 && http($base, '/usuarios.php', $cadCookies)['status'] === 403, 'conta aprovada mantém restrições de visualizador');

$cadRaceDados = ['email' => 'race-cadastro@example.test'] + $cadDados;
$servico->registrar($cadRaceDados, '127.0.0.70'); $servico->registrar($cadRaceDados, '127.0.0.71');
$cadRace = concorrentes($envWorker, [
    ['acao' => 'registrar', 'dados' => $cadRaceDados, 'ip' => '127.0.0.72'],
    ['acao' => 'registrar', 'dados' => $cadRaceDados, 'ip' => '127.0.0.73'],
]);
sort($cadRace);
ok($cadRace === [false, true], 'limite de cadastro resiste à concorrência');
ok((int) $pdo->query("SELECT count(*) FROM usuarios WHERE email='race-cadastro@example.test'")->fetchColumn() === 1, 'cadastro concorrente mantém e-mail único');
$cadInsert = $pdo->prepare("INSERT INTO tentativas_login (identificador_hash,ip_hash,finalidade,sucesso) SELECT repeat('b',64),?,'cadastro',false FROM generate_series(1,30)");
$cadInsert->execute([identificadorPrivado('ip', '127.0.0.77')]);
ok($servico->registrar(['email' => 'limite-ip@example.test'] + $cadDados, '127.0.0.77')['bloqueado'], 'limite por IP restringe novos e-mails');
$pdo->exec("UPDATE tentativas_login SET criado_em=clock_timestamp()-interval '16 minutes' WHERE finalidade='cadastro'");
ok(!$servico->registrar(['email' => 'limite-ip@example.test'] + $cadDados, '127.0.0.77')['bloqueado'], 'limite de cadastro expira');
$cadAudit = $pdo->prepare("SELECT detalhes FROM logs_auditoria WHERE acao='usuario_criado' AND recurso_id=?");
$cadAudit->execute([$cadUsuario['id']]);
ok(json_decode($cadAudit->fetchColumn(), true) === ['ativo' => false, 'perfil_novo' => 'visualizador'], 'cadastro público audita perfil e estado');
