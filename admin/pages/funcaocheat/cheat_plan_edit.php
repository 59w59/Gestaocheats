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
$plan = null;

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Buscar informações do plano
    $stmt = $db->prepare("
        SELECT cp.*, c.name as cheat_name 
        FROM cheat_plans cp 
        LEFT JOIN cheats c ON cp.cheat_id = c.id 
        WHERE cp.id = ?
    ");
    $stmt->execute([$id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new Exception('Plano não encontrado');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar dados
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
        $duration_days = isset($_POST['duration_days']) ? (int)$_POST['duration_days'] : 0;
        $features = trim($_POST['features'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Validações
        if (empty($name)) throw new Exception('Nome é obrigatório');
        if ($price <= 0) throw new Exception('Preço deve ser maior que zero');
        if ($duration_days <= 0) throw new Exception('Duração deve ser maior que zero');

        // Atualizar plano
        $stmt = $db->prepare("
            UPDATE cheat_plans 
            SET name = ?, description = ?, price = ?, 
                duration_days = ?, features = ?, is_active = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $name,
            $description,
            $price,
            $duration_days,
            $features,
            $is_active,
            $id
        ]);

        $success_message = 'Plano atualizado com sucesso!';
        
        // Recarregar dados do plano
        $stmt = $db->prepare("SELECT * FROM cheat_plans WHERE id = ?");
        $stmt->execute([$id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Editar Plano - <?php echo SITE_NAME; ?></title>
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
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>
                        <i class="fas fa-edit"></i>
                        Editar Plano: <?php echo htmlspecialchars($plan['name']); ?>
                    </h3>
                    <a href="cheat_plans.php?id=<?php echo $plan['cheat_id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>

                <div class="admin-card-body">
                    <form action="cheat_plan_edit.php?id=<?php echo $id; ?>" method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nome do Plano *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($plan['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="price" class="form-label">Preço (R$) *</label>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           value="<?php echo $plan['price']; ?>"
                                           step="0.01" min="0" required>
                                </div>

                                <div class="mb-3">
                                    <label for="duration_days" class="form-label">Duração (dias) *</label>
                                    <input type="number" class="form-control" id="duration_days" name="duration_days"
                                           value="<?php echo $plan['duration_days']; ?>"
                                           min="1" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Descrição</label>
                                    <textarea class="form-control" id="description" name="description" 
                                              rows="3"><?php echo htmlspecialchars($plan['description']); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="features" class="form-label">Características</label>
                                    <textarea class="form-control" id="features" name="features" 
                                              rows="4" placeholder="Uma característica por linha"><?php 
                                        echo htmlspecialchars($plan['features']); 
                                    ?></textarea>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" 
                                           name="is_active" <?php echo $plan['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">Plano Ativo</label>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>