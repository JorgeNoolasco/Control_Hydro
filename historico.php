<?php
require_once __DIR__ . '/includes/funcoes.php';
$titulo = 'Histórico de leituras';
$pagina = 'historico.php';
$erro = null;
$leituras = [];
$total = 0;
$paginas = 1;
$atual = max(1, (int) filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT));
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$data = is_string($_GET['data'] ?? null) ? $_GET['data'] : '';
try {
    $pdo = conectar();
    $condicoes = [];
    $parametros = [];
    if ($status !== '') {
        if (!in_array($status, ['Normal', 'Atenção', 'Crítico'], true)) throw new InvalidArgumentException('Selecione um status válido.');
        $condicoes[] = 'status_geral = :status';
        $parametros['status'] = $status;
    }
    if ($data !== '') {
        $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if (!$dia || $dia->format('Y-m-d') !== $data || $data < '1000-01-01' || $data > '9999-12-30') {
            throw new InvalidArgumentException('Informe uma data válida.');
        }
        // Intervalo permite aproveitar o índice de data.
        $condicoes[] = 'data_registro >= :inicio AND data_registro < :fim';
        $formato = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? 'Y-m-d H:i:sP' : 'Y-m-d';
        $parametros['inicio'] = $dia->format($formato);
        $parametros['fim'] = $dia->modify('+1 day')->format($formato);
    }
    $where = $condicoes ? ' WHERE ' . implode(' AND ', $condicoes) : '';
    $contagem = $pdo->prepare('SELECT COUNT(*) FROM leituras' . $where);
    $contagem->execute($parametros);
    $total = (int) $contagem->fetchColumn();
    $paginas = max(1, (int) ceil($total / 15));
    $atual = min($atual, $paginas);
    $consulta = $pdo->prepare('SELECT * FROM leituras' . $where . ' ORDER BY data_registro DESC, id DESC LIMIT :limite OFFSET :inicio_pagina');
    foreach ($parametros as $chave => $valor) $consulta->bindValue(':' . $chave, $valor);
    $consulta->bindValue(':limite', 15, PDO::PARAM_INT);
    $consulta->bindValue(':inicio_pagina', ($atual - 1) * 15, PDO::PARAM_INT);
    $consulta->execute();
    $leituras = $consulta->fetchAll();
} catch (InvalidArgumentException $ex) {
    $erro = $ex->getMessage();
} catch (PDOException $ex) {
    $erro = erroBanco();
}
require __DIR__ . '/includes/header.php';
?>
<section class="painel">
    <form method="get" action="historico.php" class="filtros">
        <label for="status">Situação
            <select id="status" name="status">
                <option value="">Todas</option>
                <?php foreach (['Normal', 'Atenção', 'Crítico'] as $opcao): ?>
                    <option value="<?= e($opcao) ?>" <?= $status === $opcao ? 'selected' : '' ?>><?= e($opcao) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label for="data">Data da leitura<input type="date" id="data" name="data" value="<?= e($data) ?>" min="1000-01-01" max="9999-12-30"></label>
        <button class="botao" type="submit">Filtrar</button>
        <a href="historico.php">Limpar filtros</a>
    </form>
    <?php if ($erro): ?>
        <p class="alerta atencao" role="alert"><?= e($erro) ?></p>
    <?php elseif (!$leituras): ?>
        <p class="vazio">Nenhuma leitura encontrada.</p>
    <?php else: ?>
        <div class="tabela" role="region" aria-label="Leituras registradas" tabindex="0">
            <table>
                <caption><?= $total ?> leitura(s) encontrada(s) · Horário de Brasília</caption>
                <thead><tr><th scope="col">Data e hora</th><th scope="col">Nível (%)</th><th scope="col">Temp. (°C)</th><th scope="col">Vazão (m³/s)</th><th scope="col">Potência (MW)</th><th scope="col">Turbina</th><th scope="col">Situação</th></tr></thead>
                <tbody>
                <?php foreach ($leituras as $leitura): ?>
                    <tr>
                        <td><?= e(dataHora($leitura['data_registro'])) ?></td>
                        <?php foreach (['nivel_reservatorio', 'temperatura', 'vazao', 'potencia'] as $campo): ?><td><?= numero($leitura[$campo]) ?></td><?php endforeach; ?>
                        <td><?= $leitura['turbina_ligada'] ? 'Ligada' : 'Desligada' ?></td>
                        <td><span class="status <?= classeStatus($leitura['status_geral']) ?>"><?= e($leitura['status_geral']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <nav class="paginacao" aria-label="Páginas do histórico">
            <?php if ($atual > 1): ?><a href="?<?= e(http_build_query(['status' => $status, 'data' => $data, 'pagina' => $atual - 1])) ?>">← Anterior</a><?php endif; ?>
            <span>Página <?= $atual ?> de <?= $paginas ?></span>
            <?php if ($atual < $paginas): ?><a href="?<?= e(http_build_query(['status' => $status, 'data' => $data, 'pagina' => $atual + 1])) ?>">Próxima →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
