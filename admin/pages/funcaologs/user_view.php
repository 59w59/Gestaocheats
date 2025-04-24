<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

// Verificar autenticação do admin
if (!is_admin_logged_in()) {
    header('Location: ../../login.php');
    exit;
}

// Obter ID do usuário
$user_id = filter_var($_GET['id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
if (!$user_id) {
    header('Location: ../logs.php?error=invalid_user');
    exit;
}

try {
    // Obter dados do usuário
    $stmt = $db->prepare("SELECT id, username, email, created_at, last_login FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Usuário não encontrado");
    }

    // Obter logs do usuário
    $stmt = $db->prepare("
        SELECT * FROM activity_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmt->execute([$user_id]);
    $user_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    header('Location: ../logs.php?error=user_fetch_failed');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Usuário - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/AdminPanelStyles.css">
</head>

<body class="admin-page">
    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3>Detalhes do Usuário</h3>
                <a href="../logs.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h4>Informações Básicas</h4>
                        <table class="table">
                            <tr>
                                <th>ID:</th>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                            </tr>
                            <tr>
                                <th>Username:</th>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Cadastro:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Último Login:</th>
                                <td><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <h4>Histórico de Atividades</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Tipo</th>
                                <th>Ação</th>
                                <th>IP</th>
                                <th>Detalhes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_logs as $log): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo get_log_type_class($log['type']); ?>">
                                            <?php echo ucfirst($log['type']); ?>
                                        </span>
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
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

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
    </script>
</body>
</html>