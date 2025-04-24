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
$success_message = '';
$user = null;
$subscriptions = [];

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Buscar informações do usuário
    $stmt = $db->prepare("
        SELECT u.*, 
        (SELECT COUNT(*) FROM user_subscriptions WHERE user_id = u.id AND status = 'active') as active_subscriptions
        FROM users u WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Usuário não encontrado');
    }

    // Buscar assinaturas do usuário usando a nova estrutura de tabelas
    $stmt = $db->prepare("
        SELECT us.*, 
               csp.name as plan_name, 
               csp.price as plan_price,
               c.name as cheat_name,
               c.id as cheat_id,
               c.image as cheat_image
        FROM user_subscriptions us
        LEFT JOIN cheat_subscription_plans csp ON us.cheat_plan_id = csp.id
        LEFT JOIN cheats c ON csp.cheat_id = c.id
        WHERE us.user_id = ?
        ORDER BY us.created_at DESC
    ");
    $stmt->execute([$id]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinaturas do Usuário - <?php echo SITE_NAME; ?></title>
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
                        <i class="fas fa-credit-card"></i>
                        Assinaturas de <?php echo htmlspecialchars($user['username']); ?>
                    </h3>
                    <div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubscriptionModal">
                            <i class="fas fa-plus"></i> Nova Assinatura
                        </button>
                        <a href="../users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>

                <div class="admin-card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>

                    <!-- Info do Usuário -->
                    <div class="user-info-card mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <h4>Informações do Usuário</h4>
                                <p><strong>ID:</strong> #<?php echo $user['id']; ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : ($user['status'] === 'banned' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h4>Assinaturas Ativas</h4>
                                <div class="subscription-stats">
                                    <div class="stat-item">
                                        <span class="stat-value"><?php echo $user['active_subscriptions']; ?></span>
                                        <span class="stat-label">Assinaturas Ativas</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Assinaturas -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cheat</th>
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
                                        <td colspan="8" class="text-center">Nenhuma assinatura encontrada</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($subscriptions as $sub): ?>
                                        <tr>
                                            <td>#<?php echo $sub['id']; ?></td>
                                            <td><?php echo htmlspecialchars($sub['cheat_name']); ?></td>
                                            <td><?php echo htmlspecialchars($sub['plan_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $sub['status'] === 'active' ? 'success' : ($sub['status'] === 'cancelled' ? 'danger' : 'warning'); ?>">
                                                    <?php echo $sub['status'] === 'active' ? 'Ativa' : ($sub['status'] === 'cancelled' ? 'Cancelada' : 'Expirada'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($sub['start_date'])); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($sub['end_date'])); ?></td>
                                            <td>R$ <?php echo number_format($sub['plan_price'], 2, ',', '.'); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <?php if ($sub['status'] === 'active'): ?>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-danger" 
                                                                onclick="cancelSubscription(<?php echo $sub['id']; ?>)"
                                                                title="Cancelar">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-info"
                                                            onclick="viewSubscriptionDetails(<?php echo $sub['id']; ?>)"
                                                            title="Detalhes">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Nova Assinatura -->
    <div class="modal fade" id="addSubscriptionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nova Assinatura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../funcaosubscriptons/subscription_add.php" method="POST">
                    <input type="hidden" name="user_id" value="<?php echo $id; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="cheat_id" class="form-label">Cheat *</label>
                            <select class="form-select" id="cheat_id" name="cheat_id" required>
                                <option value="">Selecione um cheat</option>
                                <?php
                                $stmt = $db->query("SELECT id, name FROM cheats WHERE is_active = 1 ORDER BY name");
                                while ($cheat = $stmt->fetch()) {
                                    echo '<option value="' . $cheat['id'] . '">' . htmlspecialchars($cheat['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="plan_id" class="form-label">Plano *</label>
                            <select class="form-select" id="plan_id" name="plan_id" required disabled>
                                <option value="">Primeiro selecione um cheat</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="duration_days" class="form-label">Duração (dias) *</label>
                            <input type="number" class="form-control" id="duration_days" name="duration_days" required min="1">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../assets/js/admin.js"></script>
    <script>
        // Carregar planos quando selecionar um cheat
        document.getElementById('cheat_id').addEventListener('change', function() {
            const cheatId = this.value;
            const planSelect = document.getElementById('plan_id');
            
            planSelect.disabled = true;
            planSelect.innerHTML = '<option value="">Carregando...</option>';
            
            if (cheatId) {
                // Caminho corrigido para get_cheat_plans.php
                fetch(`../funcaoplan/get_cheat_plans.php?cheat_id=${cheatId}`)
                    .then(response => response.json())
                    .then(plans => {
                        planSelect.innerHTML = '<option value="">Selecione um plano</option>';
                        plans.forEach(plan => {
                            planSelect.innerHTML += `<option value="${plan.id}">${plan.name} - R$ ${plan.price}</option>`;
                        });
                        planSelect.disabled = false;
                    })
                    .catch(error => {
                        console.error('Erro ao carregar planos:', error);
                        planSelect.innerHTML = '<option value="">Erro ao carregar planos</option>';
                    });
            } else {
                planSelect.innerHTML = '<option value="">Primeiro selecione um cheat</option>';
            }
        });

        function cancelSubscription(id) {
            if (confirm('Tem certeza que deseja cancelar esta assinatura?')) {
                // Caminho corrigido para subscription_cancel.php
                window.location.href = `../funcaosubscriptons/subscription_cancel.php?id=${id}`;
            }
        }

        function viewSubscriptionDetails(id) {
            // Caminho corrigido para subscription_details.php
            window.location.href = `../funcaosubscriptons/subscription_details.php?id=${id}`;
        }
    </script>
</body>
</html>