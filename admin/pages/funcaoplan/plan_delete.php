<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

if (!is_admin_logged_in()) {
    header('Location: ../../login.php');
    exit;
}

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Verificar se existem assinaturas ativas
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM user_subscriptions 
        WHERE cheat_plan_id = ? AND status = 'active'
    ");
    $stmt->execute([$id]);
    $active_subscriptions = $stmt->fetchColumn();

    if ($active_subscriptions > 0) {
        throw new Exception('Não é possível excluir um plano com assinaturas ativas');
    }

    // Excluir plano
    $stmt = $db->prepare("DELETE FROM cheat_subscription_plans WHERE id = ?");
    $stmt->execute([$id]);

    $_SESSION['success'] = 'Plano excluído com sucesso!';

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header('Location: ../plans.php');
exit;