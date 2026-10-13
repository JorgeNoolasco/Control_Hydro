<?php
require_once __DIR__ . '/includes/funcoes.php';
exigirMetodo(['GET', 'HEAD']);
$usuario = exigirAutenticacao('administrar');
$titulo = 'Auditoria';
$descricaoPagina = 'Acompanhe acessos e alterações realizadas no sistema.';
$pagina = 'auditoria.php';
$erro = null;
$logs = [];
$filtros = ['data' => '', 'usuario' => '', 'acao' => ''];
$mais = false;
$atual = max(1, min(100000, (int) filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT)));
try {
    foreach ($filtros as $campo => $_) $filtros[$campo] = textoEntrada($_GET, $campo, 50);
    $where = [];
    $params = [];
    if ($filtros['data'] !== '') {
        $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $filtros['data']);
        if (!$dia || $dia->format('Y-m-d') !== $filtros['data'] || $filtros['data'] < '1000-01-01' || $filtros['data'] > '9999-12-30') throw new InvalidArgumentException('Informe uma data válida.');
        $where[] = 'l.criado_em >= :inicio AND l.criado_em < :fim';
        $params['inicio'] = $dia->format('Y-m-d H:i:sP');
        $params['fim'] = $dia->modify('+1 day')->format('Y-m-d H:i:sP');
    }
    if ($filtros['usuario'] !== '') {
        $where[] = 'l.usuario_id = :usuario';
        $params['usuario'] = uuidValido($filtros['usuario']);
    }
    if ($filtros['acao'] !== '') {
        if (!in_array($filtros['acao'], Autenticacao::ACOES, true)) throw new InvalidArgumentException('Ação inválida.');
        $where[] = 'l.acao = :acao';
        $params['acao'] = $filtros['acao'];
    }
    $q = conectar()->prepare('SELECT l.id, l.criado_em, l.acao, l.recurso_id, l.detalhes, u.nome FROM logs_auditoria l LEFT JOIN usuarios u ON u.id = l.usuario_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY l.criado_em DESC, l.id DESC LIMIT 51 OFFSET :offset');
    foreach ($params as $chave => $valor) $q->bindValue(':' . $chave, $valor);
    $q->bindValue(':offset', ($atual - 1) * 50, PDO::PARAM_INT);
    $q->execute();
    $logs = $q->fetchAll();
    $mais = count($logs) > 50;
} catch (InvalidArgumentException $ex) {
    http_response_code(422);
    $erro = $ex->getMessage();
}
$usuarios = conectar()->query('SELECT id, nome FROM usuarios ORDER BY nome, id')->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<section class="painel">
    <form class="filtros" method="get" action="auditoria.php">
        <label>Data (Brasília)<input type="date" name="data" min="1000-01-01" max="9999-12-30" value="<?= e($filtros['data']) ?>"></label>
        <label>Usuário<select name="usuario">
                <option value="">Todos</option><?php foreach ($usuarios as $u): ?><option value="<?= e($u['id']) ?>" <?= $filtros['usuario'] === $u['id'] ? 'selected' : '' ?>><?= e($u['nome']) ?></option><?php endforeach; ?>
            </select></label>
        <label>Ação<select name="acao">
                <option value="">Todas</option><?php foreach (Autenticacao::ACOES as $acao): ?><option value="<?= e($acao) ?>" <?= $filtros['acao'] === $acao ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $acao)) ?></option><?php endforeach; ?>
            </select></label>
        <button type="submit" class="botao">Filtrar</button><a href="auditoria.php">Limpar</a>
    </form>
    <?php if ($erro): ?><p class="alerta atencao" role="alert"><?= e($erro) ?></p><?php else: ?>
        <div class="tabela" role="region" tabindex="0" aria-label="Eventos de auditoria">
            <table>
                <thead>
                    <tr>
                        <th>Data e hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Registro</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($logs, 0, 50) as $log): ?><tr>
                            <td><?= e(dataHora($log['criado_em'])) ?></td>
                            <td><?= e($log['nome'] ?? 'Não identificado') ?></td>
                            <td><?= e(str_replace('_', ' ', $log['acao'])) ?></td>
                            <td><?= e($log['recurso_id'] ?? '—') ?></td>
                            <td><?= e($log['detalhes']) ?></td>
                        </tr><?php endforeach; ?>
                    <?php if (!$logs): ?><tr>
                            <td colspan="5">Nenhum evento encontrado.</td>
                        </tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <nav class="paginacao" aria-label="Páginas da auditoria"><?php if ($atual > 1): ?><a href="?<?= e(http_build_query($filtros + ['pagina' => $atual - 1])) ?>">← Anterior</a><?php endif; ?><span>Página <?= $atual ?></span><?php if ($mais): ?><a href="?<?= e(http_build_query($filtros + ['pagina' => $atual + 1])) ?>">Próxima →</a><?php endif; ?></nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>