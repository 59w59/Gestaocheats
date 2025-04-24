<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

// Verificar se o administrador está logado
if (!is_admin_logged_in()) {
    header('Location: ../login.php');
    exit;
}

$error_message = '';
$success_message = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar dados
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $discord_id = trim($_POST['discord_id'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;

        // Validações
        if (empty($username)) throw new Exception('Nome de usuário é obrigatório');
        if (empty($email)) throw new Exception('Email é obrigatório');
        if (empty($password)) throw new Exception('Senha é obrigatória');
        if ($password !== $confirm_password) throw new Exception('As senhas não coincidem');
        if (strlen($password) < 6) throw new Exception('A senha deve ter no mínimo 6 caracteres');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido');

        // Verificar se username já existe
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new Exception('Nome de usuário já está em uso');
        }

        // Verificar se email já existe
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Email já está em uso');
        }

        // Hash da senha
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Inserir usuário
        $stmt = $db->prepare("
            INSERT INTO users (
                username, email, password, discord_id, 
                status, is_admin, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $username,
            $email,
            $hashed_password,
            $discord_id,
            $status,
            $is_admin
        ]);

        $user_id = $db->lastInsertId();

        // Log da ação
        log_admin_action($_SESSION['admin_id'], "create_user: Criou o usuário #$user_id ($username)");

        $success_message = 'Usuário criado com sucesso!';
        
        // Redirecionar após sucesso
        header('Location: users.php?success=created');
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
    <title>Adicionar Usuário - <?php echo SITE_NAME; ?></title>
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
                        <i class="fas fa-user-plus"></i>
                        Adicionar Novo Usuário
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

                    <form action="user_add.php" method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Nome de Usuário *</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>

                                <div class="mb-3">
                                    <label for="discord_id" class="form-label">ID do Discord</label>
                                    <input type="text" class="form-control" id="discord_id" name="discord_id">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Senha *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="text-muted">Mínimo de 6 caracteres</small>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmar Senha *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>

                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active">Ativo</option>
                                        <option value="pending">Pendente</option>
                                        <option value="banned">Banido</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin">
                                        <label class="form-check-label" for="is_admin">É Administrador</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salvar Usuário
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../assets/js/admin.js"></script>
    <script>
        // Validação do formulário
        (function() {
            'use strict';
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
        })();

        // Verificação de senha em tempo real
        document.getElementById('password').addEventListener('input', validatePasswords);
        document.getElementById('confirm_password').addEventListener('input', validatePasswords);

        function validatePasswords() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('As senhas não coincidem');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
    </script>
</body>
</html>