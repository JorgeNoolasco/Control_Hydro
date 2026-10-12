<?php
require_once __DIR__ . '/includes/funcoes.php';
exigirMetodo(['GET', 'HEAD', 'POST']);
if (usuarioAtual()) redirecionar('index.php');
$titulo = 'Bem-vindo ao HidroControl';
$descricaoPagina = 'Entre para acompanhar a usina com segurança.';
$pagina = 'login.php'; $erro = null; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf($_POST['csrf'] ?? null);
    try {
        $email = textoEntrada($_POST, 'email', 254);
        $resultado = autenticacao()->login($email, senhaEntrada($_POST), ipCliente());
        if ($resultado['usuario']) {
            iniciarAutenticacao($resultado['usuario']);
            redirecionar('index.php');
        }
        if ($resultado['bloqueado']) header('Retry-After: 900');
        http_response_code($resultado['bloqueado'] ? 429 : 401);
        $erro = $resultado['bloqueado'] ? 'Não foi possível entrar. Aguarde 15 minutos e tente novamente.' : 'E-mail ou senha inválidos.';
    } catch (InvalidArgumentException $ex) {
        http_response_code(401); $erro = 'E-mail ou senha inválidos.';
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="acesso-layout">
    <section class="acesso-historia"><span class="etiqueta">CADERNO DA USINA</span><h2>Cada leitura conta uma parte da história.</h2><p>Acompanhe a água, a energia e os sinais da sua usina em um só lugar.</p><div class="ondas-acesso" aria-hidden="true"></div><p class="nota-acesso">Monitoramento · Histórico · Dados confiáveis</p></section>
    <section class="painel acesso-formulario" aria-labelledby="entrar-titulo"><h2 id="entrar-titulo">Acesse sua conta</h2><p class="subtitulo">Use o e-mail cadastrado pelo administrador.</p>
        <?php if ($erro): ?><p class="alerta atencao" role="alert"><?= e($erro) ?></p><?php endif; ?>
        <form method="post" action="login.php" class="form-seguranca">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <label for="email">E-mail<input id="email" name="email" type="email" autocomplete="username" maxlength="254" required value="<?= e($email) ?>"></label>
            <label for="senha">Senha<input id="senha" name="senha" type="password" autocomplete="current-password" maxlength="72" required></label>
            <button type="submit" class="botao">Entrar</button><a href="recuperar_senha.php">Esqueci minha senha</a>
        </form>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
