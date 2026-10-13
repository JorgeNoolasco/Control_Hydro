<?php
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/email.php';
exigirMetodo(['GET', 'HEAD', 'POST']);
$titulo = 'Recuperar acesso';
$descricaoPagina = 'Vamos ajudar você a voltar ao acompanhamento.';
$pagina = 'recuperar_senha.php';
$enviado = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf($_POST['csrf'] ?? null);
    try {
        autenticacao()->recuperar(emailValido(textoEntrada($_POST, 'email', 254)), ipCliente(), 'enviarRecuperacao');
    } catch (InvalidArgumentException $ex) { /* Mesma resposta para endereço inválido ou desconhecido. */
    }
    $enviado = true;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
require __DIR__ . '/includes/header.php';
?>
<section class="painel acesso-simples">
    <h2>Esqueceu sua senha?</h2>
    <p>Informe o e-mail utilizado para acessar sua conta.</p>
    <?php if ($enviado): ?><p class="alerta normal" role="status">Se houver uma conta ativa para este e-mail, você receberá instruções para redefinir a senha. Confira também a pasta de spam.</p><?php endif; ?>
    <form method="post" action="recuperar_senha.php" class="form-seguranca"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <label for="email">E-mail<input type="email" id="email" name="email" autocomplete="email" maxlength="254" required></label>
        <button class="botao" type="submit">Enviar instruções</button><a href="login.php">Voltar para o login</a>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>