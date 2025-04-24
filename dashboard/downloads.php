<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Verificar se o usuário está logado
if (!is_logged_in()) {
    redirect('../pages/login.php');
}

// Obter dados do usuário
$user_id = $_SESSION['user_id'];
$auth = new Auth();
$user = $auth->get_user($user_id);

// Verificar se o usuário tem assinatura ativa
$stmt = $db->prepare("
    SELECT COUNT(*) FROM user_subscriptions 
    WHERE user_id = ? AND status = 'active' AND end_date > NOW()
");
$stmt->execute([$user_id]);
$has_active_subscription = (bool)$stmt->fetchColumn();

// Paginação
$per_page = 12; // Downloads por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Garantir que a página seja pelo menos 1
$offset = ($page - 1) * $per_page;

// Filtros
$filter_cheat = isset($_GET['cheat']) ? (int)$_GET['cheat'] : 0;
$filter_game = isset($_GET['game']) ? (int)$_GET['game'] : 0;
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Construir a query base
$query = "
    SELECT 
        ud.id,
        ud.created_at,
        ud.user_agent,
        ud.ip_address,
        c.id as cheat_id,
        c.name as cheat_name,
        c.image as cheat_image,
        c.version,
        c.file_path as downloaded_file,
        g.id as game_id,
        g.name as game_name
    FROM user_downloads ud
    JOIN cheats c ON ud.cheat_id = c.id
    JOIN games g ON c.game_id = g.id
    WHERE ud.user_id = ?
";

// Adicionar filtros
$params = [$user_id];

if ($filter_cheat) {
    $query .= " AND c.id = ?";
    $params[] = $filter_cheat;
}

if ($filter_game) {
    $query .= " AND g.id = ?";
    $params[] = $filter_game;
}

if ($filter_date) {
    switch ($filter_date) {
        case 'today':
            $query .= " AND DATE(ud.created_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND ud.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $query .= " AND ud.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'year':
            $query .= " AND ud.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
}

if ($search) {
    $query .= " AND (c.name LIKE ? OR g.name LIKE ? OR c.version LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

// Adicionar ordenação
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY ud.created_at ASC";
        break;
    case 'game':
        $query .= " ORDER BY g.name ASC, ud.created_at DESC";
        break;
    case 'cheat':
        $query .= " ORDER BY c.name ASC, ud.created_at DESC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY ud.created_at DESC";
        break;
}

// Contar o total para paginação
$count_query = preg_replace('/^SELECT.*?FROM/s', 'SELECT COUNT(*) FROM', $query);
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_downloads = $stmt->fetchColumn();
$total_pages = ceil($total_downloads / $per_page);

$query .= " LIMIT ? OFFSET ?";
$stmt = $db->prepare($query);

// Add explicit integer binding for the last two parameters
for ($i = 0; $i < count($params); $i++) {
    $stmt->bindValue($i + 1, $params[$i]);
}
$stmt->bindValue(count($params) + 1, $per_page, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter lista de jogos para filtro
$stmt = $db->prepare("
    SELECT DISTINCT g.id, g.name
    FROM games g
    JOIN cheats c ON c.game_id = g.id
    JOIN user_downloads ud ON ud.cheat_id = c.id
    WHERE ud.user_id = ?
    ORDER BY g.name
");
$stmt->execute([$user_id]);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter lista de cheats para filtro
$stmt = $db->prepare("
    SELECT DISTINCT c.id, c.name
    FROM cheats c
    JOIN user_downloads ud ON ud.cheat_id = c.id
    WHERE ud.user_id = ?
    ORDER BY c.name
");
$stmt->execute([$user_id]);
$cheats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas de download
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_downloads,
        COUNT(DISTINCT cheat_id) as unique_cheats,
        MAX(created_at) as last_download
    FROM user_downloads
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$download_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Obter downloads por mês para gráfico (últimos 6 meses)
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count
    FROM user_downloads
    WHERE user_id = ?
    AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute([$user_id]);
$monthly_downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Downloads - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon/favicon-16x16.png">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Toastify -->
    <link href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" rel="stylesheet">
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/scroll.css">
    <!-- Fontes adicionais -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Estilos específicos para o menu do usuário e dropdown - sobreposição */
        .user-menu {
            position: relative !important;
            margin-left: var(--spacing-md) !important;
            z-index: 9999 !important; /* Elevado para garantir que fique na frente */
        }

        .user-info {
            display: flex !important;
            align-items: center !important;
            gap: var(--spacing-sm) !important;
            padding: var(--spacing-xs) var(--spacing-sm) !important;
            border-radius: var(--border-radius-md) !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            border: 1px solid transparent !important;
            background-color: rgba(0, 44, 58, 0.5) !important;
        }

        .user-info:hover {
            background-color: var(--primary-alpha-20) !important;
            border-color: var(--primary) !important;
            box-shadow: 0 0 10px var(--primary-alpha-50) !important;
        }

        .user-info img {
            width: 38px !important;
            height: 38px !important;
            border-radius: 8px !important;
            border: 2px solid var(--primary-alpha-50) !important;
            box-shadow: 0 0 0 2px var(--border-glow) !important;
            transition: all 0.2s !important;
        }

        .user-dropdown {
            position: absolute !important;
            top: calc(100% + 12px) !important;
            right: 0 !important;
            width: 260px !important;
            background-color: var(--card) !important;
            border: 2px solid var(--primary) !important;
            border-radius: var(--border-radius-lg) !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 20px rgba(0, 207, 155, 0.3) !important;
            z-index: 9999 !important; /* Forçar maior z-index */
            transform: translateY(10px) !important;
            opacity: 0 !important;
            visibility: hidden !important;
            transition: transform 0.3s, opacity 0.3s, visibility 0.3s !important;
            display: block !important; /* Forçar display block */
            pointer-events: none !important;
        }

        /* Seta do dropdown */
        .user-dropdown::after {
            content: '' !important;
            position: absolute !important;
            top: -10px !important;
            right: 20px !important;
            width: 20px !important;
            height: 20px !important;
            background-color: var(--card) !important;
            border-top: 2px solid var(--primary) !important;
            border-left: 2px solid var(--primary) !important;
            transform: rotate(45deg) !important;
            z-index: -1 !important;
        }

        .user-dropdown.active {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) !important;
            pointer-events: all !important;
        }

        .user-dropdown header {
            padding: var(--spacing-md) !important;
            border-bottom: 1px solid var(--border) !important;
            text-align: center !important;
            background-color: rgba(0, 44, 58, 0.7) !important;
        }

        .user-dropdown header h4 {
            margin: 0 !important;
            font-size: var(--font-size-md) !important;
            color: var(--primary-light) !important;
            font-weight: var(--font-weight-semibold) !important;
        }

        .user-dropdown header p {
            margin-top: 5px !important;
            margin-bottom: 0 !important;
            font-size: var(--font-size-xs) !important;
            color: var(--text-secondary) !important;
        }

        .user-dropdown ul {
            list-style: none !important;
            padding: var(--spacing-sm) !important;
            margin: 0 !important;
        }

        .user-dropdown li {
            margin-bottom: 2px !important;
        }

        .user-dropdown li:last-child {
            margin-bottom: 0 !important;
        }

        .user-dropdown a {
            display: flex !important;
            align-items: center !important;
            padding: 10px !important;
            border-radius: var(--border-radius-md) !important;
            color: var(--text-secondary) !important;
            transition: all 0.2s !important;
            text-decoration: none !important;
        }

        .user-dropdown a:hover {
            background-color: var(--primary-alpha-10) !important;
            transform: translateX(3px) !important;
            color: var(--primary-light) !important;
        }

        .user-dropdown a i {
            width: 20px !important;
            margin-right: 10px !important;
            color: var(--primary) !important;
            text-align: center !important;
        }
        
        /* CSS específico para resolver a sobreposição visual */
        body::after {
            z-index: -10 !important;
        }
        
        /* Efeito de brilho ao redor do dropdown quando ativo */
        .user-dropdown.active::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            background: var(--gradient-primary);
            border-radius: calc(var(--border-radius-lg) + 3px);
            z-index: -1;
            opacity: 0.5;
            animation: pulseBorder 2s infinite;
        }
        
        @keyframes pulseBorder {
            0% { opacity: 0.3; }
            50% { opacity: 0.5; }
            100% { opacity: 0.3; }
        }

        /* Estilos para a tabela de downloads */
        .downloads-table {
            background-color: var(--card);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            padding: var(--spacing-lg);
            margin-top: var(--spacing-xl);
            transition: all var(--transition-normal);
            overflow: hidden;
        }

        .downloads-table .table {
            width: 100%;
            margin-bottom: 0;
            color: var(--text-secondary);
            background-color: transparent !important;  /* Sobrescreve o --bs-table-bg */
        }

        .downloads-table .table > :not(caption) > * > * {
            background-color: transparent !important;  /* Sobrescreve nas células também */
            box-shadow: none !important;
        }

        .downloads-table thead th {
            border-bottom: 2px solid var(--border);
            padding: var(--spacing-md) var(--spacing-sm);
            font-weight: var(--font-weight-semibold);
            color: var(--primary-light);
            text-transform: uppercase;
            font-size: var(--font-size-sm);
            letter-spacing: var(--letter-spacing-wide);
            background: rgba(0, 44, 58, 0.4) !important;  /* Força este background */
        }

        .downloads-table tbody tr {
            transition: all var(--transition-fast);
            border-bottom: 1px solid var(--border);
            background-color: transparent !important;  /* Sobrescreve o --bs-table-bg */
        }

        /* Força background transparente para linhas pares e ímpares */
        .downloads-table tbody tr:nth-of-type(odd),
        .downloads-table tbody tr:nth-of-type(even) {
            background-color: transparent !important;
        }

        /* Garantir que o hover funcione corretamente */
        .downloads-table tbody tr:hover {
            background-color: var(--primary-alpha-10) !important;
            transform: translateX(5px);
        }

        /* Estilo para o placeholder de ícone do cheat */
        .cheat-icon-placeholder {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius-md);
            background-color: var(--primary-alpha-20);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: var(--spacing-sm);
            color: var(--primary);
            font-size: var(--font-size-lg);
            box-shadow: 0 0 10px var(--primary-alpha-20);
        }

        .cheat-image-sm {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius-md);
            object-fit: cover;
            margin-right: var(--spacing-sm);
            border: 2px solid var(--primary-alpha-50);
        }

        .game-name {
            font-size: var(--font-size-xs);
            color: var(--text-muted);
            margin-bottom: 2px;
        }

        .cheat-name {
            font-weight: var(--font-weight-semibold);
            color: var(--text);
            font-size: var(--font-size-sm);
        }

        .version-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: var(--border-radius-full);
            background-color: var(--primary-alpha-20);
            color: var(--primary-light);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
            letter-spacing: var(--letter-spacing-wide);
        }

        td.date, td.ip {
            font-family: var(--font-mono);
            font-size: var(--font-size-xs);
            color: var(--text-muted);
        }

        td.actions {
            text-align: right;
        }

        td.actions .btn {
            margin-left: var(--spacing-xs);
            padding: 4px 10px;
            font-size: var(--font-size-xs);
            border-width: 1px;
        }

        td.actions .btn-outline-primary {
            border-color: var(--primary-alpha-50);
        }

        td.actions .btn-outline-primary:hover {
            background-color: var(--primary-alpha-20);
            color: var(--primary-light);
            border-color: var(--primary);
        }

        td.actions .btn-outline-secondary {
            border-color: var(--secondary-alpha-50);
        }

        td.actions .btn-outline-secondary:hover {
            background-color: var(--secondary-alpha-20);
            color: var(--secondary-light);
            border-color: var(--secondary);
        }

        /* Estilo para mensagem de downloads vazios */
        .empty-downloads {
            text-align: center;
            padding: var(--spacing-2xl) var(--spacing-xl);
        }

        .empty-downloads i {
            font-size: 4rem;
            color: var(--primary-alpha-50);
            margin-bottom: var(--spacing-lg);
            display: block;
        }

        .empty-downloads h3 {
            font-weight: var(--font-weight-semibold);
            color: var(--text);
            margin-bottom: var(--spacing-sm);
        }

        .empty-downloads p {
            color: var(--text-secondary);
            max-width: 500px;
            margin: 0 auto var(--spacing-xl);
        }

        .empty-downloads .btn {
            padding: var(--spacing-sm) var(--spacing-xl);
            font-weight: var(--font-weight-medium);
            box-shadow: var(--shadow-md);
            letter-spacing: var(--letter-spacing-wide);
        }

        /* Estilos para a paginação */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: var(--spacing-xl);
        }

        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            background-color: var(--card);
            border-radius: var(--border-radius-full);
            padding: 5px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .pagination .page-item {
            margin: 0 2px;
        }

        .pagination .page-link {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--border-radius-full);
            background-color: transparent;
            color: var(--text-secondary);
            font-weight: var(--font-weight-medium);
            border: none;
            transition: all var(--transition-normal);
            font-size: var(--font-size-sm);
        }

        .pagination .page-link:hover {
            background-color: var(--primary-alpha-20);
            color: var(--primary-light);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary);
            color: var(--dark);
        }

        .pagination .page-item.disabled .page-link {
            color: var(--text-muted);
            pointer-events: none;
            opacity: 0.5;
        }
    </style>
