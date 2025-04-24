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
    header('Location: ../../login.php');  // Changed from ../login.php
    exit;
}

$error_message = '';
$success_message = '';

try {
    // Buscar lista de jogos ativos
    $stmt = $db->query("SELECT id, name FROM games WHERE is_active = 1 ORDER BY name ASC");
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar dados
        $game_id = isset($_POST['game_id']) ? (int)$_POST['game_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $version = trim($_POST['version'] ?? '');
        $short_description = trim($_POST['short_description'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $min_subscription_level = (int)($_POST['min_subscription_level'] ?? 1);

        // Validações
        if (empty($name)) throw new Exception('Nome é obrigatório');
        if (empty($version)) throw new Exception('Versão é obrigatória');
        if ($game_id <= 0) throw new Exception('Selecione um jogo');

        // Gerar slug único
        $slug = create_slug($name);
        $original_slug = $slug;
        $counter = 1;

        while (slug_exists($db, $slug)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        // Upload do arquivo do cheat
        if (!isset($_FILES['cheat_file']) || $_FILES['cheat_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('O arquivo do cheat é obrigatório');
        }

        $file_path = handle_cheat_upload($_FILES['cheat_file']);

        // Upload da imagem (opcional)
        $image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image = handle_image_upload($_FILES['image']);
        }

        // Inserir no banco
        $stmt = $db->prepare("
            INSERT INTO cheats (
                name, slug, game_id, version, short_description, description,
                file_path, image, is_active, min_subscription_level
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $name,
            $slug,
            $game_id,
            $version,
            $short_description,
            $description,
            $file_path,
            $image,
            $is_active,
            $min_subscription_level
        ]);

        $_SESSION['success'] = 'Cheat adicionado com sucesso!';
        header('Location: ../cheats.php');  // Changed from cheats.php
        exit;
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Add helper function for file uploads
function handle_cheat_upload($file) {
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Gestaocheats/assets/files/cheats/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Get file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file type by extension
    $allowed_extensions = ['exe', 'dll', 'zip', 'rar'];
    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('Tipo de arquivo não permitido. Use .exe, .dll, .zip ou .rar');
    }

    // Additional security check for executable files
    if (in_array($file_extension, ['exe', 'dll'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mime_types = [
            'application/x-msdownload',
            'application/x-dosexec',
            'application/octet-stream',
            'application/x-executable'
        ];
        
        if (!in_array($mime_type, $allowed_mime_types)) {
            throw new Exception('Arquivo executável inválido');
        }
    }

    // Validate file size (1GB max)
    if ($file['size'] > 1024 * 1024 * 1024) {
        throw new Exception('Arquivo muito grande. Tamanho máximo: 1GB');
    }

    // Generate secure filename
    $filename = uniqid('cheat_') . '_' . preg_replace('/[^a-zA-Z0-9\-\.]/', '', $file['name']);
    $destination = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Erro ao fazer upload do arquivo');
    }

    return $filename;
}

// Add helper function for image uploads
function handle_image_upload($file) {
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Gestaocheats/assets/images/cheats/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Tipo de imagem não permitido. Use JPEG, PNG ou WEBP');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception('Imagem muito grande. Tamanho máximo: 2MB');
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('cheat_img_') . '.' . $extension;
    $destination = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Erro ao fazer upload da imagem');
    }

    return $filename;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Cheat - <?php echo SITE_NAME; ?></title>
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
                    <h3><i class="fas fa-plus-circle"></i> Adicionar Novo Cheat</h3>
                    <a href="../cheats.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>

                <div class="admin-card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="game_id" class="form-label">Jogo *</label>
                                    <select name="game_id" id="game_id" class="form-select" required>
                                        <option value="">Selecione um jogo</option>
                                        <?php foreach ($games as $game): ?>
                                            <option value="<?php echo $game['id']; ?>">
                                                <?php echo htmlspecialchars($game['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="name" class="form-label">Nome do Cheat *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>

                                <div class="mb-3">
                                    <label for="version" class="form-label">Versão *</label>
                                    <input type="text" class="form-control" id="version" name="version" required>
                                </div>

                                <div class="mb-3">
                                    <label for="short_description" class="form-label">Descrição Curta</label>
                                    <input type="text" class="form-control" id="short_description" name="short_description">
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Descrição Completa</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="cheat_file" class="form-label">Arquivo do Cheat *</label>
                                    <input type="file" class="form-control" id="cheat_file" name="cheat_file" required>
                                    <small class="text-muted">Arquivos .exe, .dll, .zip ou .rar (Máx: 1GB)</small>
                                </div>

                                <div class="mb-3">
                                    <label for="image" class="form-label">Imagem</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    <small class="text-muted">JPEG, PNG ou WEBP (Máx: 2MB)</small>
                                </div>

                                <div class="mb-3">
                                    <label for="min_subscription_level" class="form-label">Nível Mínimo de Assinatura</label>
                                    <select class="form-select" id="min_subscription_level" name="min_subscription_level">
                                        <option value="1">Básico</option>
                                        <option value="2">Premium</option>
                                        <option value="3">VIP</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                        <label class="form-check-label" for="is_active">Ativo</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salvar Cheat
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