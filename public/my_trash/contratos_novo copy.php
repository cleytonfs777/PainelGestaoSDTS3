<?php
session_start();
$host = 'db';
$db   = 'sdts3';
$user = 'sdts3user';
$pass = 'sdts3pass';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Não foi possível conectar ao banco de dados.'];
    header('Location: contratos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('INSERT INTO contratos (nome, cliente, valor, status, data_inicio, data_fim) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $_POST['nome'], $_POST['cliente'], $_POST['valor'], $_POST['status'], $_POST['data_inicio'], $_POST['data_fim']
    ]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Contrato criado com sucesso!'];
    header('Location: contratos.php');
    exit;
}

ob_start();
?>
<div class="container-main">
    <h1>Novo Contrato</h1>
    <form class="form-contrato" method="post">
        <input type="text" name="nome" placeholder="Nome do Contrato" required />
        <input type="text" name="cliente" placeholder="Cliente" required />
        <input type="number" step="0.01" name="valor" placeholder="Valor" required />
        <select name="status" required>
            <option value="Ativo">Ativo</option>
            <option value="Inativo">Inativo</option>
            <option value="Pendente">Pendente</option>
        </select>
        <input type="date" name="data_inicio" required />
        <input type="date" name="data_fim" required />
        <div style="display:flex;gap:1rem;justify-content:flex-end;">
            <button type="submit">Salvar</button>
            <a href="contratos.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$page_title = 'Novo Contrato - SDTS3 Manager';
include 'base.php'; 