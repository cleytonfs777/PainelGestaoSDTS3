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

// Função para formatar dotação completa (receitas)
function formatarDotacao($acao, $grupo, $elemento) {
    return sprintf('%04d.%d.%06d', $acao, $grupo, $elemento);
}

// Função para formatar dotação de despesa (apenas Grupo.Elemento)
function formatarDotacaoDespesa($grupo, $elemento) {
    return sprintf('%d.%06d', $grupo, $elemento);
}

// Parâmetros de filtro
$ano_filtro = $_GET['ano'] ?? date('Y');
$meses_filtro = $_GET['meses'] ?? [];
$dotacao_filtro = $_GET['dotacao'] ?? '';

// Se meses não foi passado como array, converter
if (!is_array($meses_filtro) && !empty($meses_filtro)) {
    $meses_filtro = [$meses_filtro];
}

// Se nenhum mês selecionado, usar o mês atual
if (empty($meses_filtro)) {
    $meses_filtro = [date('n')];
}

// Construir query base para receitas
$receitas_query = "
    SELECT 
        ci.acao,
        ci.grupo,
        ci.elemento_item,
        ci.fonte,
        SUM(lm.valor_mes) as total_receita,
        GROUP_CONCAT(DISTINCT pr.unidade_gerenciadora ORDER BY pr.unidade_gerenciadora SEPARATOR ', ') as unidades
    FROM LancamentosMensais lm
    JOIN PacoteReceitaItens pri ON lm.id_pacote_receita_item = pri.id
    JOIN CatalogoItens ci ON pri.id_item_catalogo = ci.id
    JOIN PacotesReceita pr ON pri.id_pacote_receita = pr.id
    WHERE lm.ano = ? 
";

$params_receitas = [$ano_filtro];

if (!empty($meses_filtro)) {
    $placeholders = str_repeat('?,', count($meses_filtro) - 1) . '?';
    $receitas_query .= " AND lm.mes IN ($placeholders)";
    $params_receitas = array_merge($params_receitas, $meses_filtro);
}

if (!empty($dotacao_filtro)) {
    $dotacao_parts = explode('.', $dotacao_filtro);
    if (count($dotacao_parts) == 3) {
        $receitas_query .= " AND ci.acao = ? AND ci.grupo = ? AND ci.elemento_item = ?";
        $params_receitas = array_merge($params_receitas, $dotacao_parts);
    }
}

$receitas_query .= " GROUP BY ci.acao, ci.grupo, ci.elemento_item, ci.fonte ORDER BY ci.acao, ci.grupo, ci.elemento_item, ci.fonte";

// Construir query base para despesas (sem ação)
$despesas_query = "
    SELECT 
        ci.grupo,
        ci.elemento_item,
        ci.fonte,
        SUM(pdi.quantidade * ci.valor_unitario) as total_despesa,
        GROUP_CONCAT(DISTINCT pd.unidade_gerenciadora ORDER BY pd.unidade_gerenciadora SEPARATOR ', ') as unidades
    FROM PacoteDespesaItens pdi
    JOIN PacotesDespesa pd ON pdi.id_pacote_despesa = pd.id
    JOIN CatalogoItens ci ON pdi.id_item_catalogo = ci.id
    WHERE YEAR(pd.data_criacao) = ?
";

$params_despesas = [$ano_filtro];

if (!empty($meses_filtro)) {
    $placeholders = str_repeat('?,', count($meses_filtro) - 1) . '?';
    $despesas_query .= " AND MONTH(pd.data_criacao) IN ($placeholders)";
    $params_despesas = array_merge($params_despesas, $meses_filtro);
}

if (!empty($dotacao_filtro)) {
    $dotacao_parts = explode('.', $dotacao_filtro);
    if (count($dotacao_parts) == 3) {
        // Filtro para receitas (Ação.Grupo.Elemento)
        $receitas_query .= " AND ci.acao = ? AND ci.grupo = ? AND ci.elemento_item = ?";
        $params_receitas = array_merge($params_receitas, $dotacao_parts);
    } elseif (count($dotacao_parts) == 2) {
        // Filtro para despesas (Grupo.Elemento)
        $despesas_query .= " AND ci.grupo = ? AND ci.elemento_item = ?";
        $params_despesas = array_merge($params_despesas, $dotacao_parts);
    }
}

