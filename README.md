# HidroControl

Sistema educacional simples para registrar manualmente leituras de uma usina e acompanhar alertas. PHP 8+, MySQL 8.0.16+, PDO, HTML, CSS e JavaScript puro. Sem frameworks.

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
- `CONFIG/conexao.php`: conexão PDO.
- `includes/`: funções compartilhadas, cabeçalho e rodapé.
- `assets/css/style.css` e `assets/js/script.js`: visual responsivo e gráficos SVG.
- `database/hidrocontrol.sql`: estrutura MySQL e sensores conceituais.
- `tests/usina.php`: testes das regras e validações.

As pastas CLASSES e CONFIG mantêm os nomes usados nos includes, inclusive em servidores Linux.

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
O projeto inclui o runtime PHP, as rotas públicas, configuração por variáveis de ambiente e sessões MySQL.
É necessário um MySQL hospedado; o banco local do Laragon não acompanha o deploy.
Para criar as tabelas no banco do provedor, use database/schema.sql.
