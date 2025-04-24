<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

if (!is_admin_logged_in()) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$success_message = '';

// Add slug creation function
function create_slug($text) {
    // Replace non-letter characters with a dash
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // Transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // Trim
    $text = trim($text, '-');
    // Remove duplicate dashes
    $text = preg_replace('~-+~', '-', $text);
    // Lowercase
    $text = strtolower($text);

    if (empty($text)) {
        return 'n-a';
    }

    return $text;
}

function slug_exists($db, $slug, $table = 'products') {
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetchColumn() > 0;
}

try {
    // Buscar lista de cheats ativos
    $stmt = $db->query("
        SELECT c.id, c.name, g.name as game_name 
        FROM cheats c
        JOIN games g ON c.game_id = g.id 
        WHERE c.is_active = 1 
        ORDER BY g.name, c.name
    ");
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

        // Gerar slug
        $slug = create_slug($name);
        $original_slug = $slug;
        $counter = 1;

        while (slug_exists($db, $slug, 'cheat_subscription_plans')) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        // Inserir plano
        $stmt = $db->prepare("
            INSERT INTO cheat_subscription_plans (
                cheat_id, name, slug, description, price, 
                duration_days, features, is_active, is_popular,
                hwid_protection, update_frequency, support_level,
                discord_access
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $cheat_id, $name, $slug, $description, $price,
            $duration_days, $features, $is_active, $is_popular,
            $hwid_protection, $update_frequency, $support_level,
            $discord_access
        ]);

        $_SESSION['success'] = 'Plano adicionado com sucesso!';
        header('Location: ../plans.php');
        exit;
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
    <title>Adicionar Plano - <?php echo SITE_NAME; ?></title>
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
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3><i class="fas fa-plus"></i> Adicionar Novo Plano</h3>
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

                    <form action="plan_add.php" method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="cheat_id" class="form-label">Cheat *</label>
                                    <select class="form-select" id="cheat_id" name="cheat_id" required>
                                        <option value="">Selecione um cheat</option>
                                        <?php foreach ($cheats as $cheat): ?>
                                            <option value="<?php echo $cheat['id']; ?>">
                                                <?php echo htmlspecialchars($cheat['game_name'] . ' - ' . $cheat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="name" class="form-label">Nome do Plano *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Descrição</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="features" class="form-label">Características</label>
                                    <textarea class="form-control" id="features" name="features" rows="4" 
                                              placeholder="Uma característica por linha"></textarea>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Preço (R$) *</label>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           step="0.01" min="0" required>
                                </div>

                                <div class="mb-3">
                                    <label for="duration_days" class="form-label">Duração (dias) *</label>
                                    <input type="number" class="form-control" id="duration_days" 
                                           name="duration_days" min="1" required>
                                </div>

                                <div class="mb-3">
                                    <label for="update_frequency" class="form-label">Frequência de Atualizações</label>
                                    <select class="form-select" id="update_frequency" name="update_frequency">
                                        <option value="daily">Diária</option>
                                        <option value="weekly">Semanal</option>
                                        <option value="monthly" selected>Mensal</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="support_level" class="form-label">Nível de Suporte</label>
                                    <select class="form-select" id="support_level" name="support_level">
                                        <option value="basic" selected>Básico</option>
                                        <option value="priority">Prioritário</option>
                                        <option value="vip">VIP</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_active" 
                                               name="is_active" checked>
                                        <label class="form-check-label" for="is_active">Plano Ativo</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_popular" 
                                               name="is_popular">
                                        <label class="form-check-label" for="is_popular">Plano Popular</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="hwid_protection" 
                                               name="hwid_protection">
                                        <label class="form-check-label" for="hwid_protection">Proteção HWID</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="discord_access" 
                                               name="discord_access">
                                        <label class="form-check-label" for="discord_access">Acesso ao Discord</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salvar Plano
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