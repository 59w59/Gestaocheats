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

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Buscar informações do usuário
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Usuário não encontrado');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar dados
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $discord_id = trim($_POST['discord_id'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        $new_password = trim($_POST['new_password'] ?? '');

        // Validações
        if (empty($username)) throw new Exception('Nome de usuário é obrigatório');
        if (empty($email)) throw new Exception('Email é obrigatório');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido');

        // Verificar se username já existe (exceto para o usuário atual)
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            throw new Exception('Nome de usuário já está em uso');
        }

        // Verificar se email já existe (exceto para o usuário atual)
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            throw new Exception('Email já está em uso');
        }

        // Preparar a query base
        $sql = "UPDATE users SET 
                username = ?, 
                email = ?, 
                discord_id = ?, 
                status = ?, 
                is_admin = ?, 
                updated_at = NOW()";
        $params = [$username, $email, $discord_id, $status, $is_admin];

        // Adicionar senha se fornecida
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                throw new Exception('A nova senha deve ter no mínimo 6 caracteres');
            }
            $sql .= ", password = ?";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        // Executar a atualização
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        // Log da ação
        log_admin_action($_SESSION['admin_id'], "edit_user", "edit_user: Editou o usuário #$id ($username)");

        $success_message = 'Usuário atualizado com sucesso!';
        
        // Recarregar dados do usuário
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Editar Usuário - <?php echo SITE_NAME; ?></title>
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
                        <i class="fas fa-user-edit"></i>
                        Editar Usuário: <?php echo htmlspecialchars($user['username']); ?>
                    </h3>
                    <a href="../users.php" class="btn btn-outline-primary">
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

                    <form action="user_edit.php?id=<?php echo (int)$id; ?>" method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Nome de Usuário *</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="discord_id" class="form-label">ID do Discord</label>
                                    <input type="text" class="form-control" id="discord_id" name="discord_id"
                                           value="<?php echo htmlspecialchars($user['discord_id'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nova Senha</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password"
                                           minlength="6">
                                    <small class="text-muted">Deixe em branco para manter a senha atual</small>
                                </div>

                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?php echo ($user['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Ativo</option>
                                        <option value="pending" <?php echo ($user['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pendente</option>
                                        <option value="banned" <?php echo ($user['status'] ?? '') === 'banned' ? 'selected' : ''; ?>>Banido</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin"
                                               <?php echo ($user['is_admin'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_admin">É Administrador</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <p class="text-muted">
                                    <small>
                                        Criado em: <?php echo isset($user['created_at']) ? date('d/m/Y H:i', strtotime($user['created_at'])) : 'N/A'; ?><br>
                                        Último login: <?php echo isset($user['last_login']) && $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca'; ?>
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
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../assets/js/admin.js"></script>
</body>
</html>