<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/** Testes nunca reutilizam DATABASE_URL/POSTGRES_URL ou CONFIG/local.php. */
function bancoTesteLocal(): array
{
    $p = parse_url(getenv('TEST_DATABASE_URL') ?: '');
    if (!$p || !in_array($p['scheme'] ?? '', ['postgres', 'postgresql'], true)
        || !in_array($p['host'] ?? '', ['127.0.0.1', 'localhost', '[::1]'], true)
        || !preg_match('~^/hidrocontrol_test[a-z0-9_]*$~D', $p['path'] ?? '')
        || !isset($p['user']) || isset($p['query']) || isset($p['fragment'])) {
        throw new RuntimeException('Defina TEST_DATABASE_URL para PostgreSQL loopback, banco hidrocontrol_test*. Nenhum destino externo é aceito.');
    }
    return $p;
}

function pdoTeste(array $p, ?string $banco = null): PDO
{
    return new PDO('pgsql:host=' . $p['host'] . ';port=' . ($p['port'] ?? 5432) . ';dbname=' . ($banco ?? substr($p['path'], 1)) . ';sslmode=disable;connect_timeout=3',
        rawurldecode($p['user']), rawurldecode($p['pass'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
