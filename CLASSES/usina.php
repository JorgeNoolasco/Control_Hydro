<?php
declare(strict_types=1);

/** Representa uma leitura da usina e calcula alertas didáticos, sem acessar o banco. */
class Usina
{
    /**
     * Guarda os dados em propriedades privadas, declaradas nos parâmetros do construtor.
     * Unidades: nível em %, temperatura em °C, vazão em m³/s e potência em MW.
     * Uma instância só é criada se todas as medições respeitarem os limites aceitos.
     */
    public function __construct(
        private string $nome,
        private float $nivelReservatorio,
        private float $temperaturaTurbina,
        private float $vazao,
        private float $potenciaGerada,
        private bool $turbinaLigada
    ) {
        // Limites também respeitam a capacidade das colunas DECIMAL do banco.
        foreach ([$nivelReservatorio, $temperaturaTurbina, $vazao, $potenciaGerada] as $valor) {
            // Rejeita infinito, NaN e precisão maior que duas casas; tolera erro de ponto flutuante.
            if (!is_finite($valor) || abs($valor - round($valor, 2)) > 0.000001) {
                throw new InvalidArgumentException('Use números válidos com até duas casas decimais.');
            }
        }
        // Percentuais são limitados a 0–100; os demais limites acompanham as colunas do banco.
        if ($nivelReservatorio < 0 || $nivelReservatorio > 100) {
            throw new InvalidArgumentException('O nível deve estar entre 0 e 100%.');
        }
        if ($temperaturaTurbina < -999.99 || $temperaturaTurbina > 999.99) {
            throw new InvalidArgumentException('A temperatura deve estar entre -999,99 e 999,99 °C.');
        }
        if ($vazao < 0 || $potenciaGerada < 0 || $vazao > 99999999.99 || $potenciaGerada > 99999999.99) {
            throw new InvalidArgumentException('Vazão e potência devem estar entre 0 e 99.999.999,99.');
        }
    }

    /** Considera normais os níveis entre 30% e 90%, incluindo as duas extremidades. */
    public function verificarNivel(): string
    {
        if ($this->nivelReservatorio < 30) return 'Nível baixo';
        if ($this->nivelReservatorio > 90) return 'Nível muito alto';
        return 'Normal';
    }

    /** Classifica a temperatura: abaixo de 70 normal, de 70 a 85 atenção, acima de 85 crítico. */
    public function verificarTemperatura(): string
    {
        if ($this->temperaturaTurbina > 85) return 'Crítico';
        if ($this->temperaturaTurbina >= 70) return 'Atenção';
        return 'Normal';
    }

    /** Converte o estado booleano em um rótulo para a interface. */
    public function verificarTurbina(): string
    {
        return $this->turbinaLigada ? 'Em operação' : 'Desligada';
    }

    /** Combina as regras, dando prioridade à temperatura crítica sobre os outros alertas. */
    public function verificarSituacaoGeral(): string
    {
        if ($this->verificarTemperatura() === 'Crítico') return 'Crítico';
        if ($this->verificarNivel() !== 'Normal' || $this->verificarTemperatura() === 'Atenção' || !$this->turbinaLigada) {
            return 'Atenção';
        }
        return 'Normal';
    }

    /** Retorna uma mensagem por problema encontrado; um array vazio indica normalidade. */
    public function gerarAlertas(): array
    {
        $alertas = [];
        if ($this->verificarNivel() !== 'Normal') $alertas[] = 'Reservatório: ' . mb_strtolower($this->verificarNivel()) . '.';
        if ($this->verificarTemperatura() === 'Crítico') $alertas[] = 'Temperatura crítica na turbina.';
        elseif ($this->verificarTemperatura() === 'Atenção') $alertas[] = 'A temperatura da turbina exige atenção.';
        if (!$this->turbinaLigada) $alertas[] = 'A turbina está desligada.';
        return $alertas;
    }

    /** Exporta as medições com os nomes das colunas e calcula o status que será armazenado. */
    public function obterDados(): array
    {
        return [
            'nome' => $this->nome,
            'nivel_reservatorio' => $this->nivelReservatorio,
            'temperatura' => $this->temperaturaTurbina,
            'vazao' => $this->vazao,
            'potencia' => $this->potenciaGerada,
            // 0 e 1 são aceitos na gravação tanto pelo MySQL quanto pelo PostgreSQL.
            'turbina_ligada' => (int) $this->turbinaLigada,
            'status_geral' => $this->verificarSituacaoGeral(),
        ];
    }
}
