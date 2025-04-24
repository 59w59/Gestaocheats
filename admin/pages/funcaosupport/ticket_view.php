<?php
// Ativar exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Registrar erros em um arquivo
ini_set('log_errors', 1);
ini_set('error_log', '../../../logs/php-errors.log');

// Verificar inclusão de arquivos
$files_to_check = [
    '../../../includes/config.php',
    '../../../includes/db.php',
    '../../../includes/functions.php',
    '../../../includes/admin_functions.php',
    '../../../includes/admin_auth.php',
    '../../includes/admin-sidebar.php'
];

foreach ($files_to_check as $file) {
    if (!file_exists($file)) {
        die("Erro: Arquivo não encontrado: $file");
    }
}

// filepath: c:\xampp\htdocs\Gestaocheats\admin\pages\funcaosupport\ticket_view.php
define('INCLUDED_FROM_INDEX', true);
require_once '../../../includes/config.php';
require_once '../../../includes/db.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/admin_functions.php';
require_once '../../../includes/admin_auth.php';

// Verificar se o administrador está logado
if (!is_admin_logged_in()) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$success_message = '';

try {
    $ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($ticket_id <= 0) {
        header('Location: support.php');
        exit;
    }
    
    // Buscar detalhes do ticket
    $stmt = $db->prepare("
        SELECT st.*, 
               u.username, 
               u.email 
        FROM support_tickets st 
        LEFT JOIN users u ON st.user_id = u.id 
        WHERE st.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        throw new Exception('Ticket não encontrado');
    }
    
    // Buscar respostas do ticket
    $stmt = $db->prepare("
        SELECT tr.*, 
               u.username as user_name, 
               a.username as admin_name
        FROM ticket_responses tr
        LEFT JOIN users u ON tr.user_id = u.id
        LEFT JOIN admins a ON tr.admin_id = a.id
        WHERE tr.ticket_id = ?
        ORDER BY tr.created_at ASC
    ");
    $stmt->execute([$ticket_id]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Processar formulário de resposta
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'reply') {
            $message = trim($_POST['message'] ?? '');
            $admin_id = $_SESSION['admin_id'];
            
            // Verificar se é uma requisição AJAX
            $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                       strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
            
            if (empty($message)) {
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Por favor, forneça uma mensagem de resposta']);
                    exit;
                } else {
                    throw new Exception('Por favor, forneça uma mensagem de resposta');
                }
            }
            
            // Iniciar transação
            $db->beginTransaction();
            
            try {
                // Inserir resposta
                $stmt = $db->prepare("
                    INSERT INTO ticket_responses 
                    (ticket_id, admin_id, message) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$ticket_id, $admin_id, $message]);
                
                $response_id = $db->lastInsertId();
                
                // Processar anexos
                $attachment_ids = $_POST['attachment_ids'] ?? [];
                $attachments = [];

                if (!empty($attachment_ids)) {
                    error_log('Attachment IDs recebidos: ' . json_encode($attachment_ids));
                    
                    foreach ($attachment_ids as $attachment_id) {
                        // Associar anexo à resposta
                        $stmt = $db->prepare("
                            UPDATE ticket_attachments 
                            SET response_id = ? 
                            WHERE id = ? AND ticket_id = ?
                        ");
                        $stmt->execute([$response_id, $attachment_id, $ticket_id]);
                        error_log("Atualizando anexo ID $attachment_id para response_id $response_id: " . $stmt->rowCount() . " registros afetados");
                        
                        if ($stmt->rowCount() > 0) {
                            // Buscar detalhes do anexo para incluir na resposta JSON
                            $stmt = $db->prepare("
                                SELECT id, file_name, original_name, file_type, is_image, is_video, expires_at
                                FROM ticket_attachments
                                WHERE id = ?
                            ");
                            $stmt->execute([$attachment_id]);
                            $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($attachment) {
                                $attachments[] = $attachment;
                            }
                        }
                    }
                }
                
                // Adicionar log de erro
                error_log("[ADMIN] Nova resposta ID: $response_id adicionada ao ticket #$ticket_id por admin #$admin_id");
                
                // Atualizar status do ticket para "in_progress"
                if ($ticket['status'] !== 'closed') {
                    $stmt = $db->prepare("
                        UPDATE support_tickets 
                        SET status = 'in_progress', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$ticket_id]);
                }
                
                // Registrar atividade do admin
                log_admin_action($admin_id, "ticket_reply", "Respondeu ao ticket #{$ticket['ticket_id']}");
                
                $db->commit();
                
                if ($is_ajax) {
                    // Buscar nome do admin
                    $stmt = $db->prepare("SELECT username FROM admins WHERE id = ?");
                    $stmt->execute([$admin_id]);
                    $admin_name = $stmt->fetchColumn();
                    
                    // Retornar os dados da nova resposta em JSON
                    $response_data = [
                        'id' => $response_id,
                        'ticket_id' => $ticket_id,
                        'admin_id' => $admin_id,
                        'message' => $message,
                        'attachments' => $attachments,
                        'created_at' => date('Y-m-d H:i:s'),
                        'admin_name' => $admin_name,
                        'success' => true
                    ];
                    
                    header('Content-Type: application/json');
                    echo json_encode($response_data);
                    exit;
                } else {
                    $success_message = "Resposta enviada com sucesso";
                    
                    // Recarregar página para atualizar dados
                    header("Location: ticket_view.php?id={$ticket_id}&success=reply_sent");
                    exit;
                }
                
            } catch (Exception $e) {
                $db->rollBack();
                
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => $e->getMessage()]);
                    exit;
                } else {
                    throw $e;
                }
            }
        }
        
        if ($_POST['action'] === 'update_status') {
            $status = $_POST['status'] ?? '';
            $valid_statuses = ['open', 'in_progress', 'closed'];
            
            if (!in_array($status, $valid_statuses)) {
                throw new Exception('Status inválido');
            }
            
            $stmt = $db->prepare("
                UPDATE support_tickets 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $ticket_id]);
            
            // Registrar atividade do admin
            log_admin_action($_SESSION['admin_id'], "update_ticket_status", "Atualizou o status do ticket #{$ticket['ticket_id']} para '{$status}'");
            
            $success_message = "Status do ticket atualizado para " . ucfirst($status);
            
            // Recarregar página para atualizar dados
            header("Location: ticket_view.php?id={$ticket_id}&success=status_updated");
            exit;
        }
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Funções auxiliares
function get_priority_badge($priority) {
    switch ($priority) {
        case 'high':
            return '<span class="badge bg-danger">Alta</span>';
        case 'medium':
            return '<span class="badge bg-warning">Média</span>';
        case 'low':
            return '<span class="badge bg-info">Baixa</span>';
        default:
            return '<span class="badge bg-secondary">Desconhecida</span>';
    }
}

