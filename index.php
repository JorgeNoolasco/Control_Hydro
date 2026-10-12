<?php
// Painel inicial: consulta as últimas leituras e apresenta indicadores, alertas e gráficos.
require_once __DIR__ . '/includes/funcoes.php';
exigirMetodo(['GET', 'HEAD']);
$usuario = exigirAutenticacao();
// O cabeçalho compartilhado usa estas variáveis para o título e o link ativo.
$titulo = 'Visão da usina';
$descricaoPagina = 'Acompanhe a última leitura e o que precisa de atenção.';
$pagina = 'index.php';
$erro = null;
$leituras = [];
try {
    $pdo = conectar();
    // O ID desempata leituras com a mesma data; o limite evita carregar todo o histórico.
    $leituras = $pdo->query('SELECT * FROM leituras ORDER BY data_registro DESC, id DESC LIMIT 20')->fetchAll();
} catch (PDOException $ex) {
    // A interface recebe apenas a mensagem pública, sem detalhes internos do banco.
    $erro = erroBanco();
}
// A primeira linha é a mais recente; null permite tratar o banco ainda vazio.
$ultima = $leituras[0] ?? null;
require __DIR__ . '/includes/header.php';
?>
<!-- Estados da página: falha de consulta, nenhuma leitura ou painel com dados. -->
<?php if ($erro): ?>
    <p class="alerta atencao" role="alert"><?= e($erro) ?></p>
<?php elseif (!$ultima): ?>
    <section class="painel vazio vazio-inicial">
        <div class="reservatorio-vazio" aria-hidden="true"><span></span></div>
        <div>
            <h2>A primeira leitura começa aqui.</h2>
            <p>Registre o nível da água, a temperatura e os dados da turbina. Com eles, você poderá acompanhar a usina ao longo do tempo.</p>
            <?php if (pode($usuario, 'cadastrar')): ?><a class="botao" href="cadastrar.php">Registrar primeira leitura</a><?php endif; ?>
        </div>
    </section>
<?php else:
    // Reaplica as regras de domínio à leitura mais recente para apresentar as medições.
    $usina = usinaDaLeitura($ultima);
    $status = $usina->verificarSituacaoGeral();
    $alertas = $usina->gerarAlertas();
?>
    <div class="resumo-leitura">
        <p>Última leitura <strong><?= e(dataHora($ultima['data_registro'])) ?></strong><span class="fuso">Horário de Brasília</span></p>
        <span class="status <?= classeStatus($status) ?>">Situação geral: <?= e($status) ?></span>
    </div>
    <section class="visao-usina" aria-label="Dados da última leitura">
        <!-- O desenho representa de 0 a 100% a medição real; o valor textual é acessível. -->
        <article class="reservatorio">
            <div class="titulo-reservatorio">
                <h2>Reservatório</h2>
                <span class="status <?= $usina->verificarNivel() === 'Normal' ? 'normal' : 'atencao' ?>"><?= $usina->verificarNivel() === 'Normal' ? 'Normal' : 'Atenção' ?></span>
            </div>
            <div class="reservatorio-corpo">
                <div class="nivel-visual" aria-hidden="true">
                    <div class="tanque">
                        <div class="agua" data-nivel="<?= e((float) $ultima['nivel_reservatorio']) ?>"></div><span class="marca marca-alta"></span><span class="marca marca-baixa"></span>
                    </div>
                    <div class="regua"><span>100%</span><span>50%</span><span>0%</span></div>
                </div>
                <div class="nivel-dados">
                    <p class="valor valor-nivel"><?= numero($ultima['nivel_reservatorio']) ?><small>%</small></p>
                    <p class="descricao">da capacidade<br>do reservatório</p>
                </div>
            </div>
            <p class="referencia">Faixa de referência <strong>30% a 90%</strong></p>
        </article>
        <div class="medicoes">
            <article class="medicao">
                <h2>Temperatura da turbina</h2>
                <p class="valor"><?= numero($ultima['temperatura']) ?> <small>°C</small></p>
                <span class="status <?= classeStatus($usina->verificarTemperatura()) ?>"><?= e($usina->verificarTemperatura()) ?></span>
            </article>
            <article class="medicao">
                <h2>Estado da turbina</h2>
                <p class="valor valor-estado"><?= $ultima['turbina_ligada'] ? 'Ligada' : 'Desligada' ?></p>
                <span class="estado-turbina <?= $ultima['turbina_ligada'] ? 'em-operacao' : 'parada' ?>"><?= $ultima['turbina_ligada'] ? 'Em operação nesta leitura' : 'Parada nesta leitura' ?></span>
            </article>
            <article class="medicao">
                <h2>Vazão de água</h2>
                <p class="valor"><?= numero($ultima['vazao']) ?> <small>m³/s</small></p>
                <p class="descricao">Água que passa pela turbina</p>
            </article>
            <article class="medicao">
                <h2>Potência gerada</h2>
                <p class="valor"><?= numero($ultima['potencia']) ?> <small>MW</small></p>
                <p class="descricao">Geração informada na leitura</p>
            </article>
        </div>
    </section>
    <section class="quadro-alertas <?= classeStatus($status) ?>" aria-label="Alertas da usina">
        <span class="sinal-alerta" aria-hidden="true"><?= $alertas ? '!' : '✓' ?></span>
        <div>
            <h2><?= $alertas ? 'Pontos de atenção nesta leitura' : 'Tudo dentro da faixa esperada' ?></h2>
            <?php if ($alertas): ?>
                <ul><?php foreach ($alertas as $alerta): ?><li><?= e($alerta) ?></li><?php endforeach; ?></ul>
            <?php else: ?>
                <p>Nenhum alerta pelas regras simuladas do projeto.</p>
            <?php endif; ?>
        </div>
    </section>
    <div class="titulo-secao">
        <div>
            <h2>Evolução das leituras</h2>
            <p class="descricao"><?= count($leituras) === 1 ? 'A primeira leitura já está aqui. As próximas vão formar o histórico.' : 'Compare as últimas ' . count($leituras) . ' leituras, da mais antiga para a mais recente.' ?></p>
        </div>
        <a href="historico.php">Ver histórico completo</a>
    </div>
    <div class="secoes">
        <!-- data-campo e data-unidade dizem ao JavaScript qual medição desenhar em cada SVG. -->
        <?php foreach (['temperatura' => ['Temperatura', '°C'], 'nivel_reservatorio' => ['Nível do reservatório', '%']] as $campo => [$rotulo, $unidade]): ?>
            <section class="painel painel-grafico">
                <div class="titulo-grafico">
                    <h3><?= e($rotulo) ?></h3><span><?= e($unidade) ?></span>
                </div>
                <div class="grafico" data-campo="<?= e($campo) ?>" data-unidade="<?= e($unidade) ?>" role="img" aria-label="<?= e($rotulo) ?> nas últimas leituras. Valores disponíveis no histórico."></div>
                <noscript>
                    <p>Ative o JavaScript para visualizar o gráfico.</p>
                </noscript>
            </section>
        <?php endforeach; ?>
    </div>
    <!-- JSON inerte: inverte a ordem para os gráficos e escapa caracteres sensíveis ao HTML. -->
    <script type="application/json" id="dados-graficos">
        <?= json_encode(array_map(static function ($leitura) {
            // Mantém o mesmo horário de Brasília apresentado no painel e no histórico.
            $leitura['data_exibicao'] = dataHora($leitura['data_registro']);
            return $leitura;
        }, array_reverse($leituras)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    </script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
