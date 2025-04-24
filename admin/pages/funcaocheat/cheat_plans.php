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
$cheat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cheat = false;
$plans = [];

try {
    // Validar ID
    if ($cheat_id <= 0) {
        throw new Exception('ID do cheat inválido');
    }

    // Buscar informações do cheat
    $stmt = $db->prepare("SELECT * FROM cheats WHERE id = ?");
    $stmt->execute([$cheat_id]);
    $cheat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cheat) {
        throw new Exception('Cheat não encontrado');
    }

    // Buscar planos do cheat
    $stmt = $db->prepare("SELECT * FROM cheat_plans WHERE cheat_id = ? ORDER BY price ASC");
    $stmt->execute([$cheat_id]);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Se houver erro e não encontrou o cheat, redirecionar
if (!$cheat) {
    $_SESSION['error'] = $error_message;
    header('Location: cheats.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planos - <?php echo htmlspecialchars($cheat['name']); ?> - <?php echo SITE_NAME; ?></title>
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
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>
                        <i class="fas fa-tags"></i>
                        Planos para <?php echo htmlspecialchars($cheat['name']); ?>
                    </h3>
                    <div>
                        <a href="cheats.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                            <i class="fas fa-plus"></i> Novo Plano
                        </button>
                    </div>
                </div>

                <div class="admin-card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>

                    <?php if (empty($plans)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tags fa-3x mb-3"></i>
                            <p>Nenhum plano cadastrado para este cheat</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Descrição</th>
                                        <th>Preço</th>
                                        <th>Duração</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($plans as $plan): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($plan['name']); ?></td>
                                            <td><?php echo htmlspecialchars($plan['description']); ?></td>
                                            <td>R$ <?php echo number_format($plan['price'], 2, ',', '.'); ?></td>
                                            <td><?php echo $plan['duration_days']; ?> dias</td>
                                            <td>
                                                <span class="badge bg-<?php echo $plan['is_active'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $plan['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="editPlan(<?php echo $plan['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                            onclick="deletePlan(<?php echo $plan['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Adicionar Plano -->
    <div class="modal fade plan-modal" id="addPlanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle"></i>
                        Novo Plano
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="cheat_plan_add.php" method="POST">
                    <input type="hidden" name="cheat_id" value="<?php echo $cheat_id; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome do Plano *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Preço (R$) *</label>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="duration_days" class="form-label">Duração (dias) *</label>
                                    <input type="number" class="form-control" id="duration_days" 
                                           name="duration_days" min="1" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="features" class="form-label">Características</label>
                            <textarea class="form-control" id="features" name="features" rows="3"
                                    placeholder="Uma característica por linha"></textarea>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" 
                                   name="is_active" checked>
                            <label class="form-check-label" for="is_active">Plano Ativo</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Plano
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deletePlan(id) {
            if (confirm('Tem certeza que deseja excluir este plano?')) {
                window.location.href = `cheat_plan_delete.php?id=${id}`;
            }
        }

        function editPlan(id) {
            window.location.href = `cheat_plan_edit.php?id=${id}`;
        }
    </script>
</body>
</html>