function get_status_badge($status) {
    switch ($status) {
        case 'open':
            return '<span class="badge bg-success">Aberto</span>';
        case 'in_progress':
            return '<span class="badge bg-primary">Em Andamento</span>';
        case 'closed':
            return '<span class="badge bg-secondary">Fechado</span>';
        default:
            return '<span class="badge bg-secondary">Desconhecido</span>';
    }
}

function get_category_badge($category) {
    switch ($category) {
        case 'technical':
            return '<span class="badge bg-info">Técnico</span>';
        case 'billing':
            return '<span class="badge bg-warning">Pagamento</span>';
        case 'account':
            return '<span class="badge bg-primary">Conta</span>';
        case 'other':
            return '<span class="badge bg-secondary">Outro</span>';
        default:
            return '<span class="badge bg-secondary">Desconhecido</span>';
    }
}

// Verificar sucesso
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'reply_sent':
            $success_message = 'Resposta enviada com sucesso!';
            break;
        case 'status_updated':
            $success_message = 'Status do ticket atualizado com sucesso!';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Ticket #<?php echo htmlspecialchars($ticket['ticket_id'] ?? ''); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/variables.css">
    <link rel="stylesheet" href="../../../assets/css/AdminPanelStyles.css">
    <link rel="stylesheet" href="../../../assets/css/custom.css">
    <link rel="stylesheet" href="../../..assets/css/scroll.css">
