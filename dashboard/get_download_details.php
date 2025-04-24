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

// Verificar se o ID do download foi fornecido
$download_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($download_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de download inválido']);
    exit;
}

try {
    // Buscar detalhes do download
    $stmt = $db->prepare("
        SELECT 
            ud.id,
            ud.created_at as download_date,
            ud.ip_address,
            ud.user_agent,
            c.name as cheat_name,
            c.version,
            g.name as game_name
        FROM user_downloads ud
        JOIN cheats c ON ud.cheat_id = c.id
        JOIN games g ON c.game_id = g.id
        WHERE ud.id = ? AND ud.user_id = ?
    ");
    
    $stmt->execute([$download_id, $user_id]);
    $download = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$download) {
        throw new Exception('Download não encontrado ou não pertence a este usuário');
    }

    header('Content-Type: application/json');
    echo json_encode($download);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}