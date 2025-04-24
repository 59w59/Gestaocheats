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

// Configuração de paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';

try {
    // Construir a consulta SQL
    $sql = "SELECT p.*, u.username, u.email, s.plan_id, sp.name as plan_name 
            FROM payments p 
            JOIN users u ON p.user_id = u.id 
            JOIN user_subscriptions s ON p.subscription_id = s.id 
            JOIN subscription_plans sp ON s.plan_id = sp.id 
            WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR p.transaction_id LIKE ?)";
        $search_param = "%$search%";
        $params = array_fill(0, 3, $search_param);
    }

    if (!empty($status)) {
        $sql .= " AND p.status = ?";
        $params[] = $status;
    }

    if (!empty($payment_method)) {
        $sql .= " AND p.payment_method = ?";
        $params[] = $payment_method;
    }

    if (!empty($date_start)) {
        $sql .= " AND DATE(p.created_at) >= ?";
        $params[] = $date_start;
    }

    if (!empty($date_end)) {
        $sql .= " AND DATE(p.created_at) <= ?";
        $params[] = $date_end;
    }

    // Contagem total para paginação
    $count_sql = str_replace("SELECT p.*, u.username", "SELECT COUNT(*)", $sql);
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_transactions = $stmt->fetchColumn();
    $total_pages = ceil($total_transactions / $per_page);

    // Ordenação
    $allowed_sort_fields = ['created_at', 'amount', 'status', 'payment_method'];
    $sort = in_array($sort, $allowed_sort_fields) ? $sort : 'created_at';
    $order = in_array($order, ['ASC', 'DESC']) ? $order : 'DESC';

    $sql .= " ORDER BY p.$sort $order LIMIT $per_page OFFSET $offset";

    // Executar a consulta final
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obter estatísticas
    $stats = [
        'total_revenue' => get_total_revenue(),
        'monthly_revenue' => get_monthly_revenue(),
        'successful_transactions' => get_transactions_by_status('completed'),
        'pending_transactions' => get_transactions_by_status('pending')
    ];

} catch (PDOException $e) {
    error_log("Error in transactions.php: " . $e->getMessage());
    $error_message = "Ocorreu um erro ao carregar as transações. Por favor, tente novamente.";
}

// Helper functions
function get_status_color($status) {
    switch ($status) {
        case 'completed':
            return 'success';
        case 'pending':
            return 'warning';
        case 'failed':
            return 'danger';
        case 'refunded':
            return 'info';
        default:
            return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Transações - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h2>R$ <?php echo number_format($stats['total_revenue'], 2, ',', '.'); ?></h2>
                        <p>Receita Total</p>
                    </div>
                </div>
                
                <div class="admin-stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h2>R$ <?php echo number_format($stats['monthly_revenue'], 2, ',', '.'); ?></h2>
                        <p>Receita Mensal</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['successful_transactions']); ?></h2>
                        <p>Transações Concluídas</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['pending_transactions']); ?></h2>
                        <p>Transações Pendentes</p>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h3>Filtros</h3>
                </div>
                <div class="admin-card-body">
                    <form action="transactions.php" method="GET" class="filter-form">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="search">Pesquisar</label>
                                    <input type="text" name="search" id="search" class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Username, email ou ID da transação">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Concluídas</option>
                                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendentes</option>
                                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Falhas</option>
                                        <option value="refunded" <?php echo $status === 'refunded' ? 'selected' : ''; ?>>Reembolsadas</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="payment_method">Método de Pagamento</label>
                                    <select name="payment_method" id="payment_method" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="credit_card" <?php echo $payment_method === 'credit_card' ? 'selected' : ''; ?>>Cartão de Crédito</option>
                                        <option value="pix" <?php echo $payment_method === 'pix' ? 'selected' : ''; ?>>PIX</option>
                                        <option value="boleto" <?php echo $payment_method === 'boleto' ? 'selected' : ''; ?>>Boleto</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_start">Data Inicial</label>
                                    <input type="date" name="date_start" id="date_start" class="form-control" 
                                           value="<?php echo $date_start; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                            <a href="transactions.php" class="btn btn-outline-secondary">Limpar Filtros</a>
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
                                    <th>Valor</th>
                                    <th>Método</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="empty-state">
                                                <i class="fas fa-money-bill-wave fa-3x mb-3"></i>
                                                <p>Nenhuma transação encontrada</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <span class="username"><?php echo htmlspecialchars($transaction['username']); ?></span>
                                                    <span class="email"><?php echo htmlspecialchars($transaction['email']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaction['plan_name']); ?></td>
                                            <td>R$ <?php echo number_format($transaction['amount'], 2, ',', '.'); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucfirst($transaction['payment_method']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo get_status_color($transaction['status']); ?>">
                                                    <?php echo ucfirst($transaction['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="transaction_details.php?id=<?php echo $transaction['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($transaction['status'] === 'completed'): ?>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-warning"
                                                                onclick="confirmRefund(<?php echo $transaction['id']; ?>)">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                    <?php endif; ?>
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
                                           href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&payment_method=<?php echo $payment_method; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmRefund(id) {
            if (confirm('Tem certeza que deseja reembolsar esta transação?')) {
                window.location.href = `transaction_refund.php?id=${id}`;
            }
        }
    </script>
</body>
</html>