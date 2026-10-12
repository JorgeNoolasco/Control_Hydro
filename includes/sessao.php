<?php
// Inicia a sessão antes de qualquer HTML, pois cookies e cabeçalhos precisam vir primeiro.
declare(strict_types=1);

try {
    // Na Vercel o disco não compartilha sessões; localmente o banco é opcional.
    if (producao() || ambiente('SESSION_DRIVER', 'database') === 'database') {
        require_once __DIR__ . '/../CLASSES/SessaoBanco.php';
        session_set_save_handler(new SessaoBanco(conectar()), true);
    }
    // Usa um nome próprio para o cookie de sessão deste aplicativo.
    session_name('hidrocontrol');
    if (!session_start([
        // Modo estrito rejeita IDs inválidos; o identificador só é aceito por cookie.
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'use_trans_sid' => false,
        'cookie_path' => '/',
        'cookie_lifetime' => 0,
        // HttpOnly bloqueia leitura por JavaScript; Lax limita envio em navegação entre sites.
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        // Exige HTTPS na nuvem, mas permite desenvolvimento local em HTTP.
        'cookie_secure' => producao() || requisicaoHttps(),
        // A validade acompanha a inatividade; a limpeza é sorteada em 1% das inicializações.
        'gc_maxlifetime' => tempoInatividade(),
        'gc_probability' => 1,
        'gc_divisor' => 100,
    ])) throw new RuntimeException('Não foi possível iniciar a sessão.');
    // Evita que caches reutilizem páginas com token CSRF ou mensagem de outra sessão.
    header('Cache-Control: private, no-store');
} catch (PDOException $ex) {
    // Não revelar detalhes da conexão nem seguir com um formulário sem sessão.
    error_log('HidroControl: falha ao iniciar a sessão no banco. Código ' . $ex->getCode());
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    exit('<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>HidroControl</title><h1>HidroControl</h1><p>O banco de dados está indisponível ou ainda não foi configurado. Tente novamente em instantes.</p></html>');
}
