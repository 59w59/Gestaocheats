<?php
define('INCLUDED_FROM_INDEX', true);
define('ENVIRONMENT', 'development');
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

// Initialize variables with default values
$stats = [
    'total_games' => 0,
    'active_games' => 0,
    'total_cheats' => 0
];

$search = '';
$status = '';
$category = '';
$sort = 'name';
$order = 'ASC';
$page = 1;
$total_pages = 1;
$games = [];

try {
    // Get statistics
    $stats = [
        'total_games' => get_total_games($db),
        'active_games' => get_active_games_count($db),
        'total_cheats' => get_total_cheats($db)
    ];

    // Get filter values from GET parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
    $order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';

    // Build SQL query without category
    $sql = "SELECT g.*, 
            (SELECT COUNT(1) FROM cheats c WHERE c.game_id = g.id) as cheat_count
            FROM games g 
            WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (g.name LIKE ? OR g.description LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param]);
    }

    if ($status === 'active') {
        $sql .= " AND g.is_active = 1";
    } elseif ($status === 'inactive') {
        $sql .= " AND g.is_active = 0";
    }

    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) FROM games g WHERE 1=1";
    if (!empty($search)) {
        $count_sql .= " AND (g.name LIKE ? OR g.description LIKE ?)";
    }
    if ($status === 'active') {
        $count_sql .= " AND g.is_active = 1";
    } elseif ($status === 'inactive') {
        $count_sql .= " AND g.is_active = 0";
    }

    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_records / $per_page));

    // Ensure current page is within valid range
    $page = min($page, $total_pages);

    // Add sorting and pagination
    $allowed_sort_fields = ['name', 'is_active', 'created_at', 'updated_at'];
    $sort = in_array($sort, $allowed_sort_fields) ? $sort : 'name';
    $order = in_array($order, ['ASC', 'DESC']) ? $order : 'ASC';

    // Fix the LIMIT and OFFSET syntax
    $sql .= " ORDER BY g.$sort $order LIMIT ? OFFSET ?";
    
    // Prepare and execute the final query
    $stmt = $db->prepare($sql);
    
    // Bind all WHERE clause parameters first
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    
    // Bind LIMIT and OFFSET parameters
    $stmt->bindValue(count($params) + 1, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error in games.php: " . $e->getMessage());
    error_log("SQL: " . $sql);
    error_log("Params: " . print_r($params, true));
    $error_message = "Erro ao carregar os jogos: " . $e->getMessage();
}

// Helper functions
function get_total_games($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM games");
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting total games: " . $e->getMessage());
        return 0;
    }
}

function get_active_games_count($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM games WHERE is_active = 1");
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting active games count: " . $e->getMessage());
        return 0;
    }
}

function get_total_cheats($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM cheats");
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting total cheats: " . $e->getMessage());
        return 0;
    }
}

function get_status_badge($is_active) {
    return $is_active ? 'success' : 'danger';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Jogos - <?php echo SITE_NAME; ?></title>
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
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['total_games']); ?></h2>
                        <p>Total de Jogos</p>
                    </div>
                </div>
                
                <div class="admin-stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['active_games']); ?></h2>
                        <p>Jogos Ativos</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-code"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['total_cheats']); ?></h2>
                        <p>Total de Cheats</p>
                    </div>
                </div>
            </div>

            <!-- Filtros e Ações -->
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h3>Filtros e Ações</h3>
                    <a href="./funcaogame/game_add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Novo Jogo
                    </a>
                </div>
                <div class="admin-card-body">
                    <form action="games.php" method="GET" class="filter-form">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="search">Pesquisar</label>
                                    <input type="text" name="search" id="search" class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Nome ou descrição">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Ativo</option>
                                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inativo</option>
                                        <option value="maintenance" <?php echo $status === 'maintenance' ? 'selected' : ''; ?>>Manutenção</option>
                                        <option value="beta" <?php echo $status === 'beta' ? 'selected' : ''; ?>>Beta</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Filtrar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Jogos -->
            <div class="admin-card">
                <div class="admin-card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Cheats</th>
                                    <th>Status</th>
                                    <th>Última Atualização</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($games)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="empty-state">
                                                <i class="fas fa-gamepad fa-3x mb-3"></i>
                                                <p>Nenhum jogo encontrado</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($games as $game): ?>
                                        <tr>
                                            <td>
                                                <div class="game-info">
                                                    <?php if (!empty($game['image'])): ?>
                                                        <img src="../../assets/images/games/<?php echo htmlspecialchars($game['image']); ?>" 
                                                             alt="<?php echo htmlspecialchars($game['name']); ?>" 
                                                             class="game-thumbnail">
                                                    <?php endif; ?>
                                                    <span class="game-name"><?php echo htmlspecialchars($game['name']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo number_format($game['cheat_count']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo get_status_badge($game['is_active']); ?>">
                                                    <?php echo $game['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($game['updated_at'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="./funcaogame/game_edit.php?id=<?php echo $game['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="./funcaogame/game_cheats.php?id=<?php echo $game['id']; ?>" 
                                                       class="btn btn-sm btn-outline-info" 
                                                       title="Gerenciar Cheats">
                                                        <i class="fas fa-code"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="confirmDelete(<?php echo $game['id']; ?>)"
                                                            title="Excluir">
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
                                           href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
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
        function confirmDelete(id) {
            if (confirm('Tem certeza que deseja excluir este jogo? Esta ação não pode ser desfeita.')) {
                window.location.href = `./funcaogame/game_delete.php?id=${id}`;
            }
        }
    </script>
</body>
</html>