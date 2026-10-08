<?php
// Centraliza as credenciais e a criação da conexão PDO usada pelas páginas e sessões.
// Tipagem estrita evita conversões implícitas nas chamadas feitas neste arquivo.
declare(strict_types=1);

/** Lê uma variável de ambiente; usa o padrão apenas quando a variável não existe. */
function ambiente(string $nome, string $padrao = ''): string
{
    $valor = getenv($nome);
    return $valor === false ? $padrao : $valor;
}

/** Identifica o ambiente de hospedagem pela variável fornecida pela Vercel. */
function naVercel(): bool
{
    return ambiente('VERCEL') === '1';
}

/** Resolve e valida a configuração sem abrir uma conexão nem registrar senhas. */
function configuracaoBanco(): array
{
    // Credenciais do arquivo local só são aceitas fora da Vercel.
    $local = !naVercel() && is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];
    // A URI explícita tem prioridade sobre a variável gerada pela integração Supabase.
    $url = ambiente('DATABASE_URL');
    if ($url === '') $url = ambiente('POSTGRES_URL', $local['url'] ?? '');
    $driver = ambiente('DB_DRIVER', $local['driver'] ?? (naVercel() ? 'pgsql' : 'mysql'));
    $partes = [];
    if ($url !== '') {
        // Separa protocolo, host, usuário, senha, banco e parâmetros da URI.
        $partes = parse_url($url);
        if ($partes === false || !in_array($partes['scheme'] ?? '', ['postgres', 'postgresql'], true)
            || empty($partes['host']) || empty($partes['user']) || empty($partes['path'])) {
            throw new PDOException('URL PostgreSQL inválida.');
        }
        $driver = 'pgsql';
    }
    // Sem URI, aceita campos separados e mantém os padrões do MySQL local.
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
        // Decodifica caracteres como @ e # que precisam estar escapados na URI.
        $banco = rawurldecode(ltrim($partes['path'], '/'));
        $usuario = rawurldecode($partes['user']);
        $senha = rawurldecode($partes['pass'] ?? '');
        parse_str($partes['query'] ?? '', $parametros);
    } elseif (naVercel()) {
        // Na nuvem, falha cedo em vez de tentar conectar com os padrões locais.
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $chave) {
            if (ambiente($chave) === '') throw new PDOException('Configuração do banco incompleta.');
        }
    }
    // Rejeita campos vazios e portas fora do intervalo TCP válido.
    if ($host === '' || $banco === '' || $usuario === '' || (naVercel() && $senha === '')
        || !ctype_digit($porta) || (int) $porta < 1 || (int) $porta > 65535) {
        throw new PDOException('Configuração do banco incompleta.');
    }
    // PDO usa ponto e vírgula como separador do DSN.
    foreach ([$host, $banco] as $valor) {
        if (preg_match('/[;\x00\r\n]/', $valor)) throw new PDOException('Configuração do banco inválida.');
    }
    // TLS é obrigatório para PostgreSQL na Vercel; require exige criptografia.
    $sslmode = ambiente('DB_SSLMODE', $local['sslmode'] ?? ($parametros['sslmode'] ?? 'require'));
    if (!is_string($sslmode) || !in_array($sslmode, ['disable', 'require', 'verify-ca', 'verify-full'], true)
        || ($driver === 'pgsql' && naVercel() && $sslmode === 'disable')) {
        throw new PDOException('Modo SSL do banco inválido.');
    }
    // compact monta o array associativo com as variáveis já validadas.
    return compact('driver', 'host', 'porta', 'banco', 'usuario', 'senha', 'sslmode');
}

/** Abre a conexão na primeira chamada e reaproveita o mesmo PDO na requisição. */
function conectar(): PDO
{
    // Uma conexão por requisição: leitura, gravação e sessão compartilham a transação.
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $config = configuracaoBanco();
    // Extrai somente a configuração interna; EXTR_SKIP preserva variáveis existentes.
    extract($config, EXTR_SKIP);
    $opcoes = [
        // Erros viram exceções, resultados usam nomes de colunas e parâmetros são nativos.
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ];
    // Caminho PostgreSQL: usa o pooler do Supabase e limita a espera pela conexão.
    if ($driver === 'pgsql') {
        if (!extension_loaded('pdo_pgsql')) throw new PDOException('Extensão pdo_pgsql indisponível.');
        // Supavisor transaction pooler não mantém prepared statements entre transações.
        $opcoes[PDO::PGSQL_ATTR_DISABLE_PREPARES] = true;
        $conexao = new PDO("pgsql:host=$host;port=$porta;dbname=$banco;sslmode=$sslmode;connect_timeout=10", $usuario, $senha, $opcoes);
        return $pdo = $conexao;
    }
    // Caminho MySQL, mantido para desenvolvimento local e provedores compatíveis.
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
    // utf8mb4 preserva acentos, símbolos e outros caracteres Unicode no MySQL.
    $conexao = new PDO("mysql:host=$host;port=$porta;dbname=$banco;charset=utf8mb4", $usuario, $senha, $opcoes);
    if ($tls) {
        // Confirma que o servidor efetivamente negociou uma conexão criptografada.
        $ssl = $conexao->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_NUM);
        if (empty($ssl[1])) throw new PDOException('O banco não estabeleceu uma conexão TLS.');
    }
    // As datas DATETIME do esquema MySQL são gravadas no horário local de Brasília.
    $conexao->exec("SET time_zone = '-03:00'");
    return $pdo = $conexao;
}
