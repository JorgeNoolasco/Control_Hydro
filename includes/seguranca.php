<?php

declare(strict_types=1);

require_once __DIR__ . '/../CONFIG/conexao.php';

const PERFIS = ['administrador' => 'Administrador', 'operador' => 'Operador', 'visualizador' => 'Visualizador'];

class ErroHttp extends RuntimeException
{
    public function __construct(public int $status, string $mensagem)
    {
        parent::__construct($mensagem);
    }
}

function producao(): bool
{
    return naVercel() || ambiente('APP_ENV') === 'production';
}

function segredoAplicacao(): string
{
    $segredo = ambiente('APP_SECRET');
    if (strlen($segredo) < 64 || !ctype_xdigit($segredo)) {
        throw new RuntimeException('APP_SECRET ausente ou inválido.');
    }
    return $segredo;
}

function inteiroAmbiente(string $nome, int $padrao, int $min, int $max): int
{
    $valor = filter_var(ambiente($nome, (string) $padrao), FILTER_VALIDATE_INT);
    if ($valor === false || $valor < $min || $valor > $max) throw new RuntimeException('Configuração inválida.');
    return $valor;
}

function tempoInatividade(): int
{
    return inteiroAmbiente('SESSION_IDLE_TIMEOUT', 1800, 60, 7200);
}
function tempoAbsoluto(): int
{
    return inteiroAmbiente('SESSION_ABSOLUTE_TIMEOUT', 28800, 300, 86400);
}

/** Só confia no proxy conhecido da Vercel, nunca em cabeçalhos arbitrários no servidor local. */
function requisicaoHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (naVercel() && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function origemAplicacao(): string
{
    $url = rtrim(ambiente('APP_URL'), '/');
    $p = parse_url($url);
    if (
        !$p || !isset($p['host'], $p['scheme']) || isset($p['user']) || isset($p['pass'])
        || isset($p['query']) || isset($p['fragment']) || !empty($p['path'])
        || !in_array($p['scheme'], ['https', 'http'], true)
        || (producao() && $p['scheme'] !== 'https')
    ) {
        throw new RuntimeException('APP_URL inválida.');
    }
    return $url;
}

function cabecalhosSeguranca(): void
{
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'; object-src 'none'");
    if (producao()) {
        header('Strict-Transport-Security: max-age=31536000');
        if (!requisicaoHttps()) {
            // Destino fixo configurado; nunca usa Host ou uma URL fornecida pelo visitante.
            header('Location: ' . origemAplicacao() . '/login.php', true, 308);
            exit;
        }
    }
}

function textoEntrada(array $dados, string $campo, int $max = 255): string
{
    $valor = $dados[$campo] ?? '';
    if (!is_string($valor) || strlen($valor) > $max || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $valor)) {
        throw new InvalidArgumentException('Verifique os campos informados.');
    }
    return trim($valor);
}

function emailValido(string $email): string
{
    $email = strtolower(trim($email));
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Informe um e-mail válido.');
    return $email;
}

function nomeValido(string $nome): string
{
    $nome = trim($nome);
    if (strlen($nome) < 2 || strlen($nome) > 120 || preg_match('/[\x00-\x1f\x7f]/', $nome) || !preg_match('//u', $nome)) {
        throw new InvalidArgumentException('O nome deve ter entre 2 e 120 bytes, sem caracteres de controle.');
    }
    return $nome;
}

function senhaEntrada(array $dados, string $campo = 'senha'): string
{
    $senha = $dados[$campo] ?? '';
    if (!is_string($senha) || strlen($senha) > 72 || str_contains($senha, "\0")) throw new InvalidArgumentException('Senha inválida.');
    return $senha;
}

function validarNovaSenha(string $senha): void
{
    // O limite também evita o truncamento silencioso do bcrypt em 72 bytes.
    if (strlen($senha) < 12 || strlen($senha) > 72 || str_contains($senha, "\0") || trim($senha) === '') {
        throw new InvalidArgumentException('Use uma senha com 12 a 72 bytes. Frases longas são bem-vindas.');
    }
}

function hashSenha(string $senha): string
{
    validarNovaSenha($senha);
    return password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
}

function uuidValido(string $id): string
{
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $id)) throw new InvalidArgumentException('Identificador inválido.');
    return $id;
}

function pode(array $usuario, string $permissao): bool
{
    $mapa = [
        'administrador' => ['consultar', 'cadastrar', 'editar', 'excluir', 'administrar'],
        'operador' => ['consultar', 'cadastrar', 'editar'],
        'visualizador' => ['consultar'],
    ];
    return !empty($usuario['ativo']) && in_array($permissao, $mapa[$usuario['perfil']] ?? [], true);
}

function csrfToken(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function validarCsrf(mixed $token): void
{
    if (!is_string($token) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        throw new ErroHttp(403, 'O formulário expirou. Atualize a página e tente novamente.');
    }
}

function exigirMetodo(array $metodos): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $metodos, true)) {
        header('Allow: ' . implode(', ', $metodos));
        throw new ErroHttp(405, 'Método não permitido.');
    }
}

function ipCliente(): string
{
    $ip = naVercel() ? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '') : ($_SERVER['REMOTE_ADDR'] ?? '');
    $ip = trim(explode(',', $ip)[0]);
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'desconhecido';
}

function identificadorPrivado(string $tipo, string $valor): string
{
    return hash_hmac('sha256', $tipo . ':' . $valor, segredoAplicacao());
}

/** Reutiliza a transação da sessão, mas desfaz integralmente a operação em caso de falha. */
function transacao(PDO $pdo, callable $operacao): mixed
{
    $externa = $pdo->inTransaction();
    $savepoint = 'hc_' . bin2hex(random_bytes(6));
    if ($externa) $pdo->exec('SAVEPOINT ' . $savepoint);
    else $pdo->beginTransaction();
    try {
        $resultado = $operacao();
        if ($externa) $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        else $pdo->commit();
        return $resultado;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            if ($externa) {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            } else $pdo->rollBack();
        }
        throw $ex;
    }
}

function sessaoExpirada(array $auth, ?int $agora = null): bool
{
    $agora ??= time();
    return !isset($auth['inicio'], $auth['atividade'])
        || $agora - (int) $auth['atividade'] >= tempoInatividade()
        || $agora - (int) $auth['inicio'] >= tempoAbsoluto();
}

function redirecionar(string $pagina): void
{
    if (session_status() === PHP_SESSION_ACTIVE && !session_write_close()) throw new RuntimeException('Falha de sessão.');
    header('Location: /' . $pagina, true, 303);
    exit;
}
