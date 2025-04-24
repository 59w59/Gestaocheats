<?php
/**
 * Arquivo de logout
 *
 * Este arquivo encerra a sessão do usuário, remove os tokens de "lembrar-me"
 * e redireciona para a página de login.
 */

// Iniciar a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir os arquivos necessários
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

// Verificar se o usuário está logado
$user_id = $_SESSION['user_id'] ?? null;

// Registrar o logout no log de atividades (se o usuário estiver logado)
if ($user_id) {
    // Registrar atividade de logout
    $stmt = $db->prepare("INSERT INTO user_logs (user_id, action, description, ip_address) 
                           VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, 'logout', 'Logout do sistema', get_client_ip()]);
    
    // Remover tokens de lembrar-me para este usuário (se o parâmetro 'all_devices' estiver definido)
    // ou apenas o token atual (se existir)
    if (isset($_GET['all_devices']) && $_GET['all_devices'] == 1) {
        // Remover todos os tokens deste usuário
        $stmt = $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } elseif (isset($_COOKIE['remember_token'])) {
        // Remover apenas o token atual
        $stmt = $db->prepare("DELETE FROM remember_tokens WHERE token = ?");
        $stmt->execute([$_COOKIE['remember_token']]);
        
        // Remover o cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Definir mensagem de sucesso para a página de login (usando session flash)
    $_SESSION['flash_message'] = "Você saiu com sucesso.";
    $_SESSION['flash_type'] = "success";
}

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Destruir o cookie da sessão se existir
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Iniciar uma nova sessão para armazenar a mensagem de sucesso
session_start();
$_SESSION['logout_success'] = true;

// Redirecionar para a página de login
header('Location: ../pages/login.php');
exit();
?>