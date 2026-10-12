-- EXECUTAR somente por credencial administrativa de manutenção.
-- NUNCA executar com o papel hidrocontrol_app da aplicação.
-- Antes de executar, revise os prazos com a política de retenção do projeto.
BEGIN;
DELETE FROM public.tentativas_login
 WHERE criado_em < clock_timestamp() - interval '30 days';
DELETE FROM public.tokens_redefinicao_senha
 WHERE (usado_em IS NOT NULL AND usado_em < clock_timestamp() - interval '7 days')
    OR expira_em < clock_timestamp() - interval '7 days';
-- As sessões antigas também são removidas pela aplicação; aqui somente limpe
-- sessões se o formato e a coluna de expiração forem confirmados no schema.
COMMIT;
-- Os logs de auditoria NÃO são apagados neste script.
