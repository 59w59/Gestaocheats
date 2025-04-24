<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';
require_once '../includes/admin_auth.php';

// Verificar se o administrador está logado
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    // Redirecionar para a página de login se não estiver logado
    header('Location: login.php');
    exit();
}

// Add this after the session check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    // If last activity was more than 30 minutes ago
    session_destroy();
    header('Location: login.php?error=session_expired');
    exit();
}
$_SESSION['last_activity'] = time(); // Update last activity time

// Obter dados do administrador com verificação de segurança
$admin_id = filter_var($_SESSION['admin_id'], FILTER_SANITIZE_NUMBER_INT);
$stmt = $db->prepare("SELECT id, username, email FROM admins WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar se o admin existe e está ativo
if (!$admin) {
    // Destruir sessão e redirecionar se o admin não for válido
    session_destroy();
    header('Location: login.php?error=invalid_session');
    exit();
}

// Obter estatísticas gerais
$stats = [
    'total_users' => get_total_users(),
    'active_subscriptions' => get_active_subscriptions(),
    'recent_registrations' => get_recent_registrations(7),
    'monthly_revenue' => get_monthly_revenue(),
    'total_downloads' => get_total_downloads(),
    'pending_support_tickets' => get_pending_support_tickets()
];

// Obter dados para os gráficos
$revenue_chart_data = get_revenue_chart_data(30);
$users_chart_data = get_users_chart_data(30);
$downloads_chart_data = get_downloads_chart_data(30);

// Obter atividades recentes
$recent_activities = get_recent_activities(10);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Apex Charts - Versão atualizada -->
    <link href="https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.css" rel="stylesheet">
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/AdminPanelStyles.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <link rel="stylesheet" href="../assets/css/scroll.css">
</head>

<body class="admin-page">
    <aside class="admin-sidebar">
        <div class="admin-sidebar-header">
            <div class="admin-logo">
                <span class="logo-text"><?php echo SITE_NAME; ?></span>
                <span class="admin-badge">ADMIN</span>
            </div>
            <button class="sidebar-toggle d-md-none">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <nav class="admin-nav">
            <div class="nav-section">
                <h6 class="nav-section-title">Principal</h6>
                <ul>
                    <li>
                        <a href="/Gestaocheats/admin/index.php" 
                           class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="/Gestaocheats/admin/pages/users.php" 
                           class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i> Usuários
                        </a>
                    </li>
                </ul>
            </div>

            <div class="nav-section">
                <h6 class="nav-section-title">Gerenciamento</h6>
                <ul>
                    <li>
                        <a href="/Gestaocheats/admin/pages/subscriptions.php" 
                           class="<?php echo basename($_SERVER['PHP_SELF']) == 'subscriptions.php' ? 'active' : ''; ?>">
                            <i class="fas fa-credit-card"></i> Assinaturas
                        </a>
                    </li>
                    <li>
                        <a href="/Gestaocheats/admin/pages/plans.php" 
                           class="<?php echo basename($_SERVER['PHP_SELF']) == 'plans.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tags"></i> Planos
                        </a>
                    </li>
                </ul>
            </div>

            <div class="nav-section">
                <h6 class="nav-section-title">Conteúdo</h6>
                <ul>
                    <li>
                        <a href="/Gestaocheats/admin/pages/games.php" 
                           class="<?php echo basename($_SERVER['PHP_SELF']) == 'games.php' ? 'active' : ''; ?>">
                            <i class="fas fa-gamepad"></i> Jogos
                        </a>
                    </li>
                    <li>
                        <a href="/Gestaocheats/admin/pages/cheats.php" 
                           class="<?php echo basename($_SERVER['PHP_SELF']) == 'cheats.php' ? 'active' : ''; ?>">
                            <i class="fas fa-code"></i> Cheats
                        </a>
                    </li>
                </ul>
            </div>

            <div class="nav-section">
                <h6 class="nav-section-title">Sistema</h6>
                <ul>
                    <li>
                        <a href="/Gestaocheats/admin/pages/transactions.php" 
                           class="<?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>">
                            <i class="fas fa-money-bill-wave"></i> Transações
                        </a>
                    </li>
                    <li>
                        <a href="/Gestaocheats/admin/pages/support.php" 
                           class="<?php echo basename($_SERVER['PHP_SELF']) == 'support.php' ? 'active' : ''; ?>">
                            <i class="fas fa-headset"></i> Suporte
                            <?php if (function_exists('get_pending_support_tickets') && get_pending_support_tickets() > 0): ?>
                                <span class="badge bg-danger"><?php echo get_pending_support_tickets(); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="/Gestaocheats/admin/pages/logs.php" 
                           class="<?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i> Logs
                        </a>
                    </li>
                    <li>
                        <a href="/Gestaocheats/admin/pages/settings.php" 
                           class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                            <i class="fas fa-cog"></i> Configurações
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="admin-sidebar-footer">
            <div class="admin-user-info">
            <a href="/Gestaocheats/admin/logout.php" class="btn btn-logout" title="Sair">
                <i class="fas fa-sign-out-alt"></i> <span class="btn-text">Sair</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="admin-main">
        <!-- Header -->
        <header class="admin-header">
            <div class="admin-header-left">
                <button class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="admin-page-title">Dashboard</h1>
            </div>
            <div class="admin-header-right">
                <div class="admin-search">
                    <form action="pages/search.php" method="GET">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" name="q" class="form-control" placeholder="Buscar...">
                        </div>
                    </form>
                </div>
                <div class="admin-notifications">
                    <a href="#" class="dropdown-toggle">
                        <i class="fas fa-bell"></i>
                        <?php if ($stats['pending_support_tickets'] > 0): ?>
                            <span class="badge bg-danger"><?php echo $stats['pending_support_tickets']; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu">
                        <div class="dropdown-header">Notificações</div>
                        <div class="dropdown-items">
                            <?php
                            // Obter notificações recentes
                            $notifications = get_admin_notifications(5);
                            if (!empty($notifications)):
                                foreach ($notifications as $notification):
                            ?>
                                    <a href="<?php echo $notification['link']; ?>" class="dropdown-item">
                                        <div class="notification-icon <?php echo $notification['icon_class']; ?>">
                                            <i class="<?php echo $notification['icon']; ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <p><?php echo $notification['message']; ?></p>
                                            <span class="notification-time"><?php echo time_elapsed_string($notification['created_at']); ?></span>
                                        </div>
                                    </a>
                                <?php
                                endforeach;
                            else:
                                ?>
                                <div class="dropdown-item empty">
                                    <p>Nenhuma notificação recente</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-footer">
                            <a href="pages/notifications.php">Ver todas</a>
                        </div>
                    </div>
                </div>
                <div class="admin-user">
                    <a href="#" class="dropdown-toggle">
                        <img src="assets/images/admin-avatar.png" alt="Admin">
                        <span><?php echo $admin['username']; ?></span>
                    </a>
                    <div class="dropdown-menu">
                        <a href="pages/profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Perfil
                        </a>
                        <a href="pages/settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i> Configurações
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Sair
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="admin-content">
            <!-- Stat Cards -->
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['total_users']); ?></h2>
                        <p>Usuários</p>
                    </div>
                    <div class="stat-footer">
                        <span class="<?php echo $stats['recent_registrations'] > 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $stats['recent_registrations'] > 0 ? '+' : ''; ?><?php echo $stats['recent_registrations']; ?>
                        </span>
                        <span>nos últimos 7 dias</span>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['active_subscriptions']); ?></h2>
                        <p>Assinaturas ativas</p>
                    </div>
                    <div class="stat-footer">
                        <a href="pages/subscriptions.php" class="stat-link">Ver detalhes <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h2>R$ <?php echo number_format($stats['monthly_revenue'], 2, ',', '.'); ?></h2>
                        <p>Receita mensal</p>
                    </div>
                    <div class="stat-footer">
                        <a href="pages/transactions.php" class="stat-link">Ver relatório <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['total_downloads']); ?></h2>
                        <p>Downloads totais</p>
                    </div>
                    <div class="stat-footer">
                        <a href="pages/downloads.php" class="stat-link">Ver detalhes <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="admin-charts-row">
                <div class="admin-chart-card">
                    <div class="chart-header">
                        <h3>Receita (Últimos 30 dias)</h3>
                        <div class="chart-actions">
                            <button class="chart-action" data-range="7">7d</button>
                            <button class="chart-action active" data-range="30">30d</button>
                            <button class="chart-action" data-range="90">90d</button>
                        </div>
                    </div>
                    <div class="chart-body">
                        <div id="revenueChart"></div>
                    </div>
                </div>

                <div class="admin-chart-card">
                    <div class="chart-header">
                        <h3>Usuários vs. Assinaturas</h3>
                        <div class="chart-actions">
                            <button class="chart-action" data-range="7">7d</button>
                            <button class="chart-action active" data-range="30">30d</button>
                            <button class="chart-action" data-range="90">90d</button>
                        </div>
                    </div>
                    <div class="chart-body">
                        <div id="usersChart"></div>
                    </div>
                </div>
            </div>

            <!-- Additional Content Row -->
            <div class="admin-content-row">
                <div class="admin-table-card">
                    <div class="card-header">
                        <h3>Atividades Recentes</h3>
                        <a href="pages/logs.php" class="btn btn-sm btn-primary">Ver Todos</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Usuário</th>
                                        <th>Ação</th>
                                        <th>Data/Hora</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <img src="<?php echo get_user_avatar($activity['user_id']); ?>" alt="Avatar" class="user-avatar">
                                                    <span><?php echo $activity['username']; ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="activity-label 
                                                <?php
                                                if (strpos($activity['action'], 'login') !== false) echo 'bg-info';
                                                else if (strpos($activity['action'], 'download') !== false) echo 'bg-success';
                                                else if (strpos($activity['action'], 'subscribe') !== false) echo 'bg-primary';
                                                else echo 'bg-secondary';
                                                ?>
                                            ">
                                                    <?php echo $activity['action']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></td>
                                            <td><?php echo isset($activity['ip']) ? $activity['ip'] : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="admin-side-card">
                    <div class="card-header">
                        <h3>Últimos Usuários</h3>
                    </div>
                    <div class="card-body">
                        <ul class="recent-users-list">
                            <?php
                            $recent_users = get_recent_users(5);
                            foreach ($recent_users as $user):
                            ?>
                                <li>
                                    <div class="user-info">
                                        <img src="<?php echo get_user_avatar($user['id']); ?>" alt="Avatar" class="user-avatar">
                                        <div class="user-details">
                                            <span class="user-name"><?php echo $user['username']; ?></span>
                                            <span class="user-date"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <a href="pages/user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="card-footer">
                        <a href="pages/users.php" class="btn btn-sm btn-primary btn-block">Ver Todos Usuários</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="admin-footer">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - Painel Administrativo</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p>Versão 1.0.0</p>
                    </div>
                </div>
            </div>
        </footer>
    </main>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Apex Charts - Versão atualizada -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.min.js"></script>
    <script>
        // Inicialização dos gráficos
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Inicializando gráficos...');

            // Dados para os gráficos
            const revenueData = <?php echo json_encode($revenue_chart_data); ?>;
            const usersData = <?php echo json_encode($users_chart_data); ?>;
            
            // Configuração do gráfico de receita
            const revenueOptions = {
                series: [{
                    name: 'Receita',
                    data: revenueData.values
                }],
                chart: {
                    type: 'area',
                    height: 350,
                    toolbar: {
                        show: false
                    },
                    theme: 'dark' // Add this line to set the theme to dark
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    curve: 'smooth',
                    width: 2
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.7,
                        opacityTo: 0.3
                    }
                },
                xaxis: {
                    categories: revenueData.dates,
                    labels: {
                        style: {
                            colors: '#8e8da4'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        formatter: function(val) {
                            return 'R$ ' + val.toFixed(2);
                        },
                        style: {
                            colors: '#8e8da4'
                        }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return 'R$ ' + val.toFixed(2);
                        }
                    },
                    theme: 'dark' // Add this line to set tooltip theme to dark
                }
            };
            // Configuração do gráfico de usuários
            const usersOptions = {
                series: [{
                    name: 'Usuários',
                    data: usersData.new_users
                }, {
                    name: 'Assinaturas',
                    data: usersData.new_subscriptions
                }],
                chart: {
                    type: 'line',
                    height: 350,
                    toolbar: {
                        show: false
                    },
                    theme: 'dark'
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    width: [2, 2],
                    curve: 'smooth'
                },
                xaxis: {
                    categories: usersData.dates,
                    labels: {
                        style: {
                            colors: '#8e8da4'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: '#8e8da4'
                        }
                    }
                },
                tooltip: {
                    theme: 'dark'
                },
                legend: {
                    position: 'top'
                }
            };
            
            // Fechar dropdowns ao clicar fora
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown-toggle') && !e.target.closest('.dropdown-menu')) {
                    document.querySelectorAll('.admin-notifications, .admin-user').forEach(function(container) {
                        container.classList.remove('show');
                    });
                }
            });
            
            // Renderizar os gráficos
            if (document.getElementById('revenueChart')) {
                const revenueChart = new ApexCharts(document.getElementById('revenueChart'), revenueOptions);
                revenueChart.render();
            }
            
            if (document.getElementById('usersChart')) {
                const usersChart = new ApexCharts(document.getElementById('usersChart'), usersOptions);
                usersChart.render();
            }
            
            // Adicionar eventos aos botões de intervalo de tempo
            document.querySelectorAll('.chart-action').forEach(button => {
                button.addEventListener('click', function() {
                    const range = this.getAttribute('data-range');
                    const chartType = this.closest('.admin-chart-card').querySelector('.chart-header h3').textContent.includes('Receita') ? 'revenue' : 'users';
                    
                    // Remover classe ativa de todos os botões no mesmo grupo
                    this.closest('.chart-actions').querySelectorAll('.chart-action').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    
                    // Adicionar classe ativa ao botão clicado
                    this.classList.add('active');
                    
                    // Aqui você pode implementar a lógica para atualizar os dados do gráfico
                    console.log(`Alterando intervalo do gráfico ${chartType} para ${range} dias`);
                });
            });
        });
    </script>
</body>

</html>