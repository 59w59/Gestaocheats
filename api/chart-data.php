<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';
require_once '../../includes/admin_auth.php';

// Verificar se o administrador está logado
if (!is_admin_logged_in()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Obter parâmetros da requisição
$type = $_GET['type'] ?? '';
$range = intval($_GET['range'] ?? 30);

// Validar o intervalo
if (!in_array($range, [7, 30, 90])) {
    $range = 30;
}

// Retornar dados com base no tipo de gráfico
header('Content-Type: application/json');

if ($type === 'revenue') {
    $data = get_revenue_chart_data($range);
    echo json_encode($data);
} elseif ($type === 'users') {
    $data = get_users_chart_data($range);
    echo json_encode($data);
} else {
    echo json_encode(['error' => 'Tipo de gráfico inválido']);
}