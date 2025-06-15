<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Verificar se o usuário está logado
if (!is_logged_in()) {
    redirect('../pages/login.php');
}

// Obter dados do usuário
$user_id = $_SESSION['user_id'];
$auth = new Auth();
$user = $auth->get_user($user_id);

// Verificar se é um POST válido
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['plan_id']) || empty($_POST['payment_method'])) {
    $_SESSION['payment_error'] = 'Requisição inválida. Por favor, tente novamente.';
    redirect('purchases.php');
}

// Obter dados do formulário
$plan_id = (int)$_POST['plan_id'];
$payment_method = $_POST['payment_method'];

// Validar método de pagamento
if ($payment_method !== 'pix') {
    $_SESSION['payment_error'] = 'Apenas pagamento via PIX está disponível no momento.';
    redirect('checkout.php?plan=' . $plan_id);
}

try {
    // Obter detalhes do plano
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
        throw new Exception('Plano não encontrado ou não está disponível.');
    }

    // Verificar se o usuário já tem uma assinatura ativa para este cheat
    $stmt = $db->prepare("
        SELECT us.id, us.end_date, csp.name as plan_name, csp.price as plan_price
        FROM user_subscriptions us
        INNER JOIN cheat_subscription_plans csp ON us.cheat_plan_id = csp.id
        WHERE us.user_id = ? AND csp.cheat_id = ? AND us.status = 'active' AND us.end_date > NOW()
    ");
    $stmt->execute([$user_id, $plan['cheat_id']]);
    $existing_subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    // Iniciar transação
    $db->beginTransaction();

    // Gerar ID de transação único
    $transaction_id = 'PIX-' . strtoupper(bin2hex(random_bytes(8)));
    
    // Gerar código PIX (simulado - em um cenário real, isso viria do MercadoPago)
    $pix_code = 'PIX-' . strtoupper(bin2hex(random_bytes(16)));
    $qr_code_image = '../assets/images/pix_qrcode_example.png'; // Em um sistema real, geraria um QR code

    // Data de início e término da assinatura
    $start_date = date('Y-m-d H:i:s');
    $end_date = date('Y-m-d H:i:s', strtotime('+' . $plan['duration_days'] . ' days'));
    
    // Se existir uma assinatura, estender a data de término
    if ($existing_subscription) {
        $end_date = date('Y-m-d H:i:s', strtotime($existing_subscription['end_date'] . ' +' . $plan['duration_days'] . ' days'));
        
        // Atualizar assinatura existente
        $stmt = $db->prepare("
            UPDATE user_subscriptions 
            SET end_date = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$end_date, $existing_subscription['id']]);
        
        $subscription_id = $existing_subscription['id'];
        $is_renewal = true;
    }
    else {
        // Criar nova assinatura
        $stmt = $db->prepare("
            INSERT INTO user_subscriptions 
            (user_id, cheat_plan_id, status, start_date, end_date) 
            VALUES (?, ?, 'active', ?, ?)
        ");
        $stmt->execute([$user_id, $plan_id, $start_date, $end_date]);
        
        $subscription_id = $db->lastInsertId();
        $is_renewal = false;
    }
    
    // Registrar pagamento como pendente (PIX)
    $stmt = $db->prepare("
        INSERT INTO payments 
        (user_id, subscription_id, transaction_id, payment_method, amount, status, pix_code) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        $subscription_id,
        $transaction_id,
        'pix',
        $plan['price'],
        'pending',
        $pix_code
    ]);
    
    $payment_id = $db->lastInsertId();
    
    // Registrar atividade
    $stmt = $db->prepare("
        INSERT INTO user_logs
        (user_id, action, description, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    
    $log_description = $is_renewal 
        ? "Geração de PIX para renovação da assinatura do plano {$plan['name']} ({$plan['cheat_name']}) por R$ " . number_format($plan['price'], 2, ',', '.')
        : "Geração de PIX para nova assinatura do plano {$plan['name']} ({$plan['cheat_name']}) por R$ " . number_format($plan['price'], 2, ',', '.');
    
    $stmt->execute([
        $user_id,
        'subscription_' . ($is_renewal ? 'renewal_pix_generated' : 'purchase_pix_generated'),
        $log_description,
        get_client_ip()
    ]);
    
    // Confirmar transação
    $db->commit();
    
    // Para PIX, redirecionar para página de espera de pagamento
    $_SESSION['pix_code'] = $pix_code;
    $_SESSION['qr_code'] = $qr_code_image;
    $_SESSION['transaction_id'] = $transaction_id;
    $_SESSION['payment_id'] = $payment_id;
    redirect('payment_pending.php?id=' . $payment_id);

} catch (Exception $e) {
    // Reverter transação em caso de erro
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // Registrar erro
    error_log("Payment error: " . $e->getMessage());
    
    // Mostrar erro para o usuário
    $_SESSION['payment_error'] = 'Erro ao processar o pagamento: ' . $e->getMessage();
    redirect('checkout.php?plan=' . $plan_id);
}