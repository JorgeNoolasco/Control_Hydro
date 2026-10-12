<?php
require_once __DIR__ . '/includes/funcoes.php';
exigirMetodo(['GET', 'HEAD', 'POST']);
$titulo = 'Defina uma nova senha'; $descricaoPagina = 'Escolha uma senha longa que você ainda não utiliza em outros sites.';
$pagina = 'redefinir_senha.php'; $erro = null; $token = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf($_POST['csrf'] ?? null);
    try {
        $token = textoEntrada($_POST, 'token', 64);
        $senha = senhaEntrada($_POST);
        if ($senha !== senhaEntrada($_POST, 'confirmacao')) throw new InvalidArgumentException('As senhas devem ser iguais.');
        if (!autenticacao()->redefinir($token, $senha)) throw new InvalidArgumentException('Link inválido ou expirado. Solicite uma nova recuperação.');
        // Não autentica automaticamente e revoga também a sessão deste navegador.
        invalidarSessao();
        redirecionar('login.php');
    } catch (InvalidArgumentException $ex) { http_response_code(422); $erro = $ex->getMessage(); }
}
require __DIR__ . '/includes/header.php';
?>
<section class="painel acesso-simples"><h2>Nova senha</h2><p>O link pode ser usado uma vez e expira em 30 minutos.</p>
    <?php if ($erro): ?><p class="alerta atencao" role="alert"><?= e($erro) ?></p><?php endif; ?>
    <form method="post" action="redefinir_senha.php" class="form-seguranca" data-redefinir>
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>">
        <label for="senha">Nova senha<input type="password" id="senha" name="senha" autocomplete="new-password" minlength="12" maxlength="72" required aria-describedby="senha-ajuda"></label>
        <p id="senha-ajuda" class="ajuda-campo">Use de 12 a 72 bytes. Caracteres acentuados podem ocupar mais de um byte.</p>
        <label for="confirmacao">Confirme a nova senha<input type="password" id="confirmacao" name="confirmacao" autocomplete="new-password" minlength="12" maxlength="72" required></label>
        <button class="botao" type="submit">Salvar nova senha</button><a href="recuperar_senha.php">Solicitar outro link</a>
        <noscript><p>Ative o JavaScript para utilizar o link de recuperação.</p></noscript>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
