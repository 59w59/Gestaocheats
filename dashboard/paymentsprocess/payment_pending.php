<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php'; // Composer autoload

// SDK do MercadoPago
MercadoPago\SDK::setAccessToken(MP_ACCESS_TOKEN);

// Verificar se o usuário está logado
if (!is_logged_in()) {
    redirect('../../pages/login.php');
}

// Obter ID do pagamento
$payment_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$payment_id) {
    if (!isset($_SESSION['pix_code']) || !isset($_SESSION['transaction_id'])) {
        redirect('../purchases.php');
    }
}

// Obter dados do usuário
$user_id = $_SESSION['user_id'];
$auth = new Auth();
$user = $auth->get_user($user_id);

// Verificar dados da sessão ou buscar do banco
if (isset($_SESSION['pix_code']) && isset($_SESSION['qr_code']) && isset($_SESSION['payment_id'])) {
    $pix_code = $_SESSION['pix_code'];
    $qr_code = $_SESSION['qr_code'];
    $transaction_id = $_SESSION['transaction_id'];
    $payment_id = $_SESSION['payment_id'];
    
    // Limpar dados da sessão
    unset($_SESSION['pix_code']);
    unset($_SESSION['qr_code']);
    unset($_SESSION['transaction_id']);
    unset($_SESSION['payment_id']);
} else {
    // Buscar detalhes do pagamento
    try {
        $stmt = $db->prepare("
            SELECT p.*, s.end_date, s.start_date, gateway_response,
                   csp.name as plan_name, c.name as cheat_name  
            FROM payments p
            JOIN user_subscriptions s ON p.subscription_id = s.id
            JOIN cheat_subscription_plans csp ON s.cheat_plan_id = csp.id
            JOIN cheats c ON csp.cheat_id = c.id
            WHERE p.id = ? AND p.user_id = ? AND p.payment_method = 'pix'
        ");
        $stmt->execute([$payment_id, $user_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$payment) {
            throw new Exception('Pagamento PIX não encontrado');
        }
        
        // Obter dados do PIX do gateway_response
        $gateway_data = json_decode($payment['gateway_response'], true);
        if (isset($gateway_data['qr_code']) && isset($gateway_data['qr_code_base64'])) {
            $pix_code = $gateway_data['qr_code'];
            $qr_code = 'data:image/png;base64,' . $gateway_data['qr_code_base64'];
            $transaction_id = $payment['transaction_id'];
        } else {
            throw new Exception('Dados do PIX não encontrados');
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao buscar informações do pagamento: ' . $e->getMessage();
        redirect('../purchases.php');
    }
}

// Obter ou buscar detalhes completos do pagamento para exibição
if (!isset($payment)) {
    try {
        $stmt = $db->prepare("
            SELECT p.*, s.end_date, s.start_date, 
                   csp.name as plan_name, c.name as cheat_name  
            FROM payments p
            JOIN user_subscriptions s ON p.subscription_id = s.id
            JOIN cheat_subscription_plans csp ON s.cheat_plan_id = csp.id
            JOIN cheats c ON csp.cheat_id = c.id
            WHERE p.id = ? AND p.user_id = ?
        ");
        $stmt->execute([$payment_id, $user_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            throw new Exception('Pagamento não encontrado');
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao buscar informações do pagamento: ' . $e->getMessage();
        redirect('../purchases.php');
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Pendente - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/favicon/favicon-16x16.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/variables.css">
    <link rel="stylesheet" href="../../assets/css/checkout.css">
    <style>
        .payment-pending-page {
            background: linear-gradient(135deg, var(--dark) 0%, var(--dark-alt) 100%);
            min-height: 100vh;
            padding: 60px 0;
        }
        
        .pending-card {
            background: linear-gradient(135deg, rgba(0, 10, 20, 0.95) 0%, rgba(0, 20, 40, 0.90) 100%);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            border: 1px solid var(--border);
            position: relative;
            text-align: center;
            padding: 40px;
        }
        
        .pending-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(to right, var(--warning), transparent);
        }
        
        .pending-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #F9CB40 0%, #F9A540 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: pendingPulse 2s infinite;
        }
        
        .pending-icon i {
            color: white;
            font-size: 50px;
        }
        
        @keyframes pendingPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(249, 203, 64, 0.7);
            }
            70% {
                box-shadow: 0 0 0 20px rgba(249, 203, 64, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(249, 203, 64, 0);
            }
        }
        
        .pending-title {
            font-size: 2rem;
            color: var(--warning);
            margin-bottom: 20px;
        }
        
        .pending-message {
            color: var(--text);
            margin-bottom: 30px;
            font-size: 1.2rem;
        }
        
        .qr-code-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 20px;
            position: relative;
        }
        
        .qr-code-container img {
            width: 200px;
            height: 200px;
            display: block;
        }
        
        .qr-code-container .refresh-btn {
            position: absolute;
            bottom: -15px;
            right: -15px;
            width: 40px;
            height: 40px;
            background: var(--primary);
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .qr-code-container .refresh-btn:hover {
            transform: rotate(30deg);
            background: var(--primary-dark);
        }
        
        .pix-code {
            background: rgba(0, 15, 30, 0.7);
            border-radius: 5px;
            padding: 10px 15px;
            margin: 20px auto;
            max-width: 400px;
            position: relative;
            font-family: monospace;
            font-size: 0.9rem;
            color: var(--text);
            word-break: break-all;
            text-align: left;
        }
        
        .copy-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(0, 0, 0, 0.2);
            border: none;
            border-radius: 3px;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .copy-btn:hover {
            background: var(--primary);
        }
        
        .transaction-details {
            background: rgba(0, 15, 30, 0.5);
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: var(--text-secondary);
        }
        
        .detail-value {
            color: var(--text);
            font-weight: 500;
        }
        
        .timer-container {
            margin: 20px auto;
            text-align: center;
        }
        
        .timer {
            font-size: 1.5rem;
            color: var(--warning);
            font-weight: bold;
        }
        
        .status-check {
            margin-top: 20px;
            padding: 20px;
            background: rgba(0, 15, 30, 0.5);
            border-radius: 10px;
        }
        
        .status-check h4 {
            color: var(--text);
            margin-bottom: 15px;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .status-dot {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .status-dot.pending {
            background-color: var(--warning);
            animation: blink 1.5s infinite;
        }
        
        @keyframes blink {
            0% { opacity: 0.5; }
            50% { opacity: 1; }
            100% { opacity: 0.5; }
        }
        
        .status-text {
            font-weight: 500;
        }
        
        .action-buttons {
            margin-top: 30px;
        }
        
        .action-buttons .btn {
            margin: 0 10px 10px;
            padding: 10px 30px;
            font-weight: 500;
        }
    </style>
</head>
<body class="payment-pending-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="pending-card">
                    <div class="pending-icon">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    
                    <h1 class="pending-title">Pagamento Pendente</h1>
                    <p class="pending-message">Escaneie o QR Code abaixo com seu aplicativo bancário para finalizar o pagamento via PIX.</p>
                    
                    <div class="qr-code-container">
                        <img src="<?php echo $qr_code; ?>" alt="QR Code PIX">
                        <button class="refresh-btn" id="refreshQrCode" title="Atualizar QR Code">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    
                    <div class="timer-container">
                        <p class="mb-1">O QR Code expira em:</p>
                        <div class="timer" id="countdown">24:00:00</div>
                    </div>
                    
                    <div class="pix-code">
                        <?php echo $pix_code; ?>
                        <button class="copy-btn" id="copyPixCode" title="Copiar código">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    
                    <div class="transaction-details">
                        <div class="detail-row">
                            <span class="detail-label">Plano:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['plan_name'] . ' (' . $payment['cheat_name'] . ')'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Valor:</span>
                            <span class="detail-value">R$ <?php echo number_format($payment['amount'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">ID da transação:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Data:</span>
                            <span class="detail-value"><?php echo date('d/m/Y H:i:s', strtotime($payment['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="status-check">
                        <h4>Status do pagamento</h4>
                        <div class="status-indicator">
                            <div class="status-dot pending"></div>
                            <div class="status-text">
                                Aguardando confirmação do pagamento...
                                <span id="status-checking" class="ms-2"><i class="fas fa-spinner fa-spin"></i></span>
                            </div>
                        </div>
                        <p class="small text-muted mt-3">
                            A página será atualizada automaticamente quando o pagamento for confirmado. 
                            Não feche esta janela até a confirmação.
                        </p>
                    </div>
                    
                    <div class="action-buttons">
                        <button id="verifyPayment" class="btn btn-primary">
                            <i class="fas fa-sync-alt me-2"></i> Verificar Pagamento
                        </button>
                        <a href="../index.php" class="btn btn-outline-light">
                            <i class="fas fa-home me-2"></i> Dashboard
                        </a>
                    </div>
                    
                    <div class="mt-4">
                        <p class="small text-muted">
                            Problemas com o pagamento? <a href="../support.php" class="text-primary">Entre em contato com o suporte</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Contador regressivo
            let timerHours = 23;
            let timerMinutes = 59;
            let timerSeconds = 59;
            const countdownElement = document.getElementById('countdown');
            
            function updateCountdown() {
                if (timerSeconds === 0) {
                    if (timerMinutes === 0) {
                        if (timerHours === 0) {
                            // Tempo esgotado
                            clearInterval(timerInterval);
                            countdownElement.style.color = 'var(--danger)';
                            countdownElement.textContent = 'Expirado';
                            return;
                        }
                        timerHours--;
                        timerMinutes = 59;
                    } else {
                        timerMinutes--;
                    }
                    timerSeconds = 59;
                } else {
                    timerSeconds--;
                }
                
                // Formatar contador com zeros à esquerda se necessário
                const hours = String(timerHours).padStart(2, '0');
                const minutes = String(timerMinutes).padStart(2, '0');
                const seconds = String(timerSeconds).padStart(2, '0');
                
                // Atualizar texto do contador
                countdownElement.textContent = `${hours}:${minutes}:${seconds}`;
                
                // Alterar cor quando estiver no fim
                if (timerHours === 0 && timerMinutes < 10) {
                    countdownElement.style.color = 'var(--warning)';
                }
                if (timerHours === 0 && timerMinutes < 5) {
                    countdownElement.style.color = 'var(--danger)';
                }
            }
            
            // Iniciar contador com intervalo de 1 segundo
            const timerInterval = setInterval(updateCountdown, 1000);
            
            // Executar uma vez para inicializar o contador
            updateCountdown();
            
            // Copiar código PIX para área de transferência
            document.getElementById('copyPixCode').addEventListener('click', function() {
                const pixCode = this.parentElement.textContent.trim();
                navigator.clipboard.writeText(pixCode).then(
                    function() {
                        // Feedback visual de sucesso
                        const btn = document.getElementById('copyPixCode');
                        const originalIcon = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-check"></i>';
                        btn.style.backgroundColor = 'var(--success)';
                        
                        // Restaurar ícone original após 1.5 segundos
                        setTimeout(function() {
                            btn.innerHTML = originalIcon;
                            btn.style.backgroundColor = '';
                        }, 1500);
                    },
                    function(err) {
                        console.error('Não foi possível copiar o texto: ', err);
                        alert('Não foi possível copiar o código. Por favor, copie manualmente.');
                    }
                );
            });
            
            // Verificar status do pagamento
            function checkPaymentStatus() {
                const statusChecking = document.getElementById('status-checking');
                statusChecking.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                // Fazer requisição AJAX para verificar status
                $.ajax({
                    url: 'check_payment_status.php',
                    type: 'GET',
                    data: {
                        id: <?php echo $payment_id; ?>
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'completed') {
                            // Pagamento confirmado
                            $('.status-dot').removeClass('pending').addClass('success');
                            $('.status-dot').css('background-color', 'var(--success)');
                            $('.status-dot').css('animation', 'none');
                            $('.status-text').html('Pagamento confirmado! <i class="fas fa-check-circle text-success"></i>');
                            
                            // Mostrar mensagem de sucesso
                            Swal.fire({
                                title: 'Pagamento Confirmado!',
                                text: 'Seu pagamento foi processado com sucesso. Redirecionando para a área de cliente...',
                                icon: 'success',
                                timer: 3000,
                                timerProgressBar: true,
                                showConfirmButton: false
                            }).then(() => {
                                // Redirecionar para a página de sucesso
                                window.location.href = 'payment_success.php?id=<?php echo $payment_id; ?>';
                            });
                            
                            // Parar verificações automáticas
                            clearInterval(statusCheckInterval);
                        } else {
                            // Atualizar status
                            statusChecking.innerHTML = '<i class="fas fa-clock"></i>';
                            
                            setTimeout(() => {
                                statusChecking.innerHTML = '';
                            }, 1000);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro ao verificar status:', error);
                        statusChecking.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i>';
                        
                        setTimeout(() => {
                            statusChecking.innerHTML = '';
                        }, 2000);
                    }
                });
            }
            
            // Verificar a cada 30 segundos
            const statusCheckInterval = setInterval(checkPaymentStatus, 30000);
            
            // Verificar imediatamente na carga da página
            setTimeout(checkPaymentStatus, 1500);
            
            // Botão para verificar manualmente
            document.getElementById('verifyPayment').addEventListener('click', function() {
                checkPaymentStatus();
            });
            
            // Botão para atualizar QR Code
            document.getElementById('refreshQrCode').addEventListener('click', function() {
                const button = this;
                const originalIcon = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                // Simular atualização (em um ambiente real, você faria uma requisição ao backend)
                setTimeout(function() {
                    button.innerHTML = '<i class="fas fa-check"></i>';
                    
                    setTimeout(function() {
                        button.innerHTML = originalIcon;
                        button.disabled = false;
                    }, 1000);
                }, 1500);
            });
            
            // Carregar biblioteca SweetAlert2 para notificações bonitas
            if (typeof Swal === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                document.head.appendChild(script);
            }
        });
    </script>
</body>
</html>