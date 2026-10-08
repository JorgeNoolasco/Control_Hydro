<?php
// Entrada única da função PHP na Vercel; encaminha cada URL para uma página permitida.
declare(strict_types=1);

// Somente estas três páginas podem ser executadas pela função pública.
// Remove os parâmetros da URL antes de consultar a lista de rotas.
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

// Impede que o navegador tente adivinhar um tipo de conteúdo diferente do informado.
header('X-Content-Type-Options: nosniff');
// Arquivos de configuração, SQL e testes nunca são executados a partir de uma URL.
if (!isset($paginas[$caminho])) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    exit('<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>HidroControl</title><h1>Página não encontrada</h1><a href="/">Voltar ao painel</a></html>');
}
// Somente o cadastro recebe POST; as demais páginas oferecem apenas leitura.
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$permitidos = $caminho === '/cadastrar.php' ? ['GET', 'HEAD', 'POST'] : ['GET', 'HEAD'];
if (!in_array($metodo, $permitidos, true)) {
    // 405 informa método não permitido; Allow lista os métodos aceitos pela rota.
    http_response_code(405);
    header('Allow: ' . implode(', ', $permitidos));
    exit;
}
// O caminho vem da lista fixa acima, nunca diretamente de um nome enviado pelo visitante.
require __DIR__ . '/../' . $paginas[$caminho];
