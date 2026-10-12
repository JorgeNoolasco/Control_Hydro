<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/local.php';
require_once __DIR__ . '/../CLASSES/Autenticacao.php';

// Banco, credenciais e servidores efêmeros; nenhuma API externa é chamada.
$p = bancoTesteLocal();
$controle = pdoTeste($p);
$sufixo = bin2hex(random_bytes(5));
$banco = 'hidrocontrol_test_' . $sufixo;
$papel = 'hc_test_' . $sufixo;
$pdo = null; $runtime = null; $servico = null; $processos = []; $logsServidor = []; $n = 0;
function ok(bool $valor, string $nome): void {
    global $n;
    if (!$valor) throw new RuntimeException('FALHOU: ' . $nome);
    echo 'OK: ' . $nome . "\n"; $n++;
}
function negado(callable $fn, string $nome): void {
    try { $fn(); } catch (ErroHttp|InvalidArgumentException|PDOException $ex) { ok(true, $nome); return; }
    ok(false, $nome);
}
function portaLivre(): int {
    $s = stream_socket_server('tcp://127.0.0.1:0', $errno, $erro);
    if (!$s) throw new RuntimeException('Sem porta local.');
    $porta = (int) substr(strrchr(stream_socket_get_name($s, false), ':'), 1); fclose($s); return $porta;
}
function servidor(array $env): string {
    global $processos, $logsServidor;
    $porta = portaLivre(); $url = 'http://127.0.0.1:' . $porta;
    $env['APP_URL'] = $env['APP_URL'] ?? $url;
    $log = tempnam(sys_get_temp_dir(), 'hc-http-'); $logsServidor[] = $log;
    $cmd = [PHP_BINARY, '-d', 'extension=pdo_pgsql', '-d', 'display_errors=Off', '-d', 'session.serialize_handler=php_serialize', '-S', '127.0.0.1:' . $porta, 'api/index.php'];
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, dirname(__DIR__), $env);
    if (!is_resource($proc)) throw new RuntimeException('Falha ao iniciar servidor local.');
    fclose($pipes[0]); $processos[] = $proc;
    for ($i = 0; $i < 50; $i++) {
        $socket = @stream_socket_client('tcp://127.0.0.1:' . $porta, $errno, $erro, .2);
        if ($socket) { fclose($socket); return $url; }
        usleep(100000);
    }
    throw new RuntimeException('Servidor local não iniciou.');
}
function http(string $base, string $path, array &$cookies, ?array $post = null, array $headers = [], ?string $method = null): array {
    $c = curl_init($base . $path);
    $respHeaders = [];
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15,
        CURLOPT_PROXY => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
        CURLOPT_HEADERFUNCTION => static function ($ch, $linha) use (&$cookies, &$respHeaders) {
            if (str_contains($linha, ':')) { [$nome, $valor] = explode(':', trim($linha), 2); $respHeaders[strtolower($nome)] = trim($valor); }
            if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $linha, $m)) $cookies[$m[1]] = rawurldecode($m[2]);
            return strlen($linha);
        }]);
    if ($cookies) curl_setopt($c, CURLOPT_COOKIE, implode('; ', array_map(static fn($k, $v) => $k . '=' . rawurlencode($v), array_keys($cookies), $cookies)));
    if ($post !== null) { curl_setopt($c, CURLOPT_POST, true); curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post)); }
    if ($headers) curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
    if ($method) curl_setopt($c, CURLOPT_CUSTOMREQUEST, $method);
    $body = curl_exec($c); $code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE); unset($c);
    if ($body === false) throw new RuntimeException('Falha de HTTP local.');
    return ['status' => $code, 'body' => $body, 'headers' => $respHeaders];
}
function tokenForm(array $r): string {
    if (!preg_match('/name="csrf" value="([a-f0-9]{64})"/', $r['body'], $m)) throw new RuntimeException('Formulário sem CSRF, HTTP ' . $r['status']);
    return $m[1];
}
function entrar(string $base, string $email, string $senha, array &$cookies): array {
    return http($base, '/login.php', $cookies, ['email' => $email, 'senha' => $senha, 'csrf' => tokenForm(http($base, '/login.php', $cookies))]);
}
function concorrentes(array $env, array $entradas): array {
    $jobs = [];
    foreach ($entradas as $entrada) {
        $p = proc_open([PHP_BINARY, '-d', 'extension=pdo_pgsql', __DIR__ . '/concorrencia.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $env);
        if (!is_resource($p)) throw new RuntimeException('Falha ao iniciar worker.');
        fwrite($pipes[0], json_encode($entrada)); fclose($pipes[0]); $jobs[] = [$p, $pipes];
    }
    $resultados = [];
    foreach ($jobs as [$p, $pipes]) {
        $saida = stream_get_contents($pipes[1]); $erro = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($p);
        if ($code !== 0 || $erro !== '') throw new RuntimeException('Falha no worker de concorrência.');
        $resultados[] = json_decode($saida, true, 16, JSON_THROW_ON_ERROR);
    }
    return $resultados;
}

try {
    $controle->exec('CREATE DATABASE ' . $banco);
    $pdo = pdoTeste($p, $banco);
    foreach (['anon', 'authenticated'] as $role) {
        if (!$pdo->query("SELECT 1 FROM pg_roles WHERE rolname = '$role'")->fetchColumn()) $pdo->exec('CREATE ROLE ' . $role . ' NOLOGIN');
    }
    $arquivos = glob(dirname(__DIR__) . '/supabase/migrations/*.sql'); sort($arquivos);
    foreach ($arquivos as $arquivo) $pdo->exec(file_get_contents($arquivo));
    ok((int) $pdo->query('SELECT count(*) FROM sensores')->fetchColumn() === 4, 'migrações preservam sensores');
    $senhaBanco = bin2hex(random_bytes(24));
    $pdo->exec('CREATE ROLE ' . $papel . ' LOGIN PASSWORD ' . $pdo->quote($senhaBanco));
    $pdo->exec('GRANT hidrocontrol_app TO ' . $papel);
    $pr = $p; $pr['user'] = $papel; $pr['pass'] = $senhaBanco; $pr['path'] = '/' . $banco;
    $runtime = pdoTeste($pr); $servico = new Autenticacao($runtime);
    $secret = bin2hex(random_bytes(32)); putenv('APP_SECRET=' . $secret);
    $senha = bin2hex(random_bytes(10)); $novaSenha = bin2hex(random_bytes(10));
    $adminId = $servico->provisionar('Administrador', 'admin@example.test', $senha);
    $admin = $servico->usuario($adminId);
    negado(fn() => $servico->provisionar('Outro', 'outro@example.test', $senha), 'provisionamento funciona apenas uma vez');
    $opId = $servico->criarUsuario($admin, 'Operador', 'operador@example.test', $senha, 'operador');
    $viewId = $servico->criarUsuario($admin, 'Visualizador', 'viewer@example.test', $senha, 'visualizador');
    $operador = $servico->usuario($opId); $viewer = $servico->usuario($viewId);
    negado(fn() => $servico->criarUsuario($viewer, 'Invasor', 'invasor@example.test', $senha, 'administrador'), 'serviço rejeita autoelevação');
    negado(fn() => $servico->alterarUsuario($admin, $adminId, 'operador', true), 'último administrador protegido');
    negado(fn() => $servico->atualizarPerfil($viewer, ['nome' => 'Teste', 'perfil' => 'administrador']), 'perfil rejeita mass assignment');
    negado(fn() => $runtime->exec('DELETE FROM logs_auditoria'), 'papel da aplicação não apaga auditoria');
    negado(fn() => $runtime->exec("UPDATE logs_auditoria SET acao = 'logout'"), 'papel da aplicação não altera auditoria');
    $pdo->exec('SET ROLE anon');
    foreach (['usuarios', 'tokens_redefinicao_senha', 'tentativas_login', 'logs_auditoria', 'sessoes', 'leituras'] as $t) negado(fn() => $pdo->query('SELECT * FROM ' . $t), 'anon sem acesso a ' . $t);
    $pdo->exec('RESET ROLE');
    $pdo->exec('SET ROLE authenticated');
    negado(fn() => $pdo->query('SELECT * FROM usuarios'), 'papel authenticated do Supabase também sem acesso');
    $pdo->exec('RESET ROLE');
    ok($servico->login('ADMIN@example.test', $senha, '127.0.0.2')['usuario']['id'] === $adminId, 'login válido no banco');
    for ($i = 0; $i < 5; $i++) ok(!$servico->login('admin@example.test', 'errada', '127.0.0.3')['usuario'], 'login inválido registrado');
    ok($servico->login('admin@example.test', $senha, '127.0.0.4')['bloqueado'], 'limite da conta persiste entre IPs');
    $pdo->exec("UPDATE tentativas_login SET criado_em = now() - interval '16 minutes'");
    ok((bool) $servico->login('admin@example.test', $senha, '127.0.0.4')['usuario'], 'bloqueio expira');
    $rede = identificadorPrivado('ip', '127.0.0.9');
    $q = $pdo->prepare("INSERT INTO tentativas_login (identificador_hash, ip_hash, finalidade, sucesso) SELECT repeat('a',64), ?, 'login', false FROM generate_series(1,30)"); $q->execute([$rede]);
    ok($servico->login('viewer@example.test', $senha, '127.0.0.9')['bloqueado'], 'limite de IP funciona entre contas');
    $pdo->exec('TRUNCATE tentativas_login');
    $capturado = null;
    $capturar = static function ($email, $token) use (&$capturado) { $capturado = $token; };
    $servico->recuperar('viewer@example.test', '127.0.0.1', $capturar);
    ok(strlen($capturado) === 64 && $pdo->query('SELECT token_hash FROM tokens_redefinicao_senha')->fetchColumn() === hash('sha256', $capturado), 'redefinição armazena apenas hash');
    ok($servico->redefinir($capturado, $novaSenha), 'redefinição válida');
    ok(!$servico->redefinir($capturado, $senha), 'token de uso único');
    ok(!$servico->login('viewer@example.test', $senha, '127.0.0.1')['usuario'], 'senha anterior recusada');
    ok((bool) $servico->login('viewer@example.test', $novaSenha, '127.0.0.1')['usuario'], 'nova senha aceita');
    negado(fn() => $servico->autorizar($viewer, 'consultar'), 'versão antiga da sessão revogada');
    $servico->recuperar('viewer@example.test', '127.0.0.1', $capturar);
    $pdo->exec("UPDATE tokens_redefinicao_senha SET criado_em = now() - interval '2 hours', expira_em = now() - interval '1 hour'");
    ok(!$servico->redefinir($capturado, $senha), 'token expirado recusado');
    $capturado = null; $servico->recuperar('ausente@example.test', '127.0.0.1', $capturar);
    ok($capturado === null, 'conta desconhecida não gera e-mail');

    $env = getenv();
    foreach (array_keys($env) as $k) if (preg_match('/^(VERCEL|DB_|POSTGRES_|DATABASE_|RESEND_|MAIL_)/', $k)) unset($env[$k]);
    $env['DATABASE_URL'] = 'postgresql://' . $papel . ':' . $senhaBanco . '@' . $p['host'] . ':' . ($p['port'] ?? 5432) . '/' . $banco;
    $env['DB_SSLMODE'] = 'disable'; $env['APP_SECRET'] = $secret; $env['APP_ENV'] = 'development'; $env['SESSION_DRIVER'] = 'database';
    $env['SESSION_IDLE_TIMEOUT'] = '1800'; $env['SESSION_ABSOLUTE_TIMEOUT'] = '28800';
    unset($env['APP_URL']);
    $envWorker = $env; $envWorker['TEST_DATABASE_URL'] = $env['DATABASE_URL'];
    for ($i = 0; $i < 4; $i++) $servico->login('concorrente@example.test', 'incorreta', '127.0.0.10');
    $race = concorrentes($envWorker, [
        ['acao' => 'login', 'email' => 'concorrente@example.test', 'ip' => '127.0.0.11'],
        ['acao' => 'login', 'email' => 'concorrente@example.test', 'ip' => '127.0.0.12'],
    ]);
    sort($race); ok($race === [false, true], 'limite de login serializa requisições concorrentes');
    $servico->recuperar('operador@example.test', '127.0.0.1', $capturar);
    $race = concorrentes($envWorker, [
        ['acao' => 'redefinir', 'token' => $capturado, 'senha' => $senha],
        ['acao' => 'redefinir', 'token' => $capturado, 'senha' => $senha],
    ]);
    sort($race); ok($race === [false, true], 'somente uma redefinição concorrente consome o token');
    $base = servidor($env); $base2 = servidor($env);
    $anon = [];
    foreach (['/', '/index.php', '/historico.php', '/cadastrar.php', '/usuarios.php', '/auditoria.php', '/perfil.php', '/editar_leitura.php?id=1'] as $path) ok(http($base, $path, $anon)['status'] === 303, 'GET protegido ' . $path);
    foreach (['/cadastrar.php', '/excluir_leitura.php', '/usuarios.php', '/perfil.php', '/logout.php'] as $path) ok(http($base, $path, $anon, [])['status'] === 401, 'POST sem autenticação ' . $path);
    foreach (['/CONFIG/local.php', '/includes/auth.php', '/tests/integracao.php', '/scripts/provisionar_admin.php', '/.env', '/supabase/migrations/arquivo.sql'] as $path) ok(http($base, $path, $anon)['status'] === 404, 'arquivo interno bloqueado ' . $path);
    $anon = [];
    $r = http($base, '/login.php', $anon); $csrf = tokenForm($r); $idAntes = $anon['hidrocontrol'];
    ok(str_contains($r['headers']['set-cookie'], 'HttpOnly') && str_contains($r['headers']['set-cookie'], 'SameSite=Lax'), 'cookie HttpOnly e SameSite');
    ok(str_contains($r['headers']['content-security-policy'], "script-src 'self'") && !str_contains($r['headers']['content-security-policy'], 'unsafe-inline'), 'CSP sem scripts inline');
    ok(str_contains($r['headers']['cache-control'], 'no-store'), 'respostas privadas sem cache');
    ok(http($base, '/login.php', $anon, ['email' => 'admin@example.test', 'senha' => $senha, 'csrf' => 'errado'])['status'] === 403, 'CSRF de login');
    $r = http($base, '/login.php', $anon, ['email' => "' OR 1=1--", 'senha' => $senha, 'csrf' => $csrf]);
    ok($r['status'] === 401 && str_contains($r['body'], 'E-mail ou senha inválidos.'), 'SQL injection não autentica e erro é genérico');
    $r = entrar($base, 'admin@example.test', $senha, $anon); $adminCookies = $anon;
    ok($r['status'] === 303 && $idAntes !== $anon['hidrocontrol'], 'login renova ID');
    $q = $pdo->prepare('SELECT count(*) FROM sessoes WHERE id = ?'); $q->execute([$idAntes]); ok((int) $q->fetchColumn() === 0, 'ID anterior removido do servidor');
    ok(http($base2, '/', $adminCookies)['status'] === 200, 'sessão compartilhada entre duas instâncias PHP');
    $viewCookies = []; ok(entrar($base, 'viewer@example.test', $novaSenha, $viewCookies)['status'] === 303, 'login visualizador por HTTP');
    foreach (['/cadastrar.php', '/usuarios.php', '/auditoria.php', '/editar_leitura.php?id=1'] as $path) ok(http($base, $path, $viewCookies)['status'] === 403, 'visualizador bloqueado ' . $path);
    $vcsrf = tokenForm(http($base, '/perfil.php', $viewCookies));
    ok(http($base, '/perfil.php', $viewCookies, ['csrf' => $vcsrf, 'nome' => 'Viewer', 'perfil' => 'administrador'])['status'] === 403, 'autoelevação via POST bloqueada');
    $xss = '<script>alert(1)</script>';
    ok(http($base, '/perfil.php', $viewCookies, ['csrf' => $vcsrf, 'nome' => $xss])['status'] === 303, 'nome atualizado');
    $r = http($base, '/perfil.php', $viewCookies); ok(!str_contains($r['body'], $xss) && str_contains($r['body'], '&lt;script&gt;'), 'XSS escapado na saída');
    $opCookies = []; ok(entrar($base, 'operador@example.test', $senha, $opCookies)['status'] === 303, 'login operador');
    $leitura = ['nivel_reservatorio' => '70', 'temperatura' => '60', 'vazao' => '400', 'potencia' => '60', 'turbina_ligada' => '0'];
    $csrf = tokenForm(http($base, '/cadastrar.php', $opCookies));
    ok(http($base, '/cadastrar.php', $opCookies, $leitura)['status'] === 403, 'cadastro sem CSRF bloqueado');
    ok(http($base, '/cadastrar.php', $opCookies, ['csrf' => $csrf, 'nivel_reservatorio' => '101'] + $leitura)['status'] === 422, 'limites de leituras validados no backend');
    ok(http($base, '/cadastrar.php', $viewCookies, ['csrf' => $vcsrf] + $leitura)['status'] === 403, 'escrita direta de visualizador bloqueada');
    $pdo->exec('REVOKE INSERT ON logs_auditoria FROM hidrocontrol_app');
    ok(http($base, '/cadastrar.php', $opCookies, ['csrf' => $csrf] + $leitura)['status'] === 503, 'falha de auditoria impede gravação');
    ok((int) $pdo->query('SELECT count(*) FROM leituras')->fetchColumn() === 0, 'gravação desfeita quando auditoria falha');
    $pdo->exec('GRANT INSERT ON logs_auditoria TO hidrocontrol_app');
    ok(http($base, '/cadastrar.php', $opCookies, ['csrf' => $csrf] + $leitura)['status'] === 303, 'operador cadastra leitura');
    $leituraId = $pdo->query('SELECT id FROM leituras ORDER BY id DESC LIMIT 1')->fetchColumn();
    ok($pdo->query('SELECT turbina_ligada FROM leituras WHERE id = ' . (int) $leituraId)->fetchColumn() === false, 'booleano false preservado');
    ok(http($base, '/cadastrar.php', $opCookies, ['csrf' => $csrf] + $leitura)['status'] === 403, 'reenvio de formulário antigo recusado');
    $r = http($base, '/editar_leitura.php?id=' . $leituraId, $opCookies); $csrf = tokenForm($r);
    ok(http($base, '/editar_leitura.php?id=' . $leituraId, $opCookies, ['csrf' => $csrf, 'temperatura' => '88'] + $leitura)['status'] === 303, 'operador edita leitura');
    ok(http($base, '/excluir_leitura.php', $opCookies, ['csrf' => $csrf, 'id' => $leituraId, 'confirmar' => 'excluir'])['status'] === 403, 'operador não exclui');
    $r = http($base, '/historico.php', $adminCookies); ok($r['status'] === 200 && str_contains($r['body'], 'Crítico'), 'histórico e status preservados');
    ok(str_contains(http($base, '/', $adminCookies)['body'], 'dados-graficos'), 'dados dos gráficos preservados');
    $csrf = tokenForm($r);
    ok(http($base, '/excluir_leitura.php', $adminCookies, ['csrf' => $csrf, 'id' => $leituraId, 'confirmar' => 'excluir'])['status'] === 303, 'administrador exclui com confirmação');
    foreach (['leitura_cadastrada', 'leitura_editada', 'leitura_excluida'] as $acao) ok((int) $pdo->query("SELECT count(*) FROM logs_auditoria WHERE acao = '$acao'")->fetchColumn() === 1, 'auditoria atômica ' . $acao);
    ok(http($base, '/auditoria.php?acao=leitura_excluida&usuario=' . $adminId . '&data=' . date('Y-m-d'), $adminCookies)['status'] === 200, 'auditoria com filtros');
    ok(http($base, '/auditoria.php?data=2026-02-31', $adminCookies)['status'] === 422, 'data inválida na auditoria');
    ok(http($base, '/logout.php', $adminCookies)['status'] === 405, 'logout não aceita GET');
    ok(http($base, '/logout.php', $adminCookies, [])['status'] === 403, 'logout exige CSRF');
    $csrfAdmin = tokenForm(http($base, '/usuarios.php', $adminCookies));
    $criar = ['csrf' => $csrfAdmin, 'acao' => 'criar', 'nome' => 'Nova conta', 'email' => 'nova@example.test', 'senha' => $senha, 'perfil' => 'visualizador'];
    ok(http($base, '/usuarios.php', $adminCookies, ['senha_atual' => 'errada'] + $criar)['status'] === 403, 'gerenciamento exige confirmação da senha');
    ok(http($base, '/usuarios.php', $adminCookies, ['senha_atual' => $senha] + $criar)['status'] === 303, 'administrador cria usuário pelo formulário');
    ok(http($base, '/usuarios.php', $opCookies)['status'] === 403, 'operador não gerencia usuários');
    $csrf = tokenForm(http($base, '/perfil.php', $opCookies)); $idLogout = $opCookies['hidrocontrol']; $cookieAntigo = $opCookies;
    ok(http($base, '/logout.php', $opCookies, ['csrf' => $csrf])['status'] === 303, 'logout funciona');
    $q->execute([$idLogout]); ok((int) $q->fetchColumn() === 0, 'logout apaga sessão persistida');
    ok(http($base2, '/', $cookieAntigo)['status'] === 303, 'cookie antigo não autentica após logout');
    // Simula passagem do tempo no registro de sessão, sem esperar 30 minutos.
    $qSession = $pdo->prepare('SELECT dados FROM sessoes WHERE id = ?'); $qSession->execute([$viewCookies['hidrocontrol']]);
    $sessao = unserialize(base64_decode($qSession->fetchColumn()), ['allowed_classes' => false]);
    $sessao['auth']['atividade'] = time() - 1801;
    $updSession = $pdo->prepare('UPDATE sessoes SET dados = ? WHERE id = ?'); $updSession->execute([base64_encode(serialize($sessao)), $viewCookies['hidrocontrol']]);
    ok(http($base, '/', $viewCookies)['status'] === 303, 'inatividade expira sessão por HTTP');
    ok(http($base, '/login.php', $viewCookies)['status'] === 200, 'nova sessão anônima após expiração');
    $viewCookies = []; entrar($base, 'viewer@example.test', $novaSenha, $viewCookies);
    $servico->alterarUsuario($admin, $viewId, 'operador', true);
    ok(http($base, '/', $viewCookies)['status'] === 303, 'alteração de perfil revoga sessão por HTTP');
    $viewCookies = []; entrar($base, 'viewer@example.test', $novaSenha, $viewCookies);
    $servico->alterarUsuario($admin, $viewId, 'operador', false);
    ok(http($base, '/', $viewCookies)['status'] === 303, 'desativação revoga sessão por HTTP');
    $anon = []; $r = http($base, '/recuperar_senha.php', $anon);
    $r = http($base, '/recuperar_senha.php', $anon, ['csrf' => tokenForm($r), 'email' => 'admin@example.test']);
    ok($r['status'] === 200 && str_contains($r['body'], 'Se houver uma conta ativa'), 'recuperação HTTP genérica sem provedor externo');
    $capturado = null; $servico->recuperar('admin@example.test', '127.0.0.1', $capturar);
    $r = http($base, '/redefinir_senha.php', $anon); $csrf = tokenForm($r);
    ok(http($base, '/redefinir_senha.php', $anon, ['csrf' => $csrf, 'token' => $capturado, 'senha' => $novaSenha, 'confirmacao' => $novaSenha])['status'] === 303, 'redefinição por HTTP');
    ok(http($base2, '/', $adminCookies)['status'] === 303, 'redefinição revoga outras sessões');
    $atacante = [];
    for ($i = 0; $i < 5; $i++) ok(entrar($base, 'ausente-http@example.test', 'errada', $atacante)['status'] === 401, 'falha genérica HTTP');
    $bloqueio = entrar($base2, 'ausente-http@example.test', 'errada', $atacante);
    ok($bloqueio['status'] === 429 && $bloqueio['headers']['retry-after'] === '900', 'bloqueio HTTP persiste entre instâncias');
    // Simula apenas o proxy confiável, com o banco ainda local; verifica cookie Secure e HTTPS.
    $envProd = $env; $envProd['VERCEL'] = '1'; $envProd['DB_SSLMODE'] = 'require'; $envProd['APP_URL'] = 'https://hydro.example.test';
    // O cluster de testes não precisa de TLS: a conexão falha somente depois do redirecionamento HTTPS.
    $prod = servidor($envProd); $cProd = [];
    // APP_URL deve ser HTTPS para não construir redirecionamento usando Host adulterado.
    $https = http($prod, '/login.php', $cProd, null, ['Host: host-adulterado.example']);
    ok($https['status'] === 308 && $https['headers']['location'] === 'https://hydro.example.test/login.php', 'produção obriga HTTPS com origem fixa');
    ok(isset($https['headers']['strict-transport-security']), 'HSTS em produção');
    $audit = json_encode($pdo->query('SELECT * FROM logs_auditoria')->fetchAll());
    ok(!str_contains($audit, $senha) && !str_contains($audit, $capturado) && !str_contains($audit, 'admin@example.test'), 'auditoria não contém senha, token ou e-mail');
    echo "$n verificações de integração passaram. Apenas PostgreSQL e HTTP locais; nenhum e-mail enviado.\n";
    if (in_array('--preview', $argv, true)) {
        echo "PREVIEW_LOCAL=$base\n";
        echo "Pressione Enter para encerrar e remover o banco temporário.\n";
        fgets(STDIN);
    }
} finally {
    foreach ($processos as $proc) { proc_terminate($proc); proc_close($proc); }
    $servico = null; $runtime = null; $pdo = null;
    // Só remove o banco aleatório criado por esta execução, em host validado como loopback.
    $controle->exec('DROP DATABASE IF EXISTS ' . $banco . ' WITH (FORCE)');
    $controle->exec('DROP ROLE IF EXISTS ' . $papel);
    foreach ($logsServidor as $log) if (is_file($log)) unlink($log);
}
