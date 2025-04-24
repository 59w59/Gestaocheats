<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

// Verificar se o administrador está logado
if (!is_admin_logged_in()) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$user = null;
$logs = [];

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Buscar informações do usuário
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Usuário não encontrado');
    }

    // Configuração de paginação
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    // Buscar total de logs primeiro
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_logs WHERE user_id = ?");
    $stmt->execute([$id]);
    $total_logs = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_logs / $per_page);

    // Ajustar página se estiver fora do range
    if ($page > $total_pages) {
        $page = 1;
        $offset = 0;
    }

    // Buscar logs do usuário com LIMIT e OFFSET como inteiros
    $stmt = $db->prepare("
        SELECT l.*, c.name as cheat_name 
        FROM user_logs l
        LEFT JOIN cheats c ON l.cheat_id = c.id
        WHERE l.user_id = ?
        ORDER BY l.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    // Passar parâmetros como inteiros
    $stmt->bindParam(1, $id, PDO::PARAM_INT);
    $stmt->bindParam(2, $per_page, PDO::PARAM_INT);
    $stmt->bindParam(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs do Usuário - <?php echo SITE_NAME; ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../../assets/images/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../../../assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../../assets/images/favicon/favicon-16x16.png">
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/variables.css">
    <link rel="stylesheet" href="../../../assets/css/AdminPanelStyles.css">
    <link rel="stylesheet" href="../../../assets/css/custom.css">
</head>

<body class="admin-page">
    <?php include '../../includes/admin-sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-content">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>
                        <i class="fas fa-history"></i>
                        Logs do Usuário: <?php echo htmlspecialchars($user['username']); ?>
                    </h3>
                    <a href="../users.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>

                <div class="admin-card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Informações do Usuário -->
                    <div class="user-info-card mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <h4>Informações do Usuário</h4>
                                <p><strong>ID:</strong> #<?php echo $user['id']; ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                <p><strong>Discord:</strong> <?php echo $user['discord_id'] ? htmlspecialchars($user['discord_id']) : 'N/A'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <h4>Status da Conta</h4>
                                <p>
                                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : ($user['status'] === 'banned' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </p>
                                <p><strong>Registrado em:</strong> <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></p>
                                <p><strong>Último login:</strong> <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca'; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Logs -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Ação</th>
                                    <th>Cheat</th>
                                    <th>IP</th>
                                    <th>Detalhes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Nenhum log encontrado</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($log['action']) {
                                                        'login' => 'success',
                                                        'logout' => 'secondary',
                                                        'failed_login' => 'danger',
                                                        'cheat_access' => 'primary',
                                                        'subscription_purchase' => 'info',
                                                        default => 'warning'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $log['cheat_name'] ? htmlspecialchars($log['cheat_name']) : 'N/A'; ?></td>
                                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                            <td>
                                                <?php if ($log['details']): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-info" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#logDetailsModal"
                                                            data-details="<?php echo htmlspecialchars($log['details']); ?>">
                                                        <i class="fas fa-info-circle"></i> Ver Detalhes
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">Sem detalhes</span>
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
                        <nav aria-label="Navegação de página" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <!-- Botão Previous -->
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?id=<?php echo $id; ?>&page=<?php echo $page-1; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php
                                // Mostrar no máximo 5 páginas
                                $start_page = max(1, min($page - 2, $total_pages - 4));
                                $end_page = min($total_pages, max(5, $page + 2));
                                
                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                    <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?id=<?php echo $id; ?>&page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <!-- Botão Next -->
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?id=<?php echo $id; ?>&page=<?php echo $page+1; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
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
                    <pre class="log-details"></pre>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar detalhes do log no modal
        const logDetailsModal = document.getElementById('logDetailsModal');
        if (logDetailsModal) {
            logDetailsModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const details = button.getAttribute('data-details');
                const detailsContainer = logDetailsModal.querySelector('.log-details');
                
                try {
                    // Tentar formatar como JSON
                    const formattedDetails = JSON.stringify(JSON.parse(details), null, 2);
                    detailsContainer.textContent = formattedDetails;
                } catch (e) {
                    // Se não for JSON, mostrar como texto
                    detailsContainer.textContent = details;
                }
            });
        }
    </script>
</body>
</html>