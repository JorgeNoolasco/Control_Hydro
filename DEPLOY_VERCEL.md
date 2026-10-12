# Publicar HidroControl com autenticação

O código usa PDO PostgreSQL no servidor, sessões compartilhadas no banco e `vercel-php@0.9.0`, runtime comunitário que já constava no projeto. `vercel.json` continua com uma função, Node 22 e região `iad1`. A aplicação não usa o disco da função para manter estado. Não houve publicação remota deste módulo.

O registro de publicação de 08/10/2026 correspondia à versão anterior, sem login. Ele não constitui validação da autenticação nova.

## 1. Migrações sem apagar leituras

Faça backup e valide primeiro em um banco de homologação separado. Use conexão administrativa para migrar; o usuário do aplicativo não tem DDL.

Em um banco que já recebeu as duas migrações existentes, aplique **somente**:

```text
supabase/migrations/20261012202249_autenticacao_seguranca.sql
```

Ela acrescenta `usuarios`, `tentativas_login`, `logs_auditoria`, `tokens_redefinicao_senha`, constraints, índices e o papel restrito `hidrocontrol_app`. Não altera nem apaga leituras ou sensores; preserva a tabela de sessões e reforça a revogação de acesso público.

Em banco novo, aplique os três arquivos de `supabase/migrations/` em ordem. Não execute `database/*.sql`, que são scripts MySQL antigos. Em PostgreSQL local sem Supabase, crie previamente `anon` e `authenticated` como papéis `NOLOGIN`.

Com a CLI Supabase instalada, confira a ajuda da versão instalada e o projeto vinculado:

```text
supabase migration list
supabase db push --dry-run --skip-vault
```

Depois de revisar o plano e o destino, execute `supabase db push --skip-vault`. Não use `db reset` em bancos existentes. O dry-run deve mostrar somente as migrações realmente pendentes. Não passe senhas na linha de comando; use o mecanismo de autenticação/segredos da CLI.

Alternativa com `psql`, solicitando a senha interativamente e executando a migração em uma transação:

```text
psql -h HOST_ADMIN -p 5432 -U USUARIO_ADMIN -d postgres -W -v ON_ERROR_STOP=1 --single-transaction -f supabase/migrations/20261012202249_autenticacao_seguranca.sql
```

Se aplicar manualmente, reconcilie o histórico de migrações na CLI antes de executar `db push` no futuro. Não reaplique as migrações iniciais em tabelas existentes.

## 2. Credencial restrita do backend

A migração cria `hidrocontrol_app` como `NOLOGIN`, sem senha, sem `BYPASSRLS`, `CREATEROLE` ou DDL. Em sessão administrativa `psql`, habilite login e escolha uma senha forte no prompt oculto:

```text
ALTER ROLE hidrocontrol_app LOGIN;
\password hidrocontrol_app
```

O papel pode consultar/gravar as tabelas necessárias, mas não atualizar/excluir auditoria, excluir usuários ou alterar o esquema. As políticas RLS permitem acesso exclusivamente ao papel do servidor. `anon` e `authenticated` não recebem acesso às tabelas nem sequências da aplicação. A autorização dos perfis humanos ocorre no PHP: essas contas não são contas do Supabase Auth.

Configure `DATABASE_URL` com esse papel. Na conexão compartilhada do Supabase, use o formato de usuário para um papel customizado (`hidrocontrol_app.PROJECT_REF`) e o host indicado em **Connect**; verifique a conexão em homologação. Não use a senha do proprietário em produção nem exponha chaves `service_role`/secret ao navegador.

Se a aplicação for o único consumidor do banco por PDO, considere desabilitar a Data API no projeto. Se houver outros consumidores, mantenha-a e confira seus grants/policies separadamente. Não exponha tabelas de autenticação por REST, GraphQL ou RPC.

## 3. Variáveis na Vercel

Em Settings → Environment Variables, configure separadamente em Production e Preview:

- `DATABASE_URL`: URI do Transaction pooler, porta 6543, com papel restrito. Tem prioridade sobre `POSTGRES_URL` da integração.
- `DB_SSLMODE=require`, ou `verify-full` com `PGSSLROOTCERT` apontando para a CA pública incluída no deployment.
- `SESSION_DRIVER=database`.
- `APP_SECRET`: gere 32 bytes aleatórios, representados por 64 caracteres hexadecimais; mantenha estável entre instâncias de um mesmo ambiente.
- `APP_URL`: origem HTTPS canônica, sem subpasta. Em Preview, use uma origem própria desse ambiente.
- `RESEND_API_KEY`: chave com permissão apenas de envio, de preferência restrita ao domínio remetente.
- `MAIL_FROM`: endereço de um domínio verificado no Resend.
- Opcional: `SESSION_IDLE_TIMEOUT=1800`, `SESSION_ABSOLUTE_TIMEOUT=28800`.

