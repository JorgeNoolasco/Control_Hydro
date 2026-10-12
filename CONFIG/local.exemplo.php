<?php
// Copie para local.php somente se precisar mudar os padrões locais.
return [
    // A URI opcional tem prioridade sobre os campos separados abaixo.
    // Para Supabase, preencha 'url' com a URI PostgreSQL do pooler.
    // 'url' => 'postgresql://USUARIO:SENHA@HOST:6543/postgres',
    // 'sslmode' => 'require',
    // PostgreSQL local: a autenticação nova requer esse driver.
    'driver' => 'pgsql',
    // Endereço e porta onde o serviço de banco local está escutando.
    'host' => 'localhost',
    'porta' => 5432,
    // Banco local migrado por supabase/migrations/ e credencial restrita do backend.
    'banco' => 'hidrocontrol',
    'usuario' => 'hidrocontrol_app',
    'senha' => '',
    // Use require/verify-full para Supabase; disable apenas no PostgreSQL local.
    'sslmode' => 'disable',
];
