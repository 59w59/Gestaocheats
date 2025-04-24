<?php
// filepath: c:\xampp\htdocs\Gestaocheats\admin\maintenance\clean_expired_media.php
define('INCLUDED_FROM_INDEX', true);
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Este script deve ser executado via cron job ou tarefa agendada do Windows

// Log para registro de execução
$log_file = __DIR__ . '/media_cleanup_log.txt';
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND);
}

log_message('Iniciando limpeza de mídia expirada');

try {
    // Buscar anexos expirados
    $stmt = $db->prepare("
        SELECT id, file_name 
        FROM ticket_attachments 
        WHERE expires_at < NOW()
    ");
    $stmt->execute();
    $expired_attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    log_message('Encontrados ' . count($expired_attachments) . ' arquivos expirados');
    
    if (empty($expired_attachments)) {
        log_message('Nenhum arquivo expirado para limpar');
        exit;
    }
    
    $success_count = 0;
    $failed_count = 0;
    
    foreach ($expired_attachments as $attachment) {
        $file_path = __DIR__ . '/../../uploads/ticket_media/' . $attachment['file_name'];
        
        // Excluir o arquivo físico
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                log_message("Arquivo excluído com sucesso: {$attachment['file_name']}");
                $success_count++;
            } else {
                log_message("ERRO: Não foi possível excluir o arquivo: {$attachment['file_name']}");
                $failed_count++;
            }
        } else {
            log_message("Arquivo não encontrado (já excluído): {$attachment['file_name']}");
            $success_count++;
        }
        
        // Excluir o registro do banco de dados
        $stmt = $db->prepare("DELETE FROM ticket_attachments WHERE id = ?");
        $stmt->execute([$attachment['id']]);
    }
    
    log_message("Limpeza concluída. Sucesso: {$success_count}, Falhas: {$failed_count}");
    
} catch (Exception $e) {
    log_message("ERRO: {$e->getMessage()}");
}

log_message('Finalizando limpeza de mídia expirada');