<?php
declare(strict_types=1);
require_once __DIR__ . '/../CLASSES/usina.php';

$casos = [
    ['A', 70, 65, true, 'Normal', 0],
    ['B', 25, 78, true, 'Atenção', 2],
    ['C', 95, 92, false, 'Crítico', 3],
    ['Nível 30%', 30, 65, true, 'Normal', 0],
    ['Nível 90%', 90, 65, true, 'Normal', 0],
    ['Nível 29,99%', 29.99, 65, true, 'Atenção', 1],
    ['Nível 90,01%', 90.01, 65, true, 'Atenção', 1],
    ['Temperatura 69,99', 70, 69.99, true, 'Normal', 0],
    ['Temperatura 70', 70, 70, true, 'Atenção', 1],
    ['Temperatura 85', 70, 85, true, 'Atenção', 1],
    ['Temperatura 85,01', 70, 85.01, true, 'Crítico', 1],
    ['Turbina desligada', 70, 65, false, 'Atenção', 1],
];
foreach ($casos as [$nome, $nivel, $temperatura, $turbina, $esperado, $alertas]) {
    $usina = new Usina('Teste', $nivel, $temperatura, 420, 65, $turbina);
    if ($usina->verificarSituacaoGeral() !== $esperado || count($usina->gerarAlertas()) !== $alertas) {
        fwrite(STDERR, "Falha: $nome\n");
        exit(1);
    }
    echo "OK: $nome\n";
}
foreach ([[-1,65,1,1], [101,65,1,1], [70,65,-1,1], [70,65,1,-1], [70,1000,1,1], [NAN,65,1,1], [70,65,INF,1], [70.001,65,1,1]] as $valores) {
    try {
        new Usina('Teste', $valores[0], $valores[1], $valores[2], $valores[3], true);
        fwrite(STDERR, "Falha: entrada inválida aceita.\n");
        exit(1);
    } catch (InvalidArgumentException $ex) {
        echo "OK: entrada inválida rejeitada\n";
    }
}
echo "20 testes passaram. Nenhuma leitura foi gravada no banco.\n";
