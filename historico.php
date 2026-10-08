<?php
// Histórico paginado com filtros opcionais de situação e data recebidos pela URL.
require_once __DIR__ . '/includes/funcoes.php';
$titulo = 'Histórico de leituras';
$pagina = 'historico.php';
$erro = null;
$leituras = [];
$total = 0;
$paginas = 1;
// Normaliza a página para no mínimo 1 e rejeita arrays nos filtros de texto.
$atual = max(1, (int) filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT));
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$data = is_string($_GET['data'] ?? null) ? $_GET['data'] : '';
try {
    $pdo = conectar();
    // As cláusulas vêm do código; os valores enviados pelo visitante viram parâmetros.
    $condicoes = [];
    $parametros = [];
    if ($status !== '') {
        // A lista fechada evita filtros com situações que a aplicação não produz.
        if (!in_array($status, ['Normal', 'Atenção', 'Crítico'], true)) throw new InvalidArgumentException('Selecione um status válido.');
        $condicoes[] = 'status_geral = :status';
        $parametros['status'] = $status;
    }
    if ($data !== '') {
        // ! zera o horário; comparar a data formatada rejeita normalizações como 31/02.
        $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if (!$dia || $dia->format('Y-m-d') !== $data || $data < '1000-01-01' || $data > '9999-12-30') {
            throw new InvalidArgumentException('Informe uma data válida.');
        }
        // Intervalo permite aproveitar o índice de data.
        $condicoes[] = 'data_registro >= :inicio AND data_registro < :fim';
        // PostgreSQL recebe o fuso de Brasília; MySQL usa as datas locais do seu esquema.
        $formato = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? 'Y-m-d H:i:sP' : 'Y-m-d';
        $parametros['inicio'] = $dia->format($formato);
        $parametros['fim'] = $dia->modify('+1 day')->format($formato);
    }
    // A mesma condição é reutilizada na contagem e na busca da página de resultados.
    $where = $condicoes ? ' WHERE ' . implode(' AND ', $condicoes) : '';
    $contagem = $pdo->prepare('SELECT COUNT(*) FROM leituras' . $where);
    $contagem->execute($parametros);
    $total = (int) $contagem->fetchColumn();
    // Cada página contém até 15 registros; mantém a navegação válida mesmo sem resultados.
    $paginas = max(1, (int) ceil($total / 15));
    $atual = min($atual, $paginas);
    // Ordenação estável por data e ID; OFFSET pula os registros das páginas anteriores.
    $consulta = $pdo->prepare('SELECT * FROM leituras' . $where . ' ORDER BY data_registro DESC, id DESC LIMIT :limite OFFSET :inicio_pagina');
    foreach ($parametros as $chave => $valor) $consulta->bindValue(':' . $chave, $valor);
    // LIMIT e OFFSET são vinculados como inteiros, não como textos SQL.
    $consulta->bindValue(':limite', 15, PDO::PARAM_INT);
    $consulta->bindValue(':inicio_pagina', ($atual - 1) * 15, PDO::PARAM_INT);
    $consulta->execute();
    $leituras = $consulta->fetchAll();
} catch (InvalidArgumentException $ex) {
    // Diferencia um filtro inválido de uma falha interna de acesso ao banco.
    $erro = $ex->getMessage();
} catch (PDOException $ex) {
    $erro = erroBanco();
}
require __DIR__ . '/includes/header.php';
?>
<section class="painel">
    <!-- GET mantém filtros na URL para permitir favoritos e navegação entre páginas. -->
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
    <!-- Exibe somente um estado: mensagem de erro, lista vazia ou tabela de resultados. -->
    <?php if ($erro): ?>
        <p class="alerta atencao" role="alert"><?= e($erro) ?></p>
    <?php elseif (!$leituras): ?>
        <p class="vazio">Nenhuma leitura encontrada.</p>
    <?php else: ?>
        <!-- A região focalizável permite rolar tabelas largas também pelo teclado. -->
        <div class="tabela" role="region" aria-label="Leituras registradas" tabindex="0">
            <table>
                <caption><?= $total ?> leitura(s) encontrada(s) · Horário de Brasília</caption>
                <thead><tr><th scope="col">Data e hora</th><th scope="col">Nível (%)</th><th scope="col">Temp. (°C)</th><th scope="col">Vazão (m³/s)</th><th scope="col">Potência (MW)</th><th scope="col">Turbina</th><th scope="col">Situação</th></tr></thead>
                <tbody>
                <!-- Formata números e datas, escapando os valores textuais antes da saída HTML. -->
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
        <!-- Conserva os filtros nos links e só mostra páginas anterior/próxima existentes. -->
        <nav class="paginacao" aria-label="Páginas do histórico">
            <?php if ($atual > 1): ?><a href="?<?= e(http_build_query(['status' => $status, 'data' => $data, 'pagina' => $atual - 1])) ?>">← Anterior</a><?php endif; ?>
            <span>Página <?= $atual ?> de <?= $paginas ?></span>
            <?php if ($atual < $paginas): ?><a href="?<?= e(http_build_query(['status' => $status, 'data' => $data, 'pagina' => $atual + 1])) ?>">Próxima →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
