<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perguntas Frequentes - <?php echo SITE_NAME; ?></title>
    <meta name="description" content="Encontre respostas para as dúvidas mais comuns sobre nossos cheats, suporte e assinaturas.">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon/favicon-16x16.png">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Fontes Gaming -->
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;500;600;700&family=Rajdhani:wght@400;500;600;700&family=Orbitron:wght@400;500;600;700;800&family=Audiowide&display=swap" rel="stylesheet">
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/landingpage.css">
    <style>
        /* Estilos específicos para a página FAQ */
        .faq-page {
            padding: var(--spacing-4xl) 0;
            min-height: 70vh;
        }
        
        .faq-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .faq-categories {
            display: flex;
            justify-content: center;
            margin-bottom: var(--spacing-xl);
            gap: var(--spacing-md);
            flex-wrap: wrap;
        }
        
        .category-btn {
            background: var(--card);
            border: 1px solid var(--border);
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--border-radius-md);
            color: var(--text);
            cursor: pointer;
            transition: all var(--transition-normal);
        }
        
        .category-btn:hover, .category-btn.active {
            background: var(--primary);
            color: var(--dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-primary);
        }
        
        .category-btn i {
            margin-right: var(--spacing-xs);
        }
        
        .faq-section {
            margin-bottom: var(--spacing-2xl);
        }
        
        .faq-section h3 {
            margin-bottom: var(--spacing-lg);
            font-size: var(--font-size-xl);
            color: var(--primary);
            padding-bottom: var(--spacing-xs);
            border-bottom: 2px solid var(--primary-alpha-30);
        }
        
        .page-header {
            text-align: center;
            margin-bottom: var(--spacing-2xl);
        }
        
        .page-header h1 {
            font-size: var(--font-size-3xl);
            margin-bottom: var(--spacing-md);
            color: var(--text);
        }
        
        .page-header p {
            font-size: var(--font-size-lg);
            color: var(--text-secondary);
            max-width: 700px;
            margin: 0 auto;
        }
    </style>
</head>

