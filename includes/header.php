<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HidroControl — <?= e($titulo) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js" defer></script>
</head>
<body>
<header>
    <a class="logo" href="index.php"><span aria-hidden="true">≈</span> HidroControl</a>
    <nav aria-label="Navegação principal">
        <?php foreach (['index.php' => 'Painel', 'cadastrar.php' => 'Nova leitura', 'historico.php' => 'Histórico'] as $arquivo => $rotulo): ?>
            <a href="<?= e($arquivo) ?>" <?= $pagina === $arquivo ? 'aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
        <?php endforeach; ?>
    </nav>
    <span class="ambiente">Usina </span>
</header>
<main>
    <div class="topo">
        <div><p class="sobretitulo">MONITORAMENTO HIDRELÉTRICO</p><h1><?= e($titulo) ?></h1></div>
        <?php if ($pagina !== 'cadastrar.php'): ?><a class="botao" href="cadastrar.php">+ Registrar nova leitura</a><?php endif; ?>
    </div>
    <?php if (!empty($_SESSION['sucesso'])): ?>
        <p class="alerta normal" role="status"><?= e($_SESSION['sucesso']) ?></p>
        <?php unset($_SESSION['sucesso']); ?>
    <?php endif; ?>