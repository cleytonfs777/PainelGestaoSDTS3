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

// Criação das tabelas com a nova arquitetura baseada em catálogo
$pdo->exec("CREATE TABLE IF NOT EXISTS CatalogoItens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo ENUM('Receita', 'Despesa') NOT NULL,
    descricao_padrao VARCHAR(255) NOT NULL,
    valor_unitario DECIMAL(15, 2) NOT NULL,
    acao INT NULL,
    grupo INT NOT NULL,
    elemento_item INT NOT NULL,
    fonte INT NULL,
    UNIQUE (tipo, descricao_padrao)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS PacotesReceita (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unidade_gerenciadora VARCHAR(50) NOT NULL,
    data_criacao DATE NOT NULL,
    documento_sei VARCHAR(255) NULL,
    descricao_pacote TEXT NULL,
    ano SMALLINT NOT NULL,
    valor_total_calculado DECIMAL(15, 2) DEFAULT 0.00
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS PacotesDespesa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unidade_gerenciadora VARCHAR(50) NOT NULL,
    data_criacao DATE NOT NULL,
    documento_sei VARCHAR(255) NULL,
    descricao_pacote TEXT NULL,
    valor_total_calculado DECIMAL(15, 2) DEFAULT 0.00
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS PacoteReceitaItens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pacote_receita INT NOT NULL,
    id_item_catalogo INT NOT NULL,
    quantidade INT NOT NULL,
    tipo_distribuicao VARCHAR(10) NOT NULL,
    mes_alocacao_anual TINYINT NULL,
    mes_inicial_mensal TINYINT NULL,
    mes_final_mensal TINYINT NULL,
    FOREIGN KEY (id_pacote_receita) REFERENCES PacotesReceita(id) ON DELETE CASCADE,
    FOREIGN KEY (id_item_catalogo) REFERENCES CatalogoItens(id),
    UNIQUE (id_pacote_receita, id_item_catalogo)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS PacoteDespesaItens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pacote_despesa INT NOT NULL,
    id_item_catalogo INT NOT NULL,
    quantidade INT NOT NULL,
    tipo_distribuicao VARCHAR(10) NOT NULL DEFAULT 'Mensal',
    mes_alocacao_anual TINYINT NULL,
    mes_inicial_mensal TINYINT NULL,
    mes_final_mensal TINYINT NULL,
    FOREIGN KEY (id_pacote_despesa) REFERENCES PacotesDespesa(id) ON DELETE CASCADE,
    FOREIGN KEY (id_item_catalogo) REFERENCES CatalogoItens(id),
    UNIQUE (id_pacote_despesa, id_item_catalogo)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS LancamentosMensais (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pacote_receita_item INT NOT NULL,
    ano SMALLINT NOT NULL,
    mes TINYINT NOT NULL,
    valor_mes DECIMAL(15, 2) NOT NULL,
    FOREIGN KEY (id_pacote_receita_item) REFERENCES PacoteReceitaItens(id) ON DELETE CASCADE,
    UNIQUE (id_pacote_receita_item, ano, mes)
)");

// Inserir dados iniciais do catálogo se não existir (COMENTADO - dados podem ser gerenciados via interface)
/*
if ($pdo->query('SELECT COUNT(*) FROM CatalogoItens')->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO CatalogoItens (tipo, descricao_padrao, valor_unitario, acao, grupo, elemento_item, fonte) VALUES
        ('Despesa', 'Analista de Sistemas', 120.00, NULL, 3, 339036, NULL),
        ('Despesa', 'Desenvolvedor Pleno', 100.00, NULL, 3, 339036, NULL),
        ('Despesa', 'Licença Software', 500.00, NULL, 4, 449052, NULL),
        ('Receita', 'Receita Ordinária', 50000.00, 2010, 1, 113301, 100),
        ('Receita', 'Receita Extraordinária', 25000.00, 2010, 1, 113302, 200)");
}
*/

// Verificar e migrar/limpar dados antigos se necessário
try {
    // Verificar se existem tabelas antigas
    $tables_to_check = ['ItensReceita', 'ItensPacoteDespesa'];
    foreach ($tables_to_check as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            // Tabela antiga existe, podemos fazer backup e limpar se necessário
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            if ($count > 0) {
                // Se houver dados, marcar para notificação do usuário
                $_SESSION['migration_notice'] = "Detectados dados no formato antigo. Considere migrar ou limpar as tabelas antigas.";
            }
        }
    }
} catch (Exception $e) {
    // Ignorar erros de tabelas que não existem
}

// Função para recalcular valor total do pacote
function recalcularValorPacote($pdo, $id_pacote, $tipo) {
    if ($tipo === 'receita') {
        $stmt = $pdo->prepare("
            UPDATE PacotesReceita 
            SET valor_total_calculado = (
                SELECT COALESCE(SUM(pri.quantidade * ci.valor_unitario), 0)
                FROM PacoteReceitaItens pri
                JOIN CatalogoItens ci ON pri.id_item_catalogo = ci.id
                WHERE pri.id_pacote_receita = ?
            )
            WHERE id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            UPDATE PacotesDespesa 
            SET valor_total_calculado = (
                SELECT COALESCE(SUM(pdi.quantidade * ci.valor_unitario), 0)
                FROM PacoteDespesaItens pdi
                JOIN CatalogoItens ci ON pdi.id_item_catalogo = ci.id
                WHERE pdi.id_pacote_despesa = ?
            )
            WHERE id = ?
        ");
    }
    $stmt->execute([$id_pacote, $id_pacote]);
}

// Função para regenerar lançamentos mensais
function regenerarLancamentosMensais($pdo, $id_pacote_receita_item) {
    // Buscar dados do item
    $stmt = $pdo->prepare("
        SELECT pri.*, ci.valor_unitario, pr.ano
        FROM PacoteReceitaItens pri
        JOIN CatalogoItens ci ON pri.id_item_catalogo = ci.id
        JOIN PacotesReceita pr ON pri.id_pacote_receita = pr.id
        WHERE pri.id = ?
    ");
    $stmt->execute([$id_pacote_receita_item]);
    $item = $stmt->fetch();
    
    if (!$item) return;
    
    // Deletar lançamentos existentes
    $stmt = $pdo->prepare("DELETE FROM LancamentosMensais WHERE id_pacote_receita_item = ?");
    $stmt->execute([$id_pacote_receita_item]);
    
    // Criar novos lançamentos
    $valor_total = $item['quantidade'] * $item['valor_unitario'];
    $stmt_lancamento = $pdo->prepare('INSERT INTO LancamentosMensais (id_pacote_receita_item, ano, mes, valor_mes) VALUES (?, ?, ?, ?)');
    
    if ($item['tipo_distribuicao'] === 'Anual') {
        $stmt_lancamento->execute([
            $id_pacote_receita_item,
            $item['ano'],
            $item['mes_alocacao_anual'],
            $valor_total
        ]);
    } else {
        $mes_inicial = $item['mes_inicial_mensal'];
        $mes_final = $item['mes_final_mensal'];
        $num_meses = $mes_final - $mes_inicial + 1;
        $valor_mensal = $valor_total / $num_meses;
        
        for ($mes = $mes_inicial; $mes <= $mes_final; $mes++) {
            $stmt_lancamento->execute([
                $id_pacote_receita_item,
                $item['ano'],
                $mes,
                $valor_mensal
            ]);
        }
    }
}

// Função para regenerar lançamentos mensais para despesas
function regenerarLancamentosMensaisDespesa($pdo, $id_pacote_despesa_item) {
    // Obter dados do item
    $stmt = $pdo->prepare('
        SELECT pdi.*, ci.valor_unitario, ci.grupo, ci.elemento 
        FROM PacoteDespesaItens pdi
        JOIN CatalogoItens ci ON pdi.id_item_catalogo = ci.id
        WHERE pdi.id = ?
    ');
    $stmt->execute([$id_pacote_despesa_item]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) return;
    
    // Limpar lançamentos existentes
    $stmt = $pdo->prepare('DELETE FROM LancamentosMensais WHERE id_pacote_despesa_item = ?');
    $stmt->execute([$id_pacote_despesa_item]);
    
    // Preparar statement para inserir lançamentos
    $stmt_lancamento = $pdo->prepare('
        INSERT INTO LancamentosMensais (id_pacote_despesa_item, ano, mes, valor_mensal)
        VALUES (?, ?, ?, ?)
    ');
    
    $valor_total = $item['valor_unitario'] * $item['quantidade'];
    $ano_atual = date('Y');
    $item['ano'] = $ano_atual; // Assumir ano atual
    
    // Definir distribuição baseada no tipo
    if ($item['tipo_distribuicao'] === 'Personalizada') {
        // Buscar distribuição personalizada
        $stmt_dist = $pdo->prepare('SELECT mes, valor_mensal FROM DistribuicaoMensal WHERE id_item_despesa = ?');
        $stmt_dist->execute([$id_pacote_despesa_item]);
        $distribuicoes = $stmt_dist->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($distribuicoes as $dist) {
            $stmt_lancamento->execute([
                $id_pacote_despesa_item,
                $item['ano'],
                $dist['mes'],
                $dist['valor_mensal']
            ]);
        }
    } elseif ($item['tipo_distribuicao'] === 'Anual') {
        // Distribuição anual - todo valor em um mês específico
        $mes_alocacao = $item['mes_alocacao_anual'] ?: 1;
        $stmt_lancamento->execute([
            $id_pacote_despesa_item,
            $item['ano'],
            $mes_alocacao,
            $valor_total
        ]);
    } else {
        // Distribuição mensal padrão (de janeiro a dezembro ou período específico)
        $mes_inicial = $item['mes_inicial_mensal'] ?: 1;
        $mes_final = $item['mes_final_mensal'] ?: 12;
        $num_meses = $mes_final - $mes_inicial + 1;
        $valor_mensal = $valor_total / $num_meses;
        
        for ($mes = $mes_inicial; $mes <= $mes_final; $mes++) {
            $stmt_lancamento->execute([
                $id_pacote_despesa_item,
                $item['ano'],
                $mes,
                $valor_mensal
            ]);
        }
    }
}

// Função para feedback visual
function feedback($msg, $type = 'success') {
    $_SESSION['feedback'] = "<div id='feedback' class='feedback $type'>$msg</div>";
}

// Função para formatar valores monetários
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para obter nome do mês
function getNomeMes($mes) {
    $meses = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    ];
    return $meses[$mes] ?? '';
}

// Processamento de exclusões (via GET)
// Deletar Pacote de Receita
if (isset($_GET['delete_receita'])) {
    $id = intval($_GET['delete_receita']);
    $stmt = $pdo->prepare('DELETE FROM PacotesReceita WHERE id = ?');
    $stmt->execute([$id]);
    feedback('Pacote de receita deletado com sucesso!');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Deletar Pacote de Despesa
if (isset($_GET['delete_despesa'])) {
    $id = intval($_GET['delete_despesa']);
    $stmt = $pdo->prepare('DELETE FROM PacotesDespesa WHERE id = ?');
    $stmt->execute([$id]);
    feedback('Pacote de despesa deletado com sucesso!');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Deletar Item do Pacote
if (isset($_GET['delete_item']) && isset($_GET['view']) && isset($_GET['id'])) {
    $item_id = intval($_GET['delete_item']);
    $tipo_view = $_GET['view'];
    $pacote_id = intval($_GET['id']);
    
    if ($tipo_view === 'receita') {
        $stmt = $pdo->prepare('DELETE FROM PacoteReceitaItens WHERE id = ?');
        $stmt->execute([$item_id]);
        // Recalcular valor total do pacote
        recalcularValorPacote($pdo, $pacote_id, 'receita');
        feedback('Item removido do pacote de receita com sucesso!');
    } else {
        $stmt = $pdo->prepare('DELETE FROM PacoteDespesaItens WHERE id = ?');
        $stmt->execute([$item_id]);
        // Recalcular valor total do pacote
        recalcularValorPacote($pdo, $pacote_id, 'despesa');
        feedback('Item removido do pacote de despesa com sucesso!');
    }
    
    header('Location: ?view=' . $tipo_view . '&id=' . $pacote_id);
    exit;
}

// Processamento de formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Adicionar Pacote de Receita
        if (isset($_POST['add_pacote_receita'])) {
            $stmt = $pdo->prepare('INSERT INTO PacotesReceita (unidade_gerenciadora, data_criacao, documento_sei, descricao_pacote, ano) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $_POST['unidade_gerenciadora'],
                $_POST['data_criacao'],
                $_POST['documento_sei'] ?: null,
                $_POST['descricao_pacote'] ?: null,
                $_POST['ano']
            ]);
            feedback('Pacote de receita criado com sucesso!');
        }
        
        // Editar Pacote de Receita
        if (isset($_POST['edit_pacote_receita'])) {
            $stmt = $pdo->prepare('UPDATE PacotesReceita SET unidade_gerenciadora = ?, data_criacao = ?, documento_sei = ?, descricao_pacote = ?, ano = ? WHERE id = ?');
            $stmt->execute([
                $_POST['unidade_gerenciadora'],
                $_POST['data_criacao'],
                $_POST['documento_sei'] ?: null,
                $_POST['descricao_pacote'] ?: null,
                $_POST['ano'],
                $_POST['id']
            ]);
            feedback('Pacote de receita atualizado com sucesso!');
        }
        
        // Adicionar Pacote de Despesa
        if (isset($_POST['add_pacote_despesa'])) {
            $stmt = $pdo->prepare('INSERT INTO PacotesDespesa (unidade_gerenciadora, data_criacao, documento_sei, descricao_pacote) VALUES (?, ?, ?, ?)');
            $stmt->execute([
                $_POST['unidade_gerenciadora'],
                $_POST['data_criacao'],
                $_POST['documento_sei'] ?: null,
                $_POST['descricao_pacote'] ?: null
            ]);
            feedback('Pacote de despesa criado com sucesso!');
        }
        
        // Editar Pacote de Despesa
        if (isset($_POST['edit_pacote_despesa'])) {
            $stmt = $pdo->prepare('UPDATE PacotesDespesa SET unidade_gerenciadora = ?, data_criacao = ?, documento_sei = ?, descricao_pacote = ? WHERE id = ?');
            $stmt->execute([
                $_POST['unidade_gerenciadora'],
                $_POST['data_criacao'],
                $_POST['documento_sei'] ?: null,
                $_POST['descricao_pacote'] ?: null,
                $_POST['id']
            ]);
            feedback('Pacote de despesa atualizado com sucesso!');
        }
        
        // Editar item de receita
        if (isset($_POST['edit_item_receita'])) {
            $pdo->beginTransaction();
            
            try {
                // Obter dados do item atual
                $stmt = $pdo->prepare('SELECT * FROM PacoteReceitaItens WHERE id = ?');
                $stmt->execute([$_POST['item_id']]);
                $item_atual = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$item_atual) {
                    throw new Exception('Item não encontrado');
                }
                
                // Determinar tipo de distribuição e valores
                $tipo_distribuicao = $_POST['tipo_distribuicao'];
                $mes_alocacao_anual = null;
                $mes_inicial_mensal = null;
                $mes_final_mensal = null;
                
                if ($tipo_distribuicao === 'Anual') {
                    $mes_alocacao_anual = $_POST['mes_alocacao_anual'] ?? 1;
                } elseif ($tipo_distribuicao === 'Mensal') {
                    $mes_inicial_mensal = $_POST['mes_inicial_mensal'] ?? 1;
                    $mes_final_mensal = $_POST['mes_final_mensal'] ?? 12;
                }
                
                // Atualizar valor unitário no catálogo se fornecido
                if (isset($_POST['valor_unitario']) && !empty($_POST['valor_unitario'])) {
                    $stmt = $pdo->prepare('UPDATE CatalogoItens SET valor_unitario = ? WHERE id = (SELECT id_item_catalogo FROM PacoteReceitaItens WHERE id = ?)');
                    $stmt->execute([$_POST['valor_unitario'], $_POST['item_id']]);
                }
                
                // Atualizar item
                $stmt = $pdo->prepare('UPDATE PacoteReceitaItens SET quantidade = ?, tipo_distribuicao = ?, mes_alocacao_anual = ?, mes_inicial_mensal = ?, mes_final_mensal = ? WHERE id = ?');
                $stmt->execute([
                    $_POST['quantidade'],
                    $tipo_distribuicao,
                    $mes_alocacao_anual,
                    $mes_inicial_mensal,
                    $mes_final_mensal,
                    $_POST['item_id']
                ]);
                
                // Se tiver distribuição personalizada, processar os valores mensais
                if ($tipo_distribuicao === 'Personalizada') {
                    // Excluir distribuições existentes
                    $stmt = $pdo->prepare('DELETE FROM DistribuicaoMensal WHERE id_item_receita = ?');
                    $stmt->execute([$_POST['item_id']]);
                    
                    // Inserir novas distribuições
                    for ($mes = 1; $mes <= 12; $mes++) {
                        $valor_mes = $_POST["distribuicao_mes_$mes"] ?? 0;
                        if ($valor_mes > 0) {
                            $stmt = $pdo->prepare('INSERT INTO DistribuicaoMensal (id_item_receita, mes, valor_mensal) VALUES (?, ?, ?)');
                            $stmt->execute([$_POST['item_id'], $mes, $valor_mes]);
                        }
                    }
                }
                
                // Recalcular valor total do pacote
                recalcularValorPacote($pdo, $_POST['pacote_id'], 'receita');
                
                // Regenerar lançamentos mensais
                regenerarLancamentosMensais($pdo, $_POST['item_id']);
                
                $pdo->commit();
                feedback('Item de receita atualizado com sucesso!');
                
            } catch (Exception $e) {
                $pdo->rollBack();
                feedback('Erro ao atualizar item de receita: ' . $e->getMessage(), 'error');
            }
        }
        
        // Editar item de despesa
        if (isset($_POST['edit_item_despesa'])) {
            $pdo->beginTransaction();
            
            try {
                // Obter dados do item atual
                $stmt = $pdo->prepare('SELECT * FROM PacoteDespesaItens WHERE id = ?');
                $stmt->execute([$_POST['item_id']]);
                $item_atual = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$item_atual) {
                    throw new Exception('Item não encontrado');
                }
                
                // Determinar tipo de distribuição e valores
                $tipo_distribuicao = $_POST['tipo_distribuicao'];
                $mes_alocacao_anual = null;
                $mes_inicial_mensal = null;
                $mes_final_mensal = null;
                
                if ($tipo_distribuicao === 'Anual') {
                    $mes_alocacao_anual = $_POST['mes_alocacao_anual'] ?? 1;
                } elseif ($tipo_distribuicao === 'Mensal') {
                    $mes_inicial_mensal = $_POST['mes_inicial_mensal'] ?? 1;
                    $mes_final_mensal = $_POST['mes_final_mensal'] ?? 12;
                }
                
                // Atualizar valor unitário no catálogo se fornecido
                if (isset($_POST['valor_unitario']) && !empty($_POST['valor_unitario'])) {
                    $stmt = $pdo->prepare('UPDATE CatalogoItens SET valor_unitario = ? WHERE id = (SELECT id_item_catalogo FROM PacoteDespesaItens WHERE id = ?)');
                    $stmt->execute([$_POST['valor_unitario'], $_POST['item_id']]);
                }
                
                // Atualizar item
                $stmt = $pdo->prepare('UPDATE PacoteDespesaItens SET quantidade = ?, tipo_distribuicao = ?, mes_alocacao_anual = ?, mes_inicial_mensal = ?, mes_final_mensal = ? WHERE id = ?');
                $stmt->execute([
                    $_POST['quantidade'],
                    $tipo_distribuicao,
                    $mes_alocacao_anual,
                    $mes_inicial_mensal,
                    $mes_final_mensal,
                    $_POST['item_id']
                ]);
                
                // Se tiver distribuição personalizada, processar os valores mensais
                if ($tipo_distribuicao === 'Personalizada') {
                    // Excluir distribuições existentes
                    $stmt = $pdo->prepare('DELETE FROM DistribuicaoMensal WHERE id_item_despesa = ?');
                    $stmt->execute([$_POST['item_id']]);
                    
                    // Inserir novas distribuições
                    for ($mes = 1; $mes <= 12; $mes++) {
                        $valor_mes = $_POST["distribuicao_mes_$mes"] ?? 0;
                        if ($valor_mes > 0) {
                            $stmt = $pdo->prepare('INSERT INTO DistribuicaoMensal (id_item_despesa, mes, valor_mensal) VALUES (?, ?, ?)');
                            $stmt->execute([$_POST['item_id'], $mes, $valor_mes]);
                        }
                    }
                }
                
                // Recalcular valor total do pacote
                recalcularValorPacote($pdo, $_POST['pacote_id'], 'despesa');
                
                // Regenerar lançamentos mensais para despesa
                regenerarLancamentosMensaisDespesa($pdo, $_POST['item_id']);
                
                $pdo->commit();
                feedback('Item de despesa atualizado com sucesso!');
                
            } catch (Exception $e) {
                $pdo->rollBack();
                feedback('Erro ao atualizar item de despesa: ' . $e->getMessage(), 'error');
            }
        }
        
        // Adicionar item do catálogo ao pacote de receita
        if (isset($_POST['add_item_receita_pacote'])) {
            $pdo->beginTransaction();
            
            try {
                // Inserir item no pacote
                $stmt = $pdo->prepare('INSERT INTO PacoteReceitaItens (id_pacote_receita, id_item_catalogo, quantidade, tipo_distribuicao, mes_alocacao_anual, mes_inicial_mensal, mes_final_mensal) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $_POST['id_pacote_receita'],
                    $_POST['id_item_catalogo'],
                    $_POST['quantidade'],
                    $_POST['tipo_distribuicao'],
                    $_POST['tipo_distribuicao'] === 'Anual' ? $_POST['mes_alocacao_anual'] : null,
                    $_POST['tipo_distribuicao'] === 'Mensal' ? $_POST['mes_inicial_mensal'] : null,
                    $_POST['tipo_distribuicao'] === 'Mensal' ? $_POST['mes_final_mensal'] : null
                ]);
                
                $item_id = $pdo->lastInsertId();
                
                // Recalcular valor total do pacote
                recalcularValorPacote($pdo, $_POST['id_pacote_receita'], 'receita');
                
                // Regenerar lançamentos mensais
                regenerarLancamentosMensais($pdo, $item_id);
                
                $pdo->commit();
                feedback('Item adicionado ao pacote de receita com sucesso!');
            } catch (Exception $e) {
                $pdo->rollBack();
                feedback('Erro ao adicionar item ao pacote: ' . $e->getMessage(), 'error');
            }
        }
        
        // Adicionar item do catálogo ao pacote de despesa
        if (isset($_POST['add_item_despesa_pacote'])) {
            $pdo->beginTransaction();
            
            try {
                // Inserir item no pacote
                $stmt = $pdo->prepare('INSERT INTO PacoteDespesaItens (id_pacote_despesa, id_item_catalogo, quantidade, tipo_distribuicao, mes_alocacao_anual, mes_inicial_mensal, mes_final_mensal) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $_POST['id_pacote_despesa'],
                    $_POST['id_item_catalogo'],
                    $_POST['quantidade'],
                    $_POST['tipo_distribuicao'] ?? 'Mensal',
                    $_POST['tipo_distribuicao'] === 'Anual' ? $_POST['mes_alocacao_anual'] : null,
                    $_POST['tipo_distribuicao'] === 'Mensal' ? $_POST['mes_inicial_mensal'] : null,
                    $_POST['tipo_distribuicao'] === 'Mensal' ? $_POST['mes_final_mensal'] : null
                ]);
                
                // Recalcular valor total do pacote
                recalcularValorPacote($pdo, $_POST['id_pacote_despesa'], 'despesa');
                
                $pdo->commit();
                feedback('Item adicionado ao pacote de despesa com sucesso!');
            } catch (Exception $e) {
                $pdo->rollBack();
                feedback('Erro ao adicionar item ao pacote: ' . $e->getMessage(), 'error');
            }
        }
        
    } catch (PDOException $e) {
        feedback('Erro ao processar formulário: ' . $e->getMessage(), 'error');
    }
    
    // Redirect para evitar reenvio de formulário
    header('Location: ' . $_SERVER['PHP_SELF'] . (isset($_GET['view']) ? '?view=' . $_GET['view'] . '&id=' . $_GET['id'] : ''));
    exit;
}

