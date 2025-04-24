<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

// Verificar autenticação do admin
if (!is_admin_logged_in()) {
    header('Location: ../../login.php');
    exit;
}

// Função helper para obter IP do cliente
function get_client_ip() {
    $ip = '';
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

try {
    // Obtém o período de retenção das configurações ou usa valor padrão
    $retention_days = (int)get_setting('log_retention_days', 90);
    
    // Valida o valor mínimo de retenção
    $retention_days = max($retention_days, 7); // Mínimo de 7 dias
    
    // Deleta logs antigos
    $sql = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$retention_days]);
    
    $deleted_count = $stmt->rowCount();

    // Registra a ação
    $admin_id = $_SESSION['admin_id'];
    $log_sql = "INSERT INTO activity_logs (user_id, type, action, details, ip_address) VALUES (?, 'admin', 'clear_logs', ?, ?)";
    $log_stmt = $db->prepare($log_sql);
    $log_stmt->execute([
        $admin_id,
        json_encode([
            'deleted_count' => $deleted_count,
            'retention_days' => $retention_days,
            'timestamp' => date('Y-m-d H:i:s')
        ]),
        get_client_ip()
    ]);
    
    // Redireciona com mensagem de sucesso
    header('Location: ../logs.php?success=clear&count=' . $deleted_count);
    exit;
    
} catch (Exception $e) {
    error_log("Error clearing logs: " . $e->getMessage());
    header('Location: ../logs.php?error=clear');
    exit;
}