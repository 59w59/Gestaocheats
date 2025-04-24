<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Configurar cabeçalhos para evitar cache
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Debug para requisições AJAX
error_log('check_new_responses.php chamado: ' . json_encode([
    'params' => $_GET,
    'time' => date('Y-m-d H:i:s')
]));

// Verificar se o usuário está logado
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado', 'timestamp' => date('Y-m-d H:i:s')]);
    exit;
}

// Obter dados da solicitação
$user_id = $_SESSION['user_id'];
$ticket_id = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
$last_response_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

error_log("check_new_responses: ticket_id={$ticket_id}, last_id={$last_response_id}, user_id={$user_id}");

if ($ticket_id <= 0) {
    echo json_encode(['error' => 'ID de ticket inválido', 'timestamp' => date('Y-m-d H:i:s')]);
    exit;
}

try {
    // Verificar se o ticket pertence ao usuário
    $stmt = $db->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$ticket_id, $user_id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('Ticket não encontrado ou não pertence a este usuário.');
    }
    
    // Buscar novas respostas (apenas de administradores)
    $stmt = $db->prepare("
        SELECT tr.*, a.username as admin_name
        FROM ticket_responses tr
        LEFT JOIN admins a ON tr.admin_id = a.id
        WHERE tr.ticket_id = ? 
        AND tr.id > ?
        AND tr.admin_id IS NOT NULL
        ORDER BY tr.id ASC
    ");
    $stmt->execute([$ticket_id, $last_response_id]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("check_new_responses: Encontradas " . count($responses) . " novas respostas para ticket {$ticket_id}");
    
    // Determinar o maior ID de resposta
    $max_id = $last_response_id;
    foreach ($responses as $response) {
        if ((int)$response['id'] > $max_id) {
            $max_id = (int)$response['id'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'responses' => $responses,
        'max_id' => $max_id,
        'count' => count($responses),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Erro em check_new_responses.php: " . $e->getMessage());
    echo json_encode([
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>