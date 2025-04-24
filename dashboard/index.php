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

// Obter detalhes da assinatura ativa (se existir)
$user_subscription = null;
if ($has_active_subscription) {
    $stmt = $db->prepare("
        SELECT s.*, p.name as plan_name, c.name as cheat_name, g.name as game_name 
        FROM user_subscriptions s
        JOIN cheat_subscription_plans p ON s.cheat_plan_id = p.id
        JOIN cheats c ON p.cheat_id = c.id
        JOIN games g ON c.game_id = g.id
        WHERE s.user_id = ? AND s.status = 'active' AND s.end_date > NOW()
        ORDER BY s.created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $user_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obter planos de assinatura disponíveis (agrupados por jogo)
$stmt = $db->prepare("
    SELECT 
        g.id as game_id,
        g.name as game_name,
        g.image as game_image,
        g.description as game_description,
        c.id as cheat_id,
        c.name as cheat_name,
        c.description as cheat_description,
        c.version as cheat_version,
        c.short_description,
        csp.id as plan_id,
        csp.name as plan_name,
        csp.slug as plan_slug,
        csp.description as plan_description,
        csp.price,
        csp.duration_days,
        csp.features,
        csp.is_popular,
        csp.hwid_protection,
        csp.update_frequency,
        csp.support_level,
        csp.discord_access
    FROM games g
    JOIN cheats c ON c.game_id = g.id
    JOIN cheat_subscription_plans csp ON csp.cheat_id = c.id
    WHERE g.is_active = 1 
      AND c.is_active = 1 
      AND csp.is_active = 1
    ORDER BY g.name, c.name, csp.price
");
$stmt->execute();
$all_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar planos por jogos e cheats
$games_cheats = [];
foreach ($all_plans as $plan) {
    $game_name = $plan['game_name'];
    $cheat_name = $plan['cheat_name'];

    // Inicializar estrutura do jogo se não existir
    if (!isset($games_cheats[$game_name])) {
        $games_cheats[$game_name] = [
            'id' => $plan['game_id'],
            'name' => $game_name,
            'image' => $plan['game_image'],
            'description' => $plan['game_description'],
            'cheats' => []
        ];
    }

    // Inicializar estrutura do cheat se não existir
    if (!isset($games_cheats[$game_name]['cheats'][$cheat_name])) {
        $games_cheats[$game_name]['cheats'][$cheat_name] = [
            'id' => $plan['cheat_id'],
            'name' => $cheat_name,
            'description' => $plan['cheat_description'],
            'short_description' => $plan['short_description'],
            'version' => $plan['cheat_version'],
            'plans' => []
        ];
    }

    // Adicionar o plano ao cheat
    $games_cheats[$game_name]['cheats'][$cheat_name]['plans'][] = [
        'id' => $plan['plan_id'],
        'name' => $plan['plan_name'],
        'slug' => $plan['plan_slug'],
        'description' => $plan['plan_description'],
        'price' => $plan['price'],
        'duration_days' => $plan['duration_days'],
        'features' => $plan['features'],
        'is_popular' => $plan['is_popular'],
        'hwid_protection' => $plan['hwid_protection'],
        'update_frequency' => $plan['update_frequency'],
        'support_level' => $plan['support_level'],
        'discord_access' => $plan['discord_access']
    ];
}

// Obter cheats disponíveis para o usuário com base na assinatura atual
$available_cheats = [];
if ($has_active_subscription) {
    $stmt = $db->prepare("
        SELECT c.*, g.name as game_name, g.image as game_image
        FROM cheats c
        JOIN games g ON c.game_id = g.id
        JOIN cheat_subscription_plans csp ON c.id = csp.cheat_id
        JOIN user_subscriptions us ON csp.id = us.cheat_plan_id
        WHERE us.user_id = ? 
          AND us.status = 'active' 
          AND us.end_date > NOW()
          AND c.is_active = 1
        ORDER BY g.name, c.name
    ");
    $stmt->execute([$user_id]);
    $available_cheats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Buscar estatísticas do usuário
$user_stats = [
    'downloads' => 0,
    'active_subscriptions' => 0,
    'days_remaining' => 0
];

// Contagem de downloads
$stmt = $db->prepare("
    SELECT COUNT(*) FROM user_downloads WHERE user_id = ?
");
$stmt->execute([$user_id]);
$user_stats['downloads'] = $stmt->fetchColumn();

// Assinaturas ativas
$stmt = $db->prepare("
    SELECT COUNT(*) FROM user_subscriptions 
    WHERE user_id = ? AND status = 'active' AND end_date > NOW()
");
$stmt->execute([$user_id]);
$user_stats['active_subscriptions'] = $stmt->fetchColumn();

// Dias restantes da assinatura
if ($has_active_subscription) {
    $stmt = $db->prepare("
        SELECT DATEDIFF(end_date, NOW()) as days_remaining
        FROM user_subscriptions 
        WHERE user_id = ? AND status = 'active' AND end_date > NOW()
        ORDER BY end_date DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $user_stats['days_remaining'] = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
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
    <style>
        /* Estilos específicos para o menu do usuário e dropdown - sobreposição */
        .user-menu {
            position: relative !important;
            margin-left: var(--spacing-md) !important;
            z-index: 9999 !important;
            /* Elevado para garantir que fique na frente */
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
            z-index: 9999 !important;
            /* Forçar maior z-index */
            transform: translateY(10px) !important;
            opacity: 0 !important;
            visibility: hidden !important;
            transition: transform 0.3s, opacity 0.3s, visibility 0.3s !important;
            display: block !important;
            /* Forçar display block */
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
            0% {
                opacity: 0.3;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 0.3;
            }
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
                    <li><a href="index.php" class="active">Dashboard</a></li>
                    <li><a href="downloads.php">Meus Downloads</a></li>
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
            <div class="dashboard-welcome">
                <h1>Bem-vindo, <?php echo $user['first_name'] ? $user['first_name'] : $user['username']; ?>!</h1>
                <?php if ($has_active_subscription): ?>
                    <p>Sua assinatura está ativa. Aproveite todos os nossos cheats premium!</p>
                <?php else: ?>
                    <p>Escolha um plano de assinatura para começar a usar nossos cheats premium.</p>
                <?php endif; ?>
            </div>

            <!-- User Stats -->
            <div class="user-stats">
                <div class="row">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-download"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $user_stats['downloads']; ?></h3>
                                <p>Downloads</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $user_stats['active_subscriptions']; ?></h3>
                                <p>Assinaturas Ativas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $user_stats['days_remaining']; ?></h3>
                                <p>Dias Restantes</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($has_active_subscription): ?>
                <!-- Active Subscription -->
                <section class="active-subscription">
                    <h2 class="section-title">Sua Assinatura</h2>
                    <div class="card subscription-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h3 class="subscription-title"><?php echo htmlspecialchars($user_subscription['plan_name']); ?></h3>
                                    <p class="subscription-game">Jogo: <strong><?php echo htmlspecialchars($user_subscription['game_name']); ?></strong></p>
                                    <p class="subscription-cheat">Cheat: <strong><?php echo htmlspecialchars($user_subscription['cheat_name']); ?></strong></p>
                                    <div class="subscription-details">
                                        <div class="detail">
                                            <i class="fas fa-calendar-check"></i>
                                            <span>Início: <?php echo date('d/m/Y', strtotime($user_subscription['start_date'])); ?></span>
                                        </div>
                                        <div class="detail">
                                            <i class="fas fa-calendar-times"></i>
                                            <span>Expira em: <?php echo date('d/m/Y', strtotime($user_subscription['end_date'])); ?></span>
                                        </div>
                                        <div class="detail">
                                            <i class="fas fa-shield-alt"></i>
                                            <span>Status: <span class="badge bg-success">Ativo</span></span>
                                        </div>
                                        <?php if (!empty($user_subscription['hwid'])): ?>
                                            <div class="detail">
                                                <i class="fas fa-microchip"></i>
                                                <span>HWID: <?php echo substr($user_subscription['hwid'], 0, 8) . '...'; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center subscription-actions">
                                    <a href="renew.php?id=<?php echo $user_subscription['id']; ?>" class="btn btn-primary btn-lg mb-2 w-100">Renovar Assinatura</a>
                                    <a href="upgrade.php?id=<?php echo $user_subscription['id']; ?>" class="btn btn-outline-primary btn-lg w-100">Fazer Upgrade</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Available Cheats -->
                <section class="available-cheats">
                    <h2 class="section-title">Cheats Disponíveis</h2>

                    <?php if (empty($available_cheats)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Nenhum cheat disponível para seu plano atual.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($available_cheats as $cheat): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="cheat-card">
                                        <div class="cheat-image">
                                            <?php if (!empty($cheat['image'])): ?>
                                                <img src="../assets/images/cheats/<?php echo $cheat['image']; ?>" alt="<?php echo htmlspecialchars($cheat['name']); ?>">
                                            <?php else: ?>
                                                <img src="../assets/images/defaults/cheat-default.jpg" alt="<?php echo htmlspecialchars($cheat['name']); ?>">
                                            <?php endif; ?>
                                            <span class="game-badge"><?php echo htmlspecialchars($cheat['game_name']); ?></span>
                                        </div>
                                        <div class="cheat-content">
                                            <h3><?php echo htmlspecialchars($cheat['name']); ?></h3>
                                            <p><?php echo htmlspecialchars($cheat['short_description']); ?></p>
                                            <div class="cheat-meta">
                                                <span class="version">v<?php echo $cheat['version']; ?></span>
                                                <span class="update-date">Atualizado: <?php echo date('d/m/Y', strtotime($cheat['updated_at'])); ?></span>
                                            </div>
                                            <div class="cheat-actions">
                                                <a href="download.php?id=<?php echo $cheat['id']; ?>" class="btn btn-primary download-button" 
                                                   data-cheat-id="<?php echo $cheat['id']; ?>" 
                                                   data-cheat-name="<?php echo htmlspecialchars($cheat['name']); ?>">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                                <a href="cheat_details.php?id=<?php echo $cheat['id']; ?>" class="btn btn-outline-secondary">
                                                    <i class="fas fa-info-circle"></i> Detalhes
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <!-- Subscription Plans -->
                <section class="subscription-plans">
                    <h2 class="section-title">Escolha seu Plano</h2>
                    <p class="section-description text-center">Selecione o plano que melhor atende suas necessidades</p>

                    <div class="plan-filters mb-4">
                        <div class="btn-group" role="group" aria-label="Filtros de planos">
                            <button type="button" class="btn btn-outline-primary active" data-filter="all">Todos os Jogos</button>
                            <?php foreach ($games_cheats as $game_name => $game_data): ?>
                                <button type="button" class="btn btn-outline-primary" data-filter="<?php echo 'game-' . $game_data['id']; ?>">
                                    <?php echo htmlspecialchars($game_name); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Exibição dos planos por jogo -->
                    <?php foreach ($games_cheats as $game_name => $game_data): ?>
                        <div class="game-section mb-5 filter-section" data-game="<?php echo 'game-' . $game_data['id']; ?>">
                            <div class="game-header">
                                <div class="d-flex align-items-center mb-3">
                                    <?php if (!empty($game_data['image'])): ?>
                                        <img src="../assets/images/games/<?php echo htmlspecialchars($game_data['image']); ?>" alt="<?php echo htmlspecialchars($game_name); ?>" class="game-image me-3">
                                    <?php endif; ?>
                                    <h2><?php echo htmlspecialchars($game_name); ?></h2>
                                </div>
                                <?php if (!empty($game_data['description'])): ?>
                                    <p class="game-description"><?php echo htmlspecialchars($game_data['description']); ?></p>
                                <?php endif; ?>
                            </div>

                            <!-- Cheats e seus planos -->
                            <?php foreach ($game_data['cheats'] as $cheat_name => $cheat_data): ?>
                                <div class="cheat-section mb-4">
                                    <h3><?php echo htmlspecialchars($cheat_name); ?> <span class="badge bg-info">v<?php echo $cheat_data['version']; ?></span></h3>
                                    <p><?php echo htmlspecialchars($cheat_data['short_description']); ?></p>

                                    <div class="row">
                                        <?php foreach ($cheat_data['plans'] as $plan): ?>
                                            <div class="col-md-4 mb-4">
                                                <div class="plan-card <?php echo $plan['is_popular'] ? 'popular' : ''; ?>">
                                                    <?php if ($plan['is_popular']): ?>
                                                        <div class="popular-badge">Mais Popular</div>
                                                    <?php endif; ?>

                                                    <div class="plan-header">
                                                        <h4 class="plan-title"><?php echo htmlspecialchars($plan['name']); ?></h4>
                                                        <div class="plan-price">
                                                            <span class="currency">R$</span>
                                                            <span class="amount"><?php echo number_format($plan['price'], 2, ',', '.'); ?></span>
                                                            <span class="period">/<?php echo $plan['duration_days'] == 30 ? 'mês' : $plan['duration_days'] . ' dias'; ?></span>
                                                        </div>
                                                    </div>

                                                    <div class="plan-features">
                                                        <ul>
                                                            <?php
                                                            if (!empty($plan['features'])) {
                                                                $features = explode(';', $plan['features']);
                                                                foreach ($features as $feature):
                                                            ?>
                                                                    <li>
                                                                        <i class="fas fa-check"></i>
                                                                        <?php echo htmlspecialchars(trim($feature)); ?>
                                                                    </li>
                                                            <?php
                                                                endforeach;
                                                            }
                                                            ?>

                                                            <?php if ($plan['hwid_protection']): ?>
                                                                <li><i class="fas fa-shield-alt"></i> Proteção HWID</li>
                                                            <?php endif; ?>

                                                            <?php if ($plan['update_frequency'] == 'daily'): ?>
                                                                <li><i class="fas fa-sync"></i> Atualizações diárias</li>
                                                            <?php elseif ($plan['update_frequency'] == 'weekly'): ?>
                                                                <li><i class="fas fa-sync"></i> Atualizações semanais</li>
                                                            <?php else: ?>
                                                                <li><i class="fas fa-sync"></i> Atualizações mensais</li>
                                                            <?php endif; ?>

                                                            <?php if ($plan['support_level'] == 'vip'): ?>
                                                                <li><i class="fas fa-headset"></i> Suporte VIP 24/7</li>
                                                            <?php elseif ($plan['support_level'] == 'priority'): ?>
                                                                <li><i class="fas fa-headset"></i> Suporte prioritário</li>
                                                            <?php else: ?>
                                                                <li><i class="fas fa-headset"></i> Suporte básico</li>
                                                            <?php endif; ?>

                                                            <?php if ($plan['discord_access']): ?>
                                                                <li><i class="fab fa-discord"></i> Acesso ao Discord VIP</li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>

                                                    <div class="plan-footer">
                                                        <a href="checkout.php?plan=<?php echo $plan['id']; ?>"
                                                            class="btn btn-primary btn-subscribe">
                                                            Assinar Agora
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </section>
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
    <script src="../assets/js/downloads.js"></script>
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

    <script>
        // Em assets/js/downloads.js
        const DownloadManager = {
            init: function() {
                this.setupDownloadButtons();
            },
            
            setupDownloadButtons: function() {
                const downloadButtons = document.querySelectorAll('.download-button');
                
                downloadButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        const cheatId = this.getAttribute('data-cheat-id');
                        const cheatName = this.getAttribute('data-cheat-name');
                        
                        // Confirmar download, mas não impedir ação padrão
                        if (!confirm(`Você está prestes a baixar ${cheatName}. Continuar?`)) {
                            e.preventDefault();
                            return false;
                        } 
                        
                        // Se confirmado, permitir o download (comportamento padrão)
                        console.log(`Download iniciado: ${cheatName}`);
                    });
                });
            }
        };

        // Inicializar após carregamento do DOM
        document.addEventListener('DOMContentLoaded', function() {
            DownloadManager.init();
        });
    </script>
</body>

</html>