<?php
/**
 * Arquivo de logout
 * 
 * Este arquivo encerra a sessão do usuário, remove os tokens de "lembrar-me" 
 * e redireciona para a página de login.
 */

// Incluir os arquivos necessários
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Registrar o logout no log de atividades (se o usuário estiver logado)
if (isset($_SESSION['user_id'])) {
    // Logout em todos os dispositivos (opcional - remova o parâmetro para desconectar apenas o dispositivo atual)
    $all_devices = isset($_GET['all_devices']) && $_GET['all_devices'] == 1;
    
    // Usar o método de logout da classe Auth
    $auth->logout($all_devices);
    
    // Definir mensagem de sucesso para a página de login
    $_SESSION['logout_success'] = true;
}

// Redirecionar para a página de login
redirect('pages/login.php');
?>