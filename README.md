# HidroControl

Sistema educacional simples para registrar manualmente leituras de uma usina e acompanhar alertas. PHP 8+, PDO, HTML, CSS e JavaScript puro. Na Vercel usa PostgreSQL do Supabase; também mantém suporte a MySQL 8.0.16+ local. Sem frameworks.

## Executar no Laragon

1. Coloque o projeto em `C:\laragon\www\hidrocontrol`.
2. Inicie Apache e MySQL no Laragon.
3. Abra o HeidiSQL (menu Banco de dados do Laragon), conecte ao MySQL e execute o arquivo `database/hidrocontrol.sql`. Ele cria o banco, as tabelas e quatro sensores conceituais. Nenhuma leitura de exemplo é adicionada.
4. O padrão de conexão é localhost, porta 3306, banco hidrocontrol, usuário root e senha vazia, para o ambiente local do Laragon.
5. Se necessário, copie `CONFIG/local.exemplo.php` para `CONFIG/local.php` e ajuste a conexão. Esse arquivo está no .gitignore; não versione senhas reais.
6. Acesse **http://localhost/hidrocontrol/** e clique em **Registrar nova leitura**.

O PHP precisa da extensão pdo_mysql e mbstring (disponíveis no Laragon). As datas são exibidas no horário de Brasília.

## Arquivos

- `index.php`: painel, alertas e gráficos das últimas 20 leituras.
- `cadastrar.php`: formulário, validação e gravação via POST.
- `historico.php`: filtros por situação/data e paginação de 15 registros.
- `CLASSES/usina.php`: classe com atributos privados e regras de negócio.
- `CONFIG/conexao.php`: conexão PDO MySQL/PostgreSQL por variáveis de ambiente.
- `CLASSES/SessaoBanco.php`: sessões compartilhadas no banco.
- `supabase/migrations/`: estrutura PostgreSQL, índices e proteção da API pública.
- `includes/`: funções compartilhadas, cabeçalho e rodapé.
- `assets/css/style.css` e `assets/js/script.js`: visual responsivo e gráficos SVG.
- `database/hidrocontrol.sql`: estrutura MySQL e sensores conceituais.
- `tests/usina.php`: testes das regras e validações.

As pastas CLASSES e CONFIG mantêm os nomes usados nos includes, inclusive em servidores Linux.

## Como ler o código comentado

Comece por `api/index.php`, que seleciona a página permitida. As páginas carregam `includes/funcoes.php`, responsável pelos utilitários e pela inicialização de conexão e sessão. `cadastrar.php` valida os dados com `CLASSES/usina.php`, grava a leitura e redireciona ao painel. `index.php` apresenta os indicadores e entrega o JSON dos gráficos para `assets/js/script.js`; `historico.php` consulta os registros com filtros e paginação. `assets/css/style.css` organiza o visual e a adaptação às telas menores.

Os arquivos PHP, HTML, JavaScript, CSS, SQL, INI, modelos de ambiente e regras de exclusão têm comentários em português. Os comentários nos testes explicam o que cada cenário verifica. Comentários nas migrações servem para leitura do código; não exigem executar novamente migrações já aplicadas.

### Configuração JSON

JSON não admite comentários. As opções dos dois arquivos são explicadas aqui para preservar o formato aceito pelas ferramentas.

| Arquivo / opção | Significado |
| --- | --- |
| `package.json` → `name` | Identifica o projeto como `hidrocontrol`. |
| `package.json` → `private` | Impede publicação acidental como pacote npm. |
| `package.json` → `engines.node` | Seleciona Node 22 para o runtime usado no deploy. |
| `vercel.json` → `$schema` | Referência para validação e sugestões do editor. |
| `vercel.json` → `version` | Versão do formato de configuração da Vercel. |
| `vercel.json` → `framework` | `null` corresponde ao projeto sem framework detectado. |
| `vercel.json` → `buildCommand` | Vazio: não executa um comando de build personalizado. |
| `vercel.json` → `installCommand` | Vazio: o aplicativo não precisa instalar dependências npm. |
| `vercel.json` → `regions` | `iad1` define a região das funções, próxima ao banco configurado. |
| `vercel.json` → `functions` | Declara a função PHP cuja entrada é `api/index.php`. |
| `vercel.json` → `runtime` | Fixa o runtime comunitário `vercel-php@0.9.0`. |
| `vercel.json` → `routes` | Regras de roteamento avaliadas na ordem declarada. |
| `routes` → `src` | Expressão que identifica o caminho solicitado. A primeira permite os dois assets; a segunda captura os demais caminhos. |
| `routes` → `dest` | Destino interno: `$1` reutiliza o grupo capturado do asset; os demais pedidos passam pelo roteador PHP. |

## Regras simuladas

- Reservatório abaixo de 30% ou acima de 90%: atenção. De 30% a 90%, inclusive: normal.
- Temperatura abaixo de 70 °C: normal; de 70 °C a 85 °C: atenção; acima de 85 °C: crítico.
- Turbina desligada: atenção.
- O estado crítico tem prioridade sobre os demais alertas.
- Vazão e potência não possuem limites de alerta; devem ser não negativas.
- Números aceitam até duas casas decimais, respeitando a capacidade dos campos MySQL.

Os limites são didáticos e não representam parâmetros de segurança reais. A tabela sensores é conceitual; cada linha de leituras guarda um registro completo da usina.

## Validação e segurança

Prepared statements, saída escapada, token CSRF de sessão e validação no servidor. O cadastro redireciona após salvar (Post/Redirect/Get). Erros de banco são apresentados sem credenciais. Não há login: use como exercício local.

## Testes

No terminal do Laragon, execute:

```text
php tests/usina.php
```

São verificados os cenários A (70%, 65 °C, ligada → Normal), B (25%, 78 °C, ligada → Atenção), C (95%, 92 °C, desligada → Crítico), os limites 30%, 90%, 70 °C e 85 °C e entradas inválidas. Esses testes não salvam dados.

Para verificar a sintaxe em PowerShell:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Teste manual: cadastre uma leitura, confira o painel e o histórico; filtre por data e situação. Atualizar o painel após salvar não deve cadastrar novamente. Sem registros, o painel oferece o cadastro da primeira leitura.

## Publicar na Vercel

Siga o passo a passo em [DEPLOY_VERCEL.md](DEPLOY_VERCEL.md).
O projeto inclui runtime PHP, rotas públicas, conexão PostgreSQL com TLS e sessões persistidas no Supabase.
Conecte o banco Supabase ao projeto Vercel e configure `POSTGRES_URL` ou `DATABASE_URL` com a URI do pooler.
As migrações PostgreSQL ficam em `supabase/migrations/`; `database/schema.sql` continua sendo apenas para MySQL.
Execute `php tests/deploy.php` para validar rotas/configuração e `php tests/banco.php` para verificar uma conexão PostgreSQL configurada.