$despesas_query .= " GROUP BY ci.grupo, ci.elemento_item, ci.fonte ORDER BY ci.grupo, ci.elemento_item, ci.fonte";

// Executar queries
$receitas = $pdo->prepare($receitas_query);
$receitas->execute($params_receitas);
$dados_receitas = $receitas->fetchAll();

$despesas = $pdo->prepare($despesas_query);
$despesas->execute($params_despesas);
$dados_despesas = $despesas->fetchAll();

// Consolidar dados separadamente
$receitas_consolidadas = [];
$despesas_consolidadas = [];

// Processar receitas (Ação.Grupo.Elemento)
foreach ($dados_receitas as $receita) {
    $dotacao_key = formatarDotacao($receita['acao'], $receita['grupo'], $receita['elemento_item']);
    
    if (!isset($receitas_consolidadas[$dotacao_key])) {
        $receitas_consolidadas[$dotacao_key] = [
            'acao' => $receita['acao'],
            'grupo' => $receita['grupo'],
            'elemento_item' => $receita['elemento_item'],
            'dotacao' => $dotacao_key,
            'receitas_por_fonte' => [],
            'total_receita' => 0,
            'unidades' => []
        ];
    }
    
    $receitas_consolidadas[$dotacao_key]['receitas_por_fonte'][$receita['fonte']] = $receita['total_receita'];
    $receitas_consolidadas[$dotacao_key]['total_receita'] += $receita['total_receita'];
    $receitas_consolidadas[$dotacao_key]['unidades'] = array_merge(
        $receitas_consolidadas[$dotacao_key]['unidades'], 
        explode(', ', $receita['unidades'])
    );
}

// Processar despesas (Grupo.Elemento apenas)
foreach ($dados_despesas as $despesa) {
    $dotacao_key = formatarDotacaoDespesa($despesa['grupo'], $despesa['elemento_item']);
    
    if (!isset($despesas_consolidadas[$dotacao_key])) {
        $despesas_consolidadas[$dotacao_key] = [
            'grupo' => $despesa['grupo'],
            'elemento_item' => $despesa['elemento_item'],
            'dotacao' => $dotacao_key,
            'despesas_por_fonte' => [],
            'total_despesa' => 0,
            'unidades' => []
        ];
    }
    
    $despesas_consolidadas[$dotacao_key]['despesas_por_fonte'][$despesa['fonte']] = $despesa['total_despesa'];
    $despesas_consolidadas[$dotacao_key]['total_despesa'] += $despesa['total_despesa'];
    $despesas_consolidadas[$dotacao_key]['unidades'] = array_merge(
        $despesas_consolidadas[$dotacao_key]['unidades'], 
        explode(', ', $despesa['unidades'])
    );
}

// Calcular saldos - apenas para receitas que têm despesas compatíveis
$saldos_por_elemento = [];
foreach ($receitas_consolidadas as $receita) {
    $elemento_key = $receita['grupo'] . '.' . str_pad($receita['elemento_item'], 6, '0', STR_PAD_LEFT);
    
    if (!isset($saldos_por_elemento[$elemento_key])) {
        $saldos_por_elemento[$elemento_key] = [
            'grupo' => $receita['grupo'],
            'elemento_item' => $receita['elemento_item'],
            'elemento_key' => $elemento_key,
            'total_receita' => 0,
            'total_despesa' => 0,
            'receitas_detalhadas' => [],
            'despesas_detalhadas' => []
        ];
    }
    
    $saldos_por_elemento[$elemento_key]['total_receita'] += $receita['total_receita'];
    $saldos_por_elemento[$elemento_key]['receitas_detalhadas'][] = $receita;
}

