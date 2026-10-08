<?php
declare(strict_types=1);

class Usina
{
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
            if (!is_finite($valor) || abs($valor - round($valor, 2)) > 0.000001) {
                throw new InvalidArgumentException('Use números válidos com até duas casas decimais.');
            }
        }
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

    public function verificarNivel(): string
    {
        if ($this->nivelReservatorio < 30) return 'Nível baixo';
        if ($this->nivelReservatorio > 90) return 'Nível muito alto';
        return 'Normal';
    }

    public function verificarTemperatura(): string
    {
        if ($this->temperaturaTurbina > 85) return 'Crítico';
        if ($this->temperaturaTurbina >= 70) return 'Atenção';
        return 'Normal';
    }

    public function verificarTurbina(): string
    {
        return $this->turbinaLigada ? 'Em operação' : 'Desligada';
    }

    public function verificarSituacaoGeral(): string
    {
        if ($this->verificarTemperatura() === 'Crítico') return 'Crítico';
        if ($this->verificarNivel() !== 'Normal' || $this->verificarTemperatura() === 'Atenção' || !$this->turbinaLigada) {
            return 'Atenção';
        }
        return 'Normal';
    }

    public function gerarAlertas(): array
    {
        $alertas = [];
        if ($this->verificarNivel() !== 'Normal') $alertas[] = 'Reservatório: ' . mb_strtolower($this->verificarNivel()) . '.';
        if ($this->verificarTemperatura() === 'Crítico') $alertas[] = 'Temperatura crítica na turbina.';
        elseif ($this->verificarTemperatura() === 'Atenção') $alertas[] = 'A temperatura da turbina exige atenção.';
        if (!$this->turbinaLigada) $alertas[] = 'A turbina está desligada.';
        return $alertas;
    }

    public function obterDados(): array
    {
        return [
            'nome' => $this->nome,
            'nivel_reservatorio' => $this->nivelReservatorio,
            'temperatura' => $this->temperaturaTurbina,
            'vazao' => $this->vazao,
            'potencia' => $this->potenciaGerada,
            'turbina_ligada' => (int) $this->turbinaLigada,
            'status_geral' => $this->verificarSituacaoGeral(),
        ];
    }
}
