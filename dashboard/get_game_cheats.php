<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Verificar se o usuário está logado
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Obter dados do usuário
$user_id = $_SESSION['user_id'];

// Verificar se o ID do jogo foi fornecido
$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($game_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

try {
    // Buscar cheats para este jogo que o usuário tem acesso
    $stmt = $db->prepare("
        SELECT DISTINCT c.id, c.name, c.version
        FROM cheats c
        JOIN cheat_subscription_plans csp ON csp.cheat_id = c.id
        JOIN user_subscriptions us ON us.cheat_plan_id = csp.id
        WHERE c.game_id = ?
        AND us.user_id = ?
        AND us.status = 'active'
        AND us.end_date > NOW()
        AND c.is_active = 1
        ORDER BY c.name
    ");
    
    $stmt->execute([$game_id, $user_id]);
    $cheats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($cheats);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}