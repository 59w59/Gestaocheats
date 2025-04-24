<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Verificar se o administrador está logado
if (!isset($_SESSION['admin_id'])) {
    redirect('login.php');
    exit;
}

// Redireciona para o dashboard administrativo
header('Location: AdminDashboard.php');
exit;
?>