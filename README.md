# HidroControl

Monitoramento educacional de uma usina, com leituras manuais, alertas, gráficos SVG e histórico. PHP, PDO, HTML, CSS e JavaScript puro; PostgreSQL local ou Supabase; runtime PHP comunitário na Vercel. A autenticação requer PostgreSQL. Os arquivos MySQL antigos foram preservados apenas como referência histórica.

## Acesso e segurança

| Perfil | Permissões |
| --- | --- |
| Administrador | Consultas, cadastro, edição e exclusão de leituras; usuários e auditoria |
| Operador | Consultas, cadastro e edição de leituras |
| Visualizador | Consultas |

Todas as páginas de dados exigem login. O backend verifica as permissões também em POSTs diretos. Não existe cadastro público. O perfil pessoal altera somente o nome; permissões são alteradas por administradores com confirmação de senha. O último administrador ativo não pode ser desativado ou rebaixado.

Senhas usam `password_hash()`/`password_verify()` (bcrypt, custo 12). Sessões ficam no PostgreSQL, com renovação do ID no login, expiração por inatividade e duração absoluta. Redefinição de senha, mudança de perfil e desativação revogam sessões anteriores. Logout usa POST com CSRF e apaga a sessão no servidor.

Consulte [SEGURANCA.md](SEGURANCA.md) para arquitetura, limites, auditoria e manutenção. A integração de recuperação envia e-mail pela API HTTPS do Resend, sem `mail()` ou Composer.

## Executar localmente

Recomendado: PHP 8.3+ mantido, extensões `pdo_pgsql`, `curl`, `openssl`, `mbstring`, e PostgreSQL 15+. Não é necessário Node para executar PHP localmente. O servidor precisa estar na raiz do domínio; não hospede em uma subpasta.

1. Crie um banco PostgreSQL local vazio. Aplique as migrações de `supabase/migrations/` em ordem, como descrito em [DEPLOY_VERCEL.md](DEPLOY_VERCEL.md). Em PostgreSQL fora do Supabase, crie antes os papéis `anon` e `authenticated` como `NOLOGIN`, pois as migrações iniciais os referenciam.
2. Configure a conexão PDO por variáveis de ambiente ou copie `CONFIG/local.exemplo.php` para `CONFIG/local.php`. Prefira o papel restrito `hidrocontrol_app`, com senha definida fora do código. Não use dados de produção para desenvolvimento.
3. Defina as variáveis da tabela abaixo. PHP puro não carrega `.env` automaticamente; o arquivo `.env.example` é apenas um modelo.
4. Provisione o primeiro administrador pelo terminal.
5. Inicie `php -S 127.0.0.1:8000 api/index.php` e abra `http://127.0.0.1:8000/login.php`.

Exemplo PowerShell, depois de configurar a conexão local:

```powershell
$env:APP_ENV = 'development'
$env:APP_URL = 'http://127.0.0.1:8000'
$env:APP_SECRET = php -r 'echo bin2hex(random_bytes(32));'
$env:SESSION_DRIVER = 'database'
$env:DB_SSLMODE = 'disable' # exclusivamente PostgreSQL local
./scripts/provisionar_admin.ps1
php -S 127.0.0.1:8000 api/index.php
```

O provisionamento solicita a senha sem exibi-la, envia os dados por stdin e recusa execução quando já existem usuários. Não possui rota HTTP. Para Linux/macOS, envie JSON com `nome`, `email` e `senha` por stdin a `php scripts/provisionar_admin.php`, usando um prompt seguro (`getpass`, por exemplo); nunca coloque a senha em argumentos ou arquivos versionados. Guarde `APP_SECRET` em um gerenciador de segredos e mantenha o mesmo valor entre instâncias do mesmo ambiente.

## Variáveis de ambiente

| Variável | Uso / padrão |
| --- | --- |
| `DATABASE_URL` | URI PostgreSQL do papel do backend; tem prioridade sobre `POSTGRES_URL` |
| `POSTGRES_URL` | Alternativa fornecida pela integração Supabase; substitua por `DATABASE_URL` restrita se usar o proprietário |
| `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Alternativa à URI; autenticação requer `pgsql` |
| `DB_SSLMODE` | `require`; `disable` somente no desenvolvimento local; `verify-full` verifica identidade com CA configurada |
| `PGSSLROOTCERT` | Caminho da CA pública para `verify-full` |
| `APP_SECRET` | Obrigatório: pelo menos 64 caracteres hexadecimais aleatórios; HMAC dos identificadores de conta/IP |
| `APP_URL` | Origem canônica sem caminho/query, como `https://seu-dominio`; usada no link de recuperação e no redirecionamento HTTPS |
| `APP_ENV` | `production` fora da Vercel para obrigar HTTPS; na Vercel a proteção é automática, inclusive Preview |
| `SESSION_DRIVER` | `database` por padrão e obrigatório na produção; `files` apenas para desenvolvimento |
| `SESSION_IDLE_TIMEOUT` | Segundos de inatividade: padrão `1800`, entre `60` e `7200` |
| `SESSION_ABSOLUTE_TIMEOUT` | Duração máxima: padrão `28800`, entre `300` e `86400` |
| `RESEND_API_KEY` | Chave restrita a envio, cadastrada no servidor |
| `MAIL_FROM` | E-mail remetente de domínio verificado no Resend, sem nome de exibição |
| `TEST_DATABASE_URL` | Apenas testes; loopback e nome de banco iniciado por `hidrocontrol_test` |

