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

// Obter dados do administrador
$admin_id = $_SESSION['admin_id'];
$stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Configuração de paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filtros
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$subscription = isset($_GET['subscription']) ? $_GET['subscription'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Construir a consulta SQL
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM user_subscriptions s WHERE s.user_id = u.id AND s.status = 'active') as has_active_subscription
        FROM users u WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.discord_id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status === 'active') {
    $sql .= " AND u.status = 'active'";
} elseif ($status === 'banned') {
    $sql .= " AND u.status = 'banned'";
} elseif ($status === 'pending') {
    $sql .= " AND u.status = 'pending'";
}

if ($subscription === 'active') {
    $sql .= " AND (SELECT COUNT(*) FROM user_subscriptions s WHERE s.user_id = u.id AND s.status = 'active') > 0";
} elseif ($subscription === 'expired') {
    $sql .= " AND (SELECT COUNT(*) FROM user_subscriptions s WHERE s.user_id = u.id AND s.status = 'expired') > 0";
} elseif ($subscription === 'none') {
    $sql .= " AND (SELECT COUNT(*) FROM user_subscriptions s WHERE s.user_id = u.id) = 0";
}

// Contagem total para paginação
$count_sql = str_replace(
    "SELECT u.*, (SELECT COUNT(*) FROM user_subscriptions s WHERE s.user_id = u.id AND s.status = 'active') as has_active_subscription",
    "SELECT COUNT(*)",
    $sql
);
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Ordenação
$allowed_sort_fields = ['id', 'username', 'email', 'created_at', 'last_login', 'status'];
$allowed_order = ['ASC', 'DESC'];

if (!in_array($sort, $allowed_sort_fields)) {
    $sort = 'created_at';
}

if (!in_array($order, $allowed_order)) {
    $order = 'DESC';
}

$sql .= " ORDER BY u.$sort $order LIMIT $per_page OFFSET $offset";

// Executar a consulta final
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions for user statistics
function get_users_by_status($db, $status) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE status = ?");
    $stmt->execute([$status]);
    return $stmt->fetchColumn();
}

function get_users_with_subscription($db) {
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM user_subscriptions WHERE status = 'active'");
    $stmt->execute();
    return $stmt->fetchColumn();
}

function get_users_without_subscription($db) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE id NOT IN (SELECT DISTINCT user_id FROM user_subscriptions WHERE status = 'active')");
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Estatísticas de usuários
$user_stats = [
    'total' => $total_users,
    'active' => get_users_by_status($db, 'active'),
    'banned' => get_users_by_status($db, 'banned'),
    'pending' => get_users_by_status($db, 'pending'),
    'with_subscription' => get_users_with_subscription($db),
    'without_subscription' => get_users_without_subscription($db)
];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="../../assets/css/variables.css">
    <link rel="stylesheet" href="../../assets/css/AdminPanelStyles.css">
    <link rel="stylesheet" href="../../assets/css/custom.css">
    <link rel="stylesheet" href="../../assets/css/scroll.css">
    