foreach ($despesas_consolidadas as $despesa) {
    $elemento_key = $despesa['grupo'] . '.' . str_pad($despesa['elemento_item'], 6, '0', STR_PAD_LEFT);
    
    if (!isset($saldos_por_elemento[$elemento_key])) {
        $saldos_por_elemento[$elemento_key] = [
            'grupo' => $despesa['grupo'],
            'elemento_item' => $despesa['elemento_item'],
            'elemento_key' => $elemento_key,
            'total_receita' => 0,
            'total_despesa' => 0,
            'receitas_detalhadas' => [],
            'despesas_detalhadas' => []
        ];
    }
    
    $saldos_por_elemento[$elemento_key]['total_despesa'] += $despesa['total_despesa'];
    $saldos_por_elemento[$elemento_key]['despesas_detalhadas'][] = $despesa;
}

// Calcular saldo final para cada elemento
foreach ($saldos_por_elemento as $key => $elemento) {
    $saldos_por_elemento[$key]['saldo'] = $elemento['total_receita'] - $elemento['total_despesa'];
}

// Buscar todas as dotações disponíveis para o filtro (receitas e despesas separadamente)
$dotacoes_receitas_query = "
    SELECT DISTINCT ci.acao, ci.grupo, ci.elemento_item
    FROM CatalogoItens ci
    WHERE ci.tipo = 'Receita'
    ORDER BY ci.acao, ci.grupo, ci.elemento_item
";

$dotacoes_despesas_query = "
    SELECT DISTINCT ci.grupo, ci.elemento_item
    FROM CatalogoItens ci
    WHERE ci.tipo = 'Despesa'
    ORDER BY ci.grupo, ci.elemento_item
";

$dotacoes_receitas_disponiveis = $pdo->query($dotacoes_receitas_query)->fetchAll();
$dotacoes_despesas_disponiveis = $pdo->query($dotacoes_despesas_query)->fetchAll();

