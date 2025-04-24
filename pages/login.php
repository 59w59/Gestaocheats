<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Verificar se o usuário já está logado
if (isset($_SESSION['user_id'])) {
    // Usar header diretamente em vez da função redirect
    header('Location: ../dashboard/index.php');
    exit();
}

$error = '';

// Processar o formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    // Validação básica
    if (empty($username) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        // Verificar credenciais
        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Credenciais válidas - iniciar sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // Comentamos a funcionalidade de "lembrar de mim" até criarmos a tabela
            // if ($remember) {
            //     $token = bin2hex(random_bytes(32));
            //     $expires = time() + (30 * 24 * 60 * 60); // 30 dias
            //
            //     // Armazenar token no banco de dados
            //     $stmt = $db->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            //     $stmt->execute([$user['id'], $token, date('Y-m-d H:i:s', $expires)]);
            //
            //     // Definir cookie
            //     setcookie('remember_token', $token, $expires, '/', '', false, true);
            // }

            // Redirecionar para o dashboard usando header diretamente
            header('Location: ../dashboard/index.php');
            exit();
        } else {
            $error = 'Nome de usuário ou senha inválidos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
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
    <div id="particles-js"></div>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="../index.php" class="auth-logo">
                    <?php echo SITE_NAME; ?>
                </a>
                <h1>Entrar</h1>
                <p>Acesse sua conta para utilizar nossos cheats premium</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="auth-form">
                <div class="form-group">
                    <label for="username">Usuário ou Email <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Seu usuário ou email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Senha <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Sua senha" required>
                    </div>
                </div>

                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Lembrar de mim</label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Entrar</button>
                </div>

                <div class="auth-links">
                    <a href="forgot-password.php">Esqueceu a senha?</a>
                    <span class="separator">|</span>
                    <a href="register.php">Criar uma conta</a>
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
    <script type="application/javascript" src="../assets/js/particles.js"></script>
</body>

</html>