// Buscar dados para os formulários
$pacotes_receita = $pdo->query('SELECT * FROM PacotesReceita ORDER BY ano DESC, data_criacao DESC')->fetchAll();
$pacotes_despesa = $pdo->query('SELECT * FROM PacotesDespesa ORDER BY data_criacao DESC')->fetchAll();

// Buscar itens do catálogo
$itens_catalogo_receita = $pdo->query("SELECT * FROM CatalogoItens WHERE tipo = 'Receita' ORDER BY descricao_padrao")->fetchAll();
$itens_catalogo_despesa = $pdo->query("SELECT * FROM CatalogoItens WHERE tipo = 'Despesa' ORDER BY descricao_padrao")->fetchAll();

// Verificar se estamos visualizando um pacote específico
$visualizar_pacote = null;
$itens_pacote = [];
if (isset($_GET['view']) && isset($_GET['id'])) {
    $tipo_view = $_GET['view'];
    $id_pacote = $_GET['id'];
    
    if ($tipo_view === 'receita') {
        $stmt = $pdo->prepare('SELECT * FROM PacotesReceita WHERE id = ?');
        $stmt->execute([$id_pacote]);
        $visualizar_pacote = $stmt->fetch();
        
        if ($visualizar_pacote) {
            $stmt = $pdo->prepare('
                SELECT pri.*, ci.descricao_padrao, ci.valor_unitario, ci.acao, ci.grupo, ci.elemento_item,
                       (pri.quantidade * ci.valor_unitario) as valor_total_item
                FROM PacoteReceitaItens pri
                JOIN CatalogoItens ci ON pri.id_item_catalogo = ci.id
                WHERE pri.id_pacote_receita = ?
            ');
            $stmt->execute([$id_pacote]);
            $itens_pacote = $stmt->fetchAll();
        }
    } elseif ($tipo_view === 'despesa') {
        $stmt = $pdo->prepare('SELECT * FROM PacotesDespesa WHERE id = ?');
        $stmt->execute([$id_pacote]);
        $visualizar_pacote = $stmt->fetch();
        
        if ($visualizar_pacote) {
            $stmt = $pdo->prepare('
                SELECT pdi.*, ci.descricao_padrao, ci.valor_unitario, ci.grupo, ci.elemento_item,
                       (pdi.quantidade * ci.valor_unitario) as valor_total_item
                FROM PacoteDespesaItens pdi
                JOIN CatalogoItens ci ON pdi.id_item_catalogo = ci.id
                WHERE pdi.id_pacote_despesa = ?
            ');
            $stmt->execute([$id_pacote]);
            $itens_pacote = $stmt->fetchAll();
        }
    }
}

// Relatório de controle orçamentário com a nova query
$relatorio_query = "
    SELECT
        COALESCE(r.ano, d.ano) AS ano,
        COALESCE(r.mes, d.mes) AS mes,
        COALESCE(r.grupo, d.grupo) AS grupo,
        COALESCE(r.elemento_item, d.elemento_item) AS elemento_item,
        COALESCE(r.total_receita, 0) AS receita,
        COALESCE(d.total_despesa, 0) AS despesa,
        (COALESCE(r.total_receita, 0) - COALESCE(d.total_despesa, 0)) AS saldo
    FROM
        (
            SELECT lm.ano, lm.mes, ci.grupo, ci.elemento_item, SUM(lm.valor_mes) as total_receita
            FROM LancamentosMensais lm
            JOIN PacoteReceitaItens pri ON lm.id_pacote_receita_item = pri.id
            JOIN CatalogoItens ci ON pri.id_item_catalogo = ci.id
            GROUP BY lm.ano, lm.mes, ci.grupo, ci.elemento_item
        ) AS r
    LEFT JOIN
        (
            SELECT YEAR(pd.data_criacao) as ano, MONTH(pd.data_criacao) as mes, ci.grupo, ci.elemento_item, SUM(pdi.quantidade * ci.valor_unitario) as total_despesa
            FROM PacoteDespesaItens pdi
            JOIN PacotesDespesa pd ON pdi.id_pacote_despesa = pd.id
            JOIN CatalogoItens ci ON pdi.id_item_catalogo = ci.id
            GROUP BY YEAR(pd.data_criacao), MONTH(pd.data_criacao), ci.grupo, ci.elemento_item
        ) AS d ON r.ano = d.ano AND r.mes = d.mes AND r.grupo = d.grupo AND r.elemento_item = d.elemento_item
    UNION
    SELECT
        COALESCE(r.ano, d.ano) AS ano,
        COALESCE(r.mes, d.mes) AS mes,
        COALESCE(r.grupo, d.grupo) AS grupo,
        COALESCE(r.elemento_item, d.elemento_item) AS elemento_item,
        COALESCE(r.total_receita, 0) AS receita,
        COALESCE(d.total_despesa, 0) AS despesa,
        (COALESCE(r.total_receita, 0) - COALESCE(d.total_despesa, 0)) AS saldo
    FROM
        (
            SELECT lm.ano, lm.mes, ci.grupo, ci.elemento_item, SUM(lm.valor_mes) as total_receita
            FROM LancamentosMensais lm
            JOIN PacoteReceitaItens pri ON lm.id_pacote_receita_item = pri.id
            JOIN CatalogoItens ci ON pri.id_item_catalogo = ci.id
            GROUP BY lm.ano, lm.mes, ci.grupo, ci.elemento_item
        ) AS r
    RIGHT JOIN
        (
            SELECT YEAR(pd.data_criacao) as ano, MONTH(pd.data_criacao) as mes, ci.grupo, ci.elemento_item, SUM(pdi.quantidade * ci.valor_unitario) as total_despesa
            FROM PacoteDespesaItens pdi
            JOIN PacotesDespesa pd ON pdi.id_pacote_despesa = pd.id
            JOIN CatalogoItens ci ON pdi.id_item_catalogo = ci.id
            GROUP BY YEAR(pd.data_criacao), MONTH(pd.data_criacao), ci.grupo, ci.elemento_item
        ) AS d ON r.ano = d.ano AND r.mes = d.mes AND r.grupo = d.grupo AND r.elemento_item = d.elemento_item
    ORDER BY ano DESC, mes ASC, grupo ASC, elemento_item ASC
";

$relatorio_dados = $pdo->query($relatorio_query)->fetchAll();

ob_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão Orçamentária - SDTS3</title>
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
        
        textarea {
            height: 80px;
            resize: vertical;
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
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(35, 41, 70, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
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
        
        .positive {
            color: #22c55e;
            font-weight: 600;
        }
        
        .negative {
            color: #ef4444;
            font-weight: 600;
        }
        
        .zero {
            color: #6b7280;
            font-weight: 600;
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
        
        .conditional-fields {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .conditional-fields.show {
            display: block;
        }
        
        /* Campos condicionais dos modais de edição */
        #edit-campo-anual-receita,
        #edit-campos-mensal-receita,
        #edit-campo-anual-despesa,
        #edit-campos-mensal-despesa {
            display: none;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(45deg, #232946, #4a5568);
            color: #fff;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Novos estilos para a arquitetura baseada em catálogo */
        .package-details {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            background: #fff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .package-info h3 {
            color: #232946;
            margin: 0 0 1rem 0;
            font-size: 1.5rem;
        }
        
        .package-info p {
            margin: 0.5rem 0;
            color: #4a5568;
        }
        
        .total-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #22c55e;
        }
        
        .package-actions {
            display: flex;
            gap: 1rem;
            flex-direction: column;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #232946;
            border: 1px solid #ddd;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            display: inline-block;
            text-decoration: none;
            margin-top: 0.5rem;
        }
        
        .package-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .package-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .package-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .package-header h4 {
            margin: 0;
            color: #232946;
            font-size: 1.1rem;
        }
        
        .package-value {
            font-weight: 700;
            color: #22c55e;
            font-size: 1.1rem;
        }
        
        .package-actions-inline {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .btn-edit {
            background: linear-gradient(45deg, #f59e0b, #d97706);
            color: #fff;
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
            cursor: pointer;
            z-index: 10;
            position: relative;
        }
        
        .btn-edit:hover {
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }
        
        .btn-delete {
            background: linear-gradient(45deg, #ef4444, #dc2626);
            color: #fff;
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
            cursor: pointer;
            z-index: 10;
            position: relative;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .btn-delete:hover {
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            color: #fff;
            text-decoration: none;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .item-info {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: #f8fafc;
            border-radius: 4px;
            display: none;
        }
        
        .catalog-link {
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
        
        .catalog-link:hover {
            background: #2d3748;
            color: #fff;
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
        }
        
        .btn-modal-trigger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
        }
        
        .btn-add-item {
            background: linear-gradient(45deg, #3b82f6, #2563eb);
        }
        
        .btn-add-item:hover {
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
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
        <a href="gerenciar_catalogo.php" class="catalog-link">🗂️ Gerenciar Catálogo</a>
        
        <div class="header">
            <h1>Gestão Orçamentária</h1>
            <p>Sistema Integrado de Controle de Receitas e Despesas</p>
        </div>
        
        <?php if (isset($_SESSION['feedback'])): ?>
            <?php echo $_SESSION['feedback']; unset($_SESSION['feedback']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['migration_notice'])): ?>
            <div class="feedback" style="background: #fef3c7; color: #92400e; border-left-color: #f59e0b;">
                ⚠️ <?php echo $_SESSION['migration_notice']; unset($_SESSION['migration_notice']); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($visualizar_pacote): ?>
            <!-- Visualização detalhada de um pacote -->
            <div class="section">
                <h2>� <?php echo $tipo_view === 'receita' ? 'Pacote de Receita' : 'Pacote de Despesa'; ?></h2>
                
                <div class="package-details">
                    <div class="package-info">
                        <h3><?php echo htmlspecialchars($visualizar_pacote['unidade_gerenciadora']); ?></h3>
                        <p><strong>Data de Criação:</strong> <?php echo date('d/m/Y', strtotime($visualizar_pacote['data_criacao'])); ?></p>
                        <?php if ($visualizar_pacote['documento_sei']): ?>
                            <p><strong>Documento SEI:</strong> <?php echo htmlspecialchars($visualizar_pacote['documento_sei']); ?></p>
                        <?php endif; ?>
                        <?php if ($visualizar_pacote['descricao_pacote']): ?>
                            <p><strong>Descrição:</strong> <?php echo htmlspecialchars($visualizar_pacote['descricao_pacote']); ?></p>
                        <?php endif; ?>
                        <?php if ($tipo_view === 'receita'): ?>
                            <p><strong>Ano:</strong> <?php echo $visualizar_pacote['ano']; ?></p>
                        <?php endif; ?>
                        <p><strong>Valor Total:</strong> <span class="total-value"><?php echo formatarMoeda($visualizar_pacote['valor_total_calculado']); ?></span></p>
                    </div>
                    
                    <div class="package-actions">
                        <button class="btn-add-item btn-modal-trigger" onclick="openModal('modal-add-item')">➕ Adicionar Item</button>
                        <a href="?" class="btn btn-secondary">⬅️ Voltar</a>
                    </div>
                </div>
                
                <?php if (!empty($itens_pacote)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Item do Catálogo</th>
                                <th>Valor Unitário</th>
                                <th>Quantidade</th>
                                <th>Valor Total</th>
                                <?php if ($tipo_view === 'receita'): ?>
                                    <th>Distribuição</th>
                                <?php endif; ?>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens_pacote as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['descricao_padrao']); ?></td>
                                    <td><?php echo formatarMoeda($item['valor_unitario']); ?></td>
                                    <td><?php echo $item['quantidade']; ?></td>
                                    <td><?php echo formatarMoeda($item['valor_total_item']); ?></td>
                                    <?php if ($tipo_view === 'receita'): ?>
                                        <td>
                                            <?php if ($item['tipo_distribuicao'] === 'Anual'): ?>
                                                Anual - <?php echo getNomeMes($item['mes_alocacao_anual']); ?>
                                            <?php else: ?>
                                                Mensal - <?php echo getNomeMes($item['mes_inicial_mensal']) . ' a ' . getNomeMes($item['mes_final_mensal']); ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <button class="btn btn-small btn-edit btn-edit-item-<?php echo $tipo_view; ?>" 
                                                data-item-id="<?php echo $item['id']; ?>"
                                                data-pacote-id="<?php echo $_GET['id']; ?>"
                                                data-catalogo-id="<?php echo $item['id_item_catalogo']; ?>"
                                                data-quantidade="<?php echo $item['quantidade']; ?>"
                                                data-valor-unitario="<?php echo $item['valor_unitario']; ?>"
                                                <?php if ($tipo_view === 'receita'): ?>
                                                data-acao="<?php echo $item['acao'] ?? ''; ?>"
                                                data-grupo="<?php echo $item['grupo']; ?>"
                                                data-elemento="<?php echo $item['elemento_item']; ?>"
                                                data-tipo-distribuicao="<?php echo $item['tipo_distribuicao'] ?? 'Mensal'; ?>"
                                                data-mes-alocacao="<?php echo $item['mes_alocacao_anual'] ?? ''; ?>"
                                                data-mes-inicial="<?php echo $item['mes_inicial_mensal'] ?? ''; ?>"
                                                data-mes-final="<?php echo $item['mes_final_mensal'] ?? ''; ?>"
                                                <?php else: ?>
                                                data-grupo="<?php echo $item['grupo']; ?>"
                                                data-elemento="<?php echo $item['elemento_item']; ?>"
                                                data-tipo-distribuicao="<?php echo $item['tipo_distribuicao'] ?? 'Mensal'; ?>"
                                                data-mes-alocacao="<?php echo $item['mes_alocacao_anual'] ?? ''; ?>"
                                                data-mes-inicial="<?php echo $item['mes_inicial_mensal'] ?? ''; ?>"
                                                data-mes-final="<?php echo $item['mes_final_mensal'] ?? ''; ?>"
                                                <?php endif; ?>
                                                title="Editar Item"><i class="fa fa-pen"></i></button>
                                        <a href="?view=<?php echo $tipo_view; ?>&id=<?php echo $_GET['id']; ?>&delete_item=<?php echo $item['id']; ?>" 
                                           class="btn btn-small btn-delete" 
                                           title="Excluir Item" 
                                           onclick="return confirm('Tem certeza que deseja excluir este item do pacote?')"><i class="fa fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="feedback" style="background: #f3f4f6; color: #6b7280; border-left-color: #9ca3af;">
                        ℹ️ Nenhum item adicionado ao pacote ainda. Clique em "Adicionar Item" para começar.
                    </div>
                <?php endif; ?>
                
                <!-- Modal para adicionar item ao pacote -->
                <div id="modal-add-item" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title">Adicionar Item do Catálogo</h3>
                            <button class="close-modal" onclick="closeModal('modal-add-item')">&times;</button>
                        </div>
                        
                        <form method="post">
                            <input type="hidden" name="<?php echo $tipo_view === 'receita' ? 'add_item_receita_pacote' : 'add_item_despesa_pacote'; ?>" value="1">
                            <input type="hidden" name="<?php echo $tipo_view === 'receita' ? 'id_pacote_receita' : 'id_pacote_despesa'; ?>" value="<?php echo $visualizar_pacote['id']; ?>">
                            
                            <div class="form-group">
                                <label>Item do Catálogo</label>
                                <select name="id_item_catalogo" required onchange="updateItemInfo(this)">
                                    <option value="">Selecione um item...</option>
                                    <?php 
                                    $itens_disponiveis = $tipo_view === 'receita' ? $itens_catalogo_receita : $itens_catalogo_despesa;
                                    foreach ($itens_disponiveis as $item): 
                                    ?>
                                        <option value="<?php echo $item['id']; ?>" 
                                                data-valor="<?php echo $item['valor_unitario']; ?>">
                                            <?php echo htmlspecialchars($item['descricao_padrao'] . ' - ' . formatarMoeda($item['valor_unitario'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Quantidade</label>
                                <input type="number" name="quantidade" required min="1" oninput="updateTotal()">
                                <div id="item-info" class="item-info"></div>
                            </div>
                            
                            <?php if ($tipo_view === 'receita'): ?>
                                <div class="form-group">
                                    <label>Tipo de Distribuição</label>
                                    <select name="tipo_distribuicao" required onchange="toggleDistribuicaoFields(this)">
                                        <option value="">Selecione...</option>
                                        <option value="Anual">Anual</option>
                                        <option value="Mensal">Mensal</option>
                                    </select>
                                </div>
                                
                                <div id="campo_anual" class="conditional-fields">
                                    <div class="form-group">
                                        <label>Mês de Alocação</label>
                                        <select name="mes_alocacao_anual">
                                            <option value="">Selecione...</option>
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?php echo $i; ?>"><?php echo getNomeMes($i); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div id="campos_mensal" class="conditional-fields">
                                    <div class="form-group">
                                        <label>Mês Inicial</label>
                                        <select name="mes_inicial_mensal">
                                            <option value="">Selecione...</option>
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?php echo $i; ?>"><?php echo getNomeMes($i); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Mês Final</label>
                                        <select name="mes_final_mensal">
                                            <option value="">Selecione...</option>
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?php echo $i; ?>"><?php echo getNomeMes($i); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="modal-actions">
                                <button type="button" class="btn btn-cancel" onclick="closeModal('modal-add-item')">Cancelar</button>
                                <button type="submit" class="btn">Adicionar Item</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal para editar item de receita -->
            <div id="modal-edit-item-receita" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>✏️ Editar Item de Receita</h3>
                        <button class="close-modal" onclick="closeModal('modal-edit-item-receita')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="gestao_orcamentaria.php">
                            <input type="hidden" name="edit_item_receita" value="1">
                            <input type="hidden" name="item_id" id="edit-item-receita-id">
                            <input type="hidden" name="pacote_id" id="edit-item-receita-pacote-id">
                            
                            <div class="form-group">
                                <label for="edit-item-receita-acao">Ação.Grupo.Elemento:</label>
                                <input type="text" id="edit-item-receita-acao" readonly class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="edit-item-receita-valor">Valor Unitário (R$):</label>
                                <input type="number" 
                                       id="edit-item-receita-valor" 
                                       name="valor_unitario" 
                                       step="0.01" 
                                       min="0" 
                                       class="form-control" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="edit-item-receita-quantidade">Quantidade:</label>
                                <input type="number" 
                                       id="edit-item-receita-quantidade" 
                                       name="quantidade" 
                                       min="1" 
                                       class="form-control" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="edit-item-receita-distribuicao">Tipo de Distribuição:</label>
                                <select id="edit-item-receita-distribuicao" name="tipo_distribuicao" class="form-control" required onchange="toggleEditDistribuicaoFieldsReceita(this)">
                                    <option value="">Selecione...</option>
                                    <option value="Anual">Anual</option>
                                    <option value="Mensal">Mensal</option>
                                </select>
                            </div>

                            <div id="edit-campo-anual-receita" class="conditional-fields">
                                <div class="form-group">
                                    <label for="edit-mes-alocacao-receita">Mês de Alocação:</label>
                                    <select id="edit-mes-alocacao-receita" name="mes_alocacao_anual" class="form-control">
                                        <option value="1">Janeiro</option>
                                        <option value="2">Fevereiro</option>
                                        <option value="3">Março</option>
                                        <option value="4">Abril</option>
                                        <option value="5">Maio</option>
                                        <option value="6">Junho</option>
                                        <option value="7">Julho</option>
                                        <option value="8">Agosto</option>
                                        <option value="9">Setembro</option>
                                        <option value="10">Outubro</option>
                                        <option value="11">Novembro</option>
                                        <option value="12">Dezembro</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="edit-campos-mensal-receita" class="conditional-fields">
                                <div class="form-group">
                                    <label for="edit-mes-inicial-receita">Mês Inicial:</label>
                                    <select id="edit-mes-inicial-receita" name="mes_inicial_mensal" class="form-control">
                                        <option value="1">Janeiro</option>
                                        <option value="2">Fevereiro</option>
                                        <option value="3">Março</option>
                                        <option value="4">Abril</option>
                                        <option value="5">Maio</option>
                                        <option value="6">Junho</option>
                                        <option value="7">Julho</option>
                                        <option value="8">Agosto</option>
                                        <option value="9">Setembro</option>
                                        <option value="10">Outubro</option>
                                        <option value="11">Novembro</option>
                                        <option value="12">Dezembro</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit-mes-final-receita">Mês Final:</label>
                                    <select id="edit-mes-final-receita" name="mes_final_mensal" class="form-control">
                                        <option value="1">Janeiro</option>
                                        <option value="2">Fevereiro</option>
                                        <option value="3">Março</option>
                                        <option value="4">Abril</option>
                                        <option value="5">Maio</option>
                                        <option value="6">Junho</option>
                                        <option value="7">Julho</option>
                                        <option value="8">Agosto</option>
                                        <option value="9">Setembro</option>
                                        <option value="10">Outubro</option>
                                        <option value="11">Novembro</option>
                                        <option value="12">Dezembro</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="button" class="btn btn-cancel" onclick="closeModal('modal-edit-item-receita')">Cancelar</button>
                                <button type="submit" class="btn">Salvar Alterações</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal para editar item de despesa -->
            <div id="modal-edit-item-despesa" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>✏️ Editar Item de Despesa</h3>
                        <button class="close-modal" onclick="closeModal('modal-edit-item-despesa')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="gestao_orcamentaria.php">
                            <input type="hidden" name="edit_item_despesa" value="1">
                            <input type="hidden" name="item_id" id="edit-item-despesa-id">
                            <input type="hidden" name="pacote_id" id="edit-item-despesa-pacote-id">
                            
                            <div class="form-group">
                                <label for="edit-item-despesa-grupo">Grupo.Elemento:</label>
                                <input type="text" id="edit-item-despesa-grupo" readonly class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="edit-item-despesa-valor">Valor Unitário (R$):</label>
                                <input type="number" 
                                       id="edit-item-despesa-valor" 
                                       name="valor_unitario" 
                                       step="0.01" 
                                       min="0" 
                                       class="form-control" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="edit-item-despesa-quantidade">Quantidade:</label>
                                <input type="number" 
                                       id="edit-item-despesa-quantidade" 
                                       name="quantidade" 
                                       min="1" 
                                       class="form-control" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="edit-item-despesa-distribuicao">Tipo de Distribuição:</label>
                                <select id="edit-item-despesa-distribuicao" name="tipo_distribuicao" class="form-control" required onchange="toggleEditDistribuicaoFieldsDespesa(this)">
                                    <option value="">Selecione...</option>
                                    <option value="Anual">Anual</option>
                                    <option value="Mensal">Mensal</option>
                                </select>
                            </div>

                            <div id="edit-campo-anual-despesa" class="conditional-fields">
                                <div class="form-group">
                                    <label for="edit-mes-alocacao-despesa">Mês de Alocação:</label>
                                    <select id="edit-mes-alocacao-despesa" name="mes_alocacao_anual" class="form-control">
                                        <option value="1">Janeiro</option>
                                        <option value="2">Fevereiro</option>
                                        <option value="3">Março</option>
                                        <option value="4">Abril</option>
                                        <option value="5">Maio</option>
                                        <option value="6">Junho</option>
                                        <option value="7">Julho</option>
                                        <option value="8">Agosto</option>
                                        <option value="9">Setembro</option>
                                        <option value="10">Outubro</option>
                                        <option value="11">Novembro</option>
                                        <option value="12">Dezembro</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="edit-campos-mensal-despesa" class="conditional-fields">
                                <div class="form-group">
                                    <label for="edit-mes-inicial-despesa">Mês Inicial:</label>
                                    <select id="edit-mes-inicial-despesa" name="mes_inicial_mensal" class="form-control">
                                        <option value="1">Janeiro</option>
                                        <option value="2">Fevereiro</option>
                                        <option value="3">Março</option>
                                        <option value="4">Abril</option>
                                        <option value="5">Maio</option>
                                        <option value="6">Junho</option>
                                        <option value="7">Julho</option>
                                        <option value="8">Agosto</option>
                                        <option value="9">Setembro</option>
                                        <option value="10">Outubro</option>
                                        <option value="11">Novembro</option>
                                        <option value="12">Dezembro</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit-mes-final-despesa">Mês Final:</label>
                                    <select id="edit-mes-final-despesa" name="mes_final_mensal" class="form-control">
                                        <option value="1">Janeiro</option>
                                        <option value="2">Fevereiro</option>
                                        <option value="3">Março</option>
                                        <option value="4">Abril</option>
                                        <option value="5">Maio</option>
                                        <option value="6">Junho</option>
                                        <option value="7">Julho</option>
                                        <option value="8">Agosto</option>
                                        <option value="9">Setembro</option>
                                        <option value="10">Outubro</option>
                                        <option value="11">Novembro</option>
                                        <option value="12">Dezembro</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="button" class="btn btn-cancel" onclick="closeModal('modal-edit-item-despesa')">Cancelar</button>
                                <button type="submit" class="btn">Salvar Alterações</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Visão principal com listagem de pacotes -->
            <div class="section">
                <h2>� Gerenciamento de Receitas</h2>
                
                <div style="margin-bottom: 2rem; text-align: center;">
                    <button class="btn-modal-trigger" onclick="openModal('modal-receita')">
                        ➕ Novo Pacote de Receita
                    </button>
                </div>
                
                <!-- Listagem de pacotes de receita -->
                <div class="form-card">
                    <h3>Pacotes de Receita Existentes</h3>
                    <?php if (!empty($pacotes_receita)): ?>
                        <div class="package-list">
                            <?php foreach ($pacotes_receita as $pacote): ?>
                                <div class="package-item">
                                    <div class="package-header">
                                        <h4><?php echo htmlspecialchars($pacote['unidade_gerenciadora']); ?></h4>
                                        <span class="package-value"><?php echo formatarMoeda($pacote['valor_total_calculado']); ?></span>
                                    </div>
                                    <p>Ano: <?php echo $pacote['ano']; ?> | Criado em: <?php echo date('d/m/Y', strtotime($pacote['data_criacao'])); ?></p>
                                    <div class="package-actions-inline">
                                        <a href="?view=receita&id=<?php echo $pacote['id']; ?>" class="btn btn-small" title="Ver Detalhes"><i class="fa fa-eye"></i></a>
                                        <button class="btn btn-small btn-edit btn-edit-receita" 
                                                data-id="<?php echo $pacote['id']; ?>"
                                                data-unidade="<?php echo htmlspecialchars($pacote['unidade_gerenciadora']); ?>"
                                                data-ano="<?php echo $pacote['ano']; ?>"
                                                data-data="<?php echo $pacote['data_criacao']; ?>"
                                                data-sei="<?php echo htmlspecialchars($pacote['documento_sei'] ?? ''); ?>"
                                                data-descricao="<?php echo htmlspecialchars($pacote['descricao_pacote'] ?? ''); ?>"
                                                title="Editar"><i class="fa fa-pen"></i></button>
                                        <a href="?delete_receita=<?php echo $pacote['id']; ?>" class="btn btn-small btn-delete" 
                                           title="Excluir" 
                                           onclick="return confirm('Tem certeza que deseja deletar este pacote de receita? Esta ação não pode ser desfeita.')"><i class="fa fa-trash"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Nenhum pacote de receita criado ainda.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Modal para criar pacote de receita -->
                <div id="modal-receita" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title">Novo Pacote de Receita</h3>
                            <button class="close-modal" onclick="closeModal('modal-receita')">&times;</button>
                        </div>
                        
                        <form method="post">
                            <div class="form-group">
                                <label>Unidade Gerenciadora</label>
                                <input type="text" name="unidade_gerenciadora" required maxlength="50">
                            </div>
                            
                            <div class="form-group">
                                <label>Ano</label>
                                <input type="number" name="ano" required min="2020" max="2030" value="<?php echo date('Y'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Data de Criação</label>
                                <input type="date" name="data_criacao" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Documento SEI</label>
                                <input type="text" name="documento_sei" maxlength="255">
                            </div>
                            
                            <div class="form-group">
                                <label>Descrição do Pacote</label>
                                <textarea name="descricao_pacote"></textarea>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="button" class="btn btn-cancel" onclick="closeModal('modal-receita')">Cancelar</button>
                                <button type="submit" name="add_pacote_receita" class="btn">Criar Pacote</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Modal para editar pacote de receita -->
                <div id="modal-edit-receita" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title">Editar Pacote de Receita</h3>
                            <button class="close-modal" onclick="closeModal('modal-edit-receita')">&times;</button>
                        </div>
                        
                        <form method="post">
                            <input type="hidden" name="edit_pacote_receita" value="1">
                            <input type="hidden" name="id" id="edit-receita-id" value="">
                            
                            <div class="form-group">
                                <label>Unidade Gerenciadora</label>
                                <input type="text" name="unidade_gerenciadora" id="edit-receita-unidade" required maxlength="50">
                            </div>
                            
                            <div class="form-group">
                                <label>Ano</label>
                                <input type="number" name="ano" id="edit-receita-ano" required min="2020" max="2030">
                            </div>
                            
                            <div class="form-group">
                                <label>Data de Criação</label>
                                <input type="date" name="data_criacao" id="edit-receita-data" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Documento SEI</label>
                                <input type="text" name="documento_sei" id="edit-receita-sei" maxlength="255">
                            </div>
                            
                            <div class="form-group">
                                <label>Descrição do Pacote</label>
                                <textarea name="descricao_pacote" id="edit-receita-descricao"></textarea>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="button" class="btn btn-cancel" onclick="closeModal('modal-edit-receita')">Cancelar</button>
                                <button type="submit" class="btn">Atualizar Pacote</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2>📉 Gerenciamento de Despesas</h2>
                
                <div style="margin-bottom: 2rem; text-align: center;">
                    <button class="btn-modal-trigger" onclick="openModal('modal-despesa')">
                        ➕ Novo Pacote de Despesa
                    </button>
                </div>
                
                <!-- Listagem de pacotes de despesa -->
                <div class="form-card">
                    <h3>Pacotes de Despesa Existentes</h3>
                    <?php if (!empty($pacotes_despesa)): ?>
                        <div class="package-list">
                            <?php foreach ($pacotes_despesa as $pacote): ?>
                                <div class="package-item">
                                    <div class="package-header">
                                        <h4><?php echo htmlspecialchars($pacote['unidade_gerenciadora']); ?></h4>
                                        <span class="package-value"><?php echo formatarMoeda($pacote['valor_total_calculado']); ?></span>
                                    </div>
                                    <p>Criado em: <?php echo date('d/m/Y', strtotime($pacote['data_criacao'])); ?></p>
                                    <div class="package-actions-inline">
                                        <a href="?view=despesa&id=<?php echo $pacote['id']; ?>" class="btn btn-small" title="Ver Detalhes"><i class="fa fa-eye"></i></a>
                                        <button class="btn btn-small btn-edit btn-edit-despesa" 
                                                data-id="<?php echo $pacote['id']; ?>"
                                                data-unidade="<?php echo htmlspecialchars($pacote['unidade_gerenciadora']); ?>"
                                                data-data="<?php echo $pacote['data_criacao']; ?>"
                                                data-sei="<?php echo htmlspecialchars($pacote['documento_sei'] ?? ''); ?>"
                                                data-descricao="<?php echo htmlspecialchars($pacote['descricao_pacote'] ?? ''); ?>"
                                                title="Editar"><i class="fa fa-pen"></i></button>
                                        <a href="?delete_despesa=<?php echo $pacote['id']; ?>" class="btn btn-small btn-delete" 
                                           title="Excluir" 
                                           onclick="return confirm('Tem certeza que deseja deletar este pacote de despesa? Esta ação não pode ser desfeita.')"><i class="fa fa-trash"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Nenhum pacote de despesa criado ainda.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Modal para criar pacote de despesa -->
                <div id="modal-despesa" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title">Novo Pacote de Despesa</h3>
                            <button class="close-modal" onclick="closeModal('modal-despesa')">&times;</button>
                        </div>
                        
                        <form method="post">
                            <div class="form-group">
                                <label>Unidade Gerenciadora</label>
                                <input type="text" name="unidade_gerenciadora" required maxlength="50">
                            </div>
                            
                            <div class="form-group">
                                <label>Data de Criação</label>
                                <input type="date" name="data_criacao" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Documento SEI</label>
                                <input type="text" name="documento_sei" maxlength="255">
                            </div>
                            
                            <div class="form-group">
                                <label>Descrição do Pacote</label>
                                <textarea name="descricao_pacote"></textarea>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="button" class="btn btn-cancel" onclick="closeModal('modal-despesa')">Cancelar</button>
                                <button type="submit" name="add_pacote_despesa" class="btn">Criar Pacote</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Modal para editar pacote de despesa -->
                <div id="modal-edit-despesa" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title">Editar Pacote de Despesa</h3>
                            <button class="close-modal" onclick="closeModal('modal-edit-despesa')">&times;</button>
                        </div>
                        
                        <form method="post">
                            <input type="hidden" name="edit_pacote_despesa" value="1">
                            <input type="hidden" name="id" id="edit-despesa-id" value="">
                            
                            <div class="form-group">
                                <label>Unidade Gerenciadora</label>
                                <input type="text" name="unidade_gerenciadora" id="edit-despesa-unidade" required maxlength="50">
                            </div>
                            
                            <div class="form-group">
                                <label>Data de Criação</label>
                                <input type="date" name="data_criacao" id="edit-despesa-data" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Documento SEI</label>
                                <input type="text" name="documento_sei" id="edit-despesa-sei" maxlength="255">
                            </div>
                            
                            <div class="form-group">
                                <label>Descrição do Pacote</label>
                                <textarea name="descricao_pacote" id="edit-despesa-descricao"></textarea>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="button" class="btn btn-cancel" onclick="closeModal('modal-edit-despesa')">Cancelar</button>
                                <button type="submit" class="btn">Atualizar Pacote</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Seção de Relatório de Controle Orçamentário -->
        <div class="section">
            <h2>📊 Relatório de Controle Orçamentário</h2>
            
            <?php if (!empty($relatorio_dados)): ?>
                <?php
                // Calcular estatísticas
                $total_receita = array_sum(array_column($relatorio_dados, 'receita'));
                $total_despesa = array_sum(array_column($relatorio_dados, 'despesa'));
                $saldo_total = $total_receita - $total_despesa;
                ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo formatarMoeda($total_receita); ?></div>
                        <div class="stat-label">Total de Receitas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo formatarMoeda($total_despesa); ?></div>
                        <div class="stat-label">Total de Despesas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo formatarMoeda($saldo_total); ?></div>
                        <div class="stat-label">Saldo Geral</div>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Ano</th>
                            <th>Mês</th>
                            <th>Grupo</th>
                            <th>Elemento Item</th>
                            <th>Receita</th>
                            <th>Despesa</th>
                            <th>Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($relatorio_dados as $linha): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($linha['ano']); ?></td>
                                <td><?php echo getNomeMes($linha['mes']); ?></td>
                                <td><?php echo htmlspecialchars($linha['grupo']); ?></td>
                                <td><?php echo htmlspecialchars($linha['elemento_item']); ?></td>
                                <td><?php echo formatarMoeda($linha['receita']); ?></td>
                                <td><?php echo formatarMoeda($linha['despesa']); ?></td>
                                <td class="<?php echo $linha['saldo'] > 0 ? 'positive' : ($linha['saldo'] < 0 ? 'negative' : 'zero'); ?>">
                                    <?php echo formatarMoeda($linha['saldo']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="feedback" style="background: #f3f4f6; color: #6b7280; border-left-color: #9ca3af;">
                    ℹ️ Nenhum dado encontrado para o relatório. Adicione receitas e despesas para visualizar o controle orçamentário.
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
<?php
$content = ob_get_clean();
$page_title = 'Gestão Orçamentária - SDTS3 Manager';
include 'base.php';
?>
            if (feedback) {
                setTimeout(() => {
                    feedback.style.opacity = '0';
                    setTimeout(() => {
                        feedback.style.display = 'none';
                    }, 300);
                }, 5000);
            }
            
            // Add event listeners for quantity input
            const quantidadeInput = document.querySelector('input[name="quantidade"]');
            if (quantidadeInput) {
                quantidadeInput.addEventListener('input', updateTotal);
            }
        });
        
        // Funções para controlar modais (definição duplicada removida)
        
        function openModalForEdit(modalId) {
            console.log('openModalForEdit chamado para:', modalId);
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
                console.log('Modal aberto:', modalId);
            } else {
                console.error('Modal não encontrado:', modalId);
            }
        }
        
        function closeModal(modalId) {
            console.log('closeModal chamado para:', modalId);
            const modal = document.getElementById(modalId);
            if (modal) {
                console.log('Fechando modal:', modal);
                modal.classList.remove('show');
                modal.style.display = 'none'; // Forçar display none
                document.body.style.overflow = 'auto';
                console.log('Modal fechado com sucesso');
            } else {
                console.error('Modal não encontrado para fechar:', modalId);
            }
        }
        
        // Funções para editar pacotes
        function editPacoteReceita(button) {
            console.log('editPacoteReceita chamado');
            
            // Obter dados dos data attributes
            const id = button.dataset.id;
            const unidade = button.dataset.unidade;
            const ano = button.dataset.ano;
            const data = button.dataset.data;
            const sei = button.dataset.sei;
            const descricao = button.dataset.descricao;
            
            console.log('Dados obtidos:', { id, unidade, ano, data, sei, descricao });
            
            // Verificar se os elementos existem
            const campos = {
                'edit-receita-id': document.getElementById('edit-receita-id'),
                'edit-receita-unidade': document.getElementById('edit-receita-unidade'),
                'edit-receita-ano': document.getElementById('edit-receita-ano'),
                'edit-receita-data': document.getElementById('edit-receita-data'),
                'edit-receita-sei': document.getElementById('edit-receita-sei'),
                'edit-receita-descricao': document.getElementById('edit-receita-descricao')
            };
            
            console.log('Campos encontrados:', campos);
            
            if (campos['edit-receita-id']) campos['edit-receita-id'].value = id;
            if (campos['edit-receita-unidade']) campos['edit-receita-unidade'].value = unidade;
            if (campos['edit-receita-ano']) campos['edit-receita-ano'].value = ano;
            if (campos['edit-receita-data']) campos['edit-receita-data'].value = data;
            if (campos['edit-receita-sei']) campos['edit-receita-sei'].value = sei || '';
            if (campos['edit-receita-descricao']) campos['edit-receita-descricao'].value = descricao || '';
            
            console.log('Valores após definir:', {
                id: campos['edit-receita-id']?.value,
                unidade: campos['edit-receita-unidade']?.value,
                ano: campos['edit-receita-ano']?.value,
                data: campos['edit-receita-data']?.value,
                sei: campos['edit-receita-sei']?.value,
                descricao: campos['edit-receita-descricao']?.value
            });
            
            openModalForEdit('modal-edit-receita');
        }
        
        function editPacoteDespesa(button) {
            console.log('editPacoteDespesa chamado');
            
            // Obter dados dos data attributes
            const id = button.dataset.id;
            const unidade = button.dataset.unidade;
            const data = button.dataset.data;
            const sei = button.dataset.sei;
            const descricao = button.dataset.descricao;
            
            console.log('Dados obtidos:', { id, unidade, data, sei, descricao });
            
            // Verificar se os elementos existem
            const campos = {
                'edit-despesa-id': document.getElementById('edit-despesa-id'),
                'edit-despesa-unidade': document.getElementById('edit-despesa-unidade'),
                'edit-despesa-data': document.getElementById('edit-despesa-data'),
                'edit-despesa-sei': document.getElementById('edit-despesa-sei'),
                'edit-despesa-descricao': document.getElementById('edit-despesa-descricao')
            };
            
            console.log('Campos encontrados:', campos);
            
            if (campos['edit-despesa-id']) campos['edit-despesa-id'].value = id;
            if (campos['edit-despesa-unidade']) campos['edit-despesa-unidade'].value = unidade;
            if (campos['edit-despesa-data']) campos['edit-despesa-data'].value = data;
            if (campos['edit-despesa-sei']) campos['edit-despesa-sei'].value = sei || '';
            if (campos['edit-despesa-descricao']) campos['edit-despesa-descricao'].value = descricao || '';
            
            console.log('Valores após definir:', {
                id: campos['edit-despesa-id']?.value,
                unidade: campos['edit-despesa-unidade']?.value,
                data: campos['edit-despesa-data']?.value,
                sei: campos['edit-despesa-sei']?.value,
                descricao: campos['edit-despesa-descricao']?.value
            });
            
            openModalForEdit('modal-edit-despesa');
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
                const requiredFields = this.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
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

        // Função para editar item de receita
        function editItemReceita(button) {
            console.log('editItemReceita chamado');
            console.log('Button element:', button);
            console.log('All data attributes:', button.dataset);
            
            // Obter dados dos data attributes com fallbacks
            const itemId = button.dataset.itemId || '';
            const pacoteId = button.dataset.pacoteId || '';
            const acao = button.dataset.acao || '';
            const grupo = button.dataset.grupo || '';
            const elemento = button.dataset.elemento || '';
            const valorUnitario = button.dataset.valorUnitario || '';
            const quantidade = button.dataset.quantidade || '';
            const tipoDistribuicao = button.dataset.tipoDistribuicao || 'Mensal';
            const mesAlocacao = button.dataset.mesAlocacao || '';
            const mesInicial = button.dataset.mesInicial || '';
            const mesFinal = button.dataset.mesFinal || '';
            
            console.log('Dados extraídos do botão:', {
                itemId, pacoteId, acao, grupo, elemento, 
                valorUnitario, quantidade, tipoDistribuicao,
                mesAlocacao, mesInicial, mesFinal
            });
            
            // Verificar se o modal existe
            const modal = document.getElementById('modal-edit-item-receita');
            console.log('Modal receita encontrado:', !!modal);
            
            if (!modal) {
                console.error('Modal de edição de receita não encontrado!');
                return;
            }
            
            // Preencher campos do modal
            const idField = document.getElementById('edit-item-receita-id');
            const pacoteField = document.getElementById('edit-item-receita-pacote-id');
            const acaoField = document.getElementById('edit-item-receita-acao');
            const valorField = document.getElementById('edit-item-receita-valor');
            const quantidadeField = document.getElementById('edit-item-receita-quantidade');
            const distribuicaoField = document.getElementById('edit-item-receita-distribuicao');
            const mesAlocacaoField = document.getElementById('edit-mes-alocacao-receita');
            const mesInicialField = document.getElementById('edit-mes-inicial-receita');
            const mesFinalField = document.getElementById('edit-mes-final-receita');
            
            console.log('Campos receita encontrados:', {
                idField: !!idField, pacoteField: !!pacoteField, acaoField: !!acaoField, 
                valorField: !!valorField, quantidadeField: !!quantidadeField, 
                distribuicaoField: !!distribuicaoField, mesAlocacaoField: !!mesAlocacaoField, 
                mesInicialField: !!mesInicialField, mesFinalField: !!mesFinalField
            });
            
            // Preencher campos básicos
            if (idField && itemId) {
                idField.value = itemId;
                console.log('ID preenchido:', itemId);
            }
            if (pacoteField && pacoteId) {
                pacoteField.value = pacoteId;
                console.log('Pacote ID preenchido:', pacoteId);
            }
            if (acaoField) {
                const acaoCompleta = acao ? `${acao}.${grupo}.${elemento}` : `${grupo}.${elemento}`;
                acaoField.value = acaoCompleta;
                console.log('Ação preenchida:', acaoCompleta);
            }
            if (valorField && valorUnitario) {
                valorField.value = valorUnitario;
                console.log('Valor preenchido:', valorUnitario);
            }
            if (quantidadeField && quantidade) {
                quantidadeField.value = quantidade;
                console.log('Quantidade preenchida:', quantidade);
            }
            if (distribuicaoField) {
                distribuicaoField.value = tipoDistribuicao;
                console.log('Distribuição preenchida:', tipoDistribuicao);
                
                // Mostrar campos condicionais baseado no tipo de distribuição
                console.log('Chamando toggleEditDistribuicaoFieldsReceita com:', tipoDistribuicao);
                toggleEditDistribuicaoFieldsReceita(distribuicaoField);
            }
            if (mesAlocacaoField && mesAlocacao) {
                mesAlocacaoField.value = mesAlocacao;
                console.log('Mês alocação preenchido:', mesAlocacao);
            }
            if (mesInicialField && mesInicial) {
                mesInicialField.value = mesInicial;
                console.log('Mês inicial preenchido:', mesInicial);
            }
            if (mesFinalField && mesFinal) {
                mesFinalField.value = mesFinal;
                console.log('Mês final preenchido:', mesFinal);
            }
            
            // Abrir modal
            console.log('Tentando abrir modal receita...');
            openModal('modal-edit-item-receita', false); // Não limpar formulário
        }

        // Função para editar item de despesa  
        function editItemDespesa(button) {
            console.log('editItemDespesa chamado');
            console.log('Button element:', button);
            console.log('All data attributes:', button.dataset);
            
            // Obter dados dos data attributes com fallbacks
            const itemId = button.dataset.itemId || '';
            const pacoteId = button.dataset.pacoteId || '';
            const grupo = button.dataset.grupo || '';
            const elemento = button.dataset.elemento || '';
            const valorUnitario = button.dataset.valorUnitario || '';
            const quantidade = button.dataset.quantidade || '';
            const tipoDistribuicao = button.dataset.tipoDistribuicao || 'Mensal';
            const mesAlocacao = button.dataset.mesAlocacao || '';
            const mesInicial = button.dataset.mesInicial || '';
            const mesFinal = button.dataset.mesFinal || '';
            
            console.log('Dados extraídos do botão:', {
                itemId, pacoteId, grupo, elemento, 
                valorUnitario, quantidade, tipoDistribuicao,
                mesAlocacao, mesInicial, mesFinal
            });
            
            // Verificar se o modal existe
            const modal = document.getElementById('modal-edit-item-despesa');
            console.log('Modal despesa encontrado:', !!modal);
            
            if (!modal) {
                console.error('Modal de edição de despesa não encontrado!');
                return;
            }
            
            // Preencher campos do modal
            const idField = document.getElementById('edit-item-despesa-id');
            const pacoteField = document.getElementById('edit-item-despesa-pacote-id');
            const grupoField = document.getElementById('edit-item-despesa-grupo');
            const valorField = document.getElementById('edit-item-despesa-valor');
            const quantidadeField = document.getElementById('edit-item-despesa-quantidade');
            const distribuicaoField = document.getElementById('edit-item-despesa-distribuicao');
            const mesAlocacaoField = document.getElementById('edit-mes-alocacao-despesa');
            const mesInicialField = document.getElementById('edit-mes-inicial-despesa');
            const mesFinalField = document.getElementById('edit-mes-final-despesa');
            
            console.log('Campos despesa encontrados:', {
                idField: !!idField, pacoteField: !!pacoteField, grupoField: !!grupoField, 
                valorField: !!valorField, quantidadeField: !!quantidadeField, 
                distribuicaoField: !!distribuicaoField, mesAlocacaoField: !!mesAlocacaoField, 
                mesInicialField: !!mesInicialField, mesFinalField: !!mesFinalField
            });
            
            // Preencher campos básicos
            if (idField && itemId) {
                idField.value = itemId;
                console.log('ID preenchido:', itemId);
            }
            if (pacoteField && pacoteId) {
                pacoteField.value = pacoteId;
                console.log('Pacote ID preenchido:', pacoteId);
            }
            if (grupoField) {
                const grupoCompleto = `${grupo}.${elemento}`;
                grupoField.value = grupoCompleto;
                console.log('Grupo preenchido:', grupoCompleto);
            }
            if (valorField && valorUnitario) {
                valorField.value = valorUnitario;
                console.log('Valor preenchido:', valorUnitario);
            }
            if (quantidadeField && quantidade) {
                quantidadeField.value = quantidade;
                console.log('Quantidade preenchida:', quantidade);
            }
            if (distribuicaoField) {
                distribuicaoField.value = tipoDistribuicao;
                console.log('Distribuição preenchida:', tipoDistribuicao);
                
                // Mostrar campos condicionais baseado no tipo de distribuição
                console.log('Chamando toggleEditDistribuicaoFieldsDespesa com:', tipoDistribuicao);
                toggleEditDistribuicaoFieldsDespesa(distribuicaoField);
            }
            if (mesAlocacaoField && mesAlocacao) {
                mesAlocacaoField.value = mesAlocacao;
                console.log('Mês alocação preenchido:', mesAlocacao);
            }
            if (mesInicialField && mesInicial) {
                mesInicialField.value = mesInicial;
                console.log('Mês inicial preenchido:', mesInicial);
            }
            if (mesFinalField && mesFinal) {
                mesFinalField.value = mesFinal;
                console.log('Mês final preenchido:', mesFinal);
            }
            
            // Abrir modal
            console.log('Tentando abrir modal despesa...');
            openModal('modal-edit-item-despesa', false); // Não limpar formulário
        }
            
            // Abrir modal
            console.log('Tentando abrir modal...');
            openModal('modal-edit-item-despesa', false); // Não limpar formulário
        }

        // Event listeners para distribuição personalizada nos modais de edição
        document.getElementById('edit-item-receita-distribuicao').addEventListener('change', function() {
            const personalizadaDiv = document.getElementById('edit-distribuicao-personalizada-receita');
            if (this.value === 'personalizada') {
                personalizadaDiv.style.display = 'block';
            } else {
                personalizadaDiv.style.display = 'none';
            }
        });

        document.getElementById('edit-item-despesa-distribuicao').addEventListener('change', function() {
            const personalizadaDiv = document.getElementById('edit-distribuicao-personalizada-despesa');
            if (this.value === 'personalizada') {
                personalizadaDiv.style.display = 'block';
            } else {
                personalizadaDiv.style.display = 'none';
            }
        });
    </div>

<!-- JavaScript foi movido para arquivo externo js/modal.js -->
</body>
</html>
<?php
$content = ob_get_clean();
$page_title = 'Gestão Orçamentária - SDTS3 Manager';
include 'base.php';
?>
