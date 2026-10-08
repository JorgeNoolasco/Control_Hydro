<?php
declare(strict_types=1);

function ambiente(string $nome, string $padrao = ''): string
{
    $valor = getenv($nome);
    return $valor === false ? $padrao : $valor;
}

function naVercel(): bool
{
    return ambiente('VERCEL') === '1';
}

function configuracaoBanco(): array
{
    $local = !naVercel() && is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];
    $url = ambiente('DATABASE_URL');
    if ($url === '') $url = ambiente('POSTGRES_URL', $local['url'] ?? '');
    $driver = ambiente('DB_DRIVER', $local['driver'] ?? (naVercel() ? 'pgsql' : 'mysql'));
    $partes = [];
    if ($url !== '') {
        $partes = parse_url($url);
        if ($partes === false || !in_array($partes['scheme'] ?? '', ['postgres', 'postgresql'], true)
            || empty($partes['host']) || empty($partes['user']) || empty($partes['path'])) {
            throw new PDOException('URL PostgreSQL inválida.');
        }
        $driver = 'pgsql';
    }
    if (!in_array($driver, ['mysql', 'pgsql'], true)) throw new PDOException('Driver de banco inválido.');
    $host = ambiente('DB_HOST', $local['host'] ?? 'localhost');
    $porta = ambiente('DB_PORT', (string) ($local['porta'] ?? ($driver === 'pgsql' ? 6543 : 3306)));
    $banco = ambiente('DB_NAME', $local['banco'] ?? ($driver === 'pgsql' ? 'postgres' : 'hidrocontrol'));
    $usuario = ambiente('DB_USER', $local['usuario'] ?? ($driver === 'pgsql' ? 'postgres' : 'root'));
    $senha = ambiente('DB_PASSWORD', $local['senha'] ?? '');
    $parametros = [];
    if ($url !== '') {
        $host = $partes['host'];
        $porta = (string) ($partes['port'] ?? 5432);
        $banco = rawurldecode(ltrim($partes['path'], '/'));
        $usuario = rawurldecode($partes['user']);
        $senha = rawurldecode($partes['pass'] ?? '');
        parse_str($partes['query'] ?? '', $parametros);
    } elseif (naVercel()) {
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $chave) {
            if (ambiente($chave) === '') throw new PDOException('Configuração do banco incompleta.');
        }
    }
    if ($host === '' || $banco === '' || $usuario === '' || (naVercel() && $senha === '')
        || !ctype_digit($porta) || (int) $porta < 1 || (int) $porta > 65535) {
        throw new PDOException('Configuração do banco incompleta.');
    }
    // PDO usa ponto e vírgula como separador do DSN.
    foreach ([$host, $banco] as $valor) {
        if (preg_match('/[;\x00\r\n]/', $valor)) throw new PDOException('Configuração do banco inválida.');
    }
    $sslmode = ambiente('DB_SSLMODE', $local['sslmode'] ?? ($parametros['sslmode'] ?? 'require'));
    if (!is_string($sslmode) || !in_array($sslmode, ['disable', 'require', 'verify-ca', 'verify-full'], true)
        || ($driver === 'pgsql' && naVercel() && $sslmode === 'disable')) {
        throw new PDOException('Modo SSL do banco inválido.');
    }
    return compact('driver', 'host', 'porta', 'banco', 'usuario', 'senha', 'sslmode');
}

function conectar(): PDO
{
    // Uma conexão por requisição: leitura, gravação e sessão compartilham a transação.
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $config = configuracaoBanco();
    extract($config, EXTR_SKIP);
    $opcoes = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ];
    if ($driver === 'pgsql') {
        if (!extension_loaded('pdo_pgsql')) throw new PDOException('Extensão pdo_pgsql indisponível.');
        // Supavisor transaction pooler não mantém prepared statements entre transações.
        $opcoes[PDO::PGSQL_ATTR_DISABLE_PREPARES] = true;
        $conexao = new PDO("pgsql:host=$host;port=$porta;dbname=$banco;sslmode=$sslmode;connect_timeout=10", $usuario, $senha, $opcoes);
        return $pdo = $conexao;
    }
    $tls = filter_var(ambiente('DB_SSL', naVercel() ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN);
    if ($tls) {
        // CA pública do provedor (arquivo PEM), ou certificados do sistema.
        $ca = ambiente('DB_SSL_CA');
        if ($ca === '') {
            foreach ([ini_get('openssl.cafile'), '/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/certs/ca-certificates.crt'] as $arquivo) {
                if ($arquivo && is_readable($arquivo)) { $ca = $arquivo; break; }
            }
        }
        if ($ca === '' || !is_readable($ca)) throw new PDOException('Certificado CA não configurado.');
        $opcoes[PDO::MYSQL_ATTR_SSL_CA] = $ca;
        $opcoes[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    $conexao = new PDO("mysql:host=$host;port=$porta;dbname=$banco;charset=utf8mb4", $usuario, $senha, $opcoes);
    if ($tls) {
        $ssl = $conexao->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_NUM);
        if (empty($ssl[1])) throw new PDOException('O banco não estabeleceu uma conexão TLS.');
    }
    $conexao->exec("SET time_zone = '-03:00'");
    return $pdo = $conexao;
}
