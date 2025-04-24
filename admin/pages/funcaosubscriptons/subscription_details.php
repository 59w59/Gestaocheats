<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

// Check admin authentication
if (!is_admin_logged_in()) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$success_message = '';
$subscription = null;

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Get subscription details with plan and cheat info
    $stmt = $db->prepare("
        SELECT 
            us.*, 
            csp.name as plan_name, 
            csp.description as plan_description,
            csp.price as plan_price,
            csp.features as plan_features,
            csp.duration_days as plan_duration,
            c.name as cheat_name,
            c.id as cheat_id,
            c.image as cheat_image,
            c.version as cheat_version,
            u.username as user_name,
            u.email as user_email,
            u.id as user_id
        FROM user_subscriptions us
        LEFT JOIN cheat_subscription_plans csp ON us.cheat_plan_id = csp.id
        LEFT JOIN cheats c ON csp.cheat_id = c.id
        LEFT JOIN users u ON us.user_id = u.id
        WHERE us.id = ?
    ");
    $stmt->execute([$id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        throw new Exception('Assinatura não encontrada');
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Assinatura - <?php echo SITE_NAME; ?></title>
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
    <link rel="stylesheet" href="../../..assets/css/scroll.css">
</head>

<body class="admin-page">
    <?php include '../../includes/admin-sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-content">
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if ($subscription): ?>
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h3>
                            <i class="fas fa-credit-card"></i>
                            Detalhes da Assinatura #<?php echo $subscription['id']; ?>
                        </h3>
                        <div>
                            <a href="user_subscriptions.php?id=<?php echo $subscription['user_id']; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Voltar
                            </a>
                        </div>
                    </div>

                    <div class="admin-card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="subscription-info-card">
                                    <h4 class="mb-3">Informações da Assinatura</h4>
                                    <table class="table">
                                        <tr>
                                            <th>ID</th>
                                            <td>#<?php echo $subscription['id']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>
                                                <span class="badge bg-<?php echo $subscription['status'] === 'active' ? 'success' : ($subscription['status'] === 'cancelled' ? 'danger' : 'warning'); ?>">
                                                    <?php echo $subscription['status'] === 'active' ? 'Ativa' : ($subscription['status'] === 'cancelled' ? 'Cancelada' : 'Expirada'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Data de Início</th>
                                            <td><?php echo date('d/m/Y H:i', strtotime($subscription['start_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Data de Término</th>
                                            <td><?php echo date('d/m/Y H:i', strtotime($subscription['end_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Criado em</th>
                                            <td><?php echo date('d/m/Y H:i', strtotime($subscription['created_at'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="subscription-info-card">
                                    <h4 class="mb-3">Informações do Usuário</h4>
                                    <table class="table">
                                        <tr>
                                            <th>Usuário</th>
                                            <td>
                                                <a href="../users.php?id=<?php echo $subscription['user_id']; ?>">
                                                    <?php echo htmlspecialchars($subscription['user_name']); ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Email</th>
                                            <td><?php echo htmlspecialchars($subscription['user_email']); ?></td>
                                        </tr>
                                    </table>
                                    
                                    <h4 class="mt-4 mb-3">Informações do Plano</h4>
                                    <table class="table">
                                        <tr>
                                            <th>Cheat</th>
                                            <td>
                                                <a href="../funcaocheat/cheat_edit.php?id=<?php echo $subscription['cheat_id']; ?>">
                                                    <?php echo htmlspecialchars($subscription['cheat_name']); ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Plano</th>
                                            <td><?php echo htmlspecialchars($subscription['plan_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Preço</th>
                                            <td>R$ <?php echo number_format($subscription['plan_price'], 2, ',', '.'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Duração</th>
                                            <td><?php echo htmlspecialchars($subscription['plan_duration']); ?> dias</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <?php if($subscription['status'] === 'active'): ?>
                            <div class="text-end">
                                <button class="btn btn-danger" onclick="cancelSubscription(<?php echo $subscription['id']; ?>)">
                                    <i class="fas fa-times"></i> Cancelar Assinatura
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function cancelSubscription(id) {
            if (confirm('Tem certeza que deseja cancelar esta assinatura?')) {
                window.location.href = `subscription_cancel.php?id=${id}`;
            }
        }
    </script>
</body>
</html>