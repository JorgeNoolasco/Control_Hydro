<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$raiz = dirname(__DIR__);
$config = json_decode(file_get_contents($raiz . '/vercel.json'), true, 512, JSON_THROW_ON_ERROR);
$rotas = $config['routes'];
foreach ([
    '/assets/css/style.css' => '/assets/css/style.css',
    '/assets/js/script.js' => '/assets/js/script.js',
    '/' => '/api/index.php',
    '/cadastrar.php' => '/api/index.php',
    '/historico.php' => '/api/index.php',
    '/CONFIG/local.php' => '/api/index.php',
    '/database/schema.sql' => '/api/index.php',
    '/tests/usina.php' => '/api/index.php',
    '/assets/segredo.php' => '/api/index.php',
] as $url => $esperado) {
    $destino = null;
    foreach ($rotas as $rota) {
        $padrao = '~^' . $rota['src'] . '$~';
        if (preg_match($padrao, $url)) {
            $destino = preg_replace($padrao, $rota['dest'], $url);
            break;
        }
    }
    if ($destino !== $esperado) throw new RuntimeException('Rota incorreta: ' . $url);
    echo "OK: rota $url\n";
}
foreach (['api/index.php', 'CONFIG/conexao.php', 'CLASSES/usina.php', 'CLASSES/SessaoMySQL.php', 'includes/sessao.php'] as $arquivo) {
    if (!is_file($raiz . '/' . $arquivo)) throw new RuntimeException('Arquivo ausente: ' . $arquivo);
}
require $raiz . '/CONFIG/conexao.php';
$original = getenv('VERCEL');
$hostOriginal = getenv('DB_HOST');
try {
    putenv('VERCEL=1');
    putenv('DB_HOST=');
    try {
        conectar();
        throw new RuntimeException('Conexão na nuvem aceitou configuração ausente.');
    } catch (PDOException $ex) {
        if ($ex->getMessage() !== 'Configuração do banco incompleta.') throw $ex;
        echo "OK: nuvem exige configuração do banco\n";
    }
} finally {
    putenv($original === false ? 'VERCEL' : 'VERCEL=' . $original);
    putenv($hostOriginal === false ? 'DB_HOST' : 'DB_HOST=' . $hostOriginal);
}
echo "Configuração local do deploy validada. Build remoto não executado.\n";
