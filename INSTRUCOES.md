# Complementos para Control_Hydro

Estes arquivos não alteram o GitHub nem o Supabase automaticamente.

1. Copie `usuarios.php` para a raiz, substituindo o existente após guardar um backup.
2. Copie `.github/workflows/php-checks.yml`, `.gitattributes` e `scripts/manutencao.sql` para os mesmos caminhos no repositório.
3. Execute os testes locais e confira que a área de usuários só está acessível ao administrador.
4. Revise as mudanças em um branch antes de mesclar no main/deploy.
5. A fila de pendentes apresenta as primeiras 100 contas desativadas do perfil visualizador. Nesse modelo, contas desativadas manualmente também são exibidas; para diferenciá-las de pedidos nunca aprovados, será necessário adicionar um estado de aprovação explícito no banco.
6. O script SQL exige uma conta de manutenção com permissão DELETE. Verifique um backup e execute de forma manual antes de agendar, por exemplo via ferramenta segura de jobs da sua infraestrutura.
7. O workflow não executa testes de integração PostgreSQL. Mantenha-os no ambiente isolado documentado no README.
8. Atualize o README: o cadastro público existe e cria usuários visualizadores desativados, sujeitos à aprovação do administrador. Aplique a migração de cadastro `20261012212734_cadastro_publico.sql` somente se ainda estiver pendente.
9. `*.log` já está no .gitignore, mas arquivos log previamente versionados precisam ser removidos do índice com `git rm --cached tests/servidor1.log tests/servidor2.log` após inspecionar seu conteúdo.
10. A recuperação por e-mail exige configurar RESEND_API_KEY e MAIL_FROM (domínio verificado) no painel da Vercel. Nada aqui cria credenciais ou um domínio.
11. MFA não está incluso neste pacote; requer alteração adicional no fluxo de autenticação, migração e testes de recuperação.
