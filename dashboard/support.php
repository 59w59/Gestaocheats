<?php
// filepath: c:\xampp\htdocs\Gestaocheats\dashboard\support.php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Debug para requisições AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    error_log('AJAX Request: ' . json_encode([
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'params' => $_GET,
        'post' => $_POST
    ]));
}

// Verificar se o usuário está logado
if (!is_logged_in()) {
    redirect('../pages/login.php');
}

// Obter dados do usuário
$user_id = $_SESSION['user_id'];
$auth = new Auth();
$user = $auth->get_user($user_id);

// Verificar se o usuário tem assinatura ativa
$stmt = $db->prepare("
    SELECT COUNT(*) FROM user_subscriptions 
    WHERE user_id = ? AND status = 'active' AND end_date > NOW()
");
$stmt->execute([$user_id]);
$has_active_subscription = (bool)$stmt->fetchColumn();

// Obter lista de tickets do usuário
try {
    $stmt = $db->prepare("
        SELECT st.*, 
               COUNT(tr.id) as response_count,
               MAX(tr.created_at) as last_response_at
        FROM support_tickets st
        LEFT JOIN ticket_responses tr ON st.id = tr.ticket_id
        WHERE st.user_id = ?
        GROUP BY st.id
        ORDER BY 
            CASE 
                WHEN st.status = 'open' THEN 1
                WHEN st.status = 'in_progress' THEN 2
                ELSE 3
            END,
            st.updated_at DESC
    ");
    $stmt->execute([$user_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching tickets: " . $e->getMessage());
    $tickets = [];
}

// Processar novo ticket
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $category = $_POST['category'] ?? 'technical';
    $priority = $_POST['priority'] ?? 'medium';

    // Validação básica
    if (empty($subject) || strlen($subject) < 5) {
        $error_message = 'Por favor, forneça um assunto com pelo menos 5 caracteres.';
    } elseif (empty($message) || strlen($message) < 20) {
        $error_message = 'Por favor, forneça uma descrição detalhada com pelo menos 20 caracteres.';
    } else {
        try {
            // Iniciar uma transação para garantir consistência
            $db->beginTransaction();

            // Gerar ticket ID único
            $ticket_id = 'TK-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

            // Inserir novo ticket
            $stmt = $db->prepare("
                INSERT INTO support_tickets
                (user_id, ticket_id, subject, message, category, priority, status)
                VALUES (?, ?, ?, ?, ?, ?, 'open')
            ");

            $stmt->execute([
                $user_id,
                $ticket_id,
                $subject,
                $message,
                $category,
                $priority
            ]);

            $new_ticket_id = $db->lastInsertId();

            // Confirmar a transação
            $db->commit();

            $success_message = 'Seu ticket foi criado com sucesso! Nossa equipe responderá em breve.';

            // Recarregar lista de tickets
            $stmt = $db->prepare("
                SELECT st.*, 
                       COUNT(tr.id) as response_count,
                       MAX(tr.created_at) as last_response_at
                FROM support_tickets st
                LEFT JOIN ticket_responses tr ON st.id = tr.ticket_id
                WHERE st.user_id = ?
                GROUP BY st.id
                ORDER BY 
                    CASE 
                        WHEN st.status = 'open' THEN 1
                        WHEN st.status = 'in_progress' THEN 2
                        ELSE 3
                    END,
                    st.updated_at DESC
            ");
            $stmt->execute([$user_id]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Redirecionar para o ticket recém-criado
            header("Location: support.php?id={$new_ticket_id}");
            exit;
        } catch (Exception $e) {
            // Reverter transação em caso de erro
            $db->rollBack();

            $error_message = 'Ocorreu um erro ao criar o ticket. Por favor, tente novamente.';
            error_log("Error creating support ticket: " . $e->getMessage());
        }
    }
}

// Processar resposta ao ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_ticket') {
    $ticket_id = (int)$_POST['ticket_id'];
    $reply_message = trim($_POST['reply_message'] ?? '');

    // Verificar se é uma requisição AJAX
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Validação básica
    if (empty($reply_message) || strlen($reply_message) < 5) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Por favor, forneça uma resposta com pelo menos 5 caracteres.']);
            exit;
        } else {
            $error_message = 'Por favor, forneça uma resposta com pelo menos 5 caracteres.';
        }
    } else {
        try {
            // Iniciar transação
            $db->beginTransaction();

            // Verificar se o ticket pertence ao usuário
            $stmt = $db->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
            $stmt->execute([$ticket_id, $user_id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception('Ticket não encontrado ou não pertence a este usuário.');
            }

            // Inserir resposta
            $stmt = $db->prepare("
                INSERT INTO ticket_responses
                (ticket_id, user_id, message)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $ticket_id,
                $user_id,
                $reply_message
            ]);

            $response_id = $db->lastInsertId();
            
            // Processar anexos
            $attachment_ids = [];
            if (isset($_POST['attachment_ids']) && is_array($_POST['attachment_ids'])) {
                foreach ($_POST['attachment_ids'] as $attachment_id) {
                    // Associar anexo à resposta
                    $stmt = $db->prepare("
                        UPDATE ticket_attachments 
                        SET response_id = ? 
                        WHERE id = ? AND ticket_id = ?
                    ");
                    $stmt->execute([$response_id, $attachment_id, $ticket_id]);
                    if ($stmt->rowCount() > 0) {
                        $attachment_ids[] = $attachment_id;
                    }
                }
            }

            // Atualizar status do ticket para "open" se estiver fechado
            $stmt = $db->prepare("
                UPDATE support_tickets 
                SET status = 'open', updated_at = NOW() 
                WHERE id = ? AND status = 'closed'
            ");
            $stmt->execute([$ticket_id]);

            // Confirmar transação
            $db->commit();

            // Buscar anexos para resposta AJAX
            $attachments = [];
            if (!empty($attachment_ids)) {
                $placeholders = implode(',', array_fill(0, count($attachment_ids), '?'));
                $stmt = $db->prepare("
                    SELECT id, file_name, original_name, file_type, is_image, is_video
                    FROM ticket_attachments
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute($attachment_ids);
                $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if ($is_ajax) {
                // Retornar os dados da nova resposta em JSON
                $response_data = [
                    'id' => $response_id,
                    'ticket_id' => $ticket_id,
                    'user_id' => $user_id,
                    'message' => $reply_message,
                    'attachments' => $attachments,
                    'created_at' => date('Y-m-d H:i:s'),
                    'success' => true
                ];

                header('Content-Type: application/json');
                echo json_encode($response_data);
                exit;
            } else {
                $success_message = 'Sua resposta foi enviada com sucesso!';

                // Redirecionar de volta para o ticket com um parâmetro de sucesso
                header("Location: support.php?id={$ticket_id}&success=reply");
                exit;
            }
        } catch (Exception $e) {
            // Reverter transação em caso de erro
            $db->rollBack();

            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Ocorreu um erro ao enviar a resposta: ' . $e->getMessage()]);
                exit;
            } else {
                $error_message = 'Ocorreu um erro ao enviar a resposta. Por favor, tente novamente.';
                error_log("Error replying to support ticket: " . $e->getMessage());
            }
        }
    }
}

// Para visualização de um ticket específico
$active_ticket = null;
$ticket_responses = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $ticket_id = (int)$_GET['id'];

    // Verificar se o ticket pertence ao usuário
    $stmt = $db->prepare("SELECT * FROM support_tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$ticket_id, $user_id]);
    $active_ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($active_ticket) {
        // Obter todas as respostas para este ticket
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
        $ticket_responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Verificar se há uma mensagem de sucesso na URL
        if (isset($_GET['success']) && $_GET['success'] === 'reply') {
            $success_message = 'Sua resposta foi enviada com sucesso!';
        }
    }
}

// Helper functions
function get_priority_badge($priority) {
    switch ($priority) {
        case 'high':
            return '<span class="badge bg-danger">Alta</span>';
        case 'medium':
            return '<span class="badge bg-warning text-dark">Média</span>';
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
            return '<span class="badge bg-warning text-dark">Pagamento</span>';
        case 'account':
            return '<span class="badge bg-primary">Conta</span>';
        case 'other':
            return '<span class="badge bg-secondary">Outro</span>';
        default:
            return '<span class="badge bg-secondary">Desconhecido</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suporte - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon/favicon-16x16.png">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Toastify -->
    <link href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" rel="stylesheet">
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/support.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <link rel="stylesheet" href="../assets/css/scroll.css">

    <style>
        /* Estilos para notificações */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            background-color: #fff;
            border-left: 4px solid #3498db;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.16);
            border-radius: 4px;
            max-width: 350px;
            z-index: 9999;
            transform: translateX(120%);
            transition: transform 0.3s ease;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification-content {
            display: flex;
            align-items: center;
        }

        .notification i {
            margin-right: 10px;
            font-size: 18px;
        }

        .notification-success {
            border-left-color: #2ecc71;
        }

        .notification-error {
            border-left-color: #e74c3c;
        }

        .notification-success i {
            color: #2ecc71;
        }

        .notification-error i {
            color: #e74c3c;
        }

        /* Melhorar a aparência das mensagens */
        .message-container {
            max-height: 500px;
            overflow-y: auto;
            scroll-behavior: smooth;
            padding: 15px;
        }

        .message {
            margin-bottom: 15px;
            padding: 12px;
            border-radius: 8px;
            position: relative;
            transition: opacity 0.5s, transform 0.5s;
        }

        .user-message {
            background-color: #e3f2fd;
            margin-left: 20px;
        }

        .admin-message {
            background-color: #f1f8e9;
            margin-right: 20px;
        }

        .message p {
            margin-bottom: 8px;
        }

        .message-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #666;
        }

        .message-attachments {
            margin-top: 10px;
        }

        .message-attachment {
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
            position: relative;
        }

        .message-attachment img,
        .message-attachment video {
            max-width: 100px;
            max-height: 100px;
            border-radius: 4px;
            cursor: pointer;
        }

        .message-attachment .attachment-expiry {
            display: block;
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>

<body class="dashboard-page">
    <!-- Loading Spinner -->
    <div class="loading">
        <div class="loading-logo"><?php echo SITE_NAME; ?></div>
        <div class="loading-spinner"></div>
    </div>

    <!-- Header -->
    <header class="dashboard-header">
        <div class="container">
            <div class="logo">
                <a href="../index.php"><?php echo SITE_NAME; ?></a>
            </div>
            <nav class="dashboard-nav">
                <ul>
                    <li><a href="purchases.php">Comprar Planos</a></li>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="downloads.php">Meus Downloads</a></li>
                    <li><a href="support.php" class="active">Suporte</a></li>
                    <li><a href="profile.php">Perfil</a></li>
                </ul>
            </nav>
            <div class="user-menu">
                <div class="user-info">
                    <span><?php echo $user['username']; ?></span>
                    <img src="../assets/images/avatar.png" alt="Avatar">
                </div>

                <div class="user-dropdown">
                    <header>
                        <h4><?php echo $user['first_name'] ? $user['first_name'] . ' ' . $user['last_name'] : $user['username']; ?></h4>
                        <p><?php echo $user['email']; ?></p>
                    </header>
                    <ul>
                        <li><a href="profile.php"><i class="fas fa-user"></i> Meu Perfil</a></li>
                        <li><a href="settings.php"><i class="fas fa-cog"></i> Configurações</a></li>
                        <li><a href="../includes/logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="dashboard-main">
        <div class="container">
            <div class="page-header">
                <h1>Suporte</h1>
                <p>Entre em contato com nossa equipe para resolver qualquer dúvida ou problema</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($has_active_subscription): ?>
                <!-- Support Stats -->
                <div class="support-stats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($tickets); ?></h3>
                            <p>Total de Tickets</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <?php
                            $open_tickets = 0;
                            foreach ($tickets as $ticket) {
                                if ($ticket['status'] == 'open' || $ticket['status'] == 'in_progress') {
                                    $open_tickets++;
                                }
                            }
                            ?>
                            <h3><?php echo $open_tickets; ?></h3>
                            <p>Tickets Abertos</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($tickets) - $open_tickets; ?></h3>
                            <p>Tickets Resolvidos</p>
                        </div>
                    </div>
                </div>

                <?php if ($active_ticket): ?>
                    <!-- Ticket Detail View -->
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="mb-4">
                                <a href="support.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-arrow-left"></i> Voltar para a Lista
                                </a>
                            </div>

                            <div class="support-info">
                                <div class="support-info-card w-100">
                                    <h3><i class="fas fa-info-circle"></i> Informações Úteis</h3>
                                    <ul>
                                        <li><i class="fas fa-clock"></i> Horário de atendimento: Seg-Sex 9h às 18h</li>
                                        <li><i class="fas fa-envelope"></i> E-mail: suporte@gestaocheats.com</li>
                                        <li><i class="fab fa-discord"></i> Discord: discord.gg/gestaocheats</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="ticket-detail">
                                <div class="ticket-header">
                                    <h2><?php echo htmlspecialchars($active_ticket['subject'] ?? ''); ?></h2>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php echo get_status_badge($active_ticket['status']); ?>
                                            <?php echo get_category_badge($active_ticket['category']); ?>
                                            <?php echo get_priority_badge($active_ticket['priority']); ?>
                                        </div>
                                        <div>
                                            <small class="text-muted">Criado em <?php echo date('d/m/Y H:i', strtotime($active_ticket['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="ticket-body">
                                    <div class="message-container">
                                        <!-- Original Message -->
                                        <div class="message user-message" data-message-type="original">
                                            <p><?php echo nl2br(htmlspecialchars($active_ticket['message'])); ?></p>
                                            <div class="message-meta">
                                                <span class="message-author"><?php echo htmlspecialchars($user['username']); ?></span>
                                                <span class="message-date"><?php echo date('d/m/Y H:i', strtotime($active_ticket['created_at'])); ?></span>
                                            </div>
                                        </div>

                                        <!-- Responses -->
                                        <?php foreach ($ticket_responses as $response): ?>
                                            <?php
                                            $is_admin = !empty($response['admin_id']);
                                            $author_name = $is_admin ? ($response['admin_name'] ?? 'Suporte') : 'Você';
                                            ?>
                                            <!-- Template para exibição de mensagem com anexos -->
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
                                                                        <img src="../uploads/ticket_media/<?php echo htmlspecialchars($attachment['file_name']); ?>" 
                                                                            alt="<?php echo htmlspecialchars($attachment['original_name']); ?>"
                                                                            title="<?php echo htmlspecialchars($attachment['original_name']); ?>">
                                                                    <?php elseif ($attachment['is_video']): ?>
                                                                        <video src="../uploads/ticket_media/<?php echo htmlspecialchars($attachment['file_name']); ?>"
                                                                            title="<?php echo htmlspecialchars($attachment['original_name']); ?>"
                                                                            preload="metadata"></video>
                                                                    <?php else: ?>
                                                                        <a href="../uploads/ticket_media/<?php echo htmlspecialchars($attachment['file_name']); ?>" 
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

                                    <?php if ($active_ticket['status'] !== 'closed'): ?>
                                        <!-- Reply Form -->
                                        <div class="reply-form">
                                            <h4>Responder</h4>
                                            <form action="support.php" method="POST" id="replyTicketForm" enctype="multipart/form-data">
                                                <input type="hidden" name="action" value="reply_ticket">
                                                <input type="hidden" name="ticket_id" value="<?php echo $active_ticket['id']; ?>">
                                                
                                                <div class="form-group mb-3">
                                                    <label for="reply_message">Sua resposta:</label>
                                                    <textarea name="reply_message" id="reply_message" class="form-control" rows="5" placeholder="Digite sua resposta aqui..." required></textarea>
                                                </div>
                                                
                                                <!-- Área de upload de arquivos -->
                                                <div class="form-group mb-3">
                                                    <label for="attachments">Anexos (opcional):</label>
                                                    <div class="media-upload-container">
                                                        <div class="input-group">
                                                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple accept="image/*,video/*">
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
                                                
                                                <div class="form-group">
                                                    <button type="submit" class="btn btn-primary" id="submitReply">
                                                        <i class="fas fa-paper-plane"></i> Enviar Resposta
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            Este ticket está fechado. Se você precisar de mais ajuda, crie um novo ticket.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Ticket List View -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-4 d-flex justify-content-between align-items-center">
                                <h3 class="section-title">Meus Tickets</h3>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newTicketModal">
                                    <i class="fas fa-plus"></i> Novo Ticket
                                </button>
                            </div>

                            <div class="ticket-list">
                                <?php if (empty($tickets)): ?>
                                    <div class="no-tickets">
                                        <i class="fas fa-ticket-alt"></i>
                                        <h3>Nenhum ticket encontrado</h3>
                                        <p>Você ainda não criou nenhum ticket de suporte. Se precisar de ajuda, clique no botão abaixo para criar seu primeiro ticket.</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newTicketModal">
                                            <i class="fas fa-plus"></i> Criar Novo Ticket
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <?php
                                        $has_new_responses = false;
                                        if (!empty($ticket['last_response_at'])) {
                                            $has_new_responses = strtotime($ticket['last_response_at']) > strtotime($ticket['updated_at']);
                                        }
                                        ?>
                                        <div class="ticket-list-item <?php echo $has_new_responses ? 'unread' : ''; ?>">
                                            <div class="ticket-info">
                                                <div class="ticket-title">
                                                    <span><?php echo htmlspecialchars($ticket['subject'] ?? ''); ?></span>
                                                    <?php echo get_status_badge($ticket['status']); ?>
                                                    <?php if ($has_new_responses): ?>
                                                        <span class="badge bg-danger">Nova Resposta</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ticket-meta">
                                                    <span><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></span>
                                                    <span><i class="fas fa-tag"></i> <?php echo ucfirst($ticket['category']); ?></span>
                                                    <span><i class="fas fa-comments"></i> <?php echo $ticket['response_count']; ?> respostas</span>
                                                </div>
                                            </div>
                                            <div class="ticket-actions">
                                                <a href="support.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> Ver
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="support-info">
                                <div class="support-info-card">
                                    <h3><i class="fas fa-headset"></i> Suporte</h3>
                                    <p>Nossa equipe está disponível para ajudar com quaisquer dúvidas ou problemas que você possa encontrar.</p>
                                    <ul>
                                        <li><i class="fas fa-clock"></i> Horário de atendimento: Seg-Sex 12:30h às 23h</li>
                                        <li><i class="fas fa-envelope"></i> E-mail: suporte@gestaocheats.com</li>
                                        <li><i class="fab fa-discord"></i> Discord: discord.gg/gestaocheats</li>
                                    </ul>
                                </div>

                                <div class="support-info-card">
                                    <h3><i class="fas fa-book"></i> Guias</h3>
                                    <p>Confira nossos guias e tutoriais para ajudar a resolver problemas comuns:</p>
                                    <ul>
                                        <li><i class="fas fa-file-alt"></i> <a href="tutorials.php?type=installation" class="text-primary">Como instalar os cheats</a></li>
                                        <li><i class="fas fa-file-alt"></i> <a href="tutorials.php?type=troubleshooting" class="text-primary">Solução de problemas comuns</a></li>
                                        <li><i class="fas fa-file-alt"></i> <a href="../pages/faq.php" class="text-primary">Perguntas frequentes</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Sem assinatura ativa -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <h4 class="alert-heading">Você não possui uma assinatura ativa!</h4>
                        <p>Para acessar o suporte premium e obter ajuda personalizada, é necessário ter um plano de assinatura ativo.</p>
                        <a href="purchases.php" class="btn btn-primary mt-2">Ver Planos Disponíveis</a>
                    </div>
                </div>

                <div class="support-info">
                    <div class="support-info-card">
                        <h3><i class="fas fa-envelope"></i> Contato</h3>
                        <p>Se você precisar de ajuda antes de assinar, entre em contato conosco:</p>
                        <ul>
                            <li><i class="fas fa-envelope"></i> E-mail: contato@gestaocheats.com</li>
                            <li><i class="fab fa-discord"></i> Discord público: discord.gg/gestaocheatspublic</li>
                        </ul>
                    </div>

                    <div class="support-info-card">
                        <h3><i class="fas fa-question-circle"></i> FAQ</h3>
                        <p>Confira nossas perguntas frequentes para encontrar respostas para dúvidas comuns:</p>
                        <ul>
                            <li><i class="fas fa-link"></i> <a href="../pages/faq.php" class="text-primary">Acessar FAQ</a></li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="dashboard-footer">
        <div class="container">
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos os direitos reservados.
            </div>
        </div>
    </footer>

    <!-- Modal para Novo Ticket -->
    <div class="modal fade" id="newTicketModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Criar Novo Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <form action="support.php" method="POST" id="ticketForm" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="create_ticket">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="category" class="form-label">Categoria</label>
                                    <select name="category" id="category" class="form-select" required>
                                        <option value="technical">Suporte Técnico</option>
                                        <option value="billing">Pagamento/Faturamento</option>
                                        <option value="account">Conta</option>
                                        <option value="other">Outro</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="priority" class="form-label">Prioridade</label>
                                    <select name="priority" id="priority" class="form-select" required>
                                        <option value="low">Baixa</option>
                                        <option value="medium" selected>Média</option>
                                        <option value="high">Alta</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="subject" class="form-label">Assunto</label>
                            <input type="text" name="subject" id="subject" class="form-control" placeholder="Descreva brevemente seu problema" required minlength="5">
                            <div class="invalid-feedback">
                                O assunto deve ter pelo menos 5 caracteres.
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="message" class="form-label">Descrição</label>
                            <textarea name="message" id="message" class="form-control" rows="7" placeholder="Forneça detalhes sobre seu problema, incluindo mensagens de erro e passos para reproduzir o problema..." required minlength="20"></textarea>
                            <div class="invalid-feedback">
                                A descrição deve ter pelo menos 20 caracteres.
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> Forneça o máximo de informações possível para que possamos ajudar mais rapidamente.
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="submitTicketBtn">
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" id="submitSpinner"></span>
                                <span id="submitText">Enviar Ticket</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variáveis globais
            const activeTicketId = <?php echo $active_ticket ? $active_ticket['id'] : 'null'; ?>;
            let lastResponseId = 0;
            let polling = null;

            // Encontrar o maior ID de resposta já exibido na página
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

            // Inicializar com o maior ID já na página
            lastResponseId = findMaxResponseId();
            console.log('ID inicial para verificação:', lastResponseId);

            // Funções auxiliares
            function formatDateTime(dateString) {
                try {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('pt-BR') + ' ' +
                        date.toLocaleTimeString('pt-BR', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                } catch (e) {
                    console.error('Erro ao formatar data:', e);
                    return dateString;
                }
            }

            function nl2br(str) {
                if (!str) return '';
                return String(str).replace(/\n/g, '<br>').replace(/\r/g, '');
            }

            // Notificações
            window.showNotification = function(message, type = "info") {
                // Criar elemento de notificação personalizado
                const notif = document.createElement('div');
                notif.className = `notification notification-${type}`;
                notif.innerHTML = `
                    <div class="notification-content">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : 
                                      type === 'error' ? 'fa-exclamation-circle' : 
                                      'fa-info-circle'}"></i>
                        <span>${message}</span>
                    </div>
                `;

                document.body.appendChild(notif);

                // Animar entrada
                setTimeout(() => {
                    notif.classList.add('show');
                }, 10);

                // Remover após 5 segundos
                setTimeout(() => {
                    notif.classList.remove('show');
                    setTimeout(() => {
                        notif.remove();
                    }, 300);
                }, 5000);
            };

            // Reproduzir som de notificação
            function playNotificationSound() {
                try {
                    const audio = new Audio('../assets/sounds/notification.mp3');
                    audio.volume = 0.3;
                    audio.play().catch(e => {
                        console.warn('Não foi possível reproduzir o som:', e);
                    });
                } catch (e) {
                    console.error('Erro ao reproduzir som:', e);
                }
            }

            // URL do endpoint de verificação de novas respostas
            function checkNewResponses() {
                if (!activeTicketId) return;

                console.log('Verificando novas respostas para ticket:', activeTicketId, 'desde ID:', lastResponseId);
                
                // Adicionar timestamp para evitar cache
                const timestamp = new Date().getTime();
                const url = `check_new_responses.php?ticket_id=${activeTicketId}&last_id=${lastResponseId}&_=${timestamp}`;
                
                // Log para debug
                console.log('URL de verificação:', url);

                fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache, no-store, must-revalidate'
                    }
                })
                .then(response => {
                    console.log('Status da resposta:', response.status);
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

                        // Mostrar notificação apenas se houver novas mensagens
                        showNotification(`${data.responses.length} nova(s) resposta(s) recebida(s)!`, "success");
                        playNotificationSound();

                        // Adicionar as novas mensagens ao contêiner
                        const messagesContainer = document.querySelector('.message-container');
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
                                    <div class="message admin-message" data-response-id="${response.id}" style="opacity: 0; transform: translateY(20px);">
                                        <p>${nl2br(response.message)}</p>
                                        <div class="message-meta">
                                            <span class="message-author">${response.admin_name || 'Suporte'} (Atendente)</span>
                                            <span class="message-date">${formatDateTime(response.created_at)}</span>
                                        </div>
                                    </div>
                                `;

                                messagesContainer.insertAdjacentHTML('beforeend', newMessageHtml);

                                // Animar a nova mensagem
                                const newMessage = messagesContainer.lastElementChild;
                                setTimeout(() => {
                                    newMessage.style.transition = 'all 0.5s ease';
                                    newMessage.style.opacity = '1';
                                    newMessage.style.transform = 'translateY(0)';
                                }, 100);

                                // Scroll para a nova mensagem
                                setTimeout(() => {
                                    newMessage.scrollIntoView({
                                        behavior: 'smooth',
                                        block: 'end'
                                    });
                                }, 200);
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro ao verificar respostas:', error);
                });
            }

            // Inicializar verificação periódica de novas respostas
            if (activeTicketId) {
                // Primeira verificação após 2 segundos
                setTimeout(checkNewResponses, 2000);

                // Verificações periódicas a cada 5 segundos
                polling = setInterval(checkNewResponses, 5000);

                // Adicionar data-response-id a todas as mensagens existentes
                document.querySelectorAll('.message').forEach((msg, index) => {
                    if (!msg.hasAttribute('data-response-id') && !msg.hasAttribute('data-message-type')) {
                        // Usar um ID temporário gerado para mensagens que não têm um ID específico
                        msg.setAttribute('data-response-id', `existing-${index}`);
                    }
                });
            }

            // Substitua o código do envio do formulário com esta versão corrigida

            const replyForm = document.getElementById('replyTicketForm');
            if (replyForm) {
                replyForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const submitBtn = document.getElementById('submitReply');
                    const textarea = this.querySelector('textarea');

                    if (textarea.value.trim().length < 5) {
                        showNotification("Por favor, digite uma mensagem com pelo menos 5 caracteres", "error");
                        return;
                    }

                    // IMPORTANTE: Primeiro adicionar os IDs dos anexos ao formulário
                    // Remover inputs antigos de anexos
                    const oldInputs = this.querySelectorAll('input[name="attachment_ids[]"]');
                    oldInputs.forEach(input => input.remove());
                    
                    // Adicionar um input hidden para cada anexo
                    console.log("Anexando IDs de anexos:", attachmentIds);
                    attachmentIds.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'attachment_ids[]';
                        input.value = id;
                        this.appendChild(input);
                        console.log(`Input adicionado: attachment_ids[] = ${id}`);
                    });

                    submitBtn.disabled = true;
                    const originalBtnText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';

                    // Usar a URL atual do formulário
                    const formAction = this.getAttribute('action');
                    console.log('Enviando formulário para:', formAction);
                    
                    // Criar FormData APÓS adicionar os campos hidden
                    const formData = new FormData(this);
                    
                    // Log para debug
                    console.log("Campos do formulário sendo enviados:");
                    for (let [key, value] of formData.entries()) {
                        console.log(`${key}: ${value}`);
                    }
                    
                    fetch(formAction, {
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
                        console.log('Resposta do servidor:', data);
                        
                        if (data.error) {
                            showNotification(data.error, "error");
                        } else if (data.success) {
                            // Preparar HTML para anexos, se houver
                            let attachmentsHtml = '';
                            if (data.attachments && data.attachments.length > 0) {
                                attachmentsHtml = '<div class="message-attachments">';
                                
                                data.attachments.forEach(attachment => {
                                    if (attachment.is_image) {
                                        attachmentsHtml += `
                                            <div class="message-attachment">
                                                <img src="../uploads/ticket_media/${attachment.file_name}" 
                                                    alt="${attachment.original_name}" 
                                                    title="${attachment.original_name}">
                                                <span class="attachment-expiry">
                                                    <i class="fas fa-clock me-1"></i> Expira em: ${formatDateTime(attachment.expires_at)}
                                                </span>
                                            </div>`;
                                    } else if (attachment.is_video) {
                                        attachmentsHtml += `
                                            <div class="message-attachment">
                                                <video src="../uploads/ticket_media/${attachment.file_name}"
                                                    title="${attachment.original_name}"
                                                    preload="metadata"></video>
                                                <span class="attachment-expiry">
                                                    <i class="fas fa-clock me-1"></i> Expira em: ${formatDateTime(attachment.expires_at)}
                                                </span>
                                            </div>`;
                                    }
                                });
                                
                                attachmentsHtml += '</div>';
                            }
                            
                            // Adicionar a nova mensagem ao contêiner
                            const messagesContainer = document.querySelector('.message-container');
                            
                            // Só adicionar se o contêiner existir
                            if (messagesContainer) {
                                const newMessageHtml = `
                                    <div class="message user-message" data-response-id="${data.id}" style="opacity: 0; transform: translateY(20px);">
                                        <div class="message-body">
                                            ${nl2br(data.message)}
                                            ${attachmentsHtml}
                                        </div>
                                        <div class="message-meta">
                                            <span class="message-author">Você</span>
                                            <span class="message-date">${formatDateTime(data.created_at)}</span>
                                        </div>
                                    </div>
                                `;

                                messagesContainer.insertAdjacentHTML('beforeend', newMessageHtml);

                                // Animar a entrada da nova mensagem
                                const newMessage = messagesContainer.lastElementChild;
                                setTimeout(() => {
                                    newMessage.style.transition = 'all 0.5s ease';
                                    newMessage.style.opacity = '1';
                                    newMessage.style.transform = 'translateY(0)';
                                }, 100);

                                // Limpar o textarea e anexos
                                textarea.value = '';
                                clearAttachments(); // Importante: limpar os anexos após envio

                                // Notificar sucesso
                                showNotification('Sua resposta foi enviada com sucesso!', 'success');

                                // Scroll para a nova mensagem
                                setTimeout(() => {
                                    newMessage.scrollIntoView({ behavior: 'smooth', block: 'end' });
                                }, 200);

                                // Atualizar o ID da última resposta
                                if (data.id) {
                                    lastResponseId = Math.max(lastResponseId, parseInt(data.id) || 0);
                                }
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Erro no envio:', error);
                        showNotification('Erro ao enviar resposta: ' + error.message, 'error');
                    })
                    .finally(() => {
                        // Restaurar o botão
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    });
                });
            }

            // Função para testar a conexão AJAX
            window.testAjaxConnection = function() {
                const testURL = 'check_connection.php?action=test&_=' + new Date().getTime();

                fetch(testURL, {
                    method: 'GET', 
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache, no-store, must-revalidate'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Erro na resposta do servidor: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    showNotification(`${data.message || 'Conexão AJAX funcionando!'} (${data.timestamp})`, "success");
                    console.log('Teste de conexão bem-sucedido:', data);

                    if (activeTicketId) {
                        // Forçar uma verificação imediata após teste bem-sucedido
                        setTimeout(checkNewResponses, 500);
                    }
                })
                .catch(error => {
                    showNotification(`Erro no teste AJAX: ${error.message}`, "error");
                    console.error('Erro no teste AJAX:', error);
                });
            };

            // Validação de formulário Bootstrap
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });

            // Esconder o spinner de carregamento quando a página estiver pronta
            setTimeout(() => {
                const loadingElement = document.querySelector('.loading');
                if (loadingElement) {
                    loadingElement.style.opacity = '0';
                    setTimeout(() => {
                        loadingElement.style.display = 'none';
                    }, 500);
                }
            }, 800);
            
            // Menu de usuário
            const userInfo = document.querySelector('.user-info');
            const userDropdown = document.querySelector('.user-dropdown');
            if (userInfo && userDropdown) {
                userInfo.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                });
                
                document.addEventListener('click', function(e) {
                    if (!userInfo.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.classList.remove('active');
                    }
                });
            }
        });

    let attachmentIds = [];
    
    document.addEventListener('DOMContentLoaded', function() {
        // Variáveis globais
        const activeTicketId = <?php echo $active_ticket ? $active_ticket['id'] : 'null'; ?>;
        let lastResponseId = 0;
        let polling = null;
        
        // Referências aos elementos DOM relacionados a uploads
        const attachmentsInput = document.getElementById('attachments');
        const previewContainer = document.getElementById('attachmentsPreview');
        const clearButton = document.getElementById('clearAttachments');
        const replyForm = document.getElementById('replyTicketForm');
        const maxFiles = 5;

            
            // Inicializar se os elementos existirem
            if (attachmentsInput && previewContainer) {
                
                // Ao selecionar arquivos
                attachmentsInput.addEventListener('change', function() {
                    // Limitar o número de arquivos
                    if (this.files.length > maxFiles) {
                        showNotification(`Você pode enviar no máximo ${maxFiles} arquivos por mensagem.`, 'warning');
                        this.value = '';
                        return;
                    }
                    
                    // Verificar se já existem anexos demais
                    if (attachmentIds.length + this.files.length > maxFiles) {
                        showNotification(`Você pode enviar no máximo ${maxFiles} arquivos por mensagem. Já foram selecionados ${attachmentIds.length}.`, 'warning');
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
            
            // Função para fazer upload de um arquivo
            function uploadFile(file) {
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
                formData.append('ticket_id', document.querySelector('input[name="ticket_id"]').value);
                
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
                                showNotification('Erro: ' + (response.error || 'Falha no upload'), 'error');
                            }
                        } catch (e) {
                            previewContainer.removeChild(previewItem);
                            showNotification('Erro ao processar resposta do servidor', 'error');
                        }
                    } else {
                        previewContainer.removeChild(previewItem);
                        showNotification('Erro no servidor: ' + xhr.status, 'error');
                    }
                };
                
                // Em caso de erro
                xhr.onerror = function() {
                    previewContainer.removeChild(previewItem);
                    showNotification('Erro na conexão', 'error');
                };
                
                // Abrir conexão e enviar
                xhr.open('POST', 'process_ticket_upload.php', true);
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
                console.log("Limpando todos os anexos. Antes:", attachmentIds);
                attachmentIds = [];
                if (previewContainer) {
                    previewContainer.innerHTML = '';
                }
                console.log("Após limpar:", attachmentIds);
            }
            
            // Criar visualizador de mídia
            document.addEventListener('click', function(e) {
                const attachment = e.target.closest('.message-attachment');
                
                if (attachment) {
                    e.preventDefault();
                    
                    // Obter elemento mídia clicado
                    const media = attachment.querySelector('img, video');
                    
                    if (media) {
                        const isVideo = media.tagName.toLowerCase() === 'video';
                        
                        // Criar e abrir modal
                        const modal = document.createElement('div');
                        modal.className = 'modal fade media-viewer-modal';
                        modal.id = 'mediaViewerModal';
                        modal.setAttribute('tabindex', '-1');
                        modal.setAttribute('aria-hidden', 'true');
                        
                        let mediaHTML = '';
                        if (isVideo) {
                            mediaHTML = `<video src="${media.src}" controls autoplay></video>`;
                        } else {
                            mediaHTML = `<img src="${media.src}" alt="Visualização">`;
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
                }
            });
        });
    </script>
</body>
</html>