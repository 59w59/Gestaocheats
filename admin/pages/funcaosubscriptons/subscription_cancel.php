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
    $subscription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($subscription_id <= 0) {
        throw new Exception('ID de assinatura inválido');
    }
    
    // Get subscription details
    $stmt = $db->prepare("
        SELECT us.*, u.id as user_id 
        FROM user_subscriptions us
        JOIN users u ON us.user_id = u.id
        WHERE us.id = ?
    ");
    $stmt->execute([$subscription_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscription) {
        throw new Exception('Assinatura não encontrada');
    }
    
    if ($subscription['status'] !== 'active') {
        throw new Exception('Esta assinatura já está cancelada ou expirada');
    }
    
    // Cancel subscription
    $stmt = $db->prepare("
        UPDATE user_subscriptions 
        SET status = 'cancelled', updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$subscription_id]);
    
    // Log admin action
    $admin_id = $_SESSION['admin_id'];
    log_admin_action($admin_id, "cancel_subscription: Admin #{$admin_id} cancelou a assinatura #{$subscription_id}");
    
    // Redirect back with success
    $_SESSION['success'] = 'Assinatura cancelada com sucesso!';
    header("Location: user_subscriptions.php?id={$subscription['user_id']}");
    exit;
    
} catch (Exception $e) {
    // Try to get user ID
    try {
        if (isset($subscription) && isset($subscription['user_id'])) {
            $user_id = $subscription['user_id'];
        } else {
            $stmt = $db->prepare("SELECT user_id FROM user_subscriptions WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $user_id = $stmt->fetchColumn();
        }
    } catch (Exception $e2) {
        $user_id = 0;
    }
    
    $_SESSION['error'] = $e->getMessage();
    
    if ($user_id) {
        header("Location: user_subscriptions.php?id={$user_id}");
    } else {
        header("Location: ../users.php");
    }
    exit;
}