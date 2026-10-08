<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../CONFIG/conexao.php';
require_once __DIR__ . '/../CLASSES/usina.php';
require_once __DIR__ . '/sessao.php';

function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function numero(mixed $valor): string
{
    return number_format((float) $valor, 2, ',', '.');
}

function dataHora(string $valor): string
{
    return (new DateTimeImmutable($valor))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i:s');
}

function classeStatus(string $status): string
{
    return ['Normal' => 'normal', 'Atenção' => 'atencao', 'Crítico' => 'critico'][$status] ?? '';
}

function usinaDaLeitura(array $leitura): Usina
{
    return new Usina('Usina SENAI', (float) $leitura['nivel_reservatorio'],
        (float) $leitura['temperatura'], (float) $leitura['vazao'],
        (float) $leitura['potencia'], (bool) $leitura['turbina_ligada']);
}

// Toda entrada numérica é validada antes da conversão para float.
function lerNumero(array $entrada, string $campo): float
{
    $valor = $entrada[$campo] ?? null;
    if (!is_string($valor) || !preg_match('/^-?\d+(?:[.,]\d{1,2})?$/D', trim($valor))) {
        throw new InvalidArgumentException('Preencha todos os números corretamente, com até duas casas decimais.');
    }
    return (float) str_replace(',', '.', trim($valor));
}

function erroBanco(): string
{
    return 'Não foi possível acessar o banco de dados. Tente novamente em instantes.';
}
