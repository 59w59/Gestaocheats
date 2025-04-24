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

// Verificar se o ID do cheat foi fornecido
$cheat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($cheat_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de cheat inválido']);
    exit;
}

try {
    // Buscar detalhes do cheat
    $stmt = $db->prepare("
        SELECT c.*, g.name as game_name 
        FROM cheats c
        JOIN games g ON c.game_id = g.id
        WHERE c.id = ? AND c.is_active = 1
    ");
    $stmt->execute([$cheat_id]);
    $cheat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cheat) {
        throw new Exception('Cheat não encontrado ou não está ativo.');
    }

    // Verificar se o usuário tem assinatura ativa que permite acessar este cheat
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM user_subscriptions us
        JOIN cheat_subscription_plans csp ON us.cheat_plan_id = csp.id
        WHERE us.user_id = ? 
        AND us.status = 'active' 
        AND us.end_date > NOW()
        AND csp.cheat_id = ?
    ");
    $stmt->execute([$user_id, $cheat_id]);
    $has_access = $stmt->fetchColumn();

    // Buscar contagem de downloads do usuário para este cheat
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM user_downloads
        WHERE user_id = ? AND cheat_id = ?
    ");
    $stmt->execute([$user_id, $cheat_id]);
    $download_count = $stmt->fetchColumn();

    // Buscar último download do usuário para este cheat
    $stmt = $db->prepare("
        SELECT created_at FROM user_downloads
        WHERE user_id = ? AND cheat_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id, $cheat_id]);
    $last_download = $stmt->fetchColumn();

    // Preparar resposta
    $response = [
        'cheat' => [
            'id' => $cheat['id'],
            'name' => $cheat['name'],
            'version' => $cheat['version'],
            'game_name' => $cheat['game_name'],
            'short_description' => $cheat['short_description'],
            'total_downloads' => $cheat['download_count']
        ],
        'user_info' => [
            'has_access' => (bool)$has_access,
            'download_count' => $download_count,
            'last_download' => $last_download ? date('d/m/Y H:i', strtotime($last_download)) : null
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}