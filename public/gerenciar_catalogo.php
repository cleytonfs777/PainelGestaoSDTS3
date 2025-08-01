<?php
session_start();

// Conexão com o banco de dados
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
    die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
}

// Função para feedback visual
function feedback($msg, $type = 'success') {
    $_SESSION['feedback'] = "<div id='feedback' class='feedback $type'>$msg</div>";
}

// Função para formatar valores monetários
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Processamento de formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Adicionar item ao catálogo
        if (isset($_POST['add_item_catalogo'])) {
            $acao = ($_POST['tipo'] === 'Receita' && !empty($_POST['acao'])) ? $_POST['acao'] : null;
            $fonte = ($_POST['tipo'] === 'Receita' && !empty($_POST['fonte'])) ? $_POST['fonte'] : null;
            
            $stmt = $pdo->prepare('INSERT INTO CatalogoItens (tipo, descricao_padrao, valor_unitario, acao, grupo, elemento_item, fonte) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $_POST['tipo'],
                $_POST['descricao_padrao'],
                $_POST['valor_unitario'],
                $acao,
                $_POST['grupo'],
                $_POST['elemento_item'],
                $fonte
            ]);
            feedback('Item adicionado ao catálogo com sucesso!');
        }
        
        // Editar item do catálogo
        if (isset($_POST['edit_item_catalogo'])) {
            $acao = ($_POST['tipo'] === 'Receita' && !empty($_POST['acao'])) ? $_POST['acao'] : null;
            $fonte = ($_POST['tipo'] === 'Receita' && !empty($_POST['fonte'])) ? $_POST['fonte'] : null;
            
            $stmt = $pdo->prepare('UPDATE CatalogoItens SET tipo=?, descricao_padrao=?, valor_unitario=?, acao=?, grupo=?, elemento_item=?, fonte=? WHERE id=?');
            $stmt->execute([
                $_POST['tipo'],
                $_POST['descricao_padrao'],
                $_POST['valor_unitario'],
                $acao,
                $_POST['grupo'],
                $_POST['elemento_item'],
                $fonte,
                $_POST['edit_item_catalogo']
            ]);
            feedback('Item do catálogo atualizado com sucesso!');
        }
        
        // Deletar item do catálogo
        if (isset($_POST['delete_item_catalogo'])) {
            // Verificar se o item está sendo usado
            $stmt_check = $pdo->prepare('
                SELECT 
                    (SELECT COUNT(*) FROM PacoteReceitaItens WHERE id_item_catalogo = ?) +
                    (SELECT COUNT(*) FROM PacoteDespesaItens WHERE id_item_catalogo = ?) as total
            ');
            $stmt_check->execute([$_POST['delete_item_catalogo'], $_POST['delete_item_catalogo']]);
            $usage = $stmt_check->fetch();
            
            if ($usage['total'] > 0) {
                feedback('Não é possível excluir este item pois ele está sendo usado em pacotes existentes.', 'error');
            } else {
                $stmt = $pdo->prepare('DELETE FROM CatalogoItens WHERE id=?');
                $stmt->execute([$_POST['delete_item_catalogo']]);
                feedback('Item removido do catálogo com sucesso!');
            }
        }
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            feedback('Erro: Já existe um item com essa descrição para este tipo.', 'error');
        } else {
            feedback('Erro ao processar formulário: ' . $e->getMessage(), 'error');
        }
    }
    
    // Redirect para evitar reenvio de formulário
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Buscar itens do catálogo
$itens_receita = $pdo->query("SELECT * FROM CatalogoItens WHERE tipo = 'Receita' ORDER BY descricao_padrao")->fetchAll();
$itens_despesa = $pdo->query("SELECT * FROM CatalogoItens WHERE tipo = 'Despesa' ORDER BY descricao_padrao")->fetchAll();

