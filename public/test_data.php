<?php
// Teste rápido para verificar os dados
$host = 'db';
$db   = 'sdts3';
$user = 'sdts3user';
$pass = 'sdts3pass';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass);
    
    // Buscar dados como no sistema principal
    $stmt = $pdo->prepare('
        SELECT pri.*, ci.descricao_padrao, ci.valor_unitario, ci.acao, ci.grupo, ci.elemento_item,
               (pri.quantidade * ci.valor_unitario) as valor_total_item
        FROM PacoteReceitaItens pri
        JOIN CatalogoItens ci ON pri.id_item_catalogo = ci.id
        WHERE pri.id_pacote_receita = 3
    ');
    $stmt->execute();
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Dados dos itens do pacote:</h2>";
    foreach ($itens as $item) {
        echo "<h3>Item ID: {$item['id']}</h3>";
        echo "<pre>";
        print_r($item);
        echo "</pre>";
        
        echo "<h4>Data attributes que seriam gerados:</h4>";
        echo "<ul>";
        echo "<li>data-item-id=\"{$item['id']}\"</li>";
        echo "<li>data-quantidade=\"{$item['quantidade']}\"</li>";
        echo "<li>data-valor-unitario=\"{$item['valor_unitario']}\"</li>";
        echo "<li>data-acao=\"" . ($item['acao'] ?? '') . "\"</li>";
        echo "<li>data-grupo=\"{$item['grupo']}\"</li>";
        echo "<li>data-elemento=\"{$item['elemento_item']}\"</li>";
        echo "<li>data-tipo-distribuicao=\"" . ($item['tipo_distribuicao'] ?? 'Mensal') . "\"</li>";
        echo "<li>data-mes-alocacao=\"" . ($item['mes_alocacao_anual'] ?? '') . "\"</li>";
        echo "<li>data-mes-inicial=\"" . ($item['mes_inicial_mensal'] ?? '') . "\"</li>";
        echo "<li>data-mes-final=\"" . ($item['mes_final_mensal'] ?? '') . "\"</li>";
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
