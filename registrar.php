<?php
require_once __DIR__ . '/includes/funcoes.php';
exigirMetodo(['GET', 'HEAD', 'POST']);
if (usuarioAtual()) redirecionar('index.php');
$titulo = 'Faça parte do HidroControl';
$descricaoPagina = 'Crie sua conta para acompanhar o monitoramento da usina.';
$pagina = 'registrar.php';
$erro = null;
$nome = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf($_POST['csrf'] ?? null);
    try {
        $nome = textoEntrada($_POST, 'nome', 120);
        $email = textoEntrada($_POST, 'email', 254);
        $resultado = autenticacao()->registrar($_POST, ipCliente());
        if ($resultado['bloqueado']) {
            http_response_code(429);
            header('Retry-After: 900');
            $erro = 'Muitas solicitações. Aguarde 15 minutos e tente novamente.';
        } else {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $_SESSION['sucesso'] = 'Solicitação recebida. Novas contas precisam da aprovação do administrador. Se você já possui uma conta, entre com sua senha.';
            redirecionar('login.php');
        }
    } catch (InvalidArgumentException $ex) {
        http_response_code(422);
        $erro = $ex->getMessage();
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="acesso-layout">
    <section class="acesso-historia"><span class="etiqueta">CADERNO DA USINA</span>
        <h2>Um novo olhar sobre cada leitura.</h2>
        <p>Acompanhe o reservatório, a geração de energia e o histórico da usina.</p>
        <div class="ondas-acesso" aria-hidden="true"></div>
        <p class="nota-acesso">Sua conta começa como Visualizador, com acesso a consultas após aprovação do administrador.</p>
    </section>
    <section class="painel acesso-formulario" aria-labelledby="registrar-titulo">
        <?php require __DIR__ . '/includes/acesso_abas.php'; ?>
        <h2 id="registrar-titulo">Crie sua conta</h2>
        <p class="subtitulo">Preencha seus dados e solicite acesso ao HidroControl.</p>
        <?php if ($erro): ?><p class="alerta atencao" role="alert"><?= e($erro) ?></p><?php endif; ?>
        <form method="post" action="registrar.php" class="form-seguranca">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <label for="nome">Nome<input id="nome" name="nome" autocomplete="name" minlength="2" maxlength="120" required value="<?= e($nome) ?>"></label>
            <label for="email">E-mail<input id="email" name="email" type="email" autocomplete="email" maxlength="254" required value="<?= e($email) ?>"></label>
            <label for="senha">Senha<input id="senha" name="senha" type="password" autocomplete="new-password" minlength="12" maxlength="72" required aria-describedby="senha-ajuda"></label>
            <p id="senha-ajuda" class="ajuda-campo">Use de 12 a 72 bytes. Prefira uma frase longa; caracteres acentuados podem ocupar mais de um byte.</p>
            <label for="confirmacao">Confirme a senha<input id="confirmacao" name="confirmacao" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label>
            <button type="submit" class="botao">Registrar conta</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>