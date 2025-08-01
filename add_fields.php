<?php
$pdo = new PDO('mysql:host=db;dbname=sdts3;charset=utf8mb4', 'sdts3user', 'sdts3pass');

try {
    $pdo->exec('ALTER TABLE PacoteDespesaItens ADD COLUMN tipo_distribuicao VARCHAR(10) NOT NULL DEFAULT "Mensal" AFTER quantidade');
    echo "Campo tipo_distribuicao adicionado\n";
} catch (Exception $e) {
    echo "Campo tipo_distribuicao já existe ou erro: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec('ALTER TABLE PacoteDespesaItens ADD COLUMN mes_alocacao_anual TINYINT NULL AFTER tipo_distribuicao');
    echo "Campo mes_alocacao_anual adicionado\n";
} catch (Exception $e) {
    echo "Campo mes_alocacao_anual já existe ou erro: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec('ALTER TABLE PacoteDespesaItens ADD COLUMN mes_inicial_mensal TINYINT NULL AFTER mes_alocacao_anual');
    echo "Campo mes_inicial_mensal adicionado\n";
} catch (Exception $e) {
    echo "Campo mes_inicial_mensal já existe ou erro: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec('ALTER TABLE PacoteDespesaItens ADD COLUMN mes_final_mensal TINYINT NULL AFTER mes_inicial_mensal');
    echo "Campo mes_final_mensal adicionado\n";
} catch (Exception $e) {
    echo "Campo mes_final_mensal já existe ou erro: " . $e->getMessage() . "\n";
}

echo "Migração concluída!\n";
?>
