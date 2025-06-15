<?php
// filepath: c:\xampp\htdocs\Gestaocheats\webhook\mercadopago.php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php';

// Log raw webhook data
$input = file_get_contents('php://input');
$event_json = json_decode($input, true);

// Log webhook para debug
error_log("MercadoPago Webhook Received: " . $input);

// Verificar tipo de notificação
if (isset($_GET["topic"]) && $_GET["topic"] === "payment") {
    $payment_id = $_GET["id"];
    
    try {
        // Configure MercadoPago SDK
        MercadoPago\MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);
        
        // Buscar detalhes do pagamento
        $client = new MercadoPago\Client\Payment\PaymentClient();
        $payment = $client->get($payment_id);
        
        if ($payment) {
            // Obter a referência externa (formato: "PLAN_X_USER_Y")
            $external_reference = $payment->external_reference;
            $ref_parts = explode('_', $external_reference);
            
            if (count($ref_parts) >= 4) {
                $plan_id = $ref_parts[1];
                $user_id = $ref_parts[3];
                
                // Processar o pagamento com base no status
                switch ($payment->status) {
                    case 'approved':
                        processApprovedPayment($user_id, $plan_id, $payment);
                        break;
                    
                    case 'pending':
                        // Pagamento pendente, atualizamos o registro se existir
                        updatePaymentStatus($external_reference, 'pending', json_encode($payment));
                        break;
                        
                    case 'rejected':
                    case 'cancelled':
                    case 'refunded':
                        // Pagamento falhou ou foi cancelado/estornado
                        updatePaymentStatus($external_reference, 'failed', json_encode($payment));
                        break;
                }
            }
        }
        
        http_response_code(200);
        
    } catch (Exception $e) {
        error_log("MercadoPago Webhook Error: " . $e->getMessage());
        http_response_code(500);
    }
    
} else {
    // Tipo de notificação não suportado ou evento inválido
    http_response_code(200); // Ainda retornamos 200 para o MercadoPago não retentar
}
exit;

/**
 * Processa pagamento aprovado
 */
function processApprovedPayment($user_id, $plan_id, $payment_data) {
    global $db;
    
    try {
        // Iniciar transação
        $db->beginTransaction();
        
        // Buscar detalhes do plano
        $stmt = $db->prepare("
            SELECT csp.*, c.cheat_id 
            FROM cheat_subscription_plans csp
            INNER JOIN cheats c ON csp.cheat_id = c.id
            WHERE csp.id = ? AND csp.is_active = 1
        ");
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan) {
            throw new Exception("Plano não encontrado ou inativo: {$plan_id}");
        }
        
        // Verificar se o usuário já tem uma assinatura para este cheat
        $stmt = $db->prepare("
            SELECT id, end_date FROM user_subscriptions 
            WHERE user_id = ? AND cheat_plan_id = ? AND status = 'active'
        ");
        $stmt->execute([$user_id, $plan_id]);
        $existing_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Data de início e término da assinatura
        $start_date = date('Y-m-d H:i:s');
        $end_date = date('Y-m-d H:i:s', strtotime('+' . $plan['duration_days'] . ' days'));
        
        // Gerar transaction_id
        $transaction_id = $payment_data->id;
        
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
        } else {
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
        
        // Verificar se já existe este pagamento registrado
        $stmt = $db->prepare("
            SELECT id FROM payments 
            WHERE transaction_id = ? AND user_id = ?
        ");
        $stmt->execute([$transaction_id, $user_id]);
        $existing_payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_payment) {
            // Atualizar pagamento existente
            $stmt = $db->prepare("
                UPDATE payments 
                SET status = 'completed', updated_at = NOW(), gateway_response = ? 
                WHERE id = ?
            ");
            $stmt->execute([json_encode($payment_data), $existing_payment['id']]);
        } else {
            // Registrar novo pagamento
            $stmt = $db->prepare("
                INSERT INTO payments 
                (user_id, subscription_id, transaction_id, payment_method, amount, status, gateway_response) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $payment_method = 'mercadopago_' . $payment_data->payment_type_id; // pix, credit_card, etc
            
            $stmt->execute([
                $user_id,
                $subscription_id,
                $transaction_id,
                $payment_method,
                $payment_data->transaction_amount,
                'completed',
                json_encode($payment_data)
            ]);
        }
        
        // Registrar atividade de usuário
        $stmt = $db->prepare("
            INSERT INTO user_logs
            (user_id, action, description, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        
        $log_description = $is_renewal 
            ? "Renovação da assinatura do plano {$plan['name']} por R$ " . number_format($payment_data->transaction_amount, 2, ',', '.')
            : "Nova assinatura do plano {$plan['name']} por R$ " . number_format($payment_data->transaction_amount, 2, ',', '.');
        
        $stmt->execute([
            $user_id,
            'subscription_' . ($is_renewal ? 'renewal' : 'purchase'),
            $log_description,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
        
        // Confirmar transação
        $db->commit();
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("MercadoPago Payment Processing Error: " . $e->getMessage());
    }
}

/**
 * Atualiza o status de um pagamento existente
 */
function updatePaymentStatus($external_reference, $status, $gateway_response) {
    global $db;
    
    try {
        $ref_parts = explode('_', $external_reference);
        
        if (count($ref_parts) >= 4) {
            $plan_id = $ref_parts[1];
            $user_id = $ref_parts[3];
            
            // Buscar pagamento pendente por usuário e plano
            $stmt = $db->prepare("
                SELECT p.id
                FROM payments p
                JOIN user_subscriptions us ON p.subscription_id = us.id
                WHERE p.user_id = ? 
                AND us.cheat_plan_id = ? 
                AND p.status = 'pending'
                ORDER BY p.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id, $plan_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($payment) {
                // Atualizar status do pagamento
                $stmt = $db->prepare("
                    UPDATE payments 
                    SET status = ?, gateway_response = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$status, $gateway_response, $payment['id']]);
            }
        }
    } catch (Exception $e) {
        error_log("MercadoPago Status Update Error: " . $e->getMessage());
    }
}