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

// CRUD
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $uploadFileName = null;
    if (isset($_FILES['contrato_documento']) && $_FILES['contrato_documento']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['contrato_documento']['name'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $uploadFileName = uniqid('contrato_') . '.pdf';
            move_uploaded_file($_FILES['contrato_documento']['tmp_name'], $uploadDir . $uploadFileName);
        }
    }
    if (isset($_POST['novo_contrato'])) {
        $stmt = $pdo->prepare('INSERT INTO contratos (
            objeto, detalhamento, el_item, valor_anual_estimado, valor_ppag, valor_empenhado, nr_contrato, meses, servico_continuado, anos_limite_contratual, data_inicio, data_final, empenho, liquidacao, nr_termo_aditivo, quantidade, distribuicao, processo_sei_sdts, processo_sei_csm, status_aditamento, situacao_aditamento_ano_corrente, razao_social, email, responsavel, telefone, situacao, observacoes, contrato_documento
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['objeto'], $_POST['detalhamento'], $_POST['el_item'], $_POST['valor_anual_estimado'], $_POST['valor_ppag'], $_POST['valor_empenhado'],
            $_POST['nr_contrato'], $_POST['meses'], isset($_POST['servico_continuado']) ? 1 : 0, $_POST['anos_limite_contratual'], $_POST['data_inicio'], $_POST['data_final'],
            isset($_POST['empenho']) ? 1 : 0, isset($_POST['liquidacao']) ? 1 : 0, $_POST['nr_termo_aditivo'], $_POST['quantidade'], $_POST['distribuicao'],
            $_POST['processo_sei_sdts'], $_POST['processo_sei_csm'], $_POST['status_aditamento'], $_POST['situacao_aditamento_ano_corrente'],
            $_POST['razao_social'], $_POST['email'], $_POST['responsavel'], $_POST['telefone'], $_POST['situacao'], $_POST['observacoes'], $uploadFileName
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Contrato criado com sucesso!'];
        header('Location: contratos.php');
        exit;
    }
    if (isset($_POST['edit_id'])) {
        $stmt = $pdo->prepare('UPDATE contratos SET 
            objeto=?, detalhamento=?, el_item=?, valor_anual_estimado=?, valor_ppag=?, valor_empenhado=?, nr_contrato=?, meses=?, servico_continuado=?, anos_limite_contratual=?, data_inicio=?, data_final=?, empenho=?, liquidacao=?, nr_termo_aditivo=?, quantidade=?, distribuicao=?, processo_sei_sdts=?, processo_sei_csm=?, status_aditamento=?, situacao_aditamento_ano_corrente=?, razao_social=?, email=?, responsavel=?, telefone=?, situacao=?, observacoes=?, contrato_documento=?
            WHERE id=?');
        $stmt->execute([
            $_POST['objeto'], $_POST['detalhamento'], $_POST['el_item'], $_POST['valor_anual_estimado'], $_POST['valor_ppag'], $_POST['valor_empenhado'],
            $_POST['nr_contrato'], $_POST['meses'], isset($_POST['servico_continuado']) ? 1 : 0, $_POST['anos_limite_contratual'], $_POST['data_inicio'], $_POST['data_final'],
            isset($_POST['empenho']) ? 1 : 0, isset($_POST['liquidacao']) ? 1 : 0, $_POST['nr_termo_aditivo'], $_POST['quantidade'], $_POST['distribuicao'],
            $_POST['processo_sei_sdts'], $_POST['processo_sei_csm'], $_POST['status_aditamento'], $_POST['situacao_aditamento_ano_corrente'],
            $_POST['razao_social'], $_POST['email'], $_POST['responsavel'], $_POST['telefone'], $_POST['situacao'], $_POST['observacoes'], $uploadFileName ?? $_POST['contrato_documento_atual'], $_POST['edit_id']
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Contrato atualizado com sucesso!'];
        header('Location: contratos.php');
        exit;
    }
}
if ($pdo && isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare('DELETE FROM contratos WHERE id = ?');
    $stmt->execute([$id]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Contrato deletado com sucesso!'];
    header('Location: contratos.php');
    exit;
}

// PAGINAÇÃO
$por_pagina = isset($_GET['por_pagina']) ? max(1, intval($_GET['por_pagina'])) : 10;
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$where = '';
$params = [];
if ($busca !== '') {
    $buscaLower = mb_strtolower($busca);
    if ($buscaLower === 'vigente') {
        $where = "WHERE data_final >= ?";
        $params = [date('Y-m-d')];
    } elseif ($buscaLower === 'vencido') {
        $where = "WHERE data_final < ?";
        $params = [date('Y-m-d')];
    } else {
        $where = "WHERE objeto LIKE ? OR razao_social LIKE ? OR situacao LIKE ?";
        $params = ["%$busca%", "%$busca%", "%$busca%"];
    }
}
$total_contratos = $pdo ? $pdo->prepare("SELECT COUNT(*) FROM contratos $where") : 0;
if ($total_contratos) {
    $total_contratos->execute($params);
    $total_contratos = $total_contratos->fetchColumn();
} else {
    $total_contratos = 0;
}
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $por_pagina;
$sql = "SELECT * FROM contratos $where ORDER BY id DESC LIMIT $por_pagina OFFSET $offset";
$stmt = $pdo ? $pdo->prepare($sql) : null;
if ($stmt) {
    $stmt->execute($params);
    $contratos = $stmt->fetchAll();
} else {
    $contratos = [];
}
$total_paginas = ceil($total_contratos / $por_pagina);

// Verificar contratos próximos do vencimento (menos de 60 dias)
$contratos_proximos = [];
if ($pdo) {
    $hoje = date('Y-m-d');
    $limite = date('Y-m-d', strtotime('+60 days'));
    $stmt = $pdo->prepare('SELECT * FROM contratos WHERE data_final >= ? AND data_final <= ? ORDER BY data_final ASC');
    $stmt->execute([$hoje, $limite]);
    $contratos_proximos = $stmt->fetchAll();
}

function flash_message() {
    if (!empty($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'] === 'success' ? 'alert-success' : 'alert-danger';
        $msg = $_SESSION['flash']['msg'];
        echo "<div class='alert $type' role='alert' style='margin-bottom:1rem;'>$msg</div>";
        unset($_SESSION['flash']);
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
.table-contratos {
    font-size: 1rem;
}
.table-contratos th, .table-contratos td {
    padding: 1rem;
    text-align: center;
}
.badge-status {
    display: inline-block;
    padding: 0.25em 1.2em;
    border-radius: 1em;
    font-weight: bold;
    color: #fff;
    font-size: 0.85rem;
    letter-spacing: 1px;
}
.badge-vigente {
    background: #217346;
}
.badge-vencido {
    background: #b30000;
}
.btn-view {
    border: none;
    background: none;
    cursor: pointer;
    font-size: 1.2rem;
    margin: 0 0.3rem;
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    transition: background 0.2s;
    color: #232946;
    outline: none;
    box-shadow: none;
}
.btn-view:focus, .btn-view:active {
    outline: none;
    box-shadow: none;
}
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
</style>
<div class="container-main">
    <?php flash_message(); ?>
    <?php if (!empty($contratos_proximos)): ?>
    <div class="modal fade" id="modalContratosProximos" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-warning-subtle">
            <h5 class="modal-title" style="color:#b30000;"><i class="fa fa-exclamation-triangle"></i> Contratos próximos do vencimento</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Os seguintes contratos estão a menos de 60 dias do vencimento:</p>
            <ul>
              <?php foreach ($contratos_proximos as $c): ?>
                <li><b><?php echo htmlspecialchars($c['objeto']); ?></b> (Nº <?php echo htmlspecialchars($c['nr_contrato']); ?>) - Vence em <b><?php echo date('d/m/Y', strtotime($c['data_final'])); ?></b></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
          </div>
        </div>
      </div>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        var modal = new bootstrap.Modal(document.getElementById('modalContratosProximos'));
        modal.show();
      });
    </script>
    <?php endif; ?>
    <div class="contratos-header">
        <h1>Contratos</h1>
        <button class="btn-novo" data-bs-toggle="modal" data-bs-target="#modalNovoContrato"><i class="fa fa-plus"></i> Novo Contrato</button>
    </div>
    <form method="get" class="mb-2" style="display:flex;align-items:center;gap:1rem;justify-content:space-between;">
      <div style="flex:1;">
        <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" class="form-control form-control-sm" placeholder="Pesquisar por Objeto, Razão Social ou Status..." style="max-width:350px;display:inline-block;" />
        <button type="submit" class="btn btn-sm btn-pesquisar">Pesquisar</button>
        <?php if ($busca): ?><a href="?por_pagina=<?php echo $por_pagina; ?>" class="btn btn-sm btn-secondary">Limpar</a><?php endif; ?>
      </div>
      <div>
        <label for="por_pagina" style="margin-bottom:0;">Itens por página:</label>
        <select name="por_pagina" id="por_pagina" class="form-select form-select-sm" style="width:auto;display:inline-block;" onchange="this.form.submit()">
          <option value="5" <?php if($por_pagina==5) echo 'selected'; ?>>5</option>
          <option value="10" <?php if($por_pagina==10) echo 'selected'; ?>>10</option>
          <option value="20" <?php if($por_pagina==20) echo 'selected'; ?>>20</option>
          <option value="50" <?php if($por_pagina==50) echo 'selected'; ?>>50</option>
        </select>
      </div>
    </form>
    <table class="table-contratos">
        <thead>
            <tr>
                <th>ID</th>
                <th>Objeto</th>
                <th>Nº Contrato</th>
                <th>Valor Anual</th>
                <th>Razão Social</th>
                <th>Início</th>
                <th>Status</th>
                <th>Documento</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contratos as $contrato): ?>
            <?php
                $hoje = date('Y-m-d');
                $status_vigencia = (strtotime($contrato['data_final']) >= strtotime($hoje))
                    ? '<span class="badge-status badge-vigente"><b>Vigente</b></span>'
                    : '<span class="badge-status badge-vencido"><b>Vencido</b></span>';
            ?>
            <tr>
                <td><?php echo $contrato['id']; ?></td>
                <td><?php echo htmlspecialchars($contrato['objeto']); ?></td>
                <td><?php echo htmlspecialchars($contrato['nr_contrato'] ?? ''); ?></td>
                <td>R$ <?php echo number_format($contrato['valor_anual_estimado'] ?? 0,2,',','.'); ?></td>
                <td><?php echo htmlspecialchars($contrato['razao_social'] ?? ''); ?></td>
                <td><?php echo !empty($contrato['data_inicio']) ? date('d/m/Y', strtotime($contrato['data_inicio'])) : ''; ?></td>
                <td><?php echo $status_vigencia; ?></td>
                <td>
                  <?php if (!empty($contrato['contrato_documento'])): ?>
                    <a href="uploads/<?php echo htmlspecialchars($contrato['contrato_documento']); ?>" target="_blank" title="Download do Contrato"><i class="fa fa-file-pdf" style="color:#d7263d;font-size:1.3em;"></i></a>
                  <?php else: ?>
                    <span style="color:#bbb;">-</span>
                  <?php endif; ?>
                </td>
                <td>
                    <button class="btn-view" title="Visualizar" data-bs-toggle="modal" data-bs-target="#modalVisualizarContrato<?php echo $contrato['id']; ?>"><i class="fa fa-eye"></i></button>
                    <button class="btn-edit" title="Editar" data-bs-toggle="modal" data-bs-target="#modalEditarContrato<?php echo $contrato['id']; ?>"><i class="fa fa-pen"></i></button>
                    <a href="?delete=<?php echo $contrato['id']; ?>" class="btn-delete" title="Deletar" onclick="return confirm('Tem certeza que deseja deletar este contrato?');"><i class="fa fa-trash"></i></a>
                </td>
            </tr>
            <!-- Modal Visualizar Contrato -->
            <div class="modal fade" id="modalVisualizarContrato<?php echo $contrato['id']; ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-xl">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Contrato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <div class="row">
                      <div class="col-md-6">
                        <p><strong>Objeto:</strong> <?php echo htmlspecialchars($contrato['objeto']); ?></p>
                        <p><strong>Detalhamento:</strong> <?php echo htmlspecialchars($contrato['detalhamento']); ?></p>
                        <p><strong>EL/Item:</strong> <?php echo $contrato['el_item'] ?? ''; ?></p>
                        <p><strong>Valor Anual Estimado:</strong> R$ <?php echo number_format($contrato['valor_anual_estimado'] ?? 0,2,',','.'); ?></p>
                        <p><strong>Valor PPAG:</strong> R$ <?php echo number_format($contrato['valor_ppag'] ?? 0,2,',','.'); ?></p>
                        <p><strong>Valor Empenhado:</strong> R$ <?php echo number_format($contrato['valor_empenhado'] ?? 0,2,',','.'); ?></p>
                        <p><strong>Nº Contrato:</strong> <?php echo htmlspecialchars($contrato['nr_contrato'] ?? ''); ?></p>
                        <p><strong>Meses:</strong> <?php echo $contrato['meses'] ?? ''; ?></p>
                        <p><strong>Serviço Continuado:</strong> <?php echo !empty($contrato['servico_continuado']) ? 'Sim' : 'Não'; ?></p>
                        <p><strong>Anos Limite Contratual:</strong> <?php echo $contrato['anos_limite_contratual'] ?? ''; ?></p>
                        <p><strong>Data Início:</strong> <?php echo !empty($contrato['data_inicio']) ? date('d/m/Y', strtotime($contrato['data_inicio'])) : ''; ?></p>
                        <p><strong>Data Final:</strong> <?php echo !empty($contrato['data_final']) ? date('d/m/Y', strtotime($contrato['data_final'])) : ''; ?></p>
                        <p><strong>Empenho:</strong> <?php echo !empty($contrato['empenho']) ? 'Sim' : 'Não'; ?></p>
                        <p><strong>Liquidação:</strong> <?php echo !empty($contrato['liquidacao']) ? 'Sim' : 'Não'; ?></p>
                        <p><strong>Nº Termo Aditivo:</strong> <?php echo $contrato['nr_termo_aditivo'] ?? ''; ?></p>
                        <p><strong>Quantidade:</strong> <?php echo $contrato['quantidade'] ?? ''; ?></p>
                      </div>
                      <div class="col-md-6">
                        <p><strong>Distribuição:</strong> <?php echo htmlspecialchars($contrato['distribuicao'] ?? ''); ?></p>
                        <p><strong>Processo SEI SDTS:</strong> <?php echo htmlspecialchars($contrato['processo_sei_sdts'] ?? ''); ?></p>
                        <p><strong>Processo SEI CSM:</strong> <?php echo htmlspecialchars($contrato['processo_sei_csm'] ?? ''); ?></p>
                        <p><strong>Status Aditamento:</strong> <?php echo htmlspecialchars($contrato['status_aditamento'] ?? ''); ?></p>
                        <p><strong>Situação Aditamento Ano Corrente:</strong> <?php echo htmlspecialchars($contrato['situacao_aditamento_ano_corrente'] ?? ''); ?></p>
                        <p><strong>Razão Social:</strong> <?php echo htmlspecialchars($contrato['razao_social'] ?? ''); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($contrato['email'] ?? ''); ?></p>
                        <p><strong>Responsável:</strong> <?php echo htmlspecialchars($contrato['responsavel'] ?? ''); ?></p>
                        <p><strong>Telefone:</strong> <?php echo htmlspecialchars($contrato['telefone'] ?? ''); ?></p>
                        <p><strong>Situação:</strong> <?php echo htmlspecialchars($contrato['situacao'] ?? ''); ?></p>
                        <p><strong>Observações:</strong> <?php echo nl2br(htmlspecialchars($contrato['observacoes'] ?? '')); ?></p>
                        <p><strong>Documento do Contrato:</strong> <?php if (!empty($contrato['contrato_documento'])): ?><a href="uploads/<?php echo htmlspecialchars($contrato['contrato_documento']); ?>" target="_blank" class="btn btn-outline-danger btn-sm"><i class="fa fa-file-pdf"></i> Baixar PDF</a><?php else: ?>Não enviado<?php endif; ?></p>
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                  </div>
                </div>
              </div>
            </div>
            <!-- Modal Editar Contrato -->
            <div class="modal fade" id="modalEditarContrato<?php echo $contrato['id']; ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-xl">
                <div class="modal-content">
                  <form method="post" enctype="multipart/form-data">
                    <div class="modal-header">
                      <h5 class="modal-title">Editar Contrato</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body form-contrato">
                      <input type="hidden" name="edit_id" value="<?php echo $contrato['id']; ?>" />
                      <input type="hidden" name="contrato_documento_atual" value="<?php echo htmlspecialchars($contrato['contrato_documento']); ?>" />
                      <div class="row g-3">
                        <div class="col-md-6"><input type="text" name="objeto" value="<?php echo htmlspecialchars($contrato['objeto'] ?? ''); ?>" placeholder="Objeto" class="form-control" required /></div>
                        <div class="col-md-6"><input type="text" name="detalhamento" value="<?php echo htmlspecialchars($contrato['detalhamento'] ?? ''); ?>" placeholder="Detalhamento" class="form-control" /></div>
                        <div class="col-md-6"><input type="number" name="el_item" value="<?php echo $contrato['el_item'] ?? ''; ?>" placeholder="EL/Item" class="form-control" /></div>
                        <div class="col-md-6"><input type="number" step="0.01" name="valor_anual_estimado" value="<?php echo $contrato['valor_anual_estimado'] ?? ''; ?>" placeholder="Valor Anual Estimado" class="form-control" /></div>
                        <div class="col-md-6"><input type="number" step="0.01" name="valor_ppag" value="<?php echo $contrato['valor_ppag'] ?? ''; ?>" placeholder="Valor PPAG" class="form-control" /></div>
                        <div class="col-md-6"><input type="number" step="0.01" name="valor_empenhado" value="<?php echo $contrato['valor_empenhado'] ?? ''; ?>" placeholder="Valor Empenhado" class="form-control" /></div>
                        <div class="col-md-6"><input type="text" name="nr_contrato" value="<?php echo htmlspecialchars($contrato['nr_contrato'] ?? ''); ?>" placeholder="Nº Contrato" class="form-control" /></div>
                        <div class="col-md-6"><input type="number" name="meses" value="<?php echo $contrato['meses'] ?? ''; ?>" placeholder="Meses" class="form-control" /></div>
                        <div class="col-md-6"><input type="checkbox" name="servico_continuado" value="1" <?php if(!empty($contrato['servico_continuado'])) echo 'checked'; ?> /> Serviço Continuado</div>
                        <div class="col-md-6"><input type="number" name="anos_limite_contratual" value="<?php echo $contrato['anos_limite_contratual'] ?? ''; ?>" placeholder="Anos Limite Contratual" class="form-control" /></div>
                        <div class="col-md-6"><input type="date" name="data_inicio" value="<?php echo $contrato['data_inicio'] ?? ''; ?>" class="form-control" /></div>
                        <div class="col-md-6"><input type="date" name="data_final" value="<?php echo $contrato['data_final'] ?? ''; ?>" class="form-control" /></div>
                        <div class="col-md-6"><input type="checkbox" name="empenho" value="1" <?php if(!empty($contrato['empenho'])) echo 'checked'; ?> /> Empenho</div>
                        <div class="col-md-6"><input type="checkbox" name="liquidacao" value="1" <?php if(!empty($contrato['liquidacao'])) echo 'checked'; ?> /> Liquidação</div>
                        <div class="col-md-6"><input type="number" name="nr_termo_aditivo" value="<?php echo $contrato['nr_termo_aditivo'] ?? ''; ?>" placeholder="Nº Termo Aditivo" class="form-control" /></div>
                        <div class="col-md-6"><input type="number" step="0.01" name="quantidade" value="<?php echo $contrato['quantidade'] ?? ''; ?>" placeholder="Quantidade" class="form-control" /></div>
                        <div class="col-md-6"><input type="text" name="distribuicao" value="<?php echo htmlspecialchars($contrato['distribuicao'] ?? ''); ?>" placeholder="Distribuição" class="form-control" /></div>
                        <div class="col-md-6"><input type="text" name="processo_sei_sdts" value="<?php echo htmlspecialchars($contrato['processo_sei_sdts'] ?? ''); ?>" placeholder="Processo SEI SDTS" class="form-control" /></div>
                        <div class="col-md-6"><input type="text" name="processo_sei_csm" value="<?php echo htmlspecialchars($contrato['processo_sei_csm'] ?? ''); ?>" placeholder="Processo SEI CSM" class="form-control" /></div>
                        <div class="col-md-6"><input type="text" name="status_aditamento" value="<?php echo htmlspecialchars($contrato['status_aditamento'] ?? ''); ?>" placeholder="Status Aditamento" class="form-control" /></div>
                        <div class="col-md-6">
                          <select name="situacao_aditamento_ano_corrente" class="form-select">
                            <option value="Não se aplica" <?php if(($contrato['situacao_aditamento_ano_corrente'] ?? '')==='Não se aplica') echo 'selected'; ?>>Não se aplica</option>
                            <option value="Não iniciado" <?php if(($contrato['situacao_aditamento_ano_corrente'] ?? '')==='Não iniciado') echo 'selected'; ?>>Não iniciado</option>
                            <option value="Montagem do processo" <?php if(($contrato['situacao_aditamento_ano_corrente'] ?? '')==='Montagem do processo') echo 'selected'; ?>>Montagem do processo</option>
                            <option value="NFC" <?php if(($contrato['situacao_aditamento_ano_corrente'] ?? '')==='NFC') echo 'selected'; ?>>NFC</option>
                            <option value="Núcleo Jurídico" <?php if(($contrato['situacao_aditamento_ano_corrente'] ?? '')==='Núcleo Jurídico') echo 'selected'; ?>>Núcleo Jurídico</option>
                            <option value="Ajustes finais" <?php if(($contrato['situacao_aditamento_ano_corrente'] ?? '')==='Ajustes finais') echo 'selected'; ?>>Ajustes finais</option>
                            <option value="Assinado e publicado" <?php if(($contrato['situacao_aditamento_ano_corrente'] ?? '')==='Assinado e publicado') echo 'selected'; ?>>Assinado e publicado</option>
                          </select>
                        </div>
                        <div class="col-md-6"><input type="text" name="razao_social" value="<?php echo htmlspecialchars($contrato['razao_social'] ?? ''); ?>" placeholder="Razão Social" class="form-control" /></div>
                        <div class="col-md-6"><input type="email" name="email" value="<?php echo htmlspecialchars($contrato['email'] ?? ''); ?>" placeholder="Email" class="form-control" /></div>
                        <div class="col-md-6"><input type="text" name="responsavel" value="<?php echo htmlspecialchars($contrato['responsavel'] ?? ''); ?>" placeholder="Responsável" class="form-control" /></div>
                        <div class="col-md-6"><input type="text" name="telefone" value="<?php echo htmlspecialchars($contrato['telefone'] ?? ''); ?>" placeholder="Telefone" class="form-control" /></div>
                        <div class="col-md-6">
                          <select name="situacao" class="form-select">
                            <option value="Ativo" <?php if(($contrato['situacao'] ?? '')==='Ativo') echo 'selected'; ?>>Ativo</option>
                            <option value="Inativo" <?php if(($contrato['situacao'] ?? '')==='Inativo') echo 'selected'; ?>>Inativo</option>
                            <option value="Pendente" <?php if(($contrato['situacao'] ?? '')==='Pendente') echo 'selected'; ?>>Pendente</option>
                            <option value="Não informado" <?php if(($contrato['situacao'] ?? '')==='Não informado') echo 'selected'; ?>>Não informado</option>
                          </select>
                        </div>
                        <div class="col-12"><textarea name="observacoes" placeholder="Observações" class="form-control"><?php echo htmlspecialchars($contrato['observacoes'] ?? ''); ?></textarea></div>
                        <div class="col-md-6">
                          <label>Documento do Contrato (PDF):</label>
                          <input type="file" name="contrato_documento" accept="application/pdf" class="form-control" />
                          <?php if (!empty($contrato['contrato_documento'])): ?>
                            <a href="uploads/<?php echo htmlspecialchars($contrato['contrato_documento']); ?>" target="_blank">Ver documento atual</a>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                      <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($contratos)): ?>
            <tr><td colspan="9" style="text-align:center;color:#888;">Nenhum contrato cadastrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if ($total_paginas > 1): ?>
    <nav aria-label="Paginação de contratos">
      <ul class="pagination justify-content-center mt-3">
        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
          <li class="page-item <?php if ($i == $pagina) echo 'active'; ?>">
            <a class="page-link" href="?pagina=<?php echo $i; ?>&por_pagina=<?php echo $por_pagina; ?><?php if($busca) echo '&busca=' . urlencode($busca); ?>"><?php echo $i; ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
    <?php endif; ?>
</div>
<!-- Modal Novo Contrato -->
<div class="modal fade" id="modalNovoContrato" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Novo Contrato</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body form-contrato">
          <input type="hidden" name="novo_contrato" value="1" />
          <div class="row g-3">
            <div class="col-md-6"><input type="text" name="objeto" placeholder="Objeto" class="form-control" required /></div>
            <div class="col-md-6"><input type="text" name="detalhamento" placeholder="Detalhamento" class="form-control" /></div>
            <div class="col-md-6"><input type="number" name="el_item" placeholder="EL/Item" class="form-control" /></div>
            <div class="col-md-6"><input type="number" step="0.01" name="valor_anual_estimado" placeholder="Valor Anual Estimado" class="form-control" /></div>
            <div class="col-md-6"><input type="number" step="0.01" name="valor_ppag" placeholder="Valor PPAG" class="form-control" /></div>
            <div class="col-md-6"><input type="number" step="0.01" name="valor_empenhado" placeholder="Valor Empenhado" class="form-control" /></div>
            <div class="col-md-6"><input type="text" name="nr_contrato" placeholder="Nº Contrato" class="form-control" /></div>
            <div class="col-md-6"><input type="number" name="meses" placeholder="Meses" class="form-control" /></div>
            <div class="col-md-6"><input type="checkbox" name="servico_continuado" value="1" /> Serviço Continuado</div>
            <div class="col-md-6"><input type="number" name="anos_limite_contratual" placeholder="Anos Limite Contratual" class="form-control" /></div>
            <div class="col-md-6"><input type="date" name="data_inicio" class="form-control" /></div>
            <div class="col-md-6"><input type="date" name="data_final" class="form-control" /></div>
            <div class="col-md-6"><input type="checkbox" name="empenho" value="1" /> Empenho</div>
            <div class="col-md-6"><input type="checkbox" name="liquidacao" value="1" /> Liquidação</div>
            <div class="col-md-6"><input type="number" name="nr_termo_aditivo" placeholder="Nº Termo Aditivo" class="form-control" /></div>
            <div class="col-md-6"><input type="number" step="0.01" name="quantidade" placeholder="Quantidade" class="form-control" /></div>
            <div class="col-md-6"><input type="text" name="distribuicao" placeholder="Distribuição" class="form-control" /></div>
            <div class="col-md-6"><input type="text" name="processo_sei_sdts" placeholder="Processo SEI SDTS" class="form-control" /></div>
            <div class="col-md-6"><input type="text" name="processo_sei_csm" placeholder="Processo SEI CSM" class="form-control" /></div>
            <div class="col-md-6"><input type="text" name="status_aditamento" placeholder="Status Aditamento" class="form-control" /></div>
            <div class="col-md-6">
              <select name="situacao_aditamento_ano_corrente" class="form-select">
                <option value="Não se aplica">Não se aplica</option>
                <option value="Não iniciado">Não iniciado</option>
                <option value="Montagem do processo">Montagem do processo</option>
                <option value="NFC">NFC</option>
                <option value="Núcleo Jurídico">Núcleo Jurídico</option>
                <option value="Ajustes finais">Ajustes finais</option>
                <option value="Assinado e publicado">Assinado e publicado</option>
              </select>
            </div>
            <div class="col-md-6"><input type="text" name="razao_social" placeholder="Razão Social" class="form-control" /></div>
            <div class="col-md-6"><input type="email" name="email" placeholder="Email" class="form-control" /></div>
            <div class="col-md-6"><input type="text" name="responsavel" placeholder="Responsável" class="form-control" /></div>
            <div class="col-md-6"><input type="text" name="telefone" placeholder="Telefone" class="form-control" /></div>
            <div class="col-md-6">
              <select name="situacao" class="form-select">
            <option value="Ativo">Ativo</option>
            <option value="Inativo">Inativo</option>
            <option value="Pendente">Pendente</option>
                <option value="Não informado">Não informado</option>
          </select>
            </div>
            <div class="col-12"><textarea name="observacoes" placeholder="Observações" class="form-control"></textarea></div>
            <div class="col-md-6">
              <label>Documento do Contrato (PDF):</label>
              <input type="file" name="contrato_documento" accept="application/pdf" class="form-control" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php
$content = ob_get_clean();
$page_title = 'Contratos - SDTS3 Manager';
include 'base.php'; 