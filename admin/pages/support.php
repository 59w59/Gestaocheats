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
$priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';

try {
    // Construir a consulta SQL
    $sql = "SELECT t.*, u.username, u.email 
            FROM support_tickets t 
            LEFT JOIN users u ON t.user_id = u.id 
            WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (t.title LIKE ? OR t.ticket_id LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
        $search_param = "%$search%";
        $params = array_fill(0, 4, $search_param);
    }

    if (!empty($status)) {
        $sql .= " AND t.status = ?";
        $params[] = $status;
    }

    if (!empty($priority)) {
        $sql .= " AND t.priority = ?";
        $params[] = $priority;
    }

    if (!empty($category)) {
        $sql .= " AND t.category = ?";
        $params[] = $category;
    }

    // Contagem total para paginação
    $count_sql = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(*) FROM', $sql);
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_tickets = $stmt->fetchColumn();
    $total_pages = ceil($total_tickets / $per_page);

    // Ordenação
    $allowed_sort_fields = ['created_at', 'status', 'priority', 'last_reply'];
    $sort = in_array($sort, $allowed_sort_fields) ? $sort : 'created_at';
    $order = in_array($order, ['ASC', 'DESC']) ? $order : 'DESC';

    $sql .= " ORDER BY t.$sort $order LIMIT $per_page OFFSET $offset";

    // Executar a consulta final
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obter estatísticas
    $stats = [
        'total' => $total_tickets,
        'pending' => get_tickets_by_status($db, 'pending'),
        'in_progress' => get_tickets_by_status($db, 'in_progress'),
        'resolved' => get_tickets_by_status($db, 'resolved')
    ];

} catch (PDOException $e) {
    error_log("Error in support.php: " . $e->getMessage());
    $error_message = "Ocorreu um erro ao carregar os tickets. Por favor, tente novamente.";
}

// Helper functions
function get_tickets_by_status($db, $status) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE status = ?");
    $stmt->execute([$status]);
    return $stmt->fetchColumn();
}

function get_priority_class($priority) {
    switch ($priority) {
        case 'high':
            return 'danger';
        case 'medium':
            return 'warning';
        case 'low':
            return 'info';
        default:
            return 'secondary';
    }
}

function get_status_class($status) {
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'in_progress':
            return 'info';
        case 'resolved':
            return 'success';
        case 'closed':
            return 'secondary';
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
    <title>Suporte - <?php echo SITE_NAME; ?></title>
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
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['total']); ?></h2>
                        <p>Total de Tickets</p>
                    </div>
                </div>
                
                <div class="admin-stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['pending']); ?></h2>
                        <p>Tickets Pendentes</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['in_progress']); ?></h2>
                        <p>Em Andamento</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['resolved']); ?></h2>
                        <p>Resolvidos</p>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h3>Filtros</h3>
                </div>
                <div class="admin-card-body">
                    <form action="support.php" method="GET" class="filter-form">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="search">Pesquisar</label>
                                    <input type="text" name="search" id="search" class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="ID, título ou usuário">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendentes</option>
                                        <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>Em Andamento</option>
                                        <option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>>Resolvidos</option>
                                        <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Fechados</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="priority">Prioridade</label>
                                    <select name="priority" id="priority" class="form-select">
                                        <option value="">Todas</option>
                                        <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>Alta</option>
                                        <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>Média</option>
                                        <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>Baixa</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="category">Categoria</label>
                                    <select name="category" id="category" class="form-select">
                                        <option value="">Todas</option>
                                        <option value="technical" <?php echo $category === 'technical' ? 'selected' : ''; ?>>Técnico</option>
                                        <option value="billing" <?php echo $category === 'billing' ? 'selected' : ''; ?>>Pagamento</option>
                                        <option value="account" <?php echo $category === 'account' ? 'selected' : ''; ?>>Conta</option>
                                        <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>Outros</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                            <a href="support.php" class="btn btn-outline-secondary">Limpar Filtros</a>
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
                                    <th>Título</th>
                                    <th>Categoria</th>
                                    <th>Prioridade</th>
                                    <th>Status</th>
                                    <th>Última Atualização</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tickets)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="empty-state">
                                                <i class="fas fa-ticket-alt fa-3x mb-3"></i>
                                                <p>Nenhum ticket encontrado</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($ticket['ticket_id']); ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <span class="username"><?php echo htmlspecialchars($ticket['username']); ?></span>
                                                    <span class="email"><?php echo htmlspecialchars($ticket['email']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($ticket['subject'] ?? ''); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucfirst($ticket['category']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo get_priority_class($ticket['priority']); ?>">
                                                    <?php echo ucfirst($ticket['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo get_status_class($ticket['status']); ?>">
                                                    <?php echo ucfirst($ticket['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($ticket['updated_at'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="./funcaosupport/ticket_view.php?id=<?php echo $ticket['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($ticket['status'] !== 'closed'): ?>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-success"
                                                                onclick="updateStatus(<?php echo $ticket['id']; ?>, 'resolved')">
                                                            <i class="fas fa-check"></i>
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
                                           href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&priority=<?php echo $priority; ?>&category=<?php echo $category; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
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
        function updateStatus(id, status) {
            if (confirm('Tem certeza que deseja atualizar o status deste ticket?')) {
                window.location.href = `ticket_update.php?id=${id}&status=${status}`;
            }
        }
    </script>
</body>
</html>