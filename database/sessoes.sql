CREATE TABLE IF NOT EXISTS sessoes (
    id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    dados MEDIUMBLOB NOT NULL,
    expira_em BIGINT UNSIGNED NOT NULL,
    INDEX sessoes_expiracao (expira_em)
) ENGINE=InnoDB;
