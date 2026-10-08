# HidroControl na Vercel com Supabase PostgreSQL

O aplicativo usa PHP/PDO no servidor, PostgreSQL do Supabase para leituras e sessões e o runtime comunitário [vercel-php 0.9.0](https://github.com/vercel-community/php) (PHP 8.5, Node 22). Não precisa de Composer nem de framework JavaScript.

## Projetos

- Vercel: `control_hydro`, repositório `JorgeNoolasco/Control_Hydro`.
- Supabase: `Hidro-Control` (`ythabdyplpkduqaezkxe`, região `us-east-1`).
- As funções usam `iad1`, próxima ao banco.

## Publicação verificada em 08/10/2026

- Produção: https://controlhydro.vercel.app
- Deployment: `dpl_CeGx5pumMwKUypRvZmgofS9d6CNF` (READY).
- Integração Supabase conectada; `POSTGRES_URL` disponível em Production e Preview.
- Validado pelo domínio de produção: cadastro, gravação no Supabase, painel, filtros de status/data, sessão, CSRF, proteção contra envio simultâneo duplicado e rotas privadas retornando 404.
- A leitura de teste foi removida. As migrações locais correspondem ao histórico remoto.
- A publicação foi feita a partir dos arquivos locais pela API da Vercel. As alterações no repositório ainda precisam de commit/push para que os próximos deploys pelo GitHub usem esta versão.

## Banco

As migrações em `supabase/migrations/` criam `sensores`, `leituras` e `sessoes`. No projeto acima a estrutura já foi aplicada. Não execute novamente o SQL inicial no mesmo banco.

Em um Supabase novo, aplique as migrações em ordem pelo SQL Editor, ou use a CLI Supabase para vincular o projeto e executar `supabase db push`. **Os arquivos em `database/` são exclusivos de MySQL local**, não de PostgreSQL. Nenhuma leitura local é transferida automaticamente.

As tabelas têm RLS e acesso revogado para `anon`/`authenticated`. O PHP usa a conexão PostgreSQL do servidor; não há acesso do navegador à API de dados, e nenhuma senha é enviada ao JavaScript. Por isso não existem políticas públicas nas tabelas. Os quatro sensores são conceituais, e cada leitura contém os dados da usina inteira.

## Conectar Supabase e Vercel

1. Na [área Storage do projeto](https://vercel.com/blue-math/control_hydro/stores), conecte o banco existente **Hidro-Control** a **control_hydro**.
2. Confira em Settings → Environment Variables se a integração criou `POSTGRES_URL` para Production.
3. A conexão deve ser a URI do **Transaction pooler**, porta **6543**, disponível no botão **Connect** do Supabase. Copie host e usuário do painel, sem tentar deduzir o endereço pela região.
4. Se a integração não fornecer uma URI adequada, cadastre `DATABASE_URL` diretamente na Vercel com a URI do pooler. Ela tem prioridade sobre `POSTGRES_URL`. Caracteres especiais na senha devem estar codificados como URL.
5. Gere um novo deploy após conectar o banco ou alterar variáveis.

Não use `SUPABASE_URL`, chave `anon`, publishable ou service role como conexão PostgreSQL. A aplicação precisa da URI de banco com usuário e senha. Não coloque credenciais em arquivos versionados.

| Variável | Uso |
| --- | --- |
| `DATABASE_URL` ou `POSTGRES_URL` | URI PostgreSQL do pooler, obrigatória na nuvem |
| `DB_SSLMODE` | Padrão `require`; TLS obrigatório na Vercel |
| `SESSION_DRIVER` | `database` para testar sessões compartilhadas localmente; automático na Vercel |

Como alternativa à URI, configure `DB_DRIVER=pgsql`, `DB_HOST`, `DB_PORT=6543`, `DB_NAME=postgres`, `DB_USER` e `DB_PASSWORD`.

`sslmode=require` exige criptografia. Para verificar também a identidade do servidor, use `DB_SSLMODE=verify-full` e configure `PGSSLROOTCERT` com o caminho do certificado CA público disponibilizado pelo Supabase. As opções são descritas na [documentação de conexão do Supabase](https://supabase.com/docs/guides/database/connecting-to-postgres). Prepared statements persistentes estão desativados para compatibilidade com o pooler.

Use um banco separado em Preview/Development se precisar de ambientes isolados. Sem variáveis de banco, o site responde 503 com uma mensagem genérica.

## Configuração da Vercel

`vercel.json` declara Framework Other, nenhum comando de build/instalação e Node 22 em `package.json`. Deixe Output Directory no padrão. Somente `/`, `/index.php`, `/cadastrar.php`, `/historico.php` e os dois arquivos de assets são públicos.

`.vercelignore` exclui credenciais locais, testes, SQL e documentação do upload via CLI. `.gitignore` protege `CONFIG/local.php`, arquivos `.env` e `.vercel/`. O PHP puro não carrega `.env` automaticamente.

## Validação

```text
php tests/usina.php
php tests/deploy.php
```

Para testar a conexão real localmente, copie `CONFIG/local.exemplo.php` para `CONFIG/local.php`, preencha `url` e habilite `pdo_pgsql` e `mbstring`. Então execute:

```text
php tests/banco.php
```

Esse teste verifica gravação e consulta dentro de uma transação revertida, booleanos, filtro por data de Brasília e persistência de sessão com dados binários. A sessão de teste é removida ao final.

Para testar o formulário em PowerShell:

```powershell
$env:SESSION_DRIVER = 'database'
php -S localhost:8000 api/index.php
```

Abra http://localhost:8000, cadastre uma leitura, confira a mensagem de sucesso, o painel e o histórico. Atualizar após salvar não deve duplicar a leitura. O token CSRF muda depois de cada cadastro. As datas são armazenadas com fuso e exibidas/filtradas em Brasília.

Endereços como `/CONFIG/conexao.php`, `/database/schema.sql`, `/supabase/migrations/` e `/tests/banco.php` devem retornar 404. O sistema é uma demonstração sem login: pessoas com acesso ao site podem cadastrar leituras.

## Diagnóstico

- **503:** confira a URI, a senha, a conexão do banco ao projeto e a existência de `sessoes`.
- **Tenant or user not found:** copie exatamente host e usuário do pooler no Supabase.
- **Erro de prepared statement:** use este código atualizado; ele desativa statements persistentes no PDO PostgreSQL.
- **Build PHP falhou:** confira Node 22 e runtime `vercel-php@0.9.0`.
- **Variáveis não aparecem:** faça novo deploy. As variáveis são capturadas na criação do deployment.
