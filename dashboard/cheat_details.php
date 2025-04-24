<?php
// filepath: c:\xampp\htdocs\Gestaocheats\dashboard\cheat_details.php
define('INCLUDED_FROM_INDEX', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Verificar se a coluna já existe e, se não existir, adicioná-la
$db->exec("
    ALTER TABLE `cheats` 
    ADD COLUMN IF NOT EXISTS `is_detected` TINYINT(1) NOT NULL DEFAULT 0 
    COMMENT 'Indica se o cheat foi detectado (1) ou não (0)'
");

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

// Verificar se o ID do cheat foi fornecido
$cheat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$cheat_id) {
    set_flash_message('error', 'ID do cheat inválido!');
    redirect('index.php');
}

// Verificar se o usuário tem acesso a este cheat
$stmt = $db->prepare("
    SELECT COUNT(*) FROM user_subscriptions us
    JOIN cheat_subscription_plans csp ON us.cheat_plan_id = csp.id
    WHERE us.user_id = ? 
    AND us.status = 'active' 
    AND us.end_date > NOW()
    AND csp.cheat_id = ?
");
$stmt->execute([$user_id, $cheat_id]);
$has_access = $stmt->fetchColumn();

if (!$has_access) {
    set_flash_message('error', 'Você não tem acesso a este cheat. Por favor, adquira uma assinatura.');
    redirect('index.php');
}

// Buscar detalhes do cheat
$stmt = $db->prepare("
    SELECT 
        c.*,
        g.name as game_name,
        g.image as game_image,
        (SELECT COUNT(*) FROM user_downloads WHERE cheat_id = c.id) as total_downloads,
        (SELECT COUNT(*) FROM user_downloads WHERE cheat_id = c.id AND user_id = ?) as user_downloads,
        (SELECT MAX(created_at) FROM cheat_update_logs WHERE cheat_id = c.id) as last_update_date
    FROM cheats c
    JOIN games g ON c.game_id = g.id
    WHERE c.id = ? AND c.is_active = 1
");
$stmt->execute([$user_id, $cheat_id]);
$cheat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cheat) {
    set_flash_message('error', 'Cheat não encontrado ou não está ativo.');
    redirect('index.php');
}

// Buscar histórico de atualizações
try {
    $stmt = $db->prepare("
        SELECT 
            cul.version,
            cul.changes,
            cul.created_at
        FROM cheat_update_logs cul
        WHERE cul.cheat_id = ?
        ORDER BY cul.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$cheat_id]);
    $update_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $update_logs = [];
    error_log("Error fetching update logs: " . $e->getMessage());
}

// Buscar recursos do cheat
$stmt = $db->prepare("
    SELECT 
        cf.name,
        cf.description,
        cf.category
    FROM cheat_features cf
    WHERE cf.cheat_id = ?
    ORDER BY cf.category, cf.name
");
$stmt->execute([$cheat_id]);
$features = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar recursos por categoria
$categorized_features = [];
foreach ($features as $feature) {
    $category = $feature['category'] ?: 'Outros';
    if (!isset($categorized_features[$category])) {
        $categorized_features[$category] = [];
    }
    $categorized_features[$category][] = [
        'name' => $feature['name'],
        'description' => $feature['description']
    ];
}

// Buscar planos de assinatura disponíveis para este cheat
$stmt = $db->prepare("
    SELECT 
        csp.id,
        csp.name,
        csp.description,
        csp.price,
        csp.duration_days,
        csp.features,
        csp.is_popular,
        csp.hwid_protection
    FROM cheat_subscription_plans csp
    WHERE csp.cheat_id = ? AND csp.is_active = 1
    ORDER BY csp.price ASC
");
$stmt->execute([$cheat_id]);
$subscription_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar requisitos de sistema
$stmt = $db->prepare("
    SELECT 
        csr.requirement_type,
        csr.minimum,
        csr.recommended
    FROM cheat_system_requirements csr
    WHERE csr.cheat_id = ?
");
$stmt->execute([$cheat_id]);
$system_requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar screenshots do cheat
$stmt = $db->prepare("
    SELECT 
        cs.id,
        cs.image,
        cs.caption
    FROM cheat_screenshots cs
    WHERE cs.cheat_id = ?
    ORDER BY cs.display_order
");
$stmt->execute([$cheat_id]);
$screenshots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se o usuário já baixou este cheat
$stmt = $db->prepare("
    SELECT COUNT(*) FROM user_downloads 
    WHERE user_id = ? AND cheat_id = ?
");
$stmt->execute([$user_id, $cheat_id]);
$has_downloaded = (bool)$stmt->fetchColumn();

// Último download do usuário, se houver
$stmt = $db->prepare("
    SELECT created_at 
    FROM user_downloads 
    WHERE user_id = ? AND cheat_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$user_id, $cheat_id]);
$last_download = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($cheat['name']); ?> - <?php echo SITE_NAME; ?></title>
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
    <link rel="stylesheet" href="../assets/css/details.css">
    <link rel="stylesheet" href="../assets/css/scroll.css">
    <!-- Fontes adicionais -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap">
    <!-- Splide.js para carrossel de imagens -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@splidejs/splide@latest/dist/css/splide.min.css">
</head>
<body class="dashboard-page">
    <!-- Loading Spinner - Melhorado com logo -->
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
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($cheat['name']); ?></li>
                </ol>
            </nav>

            <!-- Cheat Header -->
            <div class="cheat-header">
                <span class="game-badge"><?php echo htmlspecialchars($cheat['game_name']); ?></span>
                <h1>
                    <?php echo htmlspecialchars($cheat['name']); ?>
                    <span class="version">v<?php echo $cheat['version']; ?></span>
                </h1>
                <div class="cheat-meta">
                    <div class="meta-item">
                        <i class="fas fa-download"></i>
                        <span><?php echo number_format($cheat['total_downloads']); ?> downloads</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Atualizado em <?php echo date('d/m/Y', strtotime($cheat['last_update_date'] ?? $cheat['updated_at'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-code-branch"></i>
                        <span>Versão <?php echo $cheat['version']; ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-shield-alt"></i>
                        <span><?php echo isset($cheat['is_detected']) && $cheat['is_detected'] ? 'Detectado' : 'Não detectado'; ?></span>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="actions">
                            <a href="download.php?id=<?php echo $cheat_id; ?>" class="btn btn-primary btn-lg download-btn">
                                <i class="fas fa-download"></i> Baixar Agora
                            </a>
                            <?php if ($has_downloaded): ?>
                                <p class="mt-2 text-muted">
                                    <small>Você já baixou este cheat <?php echo $last_download ? 'em ' . date('d/m/Y H:i', strtotime($last_download)) : ''; ?></small>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="#update-history" class="btn btn-outline-primary ms-2">
                            <i class="fas fa-history"></i> Atualizações
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Imagem Principal do Cheat -->
                    <?php if (!empty($cheat['image'])): ?>
                        <img src="../assets/images/cheats/<?php echo htmlspecialchars($cheat['image']); ?>" alt="<?php echo htmlspecialchars($cheat['name']); ?>" class="cheat-image-main">
                    <?php else: ?>
                        <img src="../assets/images/defaults/cheat-default.jpg" alt="<?php echo htmlspecialchars($cheat['name']); ?>" class="cheat-image-main">
                    <?php endif; ?>

                    <!-- Descrição do Cheat -->
                    <div class="cheat-description">
                        <?php echo nl2br(htmlspecialchars($cheat['description'])); ?>
                    </div>

                    <!-- Screenshots do Cheat -->
                    <?php if (!empty($screenshots)): ?>
                        <div class="info-section screenshots-section">
                            <h3><i class="fas fa-images"></i> Screenshots</h3>
                            <div class="splide">
                                <div class="splide__track">
                                    <ul class="splide__list">
                                        <?php foreach ($screenshots as $screenshot): ?>
                                            <li class="splide__slide">
                                                <div class="screenshot" data-bs-toggle="modal" data-bs-target="#screenshotModal" data-bs-image="../assets/images/screenshots/<?php echo htmlspecialchars($screenshot['image']); ?>">
                                                    <img src="../assets/images/screenshots/<?php echo htmlspecialchars($screenshot['image']); ?>" alt="<?php echo htmlspecialchars($screenshot['caption']); ?>">
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Recursos do Cheat -->
                    <?php if (!empty($categorized_features)): ?>
                        <div class="info-section" id="features">
                            <h3><i class="fas fa-list-check"></i> Recursos</h3>
                            
                            <?php foreach ($categorized_features as $category => $features): ?>
                                <h4 class="mb-3"><?php echo htmlspecialchars($category); ?></h4>
                                <div class="features-list mb-4">
                                    <?php foreach ($features as $feature): ?>
                                        <div class="feature-item">
                                            <h4><?php echo htmlspecialchars($feature['name']); ?></h4>
                                            <?php if (!empty($feature['description'])): ?>
                                                <p><?php echo htmlspecialchars($feature['description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Requisitos de Sistema -->
                    <?php if (!empty($system_requirements)): ?>
                        <div class="info-section" id="system-requirements">
                            <h3><i class="fas fa-microchip"></i> Requisitos de Sistema</h3>
                            
                            <div class="system-requirements">
                                <div class="requirements-column">
                                    <h4>Mínimos</h4>
                                    <ul>
                                        <?php foreach ($system_requirements as $req): ?>
                                            <li>
                                                <span class="req-label"><?php echo htmlspecialchars($req['requirement_type']); ?>:</span>
                                                <span class="req-value"><?php echo htmlspecialchars($req['minimum']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                
                                <div class="requirements-column">
                                    <h4>Recomendados</h4>
                                    <ul>
                                        <?php foreach ($system_requirements as $req): ?>
                                            <li>
                                                <span class="req-label"><?php echo htmlspecialchars($req['requirement_type']); ?>:</span>
                                                <span class="req-value"><?php echo htmlspecialchars($req['recommended']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Instruções e Informações Adicionais -->
                    <?php if (!empty($cheat['instructions'])): ?>
                        <div class="info-section">
                            <h3><i class="fas fa-circle-info"></i> Como usar</h3>
                            <div class="instructions">
                                <?php echo nl2br(htmlspecialchars($cheat['instructions'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Histórico de Atualizações -->
                    <?php if (!empty($update_logs)): ?>
                        <div class="info-section" id="update-history">
                            <h3><i class="fas fa-history"></i> Histórico de Atualizações</h3>
                            
                            <ul class="update-list">
                                <?php foreach ($update_logs as $log): ?>
                                    <li class="update-item">
                                        <div class="update-version">Versão <?php echo htmlspecialchars($log['version']); ?></div>
                                        <div class="update-date"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></div>
                                        <p class="update-changes"><?php echo htmlspecialchars($log['changes']); ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <!-- Sidebar Card: Informações de Download -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-download"></i> Informações de Download</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Status:</span>
                                    <?php if (isset($cheat['is_detected']) && $cheat['is_detected']): ?>
                                        <span class="badge bg-warning">Detectado</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Não Detectado</span>
                                    <?php endif; ?>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Versão:</span>
                                    <span><?php echo $cheat['version']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Última Atualização:</span>
                                    <span><?php echo date('d/m/Y', strtotime($cheat['last_update_date'] ?? $cheat['updated_at'])); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Downloads:</span>
                                    <span><?php echo number_format($cheat['total_downloads']); ?></span>
                                </li>
                                <?php if ($has_downloaded): ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Seus Downloads:</span>
                                    <span><?php echo number_format($cheat['user_downloads']); ?></span>
                                </li>
                                <?php endif; ?>
                            </ul>
                            <div class="mt-3 d-grid">
                                <a href="download.php?id=<?php echo $cheat_id; ?>" class="btn btn-primary btn-lg">
                                    <i class="fas fa-download"></i> Baixar Agora
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Card: Planos de Assinatura -->
                    <?php if (!empty($subscription_plans)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-tag"></i> Planos Disponíveis</h5>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($subscription_plans as $plan): ?>
                                        <li class="list-group-item <?php echo $plan['is_popular'] ? 'popular' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($plan['name']); ?></strong>
                                                    <div><?php echo $plan['duration_days']; ?> dias</div>
                                                </div>
                                                <span class="badge bg-primary">R$ <?php echo number_format($plan['price'], 2, ',', '.'); ?></span>
                                            </div>
                                            <?php if ($plan['is_popular']): ?>
                                                <div class="mt-2">
                                                    <span class="badge bg-warning">Mais Popular</span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <a href="checkout.php?plan=<?php echo $plan['id']; ?>" class="btn btn-sm btn-outline-primary">Assinar</a>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Sidebar Card: Precisa de ajuda? -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-question-circle"></i> Precisa de ajuda?</h5>
                        </div>
                        <div class="card-body">
                            <p>Está com problemas para usar este cheat? Nossa equipe de suporte está disponível para ajudar.</p>
                            <a href="support.php?cheat_id=<?php echo $cheat_id; ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-headset"></i> Contatar Suporte
                            </a>
                        </div>
                    </div>

                    <!-- Sidebar Card: Voltar para área de cheats -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <a href="index.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-arrow-left"></i> Voltar para Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal para exibir screenshots em tamanho maior -->
    <div class="modal fade" id="screenshotModal" tabindex="-1" aria-labelledby="screenshotModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="screenshotModalLabel">Screenshot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-screenshot">
                        <img src="" id="modalImage" alt="Screenshot em tamanho completo">
                    </div>
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
    <script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@latest/dist/js/splide.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar Splide (carrossel)
            if (document.querySelector('.splide')) {
                new Splide('.splide', {
                    perPage: 3,
                    gap: '20px',
                    breakpoints: {
                        768: {
                            perPage: 2,
                        },
                        480: {
                            perPage: 1,
                        },
                    }
                }).mount();
            }

            // Modal para screenshots
            const screenshotModal = document.getElementById('screenshotModal');
            if (screenshotModal) {
                screenshotModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const imageUrl = button.getAttribute('data-bs-image');
                    const modalImage = document.getElementById('modalImage');
                    modalImage.src = imageUrl;
                });
            }

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

            // Animação de rolagem suave para âncoras
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        window.scrollTo({
                            top: target.offsetTop - 100,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Remover o loader quando a página estiver pronta
            setTimeout(function() {
                document.querySelector('.loading').style.display = 'none';
            }, 1500);
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
</body>
</html>