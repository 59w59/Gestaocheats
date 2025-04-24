<?php
// filepath: c:\xampp\htdocs\Gestaocheats\dashboard\process_ticket_upload.php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ativar log de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log para debug
error_log("Requisição de upload recebida: " . json_encode($_POST) . ", FILES: " . json_encode($_FILES));

// Verificar se o usuário está logado
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized', 'code' => 401]);
    exit;
}

// Obter dados do usuário
$user_id = $_SESSION['user_id'];

// Verificar se é uma requisição POST com upload de arquivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    try {
        // Obter dados do upload
        $ticket_id = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $response_id = isset($_POST['response_id']) ? (int)$_POST['response_id'] : null;
        
        // Validar IDs
        if ($ticket_id <= 0) {
            throw new Exception('ID de ticket inválido');
        }
        
        // Verificar se o ticket pertence ao usuário
        $stmt = $db->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
        $stmt->execute([$ticket_id, $user_id]);
        if ($stmt->rowCount() === 0) {
            throw new Exception('Ticket não encontrado ou não pertence a este usuário');
        }
        
        // Validar o arquivo
        $file = $_FILES['file'];
        
        // Verificar erros de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo servidor',
                UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido pelo formulário',
                UPLOAD_ERR_PARTIAL => 'O upload do arquivo foi interrompido',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
                UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao salvar o arquivo',
                UPLOAD_ERR_EXTENSION => 'Upload interrompido por uma extensão PHP'
            ];
            throw new Exception($error_messages[$file['error']] ?? 'Erro desconhecido no upload');
        }
        
        // Verificar tamanho (máximo 10MB)
        $max_size = 10 * 1024 * 1024; // 10 MB
        if ($file['size'] > $max_size) {
            throw new Exception('O arquivo excede o tamanho máximo permitido (10MB)');
        }
        
        // Verificar tipo usando função personalizada para evitar problemas com mime_content_type
        function get_mime_type($file) {
            // Tenta usar mime_content_type se disponível
            if (function_exists('mime_content_type')) {
                return mime_content_type($file);
            }
            
            // Se não tiver mime_content_type, verifica pela extensão
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $mime_types = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'mov' => 'video/quicktime'
            ];
            
            return isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
        }
        
        $mime_type = get_mime_type($file['tmp_name']);
        if ($mime_type === 'application/octet-stream') {
            // Se não conseguir detectar, usa a extensão do arquivo
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $mime_type = 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension);
            } elseif (in_array($extension, ['mp4', 'webm', 'mov'])) {
                $mime_type = 'video/' . ($extension === 'mov' ? 'quicktime' : $extension);
            }
        }
        
        error_log("[UPLOAD] Mime type detectado: " . $mime_type);
        
        $allowed_types = [
            // Imagens
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            // Vídeos
            'video/mp4', 'video/webm', 'video/quicktime'
        ];
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception('Tipo de arquivo não permitido. Apenas imagens (JPG, PNG, GIF) e vídeos (MP4, WEBM) são aceitos');
        }
        
        // Definir o tipo de mídia
        $is_image = strpos($mime_type, 'image/') === 0;
        $is_video = strpos($mime_type, 'video/') === 0;
        
        // Criar diretório se não existir
        $upload_dir = '../uploads/ticket_media/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                error_log("[UPLOAD] Falha ao criar diretório: " . $upload_dir);
                throw new Exception('Falha ao criar diretório para uploads');
            }
            error_log("[UPLOAD] Diretório criado: " . $upload_dir);
        }
        
        // Gerar nome único para o arquivo
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_filename = uniqid('ticket_' . $ticket_id . '_') . '.' . $file_extension;
        $upload_path = $upload_dir . $unique_filename;
        
        error_log("[UPLOAD] Salvando arquivo em: " . $upload_path);
        
        // Salvar o arquivo
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            error_log("[UPLOAD] Falha ao mover arquivo para: " . $upload_path);
            throw new Exception('Falha ao salvar o arquivo');
        }
        
        error_log("[UPLOAD] Arquivo salvo com sucesso");
        
        // Definir data de expiração (24 horas a partir de agora)
        $expires_at = date('Y-m-d H:i:s', time() + (24 * 60 * 60));
        
        // Iniciar transação
        $db->beginTransaction();
        
        // Inserir registro na tabela de anexos
        $stmt = $db->prepare("
            INSERT INTO ticket_attachments (
                ticket_id, 
                response_id,
                file_name, 
                original_name, 
                file_type, 
                file_size, 
                is_image, 
                is_video,
                expires_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $ticket_id,
            $response_id,
            $unique_filename,
            $file['name'],
            $mime_type,
            $file['size'],
            $is_image ? 1 : 0,
            $is_video ? 1 : 0,
            $expires_at
        ]);
        
        $attachment_id = $db->lastInsertId();
        
        $db->commit();
        
        // Retornar sucesso com dados do arquivo
        $file_data = [
            'id' => $attachment_id,
            'file_name' => $unique_filename,
            'original_name' => $file['name'],
            'file_type' => $mime_type,
            'file_size' => $file['size'],
            'is_image' => $is_image,
            'is_video' => $is_video,
            'url' => '../uploads/ticket_media/' . $unique_filename,
            'expires_at' => $expires_at,
            'success' => true
        ];
        
        error_log("[UPLOAD] Sucesso: " . json_encode($file_data));
        
        header('Content-Type: application/json');
        echo json_encode($file_data);
        
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("[UPLOAD] Erro: " . $e->getMessage());
        
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $e->getMessage(),
            'success' => false
        ]);
    }
} else {
    error_log("[UPLOAD] Requisição inválida: Método=" . $_SERVER['REQUEST_METHOD'] . ", FILES=" . json_encode($_FILES));
    
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Requisição inválida', 'code' => 400]);
}