<?php
// Copie para local.php somente se precisar mudar os padrões locais.
return [
    // A URI opcional tem prioridade sobre os campos separados abaixo.
    // Para Supabase, preencha 'url' com a URI PostgreSQL do pooler.
    // 'url' => 'postgresql://USUARIO:SENHA@HOST:6543/postgres',
    // 'sslmode' => 'require',
    // Padrões para MySQL local; este modelo não contém credenciais de produção.
    'driver' => 'mysql',
    // Endereço e porta onde o serviço de banco local está escutando.
    'host' => 'localhost',
    'porta' => 3306,
    // Banco criado por database/hidrocontrol.sql e credenciais do usuário local.
    'banco' => 'hidrocontrol',
    'usuario' => 'root',
    'senha' => '',
];
