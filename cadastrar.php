<?php
// Atende GET para exibir o formulário e POST para validar e registrar uma leitura.
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/leituras.php';
exigirMetodo(['GET', 'HEAD', 'POST']);
$edicao = $edicao ?? false;
$usuario = exigirAutenticacao($edicao ? 'editar' : 'cadastrar');
$id = $edicao ? idLeitura($_GET['id'] ?? null) : null;
$titulo = $edicao ? 'Editar leitura' : 'Registrar nova leitura';
$descricaoPagina = 'Anote os dados da usina para continuar o acompanhamento.';
$pagina = 'cadastrar.php';
$erro = null;
// Mantém um token imprevisível por sessão para verificar a origem do envio do formulário.
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
// Os valores são reapresentados se houver erro, evitando que o usuário redigite tudo.
$valores = ['nivel_reservatorio' => '', 'temperatura' => '', 'vazao' => '', 'potencia' => '', 'turbina_ligada' => ''];
if ($edicao) {
    $q = conectar()->prepare('SELECT * FROM leituras WHERE id = ?');
    $q->execute([$id]);
    $leitura = $q->fetch();
    if (!$leitura) throw new ErroHttp(404, 'Leitura não encontrada.');
    foreach ($valores as $campo => $_) $valores[$campo] = $campo === 'turbina_ligada' ? ($leitura[$campo] ? '1' : '0') : (string) $leitura[$campo];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf($_POST['csrf'] ?? null);
    // Aceita apenas textos nos campos previstos; arrays enviados no POST são descartados.
    foreach ($valores as $campo => $padrao) {
        $valores[$campo] = is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';
    }
    try {
        salvarLeitura($usuario, $_POST, $id);
        // A mensagem sobrevive ao redirecionamento e o token antigo deixa de valer.
        $_SESSION['sucesso'] = $edicao ? 'Leitura atualizada.' : 'Leitura salva. O painel já está atualizado.';
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        // Confirma também a transação antes de redirecionar para outra instância.
        redirecionar($edicao ? 'historico.php' : 'index.php');
    } catch (InvalidArgumentException $ex) {
        // Erros de preenchimento orientam o usuário; erros de banco usam mensagem genérica.
        $erro = $ex->getMessage();
        http_response_code(422);
    } catch (PDOException $ex) {
        $erro = erroBanco();
        http_response_code(503);
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="layout-cadastro">
    <section class="painel formulario" aria-labelledby="titulo-formulario">
        <h2 id="titulo-formulario">Dados da leitura</h2>
        <p class="subtitulo">Preencha todos os campos com os valores que você coletou.</p>
        <?php if ($erro): ?><p class="alerta atencao" role="alert"><?= e($erro) ?></p><?php endif; ?>
        <form method="post" action="<?= $edicao ? 'editar_leitura.php?id=' . e($id) : 'cadastrar.php' ?>" data-leitura>
            <!-- O token oculto é conferido no servidor antes de gravar qualquer leitura. -->
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <div class="campos">
                <!-- A lista centraliza rótulos e limites; a validação HTML complementa a do PHP. -->
                <?php foreach (
                    [
                        'nivel_reservatorio' => ['Nível do reservatório', '0', '100', '%', 'De 0 a 100% da capacidade.'],
                        'temperatura' => ['Temperatura da turbina', '-999.99', '999.99', '°C', 'Informe a temperatura em graus Celsius.'],
                        'vazao' => ['Vazão de água', '0', '99999999.99', 'm³/s', 'Volume de água por segundo.'],
                        'potencia' => ['Potência gerada', '0', '99999999.99', 'MW', 'Potência elétrica em megawatts.'],
                    ] as $campo => [$rotulo, $minimo, $maximo, $unidade, $ajuda]
                ): ?>
                    <label for="<?= e($campo) ?>"><?= e($rotulo) ?>
                        <span class="campo-unidade">
                            <input type="number" id="<?= e($campo) ?>" name="<?= e($campo) ?>" min="<?= e($minimo) ?>" max="<?= e($maximo) ?>" step="0.01" required aria-describedby="ajuda-<?= e($campo) ?>" value="<?= e($valores[$campo]) ?>">
                            <span><?= e($unidade) ?></span>
                        </span>
                        <span class="ajuda-campo" id="ajuda-<?= e($campo) ?>"><?= e($ajuda) ?></span>
                    </label>
                <?php endforeach; ?>
                <!-- selected preserva a opção escolhida quando o formulário volta com erro. -->
                <label for="turbina_ligada">Estado da turbina
                    <select id="turbina_ligada" name="turbina_ligada" required>
                        <option value="">Selecione o estado</option>
                        <option value="1" <?= $valores['turbina_ligada'] === '1' ? 'selected' : '' ?>>Ligada</option>
                        <option value="0" <?= $valores['turbina_ligada'] === '0' ? 'selected' : '' ?>>Desligada</option>
                    </select>
                </label>
            </div>
            <p class="nota-registro"><?= $edicao ? 'A data original da leitura será preservada.' : 'A data e o horário são preenchidos ao salvar a leitura.' ?></p>
            <!-- O JavaScript desabilita o botão durante o envio; cancelar apenas navega ao painel. -->
            <div class="acoes"><button type="submit" class="botao">Salvar leitura</button><a href="index.php">Cancelar</a></div>
        </form>
        <?php if ($edicao && pode($usuario, 'excluir')): ?>
            <details class="area-exclusao"><summary>Excluir esta leitura</summary>
                <p>Esta ação remove a leitura do histórico. A exclusão ficará registrada na auditoria.</p>
                <form method="post" action="excluir_leitura.php">
                    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= e($id) ?>">
                    <button type="submit" name="confirmar" value="excluir" class="botao botao-perigo">Confirmar exclusão</button>
                </form>
            </details>
        <?php endif; ?>
    </section>
    <aside class="guia-leitura" aria-labelledby="titulo-guia">
        <h2 id="titulo-guia">Uma leitura de cada vez.</h2>
        <p>Cada registro ajuda a entender como o nível da água e a temperatura mudam ao longo do tempo.</p>
        <h3>Como os alertas funcionam</h3>
        <dl>
            <div>
                <dt>Reservatório</dt>
                <dd>Atenção abaixo de 30% ou acima de 90%.</dd>
            </div>
            <div>
                <dt>Temperatura</dt>
                <dd>Atenção de 70 a 85 °C. Acima disso, a situação é crítica.</dd>
            </div>
            <div>
                <dt>Turbina</dt>
                <dd>Quando está desligada, o registro recebe um alerta.</dd>
            </div>
        </dl>
        <p class="nota-didatica">Essas faixas fazem parte da simulação educacional e não orientam a operação de equipamentos reais.</p>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
