<?php
declare(strict_types=1);

function idLeitura(mixed $valor): int
{
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new ErroHttp(400, 'Identificador de leitura inválido.');
    return $id;
}

function salvarLeitura(array $usuario, array $entrada, ?int $id = null): int
{
    if (!in_array($entrada['turbina_ligada'] ?? null, ['0', '1'], true)) throw new InvalidArgumentException('Selecione o estado da turbina.');
    $usina = new Usina('Usina SENAI', lerNumero($entrada, 'nivel_reservatorio'), lerNumero($entrada, 'temperatura'),
        lerNumero($entrada, 'vazao'), lerNumero($entrada, 'potencia'), $entrada['turbina_ligada'] === '1');
    $dados = $usina->obterDados();
    unset($dados['nome']);
    return transacao(conectar(), function () use ($usuario, $dados, $id) {
        autenticacao()->autorizar($usuario, $id === null ? 'cadastrar' : 'editar');
        if ($id === null) {
            $q = conectar()->prepare('INSERT INTO leituras (nivel_reservatorio, temperatura, vazao, potencia, turbina_ligada, status_geral)
                VALUES (:nivel_reservatorio, :temperatura, :vazao, :potencia, :turbina_ligada, :status_geral) RETURNING id');
        } else {
            $q = conectar()->prepare('UPDATE leituras SET nivel_reservatorio = :nivel_reservatorio, temperatura = :temperatura,
                vazao = :vazao, potencia = :potencia, turbina_ligada = :turbina_ligada, status_geral = :status_geral WHERE id = :id RETURNING id');
            $dados['id'] = $id;
        }
        $q->execute($dados);
        $salvo = $q->fetchColumn();
        if (!$salvo) throw new ErroHttp(404, 'Leitura não encontrada.');
        autenticacao()->auditar($id === null ? 'leitura_cadastrada' : 'leitura_editada', $usuario['id'], (string) $salvo);
        return (int) $salvo;
    });
}
