<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

// Verificar se o administrador está logado
if (!is_admin_logged_in()) {
    header('Location: ../../login.php');
    exit;
}

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Verificar se o usuário existe
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Usuário não encontrado');
    }

    if ($user['status'] === 'banned') {
        throw new Exception('Este usuário já está banido');
    }

    // Iniciar transação
    $db->beginTransaction();

    try {
        // Atualizar status do usuário
        $stmt = $db->prepare("
            UPDATE users 
            SET status = 'banned',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        // Cancelar todas as assinaturas ativas
        $stmt = $db->prepare("
            UPDATE user_subscriptions 
            SET status = 'cancelled',
                cancelled_at = NOW(),
                cancel_reason = 'User banned by admin'
            WHERE user_id = ? AND status = 'active'
        ");
        $stmt->execute([$id]);

        // Registrar log de banimento
        $admin_id = $_SESSION['admin_id'];
        $ip = $_SERVER['REMOTE_ADDR'];
        
        $stmt = $db->prepare("
            INSERT INTO user_logs (
                user_id, 
                action, 
                details, 
                ip_address, 
                created_by
            ) VALUES (?, 'banned', ?, ?, ?)
        ");
        
        $details = json_encode([
            'banned_by' => $admin_id,
            'banned_at' => date('Y-m-d H:i:s'),
            'previous_status' => $user['status']
        ]);
        
        $stmt->execute([$id, $details, $ip, $admin_id]);

        // Commit transação
        $db->commit();

        // Redirecionar com mensagem de sucesso
        $_SESSION['success'] = 'Usuário banido com sucesso';
        header('Location: ../users.php');
        exit;

    } catch (Exception $e) {
        // Rollback em caso de erro
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ../users.php');
    exit;
}
?>