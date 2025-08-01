<?php
// Conexão simples para teste
$host = 'db';
$db   = 'sdts3';
$user = 'sdts3user';
$pass = 'sdts3pass';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass);
    echo "Conexão OK<br>";
    
    if (isset($_GET['delete_despesa'])) {
        $id = intval($_GET['delete_despesa']);
        echo "Tentando deletar ID: $id<br>";
        
        $stmt = $pdo->prepare('DELETE FROM PacotesDespesa WHERE id = ?');
        $result = $stmt->execute([$id]);
        
        echo "Resultado: " . ($result ? 'sucesso' : 'falha') . "<br>";
        echo "Linhas afetadas: " . $stmt->rowCount() . "<br>";
    }
    
    // Mostrar todos os pacotes
    $stmt = $pdo->query('SELECT id, unidade_gerenciadora FROM PacotesDespesa');
    $pacotes = $stmt->fetchAll();
    
    echo "<h3>Pacotes existentes:</h3>";
    foreach ($pacotes as $pacote) {
        echo "ID: {$pacote['id']} - {$pacote['unidade_gerenciadora']} ";
        echo "<a href='?delete_despesa={$pacote['id']}'>DELETE</a><br>";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
