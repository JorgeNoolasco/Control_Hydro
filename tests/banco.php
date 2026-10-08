<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../CONFIG/conexao.php';
require_once __DIR__ . '/../CLASSES/SessaoBanco.php';

$pdo = conectar();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') throw new RuntimeException('Este teste requer PostgreSQL.');
$pdo->beginTransaction();
try {
    // Identificador negativo evita consumir a sequência de leituras reais.
    $id = -random_int(1, 2147483647);
    $q = $pdo->prepare('INSERT INTO leituras (id, nivel_reservatorio, temperatura, vazao, potencia, turbina_ligada, status_geral, data_registro)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING turbina_ligada');
    $q->execute([$id, '70.00', '65.00', '420.00', '65.00', '0', 'Atenção', '2026-10-08 01:30:00+00']);
    if ($q->fetchColumn() !== false) throw new RuntimeException('Booleano falso não foi preservado.');
    $q = $pdo->prepare('SELECT count(*) FROM leituras WHERE id = ? AND data_registro >= ? AND data_registro < ?');
    $q->execute([$id, '2026-10-07 00:00:00-03:00', '2026-10-08 00:00:00-03:00']);
    if ((int) $q->fetchColumn() !== 1) throw new RuntimeException('Filtro de data de Brasília falhou.');
    echo "OK: gravação, booleano e filtro de data em Brasília\n";
} finally {
    $pdo->rollBack();
}

$sessao = new SessaoBanco($pdo);
$id = 'teste-' . bin2hex(random_bytes(16));
$dados = "csrf|s:4:\"abcd\";binario|s:3:\"a\0b\";";
try {
    if ($sessao->read($id) !== '') throw new RuntimeException('Sessão nova não está vazia.');
    $sessao->write($id, $dados);
    if (!$sessao->validateId($id) || $sessao->read($id) !== $dados) throw new RuntimeException('Persistência de sessão falhou.');
    $sessao->updateTimestamp($id, $dados);
    echo "OK: sessão persistida e recuperada com dados binários\n";
} finally {
    $sessao->close();
    $sessao->destroy($id);
}
echo "Integração PostgreSQL validada; dados de teste removidos.\n";
