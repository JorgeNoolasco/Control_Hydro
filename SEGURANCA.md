# Segurança do HidroControl

## Fluxo de confiança

O navegador recebe apenas o identificador opaco de sessão. O PHP lê a sessão no PostgreSQL, consulta o usuário e confere `ativo`, perfil e `versao_sessao`. Valores enviados em formulários nunca definem a identidade nem o perfil autenticado. As operações de escrita revalidam a autorização no serviço, dentro da transação, usando o usuário persistido e sua versão.

O roteador `api/index.php` permite somente páginas e métodos conhecidos. As páginas também executam as guardas ao serem chamadas diretamente. Páginas privadas redirecionam GET/HEAD anônimos para login (303); escritas sem autenticação retornam 401, falta de permissão/CSRF retorna 403, método inválido retorna 405, campos inválidos retornam 422 e limitação de login retorna 429 com `Retry-After`. Recursos ausentes retornam 404 e falhas internas retornam 503 genérico.

## Sessões e transações

`SessaoBanco` foi reutilizado. Seu `read()` mantém um lock transacional por sessão até o fechamento; as operações compartilham a conexão `conectar()`. `transacao()` cria savepoints quando essa transação já existe: qualquer erro desfaz a operação inteira, inclusive leitura/auditoria, sem deixar gravações parciais para o fechamento da sessão confirmar.

O login renova o ID com `session_regenerate_id(true)` e cria um novo CSRF. Cookies são host-only, HttpOnly, SameSite=Lax, path `/`, sem persistência após fechar o navegador e Secure em produção/HTTPS. `use_strict_mode`, `use_only_cookies` e `use_trans_sid=false` evitam IDs arbitrários ou em URLs. O backend aplica 30 minutos de inatividade e 8 horas de duração absoluta por padrão, independentemente da coleta de sessões expiradas. Logout usa POST e apaga a sessão persistida e o cookie.

Nome e perfil são consultados no banco. Redefinição de senha, mudança de perfil ou estado incrementam `versao_sessao`; sessões anteriores são recusadas na próxima requisição. Uma operação já autorizada e em andamento pode terminar antes de a mudança de acesso ser confirmada no banco; os locks de usuário serializam essa fronteira.

## Credenciais e tentativas

Senhas têm 12 a 72 bytes, não são truncadas ou normalizadas e usam bcrypt custo 12. Não há senha padrão. O limite superior evita truncamento do bcrypt; o inferior permite frases longas. Contas inexistentes também executam `password_verify()` com um hash fictício para aproximar o custo de autenticação. Senhas antigas são rehashadas se o custo estiver desatualizado.

Em uma janela móvel de 15 minutos, o login/reauth bloqueia após 5 falhas por e-mail ou 30 por IP. Recuperação admite 3 pedidos por e-mail ou 30 por IP nessa mesma janela. Os contadores ficam no PostgreSQL, e advisory locks **transacionais** serializam contagem/gravação entre processos. Login e confirmação de senha têm contadores separados. O bloqueio é temporário, inclusive para credenciais corretas, e não é reiniciado por troca de instância ou cookie. IP e e-mail nos contadores são HMACs com `APP_SECRET`; o IP nunca é armazenado em texto claro pela aplicação. Na Vercel, o proxy confiável fornece o IP; em outros ambientes cabeçalhos de IP arbitrários são ignorados.

O segredo deve ser estável, aleatório e diferente por ambiente. Rotacioná-lo altera os identificadores dos contadores; mantenha monitoramento e proteção de borda durante a transição. A limitação da aplicação não substitui proteção contra saturação de conexões/volumetria na plataforma.

## Recuperação de senha

`recuperar_senha.php` retorna a mesma mensagem para conta existente, desconhecida, desativada, bloqueada ou falha do remetente. O transporte Resend é HTTPS, com validação TLS padrão, destino fixo, sem redirects e timeout de 10 segundos. Não usa chaves no cliente. Os testes injetam um callback local e removem a configuração do provedor dos subprocessos.

Tokens são 32 bytes aleatórios; apenas SHA-256 vai para `tokens_redefinicao_senha`, com prazo de 30 minutos. O link leva o token no **fragmento** (`#token=`), evitando query strings em access logs e Referer. JavaScript local coloca o token no campo oculto e remove o fragmento do histórico. GET não consome tokens; scanners de e-mail não redefinem senhas. POST exige CSRF e confirmação da senha. O consumo usa locks e invalida todos os tokens pendentes do usuário; duas requisições simultâneas não podem reutilizá-lo. Não há login automático após redefinir.

Falha no envio invalida o token emitido e registra `email_falha`, sem resposta do provedor ou corpo do e-mail nos logs. O envio real depende de domínio/remetente verificados e chave Resend configurada. A integração é síncrona, portanto a latência do transporte pode variar entre pedidos; a resposta pública nunca informa se o endereço existe.

## Banco e auditoria

