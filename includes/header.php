<!DOCTYPE html>
<!-- Estrutura comum: recebe $titulo e $pagina da página que incluiu este arquivo. -->
<html lang="pt-BR">
<head>
    <!-- UTF-8 preserva acentos; viewport ajusta o layout à largura dos dispositivos móveis. -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HidroControl — <?= e($titulo) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- defer executa o JavaScript depois que os elementos da página já foram interpretados. -->
    <script src="assets/js/script.js" defer></script>
</head>
<body>
<header>
    <a class="logo" href="index.php"><span aria-hidden="true">≈</span> HidroControl</a>
    <nav aria-label="Navegação principal">
        <!-- aria-current identifica o link ativo visualmente e para leitores de tela. -->
        <?php foreach (['index.php' => 'Painel', 'cadastrar.php' => 'Nova leitura', 'historico.php' => 'Histórico'] as $arquivo => $rotulo): ?>
            <a href="<?= e($arquivo) ?>" <?= $pagina === $arquivo ? 'aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
        <?php endforeach; ?>
    </nav>
    <span class="ambiente">Usina </span>
</header>
<main>
    <div class="topo">
        <!-- O atalho para cadastro aparece nas páginas que não são o próprio formulário. -->
        <div><p class="sobretitulo">MONITORAMENTO HIDRELÉTRICO</p><h1><?= e($titulo) ?></h1></div>
        <?php if ($pagina !== 'cadastrar.php'): ?><a class="botao" href="cadastrar.php">+ Registrar nova leitura</a><?php endif; ?>
    </div>
    <!-- Mensagem temporária: é consumida nesta renderização e não reaparece ao atualizar. -->
    <?php if (!empty($_SESSION['sucesso'])): ?>
        <p class="alerta normal" role="status"><?= e($_SESSION['sucesso']) ?></p>
        <?php unset($_SESSION['sucesso']); ?>
    <?php endif; ?>