</head>
<body class="admin-page">
    <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-main">
        <!-- Header -->
        <header class="admin-header">
            <div class="admin-header-left">
                <button class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="admin-page-title">Gerenciar Usuários</h1>
            </div>
            <div class="admin-header-right">
                <div class="admin-search">
                    <form action="./funcaousers/user_add.php" method="GET">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Buscar usuários..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </form>
                </div>
                <div class="admin-notifications">
                    <a href="#" class="dropdown-toggle">
                        <i class="fas fa-bell"></i>
                        <?php if (get_pending_support_tickets() > 0): ?>
                            <span class="badge bg-danger"><?php echo get_pending_support_tickets(); ?></span>
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
                            <a href="notifications.php">Ver todas</a>
                        </div>
                    </div>
                </div>
                <div class="admin-user">
                    <a href="#" class="dropdown-toggle">
                        <img src="../assets/images/admin-avatar.png" alt="Admin">
                        <span><?php echo $admin['username']; ?></span>
                    </a>
                    <div class="dropdown-menu">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Perfil
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i> Configurações
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Sair
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="admin-content">
            <!-- Estatísticas de Usuários -->
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($user_stats['total']); ?></h2>
                        <p>Total de Usuários</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($user_stats['active']); ?></h2>
                        <p>Usuários Ativos</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-danger">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($user_stats['banned']); ?></h2>
                        <p>Usuários Banidos</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($user_stats['with_subscription']); ?></h2>
                        <p>Com Assinatura</p>
                    </div>
                </div>
            </div>

            <!-- Filtros e Ações -->
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h3>Filtros e Ações</h3>
                    <a href="funcaousers/user_add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Novo Usuário
                    </a>
                </div>
                <div class="admin-card-body">
                    <form action="users.php" method="GET" class="filter-form">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="status" class="required">Status</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Ativos</option>
                                        <option value="banned" <?php echo $status === 'banned' ? 'selected' : ''; ?>>Banidos</option>
                                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendentes</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="subscription">Assinatura</label>
                                    <select name="subscription" id="subscription" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="active" <?php echo $subscription === 'active' ? 'selected' : ''; ?>>Com Assinatura Ativa</option>
                                        <option value="expired" <?php echo $subscription === 'expired' ? 'selected' : ''; ?>>Com Assinatura Expirada</option>
                                        <option value="none" <?php echo $subscription === 'none' ? 'selected' : ''; ?>>Sem Assinatura</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="sort">Ordenar por</label>
                                    <select name="sort" id="sort" class="form-select">
                                        <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Data de Registro</option>
                                        <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>Nome de Usuário</option>
                                        <option value="last_login" <?php echo $sort === 'last_login' ? 'selected' : ''; ?>>Último Login</option>
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
                            <a href="users.php" class="btn btn-outline-secondary">Limpar Filtros</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabela de Usuários -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Lista de Usuários</h3>
                    <span class="badge bg-primary"><?php echo number_format($total_users); ?> usuários encontrados</span>
                </div>
                <div class="admin-card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuário</th>
                                    <th>Email</th>
                                    <th>Discord</th>
                                    <th>Status</th>
                                    <th>Assinatura</th>
                                    <th>Registrado em</th>
                                    <th>Último Login</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">Nenhum usuário encontrado</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="user-info">
                                            <img src="<?php echo get_user_avatar($user['id']); ?>" alt="Avatar" class="user-avatar">
                                            <span><?php echo $user['username']; ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td><?php echo $user['discord_id'] ? $user['discord_id'] : 'N/A'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['status']; ?>">
                                            <?php 
                                            if ($user['status'] === 'active') echo 'Ativo';
                                            elseif ($user['status'] === 'banned') echo 'Banido';
                                            elseif ($user['status'] === 'pending') echo 'Pendente';
                                            else echo $user['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['has_active_subscription'] > 0): ?>
                                            <span class="badge bg-success">Ativa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                    <td><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca'; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="funcaousers/user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="funcaousers/user_subscriptions.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="Assinaturas">
                                                <i class="fas fa-credit-card"></i>
                                            </a>
                                            <a href="funcaousers/user_logs.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-secondary" title="Logs">
                                                <i class="fas fa-history"></i>
                                            </a>
                                            <?php if ($user['status'] === 'active'): ?>
                                            <a href="funcaousers/user_ban.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" title="Banir" onclick="return confirm('Tem certeza que deseja banir este usuário?');">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                            <?php elseif ($user['status'] === 'banned'): ?>
                                            <a href="funcaousers/user_unban.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-success" title="Desbanir" onclick="return confirm('Tem certeza que deseja desbanir este usuário?');">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="admin-card-footer">
                    <!-- Paginação -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Navegação de página">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($subscription) ? '&subscription=' . urlencode($subscription) : ''; ?><?php echo !empty($sort) ? '&sort=' . urlencode($sort) : ''; ?><?php echo !empty($order) ? '&order=' . urlencode($order) : ''; ?>" aria-label="Primeira">
                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($subscription) ? '&subscription=' . urlencode($subscription) : ''; ?><?php echo !empty($sort) ? '&sort=' . urlencode($sort) : ''; ?><?php echo !empty($order) ? '&order=' . urlencode($order) : ''; ?>" aria-label="Anterior">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($subscription) ? '&subscription=' . urlencode($subscription) : ''; ?><?php echo !empty($sort) ? '&sort=' . urlencode($sort) : ''; ?><?php echo !empty($order) ? '&order=' . urlencode($order) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($subscription) ? '&subscription=' . urlencode($subscription) : ''; ?><?php echo !empty($sort) ? '&sort=' . urlencode($sort) : ''; ?><?php echo !empty($order) ? '&order=' . urlencode($order) : ''; ?>" aria-label="Próxima">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($subscription) ? '&subscription=' . urlencode($subscription) : ''; ?><?php echo !empty($sort) ? '&sort=' . urlencode($sort) : ''; ?><?php echo !empty($order) ? '&order=' . urlencode($order) : ''; ?>" aria-label="Última">
                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            const adminPage = document.querySelector('.admin-page');
            
            sidebarToggle.addEventListener('click', function() {
                adminPage.classList.toggle('sidebar-collapsed');
            });
            
            // Fechar dropdowns ao clicar fora
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown-toggle') && !e.target.closest('.dropdown-menu')) {
                    document.querySelectorAll('.admin-notifications, .admin-user').forEach(function(container) {
                        container.classList.remove('show');
                    });
                }
            });
            
            // Toggle dropdown
            document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const parent = this.parentElement;
                    parent.classList.toggle('show');
                });
            });
        });
    </script>
</body>

</html>