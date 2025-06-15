<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Verificar se o usuário está logado
if (!is_logged_in()) {
    http_response_code(401);
    exit(json_encode(['error' => 'Não autorizado']));
}

// Obter dados do usuário
$user_id = $_SESSION['user_id'];

// Verificar se ID foi fornecido
if (!isset($_GET['id'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'ID do pagamento não fornecido']));
}

$payment_id = (int)$_GET['id'];

try {
    // Obter status do pagamento
    $stmt = $db->prepare("
        SELECT status FROM payments 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$payment_id, $user_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception('Pagamento não encontrado');
    }
    
    // Em um sistema real, você verificaria com a API do gateway de pagamento
    // Para esta simulação, ocasionalmente atualizamos o status para completed
    if ($payment['status'] === 'pending') {
        // Simulação: 20% de chance de o pagamento ser confirmado a cada verificação
        if (rand(1, 5) === 1) {
            $stmt = $db->prepare("
                UPDATE payments 
                SET status = 'completed', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$payment_id]);
            
            // Registrar log de pagamento confirmado
            $stmt = $db->prepare("
                INSERT INTO user_logs
                (user_id, action, description, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                'payment_confirmed',
                "Pagamento PIX #{$payment_id} confirmado",
                get_client_ip()
            ]);
            
            exit(json_encode([
                'status' => 'completed',
                'message' => 'Pagamento confirmado!'
            ]));
        }
    }
    
    // Retornar status atual
    exit(json_encode([
        'status' => $payment['status'],
        'message' => $payment['status'] === 'completed' ? 'Pagamento confirmado!' : 'Aguardando pagamento'
    ]));

} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode([
        'error' => 'Erro ao verificar status do pagamento',
        'message' => $e->getMessage()
    ]));
}