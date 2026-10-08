<?php
declare(strict_types=1);

// Somente estas três páginas podem ser executadas pela função pública.
$caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$paginas = [
    '/' => 'index.php',
    '/index.php' => 'index.php',
    '/cadastrar.php' => 'cadastrar.php',
    '/historico.php' => 'historico.php',
];

// Permite testar com: php -S localhost:8000 api/index.php
if (PHP_SAPI === 'cli-server' && in_array($caminho, ['/assets/css/style.css', '/assets/js/script.js'], true)) {
    return false;
}

header('X-Content-Type-Options: nosniff');
if (!isset($paginas[$caminho])) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    exit('<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>HidroControl</title><h1>Página não encontrada</h1><a href="/">Voltar ao painel</a></html>');
}
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$permitidos = $caminho === '/cadastrar.php' ? ['GET', 'HEAD', 'POST'] : ['GET', 'HEAD'];
if (!in_array($metodo, $permitidos, true)) {
    http_response_code(405);
    header('Allow: ' . implode(', ', $permitidos));
    exit;
}
require __DIR__ . '/../' . $paginas[$caminho];
