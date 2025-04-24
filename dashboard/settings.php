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

// Obter dados do usuário
$user_id = $_SESSION['user_id'];
$auth = new Auth();
$user = $auth->get_user($user_id);

// Mensagens para feedback ao usuário
$success_message = '';
$error_message = '';

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Inicializar array com dados a serem atualizados
        $settings_data = [];
        
        // Configurações de notificação via email
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $settings_data['email_notifications'] = $email_notifications;
        
        // Configurações de privacidade
        $profile_visibility = isset($_POST['profile_visibility']) ? $_POST['profile_visibility'] : 'private';
        $settings_data['profile_visibility'] = $profile_visibility;
        
        // Configurações de segurança
        if (!empty($_POST['current_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Verificar se a senha atual está correta
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $db_password = $stmt->fetchColumn();
            
            if (!password_verify($current_password, $db_password)) {
                throw new Exception('A senha atual está incorreta.');
            }
            
            // Verificar se as senhas novas coincidem
            if ($new_password !== $confirm_password) {
                throw new Exception('Nova senha e confirmação não correspondem.');
            }
            
            // Verificar comprimento mínimo da senha
            if (strlen($new_password) < 6) {
                throw new Exception('Nova senha deve ter pelo menos 6 caracteres.');
            }
            
            // Adicionar nova senha aos dados a serem atualizados
            $settings_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
        }
        
        // Atualizar configurações de 2FA
        $enable_2fa = isset($_POST['enable_2fa']) ? 1 : 0;
        
        // Aqui você implementaria a lógica para 2FA se necessário
        $settings_data['two_factor_enabled'] = $enable_2fa;
        
        // Personalização de interface
        $theme_preference = isset($_POST['theme_preference']) ? $_POST['theme_preference'] : 'dark';
        $settings_data['theme_preference'] = $theme_preference;
        
        // Atualizar conta do usuário
        if (!empty($settings_data)) {
            if ($auth->update_profile($user_id, $settings_data)) {
                $success_message = 'Configurações atualizadas com sucesso!';
                // Recarregar dados do usuário para exibir as informações atualizadas
                $user = $auth->get_user($user_id);
                
                // Registrar atividade
                $stmt = $db->prepare("
                    INSERT INTO user_logs (user_id, action, description, ip_address)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, 
                    'settings_update', 
                    'Configurações atualizadas', 
                    get_client_ip()
                ]);
            } else {
                $error_message = 'Ocorreu um erro ao atualizar as configurações. Tente novamente.';
            }
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Obter configurações atuais do usuário
$user_settings = [];

// Try to get existing settings if columns exist
try {
    $stmt = $db->prepare("
        SELECT 
            * 
        FROM 
            users 
        WHERE 
            id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if settings columns exist and set defaults if not
    $user_settings = [
        'email_notifications' => isset($user_data['email_notifications']) ? $user_data['email_notifications'] : 1,
        'profile_visibility' => isset($user_data['profile_visibility']) ? $user_data['profile_visibility'] : 'private',
        'two_factor_enabled' => isset($user_data['two_factor_enabled']) ? $user_data['two_factor_enabled'] : 0,
        'theme_preference' => isset($user_data['theme_preference']) ? $user_data['theme_preference'] : 'dark'
    ];
} catch (PDOException $e) {
    // Set default values if query fails due to missing columns
    $user_settings = [
        'email_notifications' => 1,
        'profile_visibility' => 'private',
        'two_factor_enabled' => 0,
        'theme_preference' => 'dark'
    ];
}

// Obter log de atividades recentes
$stmt = $db->prepare("
    SELECT action, ip_address, created_at
    FROM user_logs
    WHERE user_id = ? AND action LIKE 'settings%'
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_settings_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - <?php echo SITE_NAME; ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon/favicon-16x16.png">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Toastify -->
    <link href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" rel="stylesheet">
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/scroll.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <!-- Fontes adicionais -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap">
    <style>
        /* Estilos específicos para a página de configurações */
        .settings-card {
            background-color: var(--card);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .settings-header {
            border-bottom: 1px solid var(--border);
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-sm);
        }

        .settings-header h3 {
            color: var(--primary-light);
            font-size: var(--font-size-lg);
            margin-bottom: 0;
            display: flex;
            align-items: center;
        }

        .settings-header h3 i {
            margin-right: 10px;
            color: var(--primary);
        }

        .settings-body {
            padding: var(--spacing-sm) 0;
        }

        .settings-section {
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--border);
        }

        .settings-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }

        .settings-section h4 {
            font-size: var(--font-size-md);
            color: var(--text-secondary);
            margin-bottom: var(--spacing-md);
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .form-switch .form-check-input:focus {
            border-color: var(--primary-alpha-50);
            box-shadow: 0 0 0 0.2rem var(--primary-alpha-20);
        }

        .activity-item {
            padding: var(--spacing-sm);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-alpha-20);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: var(--spacing-md);
        }

        .activity-icon i {
            color: var(--primary);
            font-size: var(--font-size-md);
        }

        .activity-content {
            flex-grow: 1;
        }

        .activity-time {
            font-size: var(--font-size-xs);
            color: var(--text-muted);
        }

        .security-tips {
            background-color: rgba(0, 22, 36, 0.5);
            border-radius: var(--border-radius-md);
            padding: var(--spacing-md);
            border-left: 4px solid var(--warning);
        }

        .security-tips h5 {
            color: var(--warning);
            font-size: var(--font-size-md);
            margin-bottom: var(--spacing-sm);
        }

        .security-tips ul {
            padding-left: var(--spacing-md);
            margin-bottom: 0;
        }

        .security-tips li {
            margin-bottom: 8px;
            color: var(--text-secondary);
        }

        .security-tips li:last-child {
            margin-bottom: 0;
        }

        /* Efeito visual para switches */
        .form-switch .form-check-input {
            height: 1.5em;
            width: 2.75em;
        }

        .btn-glow {
            box-shadow: 0 0 10px var(--primary-alpha-50);
            transition: all 0.3s;
        }

        .btn-glow:hover {
            box-shadow: 0 0 15px var(--primary-alpha-70);
        }

        /* Efeito para seção em destaque */
        .highlight-section {
            position: relative;
            overflow: hidden;
        }

        .highlight-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: inherit;
            border: 2px solid transparent;
            background: linear-gradient(45deg, var(--primary), transparent) border-box;
            -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            mask-composite: exclude;
            pointer-events: none;
            opacity: 0.7;
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
                    <li><a href="support.php">Suporte</a></li>
                    <li><a href="profile.php">Perfil</a></li>
                </ul>
            </nav>
            <div class="user-menu">
                <div class="user-info">
                    <span><?php echo $user['username']; ?></span>
                    <img src="<?php echo !empty($user['avatar_url']) ? '../uploads/avatars/' . $user['avatar_url'] : '../assets/images/avatar.png'; ?>" alt="Avatar">
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
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>

            <!-- Cabeçalho da Página -->
            <div class="page-header">
                <h1><i class="fas fa-cog text-primary me-2"></i>Configurações da Conta</h1>
                <p>Personalize suas configurações de conta e preferências de segurança</p>
            </div>

            <div class="row">
                <!-- Configurações Principais -->
                <div class="col-lg-8">
                    <form action="settings.php" method="POST">
                        <!-- Notificações -->
                        <div class="settings-card">
                            <div class="settings-header">
                                <h3><i class="fas fa-bell"></i> Notificações</h3>
                            </div>
                            <div class="settings-body">
                                <div class="settings-section">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?php echo $user_settings['email_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_notifications">Receber notificações por email</label>
                                    </div>
                                    <small class="form-text text-muted">Receba emails sobre atualizações de cheats, novas versões e informações importantes da conta</small>
                                </div>
                            </div>
                        </div>

                        <!-- Segurança -->
                        <div class="settings-card highlight-section">
                            <div class="settings-header">
                                <h3><i class="fas fa-shield-alt"></i> Segurança</h3>
                            </div>
                            <div class="settings-body">
                                <div class="settings-section">
                                    <h4>Alterar Senha</h4>
                                    <div class="row mb-3">
                                        <div class="col-md-12 mb-3">
                                            <label for="current_password" class="form-label">Senha Atual</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="new_password" class="form-label">Nova Senha</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">Confirmar Nova Senha</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                        </div>
                                    </div>
                                </div>

                                <div class="settings-section">
                                    <h4>Autenticação de dois fatores (2FA)</h4>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="enable_2fa" name="enable_2fa" <?php echo $user_settings['two_factor_enabled'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_2fa">Habilitar autenticação de dois fatores</label>
                                    </div>
                                    <small class="form-text text-muted">Proteja ainda mais sua conta exigindo um código além da senha para fazer login. Você receberá um código por email ou SMS.</small>
                                </div>

                                <div class="security-tips mt-4">
                                    <h5><i class="fas fa-lightbulb me-2"></i>Dicas de Segurança</h5>
                                    <ul>
                                        <li>Use senhas fortes com pelo menos 8 caracteres, incluindo letras, números e símbolos</li>
                                        <li>Nunca compartilhe suas credenciais com outros usuários</li>
                                        <li>Ative a autenticação de dois fatores para maior segurança</li>
                                        <li>Altere sua senha regularmente</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Personalização de Interface -->
                        <div class="settings-card">
                            <div class="settings-header">
                                <h3><i class="fas fa-paint-brush"></i> Personalização</h3>
                            </div>
                            <div class="settings-body">
                                <div class="settings-section">
                                    <h4>Tema da Interface</h4>
                                    <div class="mb-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="theme_preference" id="theme_dark" value="dark" <?php echo $user_settings['theme_preference'] === 'dark' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="theme_dark">
                                                <strong>Escuro</strong> - Tema escuro (padrão)
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="theme_preference" id="theme_light" value="light" <?php echo $user_settings['theme_preference'] === 'light' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="theme_light">
                                                <strong>Claro</strong> - Tema claro
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="theme_preference" id="theme_system" value="system" <?php echo $user_settings['theme_preference'] === 'system' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="theme_system">
                                                <strong>Sistema</strong> - Usar preferência do sistema
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn btn-primary btn-glow">
                                        <i class="fas fa-save me-2"></i> Salvar Configurações
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Sidebar com Informações Adicionais -->
                <div class="col-lg-4">
                    <!-- Atividades Recentes -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h3><i class="fas fa-history"></i> Atividades Recentes</h3>
                        </div>
                        <div class="settings-body">
                            <?php if (!empty($recent_settings_activities)): ?>
                                <?php foreach ($recent_settings_activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-cog"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-text">
                                                <?php 
                                                    if ($activity['action'] === 'settings_update') {
                                                        echo 'Configurações atualizadas';
                                                    } else {
                                                        echo htmlspecialchars($activity['action']);
                                                    }
                                                ?>
                                            </div>
                                            <div class="activity-meta">
                                                <span class="activity-time"><i class="fas fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></span>
                                                <span class="ms-2"><i class="fas fa-globe me-1"></i><?php echo $activity['ip_address']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">Nenhuma atividade recente registrada</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Gerenciamento de Conta -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h3><i class="fas fa-user-cog"></i> Gerenciamento de Conta</h3>
                        </div>
                        <div class="settings-body">
                            <div class="d-grid gap-3">
                                <a href="profile.php" class="btn btn-outline-primary">
                                    <i class="fas fa-user me-2"></i> Editar Perfil
                                </a>
                                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#dataExportModal">
                                    <i class="fas fa-file-export me-2"></i> Exportar Meus Dados
                                </button>
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                    <i class="fas fa-user-slash me-2"></i> Excluir Conta
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal para exportar dados -->
    <div class="modal fade" id="dataExportModal" tabindex="-1" aria-labelledby="dataExportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title" id="dataExportModalLabel">Exportar Meus Dados</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Você pode solicitar uma exportação de seus dados pessoais. Esta operação pode levar alguns minutos e você receberá um e-mail quando seus dados estiverem prontos para download.</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="includeActivity" checked>
                        <label class="form-check-label" for="includeActivity">
                            Incluir histórico de atividades
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="includeDownloads" checked>
                        <label class="form-check-label" for="includeDownloads">
                            Incluir histórico de downloads
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmExport">Solicitar Exportação</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para excluir conta -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAccountModalLabel">Excluir Conta</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> Esta operação não pode ser desfeita!
                    </div>
                    <p>Ao excluir sua conta, você perderá:</p>
                    <ul>
                        <li>Todas as assinaturas ativas (sem reembolso)</li>
                        <li>Histórico de downloads</li>
                        <li>Dados de perfil e configurações</li>
                        <li>Acesso a todos os cheats</li>
                    </ul>
                    <div class="mb-3">
                        <label for="deleteConfirmation" class="form-label">Para confirmar, digite "EXCLUIR" abaixo:</label>
                        <input type="text" class="form-control" id="deleteConfirmation">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete" disabled>Excluir Permanentemente</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="dashboard-footer">
        <div class="container">
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos os direitos reservados.
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Esconder o spinner de carregamento
            setTimeout(function() {
                document.querySelector('.loading').classList.add('hide');
            }, 800);

            // Mostrar/Esconder o menu do usuário
            const userMenu = document.querySelector('.user-info');
            const userDropdown = document.querySelector('.user-dropdown');

            if (userMenu && userDropdown) {
                userMenu.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                });

                // Fechar ao clicar fora
                document.addEventListener('click', function(event) {
                    if (!event.target.closest('.user-menu')) {
                        userDropdown.classList.remove('active');
                    }
                });
            }

            // Validação de formulário
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (confirmPasswordInput && newPasswordInput) {
                confirmPasswordInput.addEventListener('input', function() {
                    if (newPasswordInput.value !== confirmPasswordInput.value) {
                        confirmPasswordInput.setCustomValidity('As senhas não correspondem');
                    } else {
                        confirmPasswordInput.setCustomValidity('');
                    }
                });
                
                newPasswordInput.addEventListener('input', function() {
                    if (newPasswordInput.value !== confirmPasswordInput.value && confirmPasswordInput.value) {
                        confirmPasswordInput.setCustomValidity('As senhas não correspondem');
                    } else {
                        confirmPasswordInput.setCustomValidity('');
                    }
                });
            }

            // Validação para exclusão de conta
            const deleteConfirmField = document.getElementById('deleteConfirmation');
            const confirmDeleteBtn = document.getElementById('confirmDelete');
            
            if (deleteConfirmField && confirmDeleteBtn) {
                deleteConfirmField.addEventListener('input', function() {
                    confirmDeleteBtn.disabled = this.value !== 'EXCLUIR';
                });
                
                confirmDeleteBtn.addEventListener('click', function() {
                    if (deleteConfirmField.value === 'EXCLUIR') {
                        // Aqui você pode implementar a chamada AJAX para excluir a conta
                        showNotification('Conta excluída com sucesso!', 'success');
                        setTimeout(() => {
                            window.location.href = '../index.php';
                        }, 2000);
                    }
                });
            }

            // Função para mostrar notificações
            window.showNotification = function(message, type = "info") {
                Toastify({
                    text: message,
                    duration: 3000,
                    gravity: "top",
                    position: "right",
                    className: "notification-" + type,
                    style: {
                        background: type === "success" ? "#00CF9B" : 
                                  type === "error" ? "#FF3A4E" : 
                                  type === "warning" ? "#F9CB40" : "#22C5B9",
                    }
                }).showToast();
            };

            // Solicitar exportação de dados
            document.getElementById('confirmExport').addEventListener('click', function() {
                // Aqui você pode implementar a lógica para solicitar exportação
                const includeActivity = document.getElementById('includeActivity').checked;
                const includeDownloads = document.getElementById('includeDownloads').checked;
                
                // Fechando o modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('dataExportModal'));
                modal.hide();
                
                // Mostrando notificação
                showNotification('Solicitação de exportação recebida. Você receberá um email em breve.', 'success');
            });

            <?php if ($success_message): ?>
                showNotification('<?php echo addslashes($success_message); ?>', 'success');
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                showNotification('<?php echo addslashes($error_message); ?>', 'error');
            <?php endif; ?>
        });
    </script>
</body>

</html>