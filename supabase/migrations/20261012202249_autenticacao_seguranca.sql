-- Aditiva: não apaga nem modifica leituras/sensores existentes.
CREATE TABLE public.usuarios (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    nome varchar(120) NOT NULL CHECK (octet_length(nome) BETWEEN 2 AND 120),
    email varchar(254) NOT NULL UNIQUE CHECK (email = lower(email) AND email = btrim(email)),
    senha_hash varchar(255) NOT NULL CHECK (length(senha_hash) >= 60),
    perfil varchar(20) NOT NULL DEFAULT 'visualizador' CHECK (perfil IN ('administrador', 'operador', 'visualizador')),
    ativo boolean NOT NULL DEFAULT true,
    versao_sessao bigint NOT NULL DEFAULT 1 CHECK (versao_sessao > 0),
    criado_em timestamptz NOT NULL DEFAULT clock_timestamp(),
    atualizado_em timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE INDEX usuarios_administradores ON public.usuarios (perfil) WHERE ativo;

CREATE TABLE public.tentativas_login (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    identificador_hash char(64) NOT NULL CHECK (identificador_hash ~ '^[0-9a-f]{64}$'),
    ip_hash char(64) NOT NULL CHECK (ip_hash ~ '^[0-9a-f]{64}$'),
    finalidade varchar(20) NOT NULL CHECK (finalidade IN ('login', 'recuperacao', 'confirmacao')),
    sucesso boolean NOT NULL,
    criado_em timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE INDEX tentativas_conta ON public.tentativas_login (finalidade, identificador_hash, criado_em DESC) WHERE NOT sucesso;
CREATE INDEX tentativas_ip ON public.tentativas_login (finalidade, ip_hash, criado_em DESC) WHERE NOT sucesso;
CREATE INDEX tentativas_data ON public.tentativas_login (criado_em);

CREATE TABLE public.logs_auditoria (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario_id uuid REFERENCES public.usuarios(id) ON DELETE RESTRICT,
    acao varchar(50) NOT NULL CHECK (acao IN (
        'login_sucesso', 'login_falha', 'login_bloqueado', 'logout', 'sessao_expirada',
        'leitura_cadastrada', 'leitura_editada', 'leitura_excluida', 'usuario_criado',
        'permissoes_alteradas', 'usuario_atualizado', 'perfil_atualizado', 'admin_provisionado',
        'recuperacao_solicitada', 'recuperacao_bloqueada', 'email_falha', 'senha_redefinida', 'confirmacao_falha')),
    recurso_id varchar(64),
    ip_hash char(64) NOT NULL CHECK (ip_hash ~ '^[0-9a-f]{64}$'),
    detalhes jsonb NOT NULL DEFAULT '{}'::jsonb,
    criado_em timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE INDEX auditoria_data ON public.logs_auditoria (criado_em DESC, id DESC);
CREATE INDEX auditoria_usuario ON public.logs_auditoria (usuario_id, criado_em DESC);
CREATE INDEX auditoria_acao ON public.logs_auditoria (acao, criado_em DESC);

CREATE TABLE public.tokens_redefinicao_senha (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    usuario_id uuid NOT NULL REFERENCES public.usuarios(id) ON DELETE CASCADE,
    token_hash char(64) NOT NULL UNIQUE CHECK (token_hash ~ '^[0-9a-f]{64}$'),
    criado_em timestamptz NOT NULL DEFAULT clock_timestamp(),
    expira_em timestamptz NOT NULL,
    usado_em timestamptz,
    CHECK (expira_em > criado_em)
);
CREATE INDEX tokens_usuario ON public.tokens_redefinicao_senha (usuario_id);
CREATE INDEX tokens_expiracao ON public.tokens_redefinicao_senha (expira_em);

ALTER TABLE public.usuarios ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tentativas_login ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.logs_auditoria ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tokens_redefinicao_senha ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.usuarios, public.tentativas_login, public.logs_auditoria,
    public.tokens_redefinicao_senha, public.sessoes, public.leituras, public.sensores FROM PUBLIC, anon, authenticated;
REVOKE ALL ON SEQUENCE public.tentativas_login_id_seq, public.logs_auditoria_id_seq,
    public.leituras_id_seq, public.sensores_id_seq FROM PUBLIC, anon, authenticated;

-- Papel exclusivo do backend PDO, sem credencial versionada e sem acesso DDL/BYPASSRLS.
-- Após migrar, habilite LOGIN e defina a senha por um canal administrativo seguro.
DO $migration$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'hidrocontrol_app') THEN
        CREATE ROLE hidrocontrol_app NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
    END IF;
END;
$migration$;
GRANT USAGE ON SCHEMA public TO hidrocontrol_app;
GRANT SELECT ON public.sensores TO hidrocontrol_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.leituras, public.sessoes TO hidrocontrol_app;
GRANT SELECT, INSERT, UPDATE ON public.usuarios, public.tokens_redefinicao_senha TO hidrocontrol_app;
GRANT SELECT, INSERT ON public.logs_auditoria, public.tentativas_login TO hidrocontrol_app;
GRANT USAGE ON SEQUENCE public.leituras_id_seq, public.tentativas_login_id_seq, public.logs_auditoria_id_seq TO hidrocontrol_app;
-- O banco identifica o serviço; o PHP valida o perfil humano em cada operação.
CREATE POLICY backend_sensores ON public.sensores FOR SELECT TO hidrocontrol_app USING (true);
CREATE POLICY backend_leituras ON public.leituras TO hidrocontrol_app USING (true) WITH CHECK (true);
CREATE POLICY backend_sessoes ON public.sessoes TO hidrocontrol_app USING (true) WITH CHECK (true);
CREATE POLICY backend_usuarios ON public.usuarios TO hidrocontrol_app USING (true) WITH CHECK (true);
CREATE POLICY backend_tokens ON public.tokens_redefinicao_senha TO hidrocontrol_app USING (true) WITH CHECK (true);
CREATE POLICY backend_auditoria_ler ON public.logs_auditoria FOR SELECT TO hidrocontrol_app USING (true);
CREATE POLICY backend_auditoria_inserir ON public.logs_auditoria FOR INSERT TO hidrocontrol_app WITH CHECK (true);
CREATE POLICY backend_tentativas_ler ON public.tentativas_login FOR SELECT TO hidrocontrol_app USING (true);
CREATE POLICY backend_tentativas_inserir ON public.tentativas_login FOR INSERT TO hidrocontrol_app WITH CHECK (true);
