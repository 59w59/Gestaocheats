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
$game = isset($_GET['game']) ? (int)$_GET['game'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';

try {
    // Construir a consulta SQL
    $sql = "SELECT c.*, g.name as game_name 
            FROM cheats c 
            LEFT JOIN games g ON c.game_id = g.id 
            WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (c.name LIKE ? OR c.description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if (!empty($game)) {
        $sql .= " AND c.game_id = ?";
        $params[] = $game;
    }

    if ($status === 'active') {
        $sql .= " AND c.is_active = 1";
    } elseif ($status === 'inactive') {
        $sql .= " AND c.is_active = 0";
    }

    // Contagem total para paginação
    $count_sql = str_replace("SELECT c.*, g.name as game_name", "SELECT COUNT(*)", $sql);
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_cheats = $stmt->fetchColumn();
    $total_pages = ceil($total_cheats / $per_page);

    // Ordenação
    $allowed_sort_fields = ['name', 'game_name', 'version', 'created_at', 'updated_at', 'download_count'];
    $allowed_order = ['ASC', 'DESC'];

    $sort = in_array($sort, $allowed_sort_fields) ? $sort : 'created_at';
    $order = in_array($order, $allowed_order) ? $order : 'DESC';

    $sql .= " ORDER BY c.$sort $order LIMIT $per_page OFFSET $offset";

    // Executar a consulta final
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $cheats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obter lista de jogos para o filtro
    $games_stmt = $db->query("SELECT id, name FROM games ORDER BY name ASC");
    $games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obter estatísticas
    $stats = [
        'total' => $total_cheats,
        'active' => get_cheats_by_status($db, true),
        'inactive' => get_cheats_by_status($db, false),
        'total_downloads' => get_total_cheat_downloads($db)
    ];

} catch (PDOException $e) {
    error_log("Error in cheats.php: " . $e->getMessage());
    $error_message = "Ocorreu um erro ao carregar os cheats. Por favor, tente novamente.";
}

// Helper functions
function get_cheats_by_status($db, $is_active) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM cheats WHERE is_active = ?");
    $stmt->execute([$is_active ? 1 : 0]);
    return $stmt->fetchColumn();
}

function get_total_cheat_downloads($db) {
    $stmt = $db->prepare("SELECT SUM(download_count) FROM cheats");
    $stmt->execute();
    return $stmt->fetchColumn() ?: 0;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Cheats - <?php echo SITE_NAME; ?></title>
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
                        <i class="fas fa-code"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['total']); ?></h2>
                        <p>Total de Cheats</p>
                    </div>
                </div>
                
                <div class="admin-stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['active']); ?></h2>
                        <p>Cheats Ativos</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['inactive']); ?></h2>
                        <p>Cheats Inativos</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="stat-content">
                        <h2><?php echo number_format($stats['total_downloads']); ?></h2>
                        <p>Downloads Totais</p>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h3>Filtros e Ações</h3>
                    <a href="./funcaocheat/cheat_add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Novo Cheat
                    </a>
                </div>
                <div class="admin-card-body">
                    <form action="cheats.php" method="GET" class="filter-form">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="search">Pesquisar</label>
                                    <input type="text" name="search" id="search" class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Nome ou descrição">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="game">Jogo</label>
                                    <select name="game" id="game" class="form-select">
                                        <option value="">Todos</option>
                                        <?php foreach ($games as $game_item): ?>
                                            <option value="<?php echo $game_item['id']; ?>" 
                                                    <?php echo $game == $game_item['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($game_item['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Ativos</option>
                                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inativos</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="sort">Ordenar por</label>
                                    <select name="sort" id="sort" class="form-select">
                                        <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Data de Criação</option>
                                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nome</option>
                                        <option value="game_name" <?php echo $sort === 'game_name' ? 'selected' : ''; ?>>Jogo</option>
                                        <option value="download_count" <?php echo $sort === 'download_count' ? 'selected' : ''; ?>>Downloads</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                            <a href="cheats.php" class="btn btn-outline-secondary">Limpar Filtros</a>
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
                                    <th>Jogo</th>
                                    <th>Versão</th>
                                    <th>Downloads</th>
                                    <th>Status</th>
                                    <th>Última Atualização</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cheats)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="empty-state">
                                                <i class="fas fa-code fa-3x mb-3"></i>
                                                <p>Nenhum cheat encontrado</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cheats as $cheat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cheat['id']); ?></td>
                                            <td><?php echo htmlspecialchars($cheat['name']); ?></td>
                                            <td><?php echo htmlspecialchars($cheat['game_name']); ?></td>
                                            <td>v<?php echo htmlspecialchars($cheat['version']); ?></td>
                                            <td><?php echo number_format($cheat['download_count']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $cheat['is_active'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $cheat['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($cheat['updated_at'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="./funcaocheat/cheat_edit.php?id=<?php echo $cheat['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="./funcaocheat/cheat_plans.php?id=<?php echo $cheat['id']; ?>" 
                                                       class="btn btn-sm btn-outline-success" title="Planos">
                                                        <i class="fas fa-tags"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="confirmDelete(<?php echo $cheat['id']; ?>)"
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
                                           href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&game=<?php echo $game; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
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
    <script src="../../assets/js/admin.js"></script>
    <script>
        function confirmDelete(id) {
            if (confirm('Tem certeza que deseja excluir este cheat?')) {
                window.location.href = `./funcaocheat/cheat_delete.php?id=${id}`;
            }
        }
    </script>
</body>
</html>