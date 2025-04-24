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
$game = null;

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Buscar informações do jogo
    $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        throw new Exception('Jogo não encontrado');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar dados
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_popular = isset($_POST['is_popular']) ? 1 : 0;

        if (empty($name)) {
            throw new Exception('O nome do jogo é obrigatório.');
        }

        // Verificar se o slug precisa ser atualizado
        $slug = $game['slug'];
        if ($name !== $game['name']) {
            $slug = create_slug($name);
            $original_slug = $slug;
            $counter = 1;

            while (slug_exists($db, $slug) && $slug !== $game['slug']) {
                $slug = $original_slug . '-' . $counter;
                $counter++;
            }
        }

        // Upload da nova imagem (se fornecida)
        $image = $game['image'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $new_image = handle_image_upload($_FILES['image']);
            
            // Deletar imagem antiga
            if (!empty($game['image'])) {
                $old_image = '../../../assets/images/games/' . $game['image'];
                if (file_exists($old_image)) {
                    unlink($old_image);
                }
            }
            
            $image = $new_image;
        }

        // Atualizar no banco
        $stmt = $db->prepare("
            UPDATE games 
            SET name = ?, slug = ?, description = ?, image = ?, 
                is_active = ?, is_popular = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $name,
            $slug,
            $description,
            $image,
            $is_active,
            $is_popular,
            $id
        ]);

        $success_message = 'Jogo atualizado com sucesso!';
        
        // Recarregar dados do jogo
        $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$id]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Helper function
function handle_image_upload($file) {
    $upload_dir = '../../../assets/images/games/';
    
    // Validar tipo
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        throw new Exception('Tipo de arquivo inválido. Use JPEG, PNG ou WEBP.');
    }
    
    // Validar tamanho (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception('O arquivo é muito grande. Tamanho máximo: 2MB.');
    }

    // Gerar nome único
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('game_') . '.' . $extension;
    $destination = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Erro ao fazer upload da imagem.');
    }

    return $filename;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Jogo - <?php echo SITE_NAME; ?></title>
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
                    <h3>
                        <i class="fas fa-edit"></i>
                        Editar Jogo: <?php echo htmlspecialchars($game['name']); ?>
                    </h3>
                    <a href="../games.php" class="btn btn-outline-primary">
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

                    <form action="game_edit.php?id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nome do Jogo *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($game['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Descrição</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php 
                                        echo htmlspecialchars($game['description']); 
                                    ?></textarea>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="image" class="form-label">Imagem do Jogo</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    <?php if (!empty($game['image'])): ?>
                                        <div class="mt-2">
                                            <img src="../../../assets/images/games/<?php echo $game['image']; ?>" 
                                                 alt="Preview" class="img-thumbnail" style="max-width: 150px;">
                                        </div>
                                    <?php endif; ?>
                                    <small class="text-muted">Formatos: JPEG, PNG ou WEBP (Máx: 2MB)</small>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                               <?php echo $game['is_active'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Ativo</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_popular" name="is_popular"
                                               <?php echo $game['is_popular'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_popular">Popular</label>
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