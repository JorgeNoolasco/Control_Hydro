<!DOCTYPE html>
<!-- Estrutura comum: recebe $titulo e $pagina da página que incluiu este arquivo. -->
<html lang="pt-BR">

<head>
    <!-- UTF-8 preserva acentos; viewport ajusta o layout à largura dos dispositivos móveis. -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HidroControl — <?= e($titulo) ?></title>
    <!-- SVGs embutidos usam os arquivos locais sem acrescentar rotas públicas de assets. -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,<?= base64_encode(file_get_contents(__DIR__ . '/../assets/favicon.svg')) ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- defer executa o JavaScript depois que os elementos da página já foram interpretados. -->
    <script src="assets/js/script.js" defer></script>
</head>

<body>
    <a class="pular-conteudo" href="#conteudo">Pular para o conteúdo</a>
    <header class="cabecalho">
        <div class="cabecalho-conteudo">
            <a class="logo" href="index.php" aria-label="HidroControl — página inicial"><?php readfile(__DIR__ . '/../assets/logo.svg'); ?></a>
            <nav aria-label="Navegação principal">
                <!-- aria-current identifica o link ativo visualmente e para leitores de tela. -->
                <?php
                $links = isset($usuario) ? ['index.php' => 'Painel', 'historico.php' => 'Histórico', 'perfil.php' => 'Perfil'] : ['login.php' => 'Entrar', 'registrar.php' => 'Registrar conta'];
                if (isset($usuario) && pode($usuario, 'administrar')) $links += ['usuarios.php' => 'Usuários', 'auditoria.php' => 'Auditoria'];
                foreach ($links as $arquivo => $rotulo): ?>
                    <a href="<?= e($arquivo) ?>" <?= $pagina === $arquivo ? 'aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if (isset($usuario)): ?>
                <div class="conta-atual"><span><?= e($usuario['nome']) ?><small><?= e(PERFIS[$usuario['perfil']]) ?></small></span>
                    <form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><button type="submit" class="botao-sair">Sair</button></form>
                </div>
            <?php else: ?><span class="ambiente">Acesso seguro</span><?php endif; ?>
        </div>
    </header>
    <main id="conteudo" tabindex="-1">
        <div class="topo">
            <!-- O atalho para cadastro aparece nas páginas que não são o próprio formulário. -->
            <div>
                <h1><?= e($titulo) ?></h1>
                <p class="introducao"><?= e($descricaoPagina ?? '') ?></p>
            </div>
            <?php if ($pagina !== 'cadastrar.php' && isset($usuario) && pode($usuario, 'cadastrar')): ?><a class="botao" href="cadastrar.php"><span aria-hidden="true" class="mais">+</span> Registrar leitura</a><?php endif; ?>
        </div>
        <!-- Mensagem temporária: é consumida nesta renderização e não reaparece ao atualizar. -->
        <?php if (!empty($_SESSION['sucesso'])): ?>
            <p class="alerta normal" role="status"><?= e($_SESSION['sucesso']) ?></p>
            <?php unset($_SESSION['sucesso']); ?>
        <?php endif; ?>
