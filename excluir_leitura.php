<?php
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/leituras.php';
exigirMetodo(['POST']);
$usuario = exigirAutenticacao('excluir');
validarCsrf($_POST['csrf'] ?? null);
$id = idLeitura($_POST['id'] ?? null);
if (($_POST['confirmar'] ?? '') !== 'excluir') throw new ErroHttp(400, 'Confirme a exclusão da leitura.');
transacao(conectar(), function () use ($usuario, $id) {
    autenticacao()->autorizar($usuario, 'excluir');
    $q = conectar()->prepare('DELETE FROM leituras WHERE id = ? RETURNING id');
    $q->execute([$id]);
    if (!$q->fetchColumn()) throw new ErroHttp(404, 'Leitura não encontrada.');
    autenticacao()->auditar('leitura_excluida', $usuario['id'], (string) $id);
});
$_SESSION['csrf'] = bin2hex(random_bytes(32));
$_SESSION['sucesso'] = 'Leitura excluída.';
redirecionar('historico.php');
