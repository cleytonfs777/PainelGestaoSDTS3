<?php
if (!isset($page_title)) $page_title = 'SDTS3 Manager';
if (!isset($content)) $content = '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Roboto', Arial, sans-serif;
            background: #f4f6f8;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 240px;
            min-width: 200px;
            background: #232946;
            color: #fff;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }
        .sidebar h2 {
            margin: 0 0 2rem 0;
            font-size: 1.5rem;
            letter-spacing: 1px;
        }
        .menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .menu li {
            margin-bottom: 1.5rem;
        }
        .menu a {
            color: #fff;
            text-decoration: none;
            font-size: 1.1rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            display: block;
            transition: background 0.2s;
        }
        .menu a.active, .menu a:hover {
            background: #ffb347;
            color: #232946;
        }
        
        /* Menu hierárquico */
        .menu-item-parent {
            margin-bottom: 1rem;
        }
        .menu-item-parent > .menu-title {
            color: #ffb347;
            font-weight: bold;
            font-size: 1.1rem;
            padding: 0.5rem 0;
            margin-bottom: 0.5rem;
            display: block;
            cursor: pointer;
            user-select: none;
        }
        .menu-item-parent > .menu-title:hover {
            color: #fff;
        }
        .menu-submenu {
            list-style: none;
            padding-left: 1.5rem;
            margin: 0;
        }
        .menu-submenu li {
            margin-bottom: 0.8rem;
        }
        .menu-submenu a {
            font-size: 1rem;
            padding: 0.4rem 0.8rem;
            border-left: 2px solid transparent;
        }
        .menu-submenu a:hover {
            border-left-color: #ffb347;
        }
        .menu-submenu a.active {
            border-left-color: #ffb347;
            background: rgba(255, 179, 71, 0.1);
        }
        .main {
            margin-left: 240px;
            flex: 1;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: flex-start;
        }
        /* --- ESTILO DAS PÁGINAS DE CONTRATOS E FORMULÁRIOS --- */
        .container-main {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 1rem 1rem 1rem;
        }
        .contratos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .btn-novo {
            background: #232946;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.7rem 1.5rem;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-novo:hover {
            background: #ffb347;
            color: #232946;
        }
        .table-contratos {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            overflow: hidden;
        }
        .table-contratos th, .table-contratos td {
            padding: 1rem;
            text-align: left;
        }
        .table-contratos th {
            background: #232946;
            color: #fff;
        }
        .table-contratos tr:nth-child(even) {
            background: #f4f6f8;
        }
        .table-contratos tr:hover {
            background: #ffb347;
            color: #232946;
        }
        .btn-edit, .btn-delete {
            border: none;
            background: none;
            cursor: pointer;
            font-size: 1.2rem;
            margin: 0 0.3rem;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .btn-edit {
            color: #232946;
        }
        .btn-edit:hover {
            background: #ffb347;
        }
        .btn-delete {
            color: #d7263d;
        }
        .btn-delete:hover {
            background: #ffe0e6;
        }
        .form-contrato {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: #fff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .form-contrato input, .form-contrato select {
            padding: 0.7rem;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 1rem;
        }
        .form-contrato button {
            background: #232946;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.7rem 1.5rem;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        .form-contrato button:hover {
            background: #ffb347;
            color: #232946;
        }
        @media (max-width: 800px) {
            .sidebar {
                position: static;
                width: 100%;
                min-width: unset;
                flex-direction: row;
                padding: 1rem;
                justify-content: space-around;
            }
            .sidebar h2 {
                display: none;
            }
            .menu {
                display: flex;
                flex-direction: row;
                width: 100%;
                justify-content: space-around;
            }
            .menu li {
                margin-bottom: 0;
            }
            .main {
                margin-left: 0;
                padding: 1rem;
            }
            .container-main {
                max-width: 100vw;
                padding: 1rem 0.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="sidebar-logo" style="text-align:center; margin-bottom:2rem;">
            <img src="img/logo.png" alt="SDTS 3 - Desenvolvimento de Sistemas" style="max-width:210px; width:100%; height:auto; display:block; margin:0 auto;">
        </div>
        <ul class="menu">
            <!-- 1. Contratos -->
            <li class="menu-item-parent">
                <div class="menu-title">
                    <i class="fa fa-file-contract"></i> 1. Contratos
                </div>
                <ul class="menu-submenu">
                    <li><a href="contratos.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'contratos.php') ? 'active' : ''; ?>">Gerenciar Contratos</a></li>
                </ul>
            </li>
            
            <!-- 2. Financeiro -->
            <li class="menu-item-parent">
                <div class="menu-title">
                    <i class="fa fa-money-bill-wave"></i> 2. Financeiro
                </div>
                <ul class="menu-submenu">
                    <li><a href="controle_dotacoes.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'controle_dotacoes.php') ? 'active' : ''; ?>">Controle de Dotações</a></li>
                    <li><a href="gerenciar_catalogo.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'gerenciar_catalogo.php') ? 'active' : ''; ?>">Catálogo</a></li>
                    <li><a href="gestao_orcamentaria.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'gestao_orcamentaria.php') ? 'active' : ''; ?>">Gestão Orçamentária</a></li>
                </ul>
            </li>
            
            <!-- 3. Documentos -->
            <li class="menu-item-parent">
                <div class="menu-title">
                    <i class="fa fa-folder-open"></i> 3. Documentos
                </div>
                <ul class="menu-submenu">
                    <li><a href="documentos.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'documentos.php') ? 'active' : ''; ?>">Documentos SEI</a></li>
                </ul>
            </li>
        </ul>
    </nav>
    <main class="main">
        <?php echo $content; ?>
    </main>
    
    <!-- Bootstrap JS para modais e outros componentes -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Modal JS customizado -->
    <script src="js/modal.js"></script>
</body>
</html> 