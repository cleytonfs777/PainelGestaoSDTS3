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

if (!isset($_GET['id'])) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Contrato não encontrado.'];
    header('Location: contratos.php');
    exit;
}

$id = intval($_GET['id']);
$stmt = $pdo->prepare('SELECT * FROM contratos WHERE id = ?');
$stmt->execute([$id]);
$contrato = $stmt->fetch();

if (!$contrato) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Contrato não encontrado.'];
    header('Location: contratos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('UPDATE contratos SET nome=?, cliente=?, valor=?, status=?, data_inicio=?, data_fim=? WHERE id=?');
    $stmt->execute([
        $_POST['nome'], $_POST['cliente'], $_POST['valor'], $_POST['status'], $_POST['data_inicio'], $_POST['data_fim'], $id
    ]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Contrato atualizado com sucesso!'];
    header('Location: contratos.php');
    exit;
}

ob_start();
?>
<div class="container-main">
    <h1>Editar Contrato</h1>
    <form class="form-contrato" method="post">
        <input type="text" name="nome" value="<?php echo htmlspecialchars($contrato['nome']); ?>" required />
        <input type="text" name="cliente" value="<?php echo htmlspecialchars($contrato['cliente']); ?>" required />
        <input type="number" step="0.01" name="valor" value="<?php echo $contrato['valor']; ?>" required />
        <select name="status" required>
            <option value="Ativo" <?php if($contrato['status']==='Ativo') echo 'selected'; ?>>Ativo</option>
            <option value="Inativo" <?php if($contrato['status']==='Inativo') echo 'selected'; ?>>Inativo</option>
            <option value="Pendente" <?php if($contrato['status']==='Pendente') echo 'selected'; ?>>Pendente</option>
        </select>
        <input type="date" name="data_inicio" value="<?php echo $contrato['data_inicio']; ?>" required />
        <input type="date" name="data_fim" value="<?php echo $contrato['data_fim']; ?>" required />
        <div style="display:flex;gap:1rem;justify-content:flex-end;">
            <button type="submit">Salvar</button>
            <a href="contratos.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$page_title = 'Editar Contrato - SDTS3 Manager';
include 'base.php'; 