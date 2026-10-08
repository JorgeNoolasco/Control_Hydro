<?php
declare(strict_types=1);

try {
    if (naVercel() || ambiente('SESSION_DRIVER') === 'database') {
        require_once __DIR__ . '/../CLASSES/SessaoMySQL.php';
        session_set_save_handler(new SessaoMySQL(conectar()), true);
    }
    session_name('hidrocontrol');
    session_start([
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => naVercel() || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'gc_maxlifetime' => 7200,
        'gc_probability' => 1,
        'gc_divisor' => 100,
    ]);
    header('Cache-Control: private, no-store');
} catch (PDOException $ex) {
    // Não revelar detalhes da conexão nem seguir com um formulário sem sessão.
    error_log('HidroControl: falha ao iniciar a sessão MySQL. Código ' . $ex->getCode());
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    exit('<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>HidroControl</title><h1>HidroControl</h1><p>O banco de dados está indisponível ou ainda não foi configurado. Tente novamente em instantes.</p></html>');
}