A aplicação falha com resposta genérica se a configuração de segurança ou o banco estiverem indisponíveis. Falhas do remetente são auditadas como `email_falha`; o usuário sempre recebe a resposta genérica de recuperação.

## Testes sem serviços externos

```text
php tests/usina.php
php tests/deploy.php
php tests/seguranca.php
```

A integração usa **somente PostgreSQL e servidores HTTP em loopback**, cria um banco e um papel aleatórios e os remove ao final. Não lê credenciais de `CONFIG/local.php`, não usa o banco configurado para a aplicação e não chama Resend/Supabase/Vercel. Use um cluster local descartável: o usuário de testes precisa de `CREATEDB` e `CREATEROLE` para preparar a infraestrutura e testar os privilégios.

```powershell
# Crie uma vez o banco vazio hidrocontrol_test em um PostgreSQL local de testes.
$env:TEST_DATABASE_URL = 'postgresql://USUARIO_LOCAL@127.0.0.1:5432/hidrocontrol_test'
php tests/integracao.php
```

No XAMPP, se necessário: `C:/xampp/php/php.exe -d extension=pdo_pgsql tests/integracao.php`. O teste `tests/banco.php` também exige `TEST_DATABASE_URL`; serve para um banco local já migrado e verifica os dados da usina e a persistência de sessão. Não aponte testes a um túnel para banco remoto.

A suíte cobre login válido/inválido, limites por conta/IP e concorrência, acesso anônimo, RBAC, CSRF, XSS, SQL injection, entradas inválidas, expiração, logout, redefinição/uso único/expiração/concorrência de tokens, revogação, proteção do último administrador, alteração indevida de perfil, isolamento da API Supabase e atomicidade entre leitura e auditoria. Duas instâncias PHP compartilham as sessões durante os testes.

Para inspecionar a interface com os dados descartáveis da suíte, use `php tests/integracao.php --preview`; o processo informa uma URL local e aguarda Enter antes de limpar o ambiente.

Sintaxe em PowerShell:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Organização

- `index.php`, `historico.php`, `cadastrar.php`: monitoramento, gráficos e leituras existentes.
- `editar_leitura.php`, `excluir_leitura.php`: edição para operador/admin e exclusão para admin.
- `login.php`, `logout.php`, `recuperar_senha.php`, `redefinir_senha.php`: autenticação.
- `perfil.php`, `usuarios.php`, `auditoria.php`: conta e administração.
- `includes/auth.php`, `includes/seguranca.php`: guardas, sessão, CSRF e validação.
- `CLASSES/Autenticacao.php`: serviços transacionais, limites e auditoria.
- `CLASSES/SessaoBanco.php`, `CONFIG/conexao.php`: PDO e sessões compartilhadas reutilizados.
- `includes/leituras.php`: gravação de leitura e auditoria na mesma transação.
- `scripts/provisionar_admin.*`: bootstrap exclusivo por CLI.
- `supabase/migrations/`: migrações PostgreSQL incrementais.

As pastas `CLASSES` e `CONFIG` mantêm sua capitalização para compatibilidade com Linux.

## Regras simuladas preservadas

Reservatório abaixo de 30% ou acima de 90%: atenção. Temperatura entre 70 °C e 85 °C: atenção; acima de 85 °C: crítico. Turbina desligada: atenção. O estado crítico prevalece. Vazão e potência não podem ser negativas; números aceitam até duas casas decimais. Datas são exibidas e filtradas em Brasília. Os limites são didáticos e não orientam equipamentos reais.

## Publicação

Publicado em [controlhydro.vercel.app](https://controlhydro.vercel.app). O `vercel.json` foi preservado: a regra existente já encaminha novas páginas ao roteador permitido. Em 12/10/2026, a migração foi aplicada preservando os dados, o administrador foi provisionado e a conexão restrita foi configurada. A recuperação por e-mail depende da configuração de Resend/domínio verificado. Consulte [DEPLOY_VERCEL.md](DEPLOY_VERCEL.md) para o registro operacional, as validações e as instruções de publicação.
