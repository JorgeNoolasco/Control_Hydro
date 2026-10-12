<?php
// Atende GET para exibir o formulário e POST para validar e registrar uma leitura.
require_once __DIR__ . '/includes/funcoes.php';
$titulo = 'Registrar nova leitura';
$descricaoPagina = 'Anote os dados da usina para continuar o acompanhamento.';
$pagina = 'cadastrar.php';
$erro = null;
// Mantém um token imprevisível por sessão para verificar a origem do envio do formulário.
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
// Os valores são reapresentados se houver erro, evitando que o usuário redigite tudo.
$valores = ['nivel_reservatorio' => '', 'temperatura' => '', 'vazao' => '', 'potencia' => '', 'turbina_ligada' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Aceita apenas textos nos campos previstos; arrays enviados no POST são descartados.
    foreach ($valores as $campo => $padrao) {
        $valores[$campo] = is_string($_POST[$campo] ?? null) ? $_POST[$campo] : '';
    }
    try {
        // hash_equals compara os tokens; o teste de tipo evita entrada inesperada.
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            throw new InvalidArgumentException('O formulário expirou. Confira os dados e envie novamente.');
        }
        // O estado só pode ser uma das duas opções efetivamente oferecidas pelo formulário.
        if (!in_array($valores['turbina_ligada'], ['0', '1'], true)) {
            throw new InvalidArgumentException('Selecione o estado da turbina.');
        }
        // Valida o formato dos números e, no construtor, os limites das medições.
        $usina = new Usina('Usina SENAI', lerNumero($_POST, 'nivel_reservatorio'),
            lerNumero($_POST, 'temperatura'), lerNumero($_POST, 'vazao'),
            lerNumero($_POST, 'potencia'), $valores['turbina_ligada'] === '1');
        $dados = $usina->obterDados();
        // O nome pertence ao objeto, mas não é uma coluna da tabela de leituras.
        unset($dados['nome']);
        // Os parâmetros nomeados separam os valores da instrução SQL.
        $consulta = conectar()->prepare('INSERT INTO leituras
            (nivel_reservatorio, temperatura, vazao, potencia, turbina_ligada, status_geral)
            VALUES (:nivel_reservatorio, :temperatura, :vazao, :potencia, :turbina_ligada, :status_geral)');
        $consulta->execute($dados);
        // A mensagem sobrevive ao redirecionamento e o token antigo deixa de valer.
        $_SESSION['sucesso'] = 'Leitura salva. O painel já está atualizado.';
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        // Confirma também a transação antes de redirecionar para outra instância.
        session_write_close();
        // Post/Redirect/Get: atualizar o painel não reenvia o POST de cadastro.
        header('Location: index.php', true, 303);
        exit;
    } catch (InvalidArgumentException $ex) {
        // Erros de preenchimento orientam o usuário; erros de banco usam mensagem genérica.
        $erro = $ex->getMessage();
    } catch (PDOException $ex) {
        $erro = erroBanco();
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="layout-cadastro">
<section class="painel formulario" aria-labelledby="titulo-formulario">
    <h2 id="titulo-formulario">Dados da leitura</h2>
    <p class="subtitulo">Preencha todos os campos com os valores que você coletou.</p>
    <?php if ($erro): ?><p class="alerta atencao" role="alert"><?= e($erro) ?></p><?php endif; ?>
    <form method="post" action="cadastrar.php" data-leitura>
        <!-- O token oculto é conferido no servidor antes de gravar qualquer leitura. -->
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <div class="campos">
            <!-- A lista centraliza rótulos e limites; a validação HTML complementa a do PHP. -->
            <?php foreach ([
                'nivel_reservatorio' => ['Nível do reservatório', '0', '100', '%', 'De 0 a 100% da capacidade.'],
                'temperatura' => ['Temperatura da turbina', '-999.99', '999.99', '°C', 'Informe a temperatura em graus Celsius.'],
                'vazao' => ['Vazão de água', '0', '99999999.99', 'm³/s', 'Volume de água por segundo.'],
                'potencia' => ['Potência gerada', '0', '99999999.99', 'MW', 'Potência elétrica em megawatts.'],
            ] as $campo => [$rotulo, $minimo, $maximo, $unidade, $ajuda]): ?>
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
        <p class="nota-registro">A data e o horário são preenchidos ao salvar a leitura.</p>
        <!-- O JavaScript desabilita o botão durante o envio; cancelar apenas navega ao painel. -->
        <div class="acoes"><button type="submit" class="botao">Salvar leitura</button><a href="index.php">Cancelar</a></div>
    </form>
</section>
<aside class="guia-leitura" aria-labelledby="titulo-guia">
    <h2 id="titulo-guia">Uma leitura de cada vez.</h2>
    <p>Cada registro ajuda a entender como o nível da água e a temperatura mudam ao longo do tempo.</p>
    <h3>Como os alertas funcionam</h3>
    <dl>
        <div><dt>Reservatório</dt><dd>Atenção abaixo de 30% ou acima de 90%.</dd></div>
        <div><dt>Temperatura</dt><dd>Atenção de 70 a 85 °C. Acima disso, a situação é crítica.</dd></div>
        <div><dt>Turbina</dt><dd>Quando está desligada, o registro recebe um alerta.</dd></div>
    </dl>
    <p class="nota-didatica">Essas faixas fazem parte da simulação educacional e não orientam a operação de equipamentos reais.</p>
</aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
