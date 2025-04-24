<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Registrar o logout nos logs se o usuário estiver logado
if (isset($_SESSION['admin_id'])) {
    try {
        $admin_id = $_SESSION['admin_id'];
        $log_sql = "INSERT INTO activity_logs (user_id, type, action, details, ip_address) 
                    VALUES (?, 'admin', 'logout', ?, ?)";
        $db->prepare($log_sql)->execute([
            $admin_id,
            json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]),
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $e) {
        error_log("Logout log error: " . $e->getMessage());
    }
}

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Destruir o cookie da sessão se existir
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destruir a sessão
session_destroy();

// Redirecionar para a página de login
header('Location: login.php');
exit;