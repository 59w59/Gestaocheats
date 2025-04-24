<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';
require_once '../../includes/admin_auth.php';

if (!is_admin_logged_in()) {
    header('Location: ../login.php');
    exit;
}

// Configuração de paginação
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';

// Initialize variables with default values
$stats = [
    'total' => 0,
    'today' => 0,
    'week' => 0,
    'month' => 0
];
$total_pages = 1;
$logs = [];
$error_message = null;

try {
    // Verificar se a tabela activity_logs existe
    $table_check = $db->query("SHOW TABLES LIKE 'activity_logs'");
    if ($table_check->rowCount() === 0) {
        throw new Exception("Tabela de logs não encontrada");
    }

    // Construir a consulta SQL
    $sql = "SELECT l.*, u.username 
            FROM activity_logs l 
            LEFT JOIN users u ON l.user_id = u.id 
            WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (l.action LIKE ? OR l.ip_address LIKE ? OR u.username LIKE ?)";
        $search_param = "%$search%";
        $params = array_fill(0, 3, $search_param);
    }

    if (!empty($type)) {
        $sql .= " AND l.type = ?";
        $params[] = $type;
    }

    if (!empty($date_start)) {
        $sql .= " AND DATE(l.created_at) >= ?";
        $params[] = $date_start;
    }

    if (!empty($date_end)) {
        $sql .= " AND DATE(l.created_at) <= ?";
        $params[] = $date_end;
    }

    // Contagem total para paginação
    $count_sql = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(*) FROM', $sql);
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_logs = $stmt->fetchColumn();
    $total_pages = ceil($total_logs / $per_page);

    // Ordenação
    $allowed_sort_fields = ['created_at', 'type', 'action', 'ip_address', 'username'];
    $sort = in_array($sort, $allowed_sort_fields) ? $sort : 'created_at';
    $order = in_array($order, ['ASC', 'DESC']) ? $order : 'DESC';

    $sql .= " ORDER BY l.$sort $order LIMIT $per_page OFFSET $offset";

    // Executar a consulta final
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Atualizar estatísticas mesmo se houver erro na consulta principal
    $stats = [
        'total' => get_logs_count_by_period($db, 'total'),
        'today' => get_logs_count_by_period($db, 'today'),
        'week' => get_logs_count_by_period($db, 'week'),
        'month' => get_logs_count_by_period($db, 'month')
    ];

} catch (Exception $e) {
    error_log("Error in logs.php: " . $e->getMessage());
    $error_message = "Ocorreu um erro ao carregar os logs. Por favor, tente novamente.";
}

// Helper functions
// Atualizar função helper para tratar erros
function get_logs_count_by_period($db, $period) {
    try {
        $sql = "SELECT COUNT(*) FROM activity_logs WHERE 1=1";
        
        switch ($period) {
            case 'total':
                break;
            case 'today':
                $sql .= " AND DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $sql .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $sql .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            default:
                return 0;
        }

        $stmt = $db->query($sql);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error getting log count: " . $e->getMessage());
        return 0;
    }
}

function get_log_type_class($type) {
    switch ($type) {
        case 'auth':
            return 'info';
        case 'action':
            return 'primary';
        case 'error':
            return 'danger';
        case 'payment':
            return 'success';
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
    <title>Logs do Sistema - <?php echo SITE_NAME; ?></title>
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

            <?php if (isset($_GET['success']) && $_GET['success'] === 'clear'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Logs antigos foram limpos com sucesso! 
                    <?php if (isset($_GET['count'])): ?>
                        (<?php echo (int)$_GET['count']; ?> registros removidos)
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'clear'): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    Erro ao tentar limpar os logs antigos. Por favor, tente novamente.
                </div>
            <?php endif; ?>

            <!-- Estatísticas -->
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['total']); ?></h2>
                        <p>Total de Logs</p>
                    </div>
                </div>
                
                <div class="admin-stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['today']); ?></h2>
                        <p>Logs de Hoje</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['week']); ?></h2>
                        <p>Logs da Semana</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['month']); ?></h2>
                        <p>Logs do Mês</p>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h3>Filtros</h3>
                    <button class="btn btn-danger" onclick="confirmClearLogs()">
                        <i class="fas fa-trash"></i> Limpar Logs Antigos
                    </button>
                </div>
                <div class="admin-card-body">
                    <form action="logs.php" method="GET" class="filter-form">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="search">Pesquisar</label>
                                    <input type="text" name="search" id="search" class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Ação, IP ou usuário">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="type">Tipo</label>
                                    <select name="type" id="type" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="auth" <?php echo $type === 'auth' ? 'selected' : ''; ?>>Autenticação</option>
                                        <option value="action" <?php echo $type === 'action' ? 'selected' : ''; ?>>Ação</option>
                                        <option value="error" <?php echo $type === 'error' ? 'selected' : ''; ?>>Erro</option>
                                        <option value="payment" <?php echo $type === 'payment' ? 'selected' : ''; ?>>Pagamento</option>
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
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_end">Data Final</label>
                                    <input type="date" name="date_end" id="date_end" class="form-control" 
                                           value="<?php echo $date_end; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                            <a href="logs.php" class="btn btn-outline-secondary">Limpar Filtros</a>
                            <button type="button" class="btn btn-success" onclick="exportLogs()">
                                <i class="fas fa-download"></i> Exportar
                            </button>
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
                                    <th>Data/Hora</th>
                                    <th>Tipo</th>
                                    <th>Usuário</th>
                                    <th>Ação</th>
                                    <th>IP</th>
                                    <th>Detalhes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="empty-state">
                                                <i class="fas fa-list fa-3x mb-3"></i>
                                                <p>Nenhum log encontrado</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo get_log_type_class($log['type']); ?>">
                                                    <?php echo ucfirst($log['type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($log['username']): ?>
                                                    <a href="./funcaologs/user_view.php?id=<?php echo $log['user_id']; ?>">
                                                        <?php echo htmlspecialchars($log['username']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Sistema</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                            <td>
                                                <?php if (!empty($log['details'])): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-info"
                                                            onclick="showLogDetails(<?php echo htmlspecialchars(json_encode($log['details'])); ?>)">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>
                                                <?php endif; ?>
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
                                           href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type; ?>&date_start=<?php echo $date_start; ?>&date_end=<?php echo $date_end; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
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

    <!-- Modal de Detalhes -->
    <div class="modal fade" id="logDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre id="logDetailsContent"></pre>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showLogDetails(details) {
            const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
            document.getElementById('logDetailsContent').textContent = JSON.stringify(details, null, 2);
            modal.show();
        }

        function confirmClearLogs() {
            if (confirm('Tem certeza que deseja limpar os logs antigos? Esta ação não pode ser desfeita.')) {
                window.location.href = './funcaologs/logs_clear.php';
            }
        }

        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = `./funcaologs/logs_export.php?${params.toString()}`;
        }
    </script>
</body>
</html>