<?php
// Verificação local de rotas e configuração; usa credenciais fictícias, sem conectar ao banco.
declare(strict_types=1);
// Evita executar o utilitário por uma requisição web fora do roteador oficial.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Resolve arquivos a partir da raiz e faz JSON inválido interromper o teste.
$raiz = dirname(__DIR__);
$config = json_decode(file_get_contents($raiz . '/vercel.json'), true, 512, JSON_THROW_ON_ERROR);
$rotas = $config['routes'];
// Assets são servidos diretamente; qualquer outra URL deve passar pelo roteador PHP.
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
        // Simula a primeira regra correspondente e a substituição dos grupos, como $1.
        $padrao = '~^' . $rota['src'] . '$~';
        if (preg_match($padrao, $url)) {
            $destino = preg_replace($padrao, $rota['dest'], $url);
            break;
        }
    }
    if ($destino !== $esperado) throw new RuntimeException('Rota incorreta: ' . $url);
    echo "OK: rota $url\n";
}
// Detecta arquivos essenciais ausentes antes de tentar publicar o pacote.
foreach (['api/index.php', 'CONFIG/conexao.php', 'CLASSES/usina.php', 'CLASSES/SessaoBanco.php', 'includes/sessao.php'] as $arquivo) {
    if (!is_file($raiz . '/' . $arquivo)) throw new RuntimeException('Arquivo ausente: ' . $arquivo);
}
require $raiz . '/CONFIG/conexao.php';
// Isola o ambiente do teste e guarda os valores anteriores para restaurá-los no finally.
$chaves = ['VERCEL', 'DATABASE_URL', 'POSTGRES_URL', 'DB_DRIVER', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_SSLMODE'];
$originais = [];
foreach ($chaves as $chave) { $originais[$chave] = getenv($chave); putenv($chave); }
try {
    // Simula produção sem credenciais: a validação precisa falhar antes de conectar.
    putenv('VERCEL=1');
    putenv('DB_HOST=');
    try {
        configuracaoBanco();
        throw new RuntimeException('Conexão na nuvem aceitou configuração ausente.');
    } catch (PDOException $ex) {
        if ($ex->getMessage() !== 'Configuração do banco incompleta.') throw $ex;
        echo "OK: nuvem exige configuração do banco\n";
    }
    // Confere a URI da integração, incluindo a decodificação dos caracteres da senha fictícia.
    putenv('POSTGRES_URL=postgresql://postgres.projeto:senha%40%3A%2F%23@pooler.example.com:6543/postgres?sslmode=require');
    $pg = configuracaoBanco();
    if ($pg['driver'] !== 'pgsql' || $pg['porta'] !== '6543' || $pg['usuario'] !== 'postgres.projeto'
        || $pg['senha'] !== 'senha@:/#' || $pg['sslmode'] !== 'require') {
        throw new RuntimeException('URL PostgreSQL não foi interpretada corretamente.');
    }
    echo "OK: POSTGRES_URL e senha com caracteres escapados\n";
    // Uma configuração explícita deve ter prioridade sobre a URI gerada pela integração.
    putenv('DATABASE_URL=postgres://outro:segredo@pooler.example.com:5432/postgres');
    if (configuracaoBanco()['usuario'] !== 'outro') throw new RuntimeException('DATABASE_URL não tem prioridade.');
    echo "OK: DATABASE_URL tem prioridade\n";
    // Desativar TLS na nuvem deve ser rejeitado mesmo com uma URI válida.
    putenv('DB_SSLMODE=disable');
    try {
        configuracaoBanco();
        throw new RuntimeException('TLS foi desativado na nuvem.');
    } catch (PDOException $ex) {
        if ($ex->getMessage() !== 'Modo SSL do banco inválido.') throw $ex;
        echo "OK: PostgreSQL exige TLS na nuvem\n";
    }
    putenv('DB_SSLMODE');
    // Rejeita protocolo incorreto, URI incompleta e tentativa de acrescentar opções ao DSN.
    foreach (['mysql://usuario:senha@host/banco', 'postgresql://host', 'postgresql://u:s@host/banco%3Bsslmode=disable'] as $invalida) {
        putenv('DATABASE_URL=' . $invalida);
        try {
            configuracaoBanco();
            throw new RuntimeException('URL inválida foi aceita.');
        } catch (PDOException $ex) { echo "OK: URL inválida rejeitada\n"; }
    }
} finally {
    // A restauração ocorre inclusive se uma verificação lançar exceção.
    foreach ($originais as $chave => $valor) putenv($valor === false ? $chave : $chave . '=' . $valor);
}
echo "Configuração local do deploy validada. Build remoto não executado.\n";