ob_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Catálogo - SDTS3</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f7f7fa 0%, #e8eaf6 100%);
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2rem;
            overflow: hidden;
            position: relative;
        }
        
        .header {
            text-align: center;
            padding-bottom: 2rem;
            border-bottom: 3px solid #232946;
            margin-bottom: 3rem;
        }
        
        .header h1 {
            color: #232946;
            font-size: 2.5rem;
            margin: 0;
            font-weight: 700;
        }
        
        .header p {
            color: #666;
            font-size: 1.1rem;
            margin: 0.5rem 0 0 0;
        }
        
        .back-link {
            position: absolute;
            top: 2rem;
            right: 2rem;
            background: #4a5568;
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .back-link:hover {
            background: #2d3748;
            color: #fff;
        }
        
        .section {
            margin-bottom: 3rem;
            background: #fafbfc;
            border-radius: 12px;
            padding: 2rem;
            border-left: 5px solid #232946;
        }
        
        .section h2 {
            color: #232946;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section h2::before {
            content: '';
            width: 8px;
            height: 30px;
            background: linear-gradient(45deg, #232946, #4a5568);
            border-radius: 4px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        
        .form-card h3 {
            color: #232946;
            margin-bottom: 1rem;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.3rem;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.95rem;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #232946;
            box-shadow: 0 0 0 3px rgba(35, 41, 70, 0.1);
            outline: none;
        }
        
        .btn {
            background: linear-gradient(45deg, #232946, #4a5568);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(35, 41, 70, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            margin: 0 0.2rem;
        }
        
        .btn-edit {
            background: linear-gradient(45deg, #2563eb, #3b82f6);
        }
        
        .btn-delete {
            background: linear-gradient(45deg, #dc2626, #ef4444);
        }
        
        .btn-cancel {
            background: #6b7280;
            color: #fff;
        }
        
        .btn-cancel:hover {
            background: #4b5563;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        th {
            background: linear-gradient(45deg, #232946, #4a5568);
            color: #fff;
            padding: 1rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 0.75rem 1rem;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }
        
        tr:nth-child(even) {
            background: #f8fafc;
        }
        
        tr:hover {
            background: #e2e8f0;
            transition: background-color 0.2s ease;
        }
        
        .feedback {
            margin: 1rem 0;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .feedback.success {
            background: linear-gradient(45deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-left: 5px solid #22c55e;
        }
        
        .feedback.error {
            background: linear-gradient(45deg, #fee2e2, #fecaca);
            color: #991b1b;
            border-left: 5px solid #ef4444;
        }
        
        .tipo-badge {
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .tipo-receita {
            background: #d1fae5;
            color: #065f46;
        }
        
        .tipo-despesa {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .classification-info {
            font-size: 0.85rem;
            color: #6b7280;
            line-height: 1.3;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            table {
                font-size: 0.9rem;
            }
            
            th, td {
                padding: 0.5rem;
            }
            
            .back-link {
                position: static;
                display: inline-block;
                margin-bottom: 1rem;
            }
        }
        
        /* Estilos para modais */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: #fff;
            border-radius: 15px;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0; 
                transform: translateY(50px) scale(0.9); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .modal-title {
            color: #232946;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6b7280;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close-modal:hover {
            background: #f3f4f6;
            color: #374151;
            transform: rotate(90deg);
        }
        
        .btn-modal-trigger {
            background: linear-gradient(45deg, #22c55e, #16a34a);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-modal-trigger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
            color: #fff;
        }
        
        .modal-actions {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            text-align: right;
        }
        
        .btn-cancel {
            background: #f8f9fa;
            color: #6b7280;
            border: 1px solid #d1d5db;
            margin-right: 1rem;
        }
        
        .btn-cancel:hover {
            background: #e5e7eb;
            transform: none;
            box-shadow: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="gestao_orcamentaria.php" class="back-link">⬅️ Voltar para Gestão Orçamentária</a>
        
        <div class="header">
            <h1>Gerenciar Catálogo de Itens</h1>
            <p>Catálogo Central de Itens Padronizados para Receitas e Despesas</p>
        </div>
        
        <?php if (isset($_SESSION['feedback'])): ?>
            <?php echo $_SESSION['feedback']; unset($_SESSION['feedback']); ?>
        <?php endif; ?>
        
        <!-- Seção de Formulário -->
        <div class="section">
            <h2>🗂️ Catálogo de Itens</h2>
            
            <div style="margin-bottom: 2rem; text-align: center;">
                <button class="btn-modal-trigger" onclick="openModal('modal-item')">
                    ➕ Novo Item do Catálogo
                </button>
            </div>
            
            <div class="form-card" style="max-width: 600px; margin: 0 auto;">
                <h3>Informações sobre o Catálogo</h3>
                <p><strong>Dica:</strong> Crie itens padronizados que poderão ser reutilizados em múltiplos pacotes.</p>
                <p><strong>Classificação Orçamentária:</strong></p>
                <ul style="text-align: left; margin-left: 20px;">
                    <li><strong>Receitas:</strong> Ação, Grupo, Elemento Item e Fonte</li>
                    <li><strong>Despesas:</strong> Apenas Grupo e Elemento Item</li>
                </ul>
                <p><strong>Valor Unitário:</strong> Defina um valor padrão que pode ser multiplicado pela quantidade ao adicionar o item a um pacote.</p>
            </div>
            
            <!-- Modal para adicionar/editar item -->
            <div id="modal-item" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title" id="modal-title">Novo Item do Catálogo</h3>
                        <button class="close-modal" onclick="closeModal('modal-item')">&times;</button>
                    </div>
                    
                    <form method="post" id="form-item">
                        <input type="hidden" id="item-action" name="" value="">
                        
                        <div class="form-group">
                            <label>Tipo</label>
                            <select name="tipo" id="input-tipo" required>
                                <option value="">Selecione...</option>
                                <option value="Receita">Receita</option>
                                <option value="Despesa">Despesa</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Descrição Padrão</label>
                            <input type="text" name="descricao_padrao" id="input-descricao" required maxlength="255">
                        </div>
                        
                        <div class="form-group">
                            <label>Valor Unitário</label>
                            <input type="number" name="valor_unitario" id="input-valor" step="0.01" required min="0">
                        </div>
                        
                        <div class="form-group">
                            <label>Ação</label>
                            <input type="number" name="acao" id="input-acao" min="1" class="campo-receita">
                            <small class="text-muted">Apenas para receitas</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Grupo</label>
                            <input type="number" name="grupo" id="input-grupo" required min="1">
                        </div>
                        
                        <div class="form-group">
                            <label>Elemento Item</label>
                            <input type="number" name="elemento_item" id="input-elemento" required min="1">
                        </div>
                        
                        <div class="form-group">
                            <label>Fonte</label>
                            <input type="number" name="fonte" id="input-fonte" min="1" class="campo-receita">
                            <small class="text-muted">Apenas para receitas</small>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="button" class="btn btn-cancel" onclick="closeModal('modal-item')">Cancelar</button>
                            <button type="submit" class="btn" id="btn-submit">Adicionar Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Seção de Itens de Receita -->
        <div class="section">
            <h2>💰 Itens de Receita</h2>
            
            <?php if (!empty($itens_receita)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Valor Unitário</th>
                            <th>Classificação Orçamentária</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens_receita as $item): ?>
                            <tr>
                                <td style="text-align: left;">
                                    <span class="tipo-badge tipo-receita">Receita</span><br>
                                    <strong><?php echo htmlspecialchars($item['descricao_padrao']); ?></strong>
                                </td>
                                <td><?php echo formatarMoeda($item['valor_unitario']); ?></td>
                                <td>
                                    <div class="classification-info">
                                        Ação: <?php echo $item['acao']; ?><br>
                                        Grupo: <?php echo $item['grupo']; ?><br>
                                        Elemento: <?php echo $item['elemento_item']; ?><br>
                                        Fonte: <?php echo $item['fonte']; ?>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-small btn-edit" onclick="editItem(<?php echo $item['id']; ?>, '<?php echo addslashes($item['tipo']); ?>', '<?php echo addslashes($item['descricao_padrao']); ?>', <?php echo $item['valor_unitario']; ?>, '<?php echo $item['acao'] ?? ''; ?>', <?php echo $item['grupo']; ?>, <?php echo $item['elemento_item']; ?>, '<?php echo $item['fonte'] ?? ''; ?>')">✏️</button>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este item?')">
                                        <input type="hidden" name="delete_item_catalogo" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="feedback" style="background: #f3f4f6; color: #6b7280; border-left-color: #9ca3af;">
                    ℹ️ Nenhum item de receita cadastrado ainda.
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Seção de Itens de Despesa -->
        <div class="section">
            <h2>💸 Itens de Despesa</h2>
            
            <?php if (!empty($itens_despesa)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Valor Unitário</th>
                            <th>Classificação Orçamentária</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens_despesa as $item): ?>
                            <tr>
                                <td style="text-align: left;">
                                    <span class="tipo-badge tipo-despesa">Despesa</span><br>
                                    <strong><?php echo htmlspecialchars($item['descricao_padrao']); ?></strong>
                                </td>
                                <td><?php echo formatarMoeda($item['valor_unitario']); ?></td>
                                <td>
                                    <div class="classification-info">
                                        Grupo: <?php echo $item['grupo']; ?><br>
                                        Elemento: <?php echo $item['elemento_item']; ?>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-small btn-edit" onclick="editItem(<?php echo $item['id']; ?>, '<?php echo addslashes($item['tipo']); ?>', '<?php echo addslashes($item['descricao_padrao']); ?>', <?php echo $item['valor_unitario']; ?>, '<?php echo $item['acao'] ?? ''; ?>', <?php echo $item['grupo']; ?>, <?php echo $item['elemento_item']; ?>, '<?php echo $item['fonte'] ?? ''; ?>')">✏️</button>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este item?')">
                                        <input type="hidden" name="delete_item_catalogo" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="feedback" style="background: #f3f4f6; color: #6b7280; border-left-color: #9ca3af;">
                    ℹ️ Nenhum item de despesa cadastrado ainda.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-hide feedback messages
        document.addEventListener('DOMContentLoaded', function() {
            const feedback = document.getElementById('feedback');
            if (feedback) {
                setTimeout(() => {
                    feedback.style.opacity = '0';
                    setTimeout(() => {
                        feedback.style.display = 'none';
                    }, 300);
                }, 5000);
            }
        });
        
        // Funções para controlar modais
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
                
                // Limpar formulário
                const form = modal.querySelector('form');
                if (form) {
                    form.reset();
                    // Reset para modo de adicionar
                    document.getElementById('modal-title').textContent = 'Novo Item do Catálogo';
                    document.getElementById('btn-submit').textContent = 'Adicionar Item';
                    document.getElementById('item-action').name = 'add_item_catalogo';
                    document.getElementById('item-action').value = '1';
                    
                    // Configurar campos baseado no tipo
                    document.getElementById('input-tipo').dispatchEvent(new Event('change'));
                }
            }
        }
        
        // Controlar visibilidade dos campos baseado no tipo
        document.getElementById('input-tipo').addEventListener('change', function() {
            const isReceita = this.value === 'Receita';
            const camposReceita = document.querySelectorAll('.campo-receita');
            
            camposReceita.forEach(campo => {
                campo.required = isReceita;
                campo.disabled = !isReceita;
                
                if (!isReceita) {
                    campo.value = '';
                }
                
                // Mostrar/ocultar o grupo do campo
                const formGroup = campo.closest('.form-group');
                if (formGroup) {
                    formGroup.style.display = isReceita ? 'block' : 'none';
                }
            });
        });
        
        // Trigger change event on page load to set initial state
        document.getElementById('input-tipo').dispatchEvent(new Event('change'));
        
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        }
        
        // Função para editar item
        function editItem(id, tipo, descricao, valor, acao, grupo, elemento, fonte) {
            // Abrir modal
            openModal('modal-item');
            
            // Configurar para modo de edição
            document.getElementById('modal-title').textContent = 'Editar Item';
            document.getElementById('btn-submit').textContent = 'Atualizar Item';
            document.getElementById('item-action').name = 'edit_item_catalogo';
            document.getElementById('item-action').value = id;
            
            // Preencher campos
            document.getElementById('input-tipo').value = tipo;
            document.getElementById('input-descricao').value = descricao;
            document.getElementById('input-valor').value = valor;
            document.getElementById('input-acao').value = acao || '';
            document.getElementById('input-grupo').value = grupo;
            document.getElementById('input-elemento').value = elemento;
            document.getElementById('input-fonte').value = fonte || '';
            
            // Configurar campos baseado no tipo
            document.getElementById('input-tipo').dispatchEvent(new Event('change'));
        }
        
        // Fechar modal clicando fora do conteúdo
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal') && e.target.classList.contains('show')) {
                closeModal(e.target.id);
            }
        });
        
        // Fechar modal com tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    closeModal(openModal.id);
                }
            }
        });
        
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                    // Não validar formulários de delete
                    if (this.querySelector('input[name="delete_item_catalogo"]')) {
                        return;
                    }
                    
                    const requiredFields = this.querySelectorAll('[required]:not([disabled])');
                    let isValid = true;                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#ef4444';
                        isValid = false;
                    } else {
                        field.style.borderColor = '#e2e8f0';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Por favor, preencha todos os campos obrigatórios.');
                }
            });
        });
    </script>
</body>
</html>
<?php
$content = ob_get_clean();
$page_title = 'Gerenciar Catálogo - SDTS3 Manager';
include 'base.php';
?>
