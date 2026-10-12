-- Amplia somente os valores permitidos; preserva contas, leituras e auditoria.
ALTER TABLE public.tentativas_login DROP CONSTRAINT tentativas_login_finalidade_check;
ALTER TABLE public.tentativas_login ADD CONSTRAINT tentativas_login_finalidade_check
    CHECK (finalidade IN ('login', 'recuperacao', 'confirmacao', 'cadastro'));

ALTER TABLE public.logs_auditoria DROP CONSTRAINT logs_auditoria_acao_check;
ALTER TABLE public.logs_auditoria ADD CONSTRAINT logs_auditoria_acao_check CHECK (acao IN (
    'login_sucesso', 'login_falha', 'login_bloqueado', 'logout', 'sessao_expirada',
    'leitura_cadastrada', 'leitura_editada', 'leitura_excluida', 'usuario_criado',
    'permissoes_alteradas', 'usuario_atualizado', 'perfil_atualizado', 'admin_provisionado',
    'recuperacao_solicitada', 'recuperacao_bloqueada', 'email_falha', 'senha_redefinida',
    'confirmacao_falha', 'cadastro_bloqueado'
));