ob_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Dotações - SDTS3</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f7f7fa 0%, #e8eaf6 100%);
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
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
        
        .filters-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            border-left: 5px solid #232946;
        }
        
        .filters-title {
            color: #232946;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .filter-group select, .filter-group input {
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .filter-group select:focus, .filter-group input:focus {
            border-color: #232946;
            box-shadow: 0 0 0 3px rgba(35, 41, 70, 0.1);
            outline: none;
        }
        
        .meses-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .mes-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .mes-checkbox:hover {
            background: #f8fafc;
            border-color: #232946;
        }
        
        .mes-checkbox input[type="checkbox"] {
            margin: 0;
        }
        
        .mes-checkbox.selected {
            background: #232946;
            color: #fff;
            border-color: #232946;
        }
        
        .btn-filtrar {
            background: linear-gradient(45deg, #232946, #4a5568);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-filtrar:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(35, 41, 70, 0.3);
        }
        
        .resultados-section {
            margin-top: 2rem;
        }
        
        .resumo-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .resumo-card {
            background: linear-gradient(45deg, #232946, #4a5568);
            color: #fff;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .resumo-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .resumo-label {
            font-size: 0.9rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .dotacoes-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .dotacoes-table th {
            background: linear-gradient(45deg, #232946, #4a5568);
            color: #fff;
            padding: 1rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .dotacoes-table td {
            padding: 0.75rem;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        
        .dotacoes-table tr:nth-child(even) {
            background: #f8fafc;
        }
        
        .dotacoes-table tr:hover {
            background: #e2e8f0;
            transition: background-color 0.2s ease;
        }
        
        .dotacao-code {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #232946;
            font-size: 1.1rem;
        }
        
        .valor-positivo {
            color: #22c55e;
            font-weight: 600;
        }
        
        .valor-negativo {
            color: #ef4444;
            font-weight: 600;
        }
        
        .valor-neutro {
            color: #6b7280;
            font-weight: 600;
        }
        
        .fontes-detalhes {
            font-size: 0.9rem;
            text-align: left;
        }
        
        .fonte-item {
            display: flex;
            justify-content: space-between;
            padding: 0.2rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .fonte-item:last-child {
            border-bottom: none;
        }
        
        .unidades-lista {
            font-size: 0.85rem;
            color: #6b7280;
            text-align: left;
            max-width: 200px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .meses-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .resumo-cards {
                grid-template-columns: 1fr;
            }
            
            .dotacoes-table {
                font-size: 0.9rem;
            }
            
            .dotacoes-table th, .dotacoes-table td {
                padding: 0.5rem;
            }
        }
        
        .periodo-info {
            background: #e0f2fe;
            border-left: 4px solid #0288d1;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0 8px 8px 0;
        }
        
        .periodo-info strong {
            color: #01579b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Controle de Dotações Orçamentárias</h1>
            <p>Saldos por Elemento Item: Receitas (Ação.Grupo.Elemento) vs Despesas (Grupo.Elemento)</p>
        </div>
        
        <div class="filters-section">
            <h2 class="filters-title">🔍 Filtros de Consulta</h2>
            
            <form method="get">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Ano</label>
                        <select name="ano">
                            <?php for ($ano = 2020; $ano <= 2030; $ano++): ?>
                                <option value="<?php echo $ano; ?>" <?php echo ($ano == $ano_filtro) ? 'selected' : ''; ?>>
                                    <?php echo $ano; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Dotação Específica (opcional)</label>
                        <select name="dotacao">
                            <option value="">Todas as dotações</option>
                            <optgroup label="Receitas (Ação.Grupo.Elemento)">
                                <?php foreach ($dotacoes_receitas_disponiveis as $dot): ?>
                                    <?php $dot_formatted = formatarDotacao($dot['acao'], $dot['grupo'], $dot['elemento_item']); ?>
                                    <option value="<?php echo $dot_formatted; ?>" <?php echo ($dot_formatted == $dotacao_filtro) ? 'selected' : ''; ?>>
                                        <?php echo $dot_formatted; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Despesas (Grupo.Elemento)">
                                <?php foreach ($dotacoes_despesas_disponiveis as $dot): ?>
                                    <?php $dot_formatted = formatarDotacaoDespesa($dot['grupo'], $dot['elemento_item']); ?>
                                    <option value="<?php echo $dot_formatted; ?>" <?php echo ($dot_formatted == $dotacao_filtro) ? 'selected' : ''; ?>>
                                        <?php echo $dot_formatted; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                </div>
                
                <div class="filter-group">
                    <label>Meses (selecione um ou mais)</label>
                    <div class="meses-grid">
                        <?php for ($mes = 1; $mes <= 12; $mes++): ?>
                            <label class="mes-checkbox <?php echo in_array($mes, $meses_filtro) ? 'selected' : ''; ?>">
                                <input type="checkbox" name="meses[]" value="<?php echo $mes; ?>" 
                                       <?php echo in_array($mes, $meses_filtro) ? 'checked' : ''; ?>
                                       onchange="toggleMesCheckbox(this)">
                                <span><?php echo getNomeMes($mes); ?></span>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn-filtrar">🔍 Filtrar Dados</button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($saldos_por_elemento)): ?>
            <div class="resultados-section">
                <div class="periodo-info">
                    <strong>Período consultado:</strong> 
                    <?php echo $ano_filtro; ?> - 
                    <?php echo implode(', ', array_map('getNomeMes', $meses_filtro)); ?>
                    <?php if (!empty($dotacao_filtro)): ?>
                        | <strong>Dotação:</strong> <?php echo $dotacao_filtro; ?>
                    <?php endif; ?>
                </div>
                
                <?php
                $total_geral_receita = array_sum(array_column($saldos_por_elemento, 'total_receita'));
                $total_geral_despesa = array_sum(array_column($saldos_por_elemento, 'total_despesa'));
                $saldo_geral = $total_geral_receita - $total_geral_despesa;
                ?>
                
                <div class="resumo-cards">
                    <div class="resumo-card">
                        <div class="resumo-value"><?php echo formatarMoeda($total_geral_receita); ?></div>
                        <div class="resumo-label">Total Receitas</div>
                    </div>
                    <div class="resumo-card">
                        <div class="resumo-value"><?php echo formatarMoeda($total_geral_despesa); ?></div>
                        <div class="resumo-label">Total Despesas</div>
                    </div>
                    <div class="resumo-card">
                        <div class="resumo-value"><?php echo formatarMoeda($saldo_geral); ?></div>
                        <div class="resumo-label">Saldo Geral</div>
                    </div>
                    <div class="resumo-card">
                        <div class="resumo-value"><?php echo count($saldos_por_elemento); ?></div>
                        <div class="resumo-label">Elementos</div>
                    </div>
                </div>
                
                <table class="dotacoes-table">
                    <thead>
                        <tr>
                            <th>Elemento Item (Grupo.Elemento)</th>
                            <th>Receitas Detalhadas</th>
                            <th>Despesas Detalhadas</th>
                            <th>Total Receita</th>
                            <th>Total Despesa</th>
                            <th>Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saldos_por_elemento as $elemento): ?>
                            <tr>
                                <td>
                                    <div class="dotacao-code"><?php echo $elemento['elemento_key']; ?></div>
                                    <div style="font-size: 0.8rem; color: #666;">
                                        Grupo: <?php echo $elemento['grupo']; ?> | 
                                        Elemento: <?php echo str_pad($elemento['elemento_item'], 6, '0', STR_PAD_LEFT); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fonte-details">
                                        <?php if (!empty($elemento['receitas_detalhadas'])): ?>
                                            <?php foreach ($elemento['receitas_detalhadas'] as $receita): ?>
                                                <div style="margin-bottom: 5px;">
                                                    <strong><?php echo $receita['dotacao']; ?></strong><br>
                                                    <?php foreach ($receita['receitas_por_fonte'] as $fonte => $valor): ?>
                                                        <small>Fonte <?php echo $fonte; ?>: <?php echo formatarMoeda($valor); ?></small><br>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color: #999;">Nenhuma receita</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fonte-details">
                                        <?php if (!empty($elemento['despesas_detalhadas'])): ?>
                                            <?php foreach ($elemento['despesas_detalhadas'] as $despesa): ?>
                                                <div style="margin-bottom: 5px;">
                                                    <strong><?php echo $despesa['dotacao']; ?></strong><br>
                                                    <?php foreach ($despesa['despesas_por_fonte'] as $fonte => $valor): ?>
                                                        <small>Fonte <?php echo $fonte; ?>: <?php echo formatarMoeda($valor); ?></small><br>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color: #999;">Nenhuma despesa</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="value receita-value"><?php echo formatarMoeda($elemento['total_receita']); ?></td>
                                <td class="value despesa-value"><?php echo formatarMoeda($elemento['total_despesa']); ?></td>
                                <td class="value saldo-value <?php echo $elemento['saldo'] >= 0 ? 'positivo' : 'negativo'; ?>">
                                    <?php echo formatarMoeda($elemento['saldo']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="resultados-section">
                <div style="text-align: center; padding: 3rem; background: #f8fafc; border-radius: 10px;">
                    <h3 style="color: #6b7280;">Nenhuma dotação encontrada</h3>
                    <p style="color: #9ca3af;">Ajuste os filtros para encontrar dados no período selecionado.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleMesCheckbox(checkbox) {
            const label = checkbox.closest('.mes-checkbox');
            if (checkbox.checked) {
                label.classList.add('selected');
            } else {
                label.classList.remove('selected');
            }
        }
        
        // Inicializar estado dos checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.mes-checkbox input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                toggleMesCheckbox(checkbox);
            });
        });
    </script>
</body>
</html>
<?php
$content = ob_get_clean();
$page_title = 'Controle de Dotações - SDTS3 Manager';
include 'base.php';
?>
