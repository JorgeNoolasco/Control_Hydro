<?php
require_once __DIR__ . '/includes/funcoes.php';
$titulo = 'Painel de Monitoramento';
$pagina = 'index.php';
$erro = null;
$leituras = [];
try {
    $pdo = conectar();
    $leituras = $pdo->query('SELECT * FROM leituras ORDER BY data_registro DESC, id DESC LIMIT 20')->fetchAll();
} catch (PDOException $ex) {
    $erro = erroBanco();
}
$ultima = $leituras[0] ?? null;
require __DIR__ . '/includes/header.php';
?>
<?php if ($erro): ?>
    <p class="alerta atencao" role="alert"><?= e($erro) ?></p>
<?php elseif (!$ultima): ?>
    <section class="painel vazio">
        <span class="icone-vazio" aria-hidden="true">≈</span>
        <h2>Vamos começar?</h2>
        <p>Nenhuma leitura registrada. Adicione os primeiros dados para acompanhar a usina.</p>
        <a class="botao" href="cadastrar.php">Cadastrar primeira leitura</a>
    </section>
<?php else:
    $usina = usinaDaLeitura($ultima);
    $status = $usina->verificarSituacaoGeral();
?>
    <p class="subtitulo">Última leitura: <?= e(dataHora($ultima['data_registro'])) ?> · Horário de Brasília</p>
    <section class="grid" aria-label="Dados da última leitura">
        <article class="card">
            <h2>Nível do reservatório</h2>
            <div class="valor"><?= numero($ultima['nivel_reservatorio']) ?> <small>%</small></div>
            <progress max="100" value="<?= e($ultima['nivel_reservatorio']) ?>" aria-label="Nível do reservatório"></progress>
            <span class="status <?= $usina->verificarNivel() === 'Normal' ? 'normal' : 'atencao' ?>"><?= e($usina->verificarNivel()) ?></span>
        </article>
        <article class="card">
            <h2>Temperatura da turbina</h2>
            <div class="valor"><?= numero($ultima['temperatura']) ?> <small>°C</small></div>
            <span class="status <?= classeStatus($usina->verificarTemperatura()) ?>"><?= e($usina->verificarTemperatura()) ?></span>
        </article>
        <article class="card">
            <h2>Vazão de água</h2>
            <div class="valor"><?= numero($ultima['vazao']) ?> <small>m³/s</small></div>
            <p class="descricao">Fluxo de água informado</p>
        </article>
        <article class="card">
            <h2>Potência gerada</h2>
            <div class="valor"><?= numero($ultima['potencia']) ?> <small>MW</small></div>
            <p class="descricao">Potência elétrica informada</p>
        </article>
        <article class="card">
            <h2>Estado da turbina</h2>
            <div class="valor"><?= $ultima['turbina_ligada'] ? 'Ligada' : 'Desligada' ?></div>
            <span class="status <?= $ultima['turbina_ligada'] ? 'normal' : 'atencao' ?>"><?= e($usina->verificarTurbina()) ?></span>
        </article>
        <article class="card">
            <h2>Situação geral</h2>
            <div class="valor"><span class="status <?= classeStatus($status) ?>"><?= e($status) ?></span></div>
            <p class="descricao">Avaliação da última leitura</p>
        </article>
    </section>
    <section class="painel espacamento">
        <h2>Alertas da usina</h2>
        <?php foreach ($usina->gerarAlertas() ?: ['Todos os parâmetros monitorados estão normais.'] as $alerta): ?>
            <p class="alerta <?= classeStatus($status) ?>"><?= e($alerta) ?></p>
        <?php endforeach; ?>
    </section>
    <div class="secoes">
        <?php foreach (['temperatura' => ['Temperatura', '°C'], 'nivel_reservatorio' => ['Nível do reservatório', '%']] as $campo => [$rotulo, $unidade]): ?>
            <section class="painel">
                <h2><?= e($rotulo) ?></h2>
                <p class="descricao">Últimas <?= count($leituras) ?> leituras · da mais antiga para a mais recente</p>
                <div class="grafico" data-campo="<?= e($campo) ?>" data-unidade="<?= e($unidade) ?>" role="img" aria-label="<?= e($rotulo) ?> nas últimas leituras. Valores disponíveis no histórico."></div>
                <noscript><p>Ative o JavaScript para visualizar o gráfico.</p></noscript>
                <a href="historico.php">Consultar valores no histórico →</a>
            </section>
        <?php endforeach; ?>
    </div>
    <script type="application/json" id="dados-graficos"><?= json_encode(array_reverse($leituras), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>