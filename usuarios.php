<?php
require_once __DIR__ . '/includes/funcoes.php';
exigirMetodo(['GET', 'HEAD', 'POST']);
$usuario = exigirAutenticacao('administrar');
$titulo = 'Gerenciar usuários'; $descricaoPagina = 'Defina quem pode consultar, registrar e administrar.'; $pagina = 'usuarios.php'; $erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf($_POST['csrf'] ?? null);
    try {
        confirmarSenha($usuario);
        $acao = textoEntrada($_POST, 'acao', 20);
        if ($acao === 'criar') {
            autenticacao()->criarUsuario($usuario, textoEntrada($_POST, 'nome', 120), textoEntrada($_POST, 'email', 254), senhaEntrada($_POST), textoEntrada($_POST, 'perfil', 20));
        } elseif ($acao === 'atualizar') {
            $ativo = textoEntrada($_POST, 'ativo', 1);
            if (!in_array($ativo, ['0', '1'], true)) throw new InvalidArgumentException('Estado inválido.');
            autenticacao()->alterarUsuario($usuario, textoEntrada($_POST, 'id', 36), textoEntrada($_POST, 'perfil', 20), $ativo === '1');
        } else throw new InvalidArgumentException('Operação inválida.');
        $_SESSION['sucesso'] = 'Usuário atualizado. Alterações de acesso revogam as sessões anteriores.';
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        redirecionar('usuarios.php');
    } catch (InvalidArgumentException $ex) { http_response_code(422); $erro = $ex->getMessage(); }
    catch (PDOException $ex) { http_response_code($ex->getCode() === '23505' ? 422 : 503); $erro = $ex->getCode() === '23505' ? 'Não foi possível cadastrar: confira os dados e se o e-mail já está em uso.' : erroBanco(); }
}
$atual = max(1, min(100000, (int) filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT)));
$q = conectar()->prepare('SELECT id, nome, email, perfil, ativo FROM usuarios ORDER BY nome, id LIMIT 26 OFFSET ?');
$q->bindValue(1, ($atual - 1) * 25, PDO::PARAM_INT); $q->execute(); $lista = $q->fetchAll(); $mais = count($lista) > 25;
require __DIR__ . '/includes/header.php';
?>
<?php if ($erro): ?><p class="alerta atencao" role="alert"><?= e($erro) ?></p><?php endif; ?>
<section class="painel"><h2>Novo usuário</h2><p>Visualizadores consultam; operadores também cadastram e editam; administradores gerenciam acessos e excluem leituras.</p>
    <form method="post" action="usuarios.php"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="acao" value="criar">
        <div class="campos">
            <label for="novo-nome">Nome<input id="novo-nome" name="nome" maxlength="120" required autocomplete="off"></label>
            <label for="novo-email">E-mail<input type="email" id="novo-email" name="email" maxlength="254" required autocomplete="off"></label>
            <label for="nova-senha">Senha inicial (12 a 72 bytes)<input type="password" id="nova-senha" name="senha" minlength="12" maxlength="72" autocomplete="new-password" required></label>
            <label for="novo-perfil">Perfil<select id="novo-perfil" name="perfil"><?php foreach (PERFIS as $valor => $rotulo): ?><option value="<?= e($valor) ?>" <?= $valor === 'visualizador' ? 'selected' : '' ?>><?= e($rotulo) ?></option><?php endforeach; ?></select></label>
            <label for="confirmar-admin">Confirme sua senha de administrador<input type="password" id="confirmar-admin" name="senha_atual" autocomplete="current-password" maxlength="72" required></label>
        </div><button type="submit" class="botao">Criar usuário</button>
    </form>
</section>
<section class="painel lista-usuarios"><h2>Contas cadastradas</h2>
    <p>Cadastros públicos chegam como Visualizadores desativados. Para aprovar um acesso, abra a conta, selecione <strong>Ativo</strong> e confirme com sua senha de administrador.</p>
    <?php foreach (array_slice($lista, 0, 25) as $u): ?>
        <details class="usuario-item"><summary><strong><?= e($u['nome']) ?></strong> · <?= e(PERFIS[$u['perfil']]) ?> · <?= $u['ativo'] ? 'Ativo' : 'Desativado' ?></summary>
            <p><?= e($u['email']) ?></p><form method="post" action="usuarios.php"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="acao" value="atualizar"><input type="hidden" name="id" value="<?= e($u['id']) ?>">
                <div class="campos"><label>Perfil de <?= e($u['nome']) ?><select name="perfil"><?php foreach (PERFIS as $valor => $rotulo): ?><option value="<?= e($valor) ?>" <?= $valor === $u['perfil'] ? 'selected' : '' ?>><?= e($rotulo) ?></option><?php endforeach; ?></select></label>
                    <label>Estado da conta<select name="ativo"><option value="1" <?= $u['ativo'] ? 'selected' : '' ?>>Ativo</option><option value="0" <?= !$u['ativo'] ? 'selected' : '' ?>>Desativado</option></select></label>
                    <label>Sua senha de administrador<input type="password" name="senha_atual" autocomplete="current-password" maxlength="72" required></label>
                </div><button class="botao" type="submit">Salvar acesso e revogar sessões</button>
            </form>
        </details>
    <?php endforeach; ?>
    <nav class="paginacao" aria-label="Páginas de usuários"><?php if ($atual > 1): ?><a href="?pagina=<?= $atual - 1 ?>">← Anterior</a><?php endif; ?><span>Página <?= $atual ?></span><?php if ($mais): ?><a href="?pagina=<?= $atual + 1 ?>">Próxima →</a><?php endif; ?></nav>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