<body>
    <!-- Loading Spinner -->
    <div class="loading">
        <div class="loading-spinner"></div>
    </div>

    <header class="header">
        <div class="container">
            <div class="logo">
                <a href="../index.php"><?php echo SITE_NAME; ?></a>
            </div>

            <nav class="main-nav">
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="../index.php#features">Recursos</a></li>
                    <li><a href="../index.php#plans">Planos</a></li>
                    <li><a href="../index.php#testimonials">Depoimentos</a></li>
                    <li><a href="../index.php#faq">FAQ</a></li>
                </ul>
            </nav>

            <div class="auth-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../dashboard/index.php" class="btn btn-primary">Minha Conta</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline">Login</a>
                    <a href="register.php" class="btn btn-primary">Registrar</a>
                <?php endif; ?>
            </div>

            <div class="mobile-menu-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </header>

    <!-- Menu móvel -->
    <div class="mobile-menu">
        <ul>
            <li><a href="../index.php">Home</a></li>
            <li><a href="../index.php#features">Recursos</a></li>
            <li><a href="../index.php#plans">Planos</a></li>
            <li><a href="../index.php#testimonials">Depoimentos</a></li>
            <li><a href="../index.php#faq">FAQ</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="../dashboard/index.php">Minha Conta</a></li>
            <?php else: ?>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php">Registrar</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- FAQ Page Content -->
    <section class="faq-page">
        <div class="container">
            <div class="page-header">
                <h1>Perguntas Frequentes</h1>
                <p>Encontre respostas para as dúvidas mais comuns sobre nossos cheats, sistema de assinaturas e suporte técnico.</p>
            </div>

            <div class="faq-categories">
                <button class="category-btn active" data-category="all">
                    <i class="fas fa-th-list"></i> Todas
                </button>
                <button class="category-btn" data-category="general">
                    <i class="fas fa-info-circle"></i> Gerais
                </button>
                <button class="category-btn" data-category="security">
                    <i class="fas fa-shield-alt"></i> Segurança
                </button>
                <button class="category-btn" data-category="subscription">
                    <i class="fas fa-tags"></i> Assinaturas
                </button>
                <button class="category-btn" data-category="technical">
                    <i class="fas fa-wrench"></i> Técnico
                </button>
                <button class="category-btn" data-category="support">
                    <i class="fas fa-headset"></i> Suporte
                </button>
            </div>

            <div class="faq-container">
                <!-- Geral -->
                <div class="faq-section" data-category="general">
                    <h3><i class="fas fa-info-circle"></i> Informações Gerais</h3>
                    
                    <div class="faq-list">
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>O que é o <?php echo SITE_NAME; ?>?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p><?php echo SITE_NAME; ?> é uma plataforma premium de cheats e hacks para diversos jogos populares. Oferecemos soluções de alta qualidade, constantemente atualizadas e com suporte técnico dedicado para proporcionar a melhor experiência aos nossos clientes.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Para quais jogos vocês oferecem cheats?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Atualmente oferecemos cheats para vários jogos populares, incluindo CS:GO, Valorant, Free Fire, FiveM, Warzone, Apex Legends, entre outros. Nossa biblioteca de jogos suportados está sempre em expansão. Para a lista completa, confira nossa página de jogos no painel do cliente ou entre em contato com nosso suporte.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>É legal usar cheats em jogos online?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>O uso de cheats geralmente viola os termos de serviço dos jogos online. Nossa plataforma é destinada apenas para fins educacionais e entretenimento pessoal. Recomendamos que você compreenda as políticas do jogo antes de utilizar qualquer software de terceiros. Não nos responsabilizamos por banimentos ou outras penalidades aplicadas por desenvolvedores de jogos.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Segurança -->
                <div class="faq-section" data-category="security">
                    <h3><i class="fas fa-shield-alt"></i> Segurança</h3>
                    
                    <div class="faq-list">
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Os cheats são seguros de usar?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Sim, nossos cheats são desenvolvidos com camadas avançadas de segurança para minimizar o risco de detecção. Atualizamos constantemente para garantir que permaneçam indetectáveis. No entanto, sempre há um risco inerente ao usar qualquer tipo de cheat em jogos online. Recomendamos seguir nossas diretrizes de uso para maior segurança.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Posso ser banido ao usar os cheats?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Embora nossos cheats sejam projetados para serem indetectáveis, não podemos garantir 100% de segurança contra banimentos. Os desenvolvedores de jogos estão constantemente atualizando seus sistemas anti-cheat. Para minimizar riscos, recomendamos usar as configurações recomendadas, atualizar regularmente os cheats e não usar comportamentos óbvios que possam levar a denúncias de outros jogadores.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Os cheats contêm malware ou vírus?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Não. Todos os nossos produtos são desenvolvidos internamente e passam por rigorosos testes de segurança. Não incluímos malware, spyware, ransomware ou qualquer código malicioso em nosso software. Alguns antivírus podem detectar falsos positivos devido à natureza do nosso software, mas garantimos que são 100% seguros para seu computador.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Como proteger minha conta de jogo ao usar cheats?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Para maior proteção, recomendamos: usar os cheats conforme as instruções, não exagerar nas configurações, manter o software sempre atualizado, não mencionar o uso de cheats no chat do jogo, e considerar usar uma conta secundária para testar novos cheats ou recursos. Oferecemos também proteção HWID em planos superiores para maior segurança.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assinaturas -->
                <div class="faq-section" data-category="subscription">
                    <h3><i class="fas fa-tags"></i> Assinaturas</h3>
                    
                    <div class="faq-list">
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Como funcionam os planos de assinatura?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Oferecemos diferentes planos de assinatura: Básico (diário), Premium (semanal) e Ultimate (mensal). Cada plano dá acesso a diferentes níveis de recursos, prioridade de suporte e frequência de atualizações. Todos os planos são recorrentes e serão renovados automaticamente até que você os cancele.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Posso mudar de plano a qualquer momento?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Sim, você pode fazer upgrade do seu plano a qualquer momento. O valor proporcional restante do seu plano atual será automaticamente creditado no novo plano. Para fazer downgrade, você precisará esperar até o fim do período atual de assinatura.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Como cancelar minha assinatura?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Você pode cancelar sua assinatura a qualquer momento através do seu painel do cliente, na seção "Minhas Assinaturas". O cancelamento será efetivado ao final do período atual. Você continuará tendo acesso aos cheats até o último dia do período pago.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Quais métodos de pagamento são aceitos?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Aceitamos cartões de crédito (Visa, Mastercard, American Express), PayPal, PagSeguro, PIX e transferência bancária. Todas as transações são seguras e criptografadas para proteger seus dados financeiros.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Oferecem reembolso?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Avaliamos solicitações de reembolso caso a caso dentro das primeiras 24 horas após a compra, desde que o produto não tenha sido extensivamente utilizado. Para solicitar um reembolso, entre em contato com nosso suporte com o motivo detalhado do seu pedido.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Técnico -->
                <div class="faq-section" data-category="technical">
                    <h3><i class="fas fa-wrench"></i> Questões Técnicas</h3>
                    
                    <div class="faq-list">
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Com que frequência os cheats são atualizados?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>A frequência de atualização depende do seu plano. O plano Ultimate recebe atualizações diárias, o Premium recebe atualizações semanais, e o Básico recebe atualizações mensais. Em caso de grandes atualizações nos jogos que possam afetar nossos cheats, realizamos atualizações emergenciais para todos os planos.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Quais são os requisitos de sistema?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Nossos cheats são otimizados para funcionar em qualquer sistema que consiga rodar o jogo em questão. Requisitos mínimos recomendados: Windows 10 (64 bits), 8GB RAM, processador dual-core, placa de vídeo compatível com DirectX 11 e antivírus desativado durante a execução do cheat.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Meu antivírus detecta o cheat como malware. O que fazer?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Isso é normal e ocorre devido à natureza do nosso software. Para usar os cheats, você precisará adicionar uma exceção no seu antivírus ou desativá-lo temporariamente durante a execução. Nossos cheats não contêm malware, apenas utilizam técnicas que podem ser erroneamente detectadas como suspeitas pelos antivírus.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>O que é proteção HWID e como funciona?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>HWID (Hardware ID) é um identificador único do seu computador. Nossa proteção HWID vincula sua assinatura ao seu hardware para evitar compartilhamento não autorizado. Todos os planos Premium e Ultimate incluem esta proteção. Se precisar usar o cheat em outro computador, você pode solicitar a alteração do HWID no painel do cliente (limitado a uma vez por semana).</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Os cheats funcionam em computadores Mac ou Linux?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Atualmente, nossos cheats são desenvolvidos exclusivamente para Windows. Não garantimos funcionamento em sistemas Mac ou Linux, mesmo com uso de software de virtualização ou Wine.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Suporte -->
                <div class="faq-section" data-category="support">
                    <h3><i class="fas fa-headset"></i> Suporte e Ajuda</h3>
                    
                    <div class="faq-list">
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Como funciona o suporte técnico?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Oferecemos suporte via chat e email. Clientes do plano Básico têm suporte por email com tempo de resposta de até 24h. Clientes Premium recebem prioridade com tempo de resposta de até 6h. Clientes Ultimate têm acesso ao suporte VIP 24/7 com tempo de resposta de até 1h.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Como acessar o suporte técnico?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Você pode acessar nosso suporte técnico através do painel do cliente, na seção "Suporte". Lá você pode criar tickets, verificar o status de tickets existentes e receber atualizações. Clientes com planos Premium e Ultimate também têm acesso ao nosso servidor Discord privado para suporte mais rápido.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Quanto tempo leva para receber resposta do suporte?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>O tempo de resposta varia conforme seu plano: Plano Básico - até 24 horas; Plano Premium - até 6 horas; Plano Ultimate - até 1 hora. Em períodos de alto volume de tickets, esses tempos podem ser ligeiramente estendidos.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Oferecem tutoriais para utilização dos cheats?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Sim, fornecemos tutoriais detalhados para cada um dos nossos cheats. Você pode encontrar vídeos e guias escritos na seção "Tutoriais" do painel do cliente. Além disso, nossa equipe de suporte está sempre disponível para ajudar com dúvidas específicas sobre a configuração e utilização.</p>
                            </div>
                        </div>
                        
                        <div class="faq-item">
                            <div class="faq-question">
                                <h3>Como reportar um bug ou problema com o cheat?</h3>
                                <div class="faq-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="faq-answer">
                                <p>Caso encontre algum bug ou problema, crie um ticket na seção de suporte informando: o nome do cheat, descrição detalhada do problema, passos para reproduzir o erro, capturas de tela ou vídeos (se possível) e informações do seu sistema. Nossa equipe irá analisar e resolver o problema o mais rápido possível.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5">
                <p>Não encontrou o que procurava? Entre em contato com nosso suporte.</p>
                <a href="<?php echo isset($_SESSION['user_id']) ? '../dashboard/support.php' : 'register.php'; ?>" class="btn btn-primary">
                    <i class="fas fa-headset"></i> Contatar Suporte
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <div class="footer-logo">
                        <a href="../index.php"><?php echo SITE_NAME; ?></a>
                    </div>
                    <p>Fornecendo cheats premium de alta qualidade com segurança garantida e suporte 24/7 para os melhores jogos do mercado.</p>
                    <div class="social-icons">
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="Discord"><i class="fab fa-discord"></i></a>
                        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Links Rápidos</h3>
                    <ul class="footer-links">
                        <li><a href="../index.php">Home</a></li>
                        <li><a href="../index.php#features">Recursos</a></li>
                        <li><a href="../index.php#plans">Planos</a></li>
                        <li><a href="../index.php#games">Jogos</a></li>
                        <li><a href="../index.php#testimonials">Depoimentos</a></li>
                        <li><a href="../index.php#faq">FAQ</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Suporte</h3>
                    <ul class="footer-links">
                        <li><a href="login.php">Área do Cliente</a></li>
                        <li><a href="<?php echo isset($_SESSION['user_id']) ? '../dashboard/support.php' : 'register.php'; ?>">Suporte Técnico</a></li>
                        <li><a href="faq.php">Perguntas Frequentes</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contato</h3>
                    <div class="footer-contact">
                        <p><i class="fab fa-whatsapp"></i> (11) 99999-9999</p>
                        <p><i class="fab fa-discord"></i> discord.gg/<?php echo strtolower(SITE_NAME); ?></p>
                        <p><i class="fas fa-clock"></i> Seg-Sex: 9h às 18h</p>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="copyright">
                    &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos os direitos reservados.
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <div class="back-to-top">
        <i class="fas fa-arrow-up"></i>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/loader.js"></script>
    <script>
        // FAQ Accordion Functionality
        document.addEventListener('DOMContentLoaded', function() {
            // FAQ Toggle
            const faqQuestions = document.querySelectorAll('.faq-question');
            faqQuestions.forEach(question => {
                question.addEventListener('click', () => {
                    const faqItem = question.parentElement;
                    faqItem.classList.toggle('active');
                });
            });
            
            // Category Filtering
            const categoryButtons = document.querySelectorAll('.category-btn');
            const faqSections = document.querySelectorAll('.faq-section');
            
            categoryButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const category = button.getAttribute('data-category');
                    
                    // Update active button
                    categoryButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    
                    // Show/hide sections based on category
                    if (category === 'all') {
                        faqSections.forEach(section => {
                            section.style.display = 'block';
                        });
                    } else {
                        faqSections.forEach(section => {
                            if (section.getAttribute('data-category') === category) {
                                section.style.display = 'block';
                            } else {
                                section.style.display = 'none';
                            }
                        });
                    }
                });
            });
            
            // Mobile Menu Toggle
            const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
            const mobileMenu = document.querySelector('.mobile-menu');
            
            mobileMenuToggle.addEventListener('click', () => {
                mobileMenuToggle.classList.toggle('active');
                mobileMenu.classList.toggle('active');
            });
            
            // Back to Top Button
            const backToTopButton = document.querySelector('.back-to-top');
            
            window.addEventListener('scroll', () => {
                if (window.pageYOffset > 300) {
                    backToTopButton.classList.add('show');
                } else {
                    backToTopButton.classList.remove('show');
                }
            });
            
            backToTopButton.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    </script>
</body>
</html>