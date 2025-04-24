<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar se o usuário já está logado
if (isset($_SESSION['user_id'])) {
    redirect('../dashboard/index.php');
}

$error = '';
$success = '';

// Processar o formulário de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $discord_id = isset($_POST['discord_id']) ? trim($_POST['discord_id']) : '';
    $terms = isset($_POST['terms']);

    // Validação básica
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($discord_id)) {
        $error = 'Por favor, preencha todos os campos obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, insira um email válido.';
    } elseif ($password !== $confirm_password) {
        $error = 'As senhas não coincidem.';
    } elseif (strlen($password) < 8) {
        $error = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif (!preg_match('/^\d{17,19}$/', $discord_id)) {
        $error = 'O ID do Discord deve conter entre 17 e 19 números.';
    } elseif (!$terms) {
        $error = 'Você precisa aceitar os termos de serviço.';
    } else {
        // Verificar se o usuário ou email já existe
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $error = 'Este nome de usuário ou email já está em uso.';
        } else {
            // Criar o novo usuário
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $created_at = date('Y-m-d H:i:s');

            $stmt = $db->prepare("INSERT INTO users (username, email, password, first_name, last_name, discord_id, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?)");

            if ($stmt->execute([$username, $email, $hashed_password, $first_name, $last_name, $discord_id, $created_at])) {
                $success = 'Conta criada com sucesso! Agora você pode fazer login.';
                // Opcionalmente, redirecionar para a página de login após alguns segundos
                header("refresh:3;url=login.php");
            } else {
                $error = 'Ocorreu um erro ao criar sua conta. Por favor, tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon/favicon-16x16.png">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Toastify -->
    <link href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" rel="stylesheet">
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/scroll.css">
</head>
<body class="auth-page">
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <a href="../index.php" class="auth-logo">
                <?php echo SITE_NAME; ?>
            </a>
            <h1>Criar Conta</h1>
            <p>Registre-se para acessar nossos cheats premium</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="auth-form">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="first_name">Nome</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" placeholder="Seu nome">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="last_name">Sobrenome</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" placeholder="Seu sobrenome">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="username">Nome de Usuário <span class="required">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Escolha um nome de usuário" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email <span class="required">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" id="email" name="email" class="form-control" placeholder="Seu email" required>
                </div>
            </div>

            <div class="form-group">
                <label for="discord_id">ID do Discord <span class="required">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fab fa-discord"></i></span>
                    <input type="text" 
                           id="discord_id" 
                           name="discord_id" 
                           class="form-control" 
                           placeholder="Seu ID do Discord"
                           pattern="^\d{17,19}$"
                           title="ID do Discord deve conter entre 17 e 19 números"
                           value="<?php echo isset($_POST['discord_id']) ? htmlspecialchars($_POST['discord_id']) : ''; ?>"
                           required>
                </div>
                <small class="form-text text-muted">Para encontrar seu ID do Discord, ative o modo desenvolvedor nas configurações e clique com o botão direito em seu perfil</small>
            </div>

            <div class="form-group">
                <label for="password">Senha <span class="required">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Crie uma senha" required>
                </div>
                <small class="form-text text-muted">A senha deve ter pelo menos 8 caracteres.</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar Senha <span class="required">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirme sua senha" required>
                </div>
            </div>

            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                <label class="form-check-label" for="terms">Eu concordo com os <a href="terms.php">Termos de Serviço</a> e <a href="privacy.php">Política de Privacidade</a></label>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">Criar Conta</button>
            </div>

            <div class="auth-links">
                <span>Já tem uma conta?</span>
                <a href="login.php">Fazer Login</a>
            </div>
        </form>
    </div>

    <div class="auth-footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos os direitos reservados.</p>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
<script type="application/javascript" src="../assets/js/particles.js"></script>
</body>
</html>