<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';
require_once '../../../includes/cheat_functions.php';

// Verificar se o administrador está logado
if (!is_admin_logged_in()) {
    header('Location: ../login.php');
    exit;
}

$error_message = '';
$success_message = '';
$cheat = null;

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Buscar informações do cheat
    $stmt = $db->prepare("
        SELECT c.*, g.name as game_name 
        FROM cheats c 
        LEFT JOIN games g ON c.game_id = g.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $cheat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cheat) {
        throw new Exception('Cheat não encontrado');
    }

    // Buscar lista de jogos ativos
    $stmt = $db->query("SELECT id, name FROM games WHERE is_active = 1 ORDER BY name ASC");
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar dados
        $name = trim($_POST['name'] ?? '');
        $game_id = isset($_POST['game_id']) ? (int)$_POST['game_id'] : 0;
        $version = trim($_POST['version'] ?? '');
        $short_description = trim($_POST['short_description'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $min_subscription_level = (int)($_POST['min_subscription_level'] ?? 1);

        // Validações
        if (empty($name)) throw new Exception('Nome é obrigatório');
        if (empty($version)) throw new Exception('Versão é obrigatória');
        if ($game_id <= 0) throw new Exception('Selecione um jogo');

        // Verificar se o slug precisa ser atualizado
        $slug = $cheat['slug'];
        if ($name !== $cheat['name']) {
            $slug = create_slug($name);
            $original_slug = $slug;
            $counter = 1;

            while (slug_exists($db, $slug) && $slug !== $cheat['slug']) {
                $slug = $original_slug . '-' . $counter;
                $counter++;
            }
        }

        // Upload do novo arquivo (se fornecido)
        $file_path = $cheat['file_path'];
        if (isset($_FILES['cheat_file']) && $_FILES['cheat_file']['error'] === UPLOAD_ERR_OK) {
            $new_file_path = handle_cheat_upload($_FILES['cheat_file']);
            
            // Deletar arquivo antigo
            if (!empty($cheat['file_path'])) {
                $old_file = '../../../uploads/cheats/' . $cheat['file_path'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
            
            $file_path = $new_file_path;
        }

        // Upload da nova imagem (se fornecida)
        $image = $cheat['image'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $new_image = handle_image_upload($_FILES['image']);
            
            // Deletar imagem antiga
            if (!empty($cheat['image'])) {
                $old_image = '../../../assets/images/cheats/' . $cheat['image'];
                if (file_exists($old_image)) {
                    unlink($old_image);
                }
            }
            
            $image = $new_image;
        }

        // Atualizar no banco
        $stmt = $db->prepare("
            UPDATE cheats 
            SET name = ?, slug = ?, game_id = ?, version = ?, 
                short_description = ?, description = ?, file_path = ?, 
                image = ?, is_active = ?, min_subscription_level = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $name, $slug, $game_id, $version, 
            $short_description, $description, $file_path,
            $image, $is_active, $min_subscription_level,
            $id
        ]);

        $success_message = 'Cheat atualizado com sucesso!';
        
        // Recarregar dados do cheat
        $stmt = $db->prepare("SELECT * FROM cheats WHERE id = ?");
        $stmt->execute([$id]);
        $cheat = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Editar Cheat - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/variables.css">
    <link rel="stylesheet" href="../../../assets/css/AdminPanelStyles.css">
    <link rel="stylesheet" href="../../../assets/css/custom.css">
</head>

<body class="admin-page">
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-content">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>
                        <i class="fas fa-edit"></i>
                        Editar Cheat: <?php echo htmlspecialchars($cheat['name']); ?>
                    </h3>
                    <a href="cheats.php" class="btn btn-outline-primary">
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

                    <form action="cheat_edit.php?id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="game_id" class="form-label">Jogo *</label>
                                    <select name="game_id" id="game_id" class="form-select" required>
                                        <option value="">Selecione um jogo</option>
                                        <?php foreach ($games as $game): ?>
                                            <option value="<?php echo $game['id']; ?>" 
                                                    <?php echo $cheat['game_id'] == $game['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($game['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="name" class="form-label">Nome do Cheat *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($cheat['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="version" class="form-label">Versão *</label>
                                    <input type="text" class="form-control" id="version" name="version" 
                                           value="<?php echo htmlspecialchars($cheat['version']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="short_description" class="form-label">Descrição Curta</label>
                                    <input type="text" class="form-control" id="short_description" name="short_description"
                                           value="<?php echo htmlspecialchars($cheat['short_description']); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Descrição Completa</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php 
                                        echo htmlspecialchars($cheat['description']); 
                                    ?></textarea>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="cheat_file" class="form-label">Arquivo do Cheat</label>
                                    <input type="file" class="form-control" id="cheat_file" name="cheat_file">
                                    <small class="text-muted">
                                        Arquivo atual: <?php echo htmlspecialchars($cheat['file_path']); ?><br>
                                        Formatos: .exe, .dll, .zip ou .rar (Máx: 1GB)
                                    </small>
                                </div>

                                <div class="mb-3">
                                    <label for="image" class="form-label">Imagem</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    <?php if (!empty($cheat['image'])): ?>
                                        <div class="mt-2">
                                            <img src="../../../assets/images/cheats/<?php echo $cheat['image']; ?>" 
                                                 alt="Preview" class="img-thumbnail" style="max-width: 150px;">
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="min_subscription_level" class="form-label">Nível Mínimo de Assinatura</label>
                                    <select class="form-select" id="min_subscription_level" name="min_subscription_level">
                                        <option value="1" <?php echo $cheat['min_subscription_level'] == 1 ? 'selected' : ''; ?>>Básico</option>
                                        <option value="2" <?php echo $cheat['min_subscription_level'] == 2 ? 'selected' : ''; ?>>Premium</option>
                                        <option value="3" <?php echo $cheat['min_subscription_level'] == 3 ? 'selected' : ''; ?>>VIP</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                               <?php echo $cheat['is_active'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Ativo</label>
                                    </div>
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
    <script>
        // Preview de imagem
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.img-thumbnail');
                    if (preview) {
                        preview.src = e.target.result;
                    } else {
                        const newPreview = document.createElement('img');
                        newPreview.src = e.target.result;
                        newPreview.classList.add('img-thumbnail');
                        newPreview.style.maxWidth = '150px';
                        document.querySelector('#image').parentNode.appendChild(newPreview);
                    }
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>