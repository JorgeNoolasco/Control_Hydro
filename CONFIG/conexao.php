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

function conectar(): PDO
{
    // Na nuvem, as credenciais vêm apenas das variáveis do projeto na Vercel.
    $local = !naVercel() && is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];
    $host = ambiente('DB_HOST', $local['host'] ?? 'localhost');
    $porta = ambiente('DB_PORT', (string) ($local['porta'] ?? 3306));
    $banco = ambiente('DB_NAME', $local['banco'] ?? 'hidrocontrol');
    $usuario = ambiente('DB_USER', $local['usuario'] ?? 'root');
    $senha = ambiente('DB_PASSWORD', $local['senha'] ?? '');

    if (naVercel()) {
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $chave) {
            if (ambiente($chave) === '') throw new PDOException('Configuração do banco incompleta.');
        }
    }
    $opcoes = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ];
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
    $pdo = new PDO("mysql:host=$host;port=$porta;dbname=$banco;charset=utf8mb4", $usuario, $senha, $opcoes);
    if ($tls) {
        $ssl = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_NUM);
        if (empty($ssl[1])) throw new PDOException('O banco não estabeleceu uma conexão TLS.');
    }
    $pdo->exec("SET time_zone = '-03:00'");
    return $pdo;
}
