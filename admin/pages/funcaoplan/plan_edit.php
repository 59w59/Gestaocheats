<?php
// filepath: c:\xampp\htdocs\Gestaocheats\admin\pages\funcaoplan\plan_edit.php
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

    // Buscar informações do plano e do cheat associado
    $stmt = $db->prepare("
        SELECT p.*, c.name as cheat_name, g.name as game_name 
        FROM cheat_subscription_plans p 
        LEFT JOIN cheats c ON p.cheat_id = c.id
        LEFT JOIN games g ON c.game_id = g.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new Exception('Plano não encontrado');
    }

    // Buscar todos os cheats para o dropdown de seleção
    $stmt = $db->prepare("
        SELECT c.id, c.name, g.name as game_name 
        FROM cheats c 
        JOIN games g ON c.game_id = g.id 
        WHERE c.is_active = 1 
        ORDER BY g.name, c.name
    ");
    $stmt->execute();
    $cheats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar dados
        $cheat_id = isset($_POST['cheat_id']) ? (int)$_POST['cheat_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
        $duration_days = isset($_POST['duration_days']) ? (int)$_POST['duration_days'] : 0;
        $features = trim($_POST['features'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_popular = isset($_POST['is_popular']) ? 1 : 0;
        $hwid_protection = isset($_POST['hwid_protection']) ? 1 : 0;
        $discord_access = isset($_POST['discord_access']) ? 1 : 0;
        $update_frequency = $_POST['update_frequency'] ?? 'monthly';
        $support_level = $_POST['support_level'] ?? 'basic';

        // Validações
        if ($cheat_id <= 0) throw new Exception('Selecione um cheat válido');
        if (empty($name)) throw new Exception('Nome é obrigatório');
        if ($price <= 0) throw new Exception('Preço deve ser maior que zero');
        if ($duration_days <= 0) throw new Exception('Duração deve ser maior que zero');

        // Verificar se o slug precisa ser alterado (se o nome ou cheat mudou)
        $new_slug = false;
        if ($cheat_id != $plan['cheat_id'] || $name != $plan['name']) {
            // Gerar novo slug
            $slug = create_slug($name);
            $original_slug = $slug;
            $counter = 1;

            // Verificar se o slug já existe para outro plano
            while (true) {
                $stmt = $db->prepare("
                    SELECT id FROM cheat_subscription_plans 
                    WHERE slug = ? AND cheat_id = ? AND id <> ?
                ");
                $stmt->execute([$slug, $cheat_id, $id]);
                if (!$stmt->fetch()) break;
                
                // Se existir, adicionar um contador
                $slug = $original_slug . '-' . $counter;
                $counter++;
            }
            $new_slug = true;
        } else {
            $slug = $plan['slug']; // Manter o slug existente
        }

        // Atualizar plano no banco de dados
        if ($new_slug) {
            $stmt = $db->prepare("
                UPDATE cheat_subscription_plans SET
                cheat_id = ?, name = ?, slug = ?, description = ?,
                price = ?, duration_days = ?, features = ?,
                is_active = ?, is_popular = ?, hwid_protection = ?,
                update_frequency = ?, support_level = ?, discord_access = ?,
                updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $cheat_id, $name, $slug, $description,
                $price, $duration_days, $features,
                $is_active, $is_popular, $hwid_protection,
                $update_frequency, $support_level, $discord_access,
                $id
            ]);
        } else {
            $stmt = $db->prepare("
                UPDATE cheat_subscription_plans SET
                cheat_id = ?, name = ?, description = ?,
                price = ?, duration_days = ?, features = ?,
                is_active = ?, is_popular = ?, hwid_protection = ?,
                update_frequency = ?, support_level = ?, discord_access = ?,
                updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $cheat_id, $name, $description,
                $price, $duration_days, $features,
                $is_active, $is_popular, $hwid_protection,
                $update_frequency, $support_level, $discord_access,
                $id
            ]);
        }

        // Log da ação
        log_admin_action($_SESSION['admin_id'], "edit_plan", "Editou o plano ID #$id ($name)");

        $success_message = 'Plano atualizado com sucesso!';
        
        // Recarregar dados do plano
        $stmt = $db->prepare("
            SELECT p.*, c.name as cheat_name, g.name as game_name 
            FROM cheat_subscription_plans p 
            LEFT JOIN cheats c ON p.cheat_id = c.id
            LEFT JOIN games g ON c.game_id = g.id 
            WHERE p.id = ?
        ");
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
    <link rel="stylesheet" href="../../../assets/css/scroll.css">
    <style>
        /* Estilização customizada para scrollbars */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
            background-color: rgba(0, 24, 36, 0.5);
        }

        ::-webkit-scrollbar-track {
            background-color: rgba(0, 24, 36, 0.5);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary-alpha-50), var(--primary));
            border-radius: 10px;
            border: 2px solid rgba(0, 24, 36, 0.5);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            box-shadow: 0 0 10px var(--primary-alpha-50);
        }

        ::-webkit-scrollbar-corner {
            background-color: rgba(0, 24, 36, 0.5);
        }

        /* Estilo Firefox (para compatibilidade) */
        * {
            scrollbar-width: thin;
            scrollbar-color: var(--primary) rgba(0, 24, 36, 0.5);
        }
    </style>
</head>
<body class="admin-page">
    <?php include '../../includes/admin-sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-content">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>
                        <i class="fas fa-edit"></i>
                        Editar Plano: <?php echo htmlspecialchars($plan['name'] ?? ''); ?>
                    </h3>
                    <a href="../plans.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>

                <div class="admin-card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <form action="plan_edit.php?id=<?php echo $id; ?>" method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="cheat_id" class="form-label">Cheat *</label>
                                    <select class="form-select" id="cheat_id" name="cheat_id" required>
                                        <option value="">Selecione um cheat</option>
                                        <?php foreach ($cheats as $cheat): ?>
                                            <option value="<?php echo $cheat['id']; ?>" <?php echo $plan['cheat_id'] == $cheat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cheat['game_name'] . ' - ' . $cheat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Por favor, selecione um cheat.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="name" class="form-label">Nome do Plano *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($plan['name']); ?>" required>
                                    <div class="invalid-feedback">
                                        Por favor, digite um nome para o plano.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Descrição</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php 
                                        echo htmlspecialchars($plan['description']); 
                                    ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="features" class="form-label">Características</label>
                                    <textarea class="form-control" id="features" name="features" rows="4" 
                                              placeholder="Uma característica por linha"><?php 
                                        echo htmlspecialchars($plan['features']); 
                                    ?></textarea>
                                    <small class="text-muted">Digite uma característica por linha. Exemplo: "Aimbot avançado"</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Preço (R$) *</label>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           value="<?php echo number_format($plan['price'], 2, '.', ''); ?>"
                                           step="0.01" min="0" required>
                                    <div class="invalid-feedback">
                                        O preço deve ser maior que zero.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="duration_days" class="form-label">Duração (dias) *</label>
                                    <input type="number" class="form-control" id="duration_days" name="duration_days"
                                           value="<?php echo $plan['duration_days']; ?>"
                                           min="1" required>
                                    <div class="invalid-feedback">
                                        A duração deve ser pelo menos 1 dia.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="update_frequency" class="form-label">Frequência de Atualizações</label>
                                    <select class="form-select" id="update_frequency" name="update_frequency">
                                        <option value="daily" <?php echo $plan['update_frequency'] === 'daily' ? 'selected' : ''; ?>>Diária</option>
                                        <option value="weekly" <?php echo $plan['update_frequency'] === 'weekly' ? 'selected' : ''; ?>>Semanal</option>
                                        <option value="monthly" <?php echo $plan['update_frequency'] === 'monthly' ? 'selected' : ''; ?>>Mensal</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="support_level" class="form-label">Nível de Suporte</label>
                                    <select class="form-select" id="support_level" name="support_level">
                                        <option value="basic" <?php echo $plan['support_level'] === 'basic' ? 'selected' : ''; ?>>Básico</option>
                                        <option value="priority" <?php echo $plan['support_level'] === 'priority' ? 'selected' : ''; ?>>Prioritário</option>
                                        <option value="vip" <?php echo $plan['support_level'] === 'vip' ? 'selected' : ''; ?>>VIP</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?php echo $plan['is_active'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Plano Ativo</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_popular" name="is_popular" <?php echo $plan['is_popular'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_popular">Plano Popular</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="hwid_protection" name="hwid_protection" <?php echo $plan['hwid_protection'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="hwid_protection">Proteção HWID</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="discord_access" name="discord_access" <?php echo $plan['discord_access'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="discord_access">Acesso ao Discord</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <p class="text-muted">
                                    <small>
                                        ID: <?php echo $plan['id']; ?><br>
                                        Slug: <?php echo $plan['slug']; ?><br>
                                        Criado em: <?php echo date('d/m/Y H:i', strtotime($plan['created_at'])); ?><br>
                                        Última atualização: <?php echo date('d/m/Y H:i', strtotime($plan['updated_at'])); ?>
                                    </small>
                                </p>
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salvar Alterações
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Estatísticas do Plano -->
            <div class="admin-card mt-4">
                <div class="admin-card-header">
                    <h3>Estatísticas do Plano</h3>
                </div>
                <div class="admin-card-body">
                    <?php
                    // Obter número de assinaturas ativas
                    $stmt = $db->prepare("
                        SELECT COUNT(*) FROM user_subscriptions 
                        WHERE cheat_plan_id = ? AND status = 'active'
                    ");
                    $stmt->execute([$id]);
                    $active_subscribers = $stmt->fetchColumn();
                    
                    // Obter número total de assinaturas (históricas)
                    $stmt = $db->prepare("
                        SELECT COUNT(*) FROM user_subscriptions 
                        WHERE cheat_plan_id = ?
                    ");
                    $stmt->execute([$id]);
                    $total_subscribers = $stmt->fetchColumn();
                    
                    // Calcular receita mensal estimada
                    $monthly_revenue = $active_subscribers * $plan['price'];
                    ?>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stat-card bg-primary text-white">
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?php echo number_format($active_subscribers); ?></h3>
                                    <p>Assinantes Ativos</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="stat-card bg-info text-white">
                                <div class="stat-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?php echo number_format($total_subscribers); ?></h3>
                                    <p>Total de Assinaturas</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="stat-card bg-success text-white">
                                <div class="stat-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="stat-content">
                                    <h3>R$ <?php echo number_format($monthly_revenue, 2, ',', '.'); ?></h3>
                                    <p>Receita Mensal Estimada</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validação do formulário
        document.addEventListener('DOMContentLoaded', function () {
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        });
    </script>
</body>
</html>