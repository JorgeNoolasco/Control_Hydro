-- Estrutura MySQL para um banco já selecionado; não cria nem troca o banco.
-- A tabela agrupa os campos abaixo; a chave primária identifica cada registro.
CREATE TABLE IF NOT EXISTS sensores (
    -- Identificador inteiro gerado automaticamente pelo MySQL.
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- Nome legível do sensor conceitual.
    nome VARCHAR(100) NOT NULL,
    -- Categoria da medição; deve ser única entre os sensores.
    tipo VARCHAR(50) NOT NULL,
    -- Símbolo usado para expressar a medição, como %, °C, m³/s ou MW.
    unidade VARCHAR(20) NOT NULL,
    -- Impede cadastrar a mesma categoria de sensor mais de uma vez.
    UNIQUE KEY sensores_tipo (tipo)
) ENGINE=InnoDB;

-- A tabela agrupa os campos abaixo; a chave primária identifica cada registro.
CREATE TABLE IF NOT EXISTS leituras (
    -- Identificador inteiro gerado automaticamente pelo MySQL.
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- Percentual de preenchimento, com duas casas decimais.
    nivel_reservatorio DECIMAL(5,2) NOT NULL,
    -- Temperatura da turbina, com até três algarismos inteiros e duas casas decimais.
    temperatura DECIMAL(5,2) NOT NULL,
    -- Vazão informada em m³/s, com até oito algarismos inteiros e duas casas decimais.
    vazao DECIMAL(10,2) NOT NULL,
    -- Potência gerada em MW, com a mesma precisão da vazão.
    potencia DECIMAL(10,2) NOT NULL,
    -- Estado operacional: verdadeiro/1 para ligada e falso/0 para desligada.
    turbina_ligada BOOLEAN NOT NULL,
    -- Classificação calculada pelo PHP: Normal, Atenção ou Crítico.
    status_geral VARCHAR(20) NOT NULL,
    -- Data local preenchida pelo MySQL; a aplicação ajusta a conexão para UTC-03:00.
    data_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Acelera consultas ordenadas e intervalos de data, usando ID como desempate.
    INDEX leituras_data (data_registro, id),
    -- Apoia consultas que combinam situação, data e ordenação.
    INDEX leituras_status_data (status_geral, data_registro, id),
    -- Regras CHECK mantêm os limites válidos também em gravações feitas fora do formulário.
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
-- Reexecutar o script atualiza nome/unidade dos sensores existentes, sem duplicá-los.
ON DUPLICATE KEY UPDATE nome = VALUES(nome), unidade = VALUES(unidade);

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
