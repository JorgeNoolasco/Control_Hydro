<?php

declare(strict_types=1);
require_once __DIR__ . '/../CLASSES/Autenticacao.php';

function autenticacao(): Autenticacao
{
    static $servico;
    return $servico ??= new Autenticacao(conectar());
}

function invalidarSessao(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE && !session_destroy()) throw new RuntimeException('Falha ao invalidar sessão.');
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'],
        'secure' => $params['secure'],
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function iniciarAutenticacao(array $usuario): void
{
    // Remove o ID anterior do banco. Nenhum dado de perfil é aceito do navegador.
    if (!session_regenerate_id(true)) throw new RuntimeException('Falha ao renovar sessão.');
    $_SESSION = ['auth' => [
        'id' => $usuario['id'],
        'versao_sessao' => (int) $usuario['versao_sessao'],
        'inicio' => time(),
        'atividade' => time()
    ], 'csrf' => bin2hex(random_bytes(32))];
}

function usuarioAtual(): ?array
{
    if (!isset($_SESSION['auth'])) return null;
    $auth = $_SESSION['auth'];
    $u = autenticacao()->usuario($auth['id']);
    if (sessaoExpirada($auth) || !$u || !$u['ativo'] || (int) $u['versao_sessao'] !== (int) $auth['versao_sessao']) {
        if ($u) autenticacao()->auditar('sessao_expirada', $u['id']);
        invalidarSessao();
        session_id('');
        if (!session_start()) throw new RuntimeException('Falha ao iniciar sessão anônima.');
        return null;
    }
    $_SESSION['auth']['atividade'] = time();
    return $u;
}

function exigirAutenticacao(string $permissao = 'consultar'): array
{
    $u = usuarioAtual();
    if (!$u) {
        if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) redirecionar('login.php');
        throw new ErroHttp(401, 'Entre para continuar.');
    }
    if (!pode($u, $permissao)) throw new ErroHttp(403, 'Você não tem permissão para esta operação.');
    return $u;
}

function confirmarSenha(array $usuario): void
{
    $resultado = autenticacao()->login($usuario['email'], senhaEntrada($_POST, 'senha_atual'), ipCliente(), 'confirmacao');
    if (!$resultado['usuario']) {
        if ($resultado['bloqueado']) header('Retry-After: 900');
        throw new ErroHttp($resultado['bloqueado'] ? 429 : 403, 'Não foi possível confirmar sua identidade. Tente novamente mais tarde.');
    }
}
