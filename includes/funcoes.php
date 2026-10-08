<?php
// Inicialização comum às páginas e utilitários de apresentação/validação.
declare(strict_types=1);

// O fuso também orienta a interpretação das datas recebidas nos filtros.
date_default_timezone_set('America/Sao_Paulo');
// __DIR__ mantém os caminhos corretos independentemente da pasta de execução.
require_once __DIR__ . '/../CONFIG/conexao.php';
require_once __DIR__ . '/../CLASSES/usina.php';
require_once __DIR__ . '/sessao.php';

/** Escapa texto e atributos HTML para que valores não sejam interpretados como marcação. */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formata medições com duas casas, vírgula decimal e ponto de milhar. */
function numero(mixed $valor): string
{
    return number_format((float) $valor, 2, ',', '.');
}

/** Converte a data do banco para Brasília antes de formatá-la para exibição. */
function dataHora(string $valor): string
{
    return (new DateTimeImmutable($valor))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i:s');
}

/** Associa somente os status conhecidos às classes CSS de cor. */
function classeStatus(string $status): string
{
    return ['Normal' => 'normal', 'Atenção' => 'atencao', 'Crítico' => 'critico'][$status] ?? '';
}

/** Reconstrói o objeto de domínio a partir de uma linha retornada pelo PDO. */
function usinaDaLeitura(array $leitura): Usina
{
    return new Usina('Usina SENAI', (float) $leitura['nivel_reservatorio'],
        (float) $leitura['temperatura'], (float) $leitura['vazao'],
        (float) $leitura['potencia'], (bool) $leitura['turbina_ligada']);
}

/** Aceita números com sinal opcional, ponto ou vírgula e até duas casas decimais. */
function lerNumero(array $entrada, string $campo): float
{
    // Rejeita campos ausentes, arrays e formatos como notação científica.
    $valor = $entrada[$campo] ?? null;
    if (!is_string($valor) || !preg_match('/^-?\d+(?:[.,]\d{1,2})?$/D', trim($valor))) {
        throw new InvalidArgumentException('Preencha todos os números corretamente, com até duas casas decimais.');
    }
    // Normaliza a vírgula somente depois de validar o conteúdo completo.
    return (float) str_replace(',', '.', trim($valor));
}

/** Mensagem pública genérica: nunca revela SQL, host, usuário ou senha. */
function erroBanco(): string
{
    return 'Não foi possível acessar o banco de dados. Tente novamente em instantes.';
}
