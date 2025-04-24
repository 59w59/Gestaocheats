<?php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Verificar e criar os diretórios necessários
$upload_dir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

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
        // Obter e validar os dados do formulário
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $discord_id = trim($_POST['discord_id'] ?? '');
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        
        // Preparar dados para atualização
        $user_data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'discord_id' => $discord_id
        ];
        
        // Adicione esta linha para depuração antes de atualizar o perfil
        error_log('Dados para atualização: ' . print_r($user_data, true));
        
        // Verificar se o usuário quer mudar a senha
        if (!empty($new_password)) {
            // Verificar se a senha atual está correta
            if (empty($current_password) || !password_verify($current_password, $user['password'])) {
                throw new Exception('Senha atual incorreta.');
            }
            
            // Verificar se a nova senha e a confirmação correspondem
            if ($new_password !== $confirm_password) {
                throw new Exception('Nova senha e confirmação não correspondem.');
            }
            
            // Verificar comprimento mínimo da senha
            if (strlen($new_password) < 6) {
                throw new Exception('Nova senha deve ter pelo menos 6 caracteres.');
            }
            
            // Adicionar nova senha aos dados a serem atualizados
            $user_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
        }
        
        // Processar upload de avatar
        if (isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1' && !empty($user['avatar_url'])) {
            // Remover avatar existente
            $avatar_path = '../uploads/avatars/' . $user['avatar_url'];
            if (file_exists($avatar_path)) {
                unlink($avatar_path);
            }
            $user_data['avatar_url'] = null;
        } else if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            // Diretório para salvar os avatares
            $upload_dir = '../uploads/avatars/';
            
            // Criar o diretório se não existir
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Verificar o tipo de arquivo
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $file_type = $_FILES['avatar']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Tipo de arquivo não permitido. Por favor, envie uma imagem JPG ou PNG.');
            }
            
            // Verificar tamanho do arquivo (máximo 2MB)
            $max_size = 2 * 1024 * 1024; // 2MB em bytes
            if ($_FILES['avatar']['size'] > $max_size) {
                throw new Exception('O tamanho do arquivo excede o limite de 2MB.');
            }
            
            // Gerar um nome de arquivo único
            $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
            
            // Mover o arquivo para o diretório de uploads
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                // Se já existir um avatar anterior, remova-o
                if (!empty($user['avatar_url'])) {
                    $old_avatar = $upload_dir . $user['avatar_url'];
                    if (file_exists($old_avatar)) {
                        unlink($old_avatar);
                    }
                }
                
                // Adicionar o novo avatar ao array de dados para atualização
                $user_data['avatar_url'] = $new_filename;
            } else {
                throw new Exception('Ocorreu um erro ao fazer upload da imagem.');
            }
        }
        
        // Atualizar perfil do usuário
        if ($auth->update_profile($user_id, $user_data)) {
            $success_message = 'Perfil atualizado com sucesso!';
            // Recarregar dados do usuário para exibir as informações atualizadas
            $user = $auth->get_user($user_id);
            
            // Registrar atividade utilizando o método correto
            $stmt = $db->prepare("
                INSERT INTO user_logs (user_id, action, description, ip_address)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id, 
                'profile_update', 
                'Perfil atualizado com sucesso', 
                get_client_ip()
            ]);
        } else {
            $error_message = 'Ocorreu um erro ao atualizar o perfil. Tente novamente.';
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Obter estatísticas do usuário
// Contagem de downloads
$stmt = $db->prepare("
    SELECT COUNT(*) FROM user_downloads WHERE user_id = ?
");
$stmt->execute([$user_id]);
$download_count = $stmt->fetchColumn();

// Verificar se o usuário tem assinatura ativa
$stmt = $db->prepare("
    SELECT COUNT(*) FROM user_subscriptions 
    WHERE user_id = ? AND status = 'active' AND end_date > NOW()
");
$stmt->execute([$user_id]);
$has_active_subscription = (bool)$stmt->fetchColumn();

// Contagem de assinaturas ativas
$stmt = $db->prepare("
    SELECT COUNT(*) FROM user_subscriptions 
    WHERE user_id = ? AND status = 'active' AND end_date > NOW()
");
$stmt->execute([$user_id]);
$active_subscriptions = $stmt->fetchColumn();

// Data de registro formatada
$registration_date = date('d/m/Y', strtotime($user['created_at']));

// Último login formatado
$last_login = $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'N/A';

// Histórico de atividades recentes
$stmt = $db->prepare("
    SELECT * FROM user_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - <?php echo SITE_NAME; ?></title>
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
    <link rel="stylesheet" href="../assets/css/custom.css">
    <link rel="stylesheet" href="../assets/css/scroll.css">
    
    <style>
                /* Estilos específicos para o menu do usuário e dropdown - sobreposição */
                .user-menu {
            position: relative !important;
            margin-left: var(--spacing-md) !important;
            z-index: 9999 !important;
            /* Elevado para garantir que fique na frente */
        }

        .user-info {
            display: flex !important;
            align-items: center !important;
            gap: var(--spacing-sm) !important;
            padding: var(--spacing-xs) var(--spacing-sm) !important;
            border-radius: var(--border-radius-md) !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            border: 1px solid transparent !important;
            background-color: rgba(0, 44, 58, 0.5) !important;
        }

        .user-info:hover {
            background-color: var(--primary-alpha-20) !important;
            border-color: var(--primary) !important;
            box-shadow: 0 0 10px var(--primary-alpha-50) !important;
        }

        .user-info img {
            width: 38px !important;
            height: 38px !important;
            border-radius: 8px !important;
            border: 2px solid var(--primary-alpha-50) !important;
            box-shadow: 0 0 0 2px var(--border-glow) !important;
            transition: all 0.2s !important;
        }

        .user-dropdown {
            position: absolute !important;
            top: calc(100% + 12px) !important;
            right: 0 !important;
            width: 260px !important;
            background-color: var(--card) !important;
            border: 2px solid var(--primary) !important;
            border-radius: var(--border-radius-lg) !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 20px rgba(0, 207, 155, 0.3) !important;
            z-index: 9999 !important;
            /* Forçar maior z-index */
            transform: translateY(10px) !important;
            opacity: 0 !important;
            visibility: hidden !important;
            transition: transform 0.3s, opacity 0.3s, visibility 0.3s !important;
            display: block !important;
            /* Forçar display block */
            pointer-events: none !important;
        }

        /* Seta do dropdown */
        .user-dropdown::after {
            content: '' !important;
            position: absolute !important;
            top: -10px !important;
            right: 20px !important;
            width: 20px !important;
            height: 20px !important;
            background-color: var(--card) !important;
            border-top: 2px solid var(--primary) !important;
            border-left: 2px solid var(--primary) !important;
            transform: rotate(45deg) !important;
            z-index: -1 !important;
        }

        .user-dropdown.active {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) !important;
            pointer-events: all !important;
        }

        .user-dropdown header {
            padding: var(--spacing-md) !important;
            border-bottom: 1px solid var(--border) !important;
            text-align: center !important;
            background-color: rgba(0, 44, 58, 0.7) !important;
        }

        .user-dropdown header h4 {
            margin: 0 !important;
            font-size: var(--font-size-md) !important;
            color: var(--primary-light) !important;
            font-weight: var(--font-weight-semibold) !important;
        }

        .user-dropdown header p {
            margin-top: 5px !important;
            margin-bottom: 0 !important;
            font-size: var(--font-size-xs) !important;
            color: var(--text-secondary) !important;
        }

        .user-dropdown ul {
            list-style: none !important;
            padding: var(--spacing-sm) !important;
            margin: 0 !important;
        }

        .user-dropdown li {
            margin-bottom: 2px !important;
        }

        .user-dropdown li:last-child {
            margin-bottom: 0 !important;
        }

        .user-dropdown a {
            display: flex !important;
            align-items: center !important;
            padding: 10px !important;
            border-radius: var(--border-radius-md) !important;
            color: var(--text-secondary) !important;
            transition: all 0.2s !important;
            text-decoration: none !important;
        }

        .user-dropdown a:hover {
            background-color: var(--primary-alpha-10) !important;
            transform: translateX(3px) !important;
            color: var(--primary-light) !important;
        }

        .user-dropdown a i {
            width: 20px !important;
            margin-right: 10px !important;
            color: var(--primary) !important;
            text-align: center !important;
        }
        /* Estilos específicos para a página de perfil */
        .profile-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
            padding-bottom: var(--spacing-xl);
            border-bottom: 1px solid var(--border);
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: var(--border-radius-lg);
            border: 3px solid var(--primary);
            box-shadow: 0 0 20px var(--primary-alpha-30);
            overflow: hidden;
            background: var(--card);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-info h2 {
            font-size: var(--font-size-2xl);
            margin-bottom: var(--spacing-xs);
            color: var(--text);
        }
        
        .profile-status {
            display: inline-flex;
            align-items: center;
            background: var(--primary-alpha-10);
            color: var(--primary);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--border-radius-full);
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
            margin-top: var(--spacing-xs);
        }
        
        .profile-status i {
            margin-right: var(--spacing-xs);
        }
        
        .profile-meta {
            display: flex;
            gap: var(--spacing-xl);
            margin-top: var(--spacing-md);
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xxs);
        }
        
        .meta-label {
            color: var(--text-secondary);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
            letter-spacing: var(--letter-spacing-wide);
            text-transform: uppercase;
        }
        
        .meta-value {
            color: var(--text);
            font-size: var(--font-size-md);
            font-weight: var(--font-weight-medium);
        }
        
        .settings-card {
            background: var(--card);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: var(--spacing-xl);
            transition: all var(--transition-normal);
        }
        
        .settings-card:hover {
            border-color: var(--primary-alpha-30);
            box-shadow: var(--shadow-lg), 0 10px 20px rgba(0, 207, 155, 0.1);
            transform: translateY(-3px);
        }
        
        .settings-header {
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--border);
            background: linear-gradient(to right, rgba(0, 44, 58, 0.8), rgba(0, 24, 36, 0.8));
            position: relative;
        }
        
        .settings-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }
        
        .settings-header h3 {
            margin: 0;
            font-size: var(--font-size-xl);
            color: var(--text);
            display: flex;
            align-items: center;
        }
        
        .settings-header h3 i {
            margin-right: var(--spacing-sm);
            color: var(--primary);
        }
        
        .settings-body {
            padding: var(--spacing-xl);
        }
        
        .form-label {
            color: var(--text);
            font-weight: var(--font-weight-medium);
            margin-bottom: var(--spacing-xs);
        }
        
        .activity-log {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-md);
            transition: all var(--transition-fast);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item:hover {
            background: var(--primary-alpha-05);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-lg);
            background: var(--primary-alpha-10);
            color: var(--primary);
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-text {
            color: var(--text);
            margin-bottom: var(--spacing-xxs);
        }
        
        .activity-meta {
            color: var(--text-secondary);
            font-size: var(--font-size-sm);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .activity-meta i {
            font-size: var(--font-size-xs);
        }
        
        /* Efeito de brilho nos botões */
        .btn-glow {
            position: relative;
            overflow: hidden;
        }
        
        .btn-glow::after {
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            z-index: -1;
            background: var(--primary);
            opacity: 0.3;
            border-radius: var(--border-radius-full);
            filter: blur(15px);
            transition: all var(--transition-normal);
            transform: scale(0.8);
        }
        
        .btn-glow:hover::after {
            opacity: 0.6;
            transform: scale(1);
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                align-items: flex-start;
                text-align: center;
            }
            
            .profile-avatar {
                margin: 0 auto var(--spacing-md);
            }
            
            .profile-meta {
                justify-content: center;
            }
        }

        /* Adicione este CSS na seção de estilos, dentro da tag <style> */
        .avatar-upload {
            margin: 0 auto 20px;
            max-width: 200px;
        }

        .avatar-preview {
            width: 150px;
            height: 150px;
            border-radius: var(--border-radius-full);
            border: 3px solid var(--primary);
            overflow: hidden;
            position: relative;
            margin: 0 auto;
            box-shadow: 0 0 20px var(--primary-alpha-30);
            background: var(--card);
            transition: all 0.3s ease;
        }

        .avatar-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 0 25px var(--primary-alpha-50);
        }

        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .avatar-edit {
            text-align: center;
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
                    <li><a href="profile.php" class="active">Perfil</a></li>
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

            <!-- Cabeçalho do Perfil -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <img src="<?php echo !empty($user['avatar_url']) ? '../uploads/avatars/' . $user['avatar_url'] : '../assets/images/avatar.png'; ?>" alt="Avatar">
                </div>
                <div class="profile-info">
                    <h2><?php echo $user['first_name'] ? $user['first_name'] . ' ' . $user['last_name'] : $user['username']; ?></h2>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                        <span class="profile-status">
                            <i class="fas fa-circle"></i> 
                            <?php echo $user['status'] === 'active' ? 'Ativo' : 'Inativo'; ?>
                        </span>
                        
                        <?php if ($has_active_subscription): ?>
                            <span class="badge bg-success ms-2">Assinante</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-meta">
                        <div class="meta-item">
                            <span class="meta-label">Nome de usuário</span>
                            <span class="meta-value"><?php echo $user['username']; ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Email</span>
                            <span class="meta-value"><?php echo $user['email']; ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Membro desde</span>
                            <span class="meta-value"><?php echo $registration_date; ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Último acesso</span>
                            <span class="meta-value"><?php echo $last_login; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Editar Perfil -->
                <div class="col-lg-8">
                    <div class="settings-card">
                        <div class="settings-header">
                            <h3><i class="fas fa-user-edit"></i> Editar Perfil</h3>
                        </div>
                        <div class="settings-body">
                            <form action="profile.php" method="POST" enctype="multipart/form-data">
                                <!-- Adicionar este bloco para upload de avatar -->
                                <div class="mb-4 text-center">
                                    <div class="avatar-upload">
                                        <div class="avatar-preview">
                                            <img src="<?php echo !empty($user['avatar_url']) ? '../uploads/avatars/' . $user['avatar_url'] : '../assets/images/avatar.png'; ?>" 
                                                 alt="Avatar" id="avatarPreview" class="img-fluid">
                                        </div>
                                        <div class="avatar-edit mt-3">
                                            <label for="avatar" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-camera me-2"></i> Alterar foto
                                            </label>
                                            <input type="file" id="avatar" name="avatar" accept="image/png, image/jpeg, image/jpg" class="d-none">
                                            <?php if (!empty($user['avatar_url'])): ?>
                                            <button type="button" id="removeAvatar" class="btn btn-sm btn-outline-danger ms-2">
                                                <i class="fas fa-trash me-2"></i> Remover
                                            </button>
                                            <input type="hidden" name="remove_avatar" id="removeAvatarInput" value="0">
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted d-block mt-2">Tamanho máximo: 2MB. Formatos: JPG, PNG</small>
                                    </div>
                                </div>
                                <!-- Continuação do formulário existente -->
                                <div class="row mb-4">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">Nome</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">Sobrenome</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="discord_id" class="form-label">ID do Discord</label>
                                    <input type="text" class="form-control" id="discord_id" name="discord_id" 
                                           value="<?php echo htmlspecialchars($user['discord_id'] ?? ''); ?>"
                                           placeholder="Seu ID numérico do Discord (ex: 1234567890123456)">
                                    <small class="form-text text-muted">Para encontrar seu ID do Discord, ative o modo desenvolvedor e clique com o botão direito em seu perfil</small>
                                </div>
                                
                                <h4 class="text-primary mb-3 mt-4">Alterar Senha</h4>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="current_password" class="form-label">Senha Atual</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="new_password" class="form-label">Nova Senha</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="confirm_password" class="form-label">Confirmar Nova Senha</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                </div>
                                <small class="form-text text-muted mb-4 d-block">Deixe os campos de senha em branco se não deseja alterá-la</small>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary btn-glow">
                                        <i class="fas fa-save me-2"></i> Salvar Alterações
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Atividades Recentes -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h3><i class="fas fa-history"></i> Atividades Recentes</h3>
                        </div>
                        <div class="settings-body">
                            <ul class="activity-log">
                                <?php if (empty($recent_activities)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                        <p>Nenhuma atividade recente encontrada.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <li class="activity-item">
                                            <div class="activity-icon">
                                                <?php
                                                $icon = 'fa-history';
                                                if ($activity['action'] === 'login') {
                                                    $icon = 'fa-sign-in-alt';
                                                } elseif ($activity['action'] === 'download') {
                                                    $icon = 'fa-download';
                                                } elseif ($activity['action'] === 'profile_update') {
                                                    $icon = 'fa-user-edit';
                                                } elseif ($activity['action'] === 'subscription_purchase') {
                                                    $icon = 'fa-credit-card';
                                                }
                                                ?>
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-text">
                                                    <?php
                                                    switch ($activity['action']) {
                                                        case 'login':
                                                            echo 'Login realizado com sucesso';
                                                            break;
                                                        case 'download':
                                                            $cheat_name = 'um cheat';
                                                            if ($activity['cheat_id']) {
                                                                $stmt = $db->prepare("SELECT name FROM cheats WHERE id = ?");
                                                                $stmt->execute([$activity['cheat_id']]);
                                                                $cheat = $stmt->fetch(PDO::FETCH_ASSOC);
                                                                if ($cheat) {
                                                                    $cheat_name = htmlspecialchars($cheat['name']);
                                                                }
                                                            }
                                                            echo "Download de $cheat_name";
                                                            break;
                                                        case 'profile_update':
                                                            echo 'Perfil atualizado';
                                                            break;
                                                        case 'subscription_purchase':
                                                            echo 'Nova assinatura adquirida';
                                                            break;
                                                        default:
                                                            echo htmlspecialchars($activity['action']);
                                                    }
                                                    ?>
                                                </div>
                                                <div class="activity-meta">
                                                    <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></span>
                                                    <span><i class="fas fa-globe"></i> <?php echo $activity['ip_address']; ?></span>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                            
                            <?php if (!empty($recent_activities)): ?>
                                <div class="mt-3 d-flex justify-content-center">
                                    <a href="activity_log.php" class="btn btn-outline-primary">
                                        <i class="fas fa-list me-2"></i> Ver Todas as Atividades
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Estatísticas do Usuário -->
                <div class="col-lg-4">
                    <div class="settings-card mb-4">
                        <div class="settings-header">
                            <h3><i class="fas fa-chart-bar"></i> Estatísticas</h3>
                        </div>
                        <div class="settings-body">
                            <div class="stat-card mb-3">
                                <div class="stat-icon">
                                    <i class="fas fa-download"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?php echo $download_count; ?></h3>
                                    <p>Downloads</p>
                                </div>
                            </div>
                            
                            <div class="stat-card mb-3">
                                <div class="stat-icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?php echo $active_subscriptions; ?></h3>
                                    <p>Assinaturas Ativas</p>
                                </div>
                            </div>
                            
                            <?php if ($has_active_subscription): ?>
                                <?php
                                // Obter detalhes da assinatura ativa
                                $stmt = $db->prepare("
                                    SELECT s.*, p.name as plan_name 
                                    FROM user_subscriptions s
                                    JOIN cheat_subscription_plans p ON s.cheat_plan_id = p.id
                                    WHERE s.user_id = ? AND s.status = 'active' AND s.end_date > NOW()
                                    ORDER BY s.end_date DESC
                                    LIMIT 1
                                ");
                                $stmt->execute([$user_id]);
                                $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($subscription):
                                    // Calcular dias restantes
                                    $now = new DateTime();
                                    $end_date = new DateTime($subscription['end_date']);
                                    $days_remaining = $now->diff($end_date)->days;
                                ?>
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo $days_remaining; ?></h3>
                                        <p>Dias Restantes na Assinatura</p>
                                    </div>
                                </div>
                                
                                <div class="alert alert-primary mt-4">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-info-circle fa-2x me-3"></i>
                                        <div>
                                            <h5 class="alert-heading">Assinatura Ativa</h5>
                                            <p class="mb-0"><?php echo htmlspecialchars($subscription['plan_name']); ?></p>
                                            <small>Válida até: <?php echo date('d/m/Y H:i', strtotime($subscription['end_date'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-warning mt-4">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                                        <div>
                                            <h5 class="alert-heading">Sem assinatura ativa</h5>
                                            <p class="mb-0">Adquira uma assinatura para ter acesso aos nossos cheats premium.</p>
                                            <a href="purchases.php" class="btn btn-sm btn-primary mt-2">Ver Planos</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Ações Rápidas -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h3><i class="fas fa-bolt"></i> Ações Rápidas</h3>
                        </div>
                        <div class="settings-body">
                            <div class="d-grid gap-3">
                                <a href="purchases.php" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart me-2"></i> Adquirir Plano
                                </a>
                                <a href="downloads.php" class="btn btn-outline-primary">
                                    <i class="fas fa-download me-2"></i> Meus Downloads
                                </a>
                                <a href="support.php" class="btn btn-outline-primary">
                                    <i class="fas fa-headset me-2"></i> Suporte Técnico
                                </a>
                                <a href="../includes/logout.php" class="btn btn-outline-danger">
                                    <i class="fas fa-sign-out-alt me-2"></i> Sair da Conta
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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

            // Remover avatar
            const removeAvatarButton = document.getElementById('removeAvatar');
            const removeAvatarInput = document.getElementById('removeAvatarInput');
            const avatarPreview = document.getElementById('avatarPreview');

            if (removeAvatarButton && removeAvatarInput && avatarPreview) {
                removeAvatarButton.addEventListener('click', function() {
                    removeAvatarInput.value = '1';
                    avatarPreview.src = '../assets/images/avatar.png';
                });
            }

            // Código para visualização prévia do avatar
            const avatarInput = document.getElementById('avatar');
            if (avatarInput && avatarPreview) {
                avatarInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const file = this.files[0];
                        
                        // Verificar tamanho do arquivo
                        const maxSize = 2 * 1024 * 1024; // 2MB
                        if (file.size > maxSize) {
                            showNotification('O tamanho do arquivo excede o limite de 2MB.', 'error');
                            this.value = '';
                            return;
                        }
                        
                        // Verificar tipo do arquivo
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                        if (!allowedTypes.includes(file.type)) {
                            showNotification('Tipo de arquivo não permitido. Por favor, envie uma imagem JPG ou PNG.', 'error');
                            this.value = '';
                            return;
                        }
                        
                        // Mostrar prévia
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            avatarPreview.src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                        
                        // Se estava marcado para remoção, desmarcar
                        if (removeAvatarInput) {
                            removeAvatarInput.value = '0';
                        }
                        
                        showNotification('Imagem selecionada. Clique em "Salvar Alterações" para confirmar.', 'info');
                    }
                });
            }
            
            // Remover avatar
            if (removeAvatarButton && removeAvatarInput) {
                removeAvatarButton.addEventListener('click', function() {
                    // Definir a imagem padrão
                    avatarPreview.src = '../assets/images/avatar.png';
                    // Limpar o input de arquivo
                    if (avatarInput) {
                        avatarInput.value = '';
                    }
                    // Marcar para remoção
                    removeAvatarInput.value = '1';
                    
                    showNotification('Avatar será removido quando você salvar as alterações.', 'warning');
                });
            }
        });
    </script>

<script>
        // Substitua o código JavaScript atual para o menu do usuário
        document.addEventListener('DOMContentLoaded', function() {
            // Mostrar/Esconder o menu do usuário
            const userMenu = document.querySelector('.user-info');
            const userDropdown = document.querySelector('.user-dropdown');

            if (userMenu && userDropdown) {
                userMenu.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                    console.log('User menu clicked, dropdown toggled');
                });

                // Fechar ao clicar fora
                document.addEventListener('click', function(event) {
                    if (!event.target.closest('.user-menu')) {
                        userDropdown.classList.remove('active');
                    }
                });
            }

            // Filtros de planos
            const filterButtons = document.querySelectorAll('[data-filter]');
            const filterSections = document.querySelectorAll('.filter-section');

            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');

                    // Ativar botão
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    // Filtrar seções
                    if (filter === 'all') {
                        filterSections.forEach(section => section.style.display = 'block');
                    } else {
                        filterSections.forEach(section => {
                            if (section.getAttribute('data-game') === filter) {
                                section.style.display = 'block';
                            } else {
                                section.style.display = 'none';
                            }
                        });
                    }
                });
            });

            // Remover o loader quando a página estiver pronta
            setTimeout(function() {
                document.querySelector('.loading').style.display = 'none';
            }, 1500);

            <?php if (isset($_SESSION['login_success'])): ?>
                showNotification("Login realizado com sucesso!", "success");
                <?php unset($_SESSION['login_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['subscription_success'])): ?>
                showNotification("<?php echo $_SESSION['subscription_success']; ?>", "success");
                <?php unset($_SESSION['subscription_success']); ?>
            <?php endif; ?>

            // Adicione este código após o DOMContentLoaded existente
            console.log('Dashboard script loaded');
            document.querySelector('.user-info')?.addEventListener('click', function() {
                console.log('User menu clicked manually');
            });
        });

        // Solução alternativa se o dropdown ainda não funcionar
        document.addEventListener('DOMContentLoaded', function() {
            // Adicionar botão auxiliar para teste
            const userMenu = document.querySelector('.user-info');
            if (userMenu) {
                userMenu.addEventListener('click', function() {
                    const dropdown = document.querySelector('.user-dropdown');
                    if (dropdown) {
                        // Forçar estado visível com estilo inline
                        if (dropdown.style.visibility === 'visible') {
                            dropdown.style.visibility = 'hidden';
                            dropdown.style.opacity = '0';
                        } else {
                            dropdown.style.visibility = 'visible';
                            dropdown.style.opacity = '1';
                            dropdown.style.display = 'block';
                            dropdown.style.zIndex = '9999';
                        }
                    }
                });
            }
        });
    </script>

    <script>
        // Script específico apenas para o dropdown do usuário
        document.addEventListener('DOMContentLoaded', function() {
            // Armazenar o dropdown e o botão
            const userDropdown = document.querySelector('.user-dropdown');
            const userInfo = document.querySelector('.user-info');

            // Verificar se os elementos foram encontrados
            if (!userDropdown || !userInfo) {
                console.error('Elementos não encontrados');
                return;
            }

            console.log('Elementos encontrados, adicionando listeners');

            // Aplicar um z-index alto para garantir que apareça acima de outros elementos
            userDropdown.style.zIndex = "9999";

            // Adicionar listener para o clique no ícone do usuário
            userInfo.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Toggle manual da classe active e inline styles
                if (userDropdown.classList.contains('active')) {
                    userDropdown.classList.remove('active');
                    userDropdown.style.opacity = "0";
                    userDropdown.style.visibility = "hidden";
                    userDropdown.style.transform = "translateY(10px)";
                    userDropdown.style.pointerEvents = "none";
                } else {
                    userDropdown.classList.add('active');
                    userDropdown.style.opacity = "1";
                    userDropdown.style.visibility = "visible";
                    userDropdown.style.transform = "translateY(0)";
                    userDropdown.style.pointerEvents = "all";
                }

                console.log('Dropdown toggled:', userDropdown.classList.contains('active'));
            });

            // Fechar ao clicar fora
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.user-menu') && userDropdown.classList.contains('active')) {
                    userDropdown.classList.remove('active');
                    userDropdown.style.opacity = "0";
                    userDropdown.style.visibility = "hidden";
                    userDropdown.style.transform = "translateY(10px)";
                    userDropdown.style.pointerEvents = "none";
                    console.log('Dropdown fechado pelo clique externo');
                }
            });
        });
    </script>
</body>

</html>