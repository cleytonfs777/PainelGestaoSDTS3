<?php
session_start();
// Configuração do banco de dados
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
    $pdo = null;
}

// Criar tabela se não existir
if ($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS documentos_sei (
        id INT PRIMARY KEY AUTO_INCREMENT,
        numero_sei VARCHAR(255) NOT NULL,
        planilha_pdf_link TEXT,
        assunto TEXT NOT NULL,
        status ENUM('Concluído', 'Em andamento no NTS', 'Em andamento na ASSJUR', 'Em andamento na Prodemge', 'Em andamento em outros') NOT NULL,
        categoria_demanda ENUM('Prodemge - SSCIP', 'Prodemge - DRH', 'NTS', 'SDTS-3', 'SEJUSP', 'Outros') NOT NULL,
        atendente ENUM('Major Rocha', 'Cap Cleyton') NOT NULL,
        observacoes TEXT,
        data_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_ultima_alteracao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

// CRUD
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['novo_documento'])) {
        $stmt = $pdo->prepare('INSERT INTO documentos_sei (
            numero_sei, planilha_pdf_link, assunto, status, categoria_demanda, atendente, observacoes
        ) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['numero_sei'], $_POST['planilha_pdf_link'], $_POST['assunto'], 
            $_POST['status'], $_POST['categoria_demanda'], $_POST['atendente'], $_POST['observacoes']
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Documento criado com sucesso!'];
        header('Location: documentos.php');
        exit;
    }
    if (isset($_POST['edit_id'])) {
        $stmt = $pdo->prepare('UPDATE documentos_sei SET 
            numero_sei=?, planilha_pdf_link=?, assunto=?, status=?, categoria_demanda=?, atendente=?, observacoes=?
            WHERE id=?');
        $stmt->execute([
            $_POST['numero_sei'], $_POST['planilha_pdf_link'], $_POST['assunto'], 
            $_POST['status'], $_POST['categoria_demanda'], $_POST['atendente'], $_POST['observacoes'], $_POST['edit_id']
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Documento atualizado com sucesso!'];
        header('Location: documentos.php');
        exit;
    }
}

if ($pdo && isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare('DELETE FROM documentos_sei WHERE id = ?');
    $stmt->execute([$id]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Documento deletado com sucesso!'];
    header('Location: documentos.php');
    exit;
}

// PAGINAÇÃO E BUSCA
$por_pagina = isset($_GET['por_pagina']) ? max(1, intval($_GET['por_pagina'])) : 10;
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$where = '';
$params = [];
if ($busca !== '') {
    $where = "WHERE assunto LIKE ? OR numero_sei LIKE ? OR status LIKE ? OR atendente LIKE ?";
    $params = ["%$busca%", "%$busca%", "%$busca%", "%$busca%"];
}

$total_documentos = $pdo ? $pdo->prepare("SELECT COUNT(*) FROM documentos_sei $where") : 0;
if ($total_documentos) {
    $total_documentos->execute($params);
    $total_documentos = $total_documentos->fetchColumn();
} else {
    $total_documentos = 0;
}

$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $por_pagina;
$sql = "SELECT * FROM documentos_sei $where ORDER BY data_registro DESC LIMIT $por_pagina OFFSET $offset";
$stmt = $pdo ? $pdo->prepare($sql) : null;
if ($stmt) {
    $stmt->execute($params);
    $documentos = $stmt->fetchAll();
} else {
    $documentos = [];
}
$total_paginas = ceil($total_documentos / $por_pagina);

function flash_message() {
    if (!empty($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'] === 'success' ? 'alert-success' : 'alert-danger';
        $msg = $_SESSION['flash']['msg'];
        echo "<div class='alert $type' role='alert' style='margin-bottom:1rem;'>$msg</div>";
        unset($_SESSION['flash']);
    }
}

function getBadgeClass($status) {
    switch($status) {
        case 'Concluído': return 'badge-concluido';
        case 'Em andamento no NTS': return 'badge-nts';
        case 'Em andamento na ASSJUR': return 'badge-assjur';
        case 'Em andamento na Prodemge': return 'badge-prodemge';
        case 'Em andamento em outros': return 'badge-outros';
        default: return 'badge-padrao';
    }
}

ob_start();
?>
<style>
.container-main {
    max-width: 1400px !important;
    width: 95vw;
    margin: 2rem auto;
}
.table-documentos {
    font-size: 0.95rem;
}
.table-documentos th, .table-documentos td {
    padding: 0.8rem;
    text-align: center;
    vertical-align: middle;
}
.badge-status {
    display: inline-block;
    padding: 0.4em 0.8em;
    border-radius: 1em;
    font-weight: bold;
    color: #fff;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    white-space: nowrap;
}
.badge-concluido { background: #28a745; }
.badge-nts { background: #007bff; }
.badge-assjur { background: #fd7e14; }
.badge-prodemge { background: #6f42c1; }
.badge-outros { background: #6c757d; }
.badge-padrao { background: #343a40; }

.btn-view {
    border: none;
    background: none;
    cursor: pointer;
    font-size: 1.1rem;
    margin: 0 0.2rem;
    padding: 0.3rem 0.5rem;
    border-radius: 4px;
    transition: all 0.2s;
    color: #232946;
}
.btn-view:hover { background: #f8f9fa; }
.btn-edit { color: #007bff; }
.btn-delete { color: #dc3545; }

.pagination .page-link, .btn-pesquisar {
    background: #232946 !important;
    color: #fff !important;
    border: none !important;
    font-weight: bold;
    transition: background 0.2s;
}
.pagination .page-link:hover, .btn-pesquisar:hover, .pagination .active .page-link {
    background: #ffb347 !important;
    color: #232946 !important;
}

.assunto-cell {
    max-width: 300px;
    text-align: left;
    word-wrap: break-word;
    padding-left: 1rem !important;
}

.sei-cell {
    font-family: monospace;
    font-size: 0.9rem;
    color: #495057;
}

.link-planilha {
    color: #007bff;
    text-decoration: none;
    font-size: 1.1rem;
}
.link-planilha:hover {
    color: #0056b3;
    text-decoration: underline;
}
</style>
<div class="container-main">
    <?php flash_message(); ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="text-primary">📄 Gestão de Documentos SEI</h1>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNovoDocumento">
            <i class="fa fa-plus"></i> Novo Documento
        </button>
    </div>

    <!-- Formulário de Busca -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" 
                           placeholder="Buscar por assunto, número SEI, status ou atendente..." class="form-control">
                </div>
                <div class="col-md-2">
                    <select name="por_pagina" class="form-select">
                        <option value="10" <?= $por_pagina == 10 ? 'selected' : '' ?>>10 por página</option>
                        <option value="25" <?= $por_pagina == 25 ? 'selected' : '' ?>>25 por página</option>
                        <option value="50" <?= $por_pagina == 50 ? 'selected' : '' ?>>50 por página</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-pesquisar w-100">
                        <i class="fa fa-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Estatísticas Rápidas -->
    <div class="row mb-4">
        <?php
        $stats = [];
        if ($pdo) {
            $status_counts = $pdo->query("SELECT status, COUNT(*) as count FROM documentos_sei GROUP BY status")->fetchAll();
            foreach ($status_counts as $stat) {
                $stats[$stat['status']] = $stat['count'];
            }
        }
        ?>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-success"><?= $stats['Concluído'] ?? 0 ?></h5>
                    <p class="card-text small">Concluídos</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-primary"><?= $stats['Em andamento no NTS'] ?? 0 ?></h5>
                    <p class="card-text small">NTS</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-warning"><?= $stats['Em andamento na ASSJUR'] ?? 0 ?></h5>
                    <p class="card-text small">ASSJUR</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-info"><?= $stats['Em andamento na Prodemge'] ?? 0 ?></h5>
                    <p class="card-text small">Prodemge</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-secondary"><?= $stats['Em andamento em outros'] ?? 0 ?></h5>
                    <p class="card-text small">Outros</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-dark"><?= $total_documentos ?></h5>
                    <p class="card-text small">Total</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Documentos -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($documentos)): ?>
                <div class="alert alert-info text-center">
                    <i class="fa fa-info-circle"></i> Nenhum documento encontrado.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-documentos">
                        <thead class="table-dark">
                            <tr>
                                <th>Assunto</th>
                                <th>Status</th>
                                <th>Atendente</th>
                                <th>Nº SEI</th>
                                <th>Data Registro</th>
                                <th>Link</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documentos as $doc): ?>
                                <tr>
                                    <td class="assunto-cell">
                                        <strong><?= htmlspecialchars($doc['assunto']) ?></strong>
                                        <?php if (!empty($doc['observacoes'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars(substr($doc['observacoes'], 0, 80)) ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-status <?= getBadgeClass($doc['status']) ?>">
                                            <?= htmlspecialchars($doc['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($doc['atendente']) ?></td>
                                    <td class="sei-cell"><?= htmlspecialchars($doc['numero_sei']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($doc['data_registro'])) ?></td>
                                    <td>
                                        <?php if (!empty($doc['planilha_pdf_link'])): ?>
                                            <a href="<?= htmlspecialchars($doc['planilha_pdf_link']) ?>" 
                                               target="_blank" class="link-planilha" title="Abrir documento">
                                                <i class="fa fa-external-link-alt"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-view btn-edit" 
                                                data-bs-toggle="modal" data-bs-target="#modalEditarDocumento"
                                                onclick="editarDocumento(<?= htmlspecialchars(json_encode($doc)) ?>)"
                                                title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?= $doc['id'] ?>" 
                                           class="btn-view btn-delete"
                                           onclick="return confirm('Tem certeza que deseja excluir este documento?')"
                                           title="Excluir">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Paginação -->
    <?php if ($total_paginas > 1): ?>
        <nav aria-label="Paginação" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $i ?>&busca=<?= urlencode($busca) ?>&por_pagina=<?= $por_pagina ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Modal Novo Documento -->
<div class="modal fade" id="modalNovoDocumento" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fa fa-plus"></i> Novo Documento SEI</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="novo_documento" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nº SEI *</label>
                            <input type="text" name="numero_sei" class="form-control" required 
                                   placeholder="Ex: 1400.01.0067586/2023-20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Atendente *</label>
                            <select name="atendente" class="form-select" required>
                                <option value="">Selecione...</option>
                                <option value="Major Rocha">Major Rocha</option>
                                <option value="Cap Cleyton">Cap Cleyton</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Assunto *</label>
                            <textarea name="assunto" class="form-control" rows="2" required 
                                      placeholder="Descrição do que se trata o processo"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="">Selecione...</option>
                                <option value="Concluído">Concluído</option>
                                <option value="Em andamento no NTS">Em andamento no NTS</option>
                                <option value="Em andamento na ASSJUR">Em andamento na ASSJUR</option>
                                <option value="Em andamento na Prodemge">Em andamento na Prodemge</option>
                                <option value="Em andamento em outros">Em andamento em outros</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoria da Demanda *</label>
                            <select name="categoria_demanda" class="form-select" required>
                                <option value="">Selecione...</option>
                                <option value="Prodemge - SSCIP">Prodemge - SSCIP</option>
                                <option value="Prodemge - DRH">Prodemge - DRH</option>
                                <option value="NTS">NTS</option>
                                <option value="SDTS-3">SDTS-3</option>
                                <option value="SEJUSP">SEJUSP</option>
                                <option value="Outros">Outros</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Link para Planilha/PDF</label>
                            <input type="url" name="planilha_pdf_link" class="form-control" 
                                   placeholder="https://exemplo.com/documento.pdf">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" class="form-control" rows="3" 
                                      placeholder="Texto complementar para entender melhor o SEI"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Criar Documento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Documento -->
<div class="modal fade" id="modalEditarDocumento" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa fa-edit"></i> Editar Documento SEI</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formEditarDocumento">
                <div class="modal-body">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nº SEI *</label>
                            <input type="text" name="numero_sei" id="edit_numero_sei" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Atendente *</label>
                            <select name="atendente" id="edit_atendente" class="form-select" required>
                                <option value="Major Rocha">Major Rocha</option>
                                <option value="Cap Cleyton">Cap Cleyton</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Assunto *</label>
                            <textarea name="assunto" id="edit_assunto" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status *</label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="Concluído">Concluído</option>
                                <option value="Em andamento no NTS">Em andamento no NTS</option>
                                <option value="Em andamento na ASSJUR">Em andamento na ASSJUR</option>
                                <option value="Em andamento na Prodemge">Em andamento na Prodemge</option>
                                <option value="Em andamento em outros">Em andamento em outros</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoria da Demanda *</label>
                            <select name="categoria_demanda" id="edit_categoria_demanda" class="form-select" required>
                                <option value="Prodemge - SSCIP">Prodemge - SSCIP</option>
                                <option value="Prodemge - DRH">Prodemge - DRH</option>
                                <option value="NTS">NTS</option>
                                <option value="SDTS-3">SDTS-3</option>
                                <option value="SEJUSP">SEJUSP</option>
                                <option value="Outros">Outros</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Link para Planilha/PDF</label>
                            <input type="url" name="planilha_pdf_link" id="edit_planilha_pdf_link" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" id="edit_observacoes" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data de Registro</label>
                            <input type="text" id="edit_data_registro" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Última Alteração</label>
                            <input type="text" id="edit_data_alteracao" class="form-control" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Atualizar Documento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editarDocumento(doc) {
    document.getElementById('edit_id').value = doc.id;
    document.getElementById('edit_numero_sei').value = doc.numero_sei;
    document.getElementById('edit_atendente').value = doc.atendente;
    document.getElementById('edit_assunto').value = doc.assunto;
    document.getElementById('edit_status').value = doc.status;
    document.getElementById('edit_categoria_demanda').value = doc.categoria_demanda;
    document.getElementById('edit_planilha_pdf_link').value = doc.planilha_pdf_link || '';
    document.getElementById('edit_observacoes').value = doc.observacoes || '';
    
    // Formatar datas para exibição
    const dataRegistro = new Date(doc.data_registro).toLocaleString('pt-BR');
    const dataAlteracao = new Date(doc.data_ultima_alteracao).toLocaleString('pt-BR');
    document.getElementById('edit_data_registro').value = dataRegistro;
    document.getElementById('edit_data_alteracao').value = dataAlteracao;
}
</script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php
$content = ob_get_clean();
$page_title = 'Documentos SEI - SDTS3 Manager';
include 'base.php'; 
?> 