CREATE DATABASE IF NOT EXISTS hidrocontrol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hidrocontrol;

CREATE TABLE IF NOT EXISTS sensores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    unidade VARCHAR(20) NOT NULL,
    UNIQUE KEY sensores_tipo (tipo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS leituras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nivel_reservatorio DECIMAL(5,2) NOT NULL,
    temperatura DECIMAL(5,2) NOT NULL,
    vazao DECIMAL(10,2) NOT NULL,
    potencia DECIMAL(10,2) NOT NULL,
    turbina_ligada BOOLEAN NOT NULL,
    status_geral VARCHAR(20) NOT NULL,
    data_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX leituras_data (data_registro, id),
    INDEX leituras_status_data (status_geral, data_registro, id),
    CONSTRAINT nivel_valido CHECK (nivel_reservatorio BETWEEN 0 AND 100),
    CONSTRAINT vazao_valida CHECK (vazao >= 0),
    CONSTRAINT potencia_valida CHECK (potencia >= 0),
    CONSTRAINT turbina_valida CHECK (turbina_ligada IN (0, 1)),
    CONSTRAINT status_valido CHECK (status_geral IN ('Normal', 'Atenção', 'Crítico'))
) ENGINE=InnoDB;

-- Sensores conceituais; cada leitura representa a usina inteira.
INSERT INTO sensores (nome, tipo, unidade) VALUES
('Nível do reservatório', 'nivel', '%'),
('Temperatura da turbina', 'temperatura', '°C'),
('Vazão de água', 'vazao', 'm³/s'),
('Potência gerada', 'potencia', 'MW')
ON DUPLICATE KEY UPDATE nome = VALUES(nome), unidade = VALUES(unidade);

CREATE TABLE IF NOT EXISTS sessoes (
    id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    dados MEDIUMBLOB NOT NULL,
    expira_em BIGINT UNSIGNED NOT NULL,
    INDEX sessoes_expiracao (expira_em)
) ENGINE=InnoDB;