A migração é aditiva e mantém leituras existentes. E-mails são únicos e normalizados, perfis têm CHECK, usuários usam UUID e as referências possuem foreign keys. Índices cobrem login, expiração e filtros da auditoria.

Use `hidrocontrol_app` no runtime. Ele não é proprietário, não tem BYPASSRLS nem DDL. As tabelas têm RLS e grants revogados de PUBLIC, anon e authenticated. As policies do backend identificam o serviço, enquanto o PHP aplica os perfis das pessoas. Usuários internos não são usuários do Supabase Auth; a API pública não participa da autenticação.

`logs_auditoria` registra login, falha/bloqueio, logout, expiração detectada, gravações de leituras, criação/alteração de usuários, permissões, bootstrap e recuperação. A conta do runtime pode inserir e consultar logs, mas não atualizá-los ou excluí-los. Modificações e seus eventos confirmam juntos. A auditoria é acessível só a administradores, com filtros parametrizados por data de Brasília, usuário e ação, em páginas de 50 eventos.

Não se registram senhas, tokens, IDs de sessão, URLs de recuperação, conteúdo de POST ou e-mails nos eventos. Metadados aceitam somente perfil anterior/novo e estado da conta. Nomes aparecem na consulta administrativa por JOIN, sem duplicar dados pessoais nos logs. Administradores do PostgreSQL ainda podem alterar registros: para requisitos de imutabilidade contra administradores do banco, exporte para armazenamento externo com retenção controlada.

## XSS, CSRF e erros

Saída dinâmica usa `e()` e JSON dos gráficos usa flags `JSON_HEX_*`. Scripts e estilos são locais; a altura do reservatório passa por valor numérico no JavaScript. A CSP não permite `unsafe-inline`/`unsafe-eval`, frames, objetos ou base URL alterada. Formulários só enviam para a própria origem. Há `nosniff`, `DENY`, `no-referrer`, Permissions Policy e `no-store`; produção acrescenta HSTS e HTTPS obrigatório.

Todas as escritas usam POST, CSRF de sessão e backend parametrizado. A confirmação de senha do administrador também é limitada por tentativas. Alterar o próprio perfil via parâmetros extras é rejeitado. O bootstrap só funciona por CLI e somente com a tabela de usuários vazia, protegido por lock concorrente.

Erros internos não são exibidos. Buffer de resposta e fechamento explícito da sessão evitam mostrar sucesso antes do commit. `api/php.ini` desativa exibição de erros, exposição de versão e upload de arquivos, e limita formulários a 64 KiB/100 campos. Não habilite captura de corpos HTTP, cookies ou cabeçalhos Authorization no monitoramento da hospedagem.

## Manutenção

A coleta da sessão remove registros expirados durante o uso. Agende, com uma credencial de manutenção separada do runtime, a limpeza de `tentativas_login` mais antigas que o prazo de retenção escolhido (nunca menos de 15 minutos), tokens vencidos/usados e sessões expiradas. Por exemplo, conservar tentativas por 30 dias e tokens por 7 dias costuma ser suficiente para diagnóstico; escolha os prazos conforme a política do projeto. Auditoria precisa de política própria de retenção e backup; não é apagada automaticamente nem por uma rota web.

Mantenha PHP/runtime e PostgreSQL atualizados, revise eventos administrativos e falhas de e-mail, proteja as credenciais da Vercel/Supabase/Resend e execute os testes em um cluster local descartável. Use banco separado para Preview. O módulo não implementa MFA e não substitui uma revisão independente de segurança antes de uso com dados críticos.

## Verificação realizada

Em 12/10/2026, os testes foram executados com PHP 8.0.30 e 8.5.11 e PostgreSQL 17.11 local isolado, incluindo duas instâncias HTTP e processos concorrentes. Passaram 111 verificações de integração, 35 de segurança e 20 de domínio, além dos testes de rotas/configuração e da integração anterior de banco/sessões. A sintaxe PHP também foi validada. A suíte verifica privilégios reais do papel do runtime e da API, não apenas mocks.

O build/deploy remoto foi concluído, com login 200, redirecionamentos de acesso anônimo, bloqueio de configuração privada, cookies e cabeçalhos conferidos no HTTPS real. O envio pelo Resend não foi executado e continua sem configuração, pois o responsável ainda não possui o serviço/domínio verificado. A revisão visual automatizada não pôde ser concluída porque nenhum navegador estava conectado; a estrutura das páginas e os fluxos HTTP foram exercitados pela suíte local. Confirme a apresentação em desktop/celular.

Na continuação de 12/10/2026, a migração foi aplicada ao Supabase de produção, com autorização expressa. Contagens/checksums confirmaram a preservação de leituras e sensores; RLS e revogação dos privilégios públicos foram verificados, e o verificador de segurança não retornou avisos. O administrador foi provisionado com senha aleatória descartada e link privado para definir sua própria senha; a credencial restrita do runtime está em uso. Não foram executados testes automatizados de escrita ou login contra produção. Consulte o registro em `DEPLOY_VERCEL.md`.
