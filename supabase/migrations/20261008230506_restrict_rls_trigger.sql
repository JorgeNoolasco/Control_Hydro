-- O gatilho interno do Supabase continua funcionando como proprietário.
-- Não deve ser chamado pelas funções RPC da API pública.
DO $migration$
-- Bloco anônimo PL/pgSQL: executa a proteção uma vez, sem criar uma função adicional.
BEGIN
    -- O gatilho depende da instalação; a verificação permite usar a migração onde ele não existe.
    IF to_regprocedure('public.rls_auto_enable()') IS NOT NULL THEN
        -- PUBLIC abrange todos os papéis; anon/authenticated são os papéis usuais da API.
        REVOKE EXECUTE ON FUNCTION public.rls_auto_enable() FROM PUBLIC, anon, authenticated;
    END IF;
END;
$migration$;
