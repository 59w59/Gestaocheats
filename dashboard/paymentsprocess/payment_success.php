<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Verificar se o usuário está logado
if (!is_logged_in()) {
    redirect('../pages/login.php');
}

// Verificar se existe uma transação bem-sucedida na sessão
if (!isset($_SESSION['payment_success']) || !isset($_GET['id'])) {
    redirect('purchases.php');
}

// Limpar a flag da sessão
$payment_success = $_SESSION['payment_success'];
unset($_SESSION['payment_success']);

// Obter dados do usuário
$user_id = $_SESSION['user_id'];
$auth = new Auth();
$user = $auth->get_user($user_id);

// Obter informações do pagamento
$payment_id = (int)$_GET['id'];

try {
    $stmt = $db->prepare("
        SELECT p.*, s.end_date, s.start_date, csp.name as plan_name, c.name as cheat_name  
        FROM payments p
        JOIN user_subscriptions s ON p.subscription_id = s.id
        JOIN cheat_subscription_plans csp ON s.cheat_plan_id = csp.id
        JOIN cheats c ON csp.cheat_id = c.id
        WHERE p.id = ? AND p.user_id = ?
    ");
    $stmt->execute([$payment_id, $user_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception('Pagamento não encontrado.');
    }

} catch (Exception $e) {
    $_SESSION['error'] = 'Erro ao buscar informações do pagamento: ' . $e->getMessage();
    redirect('purchases.php');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Concluído - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon/favicon-16x16.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/checkout.css">
    <style>
        .payment-success-page {
            background: linear-gradient(135deg, var(--dark) 0%, var(--dark-alt) 100%);
            min-height: 100vh;
            padding: 60px 0;
        }
        
        .success-card {
            background: linear-gradient(135deg, rgba(0, 10, 20, 0.95) 0%, rgba(0, 20, 40, 0.90) 100%);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            border: 1px solid var(--border);
            position: relative;
            text-align: center;
            padding: 40px;
        }
        
        .success-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(to right, var(--success), transparent);
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #00CF9B 0%, #00CF9B 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: pulse 2s infinite;
        }
        
        .success-icon i {
            color: white;
            font-size: 50px;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(0, 207, 155, 0.7);
            }
            70% {
                box-shadow: 0 0 0 20px rgba(0, 207, 155, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(0, 207, 155, 0);
            }
        }
        
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: #f0f;
            animation: confetti 5s ease-in-out infinite;
        }
        
        @keyframes confetti {
            0% { transform: translateY(0) rotateZ(0); opacity: 1; }
            100% { transform: translateY(500px) rotateZ(720deg); opacity: 0; }
        }
        
        .success-title {
            font-size: 2rem;
            color: var(--success);
            margin-bottom: 20px;
        }
        
        .success-message {
            color: var(--text);
            margin-bottom: 30px;
            font-size: 1.2rem;
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
        
        .next-steps {
            margin: 30px 0;
        }
        
        .next-steps h4 {
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .next-steps ul {
            text-align: left;
            list-style-type: none;
            padding: 0;
        }
        
        .next-steps li {
            padding: 10px 0;
            display: flex;
            align-items: center;
        }
        
        .next-steps li i {
            color: var(--success);
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .action-buttons {
            margin-top: 30px;
        }
        
        .action-buttons .btn {
            margin: 0 10px;
            padding: 10px 30px;
            font-weight: 500;
        }
    </style>
</head>
<body class="payment-success-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="success-card">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    
                    <h1 class="success-title">Pagamento Concluído!</h1>
                    <p class="success-message">Seu pagamento foi processado com sucesso e sua assinatura está ativa.</p>
                    
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
                            <span class="detail-label">Forma de pagamento:</span>
                            <span class="detail-value">
                                <?php 
                                    if ($payment['payment_method'] === 'pix') {
                                        echo '<i class="fas fa-qrcode me-1"></i> PIX';
                                    } elseif ($payment['payment_method'] === 'card') {
                                        echo '<i class="fas fa-credit-card me-1"></i> Cartão de Crédito';
                                    } else {
                                        echo htmlspecialchars(ucfirst($payment['payment_method']));
                                    }
                                ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">ID da transação:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Data:</span>
                            <span class="detail-value"><?php echo date('d/m/Y H:i:s', strtotime($payment['created_at'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i> Aprovado
                                </span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Validade:</span>
                            <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($payment['end_date'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="next-steps">
                        <h4>Próximos passos</h4>
                        <ul>
                            <li>
                                <i class="fas fa-download"></i>
                                <span>Acesse seus downloads na seção <strong>Meus Downloads</strong></span>
                            </li>
                            <li>
                                <i class="fas fa-book"></i>
                                <span>Consulte os tutoriais para instruções de instalação</span>
                            </li>
                            <li>
                                <i class="fas fa-headset"></i>
                                <span>Em caso de dúvidas, acesse nosso suporte 24/7</span>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="downloads.php" class="btn btn-primary">
                            <i class="fas fa-download me-2"></i> Ir para Downloads
                        </a>
                        <a href="index.php" class="btn btn-outline-light">
                            <i class="fas fa-home me-2"></i> Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Criar confettis para efeito de celebração
        document.addEventListener('DOMContentLoaded', function() {
            const colors = ['#00CF9B', '#22C5B9', '#3FE8C1', '#E0FF00', '#FF647C'];
            
            // Criar 50 confettis
            for (let i = 0; i < 50; i++) {
                createConfetti();
            }
            
            function createConfetti() {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                
                // Estilo aleatório para cada confetti
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.width = Math.random() * 10 + 5 + 'px';
                confetti.style.height = Math.random() * 10 + 5 + 'px';
                confetti.style.opacity = Math.random() + 0.5;
                confetti.style.animationDuration = Math.random() * 3 + 2 + 's';
                confetti.style.animationDelay = Math.random() * 5 + 's';
                
                document.body.appendChild(confetti);
                
                // Remover depois da animação
                setTimeout(() => {
                    document.body.removeChild(confetti);
                }, 7000);
            }
        });
    </script>
</body>
</html>