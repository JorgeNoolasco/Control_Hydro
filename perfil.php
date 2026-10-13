<?php
require_once __DIR__ . '/includes/funcoes.php';
exigirMetodo(['GET', 'HEAD', 'POST']);
$usuario = exigirAutenticacao();
$titulo = 'Meu perfil';
$descricaoPagina = 'Seus dados de acesso ao HidroControl.';
$pagina = 'perfil.php';
$erro = null;
//Verifica se o usuário enviou dados pelo método HTTP POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf($_POST['csrf'] ?? null); //Essa função verifica se a requisição contém um token CSRF válido.
    //O objetivo é evitar que um site malicioso tente fazer alterações na conta do usuário sem sua autorização.
    //O operador ?? null significa que, caso o campo csrf não exista, será passado null.
    try {
        autenticacao()->atualizarPerfil($usuario, $_POST);
        $_SESSION['sucesso'] = 'Nome atualizado.';
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        redirecionar('perfil.php');
    } catch (InvalidArgumentException $ex) {
        http_response_code(422);
        $erro = $ex->getMessage();
    }
}
require __DIR__ . '/includes/header.php';
?>
<section class="painel acesso-simples">
    <h2><?= e($usuario['nome']) ?></h2>
    <p><?= e($usuario['email']) ?> · <?= e(PERFIS[$usuario['perfil']]) ?></p>
    <?php if ($erro): ?><p class="alerta atencao" role="alert"><?= e($erro) ?></p><?php endif; ?>
    <form method="post" action="perfil.php" class="form-seguranca"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <label for="nome">Nome<input id="nome" name="nome" autocomplete="name" maxlength="120" required value="<?= e($usuario['nome']) ?>"></label>
        <button class="botao" type="submit">Atualizar nome</button><a href="recuperar_senha.php">Redefinir minha senha por e-mail</a>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>