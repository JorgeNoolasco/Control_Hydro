# Publicar o HidroControl na Vercel

O projeto continua em PHP + MySQL. O arquivo `vercel.json` usa o runtime comunitário [vercel-php 0.9.0](https://github.com/vercel-community/php), com PHP 8.5 e Node.js 22. A Vercel [documenta runtimes comunitários](https://vercel.com/docs/functions/runtimes); este não é um runtime PHP oficial.

## 1. Preparar um MySQL hospedado

O MySQL do Laragon em localhost não é acessível pela Vercel. Você precisa de um banco MySQL 8.0.16 ou superior, com endereço acessível pela internet e conexão TLS.

No painel ou cliente SQL do seu provedor:

1. Selecione o banco que o provedor criou para você.
2. Execute **database/schema.sql**. Ele cria as tabelas sensores, leituras e sessoes, sem tentar criar ou trocar o banco.
3. Se as tabelas sensores e leituras já existem, basta executar **database/sessoes.sql**.
4. Guarde host, porta, nome do banco, usuário e senha para cadastrar diretamente na Vercel.

Importar a estrutura não copia as leituras do Laragon. Se quiser preservar essas leituras, exporte os dados da tabela leituras e importe no banco hospedado.

O usuário utilizado pelo aplicativo precisa de SELECT, INSERT, UPDATE e DELETE nas tabelas do projeto. Importe a estrutura com um usuário autorizado a criar tabelas.

## 2. Importar o projeto

1. Envie esta pasta para um repositório no GitHub.
2. Na Vercel, use **Add New → Project** e importe o repositório.
3. Selecione **Framework Preset: Other** e a pasta raiz do projeto.
4. Use **Node.js 22.x**. O package.json já declara essa versão para o runtime.
5. Mantenha **Build Command sem comando** e **Output Directory no padrão**, sem definir public, dist ou build.
6. Cadastre as variáveis abaixo antes de clicar em Deploy.

Não precisa instalar Laravel, Composer ou dependências JavaScript. O package.json apenas informa a versão do Node usada pelo runtime PHP.

## 3. Variáveis de ambiente

Em **Settings → Environment Variables**, configure:

| Nome | Valor |
| --- | --- |
| DB_HOST | Host externo fornecido pelo banco, sem http:// |
| DB_PORT | Porta fornecida pelo banco, geralmente 3306 |
| DB_NAME | Nome exato do banco hospedado |
| DB_USER | Usuário do banco |
| DB_PASSWORD | Senha do banco, cadastrada como segredo |
| DB_SSL | true |
| DB_SSL_CA | Opcional: caminho para o certificado CA público do provedor |

Se o banco exigir sua própria CA, salve o certificado público em **CONFIG/certs/ca.pem** e configure **DB_SSL_CA=/var/task/user/CONFIG/certs/ca.pem**. Nunca inclua chaves privadas. Sem essa variável, o código procura os certificados do sistema. A conexão valida o certificado e exige TLS quando DB_SSL=true.

Configure as variáveis para **Production**. Para testar em **Preview**, use preferencialmente outro banco. Depois de alterar variáveis, faça um novo deploy.

O arquivo .env.example serve como referência; PHP puro não lê arquivos .env automaticamente. Credenciais locais em CONFIG/local.php e arquivos .env são excluídos do upload.

## 4. Conferir o site

Depois do deploy:

- Abra a página inicial.
- Cadastre uma leitura e confira a mensagem de sucesso.
- Abra o histórico e aplique filtros.
- Atualize a página: a leitura não deve duplicar.
- Endereços como /CONFIG/conexao.php, /database/schema.sql e /tests/usina.php devem retornar 404.

As sessões ficam no MySQL para o formulário funcionar entre diferentes instâncias da Vercel. O código não depende de arquivos temporários para guardar leituras ou sessões.

O sistema continua sendo uma demonstração sem login: pessoas com acesso ao site podem cadastrar leituras.

## Testar antes do deploy

No terminal do Laragon:

```text
php tests/usina.php
php tests/deploy.php
php -S localhost:8000 api/index.php
```

Para simular as sessões compartilhadas localmente, importe database/sessoes.sql e, no PowerShell, inicie o servidor assim:

```powershell
$env:SESSION_DRIVER = 'database'
php -S localhost:8000 api/index.php
```

Abra http://localhost:8000. O runtime comunitário recomenda o servidor PHP para testes locais; vercel dev não é suportado por ele.

## Se algo falhar

- **503 / banco indisponível:** confira variáveis, acesso de rede e a tabela sessoes.
- **Falha TLS:** configure a CA fornecida pelo provedor e confira se o host corresponde ao certificado.
- **Página PHP não executa:** confira Framework Other, Node 22.x e o vercel.json.
- **Mudanças nas variáveis não aparecem:** gere um novo deploy.

Esta preparação não publica o site nem cria um serviço de banco externo. A validação real na nuvem depende do seu banco e do primeiro deploy.