Gere o segredo em ambiente confiável (`php -r 'echo bin2hex(random_bytes(32));'`) e copie-o diretamente para o gerenciador de segredos. Não o versione. Não compartilhe banco, segredo ou credenciais de produção com Preview.

`APP_ENV=production` é necessário ao hospedar em outro servidor de produção; na Vercel `VERCEL=1` já ativa HTTPS obrigatório, HSTS e cookies Secure. Os cabeçalhos de proxy só são aceitos na Vercel. Fora dela, configure o servidor HTTPS para informar `HTTPS=on` ao PHP.

O Supabase recomenda transaction pooling para serverless. O PDO já desativa prepared statements persistentes com `PDO::PGSQL_ATTR_DISABLE_PREPARES`; os parâmetros continuam separados do SQL. As transações da sessão e os locks de tentativas funcionam no escopo transacional, sem depender de estado da conexão após commit.

## 4. Primeiro administrador

Em máquina administrativa confiável, configure temporariamente a conexão com o papel do backend e `APP_SECRET`. Execute:

```powershell
./scripts/provisionar_admin.ps1 -Php php
```

Nome e e-mail são solicitados no terminal; senha é lida de forma oculta e transmitida por stdin. O provisionamento trava concorrentemente a administração, exige ausência de usuários e registra `admin_provisionado`. Remova as variáveis temporárias da máquina ao terminar. Não há senha padrão, seed administrativo, parâmetro de URL ou endpoint de bootstrap.

## 5. Publicar e validar

1. Execute os testes locais do README.
2. Faça commit/push da implementação após a revisão. No projeto Vercel conectado ao repositório, mantenha Framework **Other**, Output Directory padrão e os comandos vazios previstos em `vercel.json`.
3. Crie um deployment de homologação com banco migrado e variáveis próprias. Alterar variáveis exige novo deployment.
4. Confira login/logout e recuperação com uma conta de homologação; este último passo envia um e-mail real pelo Resend e deve ser realizado pelo responsável pelo ambiente.
5. Teste os três perfis, escrita direta sem CSRF, acesso anônimo, troca de perfil, desativação, expiração, auditoria e repetição de POST. Confira também gráficos, cadastro e histórico.
6. Confira `Set-Cookie` com `Secure`, `HttpOnly`, `SameSite=Lax`, `Cache-Control: no-store`, CSP e HSTS no HTTPS real.
7. Após essa validação, publique em Production com o banco migrado, admin provisionado e variáveis de produção.

O roteador público tem uma lista explícita de páginas e métodos. Apenas os dois assets são servidos diretamente. Configuração, scripts CLI, migrações e testes retornam 404; `.vercelignore` também os exclui do upload CLI. Em Apache/Nginx, reproduza essa lista de rotas: não sirva a raiz do repositório indiscriminadamente.

Não faça rollback para a versão anterior sem autenticação: isso reabriria as leituras. Para um incidente, mantenha uma versão autenticada conhecida ou uma página de manutenção; as tabelas novas podem permanecer, sem rollback destrutivo.

## Diagnóstico e limitações de validação

- **503:** confira extensões, `APP_SECRET`, conexão, TLS, migrações e privilégios do papel do backend. Erros públicos não mostram SQL ou credenciais.
- **429:** aguarde a janela de 15 minutos. O contador persiste no banco; trocar de instância não o remove.
- **403 em formulário antigo:** atualize a página. O CSRF é renovado após escritas bem-sucedidas.
- **Sem e-mail:** verifique `email_falha` na auditoria, domínio/remetente e chave Resend. Não registre o corpo da chamada, pois contém token de uso único.
- **Login pede autenticação novamente:** confira expiração/versão da conta e se todas as instâncias usam o mesmo banco.
- **Build PHP:** confirme runtime comunitário fixado e as extensões `pdo_pgsql`, `curl`, `openssl`. Compatibilidade serverless foi exercitada com duas instâncias PHP locais; build remoto, entrega real de e-mail e HTTPS da Vercel precisam da validação do deployment.

Referências: [runtimes Vercel](https://vercel.com/docs/functions/runtimes), [runtime PHP comunitário](https://github.com/vercel-community/php), [pooling Supabase](https://supabase.com/docs/guides/database/connecting-to-postgres), [proteção da Data API](https://supabase.com/docs/guides/api/securing-your-api), [API de envio Resend](https://resend.com/docs/api-reference/emails/send-email).
