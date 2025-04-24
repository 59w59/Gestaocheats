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

// Verificar se o usuário tem assinatura ativa
$stmt = $db->prepare("
    SELECT COUNT(*) FROM user_subscriptions 
    WHERE user_id = ? AND status = 'active' AND end_date > NOW()
");
$stmt->execute([$user_id]);
$has_active_subscription = (bool)$stmt->fetchColumn();

// Identificar qual tutorial mostrar (installation ou troubleshooting)
$tutorial_type = isset($_GET['type']) ? $_GET['type'] : 'installation';
$valid_types = ['installation', 'troubleshooting'];
if (!in_array($tutorial_type, $valid_types)) {
    $tutorial_type = 'installation';
}

// Obter tutoriais do banco de dados se necessário
$tutorials = [];
if ($has_active_subscription) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM tutorials 
            WHERE type = ? AND active = 1
            ORDER BY display_order ASC
        ");
        $stmt->execute([$tutorial_type]);
        $tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching tutorials: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $tutorial_type == 'installation' ? 'Como Instalar os Cheats' : 'Solução de Problemas Comuns'; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon/favicon-16x16.png">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/support.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <link rel="stylesheet" href="../assets/css/scroll.css">
    
    <style>
        /* Estilos específicos para a página de tutoriais */
        .tutorials-container {
            padding-top: 20px;
            padding-bottom: 40px;
        }
        
        .tutorial-nav {
            background: linear-gradient(135deg, rgba(0, 30, 40, 0.4), rgba(0, 20, 30, 0.5));
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            border: 1px solid var(--border);
        }
        
        .tutorial-nav ul {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 15px;
        }
        
        .tutorial-nav li a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 8px;
            color: var(--text);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .tutorial-nav li a:hover {
            background: rgba(0, 207, 155, 0.1);
            color: var(--primary);
        }
        
        .tutorial-nav li a.active {
            background: rgba(0, 207, 155, 0.2);
            color: var(--primary);
            font-weight: 500;
        }
        
        .tutorial-card {
            background: linear-gradient(135deg, rgba(0, 30, 40, 0.4), rgba(0, 20, 30, 0.5));
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            transition: all 0.3s;
        }
        
        .tutorial-card:hover {
            border-color: var(--primary-alpha-30);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2), 0 0 15px rgba(0, 207, 155, 0.1);
            transform: translateY(-3px);
        }
        
        .tutorial-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(to right, var(--primary), var(--primary-alpha-10));
            opacity: 0.8;
        }
        
        .tutorial-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 1.4rem;
        }
        
        .tutorial-video {
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            padding-top: 56.25%; /* 16:9 Aspect Ratio */
            margin-bottom: 20px;
            background-color: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border);
        }
        
        .tutorial-video iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .step-by-step {
            counter-reset: step-counter;
            margin-bottom: 20px;
        }
        
        .step-item {
            position: relative;
            padding: 15px 15px 15px 50px;
            background: rgba(0, 20, 30, 0.3);
            border-radius: 8px;
            margin-bottom: 15px;
            counter-increment: step-counter;
        }
        
        .step-item::before {
            content: counter(step-counter);
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 26px;
            height: 26px;
            background: var(--primary);
            color: var(--dark);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .step-item h4 {
            margin-bottom: 10px;
            color: var(--text);
            font-size: 1.1rem;
        }
        
        .step-item p {
            margin-bottom: 5px;
            color: var(--text-secondary);
        }
        
        .step-item .step-image {
            max-width: 100%;
            border-radius: 6px;
            margin: 10px 0;
            border: 1px solid var(--border);
        }
        
        .faq-section {
            margin-top: 30px;
        }
        
        .faq-item {
            margin-bottom: 15px;
            background: rgba(0, 20, 30, 0.3);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        
        .faq-question {
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text);
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .faq-question:hover {
            background: rgba(0, 207, 155, 0.1);
        }
        
        .faq-question i {
            transition: all 0.3s;
            color: var(--primary);
        }
        
        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .faq-item.active .faq-question {
            background: rgba(0, 207, 155, 0.1);
        }
        
        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }
        
        .faq-item.active .faq-answer {
            padding: 15px 20px;
            max-height: 500px;
        }
        
        .tutorial-note {
            background: rgba(9, 132, 227, 0.1);
            border-left: 3px solid #0984e3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        
        .tutorial-warning {
            background: rgba(241, 196, 15, 0.1);
            border-left: 3px solid #f1c40f;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        
        .tutorial-danger {
            background: rgba(231, 76, 60, 0.1);
            border-left: 3px solid #e74c3c;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        
        @media (max-width: 768px) {
            .tutorial-nav ul {
                flex-direction: column;
                gap: 8px;
            }
            
            .tutorial-card {
                padding: 20px;
            }
            
            .step-item {
                padding: 12px 12px 12px 45px;
            }
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
                <h1><?php echo $tutorial_type == 'installation' ? 'Como Instalar os Cheats' : 'Solução de Problemas Comuns'; ?></h1>
                <p><?php echo $tutorial_type == 'installation' ? 'Guia passo a passo para instalação e configuração dos nossos cheats' : 'Guia para solucionar os problemas mais comuns'; ?></p>
            </div>

            <!-- Navigation between tutorial types -->
            <div class="tutorial-nav">
                <ul>
                    <li>
                        <a href="tutorials.php?type=installation" class="<?php echo $tutorial_type == 'installation' ? 'active' : ''; ?>">
                            <i class="fas fa-download"></i> Como Instalar os Cheats
                        </a>
                    </li>
                    <li>
                        <a href="tutorials.php?type=troubleshooting" class="<?php echo $tutorial_type == 'troubleshooting' ? 'active' : ''; ?>">
                            <i class="fas fa-wrench"></i> Solução de Problemas
                        </a>
                    </li>
                    <li>
                        <a href="../pages/faq.php">
                            <i class="fas fa-question-circle"></i> FAQ
                        </a>
                    </li>
                    <li>
                        <a href="support.php">
                            <i class="fas fa-headset"></i> Suporte
                        </a>
                    </li>
                </ul>
            </div>

            <?php if ($has_active_subscription): ?>
                <div class="tutorials-container">
                    <?php if ($tutorial_type == 'installation'): ?>
                    
                        <!-- Tutorial para Instalação -->
                        <div class="tutorial-card">
                            <h3><i class="fas fa-play-circle"></i> Vídeo Tutorial de Instalação</h3>
                            
                            <div class="tutorial-video">
                                <iframe src="https://www.youtube.com/embed/VIDEO_ID" title="Tutorial de Instalação" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                            </div>
                            
                            <h3><i class="fas fa-list-ol"></i> Guia de Instalação Passo a Passo</h3>
                            
                            <div class="step-by-step">
                                <div class="step-item">
                                    <h4>Download do Arquivo</h4>
                                    <p>Acesse a página <a href="downloads.php" class="text-primary">Meus Downloads</a> e baixe o arquivo do cheat correspondente ao jogo que você deseja usar.</p>
                                    <img src="../assets/images/tutorials/download-step.jpg" alt="Página de downloads" class="step-image">
                                </div>
                                
                                <div class="step-item">
                                    <h4>Extraia os Arquivos</h4>
                                    <p>Extraia o arquivo ZIP baixado para uma pasta de sua preferência. Recomendamos o uso do WinRAR ou 7-Zip para extração.</p>
                                    <p>A senha padrão para extração é: <code>gestaocheats</code></p>
                                </div>
                                
                                <div class="step-item">
                                    <h4>Desative o Antivírus</h4>
                                    <p>Temporariamente desative seu antivírus para evitar falsos positivos durante a instalação.</p>
                                    
                                    <div class="tutorial-warning">
                                        <h5><i class="fas fa-exclamation-triangle"></i> Importante</h5>
                                        <p>Os cheats podem ser detectados como falsos positivos pelo seu antivírus. Isso é normal e acontece porque os cheats precisam modificar a memória do jogo.</p>
                                    </div>
                                </div>
                                
                                <div class="step-item">
                                    <h4>Execute o Instalador</h4>
                                    <p>Execute o arquivo <code>Setup.exe</code> como administrador. Isto é necessário para conceder as permissões adequadas.</p>
                                    <p>Siga as instruções do instalador e aguarde o processo ser concluído.</p>
                                </div>
                                
                                <div class="step-item">
                                    <h4>Execute o Launcher</h4>
                                    <p>Após a instalação, execute o <code>Launcher.exe</code> como administrador. Faça login com suas credenciais da plataforma.</p>
                                    <img src="../assets/images/tutorials/launcher.jpg" alt="Launcher" class="step-image">
                                </div>
                                
                                <div class="step-item">
                                    <h4>Inicie o Jogo</h4>
                                    <p>Com o launcher aberto, inicie o jogo normalmente.</p>
                                </div>
                                
                                <div class="step-item">
                                    <h4>Ative o Cheat</h4>
                                    <p>Uma vez que o jogo esteja aberto, pressione a tecla <kbd>Insert</kbd> ou <kbd>Home</kbd> para abrir o menu do cheat.</p>
                                    <p>Configure as opções desejadas e comece a jogar!</p>
                                </div>
                            </div>
                            
                            <div class="tutorial-note">
                                <h5><i class="fas fa-info-circle"></i> Nota</h5>
                                <p>Para cada jogo diferente, pode haver pequenas variações no processo de instalação e uso. Consulte a documentação específica do cheat que você adquiriu para mais detalhes.</p>
                            </div>
                            
                            <h3><i class="fas fa-question-circle"></i> Perguntas Frequentes</h3>
                            
                            <div class="faq-section">
                                <div class="faq-item">
                                    <div class="faq-question">
                                        <span>O jogo fecha quando inicio o cheat. O que fazer?</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div class="faq-answer">
                                        <p>Isso pode acontecer por várias razões:</p>
                                        <ul>
                                            <li>Verifique se você está executando o launcher como administrador</li>
                                            <li>Certifique-se de que o antivírus está desativado</li>
                                            <li>Verifique se há atualizações recentes do jogo que possam ter afetado a compatibilidade</li>
                                            <li>Reinstale o cheat e tente novamente</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="faq-item">
                                    <div class="faq-question">
                                        <span>Meu antivírus detecta o cheat como um vírus. É seguro?</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div class="faq-answer">
                                        <p>Sim, é seguro. Os cheats precisam interagir com a memória do jogo de maneiras que os antivírus consideram suspeitas. Isso causa falsos positivos.</p>
                                        <p>Você pode adicionar o diretório de instalação às exceções do seu antivírus para evitar que ele bloqueie ou remova os arquivos do cheat.</p>
                                    </div>
                                </div>
                                
                                <div class="faq-item">
                                    <div class="faq-question">
                                        <span>O cheat não funciona após uma atualização do jogo. O que fazer?</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div class="faq-answer">
                                        <p>Após atualizações do jogo, geralmente é necessário que também atualizemos os cheats. Normalmente isso acontece dentro de 24-48 horas após uma atualização importante do jogo.</p>
                                        <p>Verifique a seção de notícias no dashboard ou entre em contato com o suporte para informações sobre o status de atualização.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($tutorial_type == 'troubleshooting'): ?>
                    
                        <!-- Tutorial para Solução de Problemas -->
                        <div class="tutorial-card">
                            <h3><i class="fas fa-tools"></i> Solução de Problemas Comuns</h3>
                            
                            <div class="tutorial-video">
                                <iframe src="https://www.youtube.com/embed/TROUBLESHOOT_VIDEO_ID" title="Tutorial de Solução de Problemas" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                            </div>
                            
                            <h3><i class="fas fa-bug"></i> Problemas Comuns e Soluções</h3>
                            
                            <div class="step-by-step">
                                <div class="step-item">
                                    <h4>Erro: "Failed to connect to server"</h4>
                                    <p>Este erro ocorre quando o launcher não consegue se conectar aos nossos servidores.</p>
                                    <ul>
                                        <li>Verifique sua conexão com a internet</li>
                                        <li>Desative temporariamente seu firewall e verifique novamente</li>
                                        <li>Verifique se nossos servidores não estão em manutenção (confira os avisos no dashboard)</li>
                                    </ul>
                                </div>
                                
                                <div class="step-item">
                                    <h4>Erro: "Game not found" ou "Process not found"</h4>
                                    <p>Este erro ocorre quando o launcher não consegue detectar o jogo em execução.</p>
                                    <ul>
                                        <li>Certifique-se de que o jogo está realmente em execução e completamente carregado</li>
                                        <li>Tente executar tanto o jogo quanto o launcher como administrador</li>
                                        <li>Verifique se você está usando a versão correta do cheat para a versão atual do jogo</li>
                                    </ul>
                                </div>
                                
                                <div class="step-item">
                                    <h4>Erro: "Injection failed" ou "DLL not found"</h4>
                                    <p>Este erro indica problemas na injeção do cheat no processo do jogo.</p>
                                    <ul>
                                        <li>Reinstale o cheat por completo</li>
                                        <li>Verifique se todos os arquivos DLL estão presentes na pasta de instalação</li>
                                        <li>Temporariamente desative seu antivírus, que pode estar bloqueando a injeção</li>
                                        <li>Execute o launcher como administrador</li>
                                    </ul>
                                </div>
                                
                                <div class="step-item">
                                    <h4>Tela Preta ou Crash ao Ativar o Cheat</h4>
                                    <p>Se o jogo fecha ou a tela fica preta quando você ativa o cheat:</p>
                                    <ul>
                                        <li>Verifique se o jogo está em modo janela ou tela cheia borderless (alguns cheats não funcionam bem em tela cheia)</li>
                                        <li>Tente desativar o modo HDR do jogo se estiver ativado</li>
                                        <li>Verifique se seu hardware atende aos requisitos mínimos (especialmente para cheats com ESP/overlays)</li>
                                    </ul>
                                </div>
                                
                                <div class="step-item">
                                    <h4>O Menu do Cheat Não Abre</h4>
                                    <p>Se você não consegue abrir o menu do cheat com a tecla designada:</p>
                                    <ul>
                                        <li>Verifique se o cheat foi injetado com sucesso (deve haver uma mensagem de confirmação)</li>
                                        <li>Tente teclas alternativas (<kbd>Insert</kbd>, <kbd>Home</kbd>, <kbd>Delete</kbd>, ou <kbd>End</kbd>)</li>
                                        <li>Verifique se outros programas não estão capturando a mesma tecla</li>
                                        <li>Tente reiniciar o jogo e o launcher</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <h3><i class="fas fa-desktop"></i> Problemas de Compatibilidade</h3>
                            
                            <div class="tutorial-danger">
                                <h5><i class="fas fa-exclamation-circle"></i> Atenção</h5>
                                <p>Alguns softwares podem interferir no funcionamento dos cheats e causar instabilidade ou crashes:</p>
                                <ul>
                                    <li>MSI Afterburner / Rivatuner</li>
                                    <li>Overlays do Discord, Steam ou Epic Games</li>
                                    <li>Software de gravação como OBS ou Shadowplay</li>
                                    <li>Outros softwares que modificam jogos ou possuem overlays</li>
                                </ul>
                                <p>Recomendamos desativar esses programas antes de usar nossos cheats.</p>
                            </div>
                            
                            <h3><i class="fas fa-shield-alt"></i> Problemas com Antivírus</h3>
                            
                            <div class="step-by-step">
                                <div class="step-item">
                                    <h4>Windows Defender</h4>
                                    <p>Para adicionar uma exceção ao Windows Defender:</p>
                                    <ol>
                                        <li>Abra as Configurações do Windows</li>
                                        <li>Vá para "Atualização e Segurança" > "Segurança do Windows" > "Proteção contra vírus e ameaças"</li>
                                        <li>Em "Configurações de proteção contra vírus e ameaças", clique em "Gerenciar configurações"</li>
                                        <li>Role para baixo até "Exclusões" e clique em "Adicionar ou remover exclusões"</li>
                                        <li>Adicione a pasta onde o cheat está instalado</li>
                                    </ol>
                                </div>
                                
                                <div class="step-item">
                                    <h4>Outros Antivírus</h4>
                                    <p>Para outros antivírus como Avast, AVG, Kaspersky, etc., o processo é semelhante:</p>
                                    <ol>
                                        <li>Abra o painel de controle do antivírus</li>
                                        <li>Encontre a seção de exceções ou exclusões</li>
                                        <li>Adicione a pasta de instalação dos cheats à lista de exclusões</li>
                                    </ol>
                                </div>
                            </div>
                            
                            <h3><i class="fas fa-question-circle"></i> Perguntas Frequentes</h3>
                            
                            <div class="faq-section">
                                <div class="faq-item">
                                    <div class="faq-question">
                                        <span>Posso ser banido por usar os cheats?</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div class="faq-answer">
                                        <p>Todos os nossos cheats possuem proteções anti-detecção, mas nenhum cheat é 100% indetectável. O risco de banimento sempre existe ao usar qualquer tipo de software de terceiros com jogos online.</p>
                                        <p>Recomendamos sempre usar os cheats com cautela e seguir nossas diretrizes de uso para minimizar o risco.</p>
                                    </div>
                                </div>
                                
                                <div class="faq-item">
                                    <div class="faq-question">
                                        <span>O cheat funciona em versão pirata do jogo?</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div class="faq-answer">
                                        <p>Não oferecemos suporte para versões piratas dos jogos. Nossos cheats são desenvolvidos e testados apenas nas versões oficiais adquiridas através de plataformas legítimas.</p>
                                    </div>
                                </div>
                                
                                <div class="faq-item">
                                    <div class="faq-question">
                                        <span>Posso usar mais de um cheat ao mesmo tempo?</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div class="faq-answer">
                                        <p>Não recomendamos usar múltiplos cheats simultaneamente. Isso pode causar conflitos, instabilidade e aumentar significativamente as chances de detecção e banimento.</p>
                                    </div>
                                </div>
                                
                                <div class="faq-item">
                                    <div class="faq-question">
                                        <span>O cheat não está funcionando após atualização do Windows. O que fazer?</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div class="faq-answer">
                                        <p>Atualizações do Windows podem afetar o funcionamento dos cheats. Tente as seguintes soluções:</p>
                                        <ul>
                                            <li>Reinstale o cheat completamente</li>
                                            <li>Reconfigure as exceções do antivírus</li>
                                            <li>Verifique se há atualizações disponíveis para o cheat</li>
                                            <li>Execute o launcher e o jogo como administrador</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php endif; ?>
                    
                    <!-- Seção de Suporte -->
                    <div class="tutorial-card">
                        <h3><i class="fas fa-headset"></i> Precisa de mais ajuda?</h3>
                        <p>Se você continua enfrentando problemas, nossa equipe de suporte está pronta para ajudar.</p>
                        <div class="d-flex flex-wrap gap-3 mt-4">
                            <a href="support.php" class="btn btn-primary">
                                <i class="fas fa-ticket-alt"></i> Abrir Ticket de Suporte
                            </a>
                            <a href="#" class="btn btn-outline-primary">
                                <i class="fab fa-discord"></i> Discord
                            </a>
                            <a href="mailto:suporte@gestaocheats.com" class="btn btn-outline-primary">
                                <i class="fas fa-envelope"></i> E-mail
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Sem assinatura ativa -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <h4 class="alert-heading">Você não possui uma assinatura ativa!</h4>
                        <p>Para acessar tutoriais detalhados e guias de solução de problemas, é necessário ter um plano de assinatura ativo.</p>
                        <a href="purchases.php" class="btn btn-primary mt-2">Ver Planos Disponíveis</a>
                    </div>
                </div>

                <!-- Preview limitado -->
                <div class="tutorial-card">
                    <h3><i class="fas fa-lock"></i> Conteúdo Exclusivo para Assinantes</h3>
                    <p>Os tutoriais completos, vídeos e guias de solução de problemas estão disponíveis apenas para usuários com assinatura ativa.</p>
                    <p>Alguns dos recursos que você terá acesso após assinar:</p>
                    <ul>
                        <li>Vídeos tutoriais passo a passo</li>
                        <li>Guias detalhados de instalação e configuração</li>
                        <li>Soluções para problemas comuns</li>
                        <li>Acesso ao suporte premium</li>
                        <li>Atualizações constantes</li>
                    </ul>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a href="purchases.php" class="btn btn-primary">
                            <i class="fas fa-shopping-cart"></i> Assinar Agora
                        </a>
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle FAQ items
            const faqQuestions = document.querySelectorAll('.faq-question');
            faqQuestions.forEach(question => {
                question.addEventListener('click', () => {
                    const faqItem = question.parentElement;
                    faqItem.classList.toggle('active');
                });
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
    </script>
</body>

</html>