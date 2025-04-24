<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';
require_once '../../includes/admin_auth.php';

// Verificar se o administrador está logado
if (!is_admin_logged_in()) {
    header('Location: ../login.php');
    exit;
}

// Helper functions
function get_total_plans($db) {
    $stmt = $db->query("SELECT COUNT(*) FROM cheat_subscription_plans");
    return $stmt->fetchColumn();
}

function get_active_plans_count($db) {
    $stmt = $db->query("SELECT COUNT(*) FROM cheat_subscription_plans WHERE is_active = 1");
    return $stmt->fetchColumn();
}

function get_total_subscribers($db) {
    $stmt = $db->query("SELECT COUNT(DISTINCT user_id) FROM user_subscriptions WHERE status = 'active'");
    return $stmt->fetchColumn();
}

function get_monthly_revenue_from_plans($db) {
    $stmt = $db->query("
        SELECT SUM(csp.price) 
        FROM user_subscriptions us
        JOIN cheat_subscription_plans csp ON us.cheat_plan_id = csp.id 
        WHERE us.status = 'active'
        AND us.start_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    ");
    return $stmt->fetchColumn() ?: 0;
}

// Validate and set sort fields
$allowed_sort_fields = ['name', 'price', 'created_at', 'is_active', 'active_subscribers'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_fields) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';

// Get statistics
$stats = [
    'total' => get_total_plans($db),
    'active' => get_active_plans_count($db),
    'total_subscribers' => get_total_subscribers($db),
    'monthly_revenue' => get_monthly_revenue_from_plans($db)
];

// Paginação
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

try {
    // Simplifique drasticamente a consulta SQL para fins de diagnóstico
    $sql = "SELECT * FROM cheat_subscription_plans";
    $stmt = $db->query($sql);  // Use query direta sem prepared statement
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug
    error_log("Total de planos no banco: " . count($plans));
    
    // Adicione valores padrão para subcampos que podem estar faltando
    foreach ($plans as $key => $plan) {
        $plans[$key]['active_subscribers'] = 0; // Valor padrão
    }
    
    // Calcular paginação corretamente
    $total_records = count($plans);
    $total_pages = ceil($total_records / $per_page);
    
    // Limitar os resultados para a página atual (simulando LIMIT e OFFSET)
    if ($total_records > 0) {
        $start = ($page - 1) * $per_page;
        $end = min($start + $per_page, $total_records);
        $plans = array_slice($plans, $start, $per_page);
    }
} catch (PDOException $e) {
    error_log("SQL Error: " . $e->getMessage());
    echo '<!-- SQL Error: ' . htmlspecialchars($e->getMessage()) . ' -->';
    $plans = [];
    $total_records = 0;
    $total_pages = 1; // Definir valor padrão
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Planos - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/variables.css">
    <link rel="stylesheet" href="../../assets/css/AdminPanelStyles.css">
    <link rel="stylesheet" href="../../assets/css/custom.css">
    <link rel="stylesheet" href="../../assets/css/scroll.css">
</head>

<body class="admin-page">
    <button class="sidebar-toggle d-md-none">
        <i class="fas fa-bars"></i>
    </button>

    <?php include '../includes/admin-sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                        echo htmlspecialchars($_SESSION['success']); 
                        unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                        echo htmlspecialchars($_SESSION['error']); 
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Estatísticas -->
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['total']); ?></h2>
                        <p>Total de Planos</p>
                    </div>
                </div>
                
                <div class="admin-stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['active']); ?></h2>
                        <p>Planos Ativos</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['total_subscribers']); ?></h2>
                        <p>Assinantes Ativos</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h2>R$ <?php echo number_format($stats['monthly_revenue'], 2, ',', '.'); ?></h2>
                        <p>Receita Mensal</p>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h3>Filtros e Ações</h3>
                    <a href="./funcaoplan/plan_add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Novo Plano
                    </a>
                </div>
                <div class="admin-card-body">
                    <form action="plans.php" method="GET" class="filter-form">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="search">Pesquisar</label>
                                    <input type="text" name="search" id="search" class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Nome ou descrição">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Ativos</option>
                                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inativos</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="sort">Ordenar por</label>
                                    <select name="sort" id="sort" class="form-select">
                                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nome</option>
                                        <option value="price" <?php echo $sort === 'price' ? 'selected' : ''; ?>>Preço</option>
                                        <option value="active_subscribers" <?php echo $sort === 'active_subscribers' ? 'selected' : ''; ?>>Assinantes</option>
                                        <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Data de Criação</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                            <a href="plans.php" class="btn btn-outline-secondary">Limpar Filtros</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabela -->
            <div class="admin-card">
                <div class="admin-card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Preço</th>
                                    <th>Assinantes</th>
                                    <th>Status</th>
                                    <th>Criado em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($plans)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="empty-state">
                                                <i class="fas fa-tags fa-3x mb-3"></i>
                                                <p>Nenhum plano encontrado</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($plans as $plan): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($plan['id']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($plan['name']); ?></strong>
                                                <?php if (isset($plan['is_popular']) && $plan['is_popular']): ?>
                                                    <span class="badge bg-success ms-2">Popular</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>R$ <?php echo number_format($plan['price'], 2, ',', '.'); ?></td>
                                            <td><?php echo number_format($plan['active_subscribers'] ?? 0); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo isset($plan['is_active']) && $plan['is_active'] ? 'success' : 'warning'; ?>">
                                                    <?php echo isset($plan['is_active']) && $plan['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($plan['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="./funcaoplan/plan_edit.php?id=<?php echo $plan['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="confirmDelete(<?php echo $plan['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Navegação de página">
                            <ul class="pagination justify-content-center">
                                <!-- First and Previous buttons -->
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>

                                <!-- Page numbers -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                    <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php 
                                endfor;
                                
                                if ($end_page < $total_pages) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                ?>

                                <!-- Next and Last buttons -->
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sort handlers
            const sortLinks = document.querySelectorAll('[data-sort]');
            if (sortLinks && sortLinks.length > 0) {
                sortLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const sort = this.dataset.sort;
                        const currentOrder = new URLSearchParams(window.location.search).get('order') || 'DESC';
                        const newOrder = currentOrder === 'DESC' ? 'ASC' : 'DESC';
                        
                        const url = new URL(window.location.href);
                        url.searchParams.set('sort', sort);
                        url.searchParams.set('order', newOrder);
                        window.location.href = url.toString();
                    });
                });
            }
        });

        function confirmDelete(id) {
            if (confirm('Tem certeza que deseja excluir este plano?')) {
                window.location.href = `./funcaoplan/plan_delete.php?id=${id}`;
            }
        }
    </script>
</body>
</html>