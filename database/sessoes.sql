-- Complemento MySQL: adiciona sessões compartilhadas a uma instalação que já tem leituras.
-- A tabela agrupa os campos abaixo; a chave primária identifica cada registro.
CREATE TABLE IF NOT EXISTS sessoes (
    -- ID de sessão do PHP: a comparação binária diferencia letras maiúsculas e minúsculas.
    id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    -- Conteúdo serializado da sessão, armazenado como bytes no MySQL.
    dados MEDIUMBLOB NOT NULL,
    -- Prazo da sessão em segundos desde a época Unix; a aplicação renova por duas horas.
    expira_em BIGINT UNSIGNED NOT NULL,
    -- Apoia a busca das sessões vencidas durante a coleta de lixo.
    INDEX sessoes_expiracao (expira_em)
) ENGINE=InnoDB;
