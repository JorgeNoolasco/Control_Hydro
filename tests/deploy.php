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
foreach (['api/index.php', 'CONFIG/conexao.php', 'CLASSES/usina.php', 'CLASSES/SessaoBanco.php', 'includes/sessao.php'] as $arquivo) {
    if (!is_file($raiz . '/' . $arquivo)) throw new RuntimeException('Arquivo ausente: ' . $arquivo);
}
require $raiz . '/CONFIG/conexao.php';
$chaves = ['VERCEL', 'DATABASE_URL', 'POSTGRES_URL', 'DB_DRIVER', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_SSLMODE'];
$originais = [];
foreach ($chaves as $chave) { $originais[$chave] = getenv($chave); putenv($chave); }
try {
    putenv('VERCEL=1');
    putenv('DB_HOST=');
    try {
        configuracaoBanco();
        throw new RuntimeException('Conexão na nuvem aceitou configuração ausente.');
    } catch (PDOException $ex) {
        if ($ex->getMessage() !== 'Configuração do banco incompleta.') throw $ex;
        echo "OK: nuvem exige configuração do banco\n";
    }
    putenv('POSTGRES_URL=postgresql://postgres.projeto:senha%40%3A%2F%23@pooler.example.com:6543/postgres?sslmode=require');
    $pg = configuracaoBanco();
    if ($pg['driver'] !== 'pgsql' || $pg['porta'] !== '6543' || $pg['usuario'] !== 'postgres.projeto'
        || $pg['senha'] !== 'senha@:/#' || $pg['sslmode'] !== 'require') {
        throw new RuntimeException('URL PostgreSQL não foi interpretada corretamente.');
    }
    echo "OK: POSTGRES_URL e senha com caracteres escapados\n";
    putenv('DATABASE_URL=postgres://outro:segredo@pooler.example.com:5432/postgres');
    if (configuracaoBanco()['usuario'] !== 'outro') throw new RuntimeException('DATABASE_URL não tem prioridade.');
    echo "OK: DATABASE_URL tem prioridade\n";
    putenv('DB_SSLMODE=disable');
    try {
        configuracaoBanco();
        throw new RuntimeException('TLS foi desativado na nuvem.');
    } catch (PDOException $ex) {
        if ($ex->getMessage() !== 'Modo SSL do banco inválido.') throw $ex;
        echo "OK: PostgreSQL exige TLS na nuvem\n";
    }
    putenv('DB_SSLMODE');
    foreach (['mysql://usuario:senha@host/banco', 'postgresql://host', 'postgresql://u:s@host/banco%3Bsslmode=disable'] as $invalida) {
        putenv('DATABASE_URL=' . $invalida);
        try {
            configuracaoBanco();
            throw new RuntimeException('URL inválida foi aceita.');
        } catch (PDOException $ex) { echo "OK: URL inválida rejeitada\n"; }
    }
} finally {
    foreach ($originais as $chave => $valor) putenv($valor === false ? $chave : $chave . '=' . $valor);
}
echo "Configuração local do deploy validada. Build remoto não executado.\n";
