-- O gatilho interno do Supabase continua funcionando como proprietário.
-- Não deve ser chamado pelas funções RPC da API pública.
DO $migration$
BEGIN
    IF to_regprocedure('public.rls_auto_enable()') IS NOT NULL THEN
        REVOKE EXECUTE ON FUNCTION public.rls_auto_enable() FROM PUBLIC, anon, authenticated;
    END IF;
END;
$migration$;
