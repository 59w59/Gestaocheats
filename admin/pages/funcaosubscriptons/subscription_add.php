<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

// Check admin authentication
if (!is_admin_logged_in()) {
    header('Location: ../../login.php');
    exit;
}

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido');
    }
    
    // Get form data
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
    $duration_days = isset($_POST['duration_days']) ? (int)$_POST['duration_days'] : 0;
    
    // Validate data
    if ($user_id <= 0) throw new Exception('ID de usuário inválido');
    if ($plan_id <= 0) throw new Exception('Plano não selecionado');
    if ($duration_days <= 0) throw new Exception('Duração deve ser maior que zero');
    
    // Check if user exists
    $stmt = $db->prepare("SELECT id, status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Usuário não encontrado');
    }
    
    if ($user['status'] !== 'active') {
        throw new Exception('Não é possível adicionar assinatura para um usuário inativo ou banido');
    }
    
    // Get plan details
    $stmt = $db->prepare("SELECT * FROM cheat_subscription_plans WHERE id = ? AND is_active = 1");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) {
        throw new Exception('Plano não encontrado ou inativo');
    }
    
    // Create subscription
    $now = new DateTime();
    $end_date = (new DateTime())->modify("+{$duration_days} days");
    
    $stmt = $db->prepare("
        INSERT INTO user_subscriptions (
            user_id, cheat_plan_id, status, start_date, end_date, 
            created_at, updated_at
        ) VALUES (?, ?, 'active', ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $plan_id,
        $now->format('Y-m-d H:i:s'),
        $end_date->format('Y-m-d H:i:s')
    ]);
    
    // Log admin action
    $admin_id = $_SESSION['admin_id'];
    log_admin_action($admin_id, "add_subscription: Admin #{$admin_id} adicionou assinatura do plano #{$plan_id} para o usuário #{$user_id}");
    
    // Redirect back with success
    $_SESSION['success'] = 'Assinatura adicionada com sucesso!';
    header("Location: user_subscriptions.php?id={$user_id}");
    exit;
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: user_subscriptions.php?id={$_POST['user_id']}");
    exit;
}