<?php
// filepath: c:\xampp\htdocs\Gestaocheats\admin\pages\funcaosupport\check_admin_responses.php

// Ativar exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Registrar erros em um arquivo
ini_set('log_errors', 1);
ini_set('error_log', '../../../logs/php-errors.log');

define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

// Verificar se o administrador está logado
if (!is_admin_logged_in()) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Não autorizado', 'code' => 401]);
    exit;
}

// Definir cabeçalhos para evitar cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

// Obter parâmetros
$ticket_id = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if ($ticket_id <= 0) {
    echo json_encode(['error' => 'ID de ticket inválido', 'code' => 400]);
    exit;
}

try {
    // Verificar se o ticket existe
    $stmt = $db->prepare("SELECT id FROM support_tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['error' => 'Ticket não encontrado', 'code' => 404]);
        exit;
    }

    // Buscar novas respostas do usuário (não-admin) desde o último ID
    $stmt = $db->prepare("
        SELECT tr.*, 
               u.username as user_name
        FROM ticket_responses tr
        JOIN users u ON tr.user_id = u.id
        WHERE tr.ticket_id = ? 
        AND tr.id > ? 
        AND tr.admin_id IS NULL
        ORDER BY tr.created_at ASC
    ");
    $stmt->execute([$ticket_id, $last_id]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Encontrar o maior ID de resposta
    $max_id = $last_id;
    foreach ($responses as $response) {
        if ($response['id'] > $max_id) {
            $max_id = $response['id'];
        }
    }

    // Adicionar anexos para cada resposta
    foreach ($responses as &$response) {
        $stmt = $db->prepare("
            SELECT id, file_name, original_name, file_type, is_image, is_video, expires_at
            FROM ticket_attachments
            WHERE response_id = ?
        ");
        $stmt->execute([$response['id']]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Adicionar URLs aos anexos
        foreach ($attachments as &$attachment) {
            $attachment['url'] = '../../../uploads/ticket_media/' . $attachment['file_name'];
        }
        
        $response['attachments'] = $attachments;
    }

    echo json_encode([
        'success' => true,
        'responses' => $responses,
        'max_id' => $max_id,
        'count' => count($responses)
    ]);

} catch (Exception $e) {
    error_log("Erro ao verificar respostas: " . $e->getMessage());
    echo json_encode(['error' => 'Erro ao verificar respostas', 'message' => $e->getMessage()]);
}
?>