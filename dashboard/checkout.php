<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Verificar se o usuário está logado
if (!is_logged_in()) {
    redirect('../login.php');
}

// Obter ID do plano
$plan_id = filter_input(INPUT_GET, 'plan', FILTER_SANITIZE_NUMBER_INT);
if (!$plan_id) {
    redirect('index.php');
}

// Buscar detalhes do plano
$stmt = $db->prepare("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    redirect('index.php');
}

// Buscar dados do usuário
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon/favicon-16x16.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/checkout.css">
</head>
<body class="checkout-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="checkout-card">
                    <div class="checkout-header">
                        <h2>Finalizar Assinatura</h2>
                        <div class="plan-info">
                            <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
                            <p class="price">R$ <?php echo number_format($plan['price'], 2, ',', '.'); ?>/mês</p>
                        </div>
                    </div>
                    
                    <div class="checkout-body">
                        <form action="process_payment.php" method="POST" id="payment-form">
                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                            
                            <!-- Informações Pessoais -->
                            <div class="form-section">
                                <h4>Informações Pessoais</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Nome</label>
                                            <input type="text" name="name" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" name="email" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Método de Pagamento -->
                            <div class="form-section">
                                <h4>Método de Pagamento</h4>
                                <div class="payment-methods">
                                    <div class="form-check">
                                        <input type="radio" name="payment_method" value="pix" id="pix" class="form-check-input" checked>
                                        <label for="pix" class="form-check-label">PIX</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="payment_method" value="card" id="card" class="form-check-input">
                                        <label for="card" class="form-check-label">Cartão de Crédito</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Botão de Finalizar -->
                            <div class="form-section">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    Finalizar Assinatura
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/checkout.js"></script>
</body>
</html>