</head>
<body class="dashboard-page">
    <!-- Loading Spinner - Melhorado com logo -->
    <div class="loading">
        <div class="loading-logo"><?php echo SITE_NAME; ?></div>
        <div class="loading-spinner"></div>
    </div>
    
    <!-- Header -->
    <header class="dashboard-header">
        <div class="container">
            <div class="logo">
                <a href="../index.php"><?php echo SITE_NAME; ?></a>
            </div>
            <nav class="dashboard-nav">
            <ul>
                    <li><a href="purchases.php">Comprar Planos</a></li>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="downloads.php"  class="active">Meus Downloads</a></li>
                    <li><a href="support.php">Suporte</a></li>
                    <li><a href="profile.php">Perfil</a></li>
                </ul>
            </nav>
            <div class="user-menu">
                <div class="user-info">
                    <span><?php echo $user['username']; ?></span>
                    <img src="../assets/images/avatar.png" alt="Avatar">
                </div>
                
                <div class="user-dropdown">
                    <header>
                        <h4><?php echo $user['first_name'] ? $user['first_name'] . ' ' . $user['last_name'] : $user['username']; ?></h4>
                        <p><?php echo $user['email']; ?></p>
                    </header>
                    <ul>
                        <li><a href="profile.php"><i class="fas fa-user"></i> Meu Perfil</a></li>
                        <li><a href="settings.php"><i class="fas fa-cog"></i> Configurações</a></li>
                        <li><a href="../includes/logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="dashboard-main">
        <div class="container">
            <div class="page-header">
                <h1>Meus Downloads</h1>
                <p>Histórico de todos os cheats baixados por você</p>
            </div>
            
            <?php if ($has_active_subscription): ?>
                <!-- Estatísticas de Download -->
                <div class="downloads-stats">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-download"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?php echo number_format($download_stats['total_downloads']); ?></h3>
                                    <p>Downloads Totais</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-gamepad"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?php echo number_format($download_stats['unique_cheats']); ?></h3>
                                    <p>Cheats Únicos</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?php echo !empty($download_stats['last_download']) ? date('d/m/Y', strtotime($download_stats['last_download'])) : 'N/A'; ?></h3>
                                    <p>Último Download</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card downloads-chart-card">
                                <canvas id="downloadsChart" height="80"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filter-card">
                    <form action="downloads.php" method="get" class="download-filters">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="search" class="form-label">Pesquisar:</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="search" name="search" placeholder="Nome do cheat ou jogo" value="<?php echo htmlspecialchars($search); ?>">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="game" class="form-label">Filtrar por Jogo:</label>
                                    <select class="form-select" id="game" name="game">
                                        <option value="0">Todos os jogos</option>
                                        <?php foreach ($games as $game): ?>
                                            <option value="<?php echo $game['id']; ?>" <?php echo $filter_game == $game['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($game['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="cheat" class="form-label">Filtrar por Cheat:</label>
                                    <select class="form-select" id="cheat" name="cheat">
                                        <option value="0">Todos os cheats</option>
                                        <?php foreach ($cheats as $cheat): ?>
                                            <option value="<?php echo $cheat['id']; ?>" <?php echo $filter_cheat == $cheat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cheat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="date" class="form-label">Período:</label>
                                            <select class="form-select" id="date" name="date">
                                                <option value="">Qualquer data</option>
                                                <option value="today" <?php echo $filter_date == 'today' ? 'selected' : ''; ?>>Hoje</option>
                                                <option value="week" <?php echo $filter_date == 'week' ? 'selected' : ''; ?>>Última semana</option>
                                                <option value="month" <?php echo $filter_date == 'month' ? 'selected' : ''; ?>>Último mês</option>
                                                <option value="year" <?php echo $filter_date == 'year' ? 'selected' : ''; ?>>Último ano</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="sort" class="form-label">Ordenar por:</label>
                                            <select class="form-select" id="sort" name="sort">
                                                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Mais recentes</option>
                                                <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Mais antigos</option>
                                                <option value="game" <?php echo $sort == 'game' ? 'selected' : ''; ?>>Jogo</option>
                                                <option value="cheat" <?php echo $sort == 'cheat' ? 'selected' : ''; ?>>Cheat</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="filter-actions text-end">
                            <a href="downloads.php" class="btn btn-outline-secondary me-2">Limpar Filtros</a>
                            <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                        </div>
                    </form>
                </div>

                <!-- Tabela de Downloads -->
                <div class="downloads-table">
                    <?php if (empty($downloads)): ?>
                        <div class="empty-downloads">
                            <i class="fas fa-download"></i>
                            <h3>Nenhum download encontrado</h3>
                            <p>
                                <?php if (!empty($search) || $filter_game || $filter_cheat || $filter_date): ?>
                                    Não encontramos downloads que correspondam aos filtros selecionados. Tente modificar os critérios de busca.
                                <?php else: ?>
                                    Você ainda não realizou nenhum download de cheat. Visite a página inicial para ver os cheats disponíveis.
                                <?php endif; ?>
                            </p>
                            <a href="index.php" class="btn btn-primary">Explorar Cheats Disponíveis</a>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Jogo/Cheat</th>
                                    <th>Versão</th>
                                    <th>Data de Download</th>
                                    <th>Endereço IP</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($downloads as $download): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($download['cheat_image'])): ?>
                                                    <img src="../assets/images/cheats/<?php echo htmlspecialchars($download['cheat_image']); ?>" alt="<?php echo htmlspecialchars($download['cheat_name']); ?>" class="cheat-image-sm">
                                                <?php else: ?>
                                                    <div class="cheat-icon-placeholder"><i class="fas fa-gamepad"></i></div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="game-name"><?php echo htmlspecialchars($download['game_name']); ?></div>
                                                    <div class="cheat-name"><?php echo htmlspecialchars($download['cheat_name']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($download['version'])): ?>
                                                <span class="version-badge">v<?php echo htmlspecialchars($download['version']); ?></span>
                                            <?php else: ?>
                                                <span class="version-badge">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="date">
                                            <?php echo date('d/m/Y H:i', strtotime($download['created_at'])); ?>
                                        </td>
                                        <td class="ip">
                                            <?php echo htmlspecialchars($download['ip_address']); ?>
                                        </td>
                                        <td class="actions">
                                            <a href="../downloads/<?php echo htmlspecialchars($download['downloaded_file']); ?>" class="btn btn-sm btn-outline-primary" download>
                                                <i class="fas fa-download"></i> Baixar Novamente
                                            </a>
                                            <a href="cheat_details.php?id=<?php echo $download['cheat_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-info-circle"></i> Detalhes
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Paginação -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-wrapper">
                                <nav aria-label="Navegação de página">
                                    <ul class="pagination">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_game ? '&game=' . $filter_game : ''; ?><?php echo $filter_cheat ? '&cheat=' . $filter_cheat : ''; ?><?php echo $filter_date ? '&date=' . $filter_date : ''; ?><?php echo $sort ? '&sort=' . $sort : ''; ?>" aria-label="Primeira">
                                                    <i class="fas fa-angle-double-left"></i>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_game ? '&game=' . $filter_game : ''; ?><?php echo $filter_cheat ? '&cheat=' . $filter_cheat : ''; ?><?php echo $filter_date ? '&date=' . $filter_date : ''; ?><?php echo $sort ? '&sort=' . $sort : ''; ?>" aria-label="Anterior">
                                                    <i class="fas fa-angle-left"></i>
                                                </a>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link"><i class="fas fa-angle-double-left"></i></span>
                                            </li>
                                            <li class="page-item disabled">
                                                <span class="page-link"><i class="fas fa-angle-left"></i></span>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php
                                        // Exibir no máximo 5 números de página, com o atual no meio quando possível
                                        $start_page = max(1, min($page - 2, $total_pages - 4));
                                        $end_page = min($total_pages, max(5, $page + 2));
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_game ? '&game=' . $filter_game : ''; ?><?php echo $filter_cheat ? '&cheat=' . $filter_cheat : ''; ?><?php echo $filter_date ? '&date=' . $filter_date : ''; ?><?php echo $sort ? '&sort=' . $sort : ''; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_game ? '&game=' . $filter_game : ''; ?><?php echo $filter_cheat ? '&cheat=' . $filter_cheat : ''; ?><?php echo $filter_date ? '&date=' . $filter_date : ''; ?><?php echo $sort ? '&sort=' . $sort : ''; ?>" aria-label="Próxima">
                                                    <i class="fas fa-angle-right"></i>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_game ? '&game=' . $filter_game : ''; ?><?php echo $filter_cheat ? '&cheat=' . $filter_cheat : ''; ?><?php echo $filter_date ? '&date=' . $filter_date : ''; ?><?php echo $sort ? '&sort=' . $sort : ''; ?>" aria-label="Última">
                                                    <i class="fas fa-angle-double-right"></i>
                                                </a>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link"><i class="fas fa-angle-right"></i></span>
                                            </li>
                                            <li class="page-item disabled">
                                                <span class="page-link"><i class="fas fa-angle-double-right"></i></span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Sem assinatura ativa -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <h4 class="alert-heading">Você não possui uma assinatura ativa!</h4>
                        <p>Para acessar o histórico de downloads e baixar cheats, é necessário ter um plano de assinatura ativo.</p>
                        <a href="index.php" class="btn btn-primary mt-2">Ver Planos Disponíveis</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="dashboard-footer">
        <div class="container">
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos os direitos reservados.
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Substitua o código JavaScript atual para o menu do usuário
        document.addEventListener('DOMContentLoaded', function() {
            // Mostrar/Esconder o menu do usuário
            const userMenu = document.querySelector('.user-info');
            const userDropdown = document.querySelector('.user-dropdown');

            if (userMenu && userDropdown) {
                userMenu.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                    console.log('User menu clicked, dropdown toggled');
                });

                // Fechar ao clicar fora
                document.addEventListener('click', function(event) {
                    if (!event.target.closest('.user-menu')) {
                        userDropdown.classList.remove('active');
                    }
                });
            }

            // Filtros de planos
            const filterButtons = document.querySelectorAll('[data-filter]');
            const filterSections = document.querySelectorAll('.filter-section');

            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');

                    // Ativar botão
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    // Filtrar seções
                    if (filter === 'all') {
                        filterSections.forEach(section => section.style.display = 'block');
                    } else {
                        filterSections.forEach(section => {
                            if (section.getAttribute('data-game') === filter) {
                                section.style.display = 'block';
                            } else {
                                section.style.display = 'none';
                            }
                        });
                    }
                });
            });

            // Remover o loader quando a página estiver pronta
            setTimeout(function() {
                document.querySelector('.loading').style.display = 'none';
            }, 1500);

            <?php if (isset($_SESSION['login_success'])): ?>
                showNotification("Login realizado com sucesso!", "success");
                <?php unset($_SESSION['login_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['subscription_success'])): ?>
                showNotification("<?php echo $_SESSION['subscription_success']; ?>", "success");
                <?php unset($_SESSION['subscription_success']); ?>
            <?php endif; ?>

            // Adicione este código após o DOMContentLoaded existente
            console.log('Dashboard script loaded');
            document.querySelector('.user-info')?.addEventListener('click', function() {
                console.log('User menu clicked manually');
            });

            // Efeito de aparecimento progressivo para os itens da tabela
            const tableRows = document.querySelectorAll('.downloads-table tbody tr');
            tableRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(10px)';
                row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                row.style.transitionDelay = `${index * 0.05}s`;
                
                setTimeout(() => {
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, 100);
            });

            // Efeito de pulse nos badges de versão
            const versionBadges = document.querySelectorAll('.version-badge');
            versionBadges.forEach(badge => {
                badge.addEventListener('mouseover', function() {
                    this.style.animation = 'pulse 1s infinite';
                });
                
                badge.addEventListener('mouseout', function() {
                    this.style.animation = 'none';
                });
            });
        });

        // Solução alternativa se o dropdown ainda não funcionar
        document.addEventListener('DOMContentLoaded', function() {
            // Adicionar botão auxiliar para teste
            const userMenu = document.querySelector('.user-info');
            if (userMenu) {
                userMenu.addEventListener('click', function() {
                    const dropdown = document.querySelector('.user-dropdown');
                    if (dropdown) {
                        // Forçar estado visível com estilo inline
                        if (dropdown.style.visibility === 'visible') {
                            dropdown.style.visibility = 'hidden';
                            dropdown.style.opacity = '0';
                        } else {
                            dropdown.style.visibility = 'visible';
                            dropdown.style.opacity = '1';
                            dropdown.style.display = 'block';
                            dropdown.style.zIndex = '9999';
                        }
                    }
                });
            }
        });
    </script>
    
    <script>
        // Script específico apenas para o dropdown do usuário
        document.addEventListener('DOMContentLoaded', function() {
            // Armazenar o dropdown e o botão
            const userDropdown = document.querySelector('.user-dropdown');
            const userInfo = document.querySelector('.user-info');

            // Verificar se os elementos foram encontrados
            if (!userDropdown || !userInfo) {
                console.error('Elementos não encontrados');
                return;
            }

            console.log('Elementos encontrados, adicionando listeners');

            // Aplicar um z-index alto para garantir que apareça acima de outros elementos
            userDropdown.style.zIndex = "9999";

            // Adicionar listener para o clique no ícone do usuário
            userInfo.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Toggle manual da classe active e inline styles
                if (userDropdown.classList.contains('active')) {
                    userDropdown.classList.remove('active');
                    userDropdown.style.opacity = "0";
                    userDropdown.style.visibility = "hidden";
                    userDropdown.style.transform = "translateY(10px)";
                    userDropdown.style.pointerEvents = "none";
                } else {
                    userDropdown.classList.add('active');
                    userDropdown.style.opacity = "1";
                    userDropdown.style.visibility = "visible";
                    userDropdown.style.transform = "translateY(0)";
                    userDropdown.style.pointerEvents = "all";
                }

                console.log('Dropdown toggled:', userDropdown.classList.contains('active'));
            });

            // Fechar ao clicar fora
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.user-menu') && userDropdown.classList.contains('active')) {
                    userDropdown.classList.remove('active');
                    userDropdown.style.opacity = "0";
                    userDropdown.style.visibility = "hidden";
                    userDropdown.style.transform = "translateY(10px)";
                    userDropdown.style.pointerEvents = "none";
                    console.log('Dropdown fechado pelo clique externo');
                }
            });
        });
    </script>
</body>
</html>