</head>
<!-- Adicione estes estilos ao cabeçalho, após as outras tags de estilo -->
<style>
    /* Estilos para upload e visualização de mídia */
    .media-upload-container {
        background-color: rgba(0,0,0,0.03);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    .attachments-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }

    .attachment-preview {
        position: relative;
        width: 100px;
        height: 100px;
        background-color: #f8f9fa;
        border-radius: 4px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .attachment-preview img {
        max-width: 100%;
        max-height: 100%;
        object-fit: cover;
    }

    .attachment-preview video {
        max-width: 100%;
        max-height: 100%;
        object-fit: cover;
    }

    .attachment-preview .remove-attachment {
        position: absolute;
        top: 2px;
        right: 2px;
        background-color: rgba(0,0,0,0.7);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        cursor: pointer;
    }

    .message-attachments {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
        padding: 5px;
        background-color: rgba(0,0,0,0.02);
        border-radius: 4px;
    }

    .message-attachment {
        position: relative;
    }

    .message-attachment img {
        max-width: 150px;
        max-height: 150px;
        border-radius: 4px;
        cursor: pointer;
    }

    .message-attachment video {
        max-width: 240px;
        max-height: 180px;
        border-radius: 4px;
        cursor: pointer;
    }

    .attachment-expiry {
        font-size: 0.7em;
        color: #ff9800;
        display: block;
        margin-top: 3px;
    }

    /* Modal para visualização de imagens */
    .media-viewer-modal .modal-content {
        background-color: rgba(0, 0, 0, 0.85);
        border: none;
    }

    .media-viewer-modal .modal-body {
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .media-viewer-modal img {
        max-width: 100%;
        max-height: 80vh;
    }

    .media-viewer-modal video {
        max-width: 100%;
        max-height: 80vh;
    }

    .media-viewer-modal .close-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        color: white;
        background-color: rgba(0, 0, 0, 0.5);
        border: none;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        z-index: 10;
    }

    .upload-progress {
        height: 3px;
        background-color: var(--primary);
        width: 0;
        transition: width 0.2s;
    }

    .uploading .upload-progress {
        display: block;
    }
</style>

<body class="admin-page">
    <?php include '../../includes/admin-sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-content">
            <div class="admin-card">
                <div class="admin-card-header d-flex justify-content-between align-items-center">
                    <h3>Ticket #<?php echo htmlspecialchars($ticket['ticket_id'] ?? ''); ?></h3>
                    <a href="../support.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
                
                <div class="admin-card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($ticket)): ?>
                        <div class="ticket-details mb-4">
                            <div class="row">
                                <div class="col-md-8">
                                    <h4><?php echo htmlspecialchars($ticket['subject'] ?? ''); ?></h4>
                                    <div class="d-flex gap-2 mt-2">
                                        <?php echo get_status_badge($ticket['status']); ?>
                                        <?php echo get_category_badge($ticket['category']); ?>
                                        <?php echo get_priority_badge($ticket['priority']); ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="ticket-meta">
                                        <p><strong>Usuário:</strong> <a href="funcaousers/user_edit.php?id=<?php echo $ticket['user_id']; ?>"><?php echo htmlspecialchars($ticket['username']); ?></a></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($ticket['email']); ?></p>
                                        <p><strong>Criado em:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></p>
                                        <p><strong>Última atualização:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket['updated_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="ticket-actions mt-3 d-flex gap-2">
                                <form action="ticket_view.php?id=<?php echo $ticket_id; ?>" method="POST">
                                    <input type="hidden" name="action" value="update_status">
                                    <div class="input-group">
                                        <select name="status" class="form-select">
                                            <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Aberto</option>
                                            <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>Em Andamento</option>
                                            <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Fechado</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Atualizar Status
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="conversation-container">
                            <h4 class="mb-3">Conversa</h4>
                            
                            <!-- Original Message -->
                            <div class="message user-message">
                                <div class="message-header">
                                    <span class="user-name"><?php echo htmlspecialchars($ticket['username']); ?> (Cliente)</span>
                                    <span class="message-date"><?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></span>
                                </div>
                                <div class="message-body">
                                    <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
                                </div>
                            </div>
                            
                            <!-- Responses -->
                            <?php foreach ($responses as $response): ?>
                                <?php
                                $is_admin = !empty($response['admin_id']);
                                $author_name = $is_admin ? $response['admin_name'] . ' (Atendente)' : $response['user_name'] . ' (Cliente)';
                                ?>
                                <div class="message <?php echo $is_admin ? 'admin-message' : 'user-message'; ?>" data-response-id="<?php echo $response['id']; ?>">
                                    <div class="message-header">
                                        <span class="user-name"><?php echo htmlspecialchars($author_name); ?></span>
                                        <span class="message-date"><?php echo date('d/m/Y H:i', strtotime($response['created_at'])); ?></span>
                                    </div>
                                    <div class="message-body">
                                        <?php echo nl2br(htmlspecialchars($response['message'])); ?>
                                        
                                        <?php
                                        // Buscar anexos para esta resposta
                                        $stmt = $db->prepare("
                                            SELECT id, file_name, original_name, file_type, is_image, is_video, expires_at
                                            FROM ticket_attachments
                                            WHERE response_id = ?
                                        ");
                                        $stmt->execute([$response['id']]);
                                        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (!empty($attachments)): ?>
                                            <div class="message-attachments">
                                                <?php foreach ($attachments as $attachment): ?>
                                                    <div class="message-attachment">
                                                        <?php if ($attachment['is_image']): ?>
                                                            <img src="../../../uploads/ticket_media/<?php echo htmlspecialchars($attachment['file_name']); ?>" 
                                                                alt="<?php echo htmlspecialchars($attachment['original_name']); ?>"
                                                                title="<?php echo htmlspecialchars($attachment['original_name']); ?>">
                                                        <?php elseif ($attachment['is_video']): ?>
                                                            <video src="../../../uploads/ticket_media/<?php echo htmlspecialchars($attachment['file_name']); ?>"
                                                                title="<?php echo htmlspecialchars($attachment['original_name']); ?>"
                                                                preload="metadata"></video>
                                                        <?php else: ?>
                                                            <a href="../../../uploads/ticket_media/<?php echo htmlspecialchars($attachment['file_name']); ?>" 
                                                               download="<?php echo htmlspecialchars($attachment['original_name']); ?>"
                                                               class="btn btn-sm btn-outline-secondary">
                                                                <i class="fas fa-file"></i> <?php echo htmlspecialchars($attachment['original_name']); ?>
                                                            </a>
                                                        <?php endif; ?>
                                                        <span class="attachment-expiry">
                                                            <i class="fas fa-clock me-1"></i> Expira em: <?php echo date('d/m H:i', strtotime($attachment['expires_at'])); ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Reply Form -->
                        <?php if ($ticket['status'] !== 'closed'): ?>
                            <div class="reply-form mt-4">
                                <h4>Responder ao Ticket</h4>
                                <form id="replyTicketForm" action="ticket_view.php?id=<?php echo $ticket_id; ?>" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="reply">
                                    
                                    <div class="form-group mb-3">
                                        <label for="message">Sua resposta:</label>
                                        <textarea name="message" id="message" class="form-control" rows="5" placeholder="Digite sua resposta aqui..." required></textarea>
                                    </div>
                                    
                                    <!-- Área de upload de arquivos -->
                                    <div class="form-group mb-3">
                                        <label for="admin-attachments">Anexos (opcional):</label>
                                        <div class="media-upload-container">
                                            <div class="input-group">
                                                <input type="file" class="form-control" id="admin-attachments" name="attachments[]" multiple accept="image/*,video/*">
                                                <button class="btn btn-outline-secondary" type="button" id="clearAttachments">
                                                    <i class="fas fa-times"></i> Limpar
                                                </button>
                                            </div>
                                            <small class="form-text text-muted">
                                                Formatos suportados: JPG, PNG, GIF, MP4, WEBM. Tamanho máximo: 10MB.
                                                <br>Os arquivos serão excluídos automaticamente após 24 horas.
                                            </small>
                                            
                                            <!-- Área de preview -->
                                            <div class="attachments-preview mt-2" id="attachmentsPreview"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Enviar Resposta
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mt-4">
                                <i class="fas fa-info-circle"></i> Este ticket está fechado. Para responder, você deve alterar o status para "Aberto" ou "Em Andamento".
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="alert alert-danger">
                            Ticket não encontrado.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animação para novos elementos
            document.querySelectorAll('.message').forEach(function(message, index) {
                message.style.opacity = '0';
                setTimeout(function() {
                    message.style.transition = 'opacity 0.5s ease';
                    message.style.opacity = '1';
                }, 100 * (index + 1));
            });
            
            // Substitua todo o evento de envio do formulário com este código
            const replyForm = document.getElementById('replyTicketForm') || document.querySelector('form[action*="ticket_view.php"]');
            if (replyForm) {
                replyForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const textarea = this.querySelector('textarea');
                    
                    // Verificar se o textarea existe
                    if (!textarea) {
                        console.error('Textarea não encontrado no formulário.');
                        return;
                    }
                    
                    // Verificar validação
                    if (!textarea.value.trim()) {
                        alert('Por favor, digite uma resposta.');
                        return;
                    }
                    
                    // Mostrar indicador de carregamento
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';
                    submitBtn.disabled = true;
                    
                    // *** IMPORTANTE: Adicionar os IDs dos anexos ao formulário ***
                    // Remover inputs antigos
                    const oldInputs = this.querySelectorAll('input[name="attachment_ids[]"]');
                    oldInputs.forEach(input => input.remove());
                    
                    // Adicionar um input para cada anexo
                    attachmentIds.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'attachment_ids[]';
                        input.value = id;
                        this.appendChild(input);
                    });
                    
                    // Obter a URL correta para o envio
                    const formActionUrl = `ticket_view.php?id=<?php echo $ticket_id; ?>`;
                    
                    // Criar FormData DEPOIS de adicionar os campos hidden
                    const formData = new FormData(this);
                    console.log("IDs de anexos sendo enviados:", attachmentIds);
                    
                    // Enviar via AJAX
                    fetch(formActionUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Erro do servidor: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Resposta';
                            submitBtn.disabled = false;
                        } else if (data.success) {
                            // Criar e adicionar a nova mensagem à conversa
                            const messagesContainer = document.querySelector('.conversation-container');
                            
                            // Preparar HTML para anexos, se houver
                            let attachmentsHtml = '';
                            if (data.attachments && data.attachments.length > 0) {
                                attachmentsHtml = '<div class="message-attachments">';
                                data.attachments.forEach(attachment => {
                                    if (attachment.is_image) {
                                        attachmentsHtml += `
                                            <div class="message-attachment">
                                                <img src="../../../uploads/ticket_media/${attachment.file_name}" 
                                                    alt="${attachment.original_name}" 
                                                    title="${attachment.original_name}">
                                                <span class="attachment-expiry">
                                                    <i class="fas fa-clock me-1"></i> Expira em: ${formatDateTime(attachment.expires_at || new Date(Date.now() + 24*60*60*1000))}
                                                </span>
                                            </div>`;
                                    } else if (attachment.is_video) {
                                        attachmentsHtml += `
                                            <div class="message-attachment">
                                                <video src="../../../uploads/ticket_media/${attachment.file_name}"
                                                    title="${attachment.original_name}"
                                                    preload="metadata"></video>
                                                <span class="attachment-expiry">
                                                    <i class="fas fa-clock me-1"></i> Expira em: ${formatDateTime(attachment.expires_at || new Date(Date.now() + 24*60*60*1000))}
                                                </span>
                                            </div>`;
                                    }
                                });
                                attachmentsHtml += '</div>';
                            }
                            
                            const newMessageHtml = `
                                <div class="message admin-message" style="opacity: 0" data-response-id="${data.id}">
                                    <div class="message-header">
                                        <span class="user-name">${data.admin_name} (Atendente)</span>
                                        <span class="message-date">${formatDateTime(data.created_at)}</span>
                                    </div>
                                    <div class="message-body">
                                        ${nl2br(data.message)}
                                        ${attachmentsHtml}
                                    </div>
                                </div>
                            `;
                            
                            messagesContainer.insertAdjacentHTML('beforeend', newMessageHtml);
                            
                            // Animar a nova mensagem
                            const newMessage = messagesContainer.lastElementChild;
                            setTimeout(() => {
                                newMessage.style.transition = 'opacity 0.5s ease';
                                newMessage.style.opacity = '1';
                            }, 10);
                            
                            // Limpar textarea e anexos
                            textarea.value = '';
                            clearAttachments(); // Limpar os anexos após envio
                            
                            // Restaurar botão
                            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Resposta';
                            submitBtn.disabled = false;
                            
                            // Rolar até a nova mensagem
                            newMessage.scrollIntoView({ behavior: 'smooth', block: 'end' });
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        alert('Ocorreu um erro ao enviar a resposta. Por favor, tente novamente.');
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Resposta';
                        submitBtn.disabled = false;
                    });
                });
            }

            // NOVO CÓDIGO: Verificação periódica de novas respostas do cliente
            const ticketId = <?php echo $ticket_id; ?>;
            let lastResponseId = findMaxResponseId();
            
            // Encontrar o maior ID de resposta já exibido
            function findMaxResponseId() {
                let maxId = 0;
                document.querySelectorAll('.message[data-response-id]').forEach(el => {
                    const id = parseInt(el.getAttribute('data-response-id'));
                    if (!isNaN(id) && id > maxId) {
                        maxId = id;
                    }
                });
                return maxId;
            }
            
            // Verificar novas respostas
            function checkNewResponses() {
                console.log('Verificando novas respostas para o ticket', ticketId, 'desde ID', lastResponseId);
                
                // Adicionar timestamp para evitar cache
                const url = `check_admin_responses.php?ticket_id=${ticketId}&last_id=${lastResponseId}&_=${new Date().getTime()}`;
                
                fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache, no-store, must-revalidate'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Erro do servidor: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Resposta da verificação:', data);
                    
                    // Se temos novas respostas
                    if (data.success && data.responses && data.responses.length > 0) {
                        // Atualizar o ID para a próxima verificação
                        if (data.max_id > lastResponseId) {
                            lastResponseId = data.max_id;
                        }
                        
                        // Adicionar as novas mensagens ao contêiner
                        const messagesContainer = document.querySelector('.conversation-container');
                        if (messagesContainer) {
                            data.responses.forEach(response => {
                                // Verificar se esta resposta já existe na página
                                const existingResponse = document.querySelector(`.message[data-response-id="${response.id}"]`);
                                if (existingResponse) {
                                    console.log(`Resposta #${response.id} já existe, ignorando`);
                                    return;
                                }
                                
                                console.log(`Adicionando nova resposta #${response.id}`);
                                
                                const newMessageHtml = `
                                    <div class="message user-message" data-response-id="${response.id}" style="opacity: 0">
                                        <div class="message-header">
                                            <span class="user-name">${response.user_name || 'Cliente'} (Cliente)</span>
                                            <span class="message-date">${formatDateTime(response.created_at)}</span>
                                        </div>
                                        <div class="message-body">
                                            ${nl2br(response.message)}
                                        </div>
                                    </div>
                                `;
                                
                                messagesContainer.insertAdjacentHTML('beforeend', newMessageHtml);
                                
                                // Animar a nova mensagem
                                const newMessage = messagesContainer.lastElementChild;
                                setTimeout(() => {
                                    newMessage.style.transition = 'opacity 0.5s ease';
                                    newMessage.style.opacity = '1';
                                }, 100);
                                
                                // Notificação sonora e visual
                                playNotificationSound();
                                showNotification('Nova resposta do cliente recebida!');
                                
                                // Scroll para a nova mensagem
                                setTimeout(() => {
                                    newMessage.scrollIntoView({ behavior: 'smooth', block: 'end' });
                                }, 200);
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro ao verificar respostas:', error);
                });
            }
            
            // Iniciar verificação periódica de novas respostas
            setInterval(checkNewResponses, 8000); // Verificar a cada 8 segundos
            
            // Funções auxiliares
            function formatDateTime(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('pt-BR') + ' ' + 
                       date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
            }
            
            function nl2br(str) {
                return (str || '').replace(/\n/g, '<br>').replace(/\r/g, '');
            }
            
            function showNotification(message) {
                // Criar elemento de notificação
                const notification = document.createElement('div');
                notification.className = 'alert alert-info alert-dismissible fade show notification-popup';
                notification.innerHTML = `
                    <strong><i class="fas fa-bell"></i> Notificação:</strong> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                // Adicionar estilo para notificação flutuante
                notification.style.position = 'fixed';
                notification.style.top = '20px';
                notification.style.right = '20px';
                notification.style.zIndex = '9999';
                notification.style.maxWidth = '350px';
                notification.style.boxShadow = '0 0 10px rgba(0,0,0,0.2)';
                
                // Adicionar ao corpo do documento
                document.body.appendChild(notification);
                
                // Remover após 5 segundos
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 5000);
            }
            
            // Substitua a função playNotificationSound existente por esta versão aprimorada
            function playNotificationSound() {
                try {
                    // Verificar se o usuário já interagiu com a página
                    const hasInteracted = sessionStorage.getItem('userInteracted') === 'true';
                    const audio = new Audio('../../../assets/sounds/notification.mp3');
                    audio.volume = 0.5;
                    
                    if (hasInteracted) {
                        // Tenta reproduzir o som se já houve interação
                        audio.play().catch(e => {
                            console.warn('Não foi possível reproduzir o som:', e);
                        });
                    } else {
                        // Se não houve interação, apenas mostra o indicador visual
                        console.warn('Som não reproduzido: aguardando interação do usuário');
                        
                        // Mostrar botão para ativar o som
                        if (!document.getElementById('enable-sound-btn')) {
                            const soundBtn = document.createElement('button');
                            soundBtn.id = 'enable-sound-btn';
                            soundBtn.className = 'btn btn-sm btn-outline-primary position-fixed';
                            soundBtn.innerHTML = '<i class="fas fa-volume-up"></i> Ativar notificações sonoras';
                            soundBtn.style.right = '20px';
                            soundBtn.style.bottom = '20px';
                            soundBtn.style.zIndex = '1000';
                            
                            soundBtn.addEventListener('click', function() {
                                // Marcar que o usuário interagiu
                                sessionStorage.setItem('userInteracted', 'true');
                                
                                // Reproduzir som de teste
                                const testAudio = new Audio('../../../assets/sounds/notification.mp3');
                                testAudio.volume = 0.5;
                                testAudio.play().then(() => {
                                    showNotification('Notificações sonoras ativadas!');
                                    this.remove();
                                }).catch(e => {
                                    console.warn('Erro ao ativar som:', e);
                                    showNotification('Não foi possível ativar notificações sonoras.');
                                });
                            });
                            
                            document.body.appendChild(soundBtn);
                        }
                    }
                } catch (e) {
                    console.error('Erro ao reproduzir som:', e);
                }
            }

            // Adicione este código para detectar interações do usuário
            document.addEventListener('click', function() {
                sessionStorage.setItem('userInteracted', 'true');
                
                // Remover o botão se existir
                const soundBtn = document.getElementById('enable-sound-btn');
                if (soundBtn) {
                    soundBtn.remove();
                }
            });

            document.addEventListener('keydown', function() {
                sessionStorage.setItem('userInteracted', 'true');
            });

            // Garantir que o diretório de sons exista
            function checkSoundFile() {
                fetch('../../../assets/sounds/notification.mp3', { method: 'HEAD' })
                .then(response => {
                    if (!response.ok) {
                        console.warn('Arquivo de som não encontrado. Criando diretório e arquivo...');
                        createSoundFile();
                    }
                })
                .catch(() => {
                    console.warn('Não foi possível verificar o arquivo de som. Tentando criar...');
                    createSoundFile();
                });
            }

            // Função para criar o arquivo de som se não existir
            function createSoundFile() {
                fetch('create_sound_file.php')
                .then(response => response.text())
                .then(result => {
                    console.log('Resultado da criação do arquivo de som:', result);
                })
                .catch(error => {
                    console.error('Erro ao criar arquivo de som:', error);
                });
            }

            // Verificar arquivo de som quando a página carregar
            checkSoundFile();

            // Upload de arquivos
            let attachmentIds = [];
            const attachmentsInput = document.getElementById('admin-attachments');
            const previewContainer = document.getElementById('attachmentsPreview');
            const clearButton = document.getElementById('clearAttachments');
            const maxFiles = 5;
            
            // Inicializar se os elementos existirem
            if (attachmentsInput && previewContainer) {
                
                // Ao selecionar arquivos
                attachmentsInput.addEventListener('change', function() {
                    // Limitar o número de arquivos
                    if (this.files.length > maxFiles) {
                        alert(`Você pode enviar no máximo ${maxFiles} arquivos por mensagem.`);
                        this.value = '';
                        return;
                    }
                    
                    // Verificar se já existem anexos demais
                    if (attachmentIds.length + this.files.length > maxFiles) {
                        alert(`Você pode enviar no máximo ${maxFiles} arquivos por mensagem. Já foram selecionados ${attachmentIds.length}.`);
                        this.value = '';
                        return;
                    }
                    
                    // Processar cada arquivo selecionado
                    Array.from(this.files).forEach(file => {
                        uploadFile(file);
                    });
                    
                    // Limpar o input após o processamento
                    this.value = '';
                });
                
                // Limpar anexos
                if (clearButton) {
                    clearButton.addEventListener('click', function() {
                        clearAttachments();
                    });
                }
                
                // Adicionar os IDs dos anexos ao formulário antes de enviar
                if (replyForm) {
                    replyForm.addEventListener('submit', function(e) {
                        // Remover inputs antigos
                        const oldInputs = this.querySelectorAll('input[name="attachment_ids[]"]');
                        oldInputs.forEach(input => input.remove());
                        
                        // Adicionar um input para cada anexo
                        attachmentIds.forEach(id => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'attachment_ids[]';
                            input.value = id;
                            this.appendChild(input);
                        });
                    });
                }
            }
            
            // Função corrigida para fazer upload de um arquivo
            function uploadFile(file) {
                console.log('Iniciando upload do arquivo:', file.name);
                
                // Criar container para o preview
                const previewItem = document.createElement('div');
                previewItem.className = 'attachment-preview uploading';
                
                // Barra de progresso
                const progressBar = document.createElement('div');
                progressBar.className = 'upload-progress';
                previewItem.appendChild(progressBar);
                
                // Adicionar ao container de preview
                previewContainer.appendChild(previewItem);
                
                // Criar FormData para o upload
                const formData = new FormData();
                formData.append('file', file);
                formData.append('ticket_id', <?php echo $ticket_id; ?>);
                
                // Fazer upload via AJAX
                const xhr = new XMLHttpRequest();
                
                // Monitorar progresso
                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBar.style.width = percentComplete + '%';
                    }
                };
                
                // Após o upload
                xhr.onload = function() {
                    console.log('Resposta do servidor:', xhr.status, xhr.responseText);
                    
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            
                            if (response.success) {
                                // Atualizar o preview
                                updateAttachmentPreview(previewItem, response);
                                
                                // Adicionar ID à lista
                                attachmentIds.push(response.id);
                            } else {
                                previewContainer.removeChild(previewItem);
                                alert('Erro: ' + (response.error || 'Falha no upload'));
                            }
                        } catch (e) {
                            console.error('Erro ao processar resposta:', e);
                            previewContainer.removeChild(previewItem);
                            alert('Erro ao processar resposta do servidor');
                        }
                    } else {
                        console.error('Erro no upload, status:', xhr.status);
                        previewContainer.removeChild(previewItem);
                        alert('Erro no servidor: ' + xhr.status);
                    }
                };
                
                // Em caso de erro
                xhr.onerror = function(e) {
                    console.error('Erro na requisição:', e);
                    previewContainer.removeChild(previewItem);
                    alert('Erro na conexão');
                };
                
                console.log('Enviando para:', 'process_admin_upload.php');
                
                // Abrir conexão e enviar
                xhr.open('POST', 'process_admin_upload.php', true);
                xhr.send(formData);
            }
            
            // Atualizar o preview após o upload
            function updateAttachmentPreview(previewItem, fileData) {
                previewItem.classList.remove('uploading');
                previewItem.dataset.attachmentId = fileData.id;
                
                // Limpar conteúdo existente
                previewItem.innerHTML = '';
                
                // Criar elemento apropriado baseado no tipo de arquivo
                if (fileData.is_image) {
                    const img = document.createElement('img');
                    img.src = fileData.url;
                    img.alt = fileData.original_name;
                    previewItem.appendChild(img);
                } else if (fileData.is_video) {
                    const video = document.createElement('video');
                    video.src = fileData.url;
                    video.setAttribute('muted', '');
                    previewItem.appendChild(video);
                } else {
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-file';
                    previewItem.appendChild(icon);
                }
                
                // Botão para remover
                const removeBtn = document.createElement('div');
                removeBtn.className = 'remove-attachment';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.addEventListener('click', function() {
                    removeAttachment(fileData.id);
                    previewContainer.removeChild(previewItem);
                });
                
                previewItem.appendChild(removeBtn);
            }
            
            // Remover um anexo
            function removeAttachment(id) {
                const index = attachmentIds.indexOf(id);
                if (index !== -1) {
                    attachmentIds.splice(index, 1);
                }
            }
            
            // Limpar todos os anexos
            function clearAttachments() {
                attachmentIds = [];
                previewContainer.innerHTML = '';
            }
            
            // Visualizador de mídia para cliques em imagens/vídeos
            document.addEventListener('click', function(e) {
                const attachment = e.target.closest('.message-attachment img, .message-attachment video');
                
                if (attachment) {
                    e.preventDefault();
                    
                    const isVideo = attachment.tagName.toLowerCase() === 'video';
                    
                    // Criar modal
                    const modal = document.createElement('div');
                    modal.className = 'modal fade media-viewer-modal';
                    modal.id = 'mediaViewerModal';
                    modal.setAttribute('tabindex', '-1');
                    modal.setAttribute('aria-hidden', 'true');
                    
                    let mediaHTML = '';
                    if (isVideo) {
                        mediaHTML = `<video src="${attachment.src}" controls autoplay></video>`;
                    } else {
                        mediaHTML = `<img src="${attachment.src}" alt="Visualização">`;
                    }
                    
                    modal.innerHTML = `
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <button type="button" class="close-btn" data-bs-dismiss="modal">
                                    <i class="fas fa-times"></i>
                                </button>
                                <div class="modal-body">
                                    ${mediaHTML}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.body.appendChild(modal);
                    
                    // Inicializar e abrir o modal
                    const bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
                    
                    // Remover modal do DOM quando fechado
                    modal.addEventListener('hidden.bs.modal', function() {
                        document.body.removeChild(modal);
                    });
                }
            });
        });
    </script>
</body>
</html>