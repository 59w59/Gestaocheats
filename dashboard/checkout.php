<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../vendor/autoload.php';

// Configuração do MercadoPago com versão antiga do SDK
use MercadoPago\SDK;
use MercadoPago\Preference;
use MercadoPago\Item;
use MercadoPago\Payer;

// Configurar MercadoPago com suas credenciais
SDK::setAccessToken(MP_ACCESS_TOKEN);

// Verificar se o usuário está logado
if (!is_logged_in()) {
    redirect('../login.php');
}

// Obter ID do plano
$plan_id = filter_input(INPUT_GET, 'plan', FILTER_SANITIZE_NUMBER_INT);
if (!$plan_id) {
    redirect('index.php');
}

// Buscar detalhes do plano na tabela cheat_subscription_plans
$stmt = $db->prepare("
    SELECT csp.*, c.name as cheat_name, g.name as game_name 
    FROM cheat_subscription_plans csp
    INNER JOIN cheats c ON csp.cheat_id = c.id
    INNER JOIN games g ON c.game_id = g.id
    WHERE csp.id = ? AND csp.is_active = 1
");
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

// Verificar se o usuário já tem uma assinatura ativa para este cheat
$stmt = $db->prepare("
    SELECT us.id, us.end_date, csp.name as plan_name, csp.price as plan_price
    FROM user_subscriptions us
    INNER JOIN cheat_subscription_plans csp ON us.cheat_plan_id = csp.id
    WHERE us.user_id = ? AND csp.cheat_id = ? AND us.status = 'active' AND us.end_date > NOW()
");
$stmt->execute([$user_id, $plan['cheat_id']]);
$existing_subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Determinar tipo de compra (nova ou renovação)
$is_renewal = $existing_subscription ? true : false;

// Criar objeto de preferência para o MercadoPago (versão antiga do SDK)
$preference = new Preference();

// Item da compra
$item = new Item();
$item->title = $plan['name'] . ' - ' . $plan['cheat_name'];
$item->quantity = 1;
$item->currency_id = "BRL";
$item->unit_price = (float)$plan['price'];
$item->description = $plan['description'] ?? 'Assinatura de ' . $plan['duration_days'] . ' dias';

// Pagador
$payer = new Payer();
$payer->name = $user['first_name'];
$payer->surname = $user['last_name'];
$payer->email = $user['email'];

// Configuração da preferência
$preference->items = array($item);
$preference->payer = $payer;
$preference->back_urls = array(
    "success" => SITE_URL . "/dashboard/paymentsprocess/payment_success.php",
    "failure" => SITE_URL . "/dashboard/checkout.php?plan=" . $plan_id,
    "pending" => SITE_URL . "/dashboard/paymentsprocess/payment_pending.php"
);
$preference->auto_return = "approved";
$preference->statement_descriptor = SITE_NAME;
$preference->external_reference = "PLAN_" . $plan_id . "_USER_" . $user_id;
$preference->notification_url = SITE_URL . "/webhook/mercadopago.php";

// Configurar apenas o PIX como método de pagamento
$preference->payment_methods = array(
    "excluded_payment_methods" => array(
        array("id" => "credit_card"),
        array("id" => "debit_card"),
        array("id" => "ticket"),
        array("id" => "bank_transfer"),
        array("id" => "atm")
    ),
    "excluded_payment_types" => array(
        array("id" => "credit_card"),
        array("id" => "debit_card"),
        array("id" => "ticket"),
        array("id" => "bank_transfer"),
        array("id" => "atm")
    ),
    "installments" => 1
);

try {
    $preference->save();
    $preference_id = $preference->id;
    $init_point = $preference->init_point;
} catch (Exception $e) {
    // Log do erro
    error_log('MercadoPago Error: ' . $e->getMessage());
    
    // Definir mensagem de erro para exibição
    $mp_error = "Falha ao conectar com o gateway de pagamento. Por favor, tente novamente mais tarde.";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout PIX - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon/favicon-16x16.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/checkout.css">
    <link rel="stylesheet" href="../assets/css/scroll.css">
    <script src="https://sdk.mercadopago.com/js/v2"></script>
</head>
<body class="checkout-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="checkout-card">
                    <div class="checkout-header">
                        <h2><?php echo $is_renewal ? 'Renovar Assinatura' : 'Finalizar Assinatura'; ?></h2>
                        <div class="plan-info">
                            <div>
                                <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($plan['cheat_name']); ?> - <?php echo htmlspecialchars($plan['game_name']); ?></p>
                            </div>
                            <div class="text-end">
                                <p class="price">R$ <?php echo number_format($plan['price'], 2, ',', '.'); ?></p>
                                <p class="text-muted"><?php echo $plan['duration_days']; ?> dias</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="checkout-body">
                        <?php if (isset($mp_error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $mp_error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($is_renewal): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Você já possui uma assinatura ativa até <?php echo date('d/m/Y H:i', strtotime($existing_subscription['end_date'])); ?>. Esta compra irá estender sua assinatura atual.
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-section">
                            <h4>Informações do Pedido</h4>
                            
                            <div class="order-summary">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Nome:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($is_renewal): ?>
                                            <p><strong>Assinatura atual até:</strong> <?php echo date('d/m/Y', strtotime($existing_subscription['end_date'])); ?></p>
                                            <p><strong>Nova validade até:</strong> <?php echo date('d/m/Y', strtotime($existing_subscription['end_date'] . ' +' . $plan['duration_days'] . ' days')); ?></p>
                                        <?php else: ?>
                                            <p><strong>Validade:</strong> <?php echo date('d/m/Y', strtotime('+' . $plan['duration_days'] . ' days')); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span><strong>Total:</strong></span>
                                    <span class="fs-4 text-primary">R$ <?php echo number_format($plan['price'], 2, ',', '.'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Método de Pagamento PIX -->
                        <div class="form-section">
                            <h4>Pagamento por PIX</h4>
                            
                            <div class="pix-info-box">
                                <div class="pix-icon">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <div class="pix-text">
                                    <h5>Pagamento instantâneo</h5>
                                    <p class="mb-0">Pague instantaneamente usando o PIX do seu banco. Não é necessário cadastrar cartões ou dados bancários.</p>
                                </div>
                            </div>
                            
                            <!-- Botão de Checkout do MercadoPago -->
                            <?php if (isset($preference_id)): ?>
                                <div class="mt-4">
                                    <button id="checkout-btn" class="mp-button">
                                        <i class="fas fa-qrcode"></i> Gerar QR Code PIX
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i> O gateway de pagamento não está disponível no momento. Por favor, tente novamente mais tarde.
                                </div>
                            <?php endif; ?>
                        </div>

                        <a href="purchases.php" class="back-link mt-3">
                            <i class="fas fa-arrow-left me-2"></i> Voltar para escolha de planos
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($preference_id)): ?>
                // Botão de checkout para MercadoPago
                document.getElementById('checkout-btn').addEventListener('click', function() {
                    window.location.href = '<?php echo $init_point; ?>';
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>