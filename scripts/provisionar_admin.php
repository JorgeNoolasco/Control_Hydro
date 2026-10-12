<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../CLASSES/Autenticacao.php';
try {
    // Stdin evita senhas no histórico ou na lista de processos.
    $entrada = json_decode(stream_get_contents(STDIN, 4096), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($entrada)) throw new InvalidArgumentException();
    segredoAplicacao();
    $auth = new Autenticacao(conectar());
    $auth->provisionar(textoEntrada($entrada, 'nome', 120), textoEntrada($entrada, 'email', 254), senhaEntrada($entrada));
    fwrite(STDOUT, "Primeiro administrador criado. Entre pelo login.\n");
} catch (InvalidArgumentException $ex) {
    fwrite(STDERR, ($ex->getMessage() ?: 'Dados inválidos.') . "\n"); exit(1);
} catch (Throwable $ex) {
    fwrite(STDERR, "Não foi possível provisionar. Verifique configuração, migrações e permissões.\n"); exit(1);
}
