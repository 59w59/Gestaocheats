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
function get_subscriptions_by_status($db, $status) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_subscriptions WHERE status = ?");
    $stmt->execute([$status]);
    return $stmt->fetchColumn();
}

function get_status_color($status) {
    switch ($status) {
        case 'active':
            return 'success';
        case 'expired':
            return 'warning';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}

function format_date($date) {
    if (!$date) return 'N/A';
    return date('d/m/Y H:i', strtotime($date));
}

// Configuração de paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$plan = isset($_GET['plan']) ? $_GET['plan'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';

try {
    // Construir a consulta SQL usando a nova tabela cheat_subscription_plans
    $sql = "SELECT s.*, u.username, u.email, p.name as plan_name, p.price 
            FROM user_subscriptions s 
            JOIN users u ON s.user_id = u.id 
            JOIN cheat_subscription_plans p ON s.cheat_plan_id = p.id 
            WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR s.payment_id LIKE ?)";
        $search_param = "%$search%";
        $params = array_fill(0, 3, $search_param);
    }

    if (in_array($status, ['active', 'expired', 'cancelled'])) {
        $sql .= " AND s.status = ?";
        $params[] = $status;
    }

    if (!empty($plan)) {
        $sql .= " AND s.cheat_plan_id = ?";
        $params[] = $plan;
    }

    // Contagem total para paginação
    $count_sql = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(*) FROM', $sql);
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_subscriptions = $stmt->fetchColumn();
    $total_pages = ceil($total_subscriptions / $per_page);

    // Ordenação
    $allowed_sort_fields = ['created_at', 'end_date', 'status', 'username', 'plan_name'];
    $allowed_order = ['ASC', 'DESC'];

    $sort = in_array($sort, $allowed_sort_fields) ? $sort : 'created_at';
    $order = in_array($order, $allowed_order) ? $order : 'DESC';

    $sort_field_mapping = [
        'created_at' => 's.created_at',
        'end_date' => 's.end_date',
        'status' => 's.status',
        'username' => 'u.username',
        'plan_name' => 'p.name'
    ];

    $sql .= " ORDER BY " . $sort_field_mapping[$sort] . " $order LIMIT $per_page OFFSET $offset";

    // Executar a consulta final
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Criar estatísticas de assinaturas
    $subscription_stats = [
        'total' => $total_subscriptions,
        'active' => get_subscriptions_by_status($db, 'active'),
        'expired' => get_subscriptions_by_status($db, 'expired'),
        'cancelled' => get_subscriptions_by_status($db, 'cancelled')
    ];

} catch (PDOException $e) {
    error_log("Error in subscriptions.php: " . $e->getMessage());
    $error_message = "Ocorreu um erro ao carregar as assinaturas. Por favor, tente novamente.";
    $subscription_stats = ['total' => 0, 'active' => 0, 'expired' => 0, 'cancelled' => 0];
    $total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Assinaturas - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="../../assets/css/variables.css">
    <link rel="stylesheet" href="../../assets/css/AdminPanelStyles.css">
    <link rel="stylesheet" href="../../assets/css/custom.css">
    <link rel="stylesheet" href="../../assets/css/scroll.css">
</head>

<body class="admin-page">
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-content">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Estatísticas -->
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($subscription_stats['total']); ?></h2>
                        <p>Total de Assinaturas</p>
                    </div>
                </div>
                
                <div class="admin-stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($subscription_stats['active']); ?></h2>
                        <p>Assinaturas Ativas</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($subscription_stats['expired']); ?></h2>
                        <p>Assinaturas Expiradas</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-danger">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($subscription_stats['cancelled']); ?></h2>
                        <p>Assinaturas Canceladas</p>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h3>Filtros e Ações</h3>
                    <a href="./funcaosubscriptons/subscription_add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nova Assinatura
                    </a>
                </div>
                <div class="admin-card-body">
                    <form action="subscriptions.php" method="GET" class="filter-form">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="search">Pesquisar</label>
                                    <input type="text" name="search" id="search" class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Username, email ou ID">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Ativas</option>
                                        <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expiradas</option>
                                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Canceladas</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="sort">Ordenar por</label>
                                    <select name="sort" id="sort" class="form-select">
                                        <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Data de Criação</option>
                                        <option value="end_date" <?php echo $sort === 'end_date' ? 'selected' : ''; ?>>Data de Expiração</option>
                                        <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>Username</option>
                                        <option value="plan_name" <?php echo $sort === 'plan_name' ? 'selected' : ''; ?>>Plano</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="order">Ordem</label>
                                    <select name="order" id="order" class="form-select">
                                        <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Decrescente</option>
                                        <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Crescente</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                            <a href="subscriptions.php" class="btn btn-outline-secondary">Limpar Filtros</a>
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
                                    <th>Usuário</th>
                                    <th>Plano</th>
                                    <th>Status</th>
                                    <th>Início</th>
                                    <th>Término</th>
                                    <th>Valor</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($subscriptions)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="empty-state">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <p>Nenhuma assinatura encontrada</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($subscriptions as $subscription): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subscription['id']); ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <span class="username"><?php echo htmlspecialchars($subscription['username']); ?></span>
                                                    <span class="email"><?php echo htmlspecialchars($subscription['email']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($subscription['plan_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo get_status_color($subscription['status']); ?>">
                                                    <?php echo ucfirst($subscription['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo format_date($subscription['created_at']); ?></td>
                                            <td><?php echo format_date($subscription['end_date']); ?></td>
                                            <td>R$ <?php echo number_format($subscription['price'], 2, ',', '.'); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="subscription_edit.php?id=<?php echo $subscription['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="confirmDelete(<?php echo $subscription['id']; ?>)">
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
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                        <a class="page-link" 
                                           href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin.js"></script>
</body>
</html>